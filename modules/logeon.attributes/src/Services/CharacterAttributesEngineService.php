<?php

declare(strict_types=1);

namespace Modules\Logeon\Attributes\Services;

use App\Services\CharacterAttributeModifierRegistry;
use Core\Hooks;
use Core\Http\AppError;

class CharacterAttributesEngineService extends CharacterAttributesBaseService
{
    /** @var CharacterAttributesDefinitionService */
    private $definitions;
    /** @var CharacterAttributesRuleService */
    private $rules;
    /** @var CharacterAttributesValueService */
    private $values;

    public function __construct(
        \Core\Database\DbAdapterInterface $db = null,
        CharacterAttributesDefinitionService $definitions = null,
        CharacterAttributesRuleService $rules = null,
        CharacterAttributesValueService $values = null,
    ) {
        parent::__construct($db);
        $this->definitions = $definitions ?: new CharacterAttributesDefinitionService($this->db);
        $this->rules = $rules ?: new CharacterAttributesRuleService($this->db, $this->definitions);
        $this->values = $values ?: new CharacterAttributesValueService($this->db);
    }

    public function recomputeCharacter(int $characterId): array
    {
        if ($characterId <= 0) {
            throw AppError::validation('Personaggio non valido', [], 'character_invalid');
        }

        $character = $this->db->fetchOnePrepared(
            'SELECT id, health, health_max
             FROM characters
             WHERE id = ?
             LIMIT 1',
            [$characterId],
        );
        if (empty($character)) {
            throw AppError::notFound('Personaggio non trovato', [], 'character_not_found');
        }

        $this->values->ensureRowsForCharacter($characterId);
        $definitions = $this->definitions->listActiveDefinitions();
        if (empty($definitions)) {
            return [
                'character_id' => $characterId,
                'computed_count' => 0,
                'health_max_synced' => false,
            ];
        }

        $definitionById = [];
        foreach ($definitions as $definition) {
            $definitionById[(int) $definition->id] = $definition;
        }

        $ruleMap = $this->rules->activeRulesMap();
        $valueIndex = $this->values->valuesIndexByAttribute($characterId);
        $computed = [];
        $sources = [];

        $nonDerived = [];
        $derived = [];
        foreach ($definitions as $definition) {
            if ((int) ($definition->is_derived ?? 0) === 1) {
                $derived[] = (int) $definition->id;
            } else {
                $nonDerived[] = (int) $definition->id;
            }
        }

        foreach ($nonDerived as $attributeId) {
            $definition = $definitionById[$attributeId];
            $row = $valueIndex[$attributeId] ?? null;
            $source = '';
            $baseValue = $this->resolveNonDerivedBaseValue($definition, $row, $source);
            $value = $baseValue;
            if (
                $row !== null
                && isset($row->override_value)
                && (int) ($definition->allow_manual_override ?? 0) === 1
            ) {
                $value = (float) $row->override_value;
                $source = 'override';
            }

            $value = $this->clampAndRound(
                (float) $value,
                isset($definition->min_value) ? (float) $definition->min_value : null,
                isset($definition->max_value) ? (float) $definition->max_value : null,
                (string) ($definition->round_mode ?? 'none'),
            );

            $computed[$attributeId] = $value;
            $sources[$attributeId] = $source;
        }

        $order = $this->buildDerivedOrder($derived, $ruleMap);
        foreach ($order as $attributeId) {
            $definition = $definitionById[$attributeId];
            $row = $valueIndex[$attributeId] ?? null;
            $bundle = $ruleMap[$attributeId] ?? null;
            $rule = $bundle['rule'] ?? null;
            $steps = $bundle['steps'] ?? [];

            $value = null;
            $source = 'fallback';
            $fallback = isset($rule->fallback_value)
                ? (float) $rule->fallback_value
                : (isset($definition->fallback_value) ? (float) $definition->fallback_value : 0.0);

            if (!empty($steps)) {
                try {
                    $value = $this->evaluateSteps($steps, $computed, $valueIndex, $definitionById);
                    $source = 'derived';
                } catch (\Throwable $e) {
                    $value = $fallback;
                    $source = 'fallback';
                }
            } else {
                $value = $fallback;
            }

            if (
                $row !== null
                && isset($row->override_value)
                && (int) ($definition->allow_manual_override ?? 0) === 1
            ) {
                $value = (float) $row->override_value;
                $source = 'override';
            }

            $roundMode = (string) ($definition->round_mode ?? 'none');
            if (!empty($rule) && isset($rule->round_mode) && $rule->round_mode !== '') {
                $roundMode = (string) $rule->round_mode;
            }

            $value = $this->clampAndRound(
                (float) $value,
                isset($definition->min_value) ? (float) $definition->min_value : null,
                isset($definition->max_value) ? (float) $definition->max_value : null,
                $roundMode,
            );

            $computed[$attributeId] = $value;
            $sources[$attributeId] = $source;
        }

        $modifierDeltas = [];
        $modifiers = CharacterAttributeModifierRegistry::collect($characterId);
        if ($modifiers !== []) {
            $attributeIdBySlug = [];
            foreach ($definitions as $definition) {
                $slug = trim((string) ($definition->slug ?? ''));
                $id = (int) ($definition->id ?? 0);
                if ($slug !== '' && $id > 0) {
                    $attributeIdBySlug[$slug] = $id;
                }
            }

            foreach ($modifiers as $modifier) {
                $slug = trim((string) ($modifier['attribute_slug'] ?? ''));
                if ($slug === '' || !isset($attributeIdBySlug[$slug])) {
                    continue;
                }

                $attributeId = (int) $attributeIdBySlug[$slug];
                $modifierDeltas[$attributeId] = ($modifierDeltas[$attributeId] ?? 0.0)
                    + (float) ($modifier['value'] ?? 0.0);
            }

            foreach ($modifierDeltas as $attributeId => $delta) {
                if (!isset($computed[$attributeId])) {
                    continue;
                }

                $definition = $definitionById[$attributeId] ?? null;
                if ($definition === null) {
                    continue;
                }

                $computed[$attributeId] = $this->clampAndRound(
                    (float) $computed[$attributeId] + (float) $delta,
                    isset($definition->min_value) ? (float) $definition->min_value : null,
                    isset($definition->max_value) ? (float) $definition->max_value : null,
                    (string) ($definition->round_mode ?? 'none'),
                );
                $sources[$attributeId] = 'external';
            }
        }

        $updatedCount = $this->values->persistEffectiveValues($characterId, $computed, $sources);

        $healthSync = $this->syncHealthMaxFromMappedAttribute($characterId, $computed);
        $changes = $this->detectAttributeChanges($valueIndex, $computed, $sources, $definitions);

        $this->emitAttributesChanged(
            $characterId,
            $changes,
            [
                'computed_count' => $updatedCount,
                'health_max_synced' => $healthSync['synced'],
                'health_max' => $healthSync['health_max'],
            ],
        );

        return [
            'character_id' => $characterId,
            'computed_count' => $updatedCount,
            'health_max_synced' => $healthSync['synced'],
            'health_max' => $healthSync['health_max'],
        ];
    }

