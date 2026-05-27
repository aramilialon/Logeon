<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\Http\AppError;

class UserService
{
    /** @var DbAdapterInterface */
    private $db;
    /** @var bool|null */
    private $restrictionColumnExists = null;
    /** @var bool|null */
    private $restrictionChatColumnExists = null;
    /** @var bool|null */
    private $restrictionWhisperColumnExists = null;
    /** @var bool|null */
    private $restrictionCommandsColumnExists = null;
    /** @var bool|null */
    private $superuserColumnExists = null;
    /** @var bool|null */
    private $superuserRoleColumnExists = null;

    public function __construct(DbAdapterInterface $db = null)
    {
        $this->db = $db ?: DbAdapterFactory::createFromConfig();
    }

    private function firstPrepared(string $sql, array $params = [])
    {
        return $this->db->fetchOnePrepared($sql, $params);
    }

    private function execPrepared(string $sql, array $params = []): void
    {
        $this->db->executePrepared($sql, $params);
    }

    private function cryptKey(): string
    {
        if (defined('DB')) {
            return (string) DB['crypt_key'];
        }

        return '';
    }

    private function quotedCryptKey(): string
    {
        return "'" . str_replace("'", "''", $this->cryptKey()) . "'";
    }

    private function normalizeStatusFilter($raw): string
    {
        $status = strtolower(trim((string) $raw));
        if ($status !== 'active' && $status !== 'disabled' && $status !== 'pending') {
            $status = 'all';
        }

        return $status;
    }

    private function normalizeOrderBy($raw, bool $isSuperuser = false): array
    {
        $map = [
            'character_name' => 'LOWER(CONCAT_WS(" ", IFNULL(ch.name, ""), IFNULL(ch.surname, "")))',
            'date_created' => 'users.date_created',
            'date_actived' => 'users.date_actived',
            'date_last_signin' => 'users.date_last_signin',
            'date_last_signout' => 'users.date_last_signout',
        ];
        if ($isSuperuser) {
            $quotedCryptKey = $this->quotedCryptKey();
            $map['email'] = 'LOWER(CAST(AES_DECRYPT(users.email, ' . $quotedCryptKey . ') AS CHAR(255)))';
        }

        $defaultField = 'date_created';
        $defaultDir = 'DESC';

        $parts = explode('|', (string) $raw);
        $field = trim((string) $parts[0]);
        $dir = strtoupper(trim((string) ($parts[1] ?? $defaultDir)));

        if (!isset($map[$field])) {
            $field = $defaultField;
        }
        if ($dir !== 'DESC') {
            $dir = 'ASC';
        }

        return [
            'raw' => $field . '|' . $dir,
            'sql' => ' ORDER BY ' . $map[$field] . ' ' . $dir . ', users.id DESC',
        ];
    }

    private function failValidation(string $message): void
    {
        throw AppError::validation($message);
    }

    public function allowedSuperuserRoles(bool $includeCreator = true): array
    {
        $roles = ['gestore', 'sviluppatore', 'grafico'];
        if ($includeCreator) {
            array_unshift($roles, 'creatore');
        }

        return $roles;
    }

    public function normalizeSuperuserRole(string $rawRole, string $fallback = 'gestore', bool $allowCreator = false): string
    {
        $role = strtolower(trim($rawRole));
        $allowed = $this->allowedSuperuserRoles($allowCreator);

        if ($role === '' || !in_array($role, $allowed, true)) {
            $role = strtolower(trim($fallback));
        }
        if ($role === '' || !in_array($role, $allowed, true)) {
            $role = $allowCreator ? 'creatore' : 'gestore';
        }

        return $role;
    }

    public function readSuperuserRole($user): string
    {
        if (empty($user) || (int) ($user->is_superuser ?? 0) !== 1) {
            return '';
        }

        $role = isset($user->superuser_role) ? strtolower(trim((string) $user->superuser_role)) : '';
        if ($role === '') {
            return $this->isSuperuserRoleFeatureAvailable() ? 'gestore' : 'creatore';
        }

        return $this->normalizeSuperuserRole($role, 'gestore', true);
    }

