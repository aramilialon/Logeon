<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AttributeProviderInterface;
use Core\Hooks;

class AttributeProviderRegistry
{
    /** @var AttributeProviderInterface|null */
    private static $provider = null;

    public static function setProvider(AttributeProviderInterface $provider = null): void
    {
        self::$provider = $provider;
    }

    public static function provider(): AttributeProviderInterface
    {
        if (self::$provider instanceof AttributeProviderInterface) {
            return self::$provider;
        }

        $provider = new CoreAttributeProvider();
        $provider = self::resolveWithHooks($provider);
        self::$provider = $provider;
        return self::$provider;
    }

    public static function decorateCharacterDataset(object &$dataset, int $characterId): void
    {
        self::provider()->decorateCharacterDataset($dataset, $characterId);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function definitions(): array
    {
        return self::provider()->getDefinitions();
    }

    public static function isEnabled(): bool
    {
        return self::provider()->isEnabled();
    }

    public static function getValue(int $characterId, string $attributeSlug): ?float
    {
        return self::provider()->getValue($characterId, $attributeSlug);
    }

    /**
     * @return array<string,mixed>
     */
    public static function getBreakdown(int $characterId, string $attributeSlug): array
    {
        return self::provider()->getBreakdown($characterId, $attributeSlug);
    }

    public static function meetsRequirement(
        int $characterId,
        string $attributeSlug,
        string $operator,
        int|float $requiredValue,
    ): bool {
        return self::provider()->meetsRequirement($characterId, $attributeSlug, $operator, $requiredValue);
    }

    public static function getAttributeModifier(int $characterId, string $attributeSlug): float
    {
        return self::provider()->getAttributeModifier($characterId, $attributeSlug);
    }

    public static function resetRuntimeState(): void
    {
        self::$provider = null;
    }

    private static function resolveWithHooks(AttributeProviderInterface $fallback): AttributeProviderInterface
    {
        if (!class_exists('\\Core\\Hooks')) {
            return $fallback;
        }

        $filtered = Hooks::filter('attribute.provider', $fallback);
        if ($filtered instanceof AttributeProviderInterface) {
            return $filtered;
        }

        if (is_string($filtered) && class_exists($filtered)) {
            try {
                $candidate = new $filtered();
                if ($candidate instanceof AttributeProviderInterface) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                return $fallback;
            }
        }

        return $fallback;
    }
}
