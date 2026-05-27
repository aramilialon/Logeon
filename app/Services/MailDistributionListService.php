<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;

class MailDistributionListService
{
    private const VALID_TYPES = ['manual', 'dynamic'];
    private const VALID_ORDER_COLS = ['id', 'name', 'type', 'created_at'];
    private const DYNAMIC_SEGMENTS = [
        'all_users', 'newsletter_opt_in', 'staff', 'admin', 'active_characters',
    ];

    private ?DbAdapterInterface $db = null;

    private function db(): DbAdapterInterface
    {
        if ($this->db === null) {
            $this->db = DbAdapterFactory::createFromConfig();
        }
        return $this->db;
    }

    public function list(string $query, int $page, int $results, string $orderBy): array
    {
        $page = max(1, $page);
        $results = max(1, min(100, $results));
        $offset = ($page - 1) * $results;

        [$col, $dir] = $this->parseOrder($orderBy, 'id', 'DESC');

        $where = '';
        $params = [];
        if ($query !== '') {
            $where = 'WHERE name LIKE ?';
            $params[] = '%' . $query . '%';
        }

        $tot = (int) ($this->db()->fetchOnePrepared(
            "SELECT COUNT(*) AS cnt FROM mail_distribution_lists {$where}",
            $params,
        )->cnt ?? 0);

        $rows = $this->db()->fetchAllPrepared(
            "SELECT l.id, l.name, l.description, l.type, l.segment_key, l.created_at,
                    COUNT(m.id) AS member_count
             FROM mail_distribution_lists l
             LEFT JOIN mail_distribution_list_members m ON m.list_id = l.id
             {$where}
             GROUP BY l.id
             ORDER BY l.`{$col}` {$dir}
             LIMIT ? OFFSET ?",
            array_merge($params, [$results, $offset]),
        );

        return [
            'dataset' => $rows ?: [],
            'query' => $query,
            'page' => $page,
            'results_page' => $results,
            'orderBy' => "{$col}|{$dir}",
            'tot' => $tot,
        ];
    }

    public function get(int $id): ?object
    {
        return $this->db()->fetchOnePrepared(
            'SELECT * FROM mail_distribution_lists WHERE id = ? LIMIT 1',
            [$id],
        ) ?: null;
    }

    public function create(string $name, string $description, string $type, ?string $segmentKey): int
    {
        $type = in_array($type, self::VALID_TYPES, true) ? $type : 'manual';
        $segmentKey = ($type === 'dynamic' && $segmentKey !== null && in_array($segmentKey, self::DYNAMIC_SEGMENTS, true))
            ? $segmentKey
            : null;

        $db = $this->db();
        $db->executePrepared(
            'INSERT INTO mail_distribution_lists (name, description, type, segment_key, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())',
            [$name, $description, $type, $segmentKey],
        );
        return (int) $db->lastInsertId();
    }

    public function update(int $id, string $name, string $description, string $type, ?string $segmentKey): void
    {
        $type = in_array($type, self::VALID_TYPES, true) ? $type : 'manual';
        $segmentKey = ($type === 'dynamic' && $segmentKey !== null && in_array($segmentKey, self::DYNAMIC_SEGMENTS, true))
            ? $segmentKey
            : null;

        $this->db()->executePrepared(
            'UPDATE mail_distribution_lists
             SET name = ?, description = ?, type = ?, segment_key = ?, updated_at = NOW()
             WHERE id = ? LIMIT 1',
            [$name, $description, $type, $segmentKey, $id],
        );
    }

    public function delete(int $id): void
    {
        $this->db()->executePrepared('DELETE FROM mail_distribution_list_members WHERE list_id = ?', [$id]);
        $this->db()->executePrepared('DELETE FROM mail_distribution_lists WHERE id = ? LIMIT 1', [$id]);
    }

    public function getMembers(int $listId, int $page, int $results): array
    {
        $page = max(1, $page);
        $results = max(1, min(200, $results));
        $offset = ($page - 1) * $results;

        $tot = (int) ($this->db()->fetchOnePrepared(
            'SELECT COUNT(*) AS cnt FROM mail_distribution_list_members WHERE list_id = ?',
            [$listId],
        )->cnt ?? 0);

        $rows = $this->db()->fetchAllPrepared(
            'SELECT id, list_id, user_id, email, display_name
             FROM mail_distribution_list_members
             WHERE list_id = ?
             ORDER BY id ASC
             LIMIT ? OFFSET ?',
            [$listId, $results, $offset],
        );

        return [
            'dataset' => $rows ?: [],
            'page' => $page,
            'results_page' => $results,
            'tot' => $tot,
        ];
    }

