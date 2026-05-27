<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/autoload.php';

use App\Services\MailCampaignService;

$limit = 50;
foreach (($argv ?? []) as $arg) {
    if (preg_match('/^--limit=(\d+)$/', (string) $arg, $matches) === 1) {
        $limit = max(1, min(200, (int) $matches[1]));
    }
}

$service = new MailCampaignService();
$result = $service->queueTick($limit);
$processed = isset($result['processed']) ? (int) $result['processed'] : 0;

fwrite(STDOUT, '[mail-queue-worker] processed=' . $processed . PHP_EOL);
