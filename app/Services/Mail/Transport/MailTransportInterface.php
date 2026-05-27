<?php

declare(strict_types=1);

namespace App\Services\Mail\Transport;

interface MailTransportInterface
{
    /**
     * @throws \RuntimeException se l'invio fallisce
     */
    public function send(
        string $toEmail,
        string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void;
}
