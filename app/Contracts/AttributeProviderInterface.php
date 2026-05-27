<?php

declare(strict_types=1);

namespace App\Contracts;

interface AttributeProviderInterface
{
    /**
     * Decora il dataset personaggio con dati attributi.
     */
    public function decorateCharacterDataset(object &$dataset, int $characterId): void;

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getDefinitions(): array;

    public function isEnabled(): bool;

    public function getValue(int $characterId, string $attributeSlug): ?float;

    /**
     * @return array<string,mixed>
     */
    public function getBreakdown(int $characterId, string $attributeSlug): array;

    public function meetsRequirement(
        int $characterId,
        string $attributeSlug,
        string $operator,
        int|float $requiredValue
    ): bool;

    public function getAttributeModifier(int $characterId, string $attributeSlug): float;
}
