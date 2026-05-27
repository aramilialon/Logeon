<?php

declare(strict_types=1);

namespace Modules\Logeon\Attributes;

use App\Contracts\AttributeProviderInterface;
use Modules\Logeon\Attributes\Services\CharacterAttributesFacadeService;

class AttributesModuleProvider implements AttributeProviderInterface
{
    private CharacterAttributesFacadeService $facade;

    public function __construct(CharacterAttributesFacadeService $facade = null)
    {
        $this->facade = $facade ?: new CharacterAttributesFacadeService();
    }

    public function decorateCharacterDataset(object &$dataset, int $characterId): void
    {
        if (!isset($dataset->id) && $characterId > 0) {
            $dataset->id = $characterId;
        }

        $dataset = $this->facade->decorateCharacterDataset($dataset);
    }

    public function getDefinitions(): array
    {
        $payload = $this->facade->listDefinitions((object) []);
        $rows = $payload['dataset'] ?? [];
        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (is_object($row)) {
                $row = (array) $row;
            }
            if (is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    public function isEnabled(): bool
    {
        return $this->facade->isEnabled();
    }

    public function getValue(int $characterId, string $attributeSlug): ?float
    {
        return $this->facade->getAttributeValue($characterId, $attributeSlug);
    }

    public function getBreakdown(int $characterId, string $attributeSlug): array
    {
        return $this->facade->getAttributeBreakdown($characterId, $attributeSlug);
    }

    public function meetsRequirement(
        int $characterId,
        string $attributeSlug,
        string $operator,
        int|float $requiredValue
    ): bool {
        return $this->facade->meetsRequirement($characterId, $attributeSlug, $operator, $requiredValue);
    }

    public function getAttributeModifier(int $characterId, string $attributeSlug): float
    {
        $value = $this->getValue($characterId, $attributeSlug);
        return $value !== null ? $value : 0.0;
    }
}

