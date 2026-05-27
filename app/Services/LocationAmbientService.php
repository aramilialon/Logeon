<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;

class LocationAmbientService
{
    /** @var DbAdapterInterface */
    private $db;

    private const YT_PATTERNS = [
        '/[?&]v=([a-zA-Z0-9_-]{11})/',
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
        '/\/embed\/([a-zA-Z0-9_-]{11})/',
        '/\/shorts\/([a-zA-Z0-9_-]{11})/',
        '/\/v\/([a-zA-Z0-9_-]{11})/',
    ];

    private const DANGEROUS_PROTOCOLS = ['javascript:', 'data:', 'file:', 'vbscript:'];

    public function __construct(DbAdapterInterface $db = null)
    {
        $this->db = $db ?: DbAdapterFactory::createFromConfig();
    }

    public function getState(int $locationId): ?array
    {
        $row = $this->db->fetchOnePrepared(
            'SELECT source_type, source_url, youtube_video_id, title,
                    is_active, force_muted, started_at
             FROM location_music_state
             WHERE location_id = ?
             LIMIT 1',
            [$locationId],
        );

        if (empty($row)) {
            return null;
        }

        return $this->mapStateRow($row);
    }

    public function setState(int $locationId, string $sourceUrl, ?string $title, int $characterId): array
    {
        $this->validateUrl($sourceUrl);

        $sourceType = $this->detectSourceType($sourceUrl);
        $youtubeVideoId = null;

        if ($sourceType === 'youtube') {
            $youtubeVideoId = $this->extractYouTubeVideoId($sourceUrl);
            if ($youtubeVideoId === null) {
                throw new \InvalidArgumentException('URL YouTube non riconosciuto');
            }
        }

        $this->db->executePrepared(
            'INSERT INTO location_music_state
                (location_id, source_type, source_url, youtube_video_id, title,
                 started_by_character_id, started_at, is_active, force_muted, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), 1, 0, NOW())
             ON DUPLICATE KEY UPDATE
                source_type              = VALUES(source_type),
                source_url               = VALUES(source_url),
                youtube_video_id         = VALUES(youtube_video_id),
                title                    = VALUES(title),
                started_by_character_id  = VALUES(started_by_character_id),
                started_at               = NOW(),
                is_active                = 1,
                force_muted              = 0,
                updated_at               = NOW()',
            [$locationId, $sourceType, $sourceUrl, $youtubeVideoId, $title, $characterId],
        );

        $this->db->executePrepared(
            'UPDATE locations SET ambient_music_url = ? WHERE id = ?',
            [$sourceUrl, $locationId],
        );

        $state = $this->getState($locationId);
        if ($state !== null) {
            return $state;
        }

        return [
            'is_active' => true,
            'source_type' => $sourceType,
            'source_url' => $sourceUrl,
            'youtube_video_id' => $youtubeVideoId,
            'title' => $title,
            'force_muted' => false,
            'started_at' => date('Y-m-d H:i:s'),
            'started_at_ts' => time(),
            'server_now_ts' => time(),
        ];
    }

    public function stopMusic(int $locationId): void
    {
        $this->db->executePrepared(
            'UPDATE location_music_state
             SET is_active = 0, force_muted = 0, updated_at = NOW()
             WHERE location_id = ?',
            [$locationId],
        );
        $this->db->executePrepared(
            'UPDATE locations SET ambient_music_url = NULL WHERE id = ?',
            [$locationId],
        );
    }

    public function setForceMuted(int $locationId, bool $muted): void
    {
        $this->db->executePrepared(
            'UPDATE location_music_state
             SET force_muted = ?, updated_at = NOW()
             WHERE location_id = ? AND is_active = 1',
            [(int) $muted, $locationId],
        );
    }

    public function detectSourceType(string $url): string
    {
        if ($this->isYouTubeUrl($url)) {
            return 'youtube';
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['mp3', 'ogg', 'wav', 'flac', 'm4a', 'aac'], true)) {
            return 'audio_file';
        }
        return 'external_url';
    }

    public function isYouTubeUrl(string $url): bool
    {
        return (bool) preg_match('/(?:youtube\.com|youtu\.be|music\.youtube\.com)/i', $url);
    }

    public function extractYouTubeVideoId(string $url): ?string
    {
        foreach (self::YT_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $match)) {
                return $match[1];
            }
        }
        return null;
    }

    private function mapStateRow($row): array
    {
        $startedAt = isset($row->started_at)
            ? (string) $row->started_at
            : null;
        $startedAtTs = null;
        if ($startedAt !== null && trim($startedAt) !== '') {
            $ts = strtotime($startedAt);
            if ($ts !== false) {
                $startedAtTs = (int) $ts;
            }
        }

        return [
            'is_active' => (bool) $row->is_active,
            'source_type' => (string) $row->source_type,
            'source_url' => (string) $row->source_url,
            'youtube_video_id' => isset($row->youtube_video_id)
                ? (string) $row->youtube_video_id : null,
            'title' => isset($row->title)
                ? (string) $row->title : null,
            'force_muted' => (bool) $row->force_muted,
            'started_at' => $startedAt,
            'started_at_ts' => $startedAtTs,
            'server_now_ts' => time(),
        ];
    }

    private function validateUrl(string $url): void
    {
        if (strlen($url) > 2048) {
            throw new \InvalidArgumentException('URL troppo lungo');
        }

        $lower = strtolower(trim($url));
        foreach (self::DANGEROUS_PROTOCOLS as $proto) {
            if (strpos($lower, $proto) === 0) {
                throw new \InvalidArgumentException('URL non valido');
            }
        }

        if (!preg_match('/^https?:\/\//i', $url)) {
            throw new \InvalidArgumentException('Solo URL HTTPS consentiti');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('URL non valido');
        }
    }
}
