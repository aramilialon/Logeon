<?php

declare(strict_types=1);

namespace App\Contracts;

interface CapabilityRegistryInterface
{
    public function has(string $capability): bool;

    /**
     * @return array<string,bool>
     */
    public function all(): array;
}
