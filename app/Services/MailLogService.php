<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;

class MailLogService
{
    private const VALID_STATUSES = ['sent', 'failed'];
    private const VALID_CATEGORIES = ['transactional', 'system', 'announcement', 'newsletter'];

    /**
     * Registra l'esito di un invio in mail_logs.
     * Non propaga mai eccezioni — un fallimento del log non deve bloccare il flusso principale.
     */
    public static function record(
        string $toEmail,
        string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $status,
        string $error = '',
        string $category = 'transactional',
    ): void {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $status = 'failed';
        }
        if (!in_array($category, self::VALID_CATEGORIES, true)) {
            $category = 'transactional';
        }

        try {
            $db = DbAdapterFactory::createFromConfig();
            $db->executePrepared(
                'INSERT INTO mail_logs
                    (category, from_email, from_name, to_email, to_name, subject, status, error, sent_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $category,
                    $fromEmail,
                    $fromName,
                    $toEmail,
                    $toName,
                    mb_substr($subject, 0, 500),
                    $status,
                    $error !== '' ? $error : null,
                ],
            );
        } catch (\Throwable) {
            // Silenzioso — la tabella potrebbe non esistere ancora (installer non completato)
        }
    }
}
