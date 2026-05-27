<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MediaFile;
use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Http\AppError;

class MediaManagerService
{
    private const UPLOAD_ROOT = __DIR__ . '/../../uploads';
    private const UPLOAD_BASE = __DIR__ . '/../../uploads/media';

    private const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'txt', 'md',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const MIME_VALIDATION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'txt' => ['text/plain'],
        'md' => ['text/plain', 'text/markdown', 'text/x-markdown'],
    ];

    private const DEFAULT_MAX_FILE_SIZE = 5242880;

    /** @var DbAdapterInterface */
    private $db;

    public function __construct(DbAdapterInterface $db = null)
    {
        $this->db = $db ?: DbAdapterFactory::createFromConfig();
    }

    public function validatePath(string $path): string
    {
        $this->ensureBaseDir();
        $relativePath = $this->normalizeRelativePath($path);
        $absolutePath = $this->resolveExistingDirectory($relativePath);

        return $this->relativeFromAbsolute($absolutePath);
    }

    public function ensurePathExists(string $relativePath): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = $this->ensureDirectory($relativePath);

        return $this->relativeFromAbsolute($absolutePath);
    }

    public function sanifyFilename(string $original): string
    {
        $name = str_replace("\x00", '', $original);
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^a-zA-Z0-9\s._-]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', (string) $name);
        $name = trim((string) $name, " \t\n\r\0\x0B.");

        if ($name === '' || strpos($name, '..') !== false || $name[0] === '.') {
            throw AppError::validation('Nome file non valido', [], 'media_invalid_name');
        }

        return mb_substr($name, 0, 180, 'UTF-8');
    }

    public function validateExtension(string $filename): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '') {
            throw AppError::validation('Il file deve avere una estensione valida', [], 'media_missing_extension');
        }

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw AppError::validation('Estensione non consentita', ['extension' => $extension], 'media_extension_not_allowed');
        }

        return $extension;
    }

    public function validateMimeType(string $filePath, string $extension): string
    {
        if (!is_file($filePath)) {
            throw new AppError('File temporaneo non trovato', 500, ['error_code' => 'media_tmp_missing']);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo ? finfo_file($finfo, $filePath) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!is_string($detected) || $detected === '') {
            throw new AppError('Impossibile determinare il MIME type del file', 500, ['error_code' => 'media_mime_detect_failed']);
        }

        $allowed = self::MIME_VALIDATION[$extension] ?? [];
        if (!in_array($detected, $allowed, true)) {
            throw AppError::validation('Tipo file non consentito', [
                'extension' => $extension,
                'mime_type' => $detected,
            ], 'media_mime_not_allowed');
        }

        return $detected;
    }

    public function validateFileSize(int $size): void
    {
        if ($size <= 0) {
            throw AppError::validation('Il file e vuoto', [], 'media_empty_file');
        }

        $maxSize = $this->maxFileSizeBytes();
        if ($size > $maxSize) {
            throw AppError::validation('Il file supera la dimensione massima consentita', [
                'max_bytes' => $maxSize,
                'max_mb' => round($maxSize / 1048576, 2),
            ], 'media_file_too_large');
        }
    }

    public function generateStoredName(string $originalName, string $extension): string
    {
        $base = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-zA-Z0-9_-]/', '-', $base);
        $base = trim((string) $base, '-_');
        if ($base === '') {
            $base = 'media';
        }

        $base = mb_substr($base, 0, 48, 'UTF-8');
        return time() . '_' . bin2hex(random_bytes(8)) . '_' . $base . '.' . $extension;
    }

    public function saveFile(string $tmpPath, string $storedName, string $relativePath = ''): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $directory = $this->ensureDirectory($relativePath);
        $destination = $directory . DIRECTORY_SEPARATOR . $storedName;

        if (!@move_uploaded_file($tmpPath, $destination)) {
            throw new AppError('Impossibile salvare il file', 500, ['error_code' => 'media_move_failed']);
        }

        @chmod($destination, 0644);

        return $relativePath;
    }

    public function persistMetadata(
        string $storedName,
        string $originalName,
        string $extension,
        string $mimeType,
        int $size,
        string $path,
        int $uploadedByUserId,
    ): MediaFile {
        $path = $this->normalizeRelativePath($path);

        $this->db->executePrepared(
            'INSERT INTO media_files
                (stored_name, original_name, extension, mime_type, size, path, uploaded_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $storedName,
                $originalName,
                $extension,
                $mimeType,
                $size,
                $path,
                $uploadedByUserId > 0 ? $uploadedByUserId : null,
            ],
        );

        $mediaFile = new MediaFile();
        $mediaFile->id = (int) $this->db->lastInsertId();
        $mediaFile->stored_name = $storedName;
        $mediaFile->original_name = $originalName;
        $mediaFile->extension = $extension;
        $mediaFile->mime_type = $mimeType;
        $mediaFile->size = $size;
        $mediaFile->path = $path;
        $mediaFile->uploaded_by_user_id = $uploadedByUserId > 0 ? $uploadedByUserId : null;

        return $mediaFile;
    }

    public function listDirectory(string $relativePath = '', string $search = ''): array
    {
        $path = $this->validatePath($relativePath);
        $absolute = $this->resolveExistingDirectory($path);
        $search = trim($search);

        $folders = [];
        $items = scandir($absolute);
        if ($items === false) {
            throw new AppError('Impossibile leggere la cartella richiesta', 500, ['error_code' => 'media_dir_read_failed']);
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $this->isHiddenSegment($item)) {
                continue;
            }
            $itemAbsolute = $absolute . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($itemAbsolute)) {
                continue;
            }
            if ($search !== '' && stripos($item, $search) === false) {
                continue;
            }
            $folders[] = [
                'name' => $item,
                'path' => $path !== '' ? ($path . '/' . $item) : $item,
            ];
        }

        usort($folders, static function (array $left, array $right): int {
            return strnatcasecmp($left['name'], $right['name']);
        });

        $params = [$path];
        $sql = 'SELECT mf.id, mf.stored_name, mf.original_name, mf.extension, mf.mime_type, mf.size, mf.path, mf.uploaded_by_user_id, mf.created_at, mf.updated_at
                FROM media_files mf
                WHERE mf.path = ?';

        if ($search !== '') {
            $sql .= ' AND mf.original_name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY mf.created_at DESC, mf.id DESC';
        $rows = $this->db->fetchAllPrepared($sql, $params);

        return [
            'folders' => $folders,
            'files' => $this->decorateRows($rows),
        ];
    }

    public function createDirectory(string $folderName, string $relativePath = ''): string
    {
        $parentPath = $this->validatePath($relativePath);
        $parentAbsolute = $this->resolveExistingDirectory($parentPath);
        $folderName = $this->sanitizeFolderName($folderName);
        $newAbsolute = $parentAbsolute . DIRECTORY_SEPARATOR . $folderName;

        if (file_exists($newAbsolute)) {
            throw new AppError('Esiste gia una cartella con questo nome', 409, ['error_code' => 'media_folder_exists']);
        }

        if (!@mkdir($newAbsolute, 0755, false)) {
            throw new AppError('Impossibile creare la cartella', 500, ['error_code' => 'media_folder_create_failed']);
        }

        return $parentPath !== '' ? ($parentPath . '/' . $folderName) : $folderName;
    }

    public function renameDirectory(string $relativePath, string $newName): string
    {
        $currentPath = $this->validatePath($relativePath);
        if ($currentPath === '') {
            throw AppError::validation('La cartella root non puo essere rinominata', [], 'media_root_rename_forbidden');
        }

        $currentAbsolute = $this->resolveExistingDirectory($currentPath);
        $newName = $this->sanitizeFolderName($newName);
        $parentPath = $this->parentPath($currentPath);
        $parentAbsolute = $this->resolveExistingDirectory($parentPath);
        $newPath = $parentPath !== '' ? ($parentPath . '/' . $newName) : $newName;
        $newAbsolute = $parentAbsolute . DIRECTORY_SEPARATOR . $newName;

        if (file_exists($newAbsolute)) {
            throw new AppError('Esiste gia una cartella con questo nome', 409, ['error_code' => 'media_folder_exists']);
        }

        if (!@rename($currentAbsolute, $newAbsolute)) {
            throw new AppError('Impossibile rinominare la cartella', 500, ['error_code' => 'media_folder_rename_failed']);
        }

        $this->updatePathsAfterRename($currentPath, $newPath);

        return $newPath;
    }

    public function deleteDirectory(string $relativePath): void
    {
        $path = $this->validatePath($relativePath);
        if ($path === '') {
            throw AppError::validation('La cartella root non puo essere eliminata', [], 'media_root_delete_forbidden');
        }

        $absolute = $this->resolveExistingDirectory($path);
        $items = scandir($absolute);
        if ($items === false) {
            throw new AppError('Impossibile leggere la cartella richiesta', 500, ['error_code' => 'media_dir_read_failed']);
        }

        $visibleItems = array_values(array_filter($items, function ($item): bool {
            return $item !== '.' && $item !== '..';
        }));

        if (!empty($visibleItems)) {
            throw new AppError('La cartella non e vuota', 409, ['error_code' => 'media_folder_not_empty']);
        }

        $metadataCount = $this->db->fetchOnePrepared(
            'SELECT COUNT(*) AS cnt FROM media_files WHERE path = ? OR path LIKE ?',
            [$path, $path . '/%'],
        );
        $count = isset($metadataCount['cnt']) ? (int) $metadataCount['cnt'] : (int) ($metadataCount->cnt ?? 0);
        if ($count > 0) {
            throw new AppError('La cartella contiene ancora metadata associati ai file', 409, ['error_code' => 'media_folder_not_empty']);
        }

        if (!@rmdir($absolute)) {
            throw new AppError('Impossibile eliminare la cartella', 500, ['error_code' => 'media_folder_delete_failed']);
        }
    }

    public function renameFile(int $mediaFileId, string $newName): array
    {
        $row = $this->requireFileById($mediaFileId);
        $ext = (string) $row['extension'];
        $base = $this->sanitizeRenameBaseName($newName, $ext);
        $newOriginalName = $base . '.' . $ext;

        $this->db->executePrepared(
            'UPDATE media_files SET original_name = ? WHERE id = ?',
            [$newOriginalName, $mediaFileId],
        );

        return $this->getFileById($mediaFileId);
    }

    public function deleteFile(int $mediaFileId): void
    {
        $row = $this->requireFileById($mediaFileId);
        $fullPath = $this->fullFilePath($row);
        if (is_file($fullPath) && !@unlink($fullPath)) {
            throw new AppError('Impossibile eliminare il file dallo storage', 500, ['error_code' => 'media_file_delete_failed']);
        }

        $this->db->executePrepared(
            'DELETE FROM media_files WHERE id = ?',
            [$mediaFileId],
        );
    }

    public function getFileById(int $mediaFileId): array
    {
        $row = $this->db->fetchOnePrepared(
            'SELECT mf.id, mf.stored_name, mf.original_name, mf.extension, mf.mime_type, mf.size, mf.path, mf.uploaded_by_user_id, mf.created_at, mf.updated_at
             FROM media_files mf
             WHERE mf.id = ? LIMIT 1',
            [$mediaFileId],
        );

        if (!$row) {
            return [];
        }

        $rows = $this->decorateRows([$row]);
        return $rows ? $rows[0] : [];
    }

    public function getFileAssetById(int $mediaFileId): array
    {
        $row = $this->requireFileById($mediaFileId);

        return [
            'row' => $this->decorateRows([$row])[0] ?? $row,
            'full_path' => $this->fullFilePath($row),
        ];
    }

    public function maxFileSizeBytes(): int
    {
        $row = $this->db->fetchOnePrepared(
            'SELECT `value` FROM sys_settings WHERE `key` = ? LIMIT 1',
            ['upload_max_mb'],
        );

        $value = 0;
        if (is_array($row)) {
            $value = (int) ($row['value'] ?? 0);
        } elseif (is_object($row) && isset($row->value)) {
            $value = (int) $row->value;
        }

        if ($value <= 0) {
            return self::DEFAULT_MAX_FILE_SIZE;
        }

        return $value * 1048576;
    }

    private function decorateRows(array $rows): array
    {
        $decorated = [];
        foreach ($rows as $row) {
            $item = is_object($row) ? (array) $row : $row;
            $userId = (int) ($item['uploaded_by_user_id'] ?? 0);
            $item['author_label'] = $userId > 0 ? ('Utente #' . $userId) : 'Sistema';
            $item['file_url'] = $this->buildFileUrl($item);
            $item['is_image'] = in_array(strtolower((string) ($item['extension'] ?? '')), self::IMAGE_EXTENSIONS, true);
            $decorated[] = $item;
        }

        return $decorated;
    }

    private function ensureBaseDir(): void
    {
        $this->ensureDirectoryNode(self::UPLOAD_ROOT, 'media_base_create_failed', 'Impossibile inizializzare la directory uploads');
        $this->ensureUploadGuards();
        $this->ensureDirectoryNode(self::UPLOAD_BASE, 'media_base_create_failed', 'Impossibile inizializzare la directory media');
        $this->ensurePlaceholderFile(self::UPLOAD_BASE . DIRECTORY_SEPARATOR . '.gitkeep', '');
    }

    private function ensureDirectoryNode(string $path, string $errorCode, string $message): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!@mkdir($path, 0755, true)) {
            throw new AppError($message, 500, ['error_code' => $errorCode]);
        }
    }

    private function ensureUploadGuards(): void
    {
        $htaccess = "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|php[0-9]?|cgi|pl|py|sh|env|htaccess)$\">\n    Require all denied\n</FilesMatch>\nRemoveHandler .php .phtml .phar\nRemoveType .php .phtml .phar\n";
        $indexHtml = '<!doctype html><title>403</title>';

        $this->ensurePlaceholderFile(self::UPLOAD_ROOT . DIRECTORY_SEPARATOR . '.htaccess', $htaccess);
        $this->ensurePlaceholderFile(self::UPLOAD_ROOT . DIRECTORY_SEPARATOR . 'index.html', $indexHtml);
        $this->ensurePlaceholderFile(self::UPLOAD_BASE . DIRECTORY_SEPARATOR . 'index.html', $indexHtml);
    }

    private function ensurePlaceholderFile(string $path, string $content): void
    {
        if (is_file($path)) {
            return;
        }

        @file_put_contents($path, $content);
    }

    private function ensureDirectory(string $relativePath): string
    {
        $this->ensureBaseDir();
        $relativePath = $this->normalizeRelativePath($relativePath);
        $baseReal = realpath(self::UPLOAD_BASE);
        if (!is_string($baseReal)) {
            throw new AppError('Directory media non disponibile', 500, ['error_code' => 'media_base_missing']);
        }

        if ($relativePath === '') {
            return $baseReal;
        }

        $target = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (is_dir($target)) {
            return $target;
        }

        if (!@mkdir($target, 0755, true)) {
            throw new AppError('Impossibile creare la cartella di destinazione', 500, ['error_code' => 'media_target_create_failed']);
        }

        $realTarget = realpath($target);
        if (!is_string($realTarget) || strpos($realTarget, $baseReal) !== 0) {
            throw new AppError('Percorso media non valido', 500, ['error_code' => 'media_target_invalid']);
        }

        return $realTarget;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace("\x00", '', $path);
        $path = trim(str_replace('\\', '/', $path), "/ \t\n\r");
        if ($path === '' || $path === '.') {
            return '';
        }

        $segments = preg_split('#/+#', $path) ?: [];
        $safe = [];
        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' || strpos($segment, '..') !== false) {
                throw AppError::validation('Percorso non consentito', [], 'media_path_traversal');
            }
            if ($this->isHiddenSegment($segment)) {
                throw AppError::validation('File e cartelle nascoste non sono consentiti', [], 'media_hidden_path');
            }
            $safe[] = $segment;
        }

        return implode('/', $safe);
    }

    private function resolveExistingDirectory(string $relativePath): string
    {
        $this->ensureBaseDir();
        $baseReal = realpath(self::UPLOAD_BASE);
        if (!is_string($baseReal)) {
            throw new AppError('Directory media non disponibile', 500, ['error_code' => 'media_base_missing']);
        }

        if ($relativePath === '') {
            return $baseReal;
        }

        $absolute = $baseReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $real = realpath($absolute);
        if (!is_string($real) || !is_dir($real) || strpos($real, $baseReal) !== 0) {
            throw AppError::notFound('Cartella non trovata', [], 'media_dir_not_found');
        }

        return $real;
    }

    private function relativeFromAbsolute(string $absolutePath): string
    {
        $baseReal = realpath(self::UPLOAD_BASE);
        if (!is_string($baseReal)) {
            return '';
        }

        $absolute = str_replace('\\', '/', $absolutePath);
        $base = str_replace('\\', '/', $baseReal);
        if ($absolute === $base) {
            return '';
        }

        return ltrim(substr($absolute, strlen($base)), '/');
    }

    private function sanitizeFolderName(string $folderName): string
    {
        $name = str_replace("\x00", '', $folderName);
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z0-9\s._-]/u', '', (string) $name);
        $name = preg_replace('/\s+/', ' ', (string) $name);
        $name = trim((string) $name, " \t\n\r\0\x0B.");

        if ($name === '' || strpos($name, '..') !== false || $this->isHiddenSegment($name)) {
            throw AppError::validation('Nome cartella non valido', [], 'media_invalid_folder_name');
        }

        return mb_substr($name, 0, 80, 'UTF-8');
    }

    private function sanitizeRenameBaseName(string $newName, string $extension): string
    {
        $value = str_replace("\x00", '', $newName);
        $value = basename(str_replace('\\', '/', $value));
        $value = preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '', (string) $value);
        $value = preg_replace('/[^a-zA-Z0-9\s._-]/u', '', (string) $value);
        $value = preg_replace('/\s+/', ' ', (string) $value);
        $value = trim((string) $value, " \t\n\r\0\x0B.");

        if ($value === '' || strpos($value, '..') !== false || $this->isHiddenSegment($value)) {
            throw AppError::validation('Nome file non valido', [], 'media_invalid_name');
        }

        return mb_substr($value, 0, 180, 'UTF-8');
    }

    private function requireFileById(int $mediaFileId): array
    {
        if ($mediaFileId <= 0) {
            throw AppError::validation('File non valido', [], 'media_invalid_file_id');
        }

        $row = $this->db->fetchOnePrepared(
            'SELECT id, stored_name, original_name, extension, mime_type, size, path, uploaded_by_user_id, created_at, updated_at
             FROM media_files
             WHERE id = ? LIMIT 1',
            [$mediaFileId],
        );

        if (!$row) {
            throw AppError::notFound('File non trovato', [], 'media_file_not_found');
        }

        return is_array($row) ? $row : (array) $row;
    }

    private function updatePathsAfterRename(string $oldPath, string $newPath): void
    {
        $this->db->executePrepared(
            'UPDATE media_files SET path = ? WHERE path = ?',
            [$newPath, $oldPath],
        );

        $this->db->executePrepared(
            'UPDATE media_files SET path = CONCAT(?, SUBSTRING(path, ?)) WHERE path LIKE ?',
            [$newPath, strlen($oldPath) + 1, $oldPath . '/%'],
        );
    }

    private function parentPath(string $path): string
    {
        $parts = explode('/', $path);
        array_pop($parts);
        return implode('/', array_filter($parts, static function ($part): bool {
            return $part !== '';
        }));
    }

    private function buildFileUrl(array $row): string
    {
        $fileId = (int) ($row['id'] ?? 0);
        $name = rawurlencode((string) ($row['original_name'] ?? 'file'));

        return '/admin/media/file/open?id=' . $fileId . '&name=' . $name;
    }

    private function fullFilePath(array $row): string
    {
        $path = $this->normalizeRelativePath((string) ($row['path'] ?? ''));
        $directory = $this->resolveExistingDirectory($path);
        $storedName = basename((string) ($row['stored_name'] ?? ''));
        $fullPath = $directory . DIRECTORY_SEPARATOR . $storedName;
        $realDirectory = realpath($directory);
        if (!is_string($realDirectory)) {
            throw new AppError('Directory file non disponibile', 500, ['error_code' => 'media_file_dir_missing']);
        }
        $candidate = str_replace('\\', '/', $fullPath);
        $base = str_replace('\\', '/', $realDirectory);
        if (strpos($candidate, $base) !== 0) {
            throw new AppError('Percorso file non valido', 500, ['error_code' => 'media_file_invalid_path']);
        }
        return $fullPath;
    }

    private function isHiddenSegment(string $segment): bool
    {
        return $segment !== '' && $segment[0] === '.';
    }
}
