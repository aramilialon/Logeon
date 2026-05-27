<?php

declare(strict_types=1);

use App\Services\CapabilityRegistry;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;
use Core\ModuleRuntime;

class Modules
{
    private const MODULE_DOCS_DIR = 'docs';
    private const MODULE_DOCS_MAX_BYTES = 1048576;

    private function requestDataObject()
    {
        $request = RequestData::fromGlobals();
        return InputValidator::postJsonObject($request, 'data', true);
    }

    private function emitJson(array $payload): void
    {
        ResponseEmitter::emit(ApiResponse::json($payload));
    }

    private function requireAdmin(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
    }

    private function readModuleId($data): string
    {
        return InputValidator::firstString($data, ['module_id', 'id'], '');
    }

    private function readBool($data, string $key): bool
    {
        return InputValidator::boolean($data, $key, false);
    }

    public function list()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $dataset = ModuleRuntime::instance()->manager()->listModules();
        $dataset = $this->attachModuleDocsMetadata($dataset);

        $query = [];
        if (isset($data->query) && is_object($data->query)) {
            $query = (array) $data->query;
        } elseif (isset($data->query) && is_array($data->query)) {
            $query = $data->query;
        }

        $page = max(1, (int) ($data->page ?? 1));
        $results = max(1, (int) ($data->results ?? 20));
        $orderBy = trim((string) ($data->orderBy ?? '__default__|ASC'));
        if ($orderBy === '') {
            $orderBy = '__default__|ASC';
        }

        $totalCount = is_array($dataset) ? count($dataset) : 0;

