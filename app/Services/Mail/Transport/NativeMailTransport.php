<?php

declare(strict_types=1);

namespace App\Services\Mail\Transport;

class NativeMailTransport implements MailTransportInterface
{
    public function send(
        string $toEmail,
        string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void {
        $boundary = 'lf_' . bin2hex(random_bytes(12));
        $plainText = $textBody !== '' ? $textBody : strip_tags($htmlBody);

        $fromHeader = $fromName !== ''
            ? '"' . mb_encode_mimeheader($fromName, 'UTF-8') . '" <' . $fromEmail . '>'
            : $fromEmail;

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        if ($fromEmail !== '') {
            $headers .= "From: {$fromHeader}\r\n";
            $headers .= "Reply-To: {$fromEmail}\r\n";
        }

        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= rtrim(chunk_split(base64_encode($plainText))) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= rtrim(chunk_split(base64_encode($htmlBody))) . "\r\n";
        $body .= "--{$boundary}--";

        $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8');
        $sent = @mail($toEmail, $encodedSubject, $body, $headers);

        if (!$sent) {
            throw new \RuntimeException("mail(): invio fallito a {$toEmail}");
        }
    }
}