    public function isCreatorSuperuser($user): bool
    {
        return !empty($user)
            && (int) ($user->is_superuser ?? 0) === 1
            && $this->readSuperuserRole($user) === 'creatore';
    }

    private function buildSuperuserRoleSelect(string $superuserFlagExpression, string $tableAlias = 'users'): string
    {
        if ($this->isSuperuserRoleFeatureAvailable()) {
            return $tableAlias . '.superuser_role';
        }

        return 'CASE WHEN ' . $superuserFlagExpression . ' = 1 THEN "creatore" ELSE NULL END AS superuser_role';
    }

    public function normalizePermissionsHierarchy(int $isAdministrator, int $isModerator, int $isMaster): array
    {
        $admin = ($isAdministrator === 1);
        $moderator = $admin || ($isModerator === 1);
        $master = $admin || $moderator || ($isMaster === 1);

        return [
            'is_administrator' => $admin ? 1 : 0,
            'is_moderator' => $moderator ? 1 : 0,
            'is_master' => $master ? 1 : 0,
        ];
    }

    public function normalizePrivilegeAssignment(int $isSuperuser, string $superuserRole, int $isAdministrator, int $isModerator, int $isMaster, bool $allowCreatorRole = false): array
    {
        $superuser = $this->isSuperuserFeatureAvailable() && $isSuperuser === 1;

        if ($superuser) {
            return [
                'is_superuser' => 1,
                'superuser_role' => $this->normalizeSuperuserRole($superuserRole, 'gestore', $allowCreatorRole),
                'is_administrator' => 1,
                'is_moderator' => 1,
                'is_master' => 1,
            ];
        }

        $normalized = $this->normalizePermissionsHierarchy($isAdministrator, $isModerator, $isMaster);
        $normalized['is_superuser'] = 0;
        $normalized['superuser_role'] = null;

        return $normalized;
    }

    private function verifyPassword(string $password, string $hash, int $userId = 0): bool
    {
        $info = password_get_info($hash);
        if (!empty($info['algo'])) {
            if (password_verify($password, $hash)) {
                if (password_needs_rehash($hash, PASSWORD_DEFAULT) && $userId > 0) {
                    $this->upgradePasswordHash($userId, $password);
                }

                return true;
            }

            return false;
        }

        if (hash_equals($hash, md5($password))) {
            if ($userId > 0) {
                $this->upgradePasswordHash($userId, $password);
            }

            return true;
        }

        return false;
    }

    private function upgradePasswordHash(int $userId, string $password): void
    {
        if ($userId <= 0) {
            return;
        }

        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $this->execPrepared(
            'UPDATE users SET
                password = ?,
                date_last_pass = NOW()
             WHERE id = ?',
            [$newHash, $userId],
        );
    }

    private function isPasswordUnusedForLegacyHash(string $password): bool
    {
        $row = $this->firstPrepared(
            'SELECT password
             FROM users
             WHERE password = ?
             LIMIT 1',
            [md5($password)],
        );

        return empty($row);
    }

    public function isRestrictionFeatureAvailable(): bool
    {
        if ($this->restrictionColumnExists !== null) {
            return $this->restrictionColumnExists;
        }

        $row = $this->firstPrepared(
            'SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['users', 'is_restricted'],
        );

        $this->restrictionColumnExists = !empty($row);
        return $this->restrictionColumnExists;
    }

    public function isRestrictionChatFeatureAvailable(): bool
    {
        if ($this->restrictionChatColumnExists !== null) {
            return $this->restrictionChatColumnExists;
        }

        $row = $this->firstPrepared(
            'SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['users', 'restrict_chat'],
        );

        $this->restrictionChatColumnExists = !empty($row);
        return $this->restrictionChatColumnExists;
    }

