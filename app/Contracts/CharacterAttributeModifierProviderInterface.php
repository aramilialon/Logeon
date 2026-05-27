<?php

declare(strict_types=1);

namespace App\Contracts;

interface CharacterAttributeModifierProviderInterface
{
    public function isAvailable(): bool;

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getCharacterAttributeModifiers(int $characterId): array;
}
