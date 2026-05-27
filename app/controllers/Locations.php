<?php

declare(strict_types=1);

use App\Models\Location;
use App\Services\LocationAdminService;
use App\Services\LocationService;
use Core\AuditLogService;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;

use Core\Logging\LoggerInterface;

use Core\RateLimiter;
use Core\SessionStore;

class Locations extends Location
{
    /** @var LoggerInterface|null */
    private $logger = null;
    /** @var LocationService|null */
    private $locationService = null;
    /** @var LocationAdminService|null */
    private $locationAdminService = null;

    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        return $this;
    }

    public function setLocationService(LocationService $locationService = null)
    {
        $this->locationService = $locationService;
        return $this;
    }

    private function logger(): LoggerInterface
    {
        if ($this->logger instanceof LoggerInterface) {
            return $this->logger;
        }

        $this->logger = \Core\AppContext::logger();
        return $this->logger;
    }

    private function locationService(): LocationService
    {
        if ($this->locationService instanceof LocationService) {
            return $this->locationService;
        }

        $this->locationService = new LocationService();
        return $this->locationService;
    }

    protected function trace($message, $context = false): void
    {
        $this->logger()->trace($message, $context);
    }

    private function failValidation($message, string $errorCode = 'validation_error')
    {
        throw AppError::validation((string) $message, [], $errorCode);
    }

    private function locationAdminService(): LocationAdminService
    {
        if ($this->locationAdminService instanceof LocationAdminService) {
            return $this->locationAdminService;
        }
        $this->locationAdminService = new LocationAdminService();
        return $this->locationAdminService;
    }

    private function requestDataObject()
    {
        $request = RequestData::fromGlobals();
        $data = $request->postJson('data', (object) [], false);
        if (!is_object($data)) {
            $data = (object) [];
        }
        return $data;
    }

    private function requireAdmin()
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
    }

    private function requireNarrativeStaff(): void
    {
        $auth = \Core\AppContext::authContext();
        if (!$auth->isAdmin() && !$auth->isMaster() && !$auth->isSuperuser()) {
            throw AppError::unauthorized('Operazione riservata a Master, Admin e Superuser');
        }
    }

    private function enforceRate($bucket, $limit, $windowSeconds, $identifier, $message = null, string $errorCode = 'rate_limited')
    {
        $rate = RateLimiter::hit($bucket, $limit, $windowSeconds, $identifier);
        if (!empty($rate['allowed'])) {
            return;
        }

        if ($message === null || trim((string) $message) === '') {
            $message = 'Troppi tentativi. Riprova tra ' . (int) $rate['retry_after'] . ' secondi';
        } else {
            $message .= ' Riprova tra ' . (int) $rate['retry_after'] . ' secondi';
        }
        $this->failValidation($message, $errorCode);
    }

    public function list($echo = true)
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $character_id = \Core\AuthGuard::api()->requireCharacter();

        $request = RequestData::fromGlobals();
        $post = $request->postJson('data', (object) [], false);
        if ($post === null) {
            $post = (object) [];
        }
        if (!isset($post->query)) {
            $post->query = [];
        } elseif (is_object($post->query)) {
            $post->query = (array) $post->query;
        }
        $post->query[] = 'locations.date_deleted IS NULL';
        $post->cache = false;
        $post->cache_ttl = 0;
        $response = $this->listWithPayload($post, false);
        $dataset = is_array($response['dataset'] ?? null) ? $response['dataset'] : [];
        $this->enrichRequiredSocialStatusFields($dataset);

        if (!empty($dataset)) {
            $character = $this->getCharacter($character_id);
            $invited = $this->getAcceptedInvites($character_id);
            $guildAccess = $this->getGuildAccessSet($character_id);

            foreach ($dataset as $location) {
                $access = $this->evaluateAccess($location, $character, $invited, $guildAccess);
                $location->access = $access['allowed'];
                $location->access_reason = $access['reason'];
                $location->access_reason_code = $access['reason_code'];
                $location->is_owner = $access['is_owner'] ? 1 : 0;
                $location->is_invited = $access['is_invited'] ? 1 : 0;
                $location->is_full = $access['is_full'] ? 1 : 0;
                $location->guests_count = $access['guests_count'];
            }
        }

        $response['dataset'] = $dataset;

        if ($echo) {
            ResponseEmitter::emit(ApiResponse::json($response));
        }

        return $response;
    }

    public function adminList()
    {
        $this->requireAdmin();

        $request = RequestData::fromGlobals();
        $post = $request->postJson('data', (object) [], false);
        if ($post === null) {
            $post = (object) [];
        }
        $query = (isset($post->query) && is_object($post->query))
            ? (array) $post->query
            : ((isset($post->query) && is_array($post->query)) ? $post->query : []);

        $search = '';
        if (isset($post->search) && trim((string) $post->search) !== '') {
            $search = trim((string) $post->search);
        } elseif (isset($query['name']) && trim((string) $query['name']) !== '') {
            $search = trim((string) $query['name']);
        }

        $page = isset($post->page) ? (int) $post->page : 1;
        if ($page < 1) {
            $page = 1;
        }
        $results = isset($post->results) ? (int) $post->results : 20;
        if ($results < 1) {
            $results = 20;
        }
        if ($results > 500) {
            $results = 500;
        }
        $offset = ($page - 1) * $results;

        $orderByRaw = isset($post->orderBy) ? trim((string) $post->orderBy) : 'locations.id|ASC';
        if ($orderByRaw === '') {
            $orderByRaw = 'locations.id|ASC';
        }
        $orderParts = explode('|', $orderByRaw);
        $orderFieldRaw = trim((string) $orderParts[0]);
        $orderDir = strtoupper(trim((string) ($orderParts[1] ?? 'ASC')));
        if ($orderDir !== 'DESC') {
            $orderDir = 'ASC';
        }
        $allowedOrderFields = [
            'id' => 'locations.id',
            'locations.id' => 'locations.id',
            'name' => 'locations.name',
            'locations.name' => 'locations.name',
            'status' => 'locations.status',
            'locations.status' => 'locations.status',
            'map_name' => 'maps.name',
            'maps.name' => 'maps.name',
            'is_house' => 'locations.is_house',
            'locations.is_house' => 'locations.is_house',
            'is_chat' => 'locations.is_chat',
            'locations.is_chat' => 'locations.is_chat',
            'access_policy' => 'locations.access_policy',
            'locations.access_policy' => 'locations.access_policy',
            'date_created' => 'locations.date_created',
            'locations.date_created' => 'locations.date_created',
        ];
        $orderField = $allowedOrderFields[$orderFieldRaw] ?? 'locations.id';
        $orderSql = ' ORDER BY ' . $orderField . ' ' . $orderDir;

        $whereParts = ['locations.date_deleted IS NULL'];
        $params = [];

        if ($search !== '') {
            $whereParts[] = '(LOWER(locations.name) LIKE ? OR LOWER(COALESCE(maps.name, "")) LIKE ?)';
            $like = '%' . strtolower($search) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $intFilterMap = [
            'id' => 'locations.id',
            'map_id' => 'locations.map_id',
            'owner_id' => 'locations.owner_id',
            'is_house' => 'locations.is_house',
            'is_chat' => 'locations.is_chat',
            'is_private' => 'locations.is_private',
            'min_socialstatus_id' => 'locations.min_socialstatus_id',
        ];
        foreach ($intFilterMap as $key => $field) {
            if (!array_key_exists($key, $query) || $query[$key] === '' || $query[$key] === null) {
                continue;
            }
            $whereParts[] = $field . ' = ?';
            $params[] = (int) $query[$key];
        }

        $stringFilterMap = [
            'status' => 'locations.status',
            'chat_type' => 'locations.chat_type',
            'access_policy' => 'locations.access_policy',
            'booking' => 'locations.booking',
        ];
        foreach ($stringFilterMap as $key => $field) {
            if (!array_key_exists($key, $query)) {
                continue;
            }
            $value = trim((string) $query[$key]);
            if ($value === '') {
                continue;
            }
            $whereParts[] = $field . ' = ?';
            $params[] = $value;
        }

        $joins = (!empty($this->joins)) ? implode('', $this->joins) : '';
        $whereSql = ' WHERE ' . implode(' AND ', $whereParts);

        $dataset = static::db()->fetchAllPrepared(
            'SELECT ' . implode(', ', $this->fillable) . ' FROM ' . $this->table . $joins . $whereSql . $orderSql . ' LIMIT ? OFFSET ?',
            array_merge($params, [$results, $offset]),
        );
        $this->enrichRequiredSocialStatusFields($dataset);
        $countRow = static::db()->fetchOnePrepared(
            'SELECT COUNT(DISTINCT locations.id) AS count FROM ' . $this->table . $joins . $whereSql,
            $params,
        );
        $tot = (int) ($countRow->count ?? 0);

        ResponseEmitter::emit(ApiResponse::json([
            'properties' => [
                'query' => empty($query) ? null : (object) $query,
                'page' => $page,
                'results_page' => $results,
                'orderBy' => $orderFieldRaw . '|' . $orderDir,
                'tot' => (object) ['count' => $tot],
            ],
            'dataset' => $dataset,
        ]));

        return $this;
    }

    public function adminCreate()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        if (empty(trim((string) ($data->name ?? '')))) {
            throw \Core\Http\AppError::validation('Nome luogo obbligatorio', 'name_required');
        }
        $this->locationAdminService()->create($data);
        ResponseEmitter::emit(ApiResponse::json(['success' => true, 'message' => 'Luogo creato']));
        return $this;
    }

    public function adminEdit()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        if ((int) ($data->id ?? 0) <= 0) {
            throw \Core\Http\AppError::validation('ID non valido', 'id_invalid');
        }
        if (empty(trim((string) ($data->name ?? '')))) {
            throw \Core\Http\AppError::validation('Nome luogo obbligatorio', 'name_required');
        }
        $this->locationAdminService()->update($data);
        ResponseEmitter::emit(ApiResponse::json(['success' => true, 'message' => 'Luogo aggiornato']));
        return $this;
    }

    public function adminGet()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $id = (int) ($data->id ?? 0);
        if ($id <= 0) {
            throw \Core\Http\AppError::validation('ID non valido', 'id_invalid');
        }
        $row = $this->locationAdminService()->getById($id);
        ResponseEmitter::emit(ApiResponse::json(['dataset' => $row]));
        return $this;
    }

    public function adminDelete()
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $id = (int) ($data->id ?? 0);
        if ($id <= 0) {
            throw \Core\Http\AppError::validation('ID non valido', 'id_invalid');
        }
        $this->locationAdminService()->delete($id);
        ResponseEmitter::emit(ApiResponse::json(['success' => true, 'message' => 'Luogo eliminato']));
        return $this;
    }

    public function adminUpdate()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireAdmin();

        $request = RequestData::fromGlobals();
        $data = $request->postJson('data', (object) [], false);
        if ($data === null || !isset($data->id)) {
            $this->failValidation('Dati non validi', 'payload_invalid');
        }

        $mapX = null;
        if (isset($data->map_x) && $data->map_x !== '') {
            $mapX = floatval($data->map_x);
            if ($mapX < 0 || $mapX > 100) {
                $this->failValidation('Map X fuori limite', 'map_x_out_of_range');
            }
        }

        $mapY = null;
        if (isset($data->map_y) && $data->map_y !== '') {
            $mapY = floatval($data->map_y);
            if ($mapY < 0 || $mapY > 100) {
                $this->failValidation('Map Y fuori limite', 'map_y_out_of_range');
            }
        }

        $this->locationService()->updateMapCoordinates(
            (int) $data->id,
            $mapX,
            $mapY,
        );

        return $this;
    }

    public function canAccess($location_id, $character_id = null)
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);

        if (empty($character_id)) {
            $character_id = \Core\AuthGuard::api()->requireCharacter();
        }

        $location = $this->locationService()->getLocationForAccess((int) $location_id);

        if (empty($location) || !empty($location->date_deleted)) {
            return [
                'allowed' => false,
                'reason' => 'Luogo non trovato',
                'reason_code' => 'not_found',
                'is_owner' => false,
                'is_invited' => false,
                'is_full' => false,
                'guests_count' => 0,
            ];
        }

        $character = $this->getCharacter($character_id);
        $invited = $this->getAcceptedInvites($character_id);
        $guildAccess = $this->getGuildAccessSet($character_id);
        $access = $this->evaluateAccess($location, $character, $invited, $guildAccess);
        if ($access['allowed']) {
            $this->logAccess($character_id, (int) $location->id, 1, $access['reason_code'] ?? null, $access['reason'] ?? null);
        } else {
            $this->logAccess($character_id, (int) $location->id, 0, $access['reason_code'], $access['reason']);
        }

        return $access;
    }

    public function invites()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $me = \Core\AuthGuard::api()->requireCharacter();

        $this->expirePendingInvites();
        $dataset = $this->locationService()->listPendingInvitesForCharacter((int) $me);

        ResponseEmitter::emit(ApiResponse::json([
            'dataset' => $dataset,
        ]));
    }

    public function invite()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $me = \Core\AuthGuard::api()->requireCharacter();
        $data = InputValidator::postJsonObject(RequestData::fromGlobals(), 'data', true);

        $location_id = InputValidator::integer($data, 'location_id');
        $invited_id = InputValidator::integer($data, 'character_id');

        if ($location_id <= 0 || $invited_id <= 0) {
            $this->failValidation('Dati invito non validi', 'invite_payload_invalid');
        }

        if ($invited_id === (int) $me) {
            $this->failValidation('Non puoi invitare te stesso', 'invite_self_not_allowed');
        }

        $rule = RateLimiter::getRule('location_invite', 6, 60, 1, 50, 1, 600);
        $this->enforceRate(
            'location.invite.send',
            $rule['limit'],
            $rule['window'],
            'character:' . (int) $me . ':target:' . (int) $invited_id,
            'Stai inviando inviti troppo velocemente.',
            'location_invite_rate_limited',
        );

        $this->locationService()->createOrRefreshInvite(
            (int) $me,
            (int) $location_id,
            (int) $invited_id,
        );

        ResponseEmitter::emit(ApiResponse::json([
            'status' => 'ok',
        ]));
    }

    public function respondInvite()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $me = \Core\AuthGuard::api()->requireCharacter();
        $data = InputValidator::postJsonObject(RequestData::fromGlobals(), 'data', true);

        $invite_id = InputValidator::integer($data, 'invite_id');
        $action = InputValidator::string($data, 'action');

        if ($invite_id <= 0 || ($action !== 'accept' && $action !== 'decline')) {
            $this->failValidation('Invito non valido', 'invite_invalid');
        }

        $rule = RateLimiter::getRule('location_invite_respond', 12, 60, 1, 100, 1, 600);
        $this->enforceRate(
            'location.invite.respond',
            $rule['limit'],
            $rule['window'],
            'character:' . (int) $me,
            'Stai rispondendo agli inviti troppo velocemente.',
            'location_invite_respond_rate_limited',
        );

        $this->expirePendingInvites();
        $newStatus = ($action === 'accept') ? 'accepted' : 'declined';
        $invite = $this->locationService()->respondInvite(
            (int) $invite_id,
            (int) $me,
            $newStatus,
        );

        $response = [
            'status' => $newStatus,
        ];
        if ($action === 'accept') {
            $response['location'] = [
                'id' => (int) $invite->location_id,
                'map_id' => (int) $invite->map_id,
            ];
        }

        ResponseEmitter::emit(ApiResponse::json($response));
    }

    public function inviteUpdates()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $me = \Core\AuthGuard::api()->requireCharacter();

        $this->expirePendingInvites();
        $dataset = $this->locationService()->listOwnerInviteUpdates((int) $me);

        if (!empty($dataset)) {
            $ids = [];
            foreach ($dataset as $row) {
                $ids[] = (int) $row->id;
            }
            $this->locationService()->markOwnerInviteUpdatesNotified($ids);
        }

        ResponseEmitter::emit(ApiResponse::json([
            'dataset' => $dataset,
        ]));
    }

    private function getCharacter($character_id)
    {
        return $this->locationService()->getCharacterById((int) $character_id);
    }

    private function getAcceptedInvites($character_id)
    {
        return $this->locationService()->getAcceptedInvitesSet((int) $character_id);
    }

    private function evaluateAccess($location, $character, $invitedSet, $guildAccessSet = null)
    {
        if (!is_array($invitedSet)) {
            $invitedSet = [];
        }
        if (!is_array($guildAccessSet) && $guildAccessSet !== null) {
            $guildAccessSet = null;
        }

        return $this->locationService()->evaluateAccess(
            $location,
            $character,
            $invitedSet,
            $guildAccessSet,
        );
    }

    private function getGuildAccessSet($character_id)
    {
        return $this->locationService()->getGuildAccessSet((int) $character_id);
    }

    private function expirePendingInvites()
    {
        $this->locationService()->expirePendingInvites();
    }

    private function logAccess($character_id, $location_id, $allowed, $reason_code, $reason = null)
    {
        $this->locationService()->logAccess(
            (int) $character_id,
            (int) $location_id,
            (int) $allowed,
            $reason_code === null ? null : (string) $reason_code,
            $reason === null ? null : (string) $reason,
        );
    }

    public function staffNotesList()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireNarrativeStaff();
        $actorCharacterId = \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        $locationId = InputValidator::integer($data, 'location_id', 0);
        if ($locationId <= 0) {
            $this->failValidation('Location non valida', 'location_invalid');
        }
        $this->canAccess((int) $locationId, (int) $actorCharacterId);

        $rows = static::db()->fetchAllPrepared(
            'SELECT lsn.id, lsn.location_id, lsn.author_user_id, lsn.author_character_id, lsn.note_text, lsn.priority, lsn.date_created, lsn.date_updated,
                    c.name AS author_name, c.surname AS author_surname
             FROM location_staff_notes lsn
             LEFT JOIN characters c ON c.id = lsn.author_character_id
             WHERE lsn.location_id = ?
             ORDER BY lsn.date_updated DESC, lsn.id DESC',
            [(int) $locationId],
        );

        ResponseEmitter::emit(ApiResponse::json([
            'success' => true,
            'dataset' => !empty($rows) ? $rows : [],
        ]));
    }

    public function staffNoteUpsert()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireNarrativeStaff();
        $actorUserId = \Core\AuthGuard::api()->requireUser();
        $actorCharacterId = \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        $locationId = InputValidator::integer($data, 'location_id', 0);
        if ($locationId <= 0) {
            $this->failValidation('Location non valida', 'location_invalid');
        }
        $this->canAccess((int) $locationId, (int) $actorCharacterId);

        $noteText = trim(InputValidator::string($data, 'note_text', ''));
        if ($noteText === '') {
            $this->failValidation('Nota non valida', 'staff_note_invalid');
        }
        if (mb_strlen($noteText) > 5000) {
            $this->failValidation('Nota troppo lunga', 'staff_note_too_long');
        }

        $priority = strtolower(trim(InputValidator::string($data, 'priority', 'normal')));
        if (!in_array($priority, ['low', 'normal', 'high'], true)) {
            $priority = 'normal';
        }

        $id = InputValidator::integer($data, 'id', 0);
        if ($id > 0) {
            static::db()->executePrepared(
                'UPDATE location_staff_notes SET
                    note_text = ?,
                    priority = ?,
                    date_updated = NOW()
                 WHERE id = ?
                   AND location_id = ?
                 LIMIT 1',
                [$noteText, $priority, (int) $id, (int) $locationId],
            );
        } else {
            static::db()->executePrepared(
                'INSERT INTO location_staff_notes SET
                    location_id = ?,
                    author_user_id = ?,
                    author_character_id = ?,
                    note_text = ?,
                    priority = ?,
                    date_created = NOW()',
                [(int) $locationId, (int) $actorUserId, (int) $actorCharacterId, $noteText, $priority],
            );
            $id = (int) static::db()->lastInsertID();
        }

        $row = static::db()->fetchOnePrepared(
            'SELECT lsn.id, lsn.location_id, lsn.author_user_id, lsn.author_character_id, lsn.note_text, lsn.priority, lsn.date_created, lsn.date_updated,
                    c.name AS author_name, c.surname AS author_surname
             FROM location_staff_notes lsn
             LEFT JOIN characters c ON c.id = lsn.author_character_id
             WHERE lsn.id = ?
             LIMIT 1',
            [(int) $id],
        );

        ResponseEmitter::emit(ApiResponse::json([
            'success' => true,
            'dataset' => $row,
        ]));
    }

    public function staffNoteDelete()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireNarrativeStaff();
        \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        $noteId = InputValidator::integer($data, 'id', 0);
        if ($noteId <= 0) {
            $this->failValidation('Nota non valida', 'staff_note_invalid');
        }

        static::db()->executePrepared(
            'DELETE FROM location_staff_notes WHERE id = ? LIMIT 1',
            [(int) $noteId],
        );

        ResponseEmitter::emit(ApiResponse::json([
            'success' => true,
        ]));
    }

    public function staffFlagsList()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireNarrativeStaff();
        $actorCharacterId = \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        $locationId = InputValidator::integer($data, 'location_id', 0);
        if ($locationId <= 0) {
            $this->failValidation('Location non valida', 'location_invalid');
        }
        $this->canAccess((int) $locationId, (int) $actorCharacterId);

        $rows = static::db()->fetchAllPrepared(
            'SELECT f.id, f.location_id, f.character_id, f.flag, f.note_text, f.created_by_user_id, f.created_by_character_id, f.date_created, f.date_updated,
                    c.name, c.surname
             FROM location_staff_character_flags f
             INNER JOIN characters c ON c.id = f.character_id
             WHERE f.location_id = ?
             ORDER BY f.flag DESC, c.name ASC, c.surname ASC',
            [(int) $locationId],
        );

        ResponseEmitter::emit(ApiResponse::json([
            'success' => true,
            'dataset' => !empty($rows) ? $rows : [],
        ]));
    }

    public function staffFlagUpsert()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireNarrativeStaff();
        $actorUserId = \Core\AuthGuard::api()->requireUser();
        $actorCharacterId = \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        $locationId = InputValidator::integer($data, 'location_id', 0);
        $targetCharacterId = InputValidator::integer($data, 'character_id', 0);
        if ($locationId <= 0 || $targetCharacterId <= 0) {
            $this->failValidation('Dati non validi', 'staff_flag_invalid');
        }
        $this->canAccess((int) $locationId, (int) $actorCharacterId);

        $flag = strtolower(trim(InputValidator::string($data, 'flag', 'none')));
        if (!in_array($flag, ['none', 'monitor', 'urgent', 'critical'], true)) {
            $flag = 'none';
        }
        $noteText = trim(InputValidator::string($data, 'note_text', ''));
        if (mb_strlen($noteText) > 255) {
            $noteText = mb_substr($noteText, 0, 255);
        }
        if ($noteText === '') {
            $noteText = null;
        }

        if ($flag === 'none' && $noteText === null) {
            static::db()->executePrepared(
                'DELETE FROM location_staff_character_flags
                 WHERE location_id = ?
                   AND character_id = ?
                 LIMIT 1',
                [(int) $locationId, (int) $targetCharacterId],
            );
            ResponseEmitter::emit(ApiResponse::json([
                'success' => true,
                'dataset' => [
                    'location_id' => (int) $locationId,
                    'character_id' => (int) $targetCharacterId,
                    'flag' => 'none',
                    'note_text' => null,
                ],
            ]));
            return;
        }

        static::db()->executePrepared(
            'INSERT INTO location_staff_character_flags
                (location_id, character_id, flag, note_text, created_by_user_id, created_by_character_id, date_created, date_updated)
             VALUES
                (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                flag = VALUES(flag),
                note_text = VALUES(note_text),
                created_by_user_id = VALUES(created_by_user_id),
                created_by_character_id = VALUES(created_by_character_id),
                date_updated = NOW()',
            [(int) $locationId, (int) $targetCharacterId, $flag, $noteText, (int) $actorUserId, (int) $actorCharacterId],
        );

        $row = static::db()->fetchOnePrepared(
            'SELECT f.id, f.location_id, f.character_id, f.flag, f.note_text, f.created_by_user_id, f.created_by_character_id, f.date_created, f.date_updated,
                    c.name, c.surname
             FROM location_staff_character_flags f
             INNER JOIN characters c ON c.id = f.character_id
             WHERE f.location_id = ?
               AND f.character_id = ?
             LIMIT 1',
            [(int) $locationId, (int) $targetCharacterId],
        );

        ResponseEmitter::emit(ApiResponse::json([
            'success' => true,
            'dataset' => $row,
        ]));
    }

    public function staffLocationsList()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireNarrativeStaff();
        \Core\AuthGuard::api()->requireCharacter();

        $rows = static::db()->fetchAllPrepared(
            'SELECT l.id, l.name, l.map_id, m.name AS map_name
             FROM locations l
             LEFT JOIN maps m ON m.id = l.map_id
             WHERE l.date_deleted IS NULL
             ORDER BY m.name ASC, l.name ASC, l.id ASC',
            [],
        );

        ResponseEmitter::emit(ApiResponse::json([
            'success' => true,
            'dataset' => !empty($rows) ? $rows : [],
        ]));
    }

    public function staffTeleport()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireNarrativeStaff();
        $actorUserId = \Core\AuthGuard::api()->requireUser();
        $actorCharacterId = \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        $targetCharacterId = InputValidator::integer($data, 'character_id', 0);
        $targetLocationId = InputValidator::integer($data, 'target_location_id', 0);
        $reason = trim(InputValidator::string($data, 'reason', ''));
        if ($targetCharacterId <= 0 || $targetLocationId <= 0) {
            $this->failValidation('Dati non validi', 'staff_teleport_invalid');
        }
        if ($reason === '') {
            $this->failValidation('Motivazione obbligatoria', 'staff_teleport_reason_required');
        }

        $targetCharacter = static::db()->fetchOnePrepared(
            'SELECT id, user_id, last_location, last_map
             FROM characters
             WHERE id = ?
             LIMIT 1',
            [(int) $targetCharacterId],
        );
        if (empty($targetCharacter)) {
            $this->failValidation('Personaggio non valido', 'character_invalid');
        }

        $targetLocation = static::db()->fetchOnePrepared(
            'SELECT id, map_id, name
             FROM locations
             WHERE id = ?
               AND date_deleted IS NULL
             LIMIT 1',
            [(int) $targetLocationId],
        );
        if (empty($targetLocation)) {
            $this->failValidation('Location non valida', 'location_invalid');
        }

        $targetMapId = (int) ($targetLocation->map_id ?? 0);
        static::db()->executePrepared(
            'UPDATE characters SET
                last_location = ?,
                last_map = ?,
                date_last_seed = NOW()
             WHERE id = ?
             LIMIT 1',
            [(int) $targetLocationId, $targetMapId > 0 ? $targetMapId : null, (int) $targetCharacterId],
        );

        if ((int) $targetCharacterId === (int) $actorCharacterId) {
            SessionStore::set('character_last_location', (int) $targetLocationId);
            SessionStore::set('character_last_map', $targetMapId > 0 ? $targetMapId : null);
        }

        AuditLogService::write(
            'location',
            'staff_teleport',
            [
                'target_character_id' => (int) $targetCharacterId,
                'from_location_id' => (int) ($targetCharacter->last_location ?? 0),
                'to_location_id' => (int) $targetLocationId,
                'to_map_id' => $targetMapId,
                'reason' => $reason,
                'actor_user_id' => (int) $actorUserId,
                'actor_character_id' => (int) $actorCharacterId,
            ],
            'game/location',
            \Core\Router::currentUri(),
            (int) $actorUserId,
        );

        ResponseEmitter::emit(ApiResponse::json([
            'success' => true,
            'dataset' => [
                'character_id' => (int) $targetCharacterId,
                'location_id' => (int) $targetLocationId,
                'map_id' => $targetMapId,
                'location_name' => (string) ($targetLocation->name ?? ''),
            ],
        ]));
    }

    /**
     * @param array<int,mixed> $dataset
     */
    private function enrichRequiredSocialStatusFields(array &$dataset): void
    {
        if (empty($dataset)) {
            return;
        }

        $statusIds = [];
        foreach ($dataset as $row) {
            if (!is_object($row)) {
                continue;
            }

            $statusId = isset($row->min_socialstatus_id) ? (int) $row->min_socialstatus_id : 0;
            if ($statusId > 0) {
                $statusIds[$statusId] = $statusId;
            }
        }

        $statusById = [];
        foreach ($statusIds as $statusId) {
            $status = \App\Services\SocialStatusProviderRegistry::getById((int) $statusId);
            if ($status !== null) {
                $statusById[(int) $statusId] = $status;
            }
        }

        foreach ($dataset as $row) {
            if (!is_object($row)) {
                continue;
            }

            $statusId = isset($row->min_socialstatus_id) ? (int) $row->min_socialstatus_id : 0;
            $status = $statusId > 0 && isset($statusById[$statusId]) ? $statusById[$statusId] : null;

            $row->required_status_name = $status !== null ? ($status->name ?? null) : null;
            $row->required_status_min = $status !== null ? ($status->min ?? null) : null;
        }
    }

    public function create()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);

        return $this;
    }

    public function update()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);

        return $this;
    }

}
