<?php

declare(strict_types=1);

use App\Models\MediaFile;
use App\Services\MediaManagerService;
use Core\AuthGuard;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;
use Core\Logging\LoggerInterface;

class MediaManager extends MediaFile
{
    /** @var LoggerInterface|null */
    private $logger = null;

    /** @var MediaManagerService|null */
    private $mediaService = null;

    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        return $this;
    }

    public function setMediaManagerService(MediaManagerService $service = null)
    {
        $this->mediaService = $service;
        return $this;
    }

    private function mediaService(): MediaManagerService
    {
        if ($this->mediaService instanceof MediaManagerService) {
            return $this->mediaService;
        }

        $this->mediaService = new MediaManagerService();
        return $this->mediaService;
    }

    private function requestData(?RequestData $req = null): RequestData
    {
        if ($req instanceof RequestData) {
            return $req;
        }

        return RequestData::fromGlobals();
    }

    private function emitJson(array $payload): void
    {
        ResponseEmitter::emit(ApiResponse::json($payload));
    }

    private function logFailure(string $action, Throwable $e): void
    {
        error_log(
            '[MediaManager] ' . $action
            . ' failed: ' . $e->getMessage()
            . ' in ' . $e->getFile()
            . ':' . $e->getLine(),
        );
    }

    public function actionList(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $viewer = $this->captureViewerContext('media.view');
        $viewer['scope_root'] = $this->ensureViewerScopeRoot($viewer);
        AuthGuard::releaseSession();

        try {
            $data = InputValidator::postJsonObject($req, 'data', true);
            $path = $this->requestString($req, $data, ['path']);
            $search = $this->requestString($req, $data, ['search']);
            $path = $this->mediaService()->validatePath($path);

            $result = $this->mediaService()->listDirectory($path, $search);
            $files = [];
            foreach ($result['files'] as $file) {
                $files[] = $this->serializeFileRow($file, $viewer);
            }

            $canUploadHere = $this->canUploadToPath($path, $viewer);
            $canManageFoldersHere = $this->canManageFoldersAtPath($path, $viewer);
            $canManageCurrentFolder = $this->canManageFolderNode($path, $viewer);

            $this->emitJson([
                'success' => true,
                'dataset' => [
                    'path' => $path,
                    'search' => $search,
                    'home_path' => (string) $viewer['scope_root'],
                    'breadcrumbs' => $this->buildBreadcrumbs($path),
                    'folders' => array_values($result['folders']),
                    'files' => $files,
                    'current_folder' => [
                        'path' => $path,
                        'name' => $path === '' ? 'Media' : basename(str_replace('\\\\', '/', $path)),
                        'is_root' => ($path === ''),
                        'can_rename' => $canManageCurrentFolder,
                        'can_delete' => $canManageCurrentFolder,
                        'can_create_children' => $canManageFoldersHere,
                    ],
                    'permissions' => [
                        'view' => true,
                        'upload' => $viewer['can_upload'],
                        'upload_here' => $canUploadHere,
                        'rename_own' => $viewer['can_rename_own'],
                        'delete_own' => $viewer['can_delete_own'],
                        'manage_all' => $viewer['can_manage_all'],
                        'manage_folders' => $viewer['can_manage_folders'],
                        'manage_folders_here' => $canManageFoldersHere,
                        'scope_root' => (string) $viewer['scope_root'],
                        'is_scoped' => ((string) $viewer['scope_root'] !== ''),
                    ],
                    'limits' => [
                        'max_file_size_bytes' => $this->mediaService()->maxFileSizeBytes(),
                        'max_file_size_mb' => round($this->mediaService()->maxFileSizeBytes() / 1048576, 2),
                    ],
                ],
            ]);
        } catch (AppError $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logFailure(__METHOD__, $e);
            throw new AppError('Impossibile caricare il Media Manager', 500, ['error_code' => 'media_list_failed']);
        }
    }

    public function actionUpload(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $viewer = $this->captureViewerContext('media.upload');
        $viewer['scope_root'] = $this->ensureViewerScopeRoot($viewer);
        AuthGuard::releaseSession();

        try {
            if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
                throw AppError::validation('Nessun file ricevuto', [], 'media_missing_file');
            }

            $file = $_FILES['file'];
            $data = InputValidator::postJsonObject($req, 'data', true);
            $path = $this->requestString($req, $data, ['path']);
            $path = $this->mediaService()->validatePath($path);

            if (!$this->canUploadToPath($path, $viewer)) {
                throw AppError::unauthorized('Per il tuo ruolo puoi caricare file solo nella tua cartella staff');
            }

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw AppError::validation('Upload non riuscito', ['upload_error' => (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)], 'media_upload_failed');
            }

            $originalName = $this->mediaService()->sanifyFilename((string) ($file['name'] ?? ''));
            $extension = $this->mediaService()->validateExtension($originalName);
            $size = (int) ($file['size'] ?? 0);
            $tmpName = (string) ($file['tmp_name'] ?? '');

            $this->mediaService()->validateFileSize($size);
            $mimeType = $this->mediaService()->validateMimeType($tmpName, $extension);
            $storedName = $this->mediaService()->generateStoredName($originalName, $extension);
            $savedPath = $this->mediaService()->saveFile($tmpName, $storedName, $path);
            $mediaFile = $this->mediaService()->persistMetadata(
                $storedName,
                $originalName,
                $extension,
                $mimeType,
                $size,
                $savedPath,
                (int) $viewer['user_id'],
            );

            $savedRow = $this->mediaService()->getFileById((int) $mediaFile->id);

            $this->emitJson([
                'success' => true,
                'dataset' => [
                    'path' => $savedPath,
                    'file' => $this->serializeFileRow($savedRow, $viewer),
                ],
            ]);
        } catch (AppError $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logFailure(__METHOD__, $e);
            throw new AppError('Upload non riuscito', 500, ['error_code' => 'media_upload_failed']);
        }
    }

    public function actionFolderCreate(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $viewer = $this->captureFolderManagerContext();
        $viewer['scope_root'] = $this->ensureViewerScopeRoot($viewer);
        AuthGuard::releaseSession();

        try {
            $data = InputValidator::postJsonObject($req, 'data', true);
            $path = $this->requestString($req, $data, ['path']);
            $path = $this->mediaService()->validatePath($path);
            $folderName = $this->requestString($req, $data, ['folder_name', 'name']);
            if ($folderName === '') {
                throw AppError::validation('Il nome della cartella e obbligatorio', [], 'media_folder_name_required');
            }
            if (!$this->canManageFoldersAtPath($path, $viewer)) {
                throw AppError::unauthorized('Puoi creare cartelle solo nella tua cartella staff');
            }

            $newPath = $this->mediaService()->createDirectory($folderName, $path);

            $this->emitJson([
                'success' => true,
                'dataset' => [
                    'path' => $newPath,
                    'folder' => [
                        'name' => basename(str_replace('\\\\', '/', $newPath)),
                        'path' => $newPath,
                    ],
                ],
                'message' => 'Cartella creata correttamente',
            ]);
        } catch (AppError $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logFailure(__METHOD__, $e);
            throw new AppError('Creazione cartella non riuscita', 500, ['error_code' => 'media_folder_create_failed']);
        }
    }

    public function actionFolderRename(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $viewer = $this->captureFolderManagerContext();
        $viewer['scope_root'] = $this->ensureViewerScopeRoot($viewer);
        AuthGuard::releaseSession();

        try {
            $data = InputValidator::postJsonObject($req, 'data', true);
            $path = $this->requestString($req, $data, ['path']);
            $path = $this->mediaService()->validatePath($path);
            $newName = $this->requestString($req, $data, ['new_name', 'name']);
            if ($newName === '') {
                throw AppError::validation('Il nuovo nome della cartella e obbligatorio', [], 'media_folder_name_required');
            }
            if (!$this->canManageFolderNode($path, $viewer)) {
                throw AppError::unauthorized('Puoi gestire solo sottocartelle della tua cartella staff');
            }

            $newPath = $this->mediaService()->renameDirectory($path, $newName);

            $this->emitJson([
                'success' => true,
                'dataset' => [
                    'path' => $newPath,
                    'folder' => [
                        'name' => basename(str_replace('\\\\', '/', $newPath)),
                        'path' => $newPath,
                    ],
                ],
                'message' => 'Cartella rinominata correttamente',
            ]);
        } catch (AppError $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logFailure(__METHOD__, $e);
            throw new AppError('Rinomina cartella non riuscita', 500, ['error_code' => 'media_folder_rename_failed']);
        }
    }

    public function actionFolderDelete(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $viewer = $this->captureFolderManagerContext();
        $viewer['scope_root'] = $this->ensureViewerScopeRoot($viewer);
        AuthGuard::releaseSession();

        try {
            $data = InputValidator::postJsonObject($req, 'data', true);
            $path = $this->requestString($req, $data, ['path']);
            $path = $this->mediaService()->validatePath($path);
            if ($path === '') {
                throw AppError::validation('Seleziona una cartella valida', [], 'media_invalid_path');
            }
            if (!$this->canManageFolderNode($path, $viewer)) {
                throw AppError::unauthorized('Puoi eliminare solo sottocartelle della tua cartella staff');
            }

            $this->mediaService()->deleteDirectory($path);

            $this->emitJson([
                'success' => true,
                'dataset' => [
                    'deleted_path' => $path,
                    'parent_path' => $this->parentPath($path),
                ],
                'message' => 'Cartella eliminata correttamente',
            ]);
        } catch (AppError $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->logFailure(__METHOD__, $e);
            throw new AppError('Eliminazione cartella non riuscita', 500, ['error_code' => 'media_folder_delete_failed']);
        }
    }

    public function actionFileRename(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $viewer = $this->captureViewerContext();

        try {
            $data = InputValidator::postJsonObject($req, 'data', true);
            $fileId = (int) ($data->file_id ?? 0);
            $this->checkFileOwnership($fileId, 'media.rename_own', true, $viewer);
            AuthGuard::releaseSession();

            $newName = $this->requestString($req, $data, ['new_name', 'name']);
            if ($newName === '') {
                throw AppError::validation('Il nuovo nome del file e obbligatorio', [], 'media_name_required');
            }

            $file = $this->mediaService()->renameFile($fileId, $newName);

            $this->emitJson([
                'success' => true,
                'dataset' => [
                    'file' => $this->serializeFileRow($file, $viewer),
                ],
                'message' => 'File rinominato correttamente',
            ]);
        } catch (AppError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logFailure(__METHOD__, $e);
            throw new AppError('Rinomina file non riuscita', 500, ['error_code' => 'media_file_rename_failed']);
        }
    }

    public function actionFileDelete(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $viewer = $this->captureViewerContext();

        try {
            $data = InputValidator::postJsonObject($req, 'data', true);
            $fileId = (int) ($data->file_id ?? 0);
            $this->checkFileOwnership($fileId, 'media.delete_own', true, $viewer);
            AuthGuard::releaseSession();

            $this->mediaService()->deleteFile($fileId);

            $this->emitJson([
                'success' => true,
                'dataset' => [
                    'file_id' => $fileId,
                ],
                'message' => 'File eliminato correttamente',
            ]);
        } catch (AppError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logFailure(__METHOD__, $e);
            throw new AppError('Eliminazione file non riuscita', 500, ['error_code' => 'media_file_delete_failed']);
        }
    }

    public function actionFileOpen(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $this->captureViewerContext('media.view');
        AuthGuard::releaseSession();

        $fileId = (int) $req->query('id', 0);
        if ($fileId <= 0) {
            throw AppError::validation('File non valido', [], 'media_invalid_file_id');
        }

        $asset = $this->mediaService()->getFileAssetById($fileId);
        $row = is_array($asset['row'] ?? null) ? $asset['row'] : [];
        $fullPath = (string) ($asset['full_path'] ?? '');

        if ($fullPath === '' || !is_file($fullPath)) {
            throw AppError::notFound('File non trovato', [], 'media_file_not_found');
        }

        $mimeType = (string) ($row['mime_type'] ?? 'application/octet-stream');
        $originalName = str_replace(["\r", "\n", '"'], '', (string) ($row['original_name'] ?? 'file'));
        $disposition = 'inline; filename="' . $originalName . '"; filename*=UTF-8\'\'' . rawurlencode($originalName);

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . (string) filesize($fullPath));
            header('Content-Disposition: ' . $disposition);
            header('X-Content-Type-Options: nosniff');
        }

        readfile($fullPath);
    }

    public function actionFileBulkDelete(?RequestData $req = null, ?ResponseEmitter $res = null): void
    {
        $req = $this->requestData($req);
        $viewer = $this->captureViewerContext();
        AuthGuard::releaseSession();

        try {
            $data = InputValidator::postJsonObject($req, 'data', true);
            $fileIds = InputValidator::arrayOfValues($data, 'file_ids', []);
            if (empty($fileIds)) {
                throw AppError::validation('Nessun file selezionato', [], 'media_bulk_empty');
            }

            $deleted = [];
            $failed = [];

            foreach ($fileIds as $rawId) {
                $fileId = (int) $rawId;
                if ($fileId <= 0) {
                    continue;
                }

                try {
                    $this->checkFileOwnership($fileId, 'media.delete_own', true, $viewer);
                    $this->mediaService()->deleteFile($fileId);
                    $deleted[] = $fileId;
                } catch (\Throwable $e) {
                    $failed[] = [
                        'id' => $fileId,
                        'error' => $e instanceof AppError ? $e->getMessage() : 'Operazione non riuscita',
                    ];
                }
            }

            $this->emitJson([
                'success' => empty($failed),
                'dataset' => [
                    'deleted_ids' => $deleted,
                    'failed' => $failed,
                ],
                'deleted' => count($deleted),
                'failed' => $failed,
            ]);
        } catch (AppError $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logFailure(__METHOD__, $e);
            throw new AppError('Eliminazione multipla non riuscita', 500, ['error_code' => 'media_bulk_delete_failed']);
        }
    }

    private function captureViewerContext(string $requiredCapability = ''): array
    {
        $isAdmin = AuthGuard::isAdmin();
        $canView = $isAdmin || AuthGuard::hasCapability('media.view');
        if ($requiredCapability === 'media.view' && !$canView) {
            throw AppError::unauthorized('Accesso al Media Manager non autorizzato');
        }

        $canUpload = $isAdmin || AuthGuard::hasCapability('media.upload');
        if ($requiredCapability === 'media.upload' && !$canUpload) {
            throw AppError::unauthorized('Permesso upload non disponibile');
        }

        return [
            'user_id' => (int) AuthGuard::userId(),
            'is_admin' => $isAdmin,
            'can_view' => $canView,
            'can_upload' => $canUpload,
            'can_manage_all' => $isAdmin || AuthGuard::hasCapability('media.manage_all'),
            'can_manage_folders' => $isAdmin || AuthGuard::hasCapability('media.manage_folders'),
            'can_rename_own' => $isAdmin || AuthGuard::hasCapability('media.rename_own'),
            'can_delete_own' => $isAdmin || AuthGuard::hasCapability('media.delete_own'),
            'scope_root' => '',
        ];
    }

    private function captureFolderManagerContext(): array
    {
        $viewer = $this->captureViewerContext();
        if (!$viewer['can_manage_folders']) {
            throw AppError::unauthorized('Permesso gestione cartelle non disponibile');
        }
        return $viewer;
    }

    private function ensureViewerScopeRoot(array $viewer): string
    {
        if (!empty($viewer['is_admin']) || !empty($viewer['can_manage_all'])) {
            return '';
        }

        if (empty($viewer['can_upload']) && empty($viewer['can_manage_folders'])) {
            return '';
        }

        $userId = (int) ($viewer['user_id'] ?? 0);
        if ($userId <= 0) {
            return '';
        }

        return $this->mediaService()->ensurePathExists('staff/' . $userId);
    }

    private function canUploadToPath(string $path, array $viewer): bool
    {
        if (empty($viewer['can_upload'])) {
            return false;
        }

        if (!empty($viewer['is_admin']) || !empty($viewer['can_manage_all'])) {
            return true;
        }

        return $this->pathWithinScope($path, (string) $viewer['scope_root']);
    }

    private function canManageFoldersAtPath(string $path, array $viewer): bool
    {
        if (empty($viewer['can_manage_folders'])) {
            return false;
        }

        if (!empty($viewer['is_admin']) || !empty($viewer['can_manage_all'])) {
            return true;
        }

        return $this->pathWithinScope($path, (string) $viewer['scope_root']);
    }

    private function canManageFolderNode(string $path, array $viewer): bool
    {
        if ($path === '') {
            return false;
        }

        if (!empty($viewer['is_admin']) || !empty($viewer['can_manage_all'])) {
            return true;
        }

        $scopeRoot = (string) $viewer['scope_root'];
        return $path !== $scopeRoot && $this->pathWithinScope($path, $scopeRoot);
    }

    private function pathWithinScope(string $path, string $scopeRoot): bool
    {
        $path = trim(str_replace('\\\\', '/', $path), '/');
        $scopeRoot = trim(str_replace('\\\\', '/', $scopeRoot), '/');

        if ($scopeRoot === '') {
            return true;
        }

        if ($path === '') {
            return false;
        }

        return $path === $scopeRoot || strpos($path, $scopeRoot . '/') === 0;
    }

    private function requestString(RequestData $req, object $data, array $keys): string
    {
        foreach ($keys as $key) {
            $postValue = trim((string) $req->post($key, ''));
            if ($postValue !== '') {
                return $postValue;
            }
            if (isset($data->{$key}) && trim((string) $data->{$key}) !== '') {
                return trim((string) $data->{$key});
            }
        }

        return '';
    }

    private function buildBreadcrumbs(string $path): array
    {
        $breadcrumbs = [[
            'label' => 'Media',
            'path' => '',
            'active' => ($path === ''),
        ]];

        if ($path === '') {
            return $breadcrumbs;
        }

        $segments = explode('/', $path);
        $current = '';
        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : ($current . '/' . $segment);
            $breadcrumbs[] = [
                'label' => $segment,
                'path' => $current,
                'active' => ($current === $path),
            ];
        }

        return $breadcrumbs;
    }

    private function serializeFileRow(array $file, array $viewer): array
    {
        $ownerUserId = (int) ($file['uploaded_by_user_id'] ?? 0);
        $isOwner = $ownerUserId > 0 && $ownerUserId === (int) $viewer['user_id'];
        $canManage = $viewer['is_admin'] || $viewer['can_manage_all'] || ($isOwner && $viewer['can_rename_own']);
        $canDelete = $viewer['is_admin'] || $viewer['can_manage_all'] || ($isOwner && $viewer['can_delete_own']);

        return [
            'id' => (int) ($file['id'] ?? 0),
            'stored_name' => (string) ($file['stored_name'] ?? ''),
            'name' => (string) ($file['original_name'] ?? ''),
            'original_name' => (string) ($file['original_name'] ?? ''),
            'extension' => (string) ($file['extension'] ?? ''),
            'mime_type' => (string) ($file['mime_type'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
            'size_label' => $this->formatBytes((int) ($file['size'] ?? 0)),
            'path' => (string) ($file['path'] ?? ''),
            'url' => (string) ($file['file_url'] ?? ''),
            'is_image' => !empty($file['is_image']),
            'created_at' => (string) ($file['created_at'] ?? ''),
            'updated_at' => (string) ($file['updated_at'] ?? ''),
            'uploaded_by_user_id' => $ownerUserId,
            'author_label' => (string) ($file['author_label'] ?? ('Utente #' . $ownerUserId)),
            'can_rename' => $canManage,
            'can_delete' => $canDelete,
        ];
    }

    private function checkFileOwnership(int $fileId, string $ownerCapability, bool $throw = true, array $viewer = []): bool
    {
        if ($fileId <= 0) {
            if ($throw) {
                throw AppError::validation('File non valido', [], 'media_invalid_file_id');
            }
            return false;
        }

        if (!$viewer) {
            $viewer = $this->captureViewerContext();
        }

        if (!empty($viewer['is_admin'])) {
            return true;
        }

        $file = static::db()->fetchOnePrepared(
            'SELECT uploaded_by_user_id FROM media_files WHERE id = ? LIMIT 1',
            [$fileId],
        );

        if (!$file) {
            if ($throw) {
                throw AppError::notFound('File non trovato', [], 'media_file_not_found');
            }
            return false;
        }

        $row = is_array($file) ? $file : (array) $file;
        $ownerUserId = (int) ($row['uploaded_by_user_id'] ?? 0);
        $currentUserId = (int) ($viewer['user_id'] ?? 0);
        $isOwner = $ownerUserId > 0 && $ownerUserId === $currentUserId;

        if (!$isOwner && empty($viewer['can_manage_all'])) {
            if ($throw) {
                throw AppError::unauthorized('Non puoi gestire questo file');
            }
            return false;
        }

        if ($isOwner) {
            $hasCapability = true;
            if ($ownerCapability === 'media.rename_own') {
                $hasCapability = !empty($viewer['can_rename_own']);
            } elseif ($ownerCapability === 'media.delete_own') {
                $hasCapability = !empty($viewer['can_delete_own']);
            }

            if (!$hasCapability) {
                if ($throw) {
                    throw AppError::unauthorized('Permesso insufficiente per gestire il file');
                }
                return false;
            }
        }

        return true;
    }

    private function parentPath(string $path): string
    {
        $parts = explode('/', trim($path, '/'));
        array_pop($parts);
        return implode('/', array_filter($parts, static function ($part): bool {
            return $part !== '';
        }));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }
        return $bytes . ' B';
    }
}
