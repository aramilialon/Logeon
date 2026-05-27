<?php

declare(strict_types=1);

/**
 * Demo investor data manager (CLI).
 *
 * Seed:
 *   C:\xampp\php\php.exe scripts/php/demo-investor-data.php seed --tag=investor-demo --users=3 --replace=1
 *
 * Purge:
 *   C:\xampp\php\php.exe scripts/php/demo-investor-data.php purge --tag=investor-demo
 *
 * Status:
 *   C:\xampp\php\php.exe scripts/php/demo-investor-data.php status --tag=investor-demo
 */

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;

const DEMO_MODULE_ID = 'logeon.demo-data';
const DEMO_ARTIFACT_TYPE = 'investor_seed';
const DEMO_DEFAULT_TAG = 'investor-demo';
const DEMO_DEFAULT_USERS = 3;
const DEMO_DEFAULT_PASSWORD = 'Demo2026!';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "[FAIL] Questo script e utilizzabile solo da CLI.\n");
    exit(1);
}

$root = dirname(__DIR__, 2);
$bootstrap = [
    $root . '/configs/config.php',
    $root . '/configs/db.php',
    $root . '/configs/app.php',
    $root . '/vendor/autoload.php',
];

foreach ($bootstrap as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "[FAIL] File bootstrap mancante: {$file}\n");
        exit(1);
    }
    require_once $file;
}

$customBootstrap = $root . '/custom/bootstrap.php';
if (is_file($customBootstrap)) {
    require_once $customBootstrap;
}

/**
 * @return array{action:string,tag:string,users:int,replace:bool,password:string}
 */
function demoParseArgs(array $argv): array
{
    $action = '';
    $tag = DEMO_DEFAULT_TAG;
    $users = DEMO_DEFAULT_USERS;
    $replace = false;
    $password = DEMO_DEFAULT_PASSWORD;

    foreach ($argv as $idx => $arg) {
        if ($idx === 0) {
            continue;
        }

        $raw = trim((string) $arg);
        if ($raw === '') {
            continue;
        }

        if ($raw[0] !== '-') {
            $action = strtolower($raw);
            continue;
        }

        if (strpos($raw, '--tag=') === 0) {
            $tag = trim((string) substr($raw, 6));
            continue;
        }
        if (strpos($raw, '--users=') === 0) {
            $users = (int) substr($raw, 8);
            continue;
        }
        if (strpos($raw, '--replace=') === 0) {
            $replace = ((int) substr($raw, 10) === 1);
            continue;
        }
        if (strpos($raw, '--password=') === 0) {
            $password = (string) substr($raw, 11);
            continue;
        }
    }

    if ($action === '') {
        $action = 'status';
    }

    if ($users < 2) {
        $users = 2;
    }
    if ($users > 3) {
        $users = 3;
    }

    $tag = strtolower(preg_replace('/[^a-zA-Z0-9\-_]+/', '-', $tag) ?? DEMO_DEFAULT_TAG);
    $tag = trim($tag, '-_');
    if ($tag === '') {
        $tag = DEMO_DEFAULT_TAG;
    }

    if ($password === '') {
        $password = DEMO_DEFAULT_PASSWORD;
    }

    return [
        'action' => $action,
        'tag' => $tag,
        'users' => $users,
        'replace' => $replace,
        'password' => $password,
    ];
}

function demoInfo(string $message): void
{
    fwrite(STDOUT, "[INFO] {$message}" . PHP_EOL);
}

function demoOk(string $message): void
{
    fwrite(STDOUT, "[OK] {$message}" . PHP_EOL);
}

function demoFail(string $message): void
{
    fwrite(STDERR, "[FAIL] {$message}" . PHP_EOL);
}

/**
 * @param mixed $row
 * @return mixed
 */
function demoRowGet($row, string $key, $default = null)
{
    if (is_array($row)) {
        return array_key_exists($key, $row) ? $row[$key] : $default;
    }
    if (is_object($row) && isset($row->{$key})) {
        return $row->{$key};
    }
    return $default;
}

function demoTableExists(DbAdapterInterface $db, string $table): bool
{
    $row = $db->fetchOnePrepared(
        'SELECT 1 AS ok
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         LIMIT 1',
        [$table],
    );

    return !empty($row);
}

/**
 * @return array<string,mixed>|null
 */
function demoLoadArtifact(DbAdapterInterface $db, string $tag): ?array
{
    $row = $db->fetchOnePrepared(
        'SELECT artifact_payload
         FROM module_runtime_artifacts
         WHERE module_id = ?
           AND artifact_type = ?
           AND artifact_key = ?
         LIMIT 1',
        [DEMO_MODULE_ID, DEMO_ARTIFACT_TYPE, $tag],
    );

    $artifactPayload = demoRowGet($row, 'artifact_payload');
    if (!is_string($artifactPayload) || $artifactPayload === '') {
        return null;
    }

    $payload = json_decode($artifactPayload, true);
    return is_array($payload) ? $payload : null;
}

function demoSaveArtifact(DbAdapterInterface $db, string $tag, array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        throw new RuntimeException('Impossibile serializzare payload demo.');
    }

    $checksum = sha1($json);
    $db->executePrepared(
        'INSERT INTO module_runtime_artifacts
            (module_id, artifact_type, artifact_key, artifact_scope, artifact_payload, checksum_sha1, date_seen, date_created)
         VALUES
            (?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            artifact_scope = VALUES(artifact_scope),
            artifact_payload = VALUES(artifact_payload),
            checksum_sha1 = VALUES(checksum_sha1),
            date_seen = NOW()',
        [DEMO_MODULE_ID, DEMO_ARTIFACT_TYPE, $tag, 'investor-demo', $json, $checksum],
    );
}

function demoDeleteArtifact(DbAdapterInterface $db, string $tag): void
{
    $db->executePrepared(
        'DELETE FROM module_runtime_artifacts
         WHERE module_id = ?
           AND artifact_type = ?
           AND artifact_key = ?',
        [DEMO_MODULE_ID, DEMO_ARTIFACT_TYPE, $tag],
    );
}

/**
 * @return array<int,int>
 */
