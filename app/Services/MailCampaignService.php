<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Http\AppError;

class MailCampaignService
{
    private const VALID_CATEGORIES = ['transactional', 'system', 'announcement', 'newsletter'];
    private const VALID_ORDER_COLS = ['id', 'subject', 'category', 'status', 'scheduled_at', 'created_at', 'updated_at'];
    private const VALID_STATUSES = ['draft', 'scheduled', 'queued', 'sending', 'sent', 'failed', 'cancelled'];
    private const DELETABLE_STATUSES = ['draft', 'scheduled', 'cancelled', 'failed'];
    private const SENDABLE_STATUSES = ['draft', 'scheduled'];
    private const SCHEDULABLE_STATUSES = ['draft', 'scheduled'];
    private const CANCELLABLE_STATUSES = ['scheduled', 'queued'];
    private const EDITABLE_STATUSES = ['draft'];

    private ?DbAdapterInterface $db = null;

    private function db(): DbAdapterInterface
    {
        if ($this->db === null) {
            $this->db = DbAdapterFactory::createFromConfig();
        }
        return $this->db;
    }

    public function list(string $query, string $statusFilter, int $page, int $results, string $orderBy): array
    {
        $page = max(1, $page);
        $results = max(1, min(100, $results));
        $offset = ($page - 1) * $results;

        [$col, $dir] = $this->parseOrder($orderBy, 'id', 'DESC');

        $conditions = [];
        $params = [];

        if ($query !== '') {
            $conditions[] = 'subject LIKE ?';
            $params[] = '%' . $query . '%';
        }
        if ($statusFilter !== '' && in_array($statusFilter, self::VALID_STATUSES, true)) {
            $conditions[] = 'status = ?';
            $params[] = $statusFilter;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $tot = (int) ($this->db()->fetchOnePrepared(
            "SELECT COUNT(*) AS cnt FROM mail_messages {$where}",
            $params,
        )->cnt ?? 0);

        $rows = $this->db()->fetchAllPrepared(
            "SELECT id, subject, category, status, distribution_list_id,
                    total_recipients, sent_count, failed_count,
                    scheduled_at, sent_at, created_at, updated_at
             FROM mail_messages {$where}
             ORDER BY `{$col}` {$dir}
             LIMIT ? OFFSET ?",
            array_merge($params, [$results, $offset]),
        );

        return [
            'dataset' => $rows ?: [],
            'query' => $query,
            'status' => $statusFilter,
            'page' => $page,
            'results_page' => $results,
            'orderBy' => "{$col}|{$dir}",
            'tot' => $tot,
        ];
    }

    public function get(int $id): ?object
    {
        $row = $this->db()->fetchOnePrepared(
            'SELECT * FROM mail_messages WHERE id = ?',
            [$id],
        );
        return $row ?: null;
    }

    public function create(
        string  $subject,
        string  $bodyHtml,
        string  $bodyText,
        ?int    $templateId,
        ?int    $listId,
        string  $category,
        int     $ownerUserId,
    ): int {
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            $category = 'announcement';
        }
        $this->assertMessageTemplateValid($subject, $bodyHtml, $category);
        if ($bodyText === '') {
            $bodyText = MailRendererService::htmlToText($bodyHtml);
        }

        $this->db()->executePrepared(
            'INSERT INTO mail_messages
                (owner_user_id, template_id, distribution_list_id, subject, body_html, body_text, category, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'draft\', NOW(), NOW())',
            [$ownerUserId ?: null, $templateId ?: null, $listId ?: null, $subject, $bodyHtml, $bodyText, $category],
        );

        return (int) $this->db()->lastInsertId();
    }

    public function update(
        int     $id,
        string  $subject,
        string  $bodyHtml,
        string  $bodyText,
        ?int    $templateId,
        ?int    $listId,
        string  $category,
    ): void {
        $msg = $this->get($id);
        if (!$msg) {
            throw AppError::validation('Campagna non trovata.', [], 'not_found');
        }
        if (!in_array((string) $msg->status, self::EDITABLE_STATUSES, true)) {
            throw AppError::validation('Solo le bozze possono essere modificate.', [], 'not_editable');
        }
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            $category = 'announcement';
        }
        $this->assertMessageTemplateValid($subject, $bodyHtml, $category);
        if ($bodyText === '') {
            $bodyText = MailRendererService::htmlToText($bodyHtml);
        }

        $this->db()->executePrepared(
            'UPDATE mail_messages
             SET subject=?, body_html=?, body_text=?, template_id=?, distribution_list_id=?, category=?, updated_at=NOW()
             WHERE id=?',
            [$subject, $bodyHtml, $bodyText, $templateId ?: null, $listId ?: null, $category, $id],
        );
    }

