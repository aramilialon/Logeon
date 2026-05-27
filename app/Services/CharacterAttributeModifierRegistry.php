<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CharacterAttributeModifierProviderInterface;
use Core\Hooks;

class CharacterAttributeModifierRegistry
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function collect(int $characterId): array
    {
        if ($characterId <= 0 || !class_exists('\\Core\\Hooks')) {
            return [];
        }

        $modifiers = [];

        $providers = Hooks::filter('character.attributes.modifier.providers', [], $characterId);
        if (is_array($providers)) {
            foreach ($providers as $provider) {
                if (!$provider instanceof CharacterAttributeModifierProviderInterface) {
                    continue;
                }
                if (!$provider->isAvailable()) {
                    continue;
                }

                try {
                    $providerModifiers = $provider->getCharacterAttributeModifiers($characterId);
                    $modifiers = array_merge($modifiers, $providerModifiers);
                } catch (\Throwable $e) {
                    error_log('[character.attributes.modifier.providers] provider error: ' . $e->getMessage());
                }
            }
        }

        $hookModifiers = Hooks::filter('character.attributes.modifiers', [], [
            'character_id' => $characterId,
        ]);
        if (is_array($hookModifiers)) {
            $modifiers = array_merge($modifiers, $hookModifiers);
        }

        $normalized = [];
        foreach ($modifiers as $modifier) {
            if (is_object($modifier)) {
                $modifier = (array) $modifier;
            }
            if (!is_array($modifier)) {
                continue;
            }

            $attributeSlug = trim((string) ($modifier['attribute_slug'] ?? ''));
            $operation = strtolower(trim((string) ($modifier['operation'] ?? 'add')));
            $isActive = array_key_exists('is_active', $modifier) ? ((int) $modifier['is_active'] === 1) : true;
            if ($attributeSlug === '' || !$isActive || $operation !== 'add') {
                continue;
            }

            $normalized[] = [
                'source_system' => trim((string) ($modifier['source_system'] ?? 'unknown')),
                'source_type' => trim((string) ($modifier['source_type'] ?? 'unknown')),
                'source_id' => isset($modifier['source_id']) ? (int) $modifier['source_id'] : 0,
                'source_label' => trim((string) ($modifier['source_label'] ?? '')),
                'attribute_slug' => $attributeSlug,
                'operation' => 'add',
                'value' => round((float) ($modifier['value'] ?? 0), 4),
                'priority' => isset($modifier['priority']) ? (int) $modifier['priority'] : 100,
                'stack_group' => $modifier['stack_group'] ?? null,
                'is_active' => true,
                'metadata' => is_array($modifier['metadata'] ?? null) ? $modifier['metadata'] : [],
            ];
        }

        usort($normalized, static function (array $left, array $right): int {
            $priorityCompare = ((int) $left['priority']) <=> ((int) $right['priority']);
            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return strcmp(
                (string) $left['attribute_slug'],
                (string) $right['attribute_slug'],
            );
        });

        return $normalized;
    }
}