function demoResolveForumIds(DbAdapterInterface $db): array
{
    if (!demoTableExists($db, 'forums')) {
        return [];
    }

    $rows = $db->fetchAllPrepared('SELECT id FROM forums ORDER BY id ASC');
    $ids = [];
    foreach ($rows as $row) {
        $id = (int) demoRowGet($row, 'id', 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return $ids;
}

function demoResolveMapId(DbAdapterInterface $db): int
{
    if (!demoTableExists($db, 'maps')) {
        return 0;
    }

    $row = $db->fetchOnePrepared('SELECT id FROM maps WHERE initial = 1 ORDER BY id ASC LIMIT 1', []);
    $mapId = (int) demoRowGet($row, 'id', 0);
    if ($mapId > 0) {
        return $mapId;
    }

    $row = $db->fetchOnePrepared('SELECT id FROM maps ORDER BY id ASC LIMIT 1', []);
    return max(0, (int) demoRowGet($row, 'id', 0));
}

function demoResolveLocationId(DbAdapterInterface $db, int $mapId): int
{
    if (!demoTableExists($db, 'locations')) {
        return 0;
    }

    if ($mapId > 0) {
        $row = $db->fetchOnePrepared(
            'SELECT id
             FROM locations
             WHERE date_deleted IS NULL
               AND map_id = ?
             ORDER BY is_chat DESC, id ASC
             LIMIT 1',
            [$mapId],
        );
        $locationId = (int) demoRowGet($row, 'id', 0);
        if ($locationId > 0) {
            return $locationId;
        }
    }

    $row = $db->fetchOnePrepared(
        'SELECT id FROM locations WHERE date_deleted IS NULL ORDER BY is_chat DESC, id ASC LIMIT 1',
        [],
    );
    return max(0, (int) demoRowGet($row, 'id', 0));
}

function demoResolveDefaultCurrencyId(DbAdapterInterface $db): int
{
    if (!demoTableExists($db, 'currencies')) {
        return 0;
    }

    $row = $db->fetchOnePrepared(
        'SELECT id
         FROM currencies
         WHERE is_active = 1
         ORDER BY is_default DESC, id ASC
         LIMIT 1',
        [],
    );
    return max(0, (int) demoRowGet($row, 'id', 0));
}

/**
 * @return array{userIds:array<int,int>,characterIds:array<int,int>,tables:array<string,array<int,int>>,emailDomain:string}
 */
function demoSeed(DbAdapterInterface $db, string $tag, int $userCount, string $password): array
{
    $forumIds = demoResolveForumIds($db);
    $mapId = demoResolveMapId($db);
    $locationId = demoResolveLocationId($db, $mapId);
    $currencyId = demoResolveDefaultCurrencyId($db);

    $emailDomain = 'mailhub-' . substr(sha1($tag), 0, 6) . '.local';
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($passwordHash) || $passwordHash === '') {
        throw new RuntimeException('Impossibile generare hash password demo.');
    }

    $loremShort = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed non risus.';
    $loremLong = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed non risus. Suspendisse lectus tortor, dignissim sit amet, adipiscing nec, ultricies sed, dolor. Cras elementum ultrices diam.';
    $loremHtml = '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p><p>Sed non risus. Suspendisse lectus tortor, dignissim sit amet.</p>';

    $namePool = ['Luca', 'Marta', 'Davide', 'Elena', 'Andrea', 'Ilaria'];
    $surnamePool = ['Rinaldi', 'Conti', 'Bellini', 'Ferri', 'Marin', 'Moretti'];
    $eventTitles = ['Pattuglia serale', 'Briefing narrativo', 'Missione di supporto', 'Vertice operativo'];

    $tables = [
        'users' => [],
        'characters' => [],
        'forum_threads' => [],
        'messages_threads' => [],
        'messages' => [],
        'locations_messages' => [],
        'notifications' => [],
        'character_events' => [],
        'location_access_logs' => [],
        'guilds' => [],
        'guild_roles' => [],
        'guild_members' => [],
        'guild_logs' => [],
        'guild_events' => [],
        'item_categories' => [],
        'item_rarities' => [],
        'items' => [],
        'shops' => [],
        'shop_inventory' => [],
        'shop_purchases' => [],
        'shop_sales' => [],
        'inventory_items' => [],
        'character_item_instances' => [],
        'character_equipment' => [],
        'item_equipment_rules' => [],
        'narrative_events' => [],
        'system_events' => [],
        'system_event_participations' => [],
        'system_event_effects' => [],
        'jobs' => [],
        'job_levels' => [],
        'job_tasks' => [],
        'job_task_choices' => [],
        'character_jobs' => [],
        'character_job_tasks' => [],
        'job_logs' => [],
        'character_attribute_definitions' => [],
        'character_attribute_values' => [],
        'character_attribute_rules' => [],
        'character_attribute_rule_steps' => [],
        'lf_abilities_spells_categories' => [],
        'lf_abilities_spells_point_categories' => [],
        'lf_abilities_spells_abilities' => [],
        'lf_abilities_spells_level_rules' => [],
        'lf_abilities_spells_effects' => [],
        'lf_abilities_spells_requirements' => [],
        'lf_abilities_spells_grants' => [],
        'lf_abilities_spells_rank_point_rewards' => [],
        'lf_abilities_spells_character_points' => [],
        'lf_abilities_spells_character_abilities' => [],
        'lf_abilities_spells_character_point_logs' => [],
        'quest_definitions' => [],
        'quest_step_definitions' => [],
        'quest_instances' => [],
        'quest_step_instances' => [],
        'quest_conditions' => [],
        'quest_outcomes' => [],
        'quest_event_links' => [],
        'quest_progress_logs' => [],
        'quest_reward_assignments' => [],
        'quest_closure_reports' => [],
        'sys_logs' => [],
    ];

    $userIds = [];
    $characterIds = [];

    $db->query('START TRANSACTION');
    try {
        for ($i = 0; $i < $userCount; $i++) {
            $first = $namePool[$i % count($namePool)];
            $last = $surnamePool[$i % count($surnamePool)];
            $email = strtolower($first . '.' . $last . '.' . str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) . '@' . $emailDomain);
            $gender = (int) ($i % 2);

            $db->executePrepared(
                'INSERT INTO users
                    (email, password, gender, is_administrator, is_superuser, superuser_role, is_moderator, is_master,
                     date_actived, date_created, date_last_signin, date_last_seed)
                 VALUES
                    (AES_ENCRYPT(?, ?), ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW(), NOW())',
                [
                    $email,
                    (string) DB['crypt_key'],
                    $passwordHash,
                    $gender,
                    ($i === 0) ? 1 : 0,
                    ($i === 0) ? 1 : 0,
                    ($i === 0) ? 'gestore' : null,
                    ($i === 1) ? 1 : 0,
                    ($i === 2) ? 1 : 0,
                ],
            );
            $userId = $db->lastInsertId();
            $userIds[] = $userId;
            $tables['users'][] = $userId;

            $availability = ($i % 3) + 1;
            $health = 92 + ($i * 2);
            $rank = 2 + $i;
            $experience = (float) (120 + ($i * 40));
            $money = 260 + ($i * 90);
            $bank = 500 + ($i * 220);

            $db->executePrepared(
                'INSERT INTO characters
                    (user_id, socialstatus_id, name, surname, gender, loanface, last_map, last_location,
                     description_body, description_temper, background_story, mod_status,
                     privacy_show_online, availability, is_visible,
                     health, health_max, experience, rank, money, bank, fame,
                     date_created, date_last_signin, date_last_seed)
                 VALUES
                    (?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
                [
                    $userId,
                    $first,
                    $last,
                    $gender,
                    '/assets/imgs/defaults-images/default-profile.png',
                    $mapId > 0 ? $mapId : null,
                    $locationId > 0 ? $locationId : null,
                    $loremLong,
                    $loremShort,
                    $loremLong,
                    'Profilo demo pronto per presentazione piattaforma.',
                    $availability,
                    $health,
                    (float) $health,
                    $experience,
                    $rank,
                    $money,
                    $bank,
                    (float) ($i * 3),
                ],
            );

            $characterId = $db->lastInsertId();
            $characterIds[] = $characterId;
            $tables['characters'][] = $characterId;
        }

        $focusCharacterIds = $characterIds;
        $focusUserByCharacter = [];
        foreach ($characterIds as $idx => $characterId) {
            $focusUserByCharacter[(int) $characterId] = (int) ($userIds[$idx] ?? 0);
        }

        $existingSuperusers = $db->fetchAllPrepared(
            'SELECT c.id AS character_id, c.user_id
             FROM users u
             INNER JOIN characters c ON c.user_id = u.id
             WHERE u.is_superuser = 1
               AND u.date_actived IS NOT NULL
               AND c.id IS NOT NULL
             ORDER BY u.id ASC',
            [],
        );
        foreach ($existingSuperusers as $row) {
            $existingCharacterId = (int) demoRowGet($row, 'character_id', 0);
            $existingUserId = (int) demoRowGet($row, 'user_id', 0);
            if ($existingCharacterId <= 0) {
                continue;
            }
            if (!in_array($existingCharacterId, $focusCharacterIds, true)) {
                $focusCharacterIds[] = $existingCharacterId;
            }
            if (!isset($focusUserByCharacter[$existingCharacterId])) {
                $focusUserByCharacter[$existingCharacterId] = $existingUserId;
            }
        }

        $presentationCharacterId = (int) ($focusCharacterIds[0] ?? 0);
        foreach ($existingSuperusers as $row) {
            $existingCharacterId = (int) demoRowGet($row, 'character_id', 0);
            if ($existingCharacterId > 0) {
                $presentationCharacterId = $existingCharacterId;
                break;
            }
        }
        $presentationUserId = (int) ($focusUserByCharacter[$presentationCharacterId] ?? 0);

        $db->executePrepared(
            'INSERT INTO guilds
                (name, icon, purpose_html, objectives_html, is_visible, leader_character_id, date_created)
             VALUES
                (?, ?, ?, ?, 1, ?, NOW())',
            ['Consorzio Mercanti', '/assets/imgs/defaults-images/default-icon.png', '<p>' . $loremLong . '</p>', $loremHtml, $characterIds[0] ?? null],
        );
        $guildId = $db->lastInsertId();
        $tables['guilds'][] = $guildId;

        $db->executePrepared(
            'INSERT INTO guild_roles (guild_id, name, monthly_salary, is_leader, is_officer, is_default, date_created)
             VALUES (?, ?, ?, 1, 1, 0, NOW())',
            [$guildId, 'Guida', 400],
        );
        $leaderRoleId = $db->lastInsertId();
        $tables['guild_roles'][] = $leaderRoleId;

        $db->executePrepared(
            'INSERT INTO guild_roles (guild_id, name, monthly_salary, is_leader, is_officer, is_default, date_created)
             VALUES (?, ?, ?, 0, 0, 1, NOW())',
            [$guildId, 'Membro', 160],
        );
        $memberRoleId = $db->lastInsertId();
        $tables['guild_roles'][] = $memberRoleId;

        foreach ($characterIds as $idx => $characterId) {
            $db->executePrepared(
                'INSERT INTO guild_members (guild_id, character_id, role_id, is_primary, date_joined)
                 VALUES (?, ?, ?, ?, NOW())',
                [$guildId, $characterId, $idx === 0 ? $leaderRoleId : $memberRoleId, $idx === 0 ? 1 : 0],
            );
            $tables['guild_members'][] = $db->lastInsertId();
        }

        $db->executePrepared(
            'INSERT INTO guild_logs (guild_id, action, actor_id, target_id, meta, date_created)
             VALUES (?, ?, ?, NULL, ?, NOW())',
            [$guildId, 'guild_seeded', $characterIds[0] ?? null, 'Setup demo completato'],
        );
        $tables['guild_logs'][] = $db->lastInsertId();

        if (demoTableExists($db, 'guild_events')) {
            $db->executePrepared(
                'INSERT INTO guild_events (guild_id, title, body_html, starts_at, ends_at, created_by, date_created)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 1 DAY), INTERVAL 2 HOUR), ?, NOW())',
                [$guildId, 'Briefing gilda settimanale', $loremHtml, $characterIds[0] ?? null],
            );
            $tables['guild_events'][] = $db->lastInsertId();
        }

        if ($forumIds !== []) {
            for ($i = 0; $i < 4; $i++) {
                $forumId = $forumIds[$i % count($forumIds)];
                $authorCharacterId = $characterIds[$i % count($characterIds)];
                $db->executePrepared(
                    'INSERT INTO forum_threads (father_id, forum_id, character_id, title, body, date_created, is_important, is_closed)
                     VALUES (NULL, ?, ?, ?, ?, NOW(), ?, 0)',
                    [$forumId, $authorCharacterId, 'Aggiornamento operativo #' . ($i + 1), $loremLong, ($i === 0) ? 1 : 0],
                );
                $topicId = $db->lastInsertId();
                $tables['forum_threads'][] = $topicId;

                $db->executePrepared(
                    'INSERT INTO forum_threads (father_id, forum_id, character_id, title, body, date_created, is_important, is_closed)
                     VALUES (?, ?, ?, NULL, ?, NOW(), 0, 0)',
                    [$topicId, $forumId, $characterIds[($i + 1) % count($characterIds)], $loremShort],
                );
                $tables['forum_threads'][] = $db->lastInsertId();
            }
        }

        $dmBodies = [
            'Confermo ricezione. ' . $loremShort,
            'Aggiornamento rapido: ' . $loremShort,
            'Promemoria operativo. ' . $loremShort,
        ];
        for ($i = 0; $i < count($characterIds); $i++) {
            for ($j = $i + 1; $j < count($characterIds); $j++) {
                $charOne = (int) $characterIds[$i];
                $charTwo = (int) $characterIds[$j];

                $db->executePrepared(
                    'INSERT INTO messages_threads
                        (character_one, character_two, last_message_body, last_message_type, last_sender_id, date_last_message, date_created, subject, thread_type, deleted_for_one, deleted_for_two)
                     VALUES
                        (?, ?, NULL, \'on\', NULL, NULL, NOW(), ?, \'on\', 0, 0)',
                    [$charOne, $charTwo, 'Conversazione operativa'],
                );
                $threadId = $db->lastInsertId();
                $tables['messages_threads'][] = $threadId;

                $lastBody = '';
                $lastType = 'on';
                $lastSender = $charOne;
                for ($m = 0; $m < 3; $m++) {
                    $sender = ($m % 2 === 0) ? $charOne : $charTwo;
                    $recipient = ($sender === $charOne) ? $charTwo : $charOne;
                    $body = $dmBodies[($m + $i + $j) % count($dmBodies)];
                    $type = ($m === 2) ? 'off' : 'on';
                    $isRead = ($m < 2) ? 1 : 0;
                    $db->executePrepared(
                        'INSERT INTO messages (thread_id, sender_id, recipient_id, body, message_type, is_read, date_created)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())',
                        [$threadId, $sender, $recipient, $body, $type, $isRead],
                    );
                    $tables['messages'][] = $db->lastInsertId();
                    $lastBody = $body;
                    $lastType = $type;
                    $lastSender = $sender;
                }

                $db->executePrepared(
                    'UPDATE messages_threads
                     SET last_message_body = ?, last_message_type = ?, last_sender_id = ?, date_last_message = NOW()
                     WHERE id = ?',
                    [$lastBody, $lastType, $lastSender, $threadId],
                );
            }
        }

        $notificationTopics = ['forum', 'quest', 'guild', 'system'];
        foreach ($userIds as $i => $recipientUserId) {
            $recipientCharacterId = $characterIds[$i] ?? null;
            for ($n = 0; $n < 5; $n++) {
                $actorIdx = ($i + $n + 1) % count($userIds);
                $topic = $notificationTopics[($i + $n) % count($notificationTopics)];
                $isRead = ($n % 4 === 0) ? 1 : 0;

                $db->executePrepared(
                    'INSERT INTO notifications
                        (recipient_user_id, recipient_character_id, actor_user_id, actor_character_id,
                         kind, topic, priority, title, message, action_status, action_decision, action_url,
                         source_type, source_id, source_meta_json, dedup_key, is_read, read_at, expires_at, date_created, date_updated)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())',
                    [
                        $recipientUserId,
                        $recipientCharacterId,
                        $userIds[$actorIdx] ?? null,
                        $characterIds[$actorIdx] ?? null,
                        'system_update',
                        $topic,
                        ($n % 5 === 0) ? 'high' : 'normal',
                        'Nuovo aggiornamento',
                        $loremShort,
                        $isRead ? 'resolved' : (($n % 2 === 0) ? 'pending' : 'none'),
                        '/game',
                        'demo_seed',
                        $locationId > 0 ? $locationId : null,
                        json_encode(['topic' => $topic], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'demo:' . $tag . ':' . $recipientUserId . ':' . $n . ':' . $topic,
                        $isRead,
                        $isRead ? date('Y-m-d H:i:s') : null,
                    ],
                );
                $tables['notifications'][] = $db->lastInsertId();
            }
        }

        foreach ($characterIds as $idx => $characterId) {
            for ($e = 0; $e < 2; $e++) {
                $db->executePrepared(
                    'INSERT INTO character_events
                        (character_id, title, body, location_id, date_event, is_visible, created_by_user_id, created_by_character_id, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, 1, ?, ?, NOW())',
                    [
                        $characterId,
                        $eventTitles[($idx + $e) % count($eventTitles)],
                        $loremLong,
                        $locationId > 0 ? $locationId : null,
                        date('Y-m-d', strtotime('-' . (($idx + $e + 1) * 2) . ' days')),
                        $userIds[$idx] ?? $userIds[0],
                        $characterId,
                    ],
                );
                $tables['character_events'][] = $db->lastInsertId();
            }
        }

        if ($locationId > 0) {
            foreach ($characterIds as $characterId) {
                for ($l = 0; $l < 4; $l++) {
                    $db->executePrepared(
                        'INSERT INTO location_access_logs
                            (character_id, location_id, allowed, reason_code, reason, date_created)
                         VALUES
                            (?, ?, 1, ?, ?, NOW())',
                        [$characterId, $locationId, 'ok', 'Accesso consentito'],
                    );
                    $tables['location_access_logs'][] = $db->lastInsertId();
                }
            }
        }

        if (demoTableExists($db, 'narrative_events')) {
            for ($i = 0; $i < 8; $i++) {
                $status = ($i % 3 === 0) ? 'open' : 'closed';
                $db->executePrepared(
                    'INSERT INTO narrative_events
                        (title, event_type, event_mode, status, closed_at, closed_by, scope, impact_level, description, entity_refs, location_id, visibility, tags, source_system, source_ref_id, meta_json, created_by, created_at)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))',
                    [
                        'Evento narrativo #' . ($i + 1),
                        'manual',
                        ($i % 2 === 0) ? 'point' : 'scene',
                        $status,
                        $status === 'closed' ? date('Y-m-d H:i:s') : null,
                        $status === 'closed' ? ($characterIds[$i % count($characterIds)] ?? null) : null,
                        'local',
                        ($i % 4 === 0) ? 1 : 0,
                        $loremLong,
                        '[]',
                        $locationId > 0 ? $locationId : null,
                        'public',
                        'demo,evento',
                        'manual',
                        null,
                        json_encode(['source' => 'demo'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $characterIds[$i % count($characterIds)] ?? null,
                        $i * 3,
                    ],
                );
                $tables['narrative_events'][] = $db->lastInsertId();
            }
        }

        if (demoTableExists($db, 'system_events')) {
            for ($i = 0; $i < 3; $i++) {
                $status = ($i === 0) ? 'active' : (($i === 1) ? 'scheduled' : 'completed');
                $db->executePrepared(
                    'INSERT INTO system_events
                        (title, description, type, status, visibility, show_on_homepage_feed, scope_type, scope_id, participant_mode, starts_at, ends_at, recurrence, next_run_at, last_activity_at, meta_json, created_by, updated_by, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, 1, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY), DATE_ADD(NOW(), INTERVAL ? DAY), ?, ?, NOW(), ?, ?, ?, NOW())',
                    [
                        'Evento di sistema #' . ($i + 1),
                        $loremLong,
                        'general',
                        $status,
                        'public',
                        'global',
                        null,
                        'character',
                        2 - $i,
                        5 - $i,
                        'none',
                        $status === 'scheduled' ? date('Y-m-d H:i:s', strtotime('+1 day')) : null,
                        json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $characterIds[0] ?? null,
                        $characterIds[0] ?? null,
                    ],
                );
                $systemEventId = $db->lastInsertId();
                $tables['system_events'][] = $systemEventId;

                if (demoTableExists($db, 'system_event_participations')) {
                    foreach ($characterIds as $characterId) {
                        $db->executePrepared(
                            'INSERT INTO system_event_participations
                                (system_event_id, participant_mode, character_id, status, joined_by_character_id, date_joined, meta_json, date_created)
                             VALUES
                                (?, ?, ?, ?, ?, NOW(), ?, NOW())',
                            [
                                $systemEventId,
                                'character',
                                $characterId,
                                'joined',
                                $characterIds[0] ?? $characterId,
                                json_encode(['demo' => 'join'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ],
                        );
                        $tables['system_event_participations'][] = $db->lastInsertId();
                    }
                }

                if (demoTableExists($db, 'system_event_effects') && $currencyId > 0) {
                    $db->executePrepared(
                        'INSERT INTO system_event_effects
                            (system_event_id, effect_type, currency_id, amount, is_enabled, meta_json, created_by, updated_by, date_created)
                         VALUES
                            (?, ?, ?, ?, 1, ?, ?, ?, NOW())',
                        [
                            $systemEventId,
                            'currency_reward',
                            $currencyId,
                            25 + ($i * 10),
                            json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            $characterIds[0] ?? null,
                            $characterIds[0] ?? null,
                        ],
                    );
                    $tables['system_event_effects'][] = $db->lastInsertId();
                }
            }
        }

        $itemIds = [];

        if (
            $currencyId > 0
            && demoTableExists($db, 'item_categories')
            && demoTableExists($db, 'item_rarities')
            && demoTableExists($db, 'items')
            && demoTableExists($db, 'shops')
            && demoTableExists($db, 'shop_inventory')
        ) {
            $categories = [
                ['name' => 'Consumabili', 'description' => 'Oggetti rapidi da usare in partita.', 'sort' => 10],
                ['name' => 'Equipaggiamento', 'description' => 'Oggetti utili per il personaggio.', 'sort' => 20],
                ['name' => 'Materiali', 'description' => 'Risorse per crafting o scambi.', 'sort' => 30],
            ];
            $categoryIds = [];
            foreach ($categories as $cat) {
                $existingCategory = $db->fetchOnePrepared(
                    'SELECT id FROM item_categories WHERE name = ? LIMIT 1',
                    [$cat['name']],
                );
                $catId = (int) demoRowGet($existingCategory, 'id', 0);
                if ($catId <= 0) {
                    $db->executePrepared(
                        'INSERT INTO item_categories (name, description, icon, sort_order, date_created)
                         VALUES (?, ?, ?, ?, NOW())',
                        [$cat['name'], $cat['description'], '/assets/imgs/defaults-images/default-icon.png', (int) $cat['sort']],
                    );
                    $catId = $db->lastInsertId();
                    $tables['item_categories'][] = $catId;
                }
                $categoryIds[$cat['name']] = $catId;
            }

            $rarities = [
                ['code' => 'common', 'name' => 'Comune', 'color' => '#7f8c8d', 'sort' => 10],
                ['code' => 'rare', 'name' => 'Raro', 'color' => '#3498db', 'sort' => 20],
                ['code' => 'epic', 'name' => 'Epico', 'color' => '#9b59b6', 'sort' => 30],
            ];
            $rarityIds = [];
            foreach ($rarities as $rarity) {
                $existingRarity = $db->fetchOnePrepared(
                    'SELECT id FROM item_rarities WHERE code = ? LIMIT 1',
                    [$rarity['code']],
                );
                $rarityId = (int) demoRowGet($existingRarity, 'id', 0);
                if ($rarityId <= 0) {
                    $db->executePrepared(
                        'INSERT INTO item_rarities (code, name, description, color_hex, sort_order, is_active, date_created)
                         VALUES (?, ?, ?, ?, ?, 1, NOW())',
                        [$rarity['code'], $rarity['name'], $loremShort, $rarity['color'], (int) $rarity['sort']],
                    );
                    $rarityId = $db->lastInsertId();
                    $tables['item_rarities'][] = $rarityId;
                }
                $rarityIds[$rarity['code']] = $rarityId;
            }

            $items = [
                ['name' => 'Pozione rigenerante', 'cat' => 'Consumabili', 'rarity' => 'common', 'price' => 45, 'stackable' => 1, 'max_stack' => 30, 'equippable' => 0, 'slot' => null],
                ['name' => 'Kit medico rapido', 'cat' => 'Consumabili', 'rarity' => 'rare', 'price' => 90, 'stackable' => 1, 'max_stack' => 10, 'equippable' => 0, 'slot' => null],
                ['name' => 'Spada d acciaio', 'cat' => 'Equipaggiamento', 'rarity' => 'common', 'price' => 220, 'stackable' => 0, 'max_stack' => 1, 'equippable' => 1, 'slot' => 'weapon'],
                ['name' => 'Giacca rinforzata', 'cat' => 'Equipaggiamento', 'rarity' => 'rare', 'price' => 260, 'stackable' => 0, 'max_stack' => 1, 'equippable' => 1, 'slot' => 'torso'],
                ['name' => 'Anello cerimoniale', 'cat' => 'Equipaggiamento', 'rarity' => 'epic', 'price' => 380, 'stackable' => 0, 'max_stack' => 1, 'equippable' => 1, 'slot' => 'ring'],
                ['name' => 'Erbe officinali', 'cat' => 'Materiali', 'rarity' => 'common', 'price' => 35, 'stackable' => 1, 'max_stack' => 50, 'equippable' => 0, 'slot' => null],
            ];
            $itemIds = [];
            foreach ($items as $idx => $item) {
                $slug = 'demo-item-' . ($idx + 1) . '-' . substr(sha1($item['name'] . $tag), 0, 6);
                $db->executePrepared(
                    'INSERT INTO items
                        (category_id, rarity_id, rarity, name, slug, description, icon, image, price, type, item_kind, is_stackable, stackable, max_stack, usable, consumable, tradable, droppable, destroyable, weight, value, cooldown, metadata_json, is_equippable, equip_slot, date_created, created_at)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $categoryIds[$item['cat']] ?? null,
                        $rarityIds[$item['rarity']] ?? null,
                        $item['rarity'],
                        $item['name'],
                        $slug,
                        $loremLong,
                        '/assets/imgs/defaults-images/default-icon.png',
                        '/assets/imgs/defaults-images/default-location.png',
                        (int) $item['price'],
                        $item['equippable'] === 1 ? 'equip' : 'consumable',
                        $item['equippable'] === 1 ? 'equipment' : 'resource',
                        (int) $item['stackable'],
                        (int) $item['stackable'],
                        (int) $item['max_stack'],
                        $item['equippable'] === 1 ? 0 : 1,
                        $item['equippable'] === 1 ? 0 : 1,
                        $item['equippable'] === 1 ? 2.0 : 0.5,
                        (int) $item['price'],
                        0,
                        json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        (int) $item['equippable'],
                        $item['slot'],
                    ],
                );
                $newItemId = $db->lastInsertId();
                $tables['items'][] = $newItemId;
                $itemIds[] = $newItemId;
            }

            $db->executePrepared(
                'INSERT INTO shops (name, type, location_id, is_active) VALUES (?, ?, NULL, 1)',
                ['Emporio centrale', 'global'],
            );
            $globalShopId = $db->lastInsertId();
            $tables['shops'][] = $globalShopId;

            $localShopId = 0;
            if ($locationId > 0) {
                $db->executePrepared(
                    'INSERT INTO shops (name, type, location_id, is_active) VALUES (?, ?, ?, 1)',
                    ['Bottega di quartiere', 'local', $locationId],
                );
                $localShopId = $db->lastInsertId();
                $tables['shops'][] = $localShopId;
            }

            foreach ($itemIds as $idx => $itemId) {
                $price = 30 + ($idx * 40);
                $db->executePrepared(
                    'INSERT INTO shop_inventory
                        (shop_id, item_id, currency_id, price, stock, per_character_limit, per_day_limit, is_active, is_promo, promo_discount, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())',
                    [$globalShopId, $itemId, $currencyId, $price, 30 + ($idx * 2), 5, 3, ($idx % 3 === 0) ? 1 : 0, ($idx % 3 === 0) ? 10 : 0],
                );
                $tables['shop_inventory'][] = $db->lastInsertId();

                if ($localShopId > 0 && $idx < 3) {
                    $db->executePrepared(
                        'INSERT INTO shop_inventory
                            (shop_id, item_id, currency_id, price, stock, per_character_limit, per_day_limit, is_active, is_promo, promo_discount, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, ?, 1, 0, 0, NOW())',
                        [$localShopId, $itemId, $currencyId, $price + 15, 12, 2, 1],
                    );
                    $tables['shop_inventory'][] = $db->lastInsertId();
                }
            }

            if (demoTableExists($db, 'inventory_items') && !empty($itemIds[0])) {
                foreach ($characterIds as $idx => $characterId) {
                    $db->executePrepared(
                        'INSERT INTO inventory_items
                            (item_id, owner_id, owner_type, quantity, custom_description, metadata_json, created_at, updated_at)
                         VALUES
                            (?, ?, ?, ?, ?, ?, NOW(), NOW())',
                        [$itemIds[0], $characterId, 'player', 2 + $idx, $loremShort, json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    );
                    $tables['inventory_items'][] = $db->lastInsertId();
                }
            }

            if (demoTableExists($db, 'character_item_instances') && !empty($itemIds[2])) {
                foreach ($characterIds as $characterId) {
                    $db->executePrepared(
                        'INSERT INTO character_item_instances
                            (character_id, item_id, is_equipped, slot, durability, meta_json, date_created)
                         VALUES
                            (?, ?, 0, ?, ?, ?, NOW())',
                        [$characterId, $itemIds[2], 'weapon', 100, json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    );
                    $tables['character_item_instances'][] = $db->lastInsertId();
                }
            }

            if (demoTableExists($db, 'shop_purchases') && !empty($itemIds[0])) {
                $db->executePrepared(
                    'INSERT INTO shop_purchases
                        (shop_id, character_id, item_id, currency_id, quantity, total_price, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, NOW())',
                    [$globalShopId, $characterIds[0] ?? 0, $itemIds[0], $currencyId, 2, 60],
                );
                $tables['shop_purchases'][] = $db->lastInsertId();
            }

            if (demoTableExists($db, 'shop_sales') && !empty($itemIds[1])) {
                $db->executePrepared(
                    'INSERT INTO shop_sales
                        (shop_id, character_id, item_id, quantity, unit_price, total_price, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, NOW())',
                    [$globalShopId, $characterIds[0] ?? 0, $itemIds[1], 1, 45, 45],
                );
                $tables['shop_sales'][] = $db->lastInsertId();
            }
        }

        if (
            !empty($itemIds)
            && demoTableExists($db, 'character_item_instances')
            && demoTableExists($db, 'equipment_slots')
            && demoTableExists($db, 'item_equipment_rules')
            && demoTableExists($db, 'character_equipment')
        ) {
            $slotRows = $db->fetchAllPrepared(
                'SELECT id, `key` AS slot_key
                 FROM equipment_slots
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, id ASC',
                [],
            );
            $slotMap = [];
            foreach ($slotRows as $slotRow) {
                $slotId = (int) demoRowGet($slotRow, 'id', 0);
                $slotKey = (string) demoRowGet($slotRow, 'slot_key', '');
                if ($slotId > 0 && $slotKey !== '') {
                    $slotMap[$slotKey] = $slotId;
                }
            }

            $itemToSlotKey = [
                (int) ($itemIds[2] ?? 0) => 'weapon_1',
                (int) ($itemIds[3] ?? 0) => 'armor',
                (int) ($itemIds[4] ?? 0) => 'ring_1',
            ];

            foreach ($itemToSlotKey as $itemId => $slotKey) {
                $slotId = (int) ($slotMap[$slotKey] ?? 0);
                if ($itemId <= 0 || $slotId <= 0) {
                    continue;
                }
                $existingRule = $db->fetchOnePrepared(
                    'SELECT id
                     FROM item_equipment_rules
                     WHERE item_id = ?
                       AND slot_id = ?
                     LIMIT 1',
                    [$itemId, $slotId],
                );
                if ((int) demoRowGet($existingRule, 'id', 0) <= 0) {
                    $db->executePrepared(
                        'INSERT INTO item_equipment_rules
                            (item_id, slot_id, priority, metadata_json, date_created)
                         VALUES
                            (?, ?, 10, ?, NOW())',
                        [$itemId, $slotId, json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    );
                    $tables['item_equipment_rules'][] = $db->lastInsertId();
                }
            }

            foreach ($focusCharacterIds as $focusCharacterId) {
                if ($focusCharacterId <= 0) {
                    continue;
                }
                foreach ($itemToSlotKey as $itemId => $slotKey) {
                    if ($itemId <= 0) {
                        continue;
                    }
                    $slotId = (int) ($slotMap[$slotKey] ?? 0);
                    if ($slotId <= 0) {
                        continue;
                    }

                    $db->executePrepared(
                        'INSERT INTO character_item_instances
                            (character_id, item_id, is_equipped, slot, durability, meta_json, date_created)
                         VALUES
                            (?, ?, 0, ?, ?, ?, NOW())',
                        [
                            $focusCharacterId,
                            $itemId,
                            $slotKey,
                            100,
                            json_encode(['demo' => true, 'slot_key' => $slotKey], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    );
                    $instanceId = $db->lastInsertId();
                    $tables['character_item_instances'][] = $instanceId;

                    $db->executePrepared(
                        'INSERT INTO character_equipment
                            (character_id, slot_id, inventory_item_id, character_item_instance_id, equipped_at, date_created)
                         VALUES
                            (?, ?, NULL, ?, NOW(), NOW())',
                        [$focusCharacterId, $slotId, $instanceId],
                    );
                    $tables['character_equipment'][] = $db->lastInsertId();
                }
            }
        }

        if (
            demoTableExists($db, 'jobs')
            && demoTableExists($db, 'job_levels')
            && demoTableExists($db, 'job_tasks')
            && demoTableExists($db, 'job_task_choices')
            && demoTableExists($db, 'character_jobs')
            && demoTableExists($db, 'character_job_tasks')
            && demoTableExists($db, 'job_logs')
        ) {
            $jobDefinitions = [
                ['name' => 'Messaggero di zona', 'base_pay' => 28, 'daily_tasks' => 2],
                ['name' => 'Archivista cittadino', 'base_pay' => 35, 'daily_tasks' => 2],
                ['name' => 'Guardia di ronda', 'base_pay' => 42, 'daily_tasks' => 3],
            ];
            $jobIds = [];
            foreach ($jobDefinitions as $jobDef) {
                $db->executePrepared(
                    'INSERT INTO jobs
                        (name, description, icon, location_id, min_socialstatus_id, base_pay, daily_tasks, is_active, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
                    [
                        $jobDef['name'],
                        $loremLong,
                        '/assets/imgs/defaults-images/default-icon.png',
                        $locationId > 0 ? $locationId : null,
                        1,
                        (int) $jobDef['base_pay'],
                        (int) $jobDef['daily_tasks'],
                    ],
                );
                $jobId = $db->lastInsertId();
                $jobIds[] = $jobId;
                $tables['jobs'][] = $jobId;

                for ($lvl = 1; $lvl <= 3; $lvl++) {
                    $db->executePrepared(
                        'INSERT INTO job_levels
                            (job_id, level, title, min_points, pay_bonus_percent, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, NOW())',
                        [$jobId, $lvl, 'Livello ' . $lvl, ($lvl - 1) * 100, ($lvl - 1) * 8],
                    );
                    $tables['job_levels'][] = $db->lastInsertId();
                }

                for ($t = 1; $t <= 2; $t++) {
                    $db->executePrepared(
                        'INSERT INTO job_tasks
                            (job_id, title, body, min_level, requires_location_id, is_active, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, 1, NOW())',
                        [
                            $jobId,
                            $jobDef['name'] . ' - Attivita ' . $t,
                            $loremLong,
                            1,
                            $locationId > 0 ? $locationId : null,
                        ],
                    );
                    $taskId = $db->lastInsertId();
                    $tables['job_tasks'][] = $taskId;

                    $db->executePrepared(
                        'INSERT INTO job_task_choices
                            (task_id, choice_code, label, pay, fame, points, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, NOW())',
                        [$taskId, 'on', 'Completamento positivo', 18, 2, 26],
                    );
                    $onChoiceId = $db->lastInsertId();
                    $tables['job_task_choices'][] = $onChoiceId;

                    $db->executePrepared(
                        'INSERT INTO job_task_choices
                            (task_id, choice_code, label, pay, fame, points, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, NOW())',
                        [$taskId, 'off', 'Imprevisto narrativo', 10, 1, 12],
                    );
                    $offChoiceId = $db->lastInsertId();
                    $tables['job_task_choices'][] = $offChoiceId;
                }
            }

            $jobTaskRows = $db->fetchAllPrepared(
                'SELECT id, job_id
                 FROM job_tasks
                 WHERE id IN (' . implode(',', array_fill(0, count($tables['job_tasks']), '?')) . ')
                 ORDER BY id ASC',
                $tables['job_tasks'],
            );
            $taskIdsByJob = [];
            foreach ($jobTaskRows as $taskRow) {
                $jobId = (int) demoRowGet($taskRow, 'job_id', 0);
                $taskId = (int) demoRowGet($taskRow, 'id', 0);
                if ($jobId > 0 && $taskId > 0) {
                    if (!isset($taskIdsByJob[$jobId])) {
                        $taskIdsByJob[$jobId] = [];
                    }
                    $taskIdsByJob[$jobId][] = $taskId;
                }
            }

            foreach ($focusCharacterIds as $idx => $focusCharacterId) {
                if ($focusCharacterId <= 0 || empty($jobIds)) {
                    continue;
                }
                $jobId = (int) $jobIds[$idx % count($jobIds)];
                $activeRow = $db->fetchOnePrepared(
                    'SELECT id
                     FROM character_jobs
                     WHERE character_id = ?
                       AND is_active = 1
                     LIMIT 1',
                    [$focusCharacterId],
                );
                $isActive = empty($activeRow) ? 1 : 0;
                $db->executePrepared(
                    'INSERT INTO character_jobs
                        (character_id, job_id, level, points, is_active, date_assigned, date_updated)
                     VALUES
                        (?, ?, ?, ?, ?, NOW(), NOW())',
                    [$focusCharacterId, $jobId, 1 + ($idx % 2), 60 + ($idx * 25), $isActive],
                );
                $characterJobId = $db->lastInsertId();
                $tables['character_jobs'][] = $characterJobId;

                $jobTaskIds = $taskIdsByJob[$jobId] ?? [];
                foreach ($jobTaskIds as $taskPos => $taskId) {
                    $status = ($taskPos === 0) ? 'completed' : 'pending';
                    $choiceRow = $db->fetchOnePrepared(
                        'SELECT id, pay, fame, points
                         FROM job_task_choices
                         WHERE task_id = ?
                           AND choice_code = ?
                         LIMIT 1',
                        [$taskId, $taskPos === 0 ? 'on' : 'off'],
                    );
                    $choiceId = (int) demoRowGet($choiceRow, 'id', 0);
                    $pay = (int) demoRowGet($choiceRow, 'pay', 0);
                    $fame = (int) demoRowGet($choiceRow, 'fame', 0);
                    $points = (int) demoRowGet($choiceRow, 'points', 0);

                    $db->executePrepared(
                        'INSERT INTO character_job_tasks
                            (character_job_id, task_id, assigned_date, status, choice_id, pay, fame, points, date_completed)
                         VALUES
                            (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?)',
                        [
                            $characterJobId,
                            $taskId,
                            $status,
                            $choiceId > 0 ? $choiceId : null,
                            $pay,
                            $fame,
                            $points,
                            $status === 'completed' ? date('Y-m-d H:i:s') : null,
                        ],
                    );
                    $tables['character_job_tasks'][] = $db->lastInsertId();

                    if ($status === 'completed') {
                        $db->executePrepared(
                            'INSERT INTO job_logs
                                (character_id, job_id, task_id, choice_id, assigned_date, pay, fame, points, date_created)
                             VALUES
                                (?, ?, ?, ?, CURDATE(), ?, ?, ?, NOW())',
                            [$focusCharacterId, $jobId, $taskId, $choiceId > 0 ? $choiceId : 0, $pay, $fame, $points],
                        );
                        $tables['job_logs'][] = $db->lastInsertId();
                    }
                }
            }
        }

        if (
            demoTableExists($db, 'character_attribute_definitions')
            && demoTableExists($db, 'character_attribute_values')
        ) {
            $attrSuffix = substr(sha1($tag), 0, 6);
            $attributeDefs = [
                ['slug' => 'forza-' . $attrSuffix, 'name' => 'Forza', 'group' => 'primary', 'default' => 6.0, 'derived' => 0],
                ['slug' => 'agilita-' . $attrSuffix, 'name' => 'Agilita', 'group' => 'primary', 'default' => 5.0, 'derived' => 0],
                ['slug' => 'carisma-' . $attrSuffix, 'name' => 'Carisma', 'group' => 'secondary', 'default' => 4.0, 'derived' => 0],
                ['slug' => 'resistenza-' . $attrSuffix, 'name' => 'Resistenza', 'group' => 'secondary', 'default' => 0.0, 'derived' => 1],
            ];
            $attributeIds = [];
            foreach ($attributeDefs as $pos => $attrDef) {
                $db->executePrepared(
                    'INSERT INTO character_attribute_definitions
                        (slug, name, description, attribute_group, value_type, position, min_value, max_value, default_value, fallback_value, round_mode, is_active, is_derived, allow_manual_override, visible_in_profile, visible_in_location, maps_to_core_health_max, created_by, updated_by, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 0, 1, 1, 0, ?, ?, NOW())',
                    [
                        $attrDef['slug'],
                        $attrDef['name'],
                        $loremShort,
                        $attrDef['group'],
                        'number',
                        ($pos + 1) * 10,
                        0,
                        100,
                        $attrDef['default'],
                        $attrDef['default'],
                        'round',
                        (int) $attrDef['derived'],
                        $presentationUserId > 0 ? $presentationUserId : null,
                        $presentationUserId > 0 ? $presentationUserId : null,
                    ],
                );
                $attrId = $db->lastInsertId();
                $tables['character_attribute_definitions'][] = $attrId;
                $attributeIds[$attrDef['slug']] = $attrId;
            }

            foreach ($focusCharacterIds as $idx => $focusCharacterId) {
                foreach ($attributeDefs as $aIdx => $attrDef) {
                    $attrId = (int) ($attributeIds[$attrDef['slug']] ?? 0);
                    if ($attrId <= 0) {
                        continue;
                    }
                    $baseValue = (float) ($attrDef['default'] + ($idx + $aIdx));
                    $db->executePrepared(
                        'INSERT INTO character_attribute_values
                            (character_id, attribute_id, base_value, override_value, effective_value, value_source, last_recomputed_at, date_created)
                         VALUES
                            (?, ?, ?, NULL, ?, ?, NOW(), NOW())',
                        [$focusCharacterId, $attrId, $baseValue, $baseValue, $attrDef['derived'] ? 'derived' : 'base'],
                    );
                    $tables['character_attribute_values'][] = $db->lastInsertId();
                }
            }

            if (
                demoTableExists($db, 'character_attribute_rules')
                && demoTableExists($db, 'character_attribute_rule_steps')
            ) {
                $derivedAttrId = (int) ($attributeIds['resistenza-' . $attrSuffix] ?? 0);
                $forzaAttrId = (int) ($attributeIds['forza-' . $attrSuffix] ?? 0);
                if ($derivedAttrId > 0 && $forzaAttrId > 0) {
                    $db->executePrepared(
                        'INSERT INTO character_attribute_rules
                            (attribute_id, is_active, fallback_value, round_mode, date_created)
                         VALUES
                            (?, 1, ?, ?, NOW())',
                        [$derivedAttrId, 0, 'round'],
                    );
                    $ruleId = $db->lastInsertId();
                    $tables['character_attribute_rules'][] = $ruleId;

                    $db->executePrepared(
                        'INSERT INTO character_attribute_rule_steps
                            (rule_id, step_order, operator_code, operand_type, operand_attribute_id, operand_value, date_created)
                         VALUES
                            (?, 1, ?, ?, NULL, ?, NOW())',
                        [$ruleId, 'set', 'value', 5.0],
                    );
                    $tables['character_attribute_rule_steps'][] = $db->lastInsertId();

                    $db->executePrepared(
                        'INSERT INTO character_attribute_rule_steps
                            (rule_id, step_order, operator_code, operand_type, operand_attribute_id, operand_value, date_created)
                         VALUES
                            (?, 2, ?, ?, ?, NULL, NOW())',
                        [$ruleId, 'add', 'attribute', $forzaAttrId],
                    );
                    $tables['character_attribute_rule_steps'][] = $db->lastInsertId();
                }
            }
        }

        if (
            demoTableExists($db, 'lf_abilities_spells_categories')
            && demoTableExists($db, 'lf_abilities_spells_point_categories')
            && demoTableExists($db, 'lf_abilities_spells_abilities')
            && demoTableExists($db, 'lf_abilities_spells_level_rules')
            && demoTableExists($db, 'lf_abilities_spells_effects')
            && demoTableExists($db, 'lf_abilities_spells_requirements')
            && demoTableExists($db, 'lf_abilities_spells_grants')
            && demoTableExists($db, 'lf_abilities_spells_rank_point_rewards')
            && demoTableExists($db, 'lf_abilities_spells_character_points')
            && demoTableExists($db, 'lf_abilities_spells_character_abilities')
            && demoTableExists($db, 'lf_abilities_spells_character_point_logs')
        ) {
            $abSuffix = substr(sha1($tag . '-ab'), 0, 6);
            $db->executePrepared(
                'INSERT INTO lf_abilities_spells_categories
                    (slug, name, description, is_active, sort_order)
                 VALUES
                    (?, ?, ?, 1, ?)',
                ['demo-cat-' . $abSuffix, 'Disciplina Demo', $loremShort, 10],
            );
            $abilityCategoryId = $db->lastInsertId();
            $tables['lf_abilities_spells_categories'][] = $abilityCategoryId;

            $db->executePrepared(
                'INSERT INTO lf_abilities_spells_point_categories
                    (slug, name, description, is_active, sort_order)
                 VALUES
                    (?, ?, ?, 1, ?)',
                ['demo-pt-' . $abSuffix, 'Punti Disciplina', $loremShort, 10],
            );
            $pointCategoryId = $db->lastInsertId();
            $tables['lf_abilities_spells_point_categories'][] = $pointCategoryId;

            $abilityDefs = [
                ['name' => 'Osservazione tattica', 'type' => 'ability', 'requires_approval' => 0],
                ['name' => 'Sigillo protettivo', 'type' => 'spell', 'requires_approval' => 1],
                ['name' => 'Richiamo d urgenza', 'type' => 'technique', 'requires_approval' => 0],
            ];
            $abilityIds = [];
            foreach ($abilityDefs as $idx => $abilityDef) {
                $abilitySlug = 'demo-ab-' . ($idx + 1) . '-' . $abSuffix;
                $db->executePrepared(
                    'INSERT INTO lf_abilities_spells_abilities
                        (name, slug, description, type, category_id, point_category_id, target_type, effect_mode, narrative_state_id, cooldown_seconds, sort_order, is_active, is_public, is_hidden_when_locked, requires_learning, requires_staff_approval, max_level, metadata_json, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 1, 1, 0, 1, ?, ?, ?, NOW())',
                    [
                        $abilityDef['name'],
                        $abilitySlug,
                        $loremLong,
                        $abilityDef['type'],
                        $abilityCategoryId,
                        $pointCategoryId,
                        'self',
                        'none',
                        90 + ($idx * 30),
                        ($idx + 1) * 10,
                        $abilityDef['requires_approval'],
                        3,
                        json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                );
                $abilityId = $db->lastInsertId();
                $abilityIds[] = $abilityId;
                $tables['lf_abilities_spells_abilities'][] = $abilityId;

                for ($lvl = 1; $lvl <= 3; $lvl++) {
                    $db->executePrepared(
                        'INSERT INTO lf_abilities_spells_level_rules
                            (ability_id, level, points_required, min_rank, requires_staff_approval, metadata_json, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, NOW())',
                        [
                            $abilityId,
                            $lvl,
                            2 + $lvl,
                            1,
                            $abilityDef['requires_approval'],
                            json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    );
                    $tables['lf_abilities_spells_level_rules'][] = $db->lastInsertId();
                }

                $db->executePrepared(
                    'INSERT INTO lf_abilities_spells_effects
                        (ability_id, level, effect_type, target_system, target_key, operation, value, activation_policy, policy_when_unavailable, is_active, metadata_json, date_created)
                     VALUES
                        (?, 1, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())',
                    [
                        $abilityId,
                        'modifier',
                        'character_attributes',
                        'forza-' . substr(sha1($tag), 0, 6),
                        'add',
                        1.00 + $idx,
                        'while_ability_usable',
                        'ignore',
                        json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                );
                $tables['lf_abilities_spells_effects'][] = $db->lastInsertId();

                $db->executePrepared(
                    'INSERT INTO lf_abilities_spells_requirements
                        (ability_id, level, requirement_type, requirement_key, operator, required_value, policy_when_unavailable, is_hidden, is_active, metadata_json, date_created)
                     VALUES
                        (?, 1, ?, ?, ?, ?, ?, 0, 1, ?, NOW())',
                    [
                        $abilityId,
                        'attribute',
                        'forza-' . substr(sha1($tag), 0, 6),
                        '>=',
                        '5',
                        'block',
                        json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                );
                $tables['lf_abilities_spells_requirements'][] = $db->lastInsertId();

                $db->executePrepared(
                    'INSERT INTO lf_abilities_spells_grants
                        (ability_id, source_type, source_id, grant_mode, retention_policy, min_rank, max_rank, is_active, priority, metadata_json, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())',
                    [
                        $abilityId,
                        'character',
                        $presentationCharacterId > 0 ? $presentationCharacterId : ($focusCharacterIds[0] ?? 0),
                        'unlock',
                        'keep_when_lost',
                        1,
                        null,
                        100,
                        json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                );
                $tables['lf_abilities_spells_grants'][] = $db->lastInsertId();
            }

            for ($r = 1; $r <= 4; $r++) {
                $db->executePrepared(
                    'INSERT INTO lf_abilities_spells_rank_point_rewards
                        (rank, point_category_id, points, is_active, date_created)
                     VALUES
                        (?, ?, ?, 1, NOW())',
                    [$r, $pointCategoryId, 2 + $r],
                );
                $tables['lf_abilities_spells_rank_point_rewards'][] = $db->lastInsertId();
            }

            foreach ($focusCharacterIds as $idx => $focusCharacterId) {
                $db->executePrepared(
                    'INSERT INTO lf_abilities_spells_character_points
                        (character_id, point_category_id, available_points, spent_points, lifetime_points, date_updated)
                     VALUES
                        (?, ?, ?, ?, ?, NOW())',
                    [$focusCharacterId, $pointCategoryId, 8 + $idx, 3 + $idx, 11 + ($idx * 2)],
                );
                $tables['lf_abilities_spells_character_points'][] = $db->lastInsertId();

                foreach ($abilityIds as $aIdx => $abilityId) {
                    $status = ($aIdx === 1) ? 'pending_approval' : 'learned';
                    $approvalStatus = ($status === 'pending_approval') ? 'pending' : 'approved';
                    $level = ($aIdx === 0) ? 2 : 1;
                    $pendingPoints = ($status === 'pending_approval') ? 2 : 0;
                    $db->executePrepared(
                        'INSERT INTO lf_abilities_spells_character_abilities
                            (character_id, ability_id, status, level, pending_points, spent_points, approval_status, approved_by_user_id, approved_at, suspended_reason, metadata_json, sort_order, is_active, date_created, date_updated)
                         VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 1, NOW(), NOW())',
                        [
                            $focusCharacterId,
                            $abilityId,
                            $status,
                            $level,
                            $pendingPoints,
                            2 + $aIdx,
                            $approvalStatus,
                            $approvalStatus === 'approved' ? ($presentationUserId > 0 ? $presentationUserId : null) : null,
                            $approvalStatus === 'approved' ? date('Y-m-d H:i:s') : null,
                            json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            ($aIdx + 1) * 10,
                        ],
                    );
                    $tables['lf_abilities_spells_character_abilities'][] = $db->lastInsertId();
                }

                $db->executePrepared(
                    'INSERT INTO lf_abilities_spells_character_point_logs
                        (character_id, point_category_id, delta, reason, reference_type, reference_id, created_by_user_id, note, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $focusCharacterId,
                        $pointCategoryId,
                        6,
                        'staff_grant',
                        'demo_seed',
                        null,
                        $presentationUserId > 0 ? $presentationUserId : null,
                        'Credito punti demo per presentazione',
                    ],
                );
                $tables['lf_abilities_spells_character_point_logs'][] = $db->lastInsertId();
            }
        }

        if (
            demoTableExists($db, 'quest_definitions')
            && demoTableExists($db, 'quest_step_definitions')
            && demoTableExists($db, 'quest_instances')
            && demoTableExists($db, 'quest_step_instances')
            && demoTableExists($db, 'quest_reward_assignments')
            && demoTableExists($db, 'quest_progress_logs')
            && demoTableExists($db, 'quest_conditions')
            && demoTableExists($db, 'quest_outcomes')
            && demoTableExists($db, 'quest_event_links')
            && demoTableExists($db, 'quest_closure_reports')
        ) {
            $questSuffix = substr(sha1($tag . '-quests'), 0, 6);
            $questDefs = [
                [
                    'slug' => 'demo-quest-bozza-' . $questSuffix,
                    'title' => 'Quest Bozza - Concept',
                    'status' => 'draft',
                    'visibility' => 'staff_only',
                    'details' => 'low',
                ],
                [
                    'slug' => 'demo-quest-attiva-' . $questSuffix,
                    'title' => 'Quest Attiva - Indagine in corso',
                    'status' => 'published',
                    'visibility' => 'public',
                    'details' => 'medium',
                ],
                [
                    'slug' => 'demo-quest-completa-' . $questSuffix,
                    'title' => 'Quest Completata - Rapporto finale',
                    'status' => 'published',
                    'visibility' => 'public',
                    'details' => 'high',
                ],
            ];

            $questDefinitionIds = [];
            foreach ($questDefs as $idx => $questDef) {
                $db->executePrepared(
                    'INSERT INTO quest_definitions
                        (slug, title, summary, description, quest_type, intensity_level, intensity_visibility, visibility, scope_type, scope_id, availability_type, status, sort_order, meta_json, created_by, updated_by, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $questDef['slug'],
                        $questDef['title'],
                        $loremShort,
                        $loremLong,
                        'personal',
                        $idx === 0 ? 'SOFT' : ($idx === 1 ? 'STANDARD' : 'HIGH'),
                        'visible',
                        $questDef['visibility'],
                        'character',
                        $presentationCharacterId > 0 ? $presentationCharacterId : null,
                        'automatic_unlock',
                        $questDef['status'],
                        ($idx + 1) * 10,
                        json_encode(['detail' => $questDef['details'], 'demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $presentationCharacterId > 0 ? $presentationCharacterId : null,
                        $presentationCharacterId > 0 ? $presentationCharacterId : null,
                    ],
                );
                $questDefinitionId = $db->lastInsertId();
                $questDefinitionIds[] = $questDefinitionId;
                $tables['quest_definitions'][] = $questDefinitionId;
            }

            $stepsByDefinition = [];
            foreach ($questDefinitionIds as $idx => $questDefinitionId) {
                $stepCount = ($idx === 1) ? 3 : 2;
                for ($s = 1; $s <= $stepCount; $s++) {
                    $stepKey = 'step_' . $s;
                    $db->executePrepared(
                        'INSERT INTO quest_step_definitions
                            (quest_definition_id, step_key, title, description, step_type, order_index, is_optional, completion_mode, branch_on_success, branch_on_failure, visibility_mode, is_active, meta_json, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, 1, ?, NOW())',
                        [
                            $questDefinitionId,
                            $stepKey,
                            'Fase ' . $s,
                            $loremLong,
                            'narrative_action',
                            $s,
                            0,
                            ($idx === 2 && $s === 2) ? 'staff_confirm' : 'automatic',
                            'visible',
                            json_encode(['demo' => true, 'step' => $s], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    );
                    $stepId = $db->lastInsertId();
                    $tables['quest_step_definitions'][] = $stepId;
                    if (!isset($stepsByDefinition[$questDefinitionId])) {
                        $stepsByDefinition[$questDefinitionId] = [];
                    }
                    $stepsByDefinition[$questDefinitionId][] = $stepId;
                }
            }

            $activeQuestDefinitionId = (int) ($questDefinitionIds[1] ?? 0);
            $completedQuestDefinitionId = (int) ($questDefinitionIds[2] ?? 0);

            if ($activeQuestDefinitionId > 0 && $presentationCharacterId > 0) {
                $db->executePrepared(
                    'INSERT INTO quest_instances
                        (quest_definition_id, assignee_type, assignee_id, current_status, intensity_level, current_branch, started_at, completed_at, failed_at, expires_at, source_type, source_id, assigned_by, notes, last_activity_at, meta_json, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL, DATE_ADD(NOW(), INTERVAL 7 DAY), ?, NULL, ?, ?, NOW(), ?, NOW())',
                    [
                        $activeQuestDefinitionId,
                        'character',
                        $presentationCharacterId,
                        'active',
                        'STANDARD',
                        'manual',
                        $presentationCharacterId,
                        $loremShort,
                        json_encode(['demo' => true, 'state' => 'active'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                );
                $activeInstanceId = $db->lastInsertId();
                $tables['quest_instances'][] = $activeInstanceId;

                $activeSteps = $stepsByDefinition[$activeQuestDefinitionId] ?? [];
                foreach ($activeSteps as $sIdx => $stepDefinitionId) {
                    $status = $sIdx === 0 ? 'completed' : ($sIdx === 1 ? 'active' : 'locked');
                    $db->executePrepared(
                        'INSERT INTO quest_step_instances
                            (quest_instance_id, quest_step_definition_id, progress_status, progress_value, started_at, completed_at, failed_at, updated_at, internal_notes, meta_json, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, NULL, NOW(), ?, ?, NOW())',
                        [
                            $activeInstanceId,
                            $stepDefinitionId,
                            $status,
                            $status === 'completed' ? 100 : ($status === 'active' ? 45 : null),
                            $status !== 'locked' ? date('Y-m-d H:i:s', strtotime('-1 day')) : null,
                            $status === 'completed' ? date('Y-m-d H:i:s', strtotime('-12 hours')) : null,
                            $loremShort,
                            json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    );
                    $stepInstanceId = $db->lastInsertId();
                    $tables['quest_step_instances'][] = $stepInstanceId;

                    $db->executePrepared(
                        'INSERT INTO quest_progress_logs
                            (quest_instance_id, step_instance_id, log_type, source_type, source_id, payload, created_by, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, ?, NOW())',
                        [
                            $activeInstanceId,
                            $stepInstanceId,
                            'step_status_changed',
                            'manual',
                            null,
                            json_encode(['status' => $status], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            $presentationCharacterId,
                        ],
                    );
                    $tables['quest_progress_logs'][] = $db->lastInsertId();
                }
            } else {
                $activeInstanceId = 0;
            }

            if ($completedQuestDefinitionId > 0 && $presentationCharacterId > 0) {
                $db->executePrepared(
                    'INSERT INTO quest_instances
                        (quest_definition_id, assignee_type, assignee_id, current_status, intensity_level, current_branch, started_at, completed_at, failed_at, expires_at, source_type, source_id, assigned_by, notes, last_activity_at, meta_json, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, NULL, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL, ?, NULL, ?, ?, NOW(), ?, NOW())',
                    [
                        $completedQuestDefinitionId,
                        'character',
                        $presentationCharacterId,
                        'completed',
                        'HIGH',
                        'manual',
                        $presentationCharacterId,
                        $loremShort,
                        json_encode(['demo' => true, 'state' => 'completed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                );
                $completedInstanceId = $db->lastInsertId();
                $tables['quest_instances'][] = $completedInstanceId;

                $completedSteps = $stepsByDefinition[$completedQuestDefinitionId] ?? [];
                foreach ($completedSteps as $stepDefinitionId) {
                    $db->executePrepared(
                        'INSERT INTO quest_step_instances
                            (quest_instance_id, quest_step_definition_id, progress_status, progress_value, started_at, completed_at, failed_at, updated_at, internal_notes, meta_json, date_created)
                         VALUES
                            (?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NOW(), ?, ?, NOW())',
                        [
                            $completedInstanceId,
                            $stepDefinitionId,
                            'completed',
                            100,
                            $loremShort,
                            json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ],
                    );
                    $stepInstanceId = $db->lastInsertId();
                    $tables['quest_step_instances'][] = $stepInstanceId;
                }

                $db->executePrepared(
                    'INSERT INTO quest_reward_assignments
                        (quest_instance_id, recipient_type, recipient_id, reward_type, reward_reference_id, reward_value, assigned_by, assigned_at, visibility, notes, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())',
                    [
                        $completedInstanceId,
                        'character',
                        $presentationCharacterId,
                        'experience',
                        null,
                        250,
                        $presentationCharacterId,
                        'public',
                        $loremShort,
                    ],
                );
                $tables['quest_reward_assignments'][] = $db->lastInsertId();

                if (!empty($itemIds[1])) {
                    $db->executePrepared(
                        'INSERT INTO quest_reward_assignments
                            (quest_instance_id, recipient_type, recipient_id, reward_type, reward_reference_id, reward_value, assigned_by, assigned_at, visibility, notes, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())',
                        [
                            $completedInstanceId,
                            'character',
                            $presentationCharacterId,
                            'item',
                            (int) $itemIds[1],
                            1,
                            $presentationCharacterId,
                            'public',
                            $loremShort,
                        ],
                    );
                    $tables['quest_reward_assignments'][] = $db->lastInsertId();
                }

                $db->executePrepared(
                    'INSERT INTO quest_progress_logs
                        (quest_instance_id, step_instance_id, log_type, source_type, source_id, payload, created_by, date_created)
                     VALUES
                        (?, NULL, ?, ?, NULL, ?, ?, NOW())',
                    [
                        $completedInstanceId,
                        'instance_status_changed',
                        'manual',
                        json_encode(['to' => 'completed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $presentationCharacterId,
                    ],
                );
                $tables['quest_progress_logs'][] = $db->lastInsertId();

                $db->executePrepared(
                    'INSERT INTO quest_closure_reports
                        (quest_instance_id, closure_type, summary_public, summary_private, outcome_label, closed_by, closed_at, player_visible, staff_notes, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, NOW(), 1, ?, NOW())',
                    [
                        $completedInstanceId,
                        'success',
                        $loremShort,
                        $loremLong,
                        'Obiettivo completato',
                        $presentationCharacterId,
                        $loremLong,
                    ],
                );
                $tables['quest_closure_reports'][] = $db->lastInsertId();
            } else {
                $completedInstanceId = 0;
            }

            foreach ($questDefinitionIds as $defIdx => $questDefinitionId) {
                $stepList = $stepsByDefinition[$questDefinitionId] ?? [];
                foreach ($stepList as $stepDefinitionId) {
                    $db->executePrepared(
                        'INSERT INTO quest_conditions
                            (quest_definition_id, quest_step_definition_id, condition_type, operator, condition_payload, evaluation_mode, is_active, date_created)
                         VALUES
                            (?, ?, ?, ?, ?, ?, 1, NOW())',
                        [
                            $questDefinitionId,
                            $stepDefinitionId,
                            'manual_check',
                            'eq',
                            json_encode(['field' => 'ok', 'value' => '1'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'all_required',
                        ],
                    );
                    $tables['quest_conditions'][] = $db->lastInsertId();
                }

                $db->executePrepared(
                    'INSERT INTO quest_outcomes
                        (quest_definition_id, trigger_type, outcome_type, outcome_payload, visibility, requires_staff_confirmation, sort_order, is_active, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, 1, NOW())',
                    [
                        $questDefinitionId,
                        'quest_completed',
                        'log_entry',
                        json_encode(['message' => $loremShort], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'public',
                        $defIdx === 2 ? 1 : 0,
                        10,
                    ],
                );
                $tables['quest_outcomes'][] = $db->lastInsertId();
            }

            $linkedNarrativeEventId = (int) ($tables['narrative_events'][0] ?? 0);
            $linkedSystemEventId = (int) ($tables['system_events'][0] ?? 0);
            if ($activeQuestDefinitionId > 0 && $activeInstanceId > 0) {
                $db->executePrepared(
                    'INSERT INTO quest_event_links
                        (quest_definition_id, quest_instance_id, narrative_event_id, system_event_id, link_type, meta_json, created_by, date_created)
                     VALUES
                        (?, ?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $activeQuestDefinitionId,
                        $activeInstanceId,
                        $linkedNarrativeEventId > 0 ? $linkedNarrativeEventId : null,
                        $linkedSystemEventId > 0 ? $linkedSystemEventId : null,
                        'contextualized_by',
                        json_encode(['demo' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $presentationCharacterId > 0 ? $presentationCharacterId : null,
                    ],
                );
                $tables['quest_event_links'][] = $db->lastInsertId();
            }
        }

        if (demoTableExists($db, 'sys_logs')) {
            for ($i = 0; $i < 8; $i++) {
                $db->executePrepared(
                    'INSERT INTO sys_logs (author, url, area, module, action, data, date_created)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())',
                    [
                        $userIds[$i % count($userIds)] ?? null,
                        '/game',
                        'demo',
                        'seed',
                        'attivita_demo_' . ($i + 1),
                        json_encode(['message' => $loremShort], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                );
                $tables['sys_logs'][] = $db->lastInsertId();
            }
        }

        $db->query('COMMIT');
    } catch (Throwable $e) {
        $db->query('ROLLBACK');
        throw $e;
    }

    return [
        'userIds' => $userIds,
        'characterIds' => $characterIds,
        'tables' => $tables,
        'emailDomain' => $emailDomain,
    ];
}

function demoDeleteIds(DbAdapterInterface $db, string $table, array $ids): int
{
    if (!demoTableExists($db, $table)) {
        return 0;
    }

    $ids = array_values(array_filter(array_map('intval', $ids), static function (int $value): bool {
        return $value > 0;
    }));
    if ($ids === []) {
        return 0;
    }

    $deleted = 0;
    $chunks = array_chunk($ids, 200);
    foreach ($chunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $sql = 'DELETE FROM ' . $table . ' WHERE id IN (' . $placeholders . ')';
        $db->executePrepared($sql, $chunk);
        $deleted += count($chunk);
    }

    return $deleted;
}

/**
 * @return array<string,int>
 */
function demoPurge(DbAdapterInterface $db, array $payload): array
{
    $tables = isset($payload['tables']) && is_array($payload['tables']) ? $payload['tables'] : [];
    $order = [
        'notifications',
        'messages',
        'messages_threads',
        'locations_messages',
        'location_access_logs',
        'character_events',
        'quest_reward_assignments',
        'quest_closure_reports',
        'quest_progress_logs',
        'quest_event_links',
        'quest_step_instances',
        'quest_instances',
        'quest_conditions',
        'quest_outcomes',
        'quest_step_definitions',
        'quest_definitions',
        'character_job_tasks',
        'job_logs',
        'character_jobs',
        'job_task_choices',
        'job_tasks',
        'job_levels',
        'jobs',
        'lf_abilities_spells_character_abilities',
        'lf_abilities_spells_character_point_logs',
        'lf_abilities_spells_character_points',
        'lf_abilities_spells_rank_point_rewards',
        'lf_abilities_spells_grants',
        'lf_abilities_spells_requirements',
        'lf_abilities_spells_effects',
        'lf_abilities_spells_level_rules',
        'lf_abilities_spells_abilities',
        'lf_abilities_spells_point_categories',
        'lf_abilities_spells_categories',
        'character_attribute_values',
        'character_attribute_rule_steps',
        'character_attribute_rules',
        'character_attribute_definitions',
        'character_equipment',
        'item_equipment_rules',
        'system_event_effects',
        'system_event_participations',
        'system_events',
        'narrative_events',
        'shop_purchases',
        'shop_sales',
        'shop_inventory',
        'character_item_instances',
        'inventory_items',
        'items',
        'item_rarities',
        'item_categories',
        'shops',
        'guild_events',
        'guild_logs',
        'guild_members',
        'guild_roles',
        'guilds',
        'forum_threads',
        'sys_logs',
        'characters',
        'users',
    ];

    $summary = [];
    $db->query('START TRANSACTION');
    try {
        foreach ($order as $table) {
            $ids = isset($tables[$table]) && is_array($tables[$table]) ? $tables[$table] : [];
            $summary[$table] = demoDeleteIds($db, $table, $ids);
        }
        $db->query('COMMIT');
    } catch (Throwable $e) {
        $db->query('ROLLBACK');
        throw $e;
    }

    return $summary;
}

function demoSummaryLine(array $summary): string
{
    if ($summary === []) {
        return 'nessun record';
    }

    $parts = [];
    foreach ($summary as $table => $count) {
        $parts[] = $table . '=' . (int) $count;
    }
    return implode(', ', $parts);
}

try {
    $args = demoParseArgs($argv);
    $action = $args['action'];
    $tag = $args['tag'];
    $users = $args['users'];
    $replace = $args['replace'];
    $password = $args['password'];

    $db = DbAdapterFactory::createFromConfig();
    if (!$db instanceof DbAdapterInterface) {
        throw new RuntimeException('DbAdapter non disponibile.');
    }

    if ($action === 'status') {
        $artifact = demoLoadArtifact($db, $tag);
        if ($artifact === null) {
            demoInfo('Nessun dataset demo trovato per tag "' . $tag . '".');
            exit(0);
        }
        $summary = isset($artifact['summary']) && is_array($artifact['summary']) ? $artifact['summary'] : [];
        demoOk('Dataset demo presente per tag "' . $tag . '".');
        demoInfo('Creato il: ' . (string) ($artifact['created_at'] ?? '-'));
        demoInfo('Utenti demo: ' . (string) ($artifact['users'] ?? '-'));
        demoInfo('Dominio email: ' . (string) ($artifact['email_domain'] ?? '-'));
        demoInfo('Conteggi: ' . demoSummaryLine($summary));
        exit(0);
    }

    if ($action === 'purge') {
        $artifact = demoLoadArtifact($db, $tag);
        if ($artifact === null) {
            demoInfo('Nessun dataset demo da rimuovere per tag "' . $tag . '".');
            exit(0);
        }

        $summary = demoPurge($db, $artifact);
        demoDeleteArtifact($db, $tag);
        demoOk('Dataset demo rimosso per tag "' . $tag . '".');
        demoInfo('Eliminati: ' . demoSummaryLine($summary));
        exit(0);
    }

    if ($action !== 'seed') {
        throw new InvalidArgumentException('Azione non valida. Usa: seed | purge | status');
    }

    $existing = demoLoadArtifact($db, $tag);
    if ($existing !== null) {
        if (!$replace) {
            throw new RuntimeException(
                'Esiste gia un dataset per tag "' . $tag . '". Usa --replace=1 oppure fai prima purge.'
            );
        }
        demoInfo('Dataset esistente trovato: eseguo purge automatico (replace=1).');
        $summary = demoPurge($db, $existing);
        demoDeleteArtifact($db, $tag);
        demoInfo('Purge pre-seed completato: ' . demoSummaryLine($summary));
    }

    $seedResult = demoSeed($db, $tag, $users, $password);
    $tables = $seedResult['tables'];
    $summary = [];
    foreach ($tables as $table => $ids) {
        $summary[$table] = is_array($ids) ? count($ids) : 0;
    }

    $payload = [
        'tag' => $tag,
        'created_at' => date('c'),
        'users' => count($seedResult['userIds']),
        'characters' => count($seedResult['characterIds']),
        'email_domain' => $seedResult['emailDomain'],
        'default_password' => $password,
        'summary' => $summary,
        'tables' => $tables,
    ];
    demoSaveArtifact($db, $tag, $payload);

    demoOk('Seed demo completato per tag "' . $tag . '".');
    demoInfo('Utenti creati: ' . (string) $payload['users']);
    demoInfo('Password demo: ' . $password);
    demoInfo('Dominio email demo: ' . (string) $payload['email_domain']);
    demoInfo('Conteggi: ' . demoSummaryLine($summary));
    demoInfo('Per rimuovere tutto: php scripts/php/demo-investor-data.php purge --tag=' . $tag);
    exit(0);
} catch (Throwable $e) {
    demoFail($e->getMessage());
    exit(1);
}