    public function isRestrictionWhisperFeatureAvailable(): bool
    {
        if ($this->restrictionWhisperColumnExists !== null) {
            return $this->restrictionWhisperColumnExists;
        }

        $row = $this->firstPrepared(
            'SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['users', 'restrict_whisper'],
        );

        $this->restrictionWhisperColumnExists = !empty($row);
        return $this->restrictionWhisperColumnExists;
    }

    public function isRestrictionCommandsFeatureAvailable(): bool
    {
        if ($this->restrictionCommandsColumnExists !== null) {
            return $this->restrictionCommandsColumnExists;
        }

        $row = $this->firstPrepared(
            'SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['users', 'restrict_commands'],
        );

        $this->restrictionCommandsColumnExists = !empty($row);
        return $this->restrictionCommandsColumnExists;
    }

    public function isSuperuserFeatureAvailable(): bool
    {
        if ($this->superuserColumnExists !== null) {
            return $this->superuserColumnExists;
        }

        $row = $this->firstPrepared(
            'SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['users', 'is_superuser'],
        );

        $this->superuserColumnExists = !empty($row);
        return $this->superuserColumnExists;
    }

    public function isSuperuserRoleFeatureAvailable(): bool
    {
        if ($this->superuserRoleColumnExists !== null) {
            return $this->superuserRoleColumnExists;
        }

        $row = $this->firstPrepared(
            'SELECT 1 AS ok
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
             LIMIT 1',
            ['users', 'superuser_role'],
        );

        $this->superuserRoleColumnExists = !empty($row);
        return $this->superuserRoleColumnExists;
    }

    public function isRestricted(int $userId): bool
    {
        if ($userId <= 0 || !$this->isRestrictionFeatureAvailable()) {
            return false;
        }

        $row = $this->firstPrepared(
            'SELECT is_restricted
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId],
        );

