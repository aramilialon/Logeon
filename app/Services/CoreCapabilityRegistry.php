<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CapabilityRegistryInterface;
use Core\Hooks;

class CoreCapabilityRegistry implements CapabilityRegistryInterface
{
    public function has(string $capability): bool
    {
        $capability = trim($capability);
        if ($capability === '') {
            return false;
        }

        $map = $this->all();
        return !empty($map[$capability]);
    }

    public function all(): array
    {
        $capabilities = [
            'character.rank' => static function (): bool {
                return (new CoreCharacterRankProvider())->isEnabled();
            },
        ];

        if (class_exists('\\Core\\Hooks')) {
            $filtered = Hooks::filter('capability.registry.capabilities', $capabilities);
            if (is_array($filtered)) {
                $capabilities = $filtered;
            }
        }

        $resolved = [];
        foreach ($capabilities as $name => $value) {
            $capability = trim((string) $name);
            if ($capability === '') {
                continue;
            }
            $resolved[$capability] = $this->resolveValue($value);
        }

        ksort($resolved);
        return $resolved;
    }

    private function resolveValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_callable($value)) {
            try {
                return (bool) $value();
            } catch (\Throwable $e) {
                return false;
            }
        }

        return !empty($value);
    }
}