    private function resolveNonDerivedBaseValue(object $definition, ?object $row, string &$source): float
    {
        if ($row !== null && isset($row->base_value)) {
            $source = 'base';
            return (float) $row->base_value;
        }

        if (isset($definition->default_value)) {
            $source = 'default';
            return (float) $definition->default_value;
        }

        if (isset($definition->fallback_value)) {
            $source = 'fallback';
            return (float) $definition->fallback_value;
        }

        $source = 'fallback';
        return 0.0;
    }

    private function evaluateSteps(array $steps, array $computed, array $valueIndex, array $definitionById): float
    {
        $accumulator = 0.0;
        $stepNumber = 0;
        foreach ($steps as $step) {
            $stepNumber++;
            $operator = strtolower(trim((string) ($step->operator_code ?? 'set')));
            $operandType = strtolower(trim((string) ($step->operand_type ?? 'value')));
            $operand = 0.0;

            if ($operandType === 'attribute') {
                $operandAttributeId = (int) ($step->operand_attribute_id ?? 0);
                if ($operandAttributeId <= 0) {
                    throw AppError::validation('Step regola non valido', [], 'attribute_rule_step_invalid');
                }

                if (array_key_exists($operandAttributeId, $computed)) {
                    $operand = (float) $computed[$operandAttributeId];
                } else {
                    $row = $valueIndex[$operandAttributeId] ?? null;
                    if ($row !== null && isset($row->effective_value)) {
                        $operand = (float) $row->effective_value;
                    } else {
                        $definition = $definitionById[$operandAttributeId] ?? null;
                        if ($definition !== null && isset($definition->default_value)) {
                            $operand = (float) $definition->default_value;
                        } elseif ($definition !== null && isset($definition->fallback_value)) {
                            $operand = (float) $definition->fallback_value;
                        } else {
                            $operand = 0.0;
                        }
                    }
                }
            } else {
                $operand = (float) ($step->operand_value ?? 0);
            }

            if ($stepNumber === 1 && $operator !== 'set') {
                throw AppError::validation('Primo step non valido', [], 'attribute_rule_step_invalid');
            }

            if ($operator === 'set') {
                $accumulator = $operand;
            } elseif ($operator === 'add') {
                $accumulator += $operand;
            } elseif ($operator === 'sub') {
                $accumulator -= $operand;
            } elseif ($operator === 'mul') {
                $accumulator *= $operand;
            } elseif ($operator === 'div') {
                if (abs($operand) < 0.0000001) {
                    throw AppError::validation('Divisione per zero non valida', [], 'attribute_rule_step_invalid');
                }
                $accumulator /= $operand;
            } elseif ($operator === 'min') {
                $accumulator = min($accumulator, $operand);
            } elseif ($operator === 'max') {
                $accumulator = max($accumulator, $operand);
            } else {
                throw AppError::validation('Operatore non valido', [], 'attribute_rule_step_invalid');
            }
        }

        return round((float) $accumulator, 4);
    }

