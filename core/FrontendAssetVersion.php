<?php

declare(strict_types=1);

namespace Core;

final class FrontendAssetVersion
{
    /** @var string|null */
    private static $resolved = null;

    /**
     * @param array<string,mixed>|null $appConfig
     */
    public static function resolve(array $appConfig = null): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $appConfig = is_array($appConfig) ? $appConfig : (defined('APP') ? APP : []);
        $manual = self::manualOverride($appConfig);
        if ($manual !== '') {
            self::$resolved = $manual;
            return self::$resolved;
        }

        $mtime = self::latestFrontendMtime();
        self::$resolved = $mtime > 0 ? gmdate('YmdHis', $mtime) : gmdate('YmdHis');

        return self::$resolved;
    }

    /**
     * @param array<string,mixed> $appConfig
     */
    private static function manualOverride(array $appConfig): string
    {
        $frontend = is_array($appConfig['frontend'] ?? null) ? $appConfig['frontend'] : [];
        $value = trim((string) ($frontend['pilot_bundle_version'] ?? ''));
        if ($value === '' || strtolower($value) === 'auto') {
            return '';
        }

        return $value;
    }

    private static function latestFrontendMtime(): int
    {
        $root = dirname(__DIR__);
        $targets = [
            $root . '/assets/css/admin.css',
            $root . '/assets/css/framework.css',
            $root . '/assets/css/style.css',
            $root . '/assets/css/custom.css',
            $root . '/assets/js/dist',
            $root . '/modules',
        ];

        $latest = 0;
        foreach ($targets as $target) {
            $latest = max($latest, self::targetMtime($target));
        }

        return $latest;
    }

    private static function targetMtime(string $path): int
    {
        if (!file_exists($path)) {
            return 0;
        }

        if (is_file($path)) {
            $mtime = @filemtime($path);
            return $mtime !== false ? (int) $mtime : 0;
        }

        $latest = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }

            $pathname = str_replace('\\', '/', $item->getPathname());
            if (!self::isRelevantAsset($pathname)) {
                continue;
            }

            $latest = max($latest, (int) $item->getMTime());
        }

        return $latest;
    }

    private static function isRelevantAsset(string $pathname): bool
    {
        if (substr($pathname, -4) === '.map') {
            return false;
        }

        if (strpos($pathname, '/assets/js/dist/') !== false) {
            return preg_match('/\.(?:js|mjs)$/', $pathname) === 1;
        }

        if (preg_match('#/modules/[^/]+/dist/#', $pathname) === 1) {
            return preg_match('/\.(?:js|mjs)$/', $pathname) === 1;
        }

        return preg_match('/\.(?:css|js|mjs)$/', $pathname) === 1;
    }
}