        return !empty($row) && (int) $row->is_restricted === 1;
    }

    public function getRestrictionScopes(int $userId): array
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return [
                'is_restricted' => 0,
                'restrict_chat' => 0,
                'restrict_whisper' => 0,
                'restrict_commands' => 0,
            ];
        }

        $hasGlobal = $this->isRestrictionFeatureAvailable();
        $hasChat = $this->isRestrictionChatFeatureAvailable();
        $hasWhisper = $this->isRestrictionWhisperFeatureAvailable();
        $hasCommands = $this->isRestrictionCommandsFeatureAvailable();

        $columns = [];
        $columns[] = $hasGlobal ? 'is_restricted' : '0 AS is_restricted';
        $columns[] = $hasChat ? 'restrict_chat' : '0 AS restrict_chat';
        $columns[] = $hasWhisper ? 'restrict_whisper' : '0 AS restrict_whisper';
        $columns[] = $hasCommands ? 'restrict_commands' : '0 AS restrict_commands';

        $row = $this->firstPrepared(
            'SELECT ' . implode(', ', $columns) . '
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId],
        );

        return [
            'is_restricted' => (int) ($row->is_restricted ?? 0),
            'restrict_chat' => (int) ($row->restrict_chat ?? 0),
            'restrict_whisper' => (int) ($row->restrict_whisper ?? 0),
            'restrict_commands' => (int) ($row->restrict_commands ?? 0),
        ];
    }

    public function isRestrictedForScope(int $userId, string $scope): bool
    {
        if ($this->isRestricted($userId)) {
            return true;
        }

        $scope = strtolower(trim($scope));
        if ($scope === '' || $userId <= 0) {
            return false;
        }

        $map = [
            'chat' => ['restrict_chat', 'isRestrictionChatFeatureAvailable'],
            'whisper' => ['restrict_whisper', 'isRestrictionWhisperFeatureAvailable'],
            'commands' => ['restrict_commands', 'isRestrictionCommandsFeatureAvailable'],
        ];
        if (!isset($map[$scope])) {
            return false;
        }

        [$column, $method] = $map[$scope];
        if (!method_exists($this, $method) || !$this->{$method}()) {
            return false;
        }

        $row = $this->firstPrepared(
            'SELECT ' . $column . '
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId],
        );

        return !empty($row) && (int) ($row->{$column} ?? 0) === 1;
    }

    public function emailExists(string $email): bool
    {
        $value = strtolower(trim($email));
        if ($value === '') {
            return false;
        }

        $row = $this->firstPrepared(
            'SELECT id
             FROM users
             WHERE email = AES_ENCRYPT(?, ?)
             LIMIT 1',
            [$value, $this->cryptKey()],
        );

        return !empty($row);
    }

    public function generateRandomPassword(): string
    {
        $length = (int) CONFIG['password_length'];

        do {
            $password = bin2hex(random_bytes($length));
        } while ($this->isPasswordUnusedForLegacyHash($password) === false);

        return $password;
    }

    public function assertPasswordValid(int $userId, string $password): void
    {
        if ($userId <= 0 || trim($password) === '') {
            $this->failValidation('Password non valida');
        }

        $user = $this->firstPrepared(
            'SELECT id, password
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId],
        );
        if (empty($user)) {
            $this->failValidation('Utente non trovato');
        }

        if ($this->verifyPassword($password, (string) $user->password, (int) $user->id) === false) {
            $this->failValidation('Password non valida');
        }
    }

    public function getAdminUserById(int $userId)
    {
        if ($userId <= 0) {
            return [];
        }

        $restrictionSelect = $this->isRestrictionFeatureAvailable()
            ? 'users.is_restricted'
            : '0 AS is_restricted';
        $superuserFlagExpression = $this->isSuperuserFeatureAvailable()
            ? 'users.is_superuser'
            : 'users.is_administrator';
        $superuserSelect = $superuserFlagExpression . ' AS is_superuser';
        $superuserRoleSelect = $this->buildSuperuserRoleSelect($superuserFlagExpression, 'users');

        return $this->firstPrepared(
            'SELECT users.id,
                    CAST(AES_DECRYPT(users.email, ?) AS CHAR(255)) AS email,
                    users.is_administrator,
                    users.is_moderator,
                    users.is_master,
                    ' . $superuserSelect . ',
                    ' . $superuserRoleSelect . ',
                    users.date_created,
                    users.date_actived,
                    users.date_last_signin,
                    users.date_last_signout,
                    users.date_sessions_revoked,
                    ' . $restrictionSelect . '
             FROM users
             WHERE users.id = ?
             LIMIT 1',
            [$this->cryptKey(), $userId],
        );
    }

    public function listAdminUsers(string $searchRaw, string $statusRaw, int $pageRaw, int $resultsRaw, string $orderByRaw, bool $isSuperuser = false): array
    {
        $search = strtolower(trim($searchRaw));
        $status = $this->normalizeStatusFilter($statusRaw);

        $page = $pageRaw;
        if ($page < 1) {
            $page = 1;
        }

        $results = $resultsRaw;
        if ($results < 1) {
            $results = 20;
        } elseif ($results > 100) {
            $results = 100;
        }

        $order = $this->normalizeOrderBy($orderByRaw, $isSuperuser);

        $whereParts = [];
        $whereParams = [];
        if ($search !== '') {
            $searchLike = '%' . $search . '%';
            $characterSql = 'EXISTS (
                SELECT 1
                FROM characters c_search
                WHERE c_search.user_id = users.id
                  AND (c_search.delete_scheduled_at IS NULL OR c_search.delete_scheduled_at > NOW())
                  AND LOWER(CONCAT_WS(" ", IFNULL(c_search.name, ""), IFNULL(c_search.surname, ""))) LIKE ?
                LIMIT 1
            )';
            if ($isSuperuser) {
                $whereParts[] = '('
                    . 'LOWER(CAST(AES_DECRYPT(users.email, ?) AS CHAR(255))) LIKE ?'
                    . ' OR '
                    . $characterSql
                    . ')';
                $whereParams[] = $this->cryptKey();
                $whereParams[] = $searchLike;
                $whereParams[] = $searchLike;
            } else {
                $whereParts[] = $characterSql;
                $whereParams[] = $searchLike;
            }
        }

        if ($status === 'active') {
            $whereParts[] = 'users.date_actived IS NOT NULL';
        } elseif ($status === 'disabled') {
            $whereParts[] = 'users.date_actived IS NULL';
            $whereParts[] = 'users.date_last_signin IS NOT NULL';
        } elseif ($status === 'pending') {
            $whereParts[] = 'users.date_actived IS NULL';
            $whereParts[] = 'users.date_last_signin IS NULL';
        }

        $whereSql = '';
        if (!empty($whereParts)) {
            $whereSql = ' WHERE ' . implode(' AND ', $whereParts);
        }

        $offset = ($page - 1) * $results;
        $restrictionSelect = $this->isRestrictionFeatureAvailable()
            ? 'users.is_restricted'
            : '0 AS is_restricted';
        $superuserFlagExpression = $this->isSuperuserFeatureAvailable()
            ? 'users.is_superuser'
            : 'users.is_administrator';
        $superuserSelect = $superuserFlagExpression . ' AS is_superuser';
        $superuserRoleSelect = $this->buildSuperuserRoleSelect($superuserFlagExpression, 'users');
        $emailSelect = $isSuperuser
            ? 'CAST(AES_DECRYPT(users.email, ?) AS CHAR(255)) AS email'
            : 'NULL AS email';

        $datasetParams = [];
        if ($isSuperuser) {
            $datasetParams[] = $this->cryptKey();
        }
        $datasetParams = array_merge($datasetParams, $whereParams, [$results, $offset]);
        $dataset = $this->db->fetchAllPrepared(
            'SELECT users.id,
                    ' . $emailSelect . ',
                    users.is_administrator,
                    users.is_moderator,
                    users.is_master,
                    ' . $superuserSelect . ',
                    ' . $superuserRoleSelect . ',
                    ch.id AS character_id,
                    ch.name AS character_name,
                    ch.surname AS character_surname,
                    users.date_created,
                    users.date_actived,
                    users.date_last_signin,
                    users.date_last_signout,
                    users.date_sessions_revoked,
                    ' . $restrictionSelect . ',
                    CASE
                        WHEN users.date_actived IS NOT NULL THEN "active"
                        WHEN users.date_last_signin IS NOT NULL THEN "disabled"
                        ELSE "pending"
                    END AS status
             FROM users
             LEFT JOIN characters ch ON ch.id = (
                SELECT c_pick.id
                FROM characters c_pick
                WHERE c_pick.user_id = users.id
                  AND (c_pick.delete_scheduled_at IS NULL OR c_pick.delete_scheduled_at > NOW())
                ORDER BY c_pick.id ASC
                LIMIT 1
             )
             ' . $whereSql . '
             ' . $order['sql'] . '
             LIMIT ? OFFSET ?',
            $datasetParams,
        );

        $count = $this->firstPrepared(
            'SELECT COUNT(*) AS count
             FROM users
             ' . $whereSql,
            $whereParams,
        );

        if (empty($count)) {
            $count = (object) ['count' => 0];
        }

        return [
            'query' => [
                'search' => $search,
                'status' => $status,
            ],
            'page' => $page,
            'results_page' => $results,
            'orderBy' => $order['raw'],
            'tot' => $count,
            'dataset' => !empty($dataset) ? $dataset : [],
        ];
    }

    public function createPasswordResetToken(int $userId, int $expiresMinutes = 60): string
    {
        if ($expiresMinutes <= 0) {
            $expiresMinutes = 60;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $this->execPrepared(
            'DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL',
            [$userId],
        );
        $this->execPrepared(
            'INSERT INTO password_resets SET
                user_id = ?,
                token_hash = ?,
                expires_at = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                created_at = NOW()',
            [$userId, $tokenHash, $expiresMinutes],
        );

        return $token;
    }

    public function setAdminPermissions(int $userId, int $isAdministrator, int $isModerator, int $isMaster, int $isSuperuser = 0, ?string $superuserRole = null): void
    {
        $normalized = $this->normalizePrivilegeAssignment(
            $isSuperuser,
            (string) ($superuserRole ?? ''),
            $isAdministrator,
            $isModerator,
            $isMaster,
        );

        if ($this->isSuperuserFeatureAvailable() && $this->isSuperuserRoleFeatureAvailable()) {
            $this->execPrepared(
                'UPDATE users SET
                    is_administrator = ?,
                    is_superuser = ?,
                    superuser_role = ?,
                    is_moderator = ?,
                    is_master = ?
                 WHERE id = ?',
                [
                    $normalized['is_administrator'],
                    $normalized['is_superuser'],
                    $normalized['superuser_role'],
                    $normalized['is_moderator'],
                    $normalized['is_master'],
                    $userId,
                ],
            );
            return;
        }

        if ($this->isSuperuserFeatureAvailable()) {
            $this->execPrepared(
                'UPDATE users SET
                    is_administrator = ?,
                    is_superuser = ?,
                    is_moderator = ?,
                    is_master = ?
                 WHERE id = ?',
                [
                    $normalized['is_administrator'],
                    $normalized['is_superuser'],
                    $normalized['is_moderator'],
                    $normalized['is_master'],
                    $userId,
                ],
            );
            return;
        }

        $this->execPrepared(
            'UPDATE users SET
                is_administrator = ?,
                is_moderator = ?,
                is_master = ?
             WHERE id = ?',
            [$normalized['is_administrator'], $normalized['is_moderator'], $normalized['is_master'], $userId],
        );
    }

    public function disconnectUserSessions(int $userId): void
    {
        $this->execPrepared(
            'UPDATE users SET
                session_version = IFNULL(session_version, 1) + 1,
                date_sessions_revoked = NOW()
             WHERE id = ?',
            [$userId],
        );
    }

    public function setUserRestriction(int $userId, int $isRestricted): void
    {
        $hasChat = $this->isRestrictionChatFeatureAvailable();
        $hasWhisper = $this->isRestrictionWhisperFeatureAvailable();
        $hasCommands = $this->isRestrictionCommandsFeatureAvailable();

        if ($isRestricted === 1) {
            $extraSet = '';
            if ($hasChat) {
                $extraSet .= ', restrict_chat = 1';
            }
            if ($hasWhisper) {
                $extraSet .= ', restrict_whisper = 1';
            }
            if ($hasCommands) {
                $extraSet .= ', restrict_commands = 1';
            }
            $this->execPrepared(
                'UPDATE users SET
                    is_restricted = 1,
                    ' . ltrim($extraSet, ', ') . (trim($extraSet) !== '' ? ',' : '') . '
                    session_version = IFNULL(session_version, 1) + 1,
                    date_sessions_revoked = NOW()
                 WHERE id = ?',
                [$userId],
            );
            return;
        }

        $extraUnset = '';
        if ($hasChat) {
            $extraUnset .= ', restrict_chat = 0';
        }
        if ($hasWhisper) {
            $extraUnset .= ', restrict_whisper = 0';
        }
        if ($hasCommands) {
            $extraUnset .= ', restrict_commands = 0';
        }
        $this->execPrepared(
            'UPDATE users SET
                is_restricted = 0' . $extraUnset . '
             WHERE id = ?',
            [$userId],
        );
    }

    public function setUserRestrictionScopes(
        int $userId,
        ?int $restrictChat = null,
        ?int $restrictWhisper = null,
        ?int $restrictCommands = null,
    ): void {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }

        $updates = [];
        $params = [];
        if ($restrictChat !== null && $this->isRestrictionChatFeatureAvailable()) {
            $updates[] = 'restrict_chat = ?';
            $params[] = ((int) $restrictChat === 1) ? 1 : 0;
        }
        if ($restrictWhisper !== null && $this->isRestrictionWhisperFeatureAvailable()) {
            $updates[] = 'restrict_whisper = ?';
            $params[] = ((int) $restrictWhisper === 1) ? 1 : 0;
        }
        if ($restrictCommands !== null && $this->isRestrictionCommandsFeatureAvailable()) {
            $updates[] = 'restrict_commands = ?';
            $params[] = ((int) $restrictCommands === 1) ? 1 : 0;
        }
        if (empty($updates)) {
            return;
        }

        $params[] = $userId;
        $this->execPrepared(
            'UPDATE users SET
                ' . implode(', ', $updates) . '
             WHERE id = ?',
            $params,
        );
    }

    public function revokeSessions(int $userId): ?int
    {
        $this->disconnectUserSessions($userId);

        $row = $this->firstPrepared(
            'SELECT session_version
             FROM users
             WHERE id = ?
             LIMIT 1',
            [$userId],
        );

        if (empty($row) || !isset($row->session_version)) {
            return null;
        }

        return (int) $row->session_version;
    }

    public function createUser(object $data, string $hash): void
    {
        $normalized = $this->normalizePermissionsHierarchy(
            isset($data->is_administrator) ? (int) $data->is_administrator : 0,
            isset($data->is_moderator) ? (int) $data->is_moderator : 0,
            isset($data->is_master) ? (int) $data->is_master : 0,
        );
        $this->execPrepared(
            'INSERT INTO users SET
                username = ?,
                email = AES_ENCRYPT(?, ?),
                gender  = ?,
                password = ?,
                is_administrator = ?,
                is_moderator = ?,
                is_master = ?',
            [
                (string) $data->username,
                (string) $data->email,
                $this->cryptKey(),
                (int) $data->gender,
                $hash,
                (int) $normalized['is_administrator'],
                (int) $normalized['is_moderator'],
                (int) $normalized['is_master'],
            ],
        );
    }

    public function updateUser(object $data): void
    {
        $normalized = $this->normalizePermissionsHierarchy(
            isset($data->is_administrator) ? (int) $data->is_administrator : 0,
            isset($data->is_moderator) ? (int) $data->is_moderator : 0,
            isset($data->is_master) ? (int) $data->is_master : 0,
        );
        $this->execPrepared(
            'UPDATE users SET
                username = ?,
                email = AES_ENCRYPT(?, ?),
                gender  = ?,
                is_administrator = ?,
                is_moderator = ?,
                is_master = ?
            WHERE id = ?',
            [
                (string) $data->username,
                (string) $data->email,
                $this->cryptKey(),
                (int) $data->gender,
                (int) $normalized['is_administrator'],
                (int) $normalized['is_moderator'],
                (int) $normalized['is_master'],
                (int) $data->id,
            ],
        );
    }

    public function setUserActive(int $userId, bool $active): void
    {
        $this->execPrepared(
            'UPDATE users SET
                date_actived = ' . ($active ? 'NOW()' : 'NULL') . '
            WHERE id = ?',
            [$userId],
        );
    }

    public function createSystemSeedUser(string $email = 'test@pbce.com', string $plainPassword = 'test'): void
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->execPrepared(
            'INSERT INTO users SET
                email = AES_ENCRYPT(?, ?),
                gender  = ?,
                password = ?,
                is_administrator = ?',
            [(string) $email, $this->cryptKey(), rand(0, 1), $hash, 1],
        );
    }
}
