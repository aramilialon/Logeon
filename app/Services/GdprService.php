<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Http\AppError;

class GdprService
{
    /** @var DbAdapterInterface */
    private $db;
    /** @var bool */
    private $tablesEnsured = false;

    public function __construct(DbAdapterInterface $db = null)
    {
        $this->db = $db ?: DbAdapterFactory::createFromConfig();
    }

    private function firstPrepared(string $sql, array $params = [])
    {
        return $this->db->fetchOnePrepared($sql, $params);
    }

    private function allPrepared(string $sql, array $params = []): array
    {
        $rows = $this->db->fetchAllPrepared($sql, $params);
        return is_array($rows) ? $rows : [];
    }

    private function execPrepared(string $sql, array $params = []): void
    {
        $this->db->executePrepared($sql, $params);
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = $this->firstPrepared(
            'SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            [$table, $column],
        );

        return !empty($row);
    }

    private function selectColumnOrNull(string $table, string $column, ?string $alias = null): string
    {
        $resolvedAlias = $alias ?: $column;
        return $this->columnExists($table, $column)
            ? $column
            : ('NULL AS ' . $resolvedAlias);
    }

    private function cryptKey(): string
    {
        if (!defined('DB')) {
            return '';
        }

        return (string) DB['crypt_key'];
    }

    private function ensureTables(): void
    {
        if ($this->tablesEnsured) {
            return;
        }

        $this->execPrepared(
            'CREATE TABLE IF NOT EXISTS user_legal_consents (
                user_id INT(11) UNSIGNED NOT NULL,
                privacy_policy_version VARCHAR(32) NOT NULL DEFAULT "",
                terms_of_service_version VARCHAR(32) NOT NULL DEFAULT "",
                accepted_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                source VARCHAR(64) NOT NULL DEFAULT "",
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            [],
        );
        $this->execPrepared(
            'CREATE TABLE IF NOT EXISTS legal_consent_logs (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(11) UNSIGNED NOT NULL,
                consent_key VARCHAR(64) NOT NULL DEFAULT "",
                consent_version VARCHAR(32) NOT NULL DEFAULT "",
                value TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                source VARCHAR(64) NOT NULL DEFAULT "",
                ip_address VARCHAR(64) NULL,
                user_agent VARCHAR(500) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_legal_consent_logs_user (user_id),
                KEY idx_legal_consent_logs_key (consent_key),
                KEY idx_legal_consent_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            [],
        );
        $this->execPrepared(
            'CREATE TABLE IF NOT EXISTS gdpr_requests (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(11) UNSIGNED NOT NULL,
                request_type VARCHAR(64) NOT NULL DEFAULT "",
                status VARCHAR(32) NOT NULL DEFAULT "pending",
                note TEXT NULL,
                payload_json LONGTEXT NULL,
                handled_by_user_id INT(11) UNSIGNED NULL,
                handled_at DATETIME NULL,
                resolution_note TEXT NULL,
                source VARCHAR(64) NOT NULL DEFAULT "",
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_gdpr_requests_user_status (user_id, status),
                KEY idx_gdpr_requests_type (request_type),
                KEY idx_gdpr_requests_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            [],
        );
        $this->execPrepared(
            'CREATE TABLE IF NOT EXISTS cookie_consent_logs (
                id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(11) UNSIGNED NULL,
                consent_key VARCHAR(64) NOT NULL DEFAULT "cookie_policy",
                consent_version VARCHAR(32) NOT NULL DEFAULT "",
                value TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                preferences_json LONGTEXT NULL,
                source VARCHAR(64) NOT NULL DEFAULT "",
                ip_address VARCHAR(64) NULL,
                user_agent VARCHAR(500) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_cookie_consent_logs_user (user_id),
                KEY idx_cookie_consent_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            [],
        );
        $this->tablesEnsured = true;
    }