    public function delete(int $id): void
    {
        $msg = $this->get($id);
        if (!$msg) {
            throw AppError::validation('Campagna non trovata.', [], 'not_found');
        }
        if (!in_array((string) $msg->status, self::DELETABLE_STATUSES, true)) {
            throw AppError::validation('Campagna non eliminabile in questo stato.', [], 'not_deletable');
        }

        $this->db()->executePrepared('DELETE FROM mail_queue WHERE message_id = ?', [$id]);
        $this->db()->executePrepared('DELETE FROM mail_recipients WHERE message_id = ?', [$id]);
        $this->db()->executePrepared('DELETE FROM mail_messages WHERE id = ?', [$id]);
    }

    public function schedule(int $id, string $scheduledAt): void
    {
        $msg = $this->get($id);
        if (!$msg) {
            throw AppError::validation('Campagna non trovata.', [], 'not_found');
        }
        if (!in_array((string) $msg->status, self::SCHEDULABLE_STATUSES, true)) {
            throw AppError::validation('La campagna non puÃ² essere pianificata in questo stato.', [], 'not_schedulable');
        }
        $this->assertMessageTemplateValid((string) $msg->subject, (string) $msg->body_html, (string) $msg->category);

        $ts = strtotime($scheduledAt);
        if ($ts === false || $ts <= 0) {
            throw AppError::validation('Data di pianificazione non valida.', [], 'invalid_date');
        }

        $this->db()->executePrepared(
            "UPDATE mail_messages SET status='scheduled', scheduled_at=?, updated_at=NOW() WHERE id=?",
            [date('Y-m-d H:i:s', $ts), $id],
        );
    }

    public function sendNow(int $id): int
    {
        $msg = $this->get($id);
        if (!$msg) {
            throw AppError::validation('Campagna non trovata.', [], 'not_found');
        }
        if (!in_array((string) $msg->status, self::SENDABLE_STATUSES, true)) {
            throw AppError::validation('La campagna non Ã¨ in uno stato inviabile.', [], 'not_sendable');
        }
        $this->assertMessageTemplateValid((string) $msg->subject, (string) $msg->body_html, (string) $msg->category);

        $recipients = $this->resolveRecipients($msg->distribution_list_id ? (int) $msg->distribution_list_id : null);
        $recipients = $this->snapshotRecipients($id, $recipients, (string) $msg->category);
        if (empty($recipients)) {
            throw AppError::validation('Nessun destinatario trovato nella lista selezionata.', [], 'no_recipients');
        }

        $n = count($recipients);

        $this->db()->executePrepared('DELETE FROM mail_queue WHERE message_id = ?', [$id]);
        $this->db()->executePrepared(
            "UPDATE mail_messages SET status='queued', total_recipients=?, sent_count=0, failed_count=0, updated_at=NOW() WHERE id=?",
            [$n, $id],
        );

        MailQueueService::enqueue($id, $recipients);

        return $n;
    }

    public function cancel(int $id): void
    {
        $msg = $this->get($id);
        if (!$msg) {
            throw AppError::validation('Campagna non trovata.', [], 'not_found');
        }
        if (!in_array((string) $msg->status, self::CANCELLABLE_STATUSES, true)) {
            throw AppError::validation('La campagna non puÃ² essere annullata in questo stato.', [], 'not_cancellable');
        }

        $this->db()->executePrepared(
            "UPDATE mail_queue
             SET status='failed', last_error='Campagna annullata dall\'amministratore.', locked_at=NULL, locked_by=NULL
             WHERE message_id=? AND status IN ('pending','sending')",
            [$id],
        );
        $this->db()->executePrepared(
            "UPDATE mail_recipients
             SET status='failed', failed_at=NOW(), last_error='Campagna annullata dall\'amministratore.'
             WHERE message_id=? AND status='pending'",
            [$id],
        );
        $this->db()->executePrepared(
            "UPDATE mail_messages SET status='cancelled', updated_at=NOW() WHERE id=?",
            [$id],
        );
    }

    public function sendTest(int $id, string $testEmail, string $testName = 'Test'): void
    {
        $msg = $this->get($id);
        if (!$msg) {
            throw AppError::validation('Campagna non trovata.', [], 'not_found');
        }
        $this->assertMessageTemplateValid((string) $msg->subject, (string) $msg->body_html, (string) $msg->category);

        $baseUrl = defined('APP') ? rtrim((string) constant('APP')['baseurl'], '/') : '';
        $vars = MailRendererService::buildVars(
            'test_user',
            $testEmail,
            'Personaggio',
            'Personaggio di Prova',
            [
                'unsubscribe_url' => $baseUrl . '/game/settings',
                'preferences_url' => $baseUrl . '/game/settings',
            ],
        );
        $subject = MailRendererService::renderSubject((string) $msg->subject, $vars);
        $bodyHtml = MailRendererService::renderHtml((string) $msg->body_html, $vars);
        $bodyText = MailRendererService::htmlToText($bodyHtml);

        $mailService = new MailService();
        $mailService->send($testEmail, $subject, $bodyHtml, $bodyText, $testName, (string) $msg->category);
    }

