<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;

class MailConsentService
{
    private ?DbAdapterInterface $db = null;

    private function db(): DbAdapterInterface
    {
        if ($this->db === null) {
            $this->db = DbAdapterFactory::createFromConfig();
        }
        return $this->db;
    }

    public function getPreferences(int $userId): object
    {
        $row = $this->db()->fetchOnePrepared(
            'SELECT newsletter_opt_in FROM user_mail_preferences WHERE user_id = ?',
            [$userId],
        );

        return (object) [
            'newsletter_opt_in' => $row ? (int) $row->newsletter_opt_in : 0,
        ];
    }

    public function setNewsletterOptIn(
        int     $userId,
        bool    $value,
        string  $source = 'user',
        ?string $ip = null,
        ?string $ua = null,
    ): void {
        $intVal = $value ? 1 : 0;

        $this->db()->executePrepared(
            'INSERT INTO user_mail_preferences (user_id, newsletter_opt_in, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE newsletter_opt_in = VALUES(newsletter_opt_in), updated_at = NOW()',
            [$userId, $intVal],
        );

        $this->db()->executePrepared(
            'INSERT INTO mail_consent_logs (user_id, consent_type, value, source, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$userId, 'newsletter_opt_in', $intVal, $source, $ip, $ua],
        );
    }

    public function isNewsletterOptIn(int $userId): bool
    {
        $row = $this->db()->fetchOnePrepared(
            'SELECT newsletter_opt_in FROM user_mail_preferences WHERE user_id = ?',
            [$userId],
        );
        return $row && (int) $row->newsletter_opt_in === 1;
    }

    public function buildUnsubscribeToken(int $userId, string $email): string
    {
        $payload = [
            'u' => $userId,
            'e' => strtolower(trim($email)),
            't' => time(),
        ];

        $encodedPayload = $this->base64UrlEncode((string) json_encode($payload));
        $signature = hash_hmac('sha256', $encodedPayload, $this->unsubscribeSecret(), true);

        return $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    public function buildUnsubscribeUrl(int $userId, string $email): string
    {
        $baseUrl = defined('APP') ? rtrim((string) constant('APP')['baseurl'], '/') : '';
        return $baseUrl . '/unsubscribe/' . $this->buildUnsubscribeToken($userId, $email);
    }

    public function consumeUnsubscribeToken(string $token, ?string $ip = null, ?string $ua = null): array
    {
        $token = trim($token);
        if ($token === '') {
            return [
                'success' => false,
                'status' => 'invalid',
                'message' => 'Link di disiscrizione non valido.',
            ];
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return [
                'success' => false,
                'status' => 'invalid',
                'message' => 'Link di disiscrizione non valido.',
            ];
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->unsubscribeSecret(), true));
        if (!hash_equals($expectedSignature, $encodedSignature)) {
            return [
                'success' => false,
                'status' => 'invalid',
                'message' => 'Link di disiscrizione non valido.',
            ];
        }

        $payload = json_decode($this->base64UrlDecode($encodedPayload), false);
        $userId = isset($payload->u) ? (int) $payload->u : 0;
        $email = isset($payload->e) ? strtolower(trim((string) $payload->e)) : '';

        if ($userId <= 0 || $email === '') {
            return [
                'success' => false,
                'status' => 'invalid',
                'message' => 'Link di disiscrizione non valido.',
            ];
        }

        $cryptKey = defined('DB') ? (string) DB['crypt_key'] : '';
        $user = $this->db()->fetchOnePrepared(
            'SELECT id, CAST(AES_DECRYPT(email, ?) AS CHAR(255)) AS email
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$cryptKey, $userId],
        );

        if (!$user || strtolower(trim((string) ($user->email ?? ''))) !== $email) {
            return [
                'success' => false,
                'status' => 'invalid',
                'message' => 'Il link di disiscrizione non è più valido.',
            ];
        }

        $alreadyDisabled = !$this->isNewsletterOptIn($userId);
        $this->setNewsletterOptIn($userId, false, 'unsubscribe_link', $ip, $ua);

        return [
            'success' => true,
            'status' => $alreadyDisabled ? 'already_unsubscribed' : 'success',
            'message' => $alreadyDisabled
                ? 'Le comunicazioni facoltative risultavano già disattivate.'
                : 'Hai disattivato newsletter e comunicazioni facoltative.',
        ];
    }

    private function unsubscribeSecret(): string
    {
        if (defined('DB') && isset(DB['crypt_key']) && (string) DB['crypt_key'] !== '') {
            return (string) DB['crypt_key'];
        }

        return 'logeon-mail-unsubscribe';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        return (string) base64_decode($normalized, true);
    }
}
