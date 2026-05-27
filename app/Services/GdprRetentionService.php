<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;

class GdprRetentionService
{
    /** @var DbAdapterInterface */
    private $db;

    public function __construct(DbAdapterInterface $db = null)
    {
        $this->db = $db ?: DbAdapterFactory::createFromConfig();
    }

    private function firstPrepared(string $sql, array $params = [])
    {
        return $this->db->fetchOnePrepared($sql, $params);
    }

    private function execPrepared(string $sql, array $params = []): void
    {
        $this->db->executePrepared($sql, $params);
    }

    private function tableExists(string $table): bool
    {
        $row = $this->firstPrepared(
            'SELECT 1 AS ok
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
             LIMIT 1',
            [$table],
        );

        return !empty($row);
    }

    private function getConfigString(string $key, string $fallback): string
    {
        if ($this->tableExists('sys_configs')) {
            $row = $this->firstPrepared(
                'SELECT `value`
                 FROM sys_configs
                 WHERE `key` = ?
                 LIMIT 1',
                [$key],
            );
            if (!empty($row) && isset($row->value)) {
                $raw = trim((string) $row->value);
                if ($raw !== '') {
                    return $raw;
                }
            }
        }

        return $fallback;
    }

    private function getConfigInt(string $key, int $fallback, int $min, int $max): int
    {
        $raw = $this->getConfigString($key, (string) $fallback);
        $value = (int) $raw;
        if ($value < $min) {
            $value = $min;
        }
        if ($value > $max) {
            $value = $max;
        }

        return $value;
    }

    private function configFallback(string $key, int $default): int
    {
        if (!defined('APP') || !is_array(APP)) {
            return $default;
        }

        $legal = APP['legal'] ?? null;
        if (!is_array($legal)) {
            return $default;
        }

        $retention = $legal['retention'] ?? null;
        if (!is_array($retention)) {
            return $default;
        }

        if (!array_key_exists($key, $retention)) {
            return $default;
        }

        return (int) $retention[$key];
    }

