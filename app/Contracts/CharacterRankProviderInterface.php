<?php

declare(strict_types=1);

namespace App\Contracts;

interface CharacterRankProviderInterface
{
    public function isEnabled(): bool;

    public function getRank(int $characterId): ?int;
}