    public function queueTick(int $limit = 5): array
    {
        $this->processScheduled();
        $processed = MailQueueService::tick($limit);
        return ['processed' => $processed];
    }

    public function processScheduled(): void
    {
        $dueMessages = $this->db()->fetchAllPrepared(
            "SELECT id FROM mail_messages WHERE status='scheduled' AND scheduled_at <= NOW()",
            [],
        );

        if (empty($dueMessages)) {
            return;
        }

        foreach ($dueMessages as $msg) {
            try {
                $this->sendNow((int) $msg->id);
            } catch (\Throwable) {
                $this->db()->executePrepared(
                    "UPDATE mail_messages SET status='failed', updated_at=NOW() WHERE id=?",
                    [$msg->id],
                );
            }
        }
    }

    private function resolveRecipients(?int $listId): array
    {
        if ($listId === null || $listId <= 0) {
            return [];
        }

        $list = $this->db()->fetchOnePrepared(
            'SELECT id, type, segment_key FROM mail_distribution_lists WHERE id = ?',
            [$listId],
        );
        if (!$list) {
            return [];
        }

        if ((string) $list->type === 'manual') {
            $rows = $this->db()->fetchAllPrepared(
                'SELECT user_id, email, display_name FROM mail_distribution_list_members WHERE list_id = ?',
                [$listId],
            );
            return array_map(
                static fn ($r) => [
                    'user_id' => isset($r->user_id) ? (int) $r->user_id : null,
                    'email' => (string) $r->email,
                    'name' => (string) ($r->display_name ?? ''),
                ],
                $rows ?: [],
            );
        }

        return $this->resolveDynamicSegment((string) ($list->segment_key ?? ''));
    }

    private function resolveDynamicSegment(string $segmentKey): array
    {
        $cryptKey = defined('DB') ? (string) DB['crypt_key'] : '';

        switch ($segmentKey) {
            case 'all_users':
                $rows = $this->db()->fetchAllPrepared(
                    'SELECT id AS user_id, CAST(AES_DECRYPT(email, ?) AS CHAR(255)) AS email, username AS name
                     FROM users
                     WHERE date_actived IS NOT NULL',
                    [$cryptKey],
                );
                break;
            case 'newsletter_opt_in':
                $rows = $this->db()->fetchAllPrepared(
                    'SELECT u.id AS user_id, CAST(AES_DECRYPT(u.email, ?) AS CHAR(255)) AS email, u.username AS name
                     FROM users u
                     INNER JOIN user_mail_preferences p ON p.user_id = u.id
                     WHERE p.newsletter_opt_in = 1 AND u.date_actived IS NOT NULL',
                    [$cryptKey],
                );
                break;
            case 'staff':
                $rows = $this->db()->fetchAllPrepared(
                    'SELECT id AS user_id, CAST(AES_DECRYPT(email, ?) AS CHAR(255)) AS email, username AS name
                     FROM users
                     WHERE (is_master = 1 OR is_moderator = 1 OR is_administrator = 1)
                       AND date_actived IS NOT NULL',
                    [$cryptKey],
                );
                break;
            case 'admin':
                $rows = $this->db()->fetchAllPrepared(
                    'SELECT id AS user_id, CAST(AES_DECRYPT(email, ?) AS CHAR(255)) AS email, username AS name
                     FROM users
                     WHERE is_superuser = 1 AND date_actived IS NOT NULL',
                    [$cryptKey],
                );
                break;
            case 'active_characters':
                $rows = $this->db()->fetchAllPrepared(
                    'SELECT DISTINCT u.id AS user_id, CAST(AES_DECRYPT(u.email, ?) AS CHAR(255)) AS email, u.username AS name
                     FROM users u
                     INNER JOIN characters c ON c.user_id = u.id
                     WHERE c.delete_requested_at IS NULL AND u.date_actived IS NOT NULL',
                    [$cryptKey],
                );
                break;
            default:
                return [];
        }

        return array_map(
            static fn ($r) => [
                'user_id' => isset($r->user_id) ? (int) $r->user_id : null,
                'email' => (string) $r->email,
                'name' => (string) ($r->name ?? ''),
            ],
            $rows ?: [],
        );
    }

