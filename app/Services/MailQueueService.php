<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;

class MailQueueService
{
    private const RETRY_BASE_SEC = 300;

    /**
     * @param array<array{email:string,name:string,user_id?:int|null,recipient_id?:int|null}> $recipients
     */
    public static function enqueue(int $messageId, array $recipients): void
    {
        $clean = [];
        foreach ($recipients as $r) {
            $email = strtolower(trim((string) ($r['email'] ?? '')));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $clean[] = [
                'recipient_id' => isset($r['recipient_id']) && (int) $r['recipient_id'] > 0 ? (int) $r['recipient_id'] : null,
                'user_id' => isset($r['user_id']) && (int) $r['user_id'] > 0 ? (int) $r['user_id'] : null,
                'email' => $email,
                'name' => trim((string) ($r['name'] ?? '')),
            ];
        }
        if ($clean === []) {
            return;
        }

        $db = DbAdapterFactory::createFromConfig();
        $chunks = array_chunk($clean, 500);

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), "(?, ?, ?, ?, ?, 'pending', NOW(), NOW())"));
            $params = [];
            foreach ($chunk as $r) {
                $params[] = $messageId;
                $params[] = $r['recipient_id'];
                $params[] = $r['user_id'];
                $params[] = $r['email'];
                $params[] = $r['name'];
            }
            $db->executePrepared(
                "INSERT INTO mail_queue
                    (message_id, recipient_id, user_id, recipient_email, recipient_name, status, available_at, created_at)
                 VALUES {$placeholders}",
                $params,
            );
        }
    }

    public static function tick(int $limit = 5): int
    {
        $config = (new MailService())->getMailConfig();
        $batchSize = max(1, min(100, (int) ($config['mail_batch_size'] ?? 10)));
        $limit = max(1, min($limit, $batchSize));

        $lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'logeon_mail_queue.lock';
        $fp = @fopen($lockFile, 'c');
        if ($fp === false) {
            return 0;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return 0;
        }

        $processed = 0;

        try {
            $db = DbAdapterFactory::createFromConfig();
            $limit = self::applyRateLimit($db, $limit, (int) ($config['mail_rate_per_minute'] ?? 60));
            if ($limit <= 0) {
                return 0;
            }

            $workerId = mb_substr(gethostname() . '-' . getmypid(), 0, 64);

            $items = $db->fetchAllPrepared(
                "SELECT * FROM mail_queue
                 WHERE status = 'pending' AND available_at <= NOW() AND locked_at IS NULL
                 ORDER BY available_at ASC
                 LIMIT ?",
                [$limit],
            );

            if (empty($items)) {
                return 0;
            }

            $ids = array_map(static fn ($r) => (int) $r->id, $items);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $db->executePrepared(
                "UPDATE mail_queue
                 SET status = 'sending', locked_at = NOW(), locked_by = ?
                 WHERE id IN ({$placeholders}) AND locked_at IS NULL",
                array_merge([$workerId], $ids),
            );

            $mailService = new MailService();

            foreach ($items as $item) {
                $result = self::processItem($db, $mailService, $item);

                if ($result['ok'] === true) {
                    $db->executePrepared(
                        "UPDATE mail_queue
                         SET status='sent', sent_at=NOW(), last_error=NULL, locked_at=NULL, locked_by=NULL
                         WHERE id=?",
                        [(int) $item->id],
                    );
                    self::markRecipientSent($db, $item);
                    self::incrementMessageCount($db, (int) $item->message_id, 'sent');
                } else {
                    $newAttempts = (int) $item->attempts + 1;
                    $retryAttempts = self::retryAttemptsFromConfig($config);
                    $error = mb_substr((string) ($result['error'] ?? 'Invio email non riuscito.'), 0, 500);

                    if ($newAttempts >= $retryAttempts) {
                        $db->executePrepared(
                            "UPDATE mail_queue
                             SET status='failed', attempts=?, last_error=?, locked_at=NULL, locked_by=NULL
                             WHERE id=?",
                            [$newAttempts, $error, (int) $item->id],
                        );
                        self::markRecipientFailed($db, $item, $error);
                        self::incrementMessageCount($db, (int) $item->message_id, 'failed');
                    } else {
                        $nextAvailable = date('Y-m-d H:i:s', time() + self::RETRY_BASE_SEC * $newAttempts);
                        $db->executePrepared(
                            "UPDATE mail_queue
                             SET status='pending', attempts=?, available_at=?, last_error=?, locked_at=NULL, locked_by=NULL
                             WHERE id=?",
                            [$newAttempts, $nextAvailable, $error, (int) $item->id],
                        );
                        self::markRecipientPendingRetry($db, $item, $error);
                    }
                }

                $processed++;
            }
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $processed;
    }

    private static function processItem(DbAdapterInterface $db, MailService $mailService, object $item): array
    {
        try {
            $msg = $db->fetchOnePrepared(
                'SELECT subject, body_html, category FROM mail_messages WHERE id = ?',
                [(int) $item->message_id],
            );
            if (!$msg) {
                $error = 'Messaggio non trovato.';
                $db->executePrepared(
                    'UPDATE mail_queue SET last_error=? WHERE id=?',
                    [$error, (int) $item->id],
                );
                return ['ok' => false, 'error' => $error];
            }

            $username = (string) ($item->recipient_name ?? '');
            $email = strtolower(trim((string) ($item->recipient_email ?? '')));
            $charName = '';
            $charFullName = '';
            $overrides = [];

            $userId = isset($item->user_id) && (int) $item->user_id > 0 ? (int) $item->user_id : null;
            if ($userId !== null) {
                $cryptKey = defined('DB') ? (string) DB['crypt_key'] : '';
                $user = $db->fetchOnePrepared(
                    'SELECT username, CAST(AES_DECRYPT(email, ?) AS CHAR(255)) AS email FROM users WHERE id = ?',
                    [$cryptKey, $userId],
                );
                if ($user) {
                    $username = (string) ($user->username ?? $username);
                    $email = strtolower(trim((string) ($user->email ?? $email)));
                }

                $char = $db->fetchOnePrepared(
                    'SELECT c.name
                     FROM characters c
                     WHERE c.user_id = ? AND c.delete_requested_at IS NULL
                     ORDER BY c.id ASC
                     LIMIT 1',
                    [$userId],
                );
                if ($char) {
                    $charName = (string) ($char->name ?? '');
                    $charFullName = $charName;
                }

                $overrides['unsubscribe_url'] = (new MailConsentService())->buildUnsubscribeUrl($userId, $email);
            }

            $baseUrl = defined('APP') ? rtrim((string) constant('APP')['baseurl'], '/') : '';
            $overrides['preferences_url'] = $baseUrl . '/game/settings';
            if (!isset($overrides['unsubscribe_url'])) {
                $overrides['unsubscribe_url'] = $baseUrl . '/game/settings';
            }

            $vars = MailRendererService::buildVars($username, $email, $charName, $charFullName, $overrides);
            $subject = MailRendererService::renderSubject((string) $msg->subject, $vars);
            $bodyHtml = MailRendererService::renderHtml((string) $msg->body_html, $vars);
            $bodyText = MailRendererService::htmlToText($bodyHtml);

            $sent = $mailService->send($email, $subject, $bodyHtml, $bodyText, $username, (string) $msg->category);
            if ($sent !== true) {
                return ['ok' => false, 'error' => 'Invio email non riuscito.'];
            }

            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
            $db->executePrepared(
                'UPDATE mail_queue SET last_error=? WHERE id=?',
                [$error, (int) $item->id],
            );
            return ['ok' => false, 'error' => $error];
        }
    }

    private static function incrementMessageCount(DbAdapterInterface $db, int $messageId, string $type): void
    {
        $col = $type === 'sent' ? 'sent_count' : 'failed_count';
        $db->executePrepared(
            "UPDATE mail_messages SET `{$col}` = `{$col}` + 1, updated_at = NOW() WHERE id = ?",
            [$messageId],
        );

        $msg = $db->fetchOnePrepared(
            "SELECT total_recipients, sent_count, failed_count
             FROM mail_messages
             WHERE id = ? AND status IN ('queued','sending')",
            [$messageId],
        );
        if (!$msg || (int) $msg->total_recipients === 0) {
            return;
        }

        $done = (int) $msg->sent_count + (int) $msg->failed_count;
        if ($done >= (int) $msg->total_recipients) {
            $newStatus = ((int) $msg->failed_count >= (int) $msg->total_recipients) ? 'failed' : 'sent';
            $db->executePrepared(
                'UPDATE mail_messages SET status=?, sent_at=NOW(), updated_at=NOW() WHERE id=?',
                [$newStatus, $messageId],
            );
        } else {
            $db->executePrepared(
                "UPDATE mail_messages SET status='sending', updated_at=NOW() WHERE id=? AND status='queued'",
                [$messageId],
            );
        }
    }

    private static function applyRateLimit(DbAdapterInterface $db, int $limit, int $ratePerMinute): int
    {
        $ratePerMinute = max(1, min(1000, $ratePerMinute));
        $recent = $db->fetchOnePrepared(
            "SELECT COUNT(*) AS cnt
             FROM mail_queue
             WHERE status='sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)",
            [],
        );
        $sentLastMinute = (int) ($recent->cnt ?? 0);
        $remaining = max(0, $ratePerMinute - $sentLastMinute);

        return min($limit, $remaining);
    }

    private static function retryAttemptsFromConfig(array $config): int
    {
        return max(1, min(10, (int) ($config['mail_retry_attempts'] ?? 3)));
    }

    private static function markRecipientSent(DbAdapterInterface $db, object $item): void
    {
        $recipientId = isset($item->recipient_id) ? (int) $item->recipient_id : 0;
        if ($recipientId <= 0) {
            return;
        }

        $db->executePrepared(
            "UPDATE mail_recipients
             SET status='sent', sent_at=NOW(), failed_at=NULL, last_error=NULL
             WHERE id=?",
            [$recipientId],
        );
    }

    private static function markRecipientFailed(DbAdapterInterface $db, object $item, string $error): void
    {
        $recipientId = isset($item->recipient_id) ? (int) $item->recipient_id : 0;
        if ($recipientId <= 0) {
            return;
        }

        $db->executePrepared(
            "UPDATE mail_recipients
             SET status='failed', failed_at=NOW(), last_error=?
             WHERE id=?",
            [$error, $recipientId],
        );
    }

    private static function markRecipientPendingRetry(DbAdapterInterface $db, object $item, string $error): void
    {
        $recipientId = isset($item->recipient_id) ? (int) $item->recipient_id : 0;
        if ($recipientId <= 0) {
            return;
        }

        $db->executePrepared(
            "UPDATE mail_recipients
             SET status='pending', last_error=?
             WHERE id=?",
            [$error, $recipientId],
        );
    }
}
