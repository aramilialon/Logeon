<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Mail\Transport\MailTransportInterface;
use App\Services\Mail\Transport\NativeMailTransport;
use App\Services\Mail\Transport\SmtpMailTransport;
use Core\Database\DbAdapterFactory;

class MailService
{
    private ?MailTransportInterface $transport = null;

    /**
     * @return array{
     *     mail_enabled:int,
     *     mail_transport:string,
     *     mail_from_email:string,
     *     mail_from_name:string,
     *     mail_smtp_host:string,
     *     mail_smtp_port:int,
     *     mail_smtp_username:string,
     *     mail_smtp_password:string,
     *     mail_smtp_encryption:string,
     *     mail_smtp_timeout:int,
     *     mail_batch_size:int,
     *     mail_rate_per_minute:int,
     *     mail_retry_attempts:int
     * }
     */
    public function getMailConfig(): array
    {
        $appEmail = defined('APP') ? (string) constant('APP')['support_email'] : '';
        $appName = defined('APP') ? (string) constant('APP')['support_name'] : '';

        $config = [
            'mail_enabled' => 0,
            'mail_transport' => 'smtp',
            'mail_from_email' => $appEmail,
            'mail_from_name' => $appName,
            'mail_smtp_host' => '',
            'mail_smtp_port' => 587,
            'mail_smtp_username' => '',
            'mail_smtp_password' => '',
            'mail_smtp_encryption' => 'tls',
            'mail_smtp_timeout' => 30,
            'mail_batch_size' => 10,
            'mail_rate_per_minute' => 60,
            'mail_retry_attempts' => 3,
        ];

        try {
            $db = DbAdapterFactory::createFromConfig();
            foreach (array_keys($config) as $key) {
                $row = $db->fetchOnePrepared(
                    'SELECT value FROM sys_configs WHERE `key` = ? LIMIT 1',
                    [$key],
                );
                if ($row === null || !isset($row->value)) {
                    continue;
                }

                $config[$key] = in_array($key, [
                    'mail_enabled',
                    'mail_smtp_port',
                    'mail_smtp_timeout',
                    'mail_batch_size',
                    'mail_rate_per_minute',
                    'mail_retry_attempts',
                ], true)
                    ? (int) $row->value
                    : (string) $row->value;
            }

            $legacyMap = [
                'smtp_enabled' => 'mail_enabled',
                'smtp_host' => 'mail_smtp_host',
                'smtp_port' => 'mail_smtp_port',
                'smtp_encryption' => 'mail_smtp_encryption',
                'smtp_username' => 'mail_smtp_username',
                'smtp_password' => 'mail_smtp_password',
                'smtp_from_email' => 'mail_from_email',
                'smtp_from_name' => 'mail_from_name',
            ];

            foreach ($legacyMap as $legacyKey => $canonicalKey) {
                $hasCanonicalValue = $canonicalKey === 'mail_enabled'
                    ? ((int) $config[$canonicalKey] === 1)
                    : (trim((string) $config[$canonicalKey]) !== '');
                if ($hasCanonicalValue) {
                    continue;
                }

                $row = $db->fetchOnePrepared(
                    'SELECT value FROM sys_configs WHERE `key` = ? LIMIT 1',
                    [$legacyKey],
                );
                if ($row === null || !isset($row->value)) {
                    continue;
                }

                $config[$canonicalKey] = in_array($canonicalKey, ['mail_enabled', 'mail_smtp_port'], true)
                    ? (int) $row->value
                    : (string) $row->value;
            }
        } catch (\Throwable) {
            // Fallback ai default; il transport nativo sarà usato se SMTP non è disponibile.
        }

        $config['mail_transport'] = in_array((string) $config['mail_transport'], ['smtp', 'native'], true)
            ? (string) $config['mail_transport']
            : 'smtp';
        $config['mail_smtp_encryption'] = in_array((string) $config['mail_smtp_encryption'], ['none', 'tls', 'ssl'], true)
            ? (string) $config['mail_smtp_encryption']
            : 'tls';
        $config['mail_smtp_port'] = max(1, min(65535, (int) $config['mail_smtp_port']));
        $config['mail_smtp_timeout'] = max(5, min(120, (int) $config['mail_smtp_timeout']));
        $config['mail_batch_size'] = max(1, min(100, (int) $config['mail_batch_size']));
        $config['mail_rate_per_minute'] = max(1, min(1000, (int) $config['mail_rate_per_minute']));
        $config['mail_retry_attempts'] = max(1, min(10, (int) $config['mail_retry_attempts']));

        return $config;
    }

    /**
     * Compatibilità con il pannello admin esistente.
     *
     * @return array{smtp_enabled:int,smtp_host:string,smtp_port:int,smtp_encryption:string,smtp_username:string,smtp_password:string,smtp_from_email:string,smtp_from_name:string}
     */
    public function getSmtpConfig(): array
    {
        $config = $this->getMailConfig();

        return [
            'smtp_enabled' => (int) $config['mail_enabled'],
            'smtp_host' => (string) $config['mail_smtp_host'],
            'smtp_port' => (int) $config['mail_smtp_port'],
            'smtp_encryption' => (string) $config['mail_smtp_encryption'],
            'smtp_username' => (string) $config['mail_smtp_username'],
            'smtp_password' => (string) $config['mail_smtp_password'],
            'smtp_from_email' => (string) $config['mail_from_email'],
            'smtp_from_name' => (string) $config['mail_from_name'],
        ];
    }

    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody = '',
        string $toName = '',
        string $category = 'transactional',
    ): bool {
        $to = trim($to);
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $config = $this->getMailConfig();
        $from = $config['mail_from_email'] !== ''
            ? (string) $config['mail_from_email']
            : (defined('APP') ? (string) constant('APP')['support_email'] : '');
        $fromName = (string) $config['mail_from_name'];

        try {
            $this->resolveTransport($config)->send($to, $toName, $from, $fromName, $subject, $htmlBody, $textBody);
            MailLogService::record($to, $toName, $from, $fromName, $subject, 'sent', '', $category);
            return true;
        } catch (\Throwable $e) {
            MailLogService::record($to, $toName, $from, $fromName, $subject, 'failed', $e->getMessage(), $category);
            return false;
        }
    }

    private function resolveTransport(array $config): MailTransportInterface
    {
        if ($this->transport !== null) {
            return $this->transport;
        }

        $useSmtp = (int) ($config['mail_enabled'] ?? 0) === 1
            && (string) ($config['mail_transport'] ?? 'smtp') === 'smtp'
            && trim((string) ($config['mail_smtp_host'] ?? '')) !== '';

        if ($useSmtp) {
            $this->transport = new SmtpMailTransport(
                (string) $config['mail_smtp_host'],
                (int) $config['mail_smtp_port'],
                (string) $config['mail_smtp_encryption'],
                (string) $config['mail_smtp_username'],
                (string) $config['mail_smtp_password'],
                (int) $config['mail_smtp_timeout'],
            );
        } else {
            $this->transport = new NativeMailTransport();
        }

        return $this->transport;
    }
}