    public function queueList(?int $messageId, string $statusFilter, int $page, int $results, string $orderBy): array
    {
        $validQueueStatuses = ['pending', 'sending', 'sent', 'failed'];
        $validQueueCols = ['id', 'message_id', 'recipient_email', 'status', 'attempts', 'sent_at', 'created_at'];

        $page = max(1, $page);
        $results = max(1, min(100, $results));
        $offset = ($page - 1) * $results;

        [$col, $dir] = $this->parseOrder($orderBy, 'id', 'DESC', $validQueueCols);

        $conditions = [];
        $params = [];

        if ($messageId !== null && $messageId > 0) {
            $conditions[] = 'q.message_id = ?';
            $params[] = $messageId;
        }
        if ($statusFilter !== '' && in_array($statusFilter, $validQueueStatuses, true)) {
            $conditions[] = 'q.status = ?';
            $params[] = $statusFilter;
        }

        $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $tot = (int) ($this->db()->fetchOnePrepared(
            "SELECT COUNT(*) AS cnt FROM mail_queue q {$where}",
            $params,
        )->cnt ?? 0);

        $rows = $this->db()->fetchAllPrepared(
            "SELECT q.id, q.message_id, m.subject AS message_subject,
                    q.recipient_email, q.recipient_name, q.status,
                    q.attempts, q.last_error, q.sent_at, q.created_at,
                    q.bounce_status, q.bounce_reason
             FROM mail_queue q
             LEFT JOIN mail_messages m ON m.id = q.message_id
             {$where}
             ORDER BY q.`{$col}` {$dir}
             LIMIT ? OFFSET ?",
            array_merge($params, [$results, $offset]),
        );

        return [
            'dataset' => $rows ?: [],
            'message_id' => $messageId,
            'status' => $statusFilter,
            'page' => $page,
            'results_page' => $results,
            'orderBy' => "{$col}|{$dir}",
            'tot' => $tot,
        ];
    }

    private function parseOrder(string $orderBy, string $defaultCol, string $defaultDir, ?array $validCols = null): array
    {
        $allowedCols = $validCols ?? self::VALID_ORDER_COLS;
        $parts = explode('|', $orderBy, 2);
        $col = $parts[0];
        $dir = strtoupper($parts[1] ?? $defaultDir);

        if (!in_array($col, $allowedCols, true)) {
            $col = $defaultCol;
        }
        if ($dir !== 'ASC') {
            $dir = 'DESC';
        }
        return [$col, $dir];
    }

    private function assertMessageTemplateValid(string $subject, string $bodyHtml, string $category): void
    {
        $errors = MailRendererService::validateTemplate($subject, $bodyHtml, $category);
        if ($errors === []) {
            return;
        }

        throw AppError::validation($errors[0], ['errors' => $errors], 'mail_message_invalid');
    }

    private function snapshotRecipients(int $messageId, array $recipients, string $category): array
    {
        $snapshots = [];
        $seenEmails = [];

        $this->db()->executePrepared('DELETE FROM mail_recipients WHERE message_id = ?', [$messageId]);

        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            if (isset($seenEmails[$email])) {
                continue;
            }
            $seenEmails[$email] = true;

            $userId = isset($recipient['user_id']) && (int) $recipient['user_id'] > 0
                ? (int) $recipient['user_id']
                : null;
            $consentSnapshot = $category === 'newsletter'
                ? ($userId !== null ? ($this->newsletterConsentSnapshot($userId) ? 1 : 0) : 0)
                : 1;

            if ($category === 'newsletter' && $consentSnapshot !== 1) {
                continue;
            }

            $this->db()->executePrepared(
                "INSERT INTO mail_recipients
                    (message_id, user_id, email, consent_snapshot, status, sent_at, failed_at, last_error)
                 VALUES (?, ?, ?, ?, 'pending', NULL, NULL, NULL)",
                [$messageId, $userId, $email, $consentSnapshot],
            );

            $snapshots[] = [
                'recipient_id' => (int) $this->db()->lastInsertId(),
                'user_id' => $userId,
                'email' => $email,
                'name' => trim((string) ($recipient['name'] ?? '')),
                'consent_snapshot' => $consentSnapshot,
            ];
        }

        return $snapshots;
    }

    private function newsletterConsentSnapshot(int $userId): bool
    {
        $row = $this->db()->fetchOnePrepared(
            'SELECT newsletter_opt_in FROM user_mail_preferences WHERE user_id = ? LIMIT 1',
            [$userId],
        );

        return $row && (int) $row->newsletter_opt_in === 1;
    }
}
