<?php

declare(strict_types=1);

namespace App\Services\Update;

use Core\Http\AppError;

class UpdateManifestService
{
    /**
     * @return array<string,mixed>
     */
    public function fetchAndValidate(string $url, int $timeoutSeconds = 8): array
    {
        $manifestUrl = trim($url);
        if ($manifestUrl === '') {
            throw AppError::validation(
                'URL manifest aggiornamenti non configurato',
                [],
                'update_manifest_unavailable',
            );
        }

        $raw = $this->request($manifestUrl, $timeoutSeconds);
        $decoded = null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = $this->decodeManifest($raw);
        }

        if (!is_array($decoded)) {
            $decoded = $this->loadLocalFallbackManifest();
        }

        if (!is_array($decoded)) {
            throw AppError::validation(
                'Manifest aggiornamenti non valido',
                [],
                'update_manifest_invalid',
            );
        }

        $schema = (int) ($decoded['schema'] ?? 0);
        if ($schema <= 0) {
            throw AppError::validation(
                'Schema manifest non valido',
                [],
                'update_manifest_invalid',
            );
        }

        $project = strtolower(trim((string) ($decoded['project'] ?? '')));
        if ($project !== 'logeon') {
            throw AppError::validation(
                'Manifest appartenente a un progetto diverso',
                [],
                'update_manifest_invalid',
            );
        }

        return $decoded;
    }

    private function request(string $url, int $timeoutSeconds): string
    {
        $timeout = $timeoutSeconds > 0 ? $timeoutSeconds : 8;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'User-Agent: Logeon-Updater/1.0',
                ]);

                $out = curl_exec($ch);
                curl_close($ch);

                if (is_string($out) && $out !== '') {
                    return $out;
                }
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: Logeon-Updater/1.0\r\nAccept-Encoding: gzip\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        return $this->decodeGzipIfNeeded($raw);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeManifest(string $raw): ?array
    {
        $normalized = ltrim($this->stripUtf8Bom(trim($raw)));
        if ($normalized === '') {
            return null;
        }

        $decoded = json_decode($normalized, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function stripUtf8Bom(string $value): string
    {
        if (strncmp($value, "\xEF\xBB\xBF", 3) === 0) {
            return substr($value, 3);
        }

        return $value;
    }

    private function decodeGzipIfNeeded(string $raw): string
    {
        if (strlen($raw) < 3) {
            return $raw;
        }

        $isGzip = (ord($raw[0]) === 0x1F && ord($raw[1]) === 0x8B);
        if (!$isGzip || !function_exists('gzdecode')) {
            return $raw;
        }

        $decoded = @gzdecode($raw);
        if (!is_string($decoded) || $decoded === '') {
            return $raw;
        }

        return $decoded;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadLocalFallbackManifest(): ?array
    {
        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'update-manifest.json';
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        return $this->decodeManifest($raw);
    }
}

