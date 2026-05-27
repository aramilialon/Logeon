<?php

declare(strict_types=1);

namespace App\Services\Mail\Transport;

class SmtpMailTransport implements MailTransportInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly int    $timeout = 30,
    ) {
    }

    public function send(
        string $toEmail,
        string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): void {
        $socket = $this->openSocket();
        try {
            $this->expect($socket, [220]);
            $this->ehlo($socket);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS');
                $this->expect($socket, [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('SMTP: negoziazione TLS fallita');
                }
                $this->ehlo($socket);
            }

            if ($this->username !== '') {
                $this->authenticate($socket);
            }

            $this->command($socket, "MAIL FROM:<{$fromEmail}>");
            $this->expect($socket, [250]);
            $this->command($socket, "RCPT TO:<{$toEmail}>");
            $this->expect($socket, [250, 251]);
            $this->command($socket, 'DATA');
            $this->expect($socket, [354]);

            $raw = $this->buildMime($toEmail, $toName, $fromEmail, $fromName, $subject, $htmlBody, $textBody);
            // Dot-stuffing RFC 5321: righe che iniziano con '.' ricevono un '.' aggiuntivo
            $raw = preg_replace('/^\./m', '..', $raw);
            fwrite($socket, $raw . "\r\n.\r\n");
            $this->expect($socket, [250]);

            $this->command($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private function openSocket()
    {
        $prefix = $this->encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);
        $socket = @stream_socket_client(
            $prefix . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if ($socket === false) {
            throw new \RuntimeException(
                "SMTP: connessione fallita a {$this->host}:{$this->port} — {$errstr} ({$errno})",
            );
        }
        stream_set_timeout($socket, $this->timeout);
        return $socket;
    }

    /** @param resource $socket */
    private function ehlo($socket): void
    {
        $hostname = gethostname() ?: 'localhost';
        $this->command($socket, "EHLO {$hostname}");
        // Legge risposta multi-riga 250
        $this->readMultiline($socket, 250);
    }

    /** @param resource $socket */
    private function authenticate($socket): void
    {
        $this->command($socket, 'AUTH LOGIN');
        $this->expect($socket, [334]);
        $this->command($socket, base64_encode($this->username));
        $this->expect($socket, [334]);
        $this->command($socket, base64_encode($this->password));
        $this->expect($socket, [235]);
    }

    /** @param resource $socket */
    private function command($socket, string $cmd): void
    {
        fwrite($socket, $cmd . "\r\n");
    }

    /**
     * @param resource $socket
     * @param int[]    $allowed
     */
    private function expect($socket, array $allowed): string
    {
        $response = $this->readResponse($socket);
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $allowed, true)) {
            throw new \RuntimeException(
                'SMTP: atteso ' . implode('/', $allowed) . ", ricevuto {$code}: " . trim($response),
            );
        }
        return $response;
    }

    /**
     * Legge una risposta SMTP che può essere multi-riga (250-...) o singola (250 ...).
     *
     * @param resource $socket
     */
    private function readMultiline($socket, int $expected): void
    {
        while (($line = fgets($socket, 1024)) !== false) {
            $code = (int) substr($line, 0, 3);
            // Riga finale ha spazio al quarto carattere, le intermedie hanno '-'
            if (strlen($line) >= 4 && $line[3] === ' ') {
                if ($code !== $expected) {
                    throw new \RuntimeException("SMTP: atteso {$expected}, ricevuto {$code}: {$line}");
                }
                return;
            }
        }
    }

    /** @param resource $socket */
    private function readResponse($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    private function buildMime(
        string $toEmail,
        string $toName,
        string $fromEmail,
        string $fromName,
        string $subject,
        string $htmlBody,
        string $textBody,
    ): string {
        $boundary = 'lf_' . bin2hex(random_bytes(12));
        $messageId = '<' . bin2hex(random_bytes(16)) . '@' . ($this->host ?: 'logeon.local') . '>';
        $plainText = $textBody !== '' ? $textBody : strip_tags($htmlBody);

        $from = $fromName !== ''
            ? '"' . $this->encodeHeader($fromName) . '" <' . $fromEmail . '>'
            : $fromEmail;
        $to = $toName !== ''
            ? '"' . $this->encodeHeader($toName) . '" <' . $toEmail . '>'
            : $toEmail;

        $parts = [];
        $parts[] = 'Date: ' . date('r');
        $parts[] = 'From: ' . $from;
        $parts[] = 'To: ' . $to;
        $parts[] = 'Subject: ' . $this->encodeHeader($subject);
        $parts[] = 'Message-ID: ' . $messageId;
        $parts[] = 'MIME-Version: 1.0';
        $parts[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $parts[] = '';
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/plain; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = '';
        $parts[] = rtrim(chunk_split(base64_encode($plainText)));
        $parts[] = '--' . $boundary;
        $parts[] = 'Content-Type: text/html; charset=UTF-8';
        $parts[] = 'Content-Transfer-Encoding: base64';
        $parts[] = '';
        $parts[] = rtrim(chunk_split(base64_encode($htmlBody)));
        $parts[] = '--' . $boundary . '--';

        return implode("\r\n", $parts);
    }

    private function encodeHeader(string $value): string
    {
        // Encode solo se contiene caratteri non-ASCII
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
