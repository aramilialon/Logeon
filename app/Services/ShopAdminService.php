<?php

declare(strict_types=1);

namespace App\Services;

use Core\AuditLogService;
use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Http\AppError;

class ShopAdminService
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

    private function fetchPrepared(string $sql, array $params = []): array
    {
        return $this->db->fetchAllPrepared($sql, $params);
    }

    private function execPrepared(string $sql, array $params = []): void
    {
        $this->db->executePrepared($sql, $params);
    }

    private function beginTransaction(): void
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
            // rollback best effort
        }
    }

    private function normalizeOrderBy(string $raw): string
    {
        $allowed = [
            'id' => 's.id',
            's.id' => 's.id',
            'name' => 's.name',
            's.name' => 's.name',
            'type' => 's.type',
            's.type' => 's.type',
            'location_id' => 's.location_id',
            's.location_id' => 's.location_id',
            'is_active' => 's.is_active',
            's.is_active' => 's.is_active',
        ];

        $parts = explode('|', $raw);
        $field = trim($parts[0] ?? '');
        $direction = strtoupper(trim($parts[1] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $column = $allowed[$field] ?? 's.name';

        return $column . ' ' . $direction;
    }

    private function normalizePayload(object $data, bool $requireId = false): array
    {
        $id = (int) ($data->id ?? 0);
        if ($requireId && $id <= 0) {
            throw AppError::validation('Negozio non valido', [], 'shop_invalid');
        }

        $name = trim((string) ($data->name ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw AppError::validation('Nome negozio non valido', [], 'shop_name_invalid');
        }

        $type = strtolower(trim((string) ($data->type ?? 'global')));
        if (!in_array($type, ['global', 'location'], true)) {
            throw AppError::validation('Tipo negozio non valido', [], 'shop_type_invalid');
        }

        $locationId = isset($data->location_id) && $data->location_id !== '' && $data->location_id !== null
            ? (int) $data->location_id
            : null;
        if ($type === 'location' && (!$locationId || $locationId <= 0)) {
            throw AppError::validation('Luogo negozio obbligatorio', [], 'shop_location_required');
        }
        if ($type === 'global') {
            $locationId = null;
        }

        $isActive = (int) ($data->is_active ?? 0) > 0 ? 1 : 0;

        return [
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'location_id' => $locationId,
            'is_active' => $isActive,
        ];
    }

    public function list(object $data): array
    {
        $query = is_object($data->query ?? null) ? $data->query : (object) [];
        $where = [];
        $params = [];

        $name = trim((string) ($query->name ?? ''));
        if ($name !== '') {
            $where[] = 's.name LIKE ?';
            $params[] = '%' . $name . '%';
        }

        $type = strtolower(trim((string) ($query->type ?? '')));
        if (in_array($type, ['global', 'location'], true)) {
            $where[] = 's.type = ?';
            $params[] = $type;
        }

        $locationId = (int) ($query->location_id ?? 0);
        if ($locationId > 0) {
            $where[] = 's.location_id = ?';
            $params[] = $locationId;
        }

        $activeFilter = $query->is_active ?? null;
        if ($activeFilter !== null && $activeFilter !== '') {
            $where[] = 's.is_active = ?';
            $params[] = ((int) $activeFilter === 1) ? 1 : 0;
        }

        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $page = max(1, (int) ($data->page ?? 1));
        $resultsPage = max(5, min(100, (int) ($data->results ?? 20)));
        $offset = ($page - 1) * $resultsPage;
        $orderBy = $this->normalizeOrderBy((string) ($data->orderBy ?? 'name|ASC'));

        $joins = 'FROM shops s
                  LEFT JOIN locations l ON l.id = s.location_id
                  LEFT JOIN maps m ON m.id = l.map_id';

        $countRow = $this->firstPrepared('SELECT COUNT(*) AS count ' . $joins . $whereClause, $params);
        $total = (int) ($countRow->count ?? 0);

        $rows = $this->fetchPrepared(
            'SELECT
                s.id, s.name, s.type, s.location_id, s.is_active,
                l.name AS location_name,
                m.name AS map_name
             ' . $joins . '
             ' . $whereClause . '
             ORDER BY ' . $orderBy . ', s.id ASC
             LIMIT ? OFFSET ?',
            array_merge($params, [$resultsPage, $offset]),
        );

        return [
            'query' => $data,
            'page' => $page,
            'results_page' => $resultsPage,
            'orderBy' => $orderBy,
            'tot' => ['count' => $total],
            'dataset' => $rows,
        ];
    }

    public function create(object $data): void
    {
        $payload = $this->normalizePayload($data);

        $this->execPrepared(
            'INSERT INTO shops (name, type, location_id, is_active)
             VALUES (?, ?, ?, ?)',
            [$payload['name'], $payload['type'], $payload['location_id'], $payload['is_active']],
        );
        AuditLogService::writeEvent('shops.create', ['name' => $payload['name']], 'admin');
    }

    public function update(object $data): void
    {
        $payload = $this->normalizePayload($data, true);

        $this->execPrepared(
            'UPDATE shops
             SET name = ?,
                 type = ?,
                 location_id = ?,
                 is_active = ?
             WHERE id = ?',
            [$payload['name'], $payload['type'], $payload['location_id'], $payload['is_active'], $payload['id']],
        );
        AuditLogService::writeEvent('shops.update', ['id' => $payload['id']], 'admin');
    }

    public function delete(int $id): void
    {
        if ($id <= 0) {
            throw AppError::validation('Negozio non valido', [], 'shop_invalid');
        }

        $shop = $this->firstPrepared('SELECT id, name FROM shops WHERE id = ? LIMIT 1', [$id]);
        if (empty($shop)) {
            throw AppError::notFound('Negozio non trovato', [], 'shop_not_found');
        }

        $this->beginTransaction();
        try {
            // Un negozio puo avere righe operative collegate. Se si cancella solo
            // shop_inventory, database con FK o dati storici reali bloccano la DELETE.
            // L'endpoint admin/delete deve quindi essere una cancellazione hard completa.
            $this->execPrepared('DELETE FROM shop_inventory WHERE shop_id = ?', [$id]);
            $this->execPrepared('DELETE FROM shop_purchases WHERE shop_id = ?', [$id]);
            $this->execPrepared('DELETE FROM shop_sales WHERE shop_id = ?', [$id]);
            $this->execPrepared('DELETE FROM shops WHERE id = ?', [$id]);

            $remaining = $this->firstPrepared('SELECT id FROM shops WHERE id = ? LIMIT 1', [$id]);
            if (!empty($remaining)) {
                throw AppError::validation('Il negozio non e stato eliminato', [], 'shop_delete_failed');
            }

            AuditLogService::writeEvent('shops.delete', ['id' => $id, 'name' => (string) ($shop->name ?? '')], 'admin');
            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}
