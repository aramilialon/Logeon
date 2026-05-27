<?php

declare(strict_types=1);

namespace App\Services;

use Core\AuditLogService;
use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Hooks;
use Core\Http\AppError;

class CharacterRankService
{
    private const CONFIG_ENABLED = 'character_rank_enabled';
    private const CONFIG_MANUAL_UPDATE_ENABLED = 'character_rank_manual_update_enabled';
    private const CONFIG_MAX = 'character_rank_max';

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

    private function begin(): void
    {
        $this->db->query('START TRANSACTION');
    }

    private function commit(): void
    {
        $this->db->query('COMMIT');
    }

    private function rollback(): void
    {
        try {
            $this->db->query('ROLLBACK');
        } catch (\Throwable $e) {
            // no-op: rollback best effort
        }
    }

    private function normalizeBool($value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $raw = strtolower(trim((string) $value));
        if (in_array($raw, ['1', 'true', 'yes', 'si', 'on'], true)) {
            return true;
        }
        if (in_array($raw, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return ((int) $value) === 1;
    }

    private function getSysConfigValue(string $key): ?string
    {
        $row = $this->firstPrepared(
            'SELECT `value`
             FROM sys_configs
             WHERE `key` = ?
             LIMIT 1',
            [$key],
        );

        if (empty($row) || !isset($row->value)) {
            return null;
        }

        return trim((string) $row->value);
    }

    public function isEnabled(): bool
    {
        return $this->normalizeBool($this->getSysConfigValue(self::CONFIG_ENABLED), true);
    }

    public function isManualUpdateEnabled(): bool
    {
        return $this->normalizeBool($this->getSysConfigValue(self::CONFIG_MANUAL_UPDATE_ENABLED), true);
    }

    public function getMaxRank(): ?int
    {
        $raw = $this->getSysConfigValue(self::CONFIG_MAX);
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = (int) $raw;
        return ($value > 0) ? $value : null;
    }

    public function getRank(int $characterId): ?int
    {
        if ($characterId <= 0 || !$this->isEnabled()) {
            return null;
        }

        $row = $this->firstPrepared(
            'SELECT `rank`
             FROM characters
             WHERE id = ?
             LIMIT 1',
            [$characterId],
        );

        if (empty($row) || !isset($row->rank)) {
            return null;
        }

        return max(1, (int) $row->rank);
    }

    /**
     * @param array<string,mixed> $metadata
     * @return array<string,mixed>
     */
    public function updateRank(
        int $characterId,
        int $newRank,
        ?int $changedByUserId = null,
        string $reason = 'admin_update',
        array $metadata = [],
    ): array {
        if ($characterId <= 0) {
            throw AppError::validation('Personaggio non valido', [], 'character_invalid');
        }

        if (!$this->isEnabled()) {
            throw AppError::validation('Sistema rank disattivato', [], 'character_rank_disabled');
        }

        if (!$this->isManualUpdateEnabled()) {
            throw AppError::validation('Aggiornamento manuale rank disattivato', [], 'character_rank_manual_update_disabled');
        }

        if ($newRank < 0) {
            throw AppError::validation('Rank non valido', [], 'character_rank_invalid');
        }
        if ($newRank === 0) {
            $newRank = 1;
        }

        $maxRank = $this->getMaxRank();
        if ($maxRank !== null && $newRank > $maxRank) {
            throw AppError::validation('Rank oltre il massimo consentito', [], 'character_rank_max_exceeded');
        }

        $oldRank = null;

        $this->begin();
        try {
            $current = $this->firstPrepared(
                'SELECT id, `rank`
                 FROM characters
                 WHERE id = ?
                 LIMIT 1
                 FOR UPDATE',
                [$characterId],
            );

            if (empty($current)) {
                throw AppError::notFound('Personaggio non trovato', [], 'character_not_found');
            }

            $oldRank = max(1, (int) ($current->rank ?? 1));
            if ($oldRank !== $newRank) {
                $this->execPrepared(
                    'UPDATE characters
                     SET `rank` = ?
                     WHERE id = ?',
                    [$newRank, $characterId],
                );
            }

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }

        $eventPayload = [
            'character_id' => $characterId,
            'old_rank' => $oldRank,
            'new_rank' => $newRank,
            'changed_by_user_id' => ($changedByUserId !== null && $changedByUserId > 0) ? $changedByUserId : null,
            'reason' => trim($reason) !== '' ? trim($reason) : 'admin_update',
            'occurred_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'metadata' => $metadata,
            'changed' => $oldRank !== $newRank,
        ];

        if ($oldRank !== $newRank) {
            AuditLogService::write(
                'character',
                'rank_update',
                $eventPayload,
                'admin',
                null,
                $changedByUserId,
            );

            try {
                Hooks::fire('character.rank.changed', $eventPayload);
            } catch (\Throwable $e) {
                error_log('[character.rank.changed] listener error: ' . $e->getMessage());
            }
        }

        return $eventPayload;
    }
}
