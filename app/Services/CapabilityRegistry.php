<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CapabilityRegistryInterface;
use Core\Hooks;

class CapabilityRegistry
{
    /** @var CapabilityRegistryInterface|null */
    private static $provider = null;

    public static function setProvider(CapabilityRegistryInterface $provider = null): void
    {
        self::$provider = $provider;
    }

    public static function provider(): CapabilityRegistryInterface
    {
        if (self::$provider instanceof CapabilityRegistryInterface) {
            return self::$provider;
        }

        $provider = new CoreCapabilityRegistry();
        $provider = self::resolveWithHooks($provider);
        self::$provider = $provider;
        return self::$provider;
    }

    public static function has(string $capability): bool
    {
        return self::provider()->has($capability);
    }

    /**
     * @return array<string,bool>
     */
    public static function all(): array
    {
        return self::provider()->all();
    }

    public static function resetRuntimeState(): void
    {
        self::$provider = null;
    }

    private static function resolveWithHooks(CapabilityRegistryInterface $fallback): CapabilityRegistryInterface
    {
        if (!class_exists('\\Core\\Hooks')) {
            return $fallback;
        }

        $filtered = Hooks::filter('capability.registry', $fallback);
        if ($filtered instanceof CapabilityRegistryInterface) {
            return $filtered;
        }

        if (is_string($filtered) && class_exists($filtered)) {
            try {
                $candidate = new $filtered();
                if ($candidate instanceof CapabilityRegistryInterface) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                return $fallback;
            }
        }

        return $fallback;
    }
}