    private function detectAttributeChanges(array $valueIndex, array $computed, array $sources, array $definitions): array
    {
        $changes = [];

        foreach ($computed as $attributeId => $newValue) {
            $attributeId = (int) $attributeId;
            if ($attributeId <= 0) {
                continue;
            }

            $row = $valueIndex[$attributeId] ?? null;
            $oldValue = ($row !== null && isset($row->effective_value))
                ? (float) $row->effective_value
                : null;
            $oldSource = ($row !== null && isset($row->value_source))
                ? trim((string) $row->value_source)
                : '';
            $newSource = isset($sources[$attributeId]) ? trim((string) $sources[$attributeId]) : 'derived';

            if ($oldValue !== null && abs($oldValue - (float) $newValue) < 0.0001 && $oldSource === $newSource) {
                continue;
            }

            $definition = $definitions[$attributeId] ?? null;
            $changes[] = [
                'attribute_id' => $attributeId,
                'attribute_slug' => trim((string) ($definition->slug ?? '')),
                'attribute_name' => trim((string) ($definition->name ?? '')),
                'old_value' => $oldValue,
                'new_value' => (float) $newValue,
                'old_source' => $oldSource !== '' ? $oldSource : null,
                'new_source' => $newSource,
            ];
        }

        return $changes;
    }

