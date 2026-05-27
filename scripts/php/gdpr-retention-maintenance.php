<?php

declare(strict_types=1);

/**
 * GDPR retention maintenance runner (CLI).
 *
 * Usage:
 *   C:\xampp\php\php.exe scripts/php/gdpr-retention-maintenance.php
 * Optional:
 *   C:\xampp\php\php.exe scripts/php/gdpr-retention-maintenance.php --force=1
 */

$root = dirname(__DIR__, 2);

$bootstrap = [
    $root . '/configs/config.php',
    $root . '/configs/db.php',
    $root . '/configs/app.php',
    $root . '/vendor/autoload.php',
];

foreach ($bootstrap as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "[FAIL] Missing bootstrap file: {$file}\n");
        exit(1);
    }
    require_once $file;
}

$customBootstrap = $root . '/custom/bootstrap.php';
if (is_file($customBootstrap)) {
    require_once $customBootstrap;
}

use App\Services\GdprRetentionService;

try {
    $force = false;
    foreach ($argv as $arg) {
        if (strpos((string) $arg, '--force=') === 0) {
            $force = ((int) substr((string) $arg, 8) === 1);
        }
    }

    $service = new GdprRetentionService();
    $result = $service->run($force);

    if (($result['skipped'] ?? '') === 'interval') {
        fwrite(STDOUT, '[OK] GDPR retention skipped by interval.' . PHP_EOL);
        exit(0);
    }

    $deleted = is_array($result['deleted'] ?? null) ? $result['deleted'] : [];
    $updated = is_array($result['updated'] ?? null) ? $result['updated'] : [];

    $summary = [
        'cookie=' . (int) ($deleted['cookie_consent_logs'] ?? 0),
        'mail=' . (int) ($deleted['mail_consent_logs'] ?? 0),
        'legal=' . (int) ($deleted['legal_consent_logs'] ?? 0),
        'gdpr_closed=' . (int) ($deleted['gdpr_requests_closed'] ?? 0),
        'payload_scrub=' . (int) ($updated['gdpr_requests_payload_scrubbed'] ?? 0),
    ];

    fwrite(STDOUT, '[OK] GDPR retention completed. ' . implode(', ', $summary) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