    private function nowString(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function dateBeforeDays(int $days): string
    {
        if ($days < 1) {
            $days = 1;
        }
        return date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
    }

    private function rowCountAfterLastExec(): int
    {
        $row = $this->firstPrepared('SELECT ROW_COUNT() AS n');
        return (int) ($row->n ?? 0);
    }

    private function executeWithCount(string $sql, array $params = []): int
    {
        $this->execPrepared($sql, $params);
        return $this->rowCountAfterLastExec();
    }

    private function upsertSysConfig(string $key, string $value, string $type = 'string'): void
    {
        if (!$this->tableExists('sys_configs')) {
            return;
        }

        $this->execPrepared(
            'INSERT INTO sys_configs (`key`, `value`, `type`, date_created, date_updated)
             VALUES (?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                `value` = VALUES(`value`),
                `type` = VALUES(`type`),
                date_updated = NOW()',
            [$key, $value, $type],
        );
    }

    private function retentionConfig(): array
    {
        return [
            'interval_minutes' => $this->getConfigInt(
                'gdpr_retention_interval_minutes',
                $this->configFallback('interval_minutes', 720),
                5,
                10080,
            ),
            'cookie_consent_log_days' => $this->getConfigInt(
                'gdpr_retention_cookie_consent_log_days',
                $this->configFallback('cookie_consent_log_days', 365),
                30,
                3650,
            ),
            'mail_consent_log_days' => $this->getConfigInt(
                'gdpr_retention_mail_consent_log_days',
                $this->configFallback('mail_consent_log_days', 1825),
                90,
                3650,
            ),
            'legal_consent_log_days' => $this->getConfigInt(
                'gdpr_retention_legal_consent_log_days',
                $this->configFallback('legal_consent_log_days', 1825),
                90,
                3650,
            ),
            'gdpr_request_payload_days' => $this->getConfigInt(
                'gdpr_retention_request_payload_days',
                $this->configFallback('gdpr_request_payload_days', 365),
                30,
                3650,
            ),
            'gdpr_request_closed_days' => $this->getConfigInt(
                'gdpr_retention_request_closed_days',
                $this->configFallback('gdpr_request_closed_days', 1825),
                90,
                3650,
            ),
        ];
    }

    private function shouldRunNow(int $intervalMinutes): bool
    {
        if (!$this->tableExists('sys_configs')) {
            return true;
        }

        $row = $this->firstPrepared(
            'SELECT `value`
             FROM sys_configs
             WHERE `key` = ?
             LIMIT 1',
            ['gdpr_retention_last_run_at'],
        );
        if (empty($row) || !isset($row->value)) {
            return true;
        }

        $lastTs = strtotime((string) $row->value);
        if ($lastTs === false) {
            return true;
        }

        return (time() - $lastTs) >= ($intervalMinutes * 60);
    }

    public function run(bool $force = false): array
    {
        $config = $this->retentionConfig();
        if (!$force && !$this->shouldRunNow((int) $config['interval_minutes'])) {
            return [
                'skipped' => 'interval',
                'config' => $config,
                'deleted' => [],
                'updated' => [],
                'executed_at' => $this->nowString(),
            ];
        }

        $deleted = [];
        $updated = [];

        if ($this->tableExists('cookie_consent_logs')) {
            $cookieCutoff = $this->dateBeforeDays((int) $config['cookie_consent_log_days']);
            $deleted['cookie_consent_logs'] = $this->executeWithCount(
                'DELETE FROM cookie_consent_logs
                 WHERE created_at < ?',
                [$cookieCutoff],
            );
        } else {
            $deleted['cookie_consent_logs'] = 0;
        }

        if ($this->tableExists('mail_consent_logs')) {
            $mailCutoff = $this->dateBeforeDays((int) $config['mail_consent_log_days']);
            $deleted['mail_consent_logs'] = $this->executeWithCount(
                'DELETE FROM mail_consent_logs
                 WHERE created_at < ?',
                [$mailCutoff],
            );
        } else {
            $deleted['mail_consent_logs'] = 0;
        }

        if ($this->tableExists('legal_consent_logs')) {
            $legalCutoff = $this->dateBeforeDays((int) $config['legal_consent_log_days']);
            $deleted['legal_consent_logs'] = $this->executeWithCount(
                'DELETE FROM legal_consent_logs
                 WHERE created_at < ?',
                [$legalCutoff],
            );
        } else {
            $deleted['legal_consent_logs'] = 0;
        }

        if ($this->tableExists('gdpr_requests')) {
            $payloadCutoff = $this->dateBeforeDays((int) $config['gdpr_request_payload_days']);
            $updated['gdpr_requests_payload_scrubbed'] = $this->executeWithCount(
                'UPDATE gdpr_requests
                 SET payload_json = NULL,
                     updated_at = NOW()
                 WHERE request_type = "export_data"
                   AND payload_json IS NOT NULL
                   AND LENGTH(TRIM(payload_json)) > 0
                   AND status IN ("completed", "rejected", "cancelled")
                   AND updated_at < ?',
                [$payloadCutoff],
            );

            $closedCutoff = $this->dateBeforeDays((int) $config['gdpr_request_closed_days']);
            $deleted['gdpr_requests_closed'] = $this->executeWithCount(
                'DELETE FROM gdpr_requests
                 WHERE status IN ("completed", "rejected", "cancelled")
                   AND updated_at < ?',
                [$closedCutoff],
            );
        } else {
            $updated['gdpr_requests_payload_scrubbed'] = 0;
            $deleted['gdpr_requests_closed'] = 0;
        }

        $executedAt = $this->nowString();
        $this->upsertSysConfig('gdpr_retention_last_run_at', $executedAt, 'datetime');

        return [
            'config' => $config,
            'deleted' => $deleted,
            'updated' => $updated,
            'executed_at' => $executedAt,
        ];
    }
}
