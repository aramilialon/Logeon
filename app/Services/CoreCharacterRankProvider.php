<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CharacterRankProviderInterface;

class CoreCharacterRankProvider implements CharacterRankProviderInterface
{
    private CharacterRankService $service;

    public function __construct(CharacterRankService $service = null)
    {
        $this->service = $service ?: new CharacterRankService();
    }

    public function isEnabled(): bool
    {
        return $this->service->isEnabled();
    }

    public function getRank(int $characterId): ?int
    {
        return $this->service->getRank($characterId);
    }
}