    public function legalContext(): array
    {
        $legal = defined('APP') && is_array(APP) && isset(APP['legal']) && is_array(APP['legal'])
            ? APP['legal']
            : [];

        $privacyVersion = trim((string) ($legal['privacy_policy_version'] ?? '1.0.0'));
        $termsVersion = trim((string) ($legal['terms_of_service_version'] ?? '1.0.0'));
        $cookieVersion = trim((string) ($legal['cookie_policy_version'] ?? '1.0.0'));

        return [
            'privacy_policy_version' => $privacyVersion !== '' ? $privacyVersion : '1.0.0',
            'terms_of_service_version' => $termsVersion !== '' ? $termsVersion : '1.0.0',
            'cookie_policy_version' => $cookieVersion !== '' ? $cookieVersion : '1.0.0',
            'privacy_policy_url' => (string) ($legal['privacy_policy_url'] ?? '/privacy-policy'),
            'terms_of_service_url' => (string) ($legal['terms_of_service_url'] ?? '/terms-of-service'),
            'cookie_policy_url' => (string) ($legal['cookie_policy_url'] ?? '/cookie-policy'),
            'privacy_contact_name' => (string) ($legal['privacy_contact_name'] ?? (defined('APP') ? (string) (APP['dba_name'] ?? '') : '')),
            'privacy_contact_email' => (string) ($legal['privacy_contact_email'] ?? (defined('APP') ? (string) (APP['dba_email'] ?? '') : '')),
        ];
    }

    public function recordSignupLegalAcceptances(int $userId, string $ip, ?string $userAgent = null, string $source = 'signup'): void
    {
        if ($userId <= 0) {
            throw AppError::validation('Utente non valido', [], 'user_invalid');
        }

        $this->ensureTables();
        $context = $this->legalContext();
        $privacyVersion = (string) $context['privacy_policy_version'];
        $termsVersion = (string) $context['terms_of_service_version'];

        $this->execPrepared(
            'INSERT INTO user_legal_consents
                (user_id, privacy_policy_version, terms_of_service_version, accepted_at, source, updated_at)
             VALUES (?, ?, ?, NOW(), ?, NOW())
             ON DUPLICATE KEY UPDATE
                privacy_policy_version = VALUES(privacy_policy_version),
                terms_of_service_version = VALUES(terms_of_service_version),
                accepted_at = NOW(),
                source = VALUES(source),
                updated_at = NOW()',
            [$userId, $privacyVersion, $termsVersion, $source],
        );

        $this->execPrepared(
            'INSERT INTO legal_consent_logs
                (user_id, consent_key, consent_version, value, source, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, NOW())',
            [$userId, 'privacy_policy', $privacyVersion, $source, $ip, $userAgent],
        );
        $this->execPrepared(
            'INSERT INTO legal_consent_logs
                (user_id, consent_key, consent_version, value, source, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, NOW())',
            [$userId, 'terms_of_service', $termsVersion, $source, $ip, $userAgent],
        );
    }