        $this->emitJson([
            'dataset' => $dataset,
            'properties' => [
                'query' => $query,
                'page' => $page,
                'results' => $results,
                'orderBy' => $orderBy,
                'tot' => [
                    'count' => $totalCount,
                ],
            ],
        ]);
        return $this;
    }

    public function docsList()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $moduleId = $this->readModuleId($data);
        if ($moduleId === '') {
            throw AppError::validation('Modulo non valido', [], 'module_not_found');
        }

        $manifest = $this->findModuleManifest($moduleId);
        $docs = $this->collectModuleDocs($manifest);

        $this->emitJson([
            'dataset' => $docs,
            'properties' => [
                'module_id' => $moduleId,
                'count' => count($docs),
            ],
        ]);
        return $this;
    }

    public function docsGet()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $moduleId = $this->readModuleId($data);
        if ($moduleId === '') {
            throw AppError::validation('Modulo non valido', [], 'module_not_found');
        }

        $relativePath = InputValidator::firstString($data, ['path', 'doc_path', 'file'], '');
        if ($relativePath === '') {
            throw AppError::validation('Documento non valido', [], 'module_doc_not_found');
        }

        $manifest = $this->findModuleManifest($moduleId);
        $docsRoot = $this->moduleDocsRootPath($manifest);
        if ($docsRoot === '') {
            throw AppError::validation('Documentazione non disponibile per questo modulo', [], 'module_docs_not_available');
        }

        $safeRelative = $this->sanitizeModuleDocRelativePath($relativePath);
        if ($safeRelative === '') {
            throw AppError::validation('Percorso documento non valido', [], 'module_doc_not_found');
        }

        $fullPath = $docsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safeRelative);
        $realFullPath = @realpath($fullPath);
        if (!is_string($realFullPath) || $realFullPath === '') {
            throw AppError::validation('Documento non trovato', [], 'module_doc_not_found');
        }

        $realRootPath = @realpath($docsRoot);
        if (!is_string($realRootPath) || $realRootPath === '') {
            throw AppError::validation('Documentazione non disponibile per questo modulo', [], 'module_docs_not_available');
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $realRootPath), '/') . '/';
        $normalizedDoc = str_replace('\\', '/', $realFullPath);
        if (strpos($normalizedDoc, $normalizedRoot) !== 0) {
            throw AppError::validation('Percorso documento non valido', [], 'module_doc_not_found');
        }

        if (!is_file($realFullPath) || !is_readable($realFullPath)) {
            throw AppError::validation('Documento non leggibile', [], 'module_doc_not_found');
        }

        $extension = strtolower((string) pathinfo($realFullPath, PATHINFO_EXTENSION));
        if ($extension !== 'md') {
            throw AppError::validation('Formato documento non supportato', [], 'module_doc_not_found');
        }

        $size = (int) (@filesize($realFullPath) ?: 0);
        if ($size > self::MODULE_DOCS_MAX_BYTES) {
            throw AppError::validation('Documento troppo grande per anteprima interna', [], 'module_doc_too_large');
        }

        $raw = @file_get_contents($realFullPath);
        if (!is_string($raw)) {
            throw AppError::validation('Impossibile leggere il documento', [], 'module_doc_not_found');
        }

        $title = $this->extractModuleDocTitle($raw, basename($realFullPath));
        $this->emitJson([
            'dataset' => [
                'module_id' => $moduleId,
                'path' => $safeRelative,
                'filename' => basename($realFullPath),
                'title' => $title,
                'body_markdown' => $raw,
                'updated_at' => date('c', (int) (@filemtime($realFullPath) ?: time())),
                'size_bytes' => $size,
            ],
        ]);
        return $this;
    }

    public function activate()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $moduleId = $this->readModuleId($data);
        if ($moduleId === '') {
            throw AppError::validation('Modulo non valido', [], 'module_not_found');
        }

        $result = ModuleRuntime::instance()->manager()->activate($moduleId);
        if (empty($result['ok'])) {
            throw AppError::validation(
                (string) ($result['message'] ?? 'Attivazione modulo non riuscita'),
                (array) ($result['payload'] ?? []),
                (string) ($result['error_code'] ?? 'module_activation_failed'),
            );
        }

        $this->emitJson(['dataset' => $result['dataset'] ?? ['module_id' => $moduleId, 'status' => 'active']]);
        return $this;
    }

    public function deactivate()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $moduleId = $this->readModuleId($data);
        $cascade = $this->readBool($data, 'cascade');
        if ($moduleId === '') {
            throw AppError::validation('Modulo non valido', [], 'module_not_found');
        }

        $result = ModuleRuntime::instance()->manager()->deactivate($moduleId, [
            'cascade' => $cascade ? 1 : 0,
        ]);
        if (empty($result['ok'])) {
            throw AppError::validation(
                (string) ($result['message'] ?? 'Disattivazione modulo non riuscita'),
                (array) ($result['payload'] ?? []),
                (string) ($result['error_code'] ?? 'module_activation_failed'),
            );
        }

        $this->emitJson(['dataset' => $result['dataset'] ?? ['module_id' => $moduleId, 'status' => 'inactive']]);
        return $this;
    }

    public function uninstall()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $moduleId = $this->readModuleId($data);
        $purge = $this->readBool($data, 'purge');

        if ($moduleId === '') {
            throw AppError::validation('Modulo non valido', [], 'module_not_found');
        }

        $result = ModuleRuntime::instance()->manager()->uninstall($moduleId, [
            'purge' => $purge ? 1 : 0,
        ]);
        if (empty($result['ok'])) {
            throw AppError::validation(
                (string) ($result['message'] ?? 'Disinstallazione modulo non riuscita'),
                (array) ($result['payload'] ?? []),
                (string) ($result['error_code'] ?? 'module_uninstall_failed'),
            );
        }

        $this->emitJson(['dataset' => $result['dataset'] ?? ['module_id' => $moduleId, 'status' => 'detected']]);
        return $this;
    }

    public function audit()
    {
        $this->requireAdmin();
        $result = ModuleRuntime::instance()->manager()->audit();
        if (empty($result['ok'])) {
            throw AppError::validation(
                (string) ($result['message'] ?? 'Audit moduli non riuscito'),
                (array) ($result['payload'] ?? []),
                (string) ($result['error_code'] ?? 'module_audit_failed'),
            );
        }

        $this->emitJson(['dataset' => $result['dataset'] ?? []]);
        return $this;
    }

    public function capabilities()
    {
        $this->requireAdmin();

        $rows = [];
        foreach (CapabilityRegistry::all() as $name => $available) {
            $rows[] = [
                'name' => (string) $name,
                'available' => $available ? 1 : 0,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) $left['name'], (string) $right['name']);
        });

        $this->emitJson(['dataset' => $rows]);
        return $this;
    }

    private function findModuleManifest(string $moduleId): array
    {
        $moduleId = trim($moduleId);
        if ($moduleId === '') {
            throw AppError::validation('Modulo non valido', [], 'module_not_found');
        }

        $manifests = ModuleRuntime::instance()->manager()->discover();
        $manifest = $manifests[$moduleId] ?? null;
        if (!is_array($manifest)) {
            throw AppError::validation('Modulo non trovato', [], 'module_not_found');
        }

        return $manifest;
    }

    private function attachModuleDocsMetadata($dataset): array
    {
        if (!is_array($dataset) || $dataset === []) {
            return is_array($dataset) ? $dataset : [];
        }

        $manifests = ModuleRuntime::instance()->manager()->discover();
        $index = [];
        foreach ($manifests as $moduleId => $manifest) {
            if (!is_array($manifest)) {
                continue;
            }
            $docs = $this->collectModuleDocs($manifest);
            $index[(string) $moduleId] = [
                'count' => count($docs),
            ];
        }

        foreach ($dataset as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $moduleId = trim((string) ($row['id'] ?? ''));
            $count = 0;
            if ($moduleId !== '' && isset($index[$moduleId])) {
                $count = (int) ($index[$moduleId]['count'] ?? 0);
            }
            $row['docs_count'] = $count;
            $row['docs_available'] = $count > 0 ? 1 : 0;
            $dataset[$i] = $row;
        }

        return $dataset;
    }

    private function collectModuleDocs(array $manifest): array
    {
        $docsRoot = $this->moduleDocsRootPath($manifest);
        if ($docsRoot === '') {
            return [];
        }

        $rows = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($docsRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower((string) $fileInfo->getExtension());
            if ($ext !== 'md') {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();
            $relative = substr(str_replace('\\', '/', $absolutePath), strlen(str_replace('\\', '/', $docsRoot)));
            $relative = ltrim((string) $relative, '/');
            if ($relative === '') {
                continue;
            }

            $raw = @file_get_contents($absolutePath);
            $title = $this->extractModuleDocTitle(is_string($raw) ? $raw : '', $fileInfo->getFilename());
            $rows[] = [
                'path' => $relative,
                'title' => $title,
                'filename' => $fileInfo->getFilename(),
                'size_bytes' => (int) ($fileInfo->getSize() ?: 0),
                'updated_at' => date('c', (int) ($fileInfo->getMTime() ?: time())),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['path'] ?? ''), (string) ($b['path'] ?? ''));
        });

        return $rows;
    }

    private function moduleDocsRootPath(array $manifest): string
    {
        $modulePath = trim((string) ($manifest['_path'] ?? ''));
        if ($modulePath === '') {
            return '';
        }
        $docsRoot = rtrim($modulePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::MODULE_DOCS_DIR;
        if (!is_dir($docsRoot) || !is_readable($docsRoot)) {
            return '';
        }
        return $docsRoot;
    }

    private function sanitizeModuleDocRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if ($path === '' || strpos($path, '..') !== false || strpos($path, "\0") !== false) {
            return '';
        }
        if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'md') {
            return '';
        }
        return $path;
    }

    private function extractModuleDocTitle(string $markdown, string $fallbackFilename): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $value = trim((string) $line);
                if ($value === '') {
                    continue;
                }
                if (preg_match('/^\#{1,6}\s+(.+)$/u', $value, $matches) === 1) {
                    return trim((string) ($matches[1] ?? ''));
                }
            }
        }

        $base = trim((string) pathinfo($fallbackFilename, PATHINFO_FILENAME));
        if ($base === '') {
            $base = trim($fallbackFilename);
        }
        return $base !== '' ? $base : 'Documento';
    }
}
