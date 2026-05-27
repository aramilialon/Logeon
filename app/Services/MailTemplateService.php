<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Http\AppError;

class MailTemplateService
{
    private const VALID_CATEGORIES = ['transactional', 'system', 'announcement', 'newsletter'];
    private const VALID_ORDER_COLS = ['id', 'name', 'category', 'created_at', 'updated_at'];

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
            $where = 'WHERE name LIKE ? OR description LIKE ?';
            $like = '%' . $query . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $tot = (int) ($this->db()->fetchOnePrepared(
            "SELECT COUNT(*) AS cnt FROM mail_templates {$where}",
            $params,
        )->cnt ?? 0);

        $rows = $this->db()->fetchAllPrepared(
            "SELECT id, name, description, subject, category, created_at, updated_at
             FROM mail_templates {$where}
             ORDER BY `{$col}` {$dir}
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
            'SELECT * FROM mail_templates WHERE id = ? LIMIT 1',
            [$id],
        ) ?: null;
    }

    public function create(string $name, string $description, string $subject, string $bodyHtml, string $category): int
    {
        $bodyText = \App\Services\MailRendererService::htmlToText($bodyHtml);
        $category = in_array($category, self::VALID_CATEGORIES, true) ? $category : 'transactional';
        $this->assertTemplateIsValid($subject, $bodyHtml, $category);

        $db = $this->db();
        $db->executePrepared(
            'INSERT INTO mail_templates (name, description, subject, body_html, body_text, category, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$name, $description, $subject, $bodyHtml, $bodyText, $category],
        );
        return (int) $db->lastInsertId();
    }

    public function update(int $id, string $name, string $description, string $subject, string $bodyHtml, string $category): void
    {
        $bodyText = \App\Services\MailRendererService::htmlToText($bodyHtml);
        $category = in_array($category, self::VALID_CATEGORIES, true) ? $category : 'transactional';
        $this->assertTemplateIsValid($subject, $bodyHtml, $category);

        $this->db()->executePrepared(
            'UPDATE mail_templates
             SET name = ?, description = ?, subject = ?, body_html = ?, body_text = ?, category = ?, updated_at = NOW()
             WHERE id = ? LIMIT 1',
            [$name, $description, $subject, $bodyHtml, $bodyText, $category, $id],
        );
    }

    private function assertTemplateIsValid(string $subject, string $bodyHtml, string $category): void
    {
        $errors = MailRendererService::validateTemplate($subject, $bodyHtml, $category);
        if ($errors === []) {
            return;
        }

        throw AppError::validation($errors[0], ['errors' => $errors], 'mail_template_invalid');
    }

    public function delete(int $id): void
    {
        $this->db()->executePrepared('DELETE FROM mail_templates WHERE id = ? LIMIT 1', [$id]);
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
