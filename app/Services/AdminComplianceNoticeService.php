<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;

class AdminComplianceNoticeService
{
    private const DEFAULT_NOTICE_VERSION = '2026-05-18';
    private const CONFIG_KEY_PREFIX = 'admin_compliance_notice_ack_user_';

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

    private function resolveNoticeVersion(): string
    {
        $fallback = self::DEFAULT_NOTICE_VERSION;
        if (!defined('APP') || !is_array(APP)) {
            return $fallback;
        }

        $legal = (isset(APP['legal']) && is_array(APP['legal'])) ? APP['legal'] : [];
        $value = trim((string) ($legal['instance_compliance_notice_version'] ?? ''));
        return $value !== '' ? $value : $fallback;
    }

    private function configKeyForUser(int $userId): string
    {
        return self::CONFIG_KEY_PREFIX . max(0, $userId);
    }

    /**
     * @return array<string,mixed>
     */
    public function statusForUser(int $userId): array
    {
        $noticeVersion = $this->resolveNoticeVersion();
        $key = $this->configKeyForUser($userId);

        $row = $this->firstPrepared(
            'SELECT `value`, `date_created`, `date_updated`
             FROM sys_configs
             WHERE `key` = ?
             LIMIT 1',
            [$key],
        );

        $storedVersion = '';
        $ackAt = null;
        if (!empty($row)) {
            $storedVersion = trim((string) ($row->value ?? ''));
            $updatedAt = trim((string) ($row->date_updated ?? ''));
            $createdAt = trim((string) ($row->date_created ?? ''));
            $ackAt = $updatedAt !== '' ? $updatedAt : ($createdAt !== '' ? $createdAt : null);
        }

        $acknowledged = ($storedVersion !== '' && $storedVersion === $noticeVersion);

        return [
            'notice_version' => $noticeVersion,
            'acknowledged' => $acknowledged,
            'acknowledged_at' => $ackAt,
            'should_show' => !$acknowledged,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function acknowledgeForUser(int $userId): array
    {
        $noticeVersion = $this->resolveNoticeVersion();
        $key = $this->configKeyForUser($userId);

        $this->execPrepared(
            'INSERT INTO sys_configs (`key`, `value`, `type`)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                `value` = VALUES(`value`),
                `type` = VALUES(`type`),
                `date_updated` = CURRENT_TIMESTAMP',
            [$key, $noticeVersion, 'string'],
        );

        return $this->statusForUser($userId);
    }
}