    public function listRequestsByUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $this->ensureTables();
        return $this->allPrepared(
            'SELECT id, request_type, status, note, source, created_at, updated_at, handled_at, resolution_note
             FROM gdpr_requests
             WHERE user_id = ?
             ORDER BY id DESC',
            [$userId],
        );
    }

    public function listRequestsForAdmin(string $status = 'all', string $requestType = '', int $page = 1, int $results = 20, bool $includeEmail = false): array
    {
        $this->ensureTables();

        $status = strtolower(trim($status));
        $allowedStatuses = ['pending', 'in_review', 'completed', 'rejected', 'cancelled'];
        if ($status !== 'all' && !in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $type = strtolower(trim($requestType));
        $allowedTypes = ['export_data', 'delete_account'];
        if ($type !== '' && !in_array($type, $allowedTypes, true)) {
            $type = '';
        }

        if ($page < 1) {
            $page = 1;
        }
        if ($results < 1) {
            $results = 20;
        } elseif ($results > 100) {
            $results = 100;
        }
        $offset = ($page - 1) * $results;

        $where = [];
        $params = [];
        if ($status !== 'all') {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($type !== '') {
            $where[] = 'r.request_type = ?';
            $params[] = $type;
        }
        $whereSql = '';
        if (!empty($where)) {
            $whereSql = ' WHERE ' . implode(' AND ', $where);
        }

        $emailSelect = $includeEmail
            ? 'CAST(AES_DECRYPT(u.email, ?) AS CHAR(255)) AS email'
            : 'NULL AS email';

        $datasetParams = [];
        if ($includeEmail) {
            $datasetParams[] = $this->cryptKey();
        }
        $datasetParams = array_merge($datasetParams, $params);
        $datasetParams[] = $results;
        $datasetParams[] = $offset;
        $dataset = $this->allPrepared(
            'SELECT r.id,
                    r.user_id,
                    r.request_type,
                    r.status,
                    r.note,
                    r.source,
                    r.created_at,
                    r.updated_at,
                    r.handled_at,
                    r.resolution_note,
                    CASE
                        WHEN r.request_type = "export_data"
                             AND r.payload_json IS NOT NULL
                             AND LENGTH(TRIM(r.payload_json)) > 0
                        THEN 1
                        ELSE 0
                    END AS payload_ready,
                    ' . $emailSelect . '
             FROM gdpr_requests r
             INNER JOIN users u ON u.id = r.user_id
             ' . $whereSql . '
             ORDER BY r.id DESC
             LIMIT ? OFFSET ?',
            $datasetParams,
        );

        $countRow = $this->firstPrepared(
            'SELECT COUNT(*) AS cnt
             FROM gdpr_requests r
             ' . $whereSql,
            $params,
        );

        return [
            'dataset' => $dataset,
            'page' => $page,
            'results_page' => $results,
            'tot' => (int) ($countRow->cnt ?? 0),
            'query' => [
                'status' => $status,
                'request_type' => $type,
            ],
        ];
    }

    public function createRequest(int $userId, string $requestType, string $note = '', string $source = 'settings'): array
    {
        if ($userId <= 0) {
            throw AppError::validation('Utente non valido', [], 'user_invalid');
        }

        $this->ensureTables();
        $type = strtolower(trim($requestType));
        $allowedTypes = ['export_data', 'delete_account'];
        if (!in_array($type, $allowedTypes, true)) {
            throw AppError::validation('Tipo richiesta non valido', [], 'gdpr_request_type_invalid');
        }

        $existing = $this->firstPrepared(
            'SELECT id, status
             FROM gdpr_requests
             WHERE user_id = ?
               AND request_type = ?
               AND status IN ("pending", "in_review")
             ORDER BY id DESC
             LIMIT 1',
            [$userId, $type],
        );
        if (!empty($existing)) {
            throw AppError::validation(
                'Hai gia una richiesta in lavorazione per questa operazione.',
                ['request_id' => (int) $existing->id, 'status' => (string) $existing->status],
                'gdpr_request_already_open',
            );
        }

        $cleanNote = trim($note);
        if ($cleanNote !== '') {
            $cleanNote = mb_substr($cleanNote, 0, 1000);
        }

        $this->execPrepared(
            'INSERT INTO gdpr_requests
                (user_id, request_type, status, note, payload_json, source, created_at, updated_at)
             VALUES (?, ?, "pending", ?, NULL, ?, NOW(), NOW())',
            [$userId, $type, $cleanNote !== '' ? $cleanNote : null, $source],
        );

        return [
            'id' => (int) $this->db->lastInsertId(),
            'request_type' => $type,
            'status' => 'pending',
        ];
    }

    public function updateRequestStatus(int $requestId, int $handlerUserId, string $status, string $resolutionNote = ''): array
    {
        $this->ensureTables();
        if ($requestId <= 0) {
            throw AppError::validation('Richiesta non valida', [], 'gdpr_request_invalid');
        }
        if ($handlerUserId <= 0) {
            throw AppError::validation('Utente operatore non valido', [], 'gdpr_handler_invalid');
        }

        $status = strtolower(trim($status));
        $allowedStatuses = ['pending', 'in_review', 'completed', 'rejected', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw AppError::validation('Stato richiesta non valido', [], 'gdpr_request_status_invalid');
        }

        $row = $this->firstPrepared(
            'SELECT id, user_id, request_type, status
             FROM gdpr_requests
             WHERE id = ?
             LIMIT 1',
            [$requestId],
        );
        if (empty($row)) {
            throw AppError::validation('Richiesta non trovata', [], 'gdpr_request_not_found');
        }

        $note = trim($resolutionNote);
        if ($note !== '') {
            $note = mb_substr($note, 0, 2000);
        }

        $handledAtSql = ($status === 'completed' || $status === 'rejected' || $status === 'cancelled') ? 'NOW()' : 'NULL';
        $this->execPrepared(
            'UPDATE gdpr_requests
             SET status = ?,
                 handled_by_user_id = ?,
                 handled_at = ' . $handledAtSql . ',
                 resolution_note = ?,
                 updated_at = NOW()
             WHERE id = ?',
            [$status, $handlerUserId, $note !== '' ? $note : null, $requestId],
        );

        return [
            'id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'request_type' => (string) $row->request_type,
            'status' => $status,
        ];
    }

    public function executeRequestAction(int $requestId, int $handlerUserId, string $resolutionNote = ''): array
    {
        $this->ensureTables();
        if ($requestId <= 0) {
            throw AppError::validation('Richiesta non valida', [], 'gdpr_request_invalid');
        }
        if ($handlerUserId <= 0) {
            throw AppError::validation('Utente operatore non valido', [], 'gdpr_handler_invalid');
        }

        $request = $this->firstPrepared(
            'SELECT id, user_id, request_type, status
             FROM gdpr_requests
             WHERE id = ?
             LIMIT 1',
            [$requestId],
        );
        if (empty($request)) {
            throw AppError::validation('Richiesta non trovata', [], 'gdpr_request_not_found');
        }

        $status = strtolower(trim((string) ($request->status ?? '')));
        if (!in_array($status, ['pending', 'in_review'], true)) {
            throw AppError::validation('La richiesta non e in uno stato eseguibile', [], 'gdpr_request_not_executable');
        }

        $requestType = strtolower(trim((string) ($request->request_type ?? '')));
        $note = trim($resolutionNote);
        if ($note !== '') {
            $note = mb_substr($note, 0, 2000);
        }

        if ($requestType === 'export_data') {
            $dataset = $this->exportDatasetForUser((int) $request->user_id);
            $payloadJson = json_encode($dataset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payloadJson === false) {
                throw AppError::validation('Errore durante la serializzazione del dataset export', [], 'gdpr_export_encode_failed');
            }

            $this->execPrepared(
                'UPDATE gdpr_requests
                 SET status = "completed",
                     payload_json = ?,
                     handled_by_user_id = ?,
                     handled_at = NOW(),
                     resolution_note = ?,
                     updated_at = NOW()
                 WHERE id = ?',
                [$payloadJson, $handlerUserId, $note !== '' ? $note : 'Esportazione dati generata.', $requestId],
            );

            return [
                'id' => (int) $request->id,
                'user_id' => (int) $request->user_id,
                'request_type' => $requestType,
                'status' => 'completed',
                'export_dataset' => $dataset,
            ];
        }

        if ($requestType === 'delete_account') {
            $deleteSummary = $this->anonymizeUserAccount((int) $request->user_id, $handlerUserId);
            $payloadJson = json_encode($deleteSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payloadJson === false) {
                $payloadJson = null;
            }

            $this->execPrepared(
                'UPDATE gdpr_requests
                 SET status = "completed",
                     payload_json = ?,
                     handled_by_user_id = ?,
                     handled_at = NOW(),
                     resolution_note = ?,
                     updated_at = NOW()
                 WHERE id = ?',
                [$payloadJson, $handlerUserId, $note !== '' ? $note : 'Account anonimizzato e disattivato.', $requestId],
            );

            return [
                'id' => (int) $request->id,
                'user_id' => (int) $request->user_id,
                'request_type' => $requestType,
                'status' => 'completed',
                'delete_summary' => $deleteSummary,
            ];
        }

        throw AppError::validation('Tipo richiesta non supportato per l\'esecuzione', [], 'gdpr_request_type_not_supported');
    }

    public function exportPayloadForAdmin(int $requestId): array
    {
        $this->ensureTables();
        if ($requestId <= 0) {
            throw AppError::validation('Richiesta non valida', [], 'gdpr_request_invalid');
        }

        $row = $this->firstPrepared(
            'SELECT id, user_id, request_type, status, payload_json
             FROM gdpr_requests
             WHERE id = ?
             LIMIT 1',
            [$requestId],
        );
        if (empty($row)) {
            throw AppError::validation('Richiesta non trovata', [], 'gdpr_request_not_found');
        }
        if ((string) ($row->request_type ?? '') !== 'export_data') {
            throw AppError::validation('La richiesta selezionata non e una esportazione dati', [], 'gdpr_request_not_export');
        }

        $payloadJson = trim((string) ($row->payload_json ?? ''));
        if ($payloadJson === '') {
            throw AppError::validation('Nessun payload export disponibile per questa richiesta', [], 'gdpr_export_payload_missing');
        }

        $decoded = json_decode($payloadJson, true);
        if (!is_array($decoded)) {
            throw AppError::validation('Il payload export non e valido', [], 'gdpr_export_payload_invalid');
        }

        return [
            'request_id' => (int) $row->id,
            'user_id' => (int) $row->user_id,
            'status' => (string) ($row->status ?? ''),
            'dataset' => $decoded,
        ];
    }

    private function anonymizeUserAccount(int $userId, int $handlerUserId): array
    {
        if ($userId <= 0) {
            throw AppError::validation('Utente non valido', [], 'user_invalid');
        }
        if ($handlerUserId <= 0) {
            throw AppError::validation('Utente operatore non valido', [], 'gdpr_handler_invalid');
        }
        if ($userId === $handlerUserId) {
            throw AppError::validation('Non puoi eseguire la cancellazione GDPR sul tuo account da questa procedura', [], 'gdpr_delete_self_forbidden');
        }

        $user = $this->firstPrepared(
            'SELECT id, is_superuser, superuser_role
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId],
        );
        if (empty($user)) {
            throw AppError::validation('Utente non trovato', [], 'user_not_found');
        }

        $isCreatorSuperuser = ((int) ($user->is_superuser ?? 0) === 1)
            && (strtolower(trim((string) ($user->superuser_role ?? ''))) === 'creatore');
        if ($isCreatorSuperuser) {
            throw AppError::validation('Non e consentito anonimizzare il superuser creatore da questa procedura', [], 'gdpr_delete_creator_forbidden');
        }

        $timestamp = gmdate('YmdHis');
        $anonymizedEmail = 'deleted-user-' . $userId . '-' . $timestamp . '@example.invalid';
        $randomPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        $setParts = [
            'email = AES_ENCRYPT(?, ?)',
            'password = ?',
            'google_sub = NULL',
            'google_avatar = NULL',
            'is_administrator = 0',
            'is_superuser = 0',
            'superuser_role = NULL',
            'is_moderator = 0',
            'is_master = 0',
            'session_version = IFNULL(session_version, 1) + 1',
            'date_sessions_revoked = NOW()',
            'date_actived = NULL',
            'date_last_signout = NOW()',
        ];
        $params = [$anonymizedEmail, $this->cryptKey(), $randomPasswordHash];

        if ($this->columnExists('users', 'is_restricted')) {
            $setParts[] = 'is_restricted = 1';
        }

        $params[] = $userId;
        $this->execPrepared(
            'UPDATE users
             SET ' . implode(', ', $setParts) . '
             WHERE id = ?',
            $params,
        );

        $charCountRow = $this->firstPrepared(
            'SELECT COUNT(*) AS cnt
             FROM characters
             WHERE user_id = ?
               AND (delete_scheduled_at IS NULL OR delete_scheduled_at > NOW())',
            [$userId],
        );
        $charactersScheduled = (int) ($charCountRow->cnt ?? 0);

        $this->execPrepared(
            'UPDATE characters
             SET delete_requested_at = IFNULL(delete_requested_at, NOW()),
                 delete_scheduled_at = NOW(),
                 is_visible = 0,
                 privacy_show_online = 0
             WHERE user_id = ?',
            [$userId],
        );

        return [
            'user_id' => $userId,
            'anonymized_email' => $anonymizedEmail,
            'characters_scheduled_for_delete' => $charactersScheduled,
            'executed_at' => gmdate('c'),
        ];
    }

    public function exportDatasetForUser(int $userId): array
    {
        if ($userId <= 0) {
            throw AppError::validation('Utente non valido', [], 'user_invalid');
        }

        $this->ensureTables();

        $user = $this->firstPrepared(
            'SELECT id,
                    CAST(AES_DECRYPT(email, ?) AS CHAR(255)) AS email,
                    ' . $this->selectColumnOrNull('users', 'date_created') . ',
                    ' . $this->selectColumnOrNull('users', 'date_actived') . ',
                    ' . $this->selectColumnOrNull('users', 'date_last_signin') . ',
                    ' . $this->selectColumnOrNull('users', 'date_last_signout') . ',
                    ' . $this->selectColumnOrNull('users', 'date_sessions_revoked') . '
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$this->cryptKey(), $userId],
        );
        if (empty($user)) {
            throw AppError::validation('Utente non trovato', [], 'user_not_found');
        }

        $characters = $this->allPrepared(
            'SELECT id,
                    name,
                    ' . $this->selectColumnOrNull('characters', 'surname') . ',
                    ' . $this->selectColumnOrNull('characters', 'date_created') . ',
                    ' . $this->selectColumnOrNull('characters', 'date_actived') . ',
                    ' . $this->selectColumnOrNull('characters', 'date_last_signin') . ',
                    ' . $this->selectColumnOrNull('characters', 'date_last_signout') . ',
                    ' . $this->selectColumnOrNull('characters', 'delete_scheduled_at') . '
             FROM characters
             WHERE user_id = ?
             ORDER BY id ASC',
            [$userId],
        );
        $characterIds = [];
        foreach ($characters as $character) {
            $characterIds[] = (int) ($character->id ?? 0);
        }

        $mailPrefs = $this->firstPrepared(
            'SELECT newsletter_opt_in, updated_at
             FROM user_mail_preferences
             WHERE user_id = ?
             LIMIT 1',
            [$userId],
        );
        $mailConsentLogs = $this->allPrepared(
            'SELECT consent_type, value, source, created_at
             FROM mail_consent_logs
             WHERE user_id = ?
             ORDER BY id DESC
             LIMIT 200',
            [$userId],
        );
        $legalConsents = $this->firstPrepared(
            'SELECT privacy_policy_version, terms_of_service_version, accepted_at, source, updated_at
             FROM user_legal_consents
             WHERE user_id = ?
             LIMIT 1',
            [$userId],
        );
        $legalConsentLogs = $this->allPrepared(
            'SELECT consent_key, consent_version, value, source, created_at
             FROM legal_consent_logs
             WHERE user_id = ?
             ORDER BY id DESC
             LIMIT 200',
            [$userId],
        );
        $requests = $this->listRequestsByUser($userId);

        $counts = [
            'messages_sent' => $this->countMessagesSent($characterIds),
            'forum_threads_authored' => $this->countForumThreads($characterIds, false),
            'forum_replies_authored' => $this->countForumThreads($characterIds, true),
            'location_messages_authored' => $this->countByCharacterIds('locations_messages', $characterIds),
            'notifications' => $this->countNotificationsForUser($userId),
            'uploads' => $this->countByCharacterIds('uploads', $characterIds),
        ];

        return [
            'generated_at' => gmdate('c'),
            'user' => $user,
            'characters' => $characters,
            'mail_preferences' => $mailPrefs ?: (object) ['newsletter_opt_in' => 0, 'updated_at' => null],
            'mail_consent_logs' => $mailConsentLogs,
            'legal_consents' => $legalConsents ?: (object) [],
            'legal_consent_logs' => $legalConsentLogs,
            'gdpr_requests' => $requests,
            'summary' => $counts,
        ];
    }

    public function recordCookieConsent(?int $userId, string $choice, array $preferences, string $source = 'public_banner', ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $this->ensureTables();

        $choice = strtolower(trim($choice));
        $allowedChoices = ['accept_all', 'reject_optional', 'custom'];
        if (!in_array($choice, $allowedChoices, true)) {
            throw AppError::validation('Scelta cookie non valida', [], 'cookie_choice_invalid');
        }

        $normalized = [
            'necessary' => 1,
            'preferences' => 0,
            'analytics' => 0,
            'marketing' => 0,
        ];

        if ($choice === 'accept_all') {
            $normalized['preferences'] = 1;
            $normalized['analytics'] = 1;
            $normalized['marketing'] = 1;
        } elseif ($choice === 'custom') {
            $normalized['preferences'] = !empty($preferences['preferences']) ? 1 : 0;
            $normalized['analytics'] = !empty($preferences['analytics']) ? 1 : 0;
            $normalized['marketing'] = !empty($preferences['marketing']) ? 1 : 0;
        }

        $context = $this->legalContext();
        $cookieVersion = (string) ($context['cookie_policy_version'] ?? '1.0.0');
        $prefsPayload = [
            'choice' => $choice,
            'preferences' => $normalized,
        ];
        $prefsJson = json_encode($prefsPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($prefsJson === false) {
            $prefsJson = '{}';
        }

        $this->execPrepared(
            'INSERT INTO cookie_consent_logs
                (user_id, consent_key, consent_version, value, preferences_json, source, ip_address, user_agent, created_at)
             VALUES (?, "cookie_policy", ?, 1, ?, ?, ?, ?, NOW())',
            [
                ($userId !== null && $userId > 0) ? $userId : null,
                $cookieVersion,
                $prefsJson,
                $source,
                $ipAddress,
                $userAgent,
            ],
        );

        return [
            'version' => $cookieVersion,
            'choice' => $choice,
            'preferences' => $normalized,
        ];
    }

    private function countMessagesSent(array $characterIds): int
    {
        if (empty($characterIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($characterIds), '?'));
        $params = [];
        foreach ($characterIds as $id) {
            $params[] = (int) $id;
        }
        $row = $this->firstPrepared(
            'SELECT COUNT(*) AS cnt
             FROM messages
             WHERE sender_id IN (' . $placeholders . ')',
            $params,
        );

        return (int) ($row->cnt ?? 0);
    }

    private function countForumThreads(array $characterIds, bool $isReply): int
    {
        if (empty($characterIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($characterIds), '?'));
        $sql = 'SELECT COUNT(*) AS cnt
                FROM forum_threads
                WHERE character_id IN (' . $placeholders . ')';
        if ($isReply) {
            $sql .= ' AND father_id IS NOT NULL';
        } else {
            $sql .= ' AND father_id IS NULL';
        }

        $params = [];
        foreach ($characterIds as $id) {
            $params[] = (int) $id;
        }
        $row = $this->firstPrepared($sql, $params);

        return (int) ($row->cnt ?? 0);
    }

    private function countByCharacterIds(string $table, array $characterIds): int
    {
        if (empty($characterIds)) {
            return 0;
        }

        $columnMap = [
            'locations_messages' => 'character_id',
            'uploads' => 'character_id',
        ];
        if (!isset($columnMap[$table])) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($characterIds), '?'));
        $params = [];
        foreach ($characterIds as $id) {
            $params[] = (int) $id;
        }
        $row = $this->firstPrepared(
            'SELECT COUNT(*) AS cnt
             FROM ' . $table . '
             WHERE ' . $columnMap[$table] . ' IN (' . $placeholders . ')',
            $params,
        );

        return (int) ($row->cnt ?? 0);
    }

    private function countNotificationsForUser(int $userId): int
    {
        $row = $this->firstPrepared(
            'SELECT COUNT(*) AS cnt
             FROM notifications
             WHERE recipient_user_id = ?',
            [$userId],
        );

        return (int) ($row->cnt ?? 0);
    }
}
