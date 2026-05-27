<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Http\AppError;

class MediaService
{
    // ── Whitelist ─────────────────────────────────────────────────────────────

    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'txt', 'md'];

    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
        'image/gif' => true,
        'text/plain' => true,
        'text/markdown' => true,
        'text/x-markdown' => true,
    ];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB

    // ── State ─────────────────────────────────────────────────────────────────

    /** @var DbAdapterInterface */
    private $db;

    /** @var string Absolute path to uploads/media/ with no trailing slash */
    private $baseDir;

    public function __construct(DbAdapterInterface $db = null)
    {
        $this->db = $db ?: DbAdapterFactory::createFromConfig();
        $projectRoot = realpath(dirname(__DIR__, 2));
        $this->baseDir = $projectRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'media';
    }

    // ── DB helpers ────────────────────────────────────────────────────────────

    private function firstPrepared(string $sql, array $params = [])
    {
        return $this->db->fetchOnePrepared($sql, $params);
    }

    private function fetchPrepared(string $sql, array $params = []): array
    {
        return $this->db->fetchAllPrepared($sql, $params);
    }

    private function execPrepared(string $sql, array $params = []): void
    {
        $this->db->executePrepared($sql, $params);
    }

    private function fail(string $message, string $code = 'validation_error'): void
    {
        throw AppError::validation($message, [], $code);
    }

    // ── Path security ─────────────────────────────────────────────────────────

    /**
     * Validates and resolves a relative path within baseDir.
     * Throws on path traversal or out-of-bounds access.
     */
    private function resolveDir(string $relativePath): string
    {
        $relative = $this->normalizePath($relativePath);

        if ($relative === '') {
            return $this->baseDir;
        }

        $full = $this->baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $real = realpath($full);

        if ($real === false) {
            // Directory does not exist yet — check the parent chain
            $real = $full;
        }

        // Ensure resolved path starts with baseDir
        $basePart = realpath($this->baseDir);
        if ($basePart === false || strpos($real, $basePart . DIRECTORY_SEPARATOR) !== 0
            && $real !== $basePart) {
            $this->fail('Percorso non consentito', 'path_traversal');
        }

        return $real;
    }

    /**
     * Normalises a user-supplied relative path:
     * - strips leading/trailing slashes
     * - rejects any segment that is '..' or starts with '.'
     * - collapses empty segments
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path, "/\\ \t\n\r");
        if ($path === '' || $path === '.') {
            return '';
        }

        $parts = preg_split('#[/\\\\]+#', $path);
        $safe = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..' || strpos($part, '..') !== false) {
                $this->fail('Percorso non consentito', 'path_traversal');
            }
            if ($part[0] === '.') {
                $this->fail('I nomi nascosti non sono consentiti', 'hidden_name');
            }
            $safe[] = $part;
        }

        return implode('/', $safe);
    }

    /**
     * Validates a folder/file segment name (no path separators, no dots, no special chars).
     */
    private function validateFolderName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 80) {
            $this->fail('Nome cartella non valido (max 80 caratteri)', 'invalid_folder_name');
        }
        if ($name[0] === '.') {
            $this->fail('I nomi nascosti non sono consentiti', 'hidden_name');
        }
        if (!preg_match('/^[a-zA-Z0-9_\-\. ]+$/u', $name)) {
            $this->fail('Nome cartella non valido: usare solo lettere, numeri, trattini, underscore e spazi', 'invalid_folder_name');
        }
        if (strpos($name, '..') !== false) {
            $this->fail('Percorso non consentito', 'path_traversal');
        }
        return $name;
    }

    // ── Filesystem helpers ────────────────────────────────────────────────────

    private function ensureBaseDir(): void
    {
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    private function listSubfolders(string $absoluteDir): array
    {
        if (!is_dir($absoluteDir)) {
            return [];
        }

        $items = scandir($absoluteDir);
        $folders = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            if ($item[0] === '.') {
                continue;
            }
            if (is_dir($absoluteDir . DIRECTORY_SEPARATOR . $item)) {
                $folders[] = $item;
            }
        }

        sort($folders);
        return $folders;
    }

    // ── File validation ───────────────────────────────────────────────────────

    private function validateExtension(string $ext): string
    {
        $ext = strtolower(trim($ext, '.'));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            $this->fail(
                'Estensione non consentita: ' . $ext . '. Consentite: ' . implode(', ', self::ALLOWED_EXTENSIONS),
                'extension_not_allowed',
            );
        }
        return $ext;
    }

    private function validateMimeType(string $tmpPath): string
    {
        if (!function_exists('finfo_open')) {
            return 'application/octet-stream';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!isset(self::ALLOWED_MIME_TYPES[$mime])) {
            $this->fail('Tipo file non consentito: ' . $mime, 'mime_not_allowed');
        }
        return (string) $mime;
    }

    private function sanitizeOriginalName(string $name): string
    {
        $name = trim($name);
        $name = basename($name);
        $name = preg_replace('/[^\w\.\-]/u', '_', $name);
        return mb_substr($name, 0, 200, 'UTF-8');
    }

    private function generateStoredName(string $ext): string
    {
        return uniqid('', true) . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Lists folders and files at the given relative path.
     */
    public function list(array $filters, int $limit, int $page, string $orderBy): array
    {
        $this->ensureBaseDir();

        $relativePath = $this->normalizePath($filters['path'] ?? '');
        $search = trim((string) ($filters['search'] ?? ''));
        $absoluteDir = $this->resolveDir($relativePath);

        // Folders (filesystem only, no pagination applied to folders)
        $folders = $this->listSubfolders($absoluteDir);

        // Files (DB query)
        $limit = max(1, min(100, $limit));
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;

        $where = ['mf.path = ?'];
        $params = [$relativePath];

        if ($search !== '') {
            $where[] = 'mf.original_name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $allowedOrder = ['mf.original_name', 'mf.size', 'mf.created_at', 'mf.extension'];
        $allowedDir = ['ASC', 'DESC'];
        $orderParts = explode('|', $orderBy);
        $orderColCandidate = $orderParts[0];
        $orderDirCandidate = strtoupper($orderParts[1] ?? 'ASC');
        $orderCol = in_array($orderColCandidate, $allowedOrder, true) ? $orderColCandidate : 'mf.original_name';
        $orderDir = in_array($orderDirCandidate, $allowedDir, true) ? $orderDirCandidate : 'ASC';

        $whereClause = implode(' AND ', $where);

        $totalRow = $this->firstPrepared(
            'SELECT COUNT(*) AS cnt FROM media_files mf WHERE ' . $whereClause,
            $params,
        );
        $total = (int) ($totalRow->cnt ?? 0);

        $rows = $this->fetchPrepared(
            'SELECT mf.id, mf.stored_name, mf.original_name, mf.extension, mf.mime_type,
                    mf.size, mf.path, mf.uploaded_by_user_id, mf.created_at, mf.updated_at,
                    u.email AS uploader_email
             FROM media_files mf
             LEFT JOIN users u ON u.id = mf.uploaded_by_user_id
             WHERE ' . $whereClause . '
             ORDER BY ' . $orderCol . ' ' . $orderDir . '
             LIMIT ? OFFSET ?',
            array_merge($params, [$limit, $offset]),
        );

        return [
            'folders' => $folders,
            'rows' => $rows ?: [],
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'path' => $relativePath,
        ];
    }

    /**
     * Uploads a file. Returns the DB row array.
     * $fileData = ['name'=>..., 'tmp_name'=>..., 'size'=>..., 'error'=>...]
     */
    public function upload(string $relativePath, array $fileData, int $userId): array
    {
        $this->ensureBaseDir();

        if (($fileData['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->fail('Errore durante l\'upload del file', 'upload_error');
        }

        $size = (int) ($fileData['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_FILE_SIZE_BYTES) {
            $this->fail(
                'Dimensione file non valida (max ' . (self::MAX_FILE_SIZE_BYTES / 1024 / 1024) . ' MB)',
                'file_too_large',
            );
        }

        $tmpPath = (string) ($fileData['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpPath)) {
            $this->fail('File non valido', 'invalid_file');
        }

        $originalName = $this->sanitizeOriginalName((string) ($fileData['name'] ?? ''));
        $ext = $this->validateExtension(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $this->validateMimeType($tmpPath);

        $relativePath = $this->normalizePath($relativePath);
        $absoluteDir = $this->baseDir . ($relativePath !== '' ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath) : '');

        if (!is_dir($absoluteDir)) {
            if (!mkdir($absoluteDir, 0755, true)) {
                $this->fail('Impossibile creare la directory di destinazione', 'mkdir_failed');
            }
        }

        $storedName = $this->generateStoredName($ext);
        $destination = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($tmpPath, $destination)) {
            $this->fail('Impossibile salvare il file', 'move_failed');
        }

        $this->execPrepared(
            'INSERT INTO media_files (stored_name, original_name, extension, mime_type, size, path, uploaded_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$storedName, $originalName, $ext, $mime, $size, $relativePath, $userId > 0 ? $userId : null],
        );

        $id = (int) $this->db->lastInsertId();
        return $this->getById($id);
    }

    /**
     * Creates a new subfolder within the given path.
     */
    public function createFolder(string $relativePath, string $folderName): array
    {
        $this->ensureBaseDir();

        $relativePath = $this->normalizePath($relativePath);
        $folderName = $this->validateFolderName($folderName);

        $parentDir = $this->resolveDir($relativePath);
        $newDirFull = $parentDir . DIRECTORY_SEPARATOR . $folderName;

        if (file_exists($newDirFull)) {
            $this->fail('Esiste già una cartella con questo nome', 'folder_exists');
        }

        if (!mkdir($newDirFull, 0755, true)) {
            $this->fail('Impossibile creare la cartella', 'mkdir_failed');
        }

        $newRelativePath = $relativePath !== '' ? $relativePath . '/' . $folderName : $folderName;
        return ['name' => $folderName, 'path' => $newRelativePath];
    }

    /**
     * Renames a subfolder within the given parent path.
     */
    public function renameFolder(string $parentPath, string $oldName, string $newName): array
    {
        $parentPath = $this->normalizePath($parentPath);
        $oldName = $this->validateFolderName($oldName);
        $newName = $this->validateFolderName($newName);

        $parentDir = $this->resolveDir($parentPath);
        $oldDir = $parentDir . DIRECTORY_SEPARATOR . $oldName;
        $newDir = $parentDir . DIRECTORY_SEPARATOR . $newName;

        if (!is_dir($oldDir)) {
            $this->fail('Cartella originale non trovata', 'folder_not_found');
        }
        if (file_exists($newDir)) {
            $this->fail('Esiste già una cartella con questo nome', 'folder_exists');
        }

        if (!rename($oldDir, $newDir)) {
            $this->fail('Impossibile rinominare la cartella', 'rename_failed');
        }

        // Update DB paths for all files inside the renamed folder
        $oldRelative = $parentPath !== '' ? $parentPath . '/' . $oldName : $oldName;
        $newRelative = $parentPath !== '' ? $parentPath . '/' . $newName : $newName;
        $this->updatePathsAfterRename($oldRelative, $newRelative);

        return ['name' => $newName, 'path' => $newRelative];
    }

    /**
     * Deletes an empty folder.
     */
    public function deleteFolder(string $parentPath, string $folderName): bool
    {
        $parentPath = $this->normalizePath($parentPath);
        $folderName = $this->validateFolderName($folderName);
        $parentDir = $this->resolveDir($parentPath);
        $dirToDelete = $parentDir . DIRECTORY_SEPARATOR . $folderName;

        if (!is_dir($dirToDelete)) {
            $this->fail('Cartella non trovata', 'folder_not_found');
        }

        $items = scandir($dirToDelete);
        $items = array_filter($items, fn ($i) => $i !== '.' && $i !== '..' && $i[0] !== '.');
        if (count($items) > 0) {
            $this->fail('La cartella non è vuota. Elimina prima i file contenuti', 'folder_not_empty');
        }

        if (!rmdir($dirToDelete)) {
            $this->fail('Impossibile eliminare la cartella', 'rmdir_failed');
        }

        return true;
    }

    /**
     * Renames a file (original_name only; extension locked; stored_name unchanged).
     */
    public function renameFile(int $id, string $newBaseName): array
    {
        $row = $this->getById($id);
        if (empty($row)) {
            $this->fail('File non trovato', 'not_found');
        }

        $newBaseName = trim((string) $newBaseName);
        if ($newBaseName === '') {
            $this->fail('Il nome del file non può essere vuoto', 'empty_name');
        }

        // Strip any extension the user might have typed — we always keep the original one
        $ext = (string) $row['extension'];
        $newBaseNoExt = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $newBaseName);
        $newBaseNoExt = preg_replace('/[^\w\.\-\s]/u', '_', trim($newBaseNoExt));
        $newOriginalName = $newBaseNoExt . '.' . $ext;

        if (mb_strlen($newOriginalName, 'UTF-8') > 255) {
            $this->fail('Nome file troppo lungo (max 255 caratteri)', 'name_too_long');
        }

        $this->execPrepared(
            'UPDATE media_files SET original_name = ? WHERE id = ?',
            [$newOriginalName, $id],
        );

        return $this->getById($id);
    }

    /**
     * Deletes a file: removes the physical file and the DB record.
     * $isAdmin = true bypasses the owner check.
     */
    public function deleteFile(int $id, int $userId, bool $isAdmin = false): bool
    {
        $row = $this->getById($id);
        if (empty($row)) {
            $this->fail('File non trovato', 'not_found');
        }

        if (!$isAdmin && (int) ($row['uploaded_by_user_id'] ?? 0) !== $userId) {
            $this->fail('Non hai il permesso di eliminare questo file', 'forbidden');
        }

        $this->removePhysicalFile($row);
        $this->execPrepared('DELETE FROM media_files WHERE id = ?', [$id]);
        return true;
    }

    /**
     * Bulk-deletes files. Returns array of deleted IDs.
     */
    public function bulkDeleteFiles(array $ids, int $userId, bool $isAdmin = false): array
    {
        $deleted = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            try {
                $this->deleteFile($id, $userId, $isAdmin);
                $deleted[] = $id;
            } catch (\Throwable $e) {
                // Skip files that cannot be deleted (permission or not found)
            }
        }
        return $deleted;
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    public function getById(int $id): array
    {
        $row = $this->firstPrepared(
            'SELECT mf.*, u.email AS uploader_email
             FROM media_files mf
             LEFT JOIN users u ON u.id = mf.uploaded_by_user_id
             WHERE mf.id = ? LIMIT 1',
            [$id],
        );
        return $row ? (array) $row : [];
    }

    private function removePhysicalFile(array $row): void
    {
        $path = (string) ($row['path'] ?? '');
        $storedName = (string) ($row['stored_name'] ?? '');
        if ($storedName === '') {
            return;
        }

        $dir = $this->baseDir . ($path !== '' ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path) : '');
        $full = $dir . DIRECTORY_SEPARATOR . $storedName;

        // Safety: ensure we're inside baseDir
        $realBase = realpath($this->baseDir);
        $realFull = realpath($full);
        if ($realFull !== false && $realBase !== false && strpos($realFull, $realBase) === 0) {
            @unlink($full);
        }
    }

    private function updatePathsAfterRename(string $oldPrefix, string $newPrefix): void
    {
        // Exact match
        $this->execPrepared(
            'UPDATE media_files SET path = ? WHERE path = ?',
            [$newPrefix, $oldPrefix],
        );
        // Sub-paths (with trailing slash)
        $this->execPrepared(
            'UPDATE media_files SET path = CONCAT(?, SUBSTRING(path, ?)) WHERE path LIKE ?',
            [$newPrefix, strlen($oldPrefix) + 1, $oldPrefix . '/%'],
        );
    }

    // ── URL builder ───────────────────────────────────────────────────────────

    public function fileUrl(array $row): string
    {
        $path = (string) ($row['path'] ?? '');
        $name = (string) ($row['stored_name'] ?? '');
        $base = '/uploads/media/';
        return $base . ($path !== '' ? $path . '/' : '') . $name;
    }

    public static function isImage(string $ext): bool
    {
        return in_array(strtolower($ext), self::IMAGE_EXTENSIONS, true);
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