    private function emitAttributesChanged(int $characterId, array $changes, array $metadata = []): void
    {
        if ($characterId <= 0 || $changes === [] || !class_exists('\\Core\\Hooks')) {
            return;
        }

        $attributeIds = [];
        $attributeSlugs = [];
        foreach ($changes as $change) {
            $attributeId = (int) ($change['attribute_id'] ?? 0);
            $attributeSlug = trim((string) ($change['attribute_slug'] ?? ''));
            if ($attributeId > 0) {
                $attributeIds[] = $attributeId;
            }
            if ($attributeSlug !== '') {
                $attributeSlugs[] = $attributeSlug;
            }
        }

        try {
            Hooks::fire('character.attributes.changed', [
                'character_id' => $characterId,
                'changed_attribute_ids' => array_values(array_unique($attributeIds)),
                'changed_attribute_slugs' => array_values(array_unique($attributeSlugs)),
                'reason' => 'recompute',
                'occurred_at' => date(DATE_ATOM),
                'metadata' => array_merge($metadata, [
                    'changes' => $changes,
                ]),
            ]);
        } catch (\Throwable $e) {
            error_log('[character.attributes.changed] listener error: ' . $e->getMessage());
        }
    }

    private function buildDerivedOrder(array $derivedIds, array $ruleMap): array
    {
        if (empty($derivedIds)) {
            return [];
        }

        $set = [];
        foreach ($derivedIds as $id) {
            $set[(int) $id] = true;
        }

        $graph = [];
        foreach ($derivedIds as $id) {
            $id = (int) $id;
            $graph[$id] = [];
            $bundle = $ruleMap[$id] ?? null;
            if (empty($bundle) || empty($bundle['steps'])) {
                continue;
            }

            foreach ($bundle['steps'] as $step) {
                if (strtolower(trim((string) ($step->operand_type ?? ''))) !== 'attribute') {
                    continue;
                }
                $dependencyId = (int) ($step->operand_attribute_id ?? 0);
                if ($dependencyId <= 0 || !isset($set[$dependencyId])) {
                    continue;
                }
                $graph[$id][] = $dependencyId;
            }
        }

        $state = [];
        $ordered = [];
        $visit = function ($node) use (&$visit, &$state, &$graph, &$ordered) {
            $nodeState = $state[$node] ?? 0;
            if ($nodeState === 1) {
                throw AppError::validation(
                    'Ricalcolo attributi fallito: dipendenze cicliche',
                    [],
                    'attribute_recompute_failed',
                );
            }
            if ($nodeState === 2) {
                return;
            }

            $state[$node] = 1;
            foreach ($graph[$node] as $next) {
                $visit($next);
            }
            $state[$node] = 2;
            $ordered[] = $node;
        };

        foreach (array_keys($graph) as $node) {
            $visit($node);
        }

        return array_values(array_unique($ordered));
    }

    private function syncHealthMaxFromMappedAttribute(int $characterId, array $computed): array
    {
        $mapped = $this->definitions->mappedHealthDefinition();
        if (empty($mapped)) {
            return [
                'synced' => false,
                'health_max' => null,
            ];
        }

        $attributeId = (int) ($mapped->id ?? 0);
        if ($attributeId <= 0) {
            return [
                'synced' => false,
                'health_max' => null,
            ];
        }

        $value = isset($computed[$attributeId]) ? (float) $computed[$attributeId] : null;
        if ($value === null || $value <= 0) {
            $fallback = isset($mapped->fallback_value) ? (float) $mapped->fallback_value : 100.0;
            $value = ($fallback > 0) ? $fallback : 100.0;
        }

        $min = isset($mapped->min_value) ? (float) $mapped->min_value : null;
        $max = isset($mapped->max_value) ? (float) $mapped->max_value : null;
        $value = $this->clampAndRound($value, $min, $max, (string) ($mapped->round_mode ?? 'none'));
        if ($value <= 0) {
            $value = 100.0;
        }

        $this->db->executePrepared(
            'UPDATE characters SET
                health_max = ?,
                health = LEAST(GREATEST(IFNULL(health, 0), 0), ?)
             WHERE id = ?',
            [$value, $value, $characterId],
        );

        return [
            'synced' => true,
            'health_max' => round($value, 2),
        ];
    }
}