    public function addMember(int $listId, string $email, string $displayName, ?int $userId): void
    {
        $email = strtolower(trim($email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Indirizzo email non valido.');
        }
        $this->db()->executePrepared(
            'INSERT IGNORE INTO mail_distribution_list_members (list_id, user_id, email, display_name)
             VALUES (?, ?, ?, ?)',
            [$listId, $userId, $email, $displayName],
        );
    }

    public function removeMember(int $memberId): void
    {
        $this->db()->executePrepared(
            'DELETE FROM mail_distribution_list_members WHERE id = ? LIMIT 1',
            [$memberId],
        );
    }

    public function searchUsers(string $q, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $cryptKey = defined('DB') ? (string) DB['crypt_key'] : '';
        $emailExpr = 'CAST(AES_DECRYPT(email, ?) AS CHAR(255))';
        $searchNeedle = function_exists('mb_strtolower') ? mb_strtolower($q, 'UTF-8') : strtolower($q);
        $where = 'WHERE u.date_actived IS NOT NULL';
        $params = [$cryptKey];
        if ($q !== '') {
            $where .= " AND (LOWER({$emailExpr}) LIKE ? OR EXISTS (
                SELECT 1
                FROM characters c_search
                WHERE c_search.user_id = u.id
                  AND (c_search.delete_scheduled_at IS NULL OR c_search.delete_scheduled_at > NOW())
                  AND LOWER(CONCAT_WS(' ', IFNULL(c_search.name, ''), IFNULL(c_search.surname, ''))) LIKE ?
            ))";
            $params[] = $cryptKey;
            $params[] = '%' . $searchNeedle . '%';
            $params[] = '%' . $searchNeedle . '%';
        }
        $rows = $this->db()->fetchAllPrepared(
            "SELECT u.id,
                    {$emailExpr} AS email,
                    ch.name AS character_name,
                    ch.surname AS character_surname
             FROM users u
             LEFT JOIN (
                SELECT c.user_id, MIN(c.id) AS character_id
                FROM characters c
                WHERE c.delete_scheduled_at IS NULL OR c.delete_scheduled_at > NOW()
                GROUP BY c.user_id
             ) uc ON uc.user_id = u.id
             LEFT JOIN characters ch ON ch.id = uc.character_id
             {$where}
             ORDER BY ch.name ASC, ch.surname ASC, u.id ASC
             LIMIT ?",
            array_merge($params, [$limit]),
        );
        return array_map(
            static fn ($r) => [
                'id' => (int) $r->id,
                'email' => (string) $r->email,
                'character_name' => trim((string) (($r->character_name ?? '') . ' ' . ($r->character_surname ?? ''))),
            ],
            $rows ?: [],
        );
    }

    public function addMembersBulk(int $listId, array $userIds): int
    {
        if (empty($userIds)) {
            return 0;
        }
        $cryptKey = defined('DB') ? (string) DB['crypt_key'] : '';
        $added = 0;
        foreach ($userIds as $uid) {
            $userId = (int) $uid;
            if ($userId <= 0) {
                continue;
            }
            $user = $this->db()->fetchOnePrepared(
                'SELECT id, CAST(AES_DECRYPT(email, ?) AS CHAR(255)) AS email
                 FROM users WHERE id = ? AND date_actived IS NOT NULL LIMIT 1',
                [$cryptKey, $userId],
            );
            if (!$user) {
                continue;
            }
            $email = strtolower(trim((string) $user->email));
            $this->addMember($listId, $email, $email, $userId);
            $added++;
        }
        return $added;
    }

    public function validSegmentKeys(): array
    {
        return self::DYNAMIC_SEGMENTS;
    }

    private function parseOrder(string $raw, string $defaultCol, string $defaultDir): array
    {
        $parts = explode('|', $raw, 2);
        $col = trim($parts[0]);
        $dir = strtoupper(trim($parts[1] ?? ''));

        if (!in_array($col, self::VALID_ORDER_COLS, true)) {
            $col = $defaultCol;
        }
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            $dir = $defaultDir;
        }
        return [$col, $dir];
    }
}
