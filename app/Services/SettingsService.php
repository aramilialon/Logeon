<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\DbAdapterFactory;
use Core\Database\DbAdapterInterface;
use Core\FrontendAssetVersion;
use Core\Http\AppError;
use Core\SessionStore;

class SettingsService
{
    /** @var DbAdapterInterface */
    private $db;

    public function __construct(DbAdapterInterface $db = null)
    {
        $this->db = $db ?: DbAdapterFactory::createFromConfig();
    }

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

    private function upsertSysSetting(string $key, string $value): void
    {
        $this->execPrepared(
            'INSERT INTO sys_settings (`key`, `value`)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            [$key, $value],
        );
    }

    private function upsertSysConfig(string $key, string $value, string $type): void
    {
        $this->execPrepared(
            'INSERT INTO sys_configs (`key`, `value`, `type`)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`)',
            [$key, $value, $type],
        );
    }

    private function failValidation($message, string $errorCode = ''): void
    {
        $message = (string) $message;
        if ($errorCode === '') {
            $errorCode = $this->resolveValidationErrorCode($message);
        }
        throw AppError::validation($message, [], $errorCode);
    }

    private function resolveValidationErrorCode(string $message): string
    {
        $map = [
            'Dati non validi' => 'payload_invalid',
            'Valore non valido' => 'upload_max_mb_invalid',
            'Valore troppo grande' => 'upload_max_mb_too_large',
            'Valore concurrency non valido' => 'upload_concurrency_invalid',
            'Valore concurrency troppo grande' => 'upload_concurrency_too_large',
            'Valore avatar non valido' => 'upload_avatar_mb_invalid',
            'Valore avatar troppo grande' => 'upload_avatar_mb_too_large',
            'Valore inattivita non valido' => 'availability_idle_minutes_invalid',
            'Valore inattivita troppo grande' => 'availability_idle_minutes_too_large',
            'Valore scadenza inviti non valido' => 'invite_expiry_invalid',
            'Valore limite inviti non valido' => 'invite_max_active_invalid',
            'Valore limite login non valido' => 'rate_auth_signin_limit_invalid',
            'Valore finestra login non valido' => 'rate_auth_signin_window_invalid',
            'Valore limite reset non valido' => 'rate_auth_reset_limit_invalid',
            'Valore finestra reset non valido' => 'rate_auth_reset_window_invalid',
            'Valore limite conferma reset non valido' => 'rate_auth_reset_confirm_limit_invalid',
            'Valore finestra conferma reset non valido' => 'rate_auth_reset_confirm_window_invalid',
            'Valore limite DM non valido' => 'rate_dm_send_limit_invalid',
            'Valore finestra DM non valido' => 'rate_dm_send_window_invalid',
            'Valore limite chat location non valido' => 'rate_location_chat_limit_invalid',
            'Valore finestra chat location non valido' => 'rate_location_chat_window_invalid',
            'Valore limite sussurri non valido' => 'rate_location_whisper_limit_invalid',
            'Valore finestra sussurri non valido' => 'rate_location_whisper_window_invalid',
            'Valore ripristino posizione al login non valido' => 'presence_resume_signin_invalid',
            'Google Client ID non valido' => 'auth_google_client_id_invalid',
            'Google Client Secret non valido' => 'auth_google_client_secret_invalid',
            'Google Redirect URI non valido' => 'auth_google_redirect_uri_invalid',
        ];

        return $map[$message] ?? 'validation_error';
    }

    /**
     * @return array<string, mixed>
     */
    private function appConfig(): array
    {
        if (!defined('APP')) {
            return [];
        }

        return constant('APP');
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeConfig(): array
    {
        if (!defined('CONFIG')) {
            return [];
        }

        return constant('CONFIG');
    }

    public function getUploadDataset(): array
    {
        $appConfig = $this->appConfig();
        $appOauthRaw = $appConfig['oauth_google'] ?? [];
        $appOauth = is_array($appOauthRaw) ? $appOauthRaw : [];

        $appOauthEnabledRaw = $appOauth['enabled'] ?? false;
        $appOauthEnabled = ($appOauthEnabledRaw === true || (string) $appOauthEnabledRaw === '1') ? 1 : 0;
        $appOauthClientId = trim((string) ($appOauth['client_id'] ?? ''));
        $appOauthClientSecret = trim((string) ($appOauth['client_secret'] ?? ''));
        $appOauthRedirectUri = trim((string) ($appOauth['redirect_uri'] ?? ''));
        $docsViewModeDefaults = $this->docsViewModeDefaults();

        $dataset = [
            'upload_max_mb' => 5,
            'upload_max_concurrency' => 3,
            'upload_max_avatar_mb' => 2,
            'upload_max_audio_mb' => 10,
            'multi_character_enabled' => 0,
            'multi_character_max_per_user' => 1,
            'availability_idle_minutes' => 20,
            'onlines_auto_toast' => 0,
            'presence_resume_last_position_on_signin' => 0,
            'location_invite_expiry_hours' => 48,
            'location_invite_max_active' => 10,
            'rate_auth_signin_limit' => 10,
            'rate_auth_signin_window_seconds' => 300,
            'rate_auth_reset_limit' => 5,
            'rate_auth_reset_window_seconds' => 900,
            'rate_auth_reset_confirm_limit' => 10,
            'rate_auth_reset_confirm_window_seconds' => 900,
            'rate_dm_send_limit' => 8,
            'rate_dm_send_window_seconds' => 30,
            'rate_location_chat_limit' => 12,
            'rate_location_chat_window_seconds' => 15,
            'rate_location_whisper_limit' => 6,
            'rate_location_whisper_window_seconds' => 20,
            'auth_google_enabled' => $appOauthEnabled,
            'auth_google_client_id' => $appOauthClientId,
            'auth_google_client_secret' => $appOauthClientSecret,
            'auth_google_redirect_uri' => $appOauthRedirectUri,
        ];
        $dataset = array_merge($dataset, $docsViewModeDefaults);

        $settingsRows = $this->fetchPrepared(
            "SELECT `key`, `value` FROM sys_settings WHERE `key` IN ('upload_max_mb', 'upload_max_concurrency', 'upload_max_avatar_mb', 'upload_max_audio_mb')",
        );
        if (!empty($settingsRows)) {
            foreach ($settingsRows as $row) {
                if (!isset($dataset[$row->key])) {
                    continue;
                }
                $dataset[$row->key] = (int) $row->value;
            }
        }

        $configRows = $this->fetchPrepared(
            "SELECT `key`, `value` FROM sys_configs WHERE `key` IN (
                'availability_idle_minutes',
                'onlines_auto_toast',
                'presence_resume_last_position_on_signin',
                'presence_restore_last_position_on_signin',
                'location_invite_expiry_hours',
                'location_invite_max_active',
                'rate_auth_signin_limit',
                'rate_auth_signin_window_seconds',
                'rate_auth_reset_limit',
                'rate_auth_reset_window_seconds',
                'rate_auth_reset_confirm_limit',
                'rate_auth_reset_confirm_window_seconds',
                'rate_dm_send_limit',
                'rate_dm_send_window_seconds',
                'rate_location_chat_limit',
                'rate_location_chat_window_seconds',
                'rate_location_whisper_limit',
                'rate_location_whisper_window_seconds',
                'auth_google_enabled',
                'auth_google_client_id',
                'auth_google_client_secret',
                'auth_google_redirect_uri',
                'multi_character_enabled',
                'multi_character_max_per_user'
            )",
        );

        if (!empty($configRows)) {
            $hasPresenceCanonical = false;
            foreach ($configRows as $row) {
                if ($row->key === 'presence_resume_last_position_on_signin') {
                    $dataset['presence_resume_last_position_on_signin'] = ((int) $row->value === 1) ? 1 : 0;
                    $hasPresenceCanonical = true;
                    continue;
                }
                if ($row->key === 'presence_restore_last_position_on_signin') {
                    if (!$hasPresenceCanonical) {
                        $dataset['presence_resume_last_position_on_signin'] = ((int) $row->value === 1) ? 1 : 0;
                    }
                    continue;
                }
                if (!isset($dataset[$row->key])) {
                    continue;
                }
                if (
                    $row->key === 'auth_google_client_id'
                    || $row->key === 'auth_google_client_secret'
                    || $row->key === 'auth_google_redirect_uri'
                ) {
                    $dataset[$row->key] = (string) $row->value;
                    continue;
                }
                $dataset[$row->key] = (int) $row->value;
            }
        }
        $dataset = $this->loadDocsViewModesFromConfigs($dataset);

        return $this->normalizeUploadDataset($dataset);
    }

    private function normalizeUploadDataset(array $dataset): array
    {
        if ((int) $dataset['upload_max_mb'] <= 0) {
            $dataset['upload_max_mb'] = 5;
        }
        if ((int) $dataset['upload_max_concurrency'] <= 0) {
            $dataset['upload_max_concurrency'] = 3;
        }
        if ((int) $dataset['upload_max_avatar_mb'] <= 0) {
            $dataset['upload_max_avatar_mb'] = 2;
        }
        if ((int) $dataset['upload_max_audio_mb'] <= 0) {
            $dataset['upload_max_audio_mb'] = 10;
        }
        if ((int) $dataset['availability_idle_minutes'] <= 0) {
            $dataset['availability_idle_minutes'] = 20;
        }

        $dataset['onlines_auto_toast'] = ((int) $dataset['onlines_auto_toast'] === 1) ? 1 : 0;
        $dataset['presence_resume_last_position_on_signin'] = ((int) $dataset['presence_resume_last_position_on_signin'] === 1) ? 1 : 0;
        $dataset['multi_character_enabled'] = ((int) ($dataset['multi_character_enabled'] ?? 0) === 1) ? 1 : 0;
        $dataset['multi_character_max_per_user'] = (int) ($dataset['multi_character_max_per_user'] ?? 1);
        if ($dataset['multi_character_max_per_user'] < 1) {
            $dataset['multi_character_max_per_user'] = 1;
        }
        if ($dataset['multi_character_max_per_user'] > 10) {
            $dataset['multi_character_max_per_user'] = 10;
        }
        $dataset['auth_google_enabled'] = ((int) $dataset['auth_google_enabled'] === 1) ? 1 : 0;
        $dataset['auth_google_client_id'] = trim((string) ($dataset['auth_google_client_id'] ?? ''));
        $dataset['auth_google_client_secret'] = trim((string) ($dataset['auth_google_client_secret'] ?? ''));
        $dataset['auth_google_redirect_uri'] = trim((string) ($dataset['auth_google_redirect_uri'] ?? ''));

        $dataset = $this->normalizeDocsViewModes($dataset);

        if ((int) $dataset['location_invite_expiry_hours'] < 0) {
            $dataset['location_invite_expiry_hours'] = 48;
        }
        if ((int) $dataset['location_invite_max_active'] < 0) {
            $dataset['location_invite_max_active'] = 10;
        }
        if ((int) $dataset['rate_auth_signin_limit'] < 1) {
            $dataset['rate_auth_signin_limit'] = 10;
        }
        if ((int) $dataset['rate_auth_signin_window_seconds'] < 10) {
            $dataset['rate_auth_signin_window_seconds'] = 300;
        }
        if ((int) $dataset['rate_auth_reset_limit'] < 1) {
            $dataset['rate_auth_reset_limit'] = 5;
        }
        if ((int) $dataset['rate_auth_reset_window_seconds'] < 30) {
            $dataset['rate_auth_reset_window_seconds'] = 900;
        }
        if ((int) $dataset['rate_auth_reset_confirm_limit'] < 1) {
            $dataset['rate_auth_reset_confirm_limit'] = 10;
        }
        if ((int) $dataset['rate_auth_reset_confirm_window_seconds'] < 30) {
            $dataset['rate_auth_reset_confirm_window_seconds'] = 900;
        }
        if ((int) $dataset['rate_dm_send_limit'] < 1) {
            $dataset['rate_dm_send_limit'] = 8;
        }
        if ((int) $dataset['rate_dm_send_window_seconds'] < 1) {
            $dataset['rate_dm_send_window_seconds'] = 30;
        }
        if ((int) $dataset['rate_location_chat_limit'] < 1) {
            $dataset['rate_location_chat_limit'] = 12;
        }
        if ((int) $dataset['rate_location_chat_window_seconds'] < 1) {
            $dataset['rate_location_chat_window_seconds'] = 15;
        }
        if ((int) $dataset['rate_location_whisper_limit'] < 1) {
            $dataset['rate_location_whisper_limit'] = 6;
        }
        if ((int) $dataset['rate_location_whisper_window_seconds'] < 1) {
            $dataset['rate_location_whisper_window_seconds'] = 20;
        }

        return $dataset;
    }

    public function updateUploadSettings($payload): array
    {
        if (!is_array($payload)) {
            $this->failValidation('Dati non validi');
        }
        $data = $payload;

        $value = isset($data['upload_max_mb']) ? (int) $data['upload_max_mb'] : 0;
        if ($value <= 0) {
            $this->failValidation('Valore non valido');
        }
        if ($value > 1024) {
            $this->failValidation('Valore troppo grande');
        }

        $concurrency = isset($data['upload_max_concurrency']) ? (int) $data['upload_max_concurrency'] : 0;
        if ($concurrency <= 0) {
            $this->failValidation('Valore concurrency non valido');
        }
        if ($concurrency > 10) {
            $this->failValidation('Valore concurrency troppo grande');
        }

        $avatar = isset($data['upload_max_avatar_mb']) ? (int) $data['upload_max_avatar_mb'] : 0;
        if ($avatar <= 0) {
            $this->failValidation('Valore avatar non valido');
        }
        if ($avatar > 1024) {
            $this->failValidation('Valore avatar troppo grande');
        }
        $idleMinutes = isset($data['availability_idle_minutes']) ? (int) $data['availability_idle_minutes'] : 0;
        if ($idleMinutes <= 0) {
            $this->failValidation('Valore inattivita non valido');
        }
        if ($idleMinutes > 120) {
            $this->failValidation('Valore inattivita troppo grande');
        }

        $autoToast = isset($data['onlines_auto_toast']) ? (int) $data['onlines_auto_toast'] : 0;
        $autoToast = ($autoToast === 1) ? 1 : 0;

        $inviteExpiry = isset($data['location_invite_expiry_hours']) ? (int) $data['location_invite_expiry_hours'] : 48;
        if ($inviteExpiry < 0 || $inviteExpiry > 720) {
            $this->failValidation('Valore scadenza inviti non valido');
        }

        $inviteMaxActive = isset($data['location_invite_max_active']) ? (int) $data['location_invite_max_active'] : 10;
        if ($inviteMaxActive < 0 || $inviteMaxActive > 100) {
            $this->failValidation('Valore limite inviti non valido');
        }
        $presenceResumeOnSignin = isset($data['presence_resume_last_position_on_signin']) ? (int) $data['presence_resume_last_position_on_signin'] : 0;
        if ($presenceResumeOnSignin !== 0 && $presenceResumeOnSignin !== 1) {
            $this->failValidation('Valore ripristino posizione al login non valido');
        }

        $rateAuthSigninLimit = isset($data['rate_auth_signin_limit']) ? (int) $data['rate_auth_signin_limit'] : 10;
        if ($rateAuthSigninLimit < 1 || $rateAuthSigninLimit > 100) {
            $this->failValidation('Valore limite login non valido');
        }

        $rateAuthSigninWindow = isset($data['rate_auth_signin_window_seconds']) ? (int) $data['rate_auth_signin_window_seconds'] : 300;
        if ($rateAuthSigninWindow < 10 || $rateAuthSigninWindow > 3600) {
            $this->failValidation('Valore finestra login non valido');
        }

        $rateAuthResetLimit = isset($data['rate_auth_reset_limit']) ? (int) $data['rate_auth_reset_limit'] : 5;
        if ($rateAuthResetLimit < 1 || $rateAuthResetLimit > 50) {
            $this->failValidation('Valore limite reset non valido');
        }

        $rateAuthResetWindow = isset($data['rate_auth_reset_window_seconds']) ? (int) $data['rate_auth_reset_window_seconds'] : 900;
        if ($rateAuthResetWindow < 30 || $rateAuthResetWindow > 86400) {
            $this->failValidation('Valore finestra reset non valido');
        }

        $rateAuthResetConfirmLimit = isset($data['rate_auth_reset_confirm_limit']) ? (int) $data['rate_auth_reset_confirm_limit'] : 10;
        if ($rateAuthResetConfirmLimit < 1 || $rateAuthResetConfirmLimit > 100) {
            $this->failValidation('Valore limite conferma reset non valido');
        }

        $rateAuthResetConfirmWindow = isset($data['rate_auth_reset_confirm_window_seconds']) ? (int) $data['rate_auth_reset_confirm_window_seconds'] : 900;
        if ($rateAuthResetConfirmWindow < 30 || $rateAuthResetConfirmWindow > 86400) {
            $this->failValidation('Valore finestra conferma reset non valido');
        }

        $rateDmSendLimit = isset($data['rate_dm_send_limit']) ? (int) $data['rate_dm_send_limit'] : 8;
        if ($rateDmSendLimit < 1 || $rateDmSendLimit > 60) {
            $this->failValidation('Valore limite DM non valido');
        }

        $rateDmSendWindow = isset($data['rate_dm_send_window_seconds']) ? (int) $data['rate_dm_send_window_seconds'] : 30;
        if ($rateDmSendWindow < 1 || $rateDmSendWindow > 600) {
            $this->failValidation('Valore finestra DM non valido');
        }

        $rateLocationChatLimit = isset($data['rate_location_chat_limit']) ? (int) $data['rate_location_chat_limit'] : 12;
        if ($rateLocationChatLimit < 1 || $rateLocationChatLimit > 100) {
            $this->failValidation('Valore limite chat location non valido');
        }

        $rateLocationChatWindow = isset($data['rate_location_chat_window_seconds']) ? (int) $data['rate_location_chat_window_seconds'] : 15;
        if ($rateLocationChatWindow < 1 || $rateLocationChatWindow > 300) {
            $this->failValidation('Valore finestra chat location non valido');
        }

        $rateLocationWhisperLimit = isset($data['rate_location_whisper_limit']) ? (int) $data['rate_location_whisper_limit'] : 6;
        if ($rateLocationWhisperLimit < 1 || $rateLocationWhisperLimit > 60) {
            $this->failValidation('Valore limite sussurri non valido');
        }

        $rateLocationWhisperWindow = isset($data['rate_location_whisper_window_seconds']) ? (int) $data['rate_location_whisper_window_seconds'] : 20;
        if ($rateLocationWhisperWindow < 1 || $rateLocationWhisperWindow > 300) {
            $this->failValidation('Valore finestra sussurri non valido');
        }

        $multiCharacterEnabled = isset($data['multi_character_enabled']) ? (int) $data['multi_character_enabled'] : 0;
        $multiCharacterEnabled = ($multiCharacterEnabled === 1) ? 1 : 0;
        $multiCharacterMaxPerUser = isset($data['multi_character_max_per_user']) ? (int) $data['multi_character_max_per_user'] : 1;
        if ($multiCharacterMaxPerUser < 1 || $multiCharacterMaxPerUser > 10) {
            $this->failValidation('Valore non valido', 'multi_character_max_per_user_invalid');
        }

        $this->upsertSysSetting('upload_max_mb', (string) $value);
        $this->upsertSysSetting('upload_max_concurrency', (string) $concurrency);
        $this->upsertSysSetting('upload_max_avatar_mb', (string) $avatar);

        $this->upsertSysConfig('availability_idle_minutes', (string) $idleMinutes, 'number');
        $this->upsertSysConfig('onlines_auto_toast', (string) $autoToast, 'number');
        $this->upsertSysConfig('location_invite_expiry_hours', (string) $inviteExpiry, 'number');
        $this->upsertSysConfig('location_invite_max_active', (string) $inviteMaxActive, 'number');
        $this->upsertSysConfig('presence_resume_last_position_on_signin', (string) $presenceResumeOnSignin, 'number');
        $this->upsertSysConfig('presence_restore_last_position_on_signin', (string) $presenceResumeOnSignin, 'number');
        $this->upsertSysConfig('rate_auth_signin_limit', (string) $rateAuthSigninLimit, 'number');
        $this->upsertSysConfig('rate_auth_signin_window_seconds', (string) $rateAuthSigninWindow, 'number');
        $this->upsertSysConfig('rate_auth_reset_limit', (string) $rateAuthResetLimit, 'number');
        $this->upsertSysConfig('rate_auth_reset_window_seconds', (string) $rateAuthResetWindow, 'number');
        $this->upsertSysConfig('rate_auth_reset_confirm_limit', (string) $rateAuthResetConfirmLimit, 'number');
        $this->upsertSysConfig('rate_auth_reset_confirm_window_seconds', (string) $rateAuthResetConfirmWindow, 'number');
        $this->upsertSysConfig('rate_dm_send_limit', (string) $rateDmSendLimit, 'number');
        $this->upsertSysConfig('rate_dm_send_window_seconds', (string) $rateDmSendWindow, 'number');
        $this->upsertSysConfig('rate_location_chat_limit', (string) $rateLocationChatLimit, 'number');
        $this->upsertSysConfig('rate_location_chat_window_seconds', (string) $rateLocationChatWindow, 'number');
        $this->upsertSysConfig('rate_location_whisper_limit', (string) $rateLocationWhisperLimit, 'number');
        $this->upsertSysConfig('rate_location_whisper_window_seconds', (string) $rateLocationWhisperWindow, 'number');

        $dataset = [
            'upload_max_mb' => $value,
            'upload_max_concurrency' => $concurrency,
            'upload_max_avatar_mb' => $avatar,
            'availability_idle_minutes' => $idleMinutes,
            'onlines_auto_toast' => $autoToast,
            'presence_resume_last_position_on_signin' => $presenceResumeOnSignin,
            'location_invite_expiry_hours' => $inviteExpiry,
            'location_invite_max_active' => $inviteMaxActive,
            'rate_auth_signin_limit' => $rateAuthSigninLimit,
            'rate_auth_signin_window_seconds' => $rateAuthSigninWindow,
            'rate_auth_reset_limit' => $rateAuthResetLimit,
            'rate_auth_reset_window_seconds' => $rateAuthResetWindow,
            'rate_auth_reset_confirm_limit' => $rateAuthResetConfirmLimit,
            'rate_auth_reset_confirm_window_seconds' => $rateAuthResetConfirmWindow,
            'rate_dm_send_limit' => $rateDmSendLimit,
            'rate_dm_send_window_seconds' => $rateDmSendWindow,
            'rate_location_chat_limit' => $rateLocationChatLimit,
            'rate_location_chat_window_seconds' => $rateLocationChatWindow,
            'rate_location_whisper_limit' => $rateLocationWhisperLimit,
            'rate_location_whisper_window_seconds' => $rateLocationWhisperWindow,
        ];

        $this->applySessionConfig($dataset);

        return $dataset;
    }

    public function applySessionConfig(array $dataset): void
    {
        SessionStore::set('config_availability_idle_minutes', (int) $dataset['availability_idle_minutes']);
        SessionStore::set('config_onlines_auto_toast', (int) $dataset['onlines_auto_toast']);
        SessionStore::set('config_presence_resume_last_position_on_signin', (int) $dataset['presence_resume_last_position_on_signin']);
        SessionStore::set('config_location_invite_expiry_hours', (int) $dataset['location_invite_expiry_hours']);
        SessionStore::set('config_location_invite_max_active', (int) $dataset['location_invite_max_active']);
        SessionStore::set('config_rate_auth_signin_limit', (int) $dataset['rate_auth_signin_limit']);
        SessionStore::set('config_rate_auth_signin_window_seconds', (int) $dataset['rate_auth_signin_window_seconds']);
        SessionStore::set('config_rate_auth_reset_limit', (int) $dataset['rate_auth_reset_limit']);
        SessionStore::set('config_rate_auth_reset_window_seconds', (int) $dataset['rate_auth_reset_window_seconds']);
        SessionStore::set('config_rate_auth_reset_confirm_limit', (int) $dataset['rate_auth_reset_confirm_limit']);
        SessionStore::set('config_rate_auth_reset_confirm_window_seconds', (int) $dataset['rate_auth_reset_confirm_window_seconds']);
        SessionStore::set('config_rate_dm_send_limit', (int) $dataset['rate_dm_send_limit']);
        SessionStore::set('config_rate_dm_send_window_seconds', (int) $dataset['rate_dm_send_window_seconds']);
        SessionStore::set('config_rate_location_chat_limit', (int) $dataset['rate_location_chat_limit']);
        SessionStore::set('config_rate_location_chat_window_seconds', (int) $dataset['rate_location_chat_window_seconds']);
        SessionStore::set('config_rate_location_whisper_limit', (int) $dataset['rate_location_whisper_limit']);
        SessionStore::set('config_rate_location_whisper_window_seconds', (int) $dataset['rate_location_whisper_window_seconds']);
        SessionStore::set('config_multi_character_enabled', (int) ($dataset['multi_character_enabled'] ?? 0));
        SessionStore::set('config_multi_character_max_per_user', (int) ($dataset['multi_character_max_per_user'] ?? 1));
        if (isset($dataset['location_chat_max_chars'])) {
            SessionStore::set('config_location_chat_max_chars', (int) $dataset['location_chat_max_chars']);
        }
        if (isset($dataset['default_dice_faces'])) {
            SessionStore::set('config_default_dice_faces', (int) $dataset['default_dice_faces']);
        }
    }

    // ─── Docs View Modes ─────────────────────────────────────────────────────

    /**
     * @return array<string, string>
     */
    private function docsViewModeDefaults(): array
    {
        $defaults = [
            'storyboard_view_mode' => 'navigation',
            'rules_view_mode' => 'navigation',
            'how_to_play_view_mode' => 'navigation',
        ];

        $extra = \Core\Hooks::filter('settings.docs_view_modes.defaults', []);
        if (!is_array($extra)) {
            $extra = [];
        }

        foreach ($extra as $key => $value) {
            $name = strtolower(trim((string) $key));
            if ($name === '' || !preg_match('/^[a-z0-9_]+_view_mode$/', $name)) {
                continue;
            }
            $mode = strtolower(trim((string) $value));
            $defaults[$name] = in_array($mode, ['navigation', 'monolithic'], true) ? $mode : 'navigation';
        }

        return $defaults;
    }

    /**
     * @param array<string, mixed> $dataset
     * @return array<string, mixed>
     */
    private function normalizeDocsViewModes(array $dataset): array
    {
        $allowed = ['navigation', 'monolithic'];
        foreach ($this->docsViewModeDefaults() as $key => $fallback) {
            $value = strtolower(trim((string) ($dataset[$key] ?? '')));
            $dataset[$key] = in_array($value, $allowed, true) ? $value : $fallback;
        }

        return $dataset;
    }

    /**
     * @param array<string, mixed> $dataset
     * @return array<string, mixed>
     */
    private function loadDocsViewModesFromConfigs(array $dataset): array
    {
        $defaults = $this->docsViewModeDefaults();
        $keys = array_keys($defaults);
        if ($keys === []) {
            return $dataset;
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $rows = $this->fetchPrepared(
            "SELECT `key`, `value` FROM sys_configs WHERE `key` IN ($placeholders)",
            $keys,
        );

        foreach ($rows as $row) {
            $key = (string) ($row->key ?? '');
            if (!array_key_exists($key, $defaults)) {
                continue;
            }
            $value = strtolower(trim((string) ($row->value ?? '')));
            if (in_array($value, ['navigation', 'monolithic'], true)) {
                $dataset[$key] = $value;
            }
        }

        foreach ($defaults as $key => $fallback) {
            if (!isset($dataset[$key])) {
                $dataset[$key] = $fallback;
            }
        }

        return $this->normalizeDocsViewModes($dataset);
    }

    /**
     * @return array<string, string>
     */
    public function getDocsViewModes(): array
    {
        $dataset = $this->loadDocsViewModesFromConfigs([]);
        $normalized = [];
        foreach ($this->docsViewModeDefaults() as $key => $_default) {
            $normalized[$key] = (string) ($dataset[$key] ?? $_default);
        }

        return $normalized;
    }

    // ─── Admin Settings Page ─────────────────────────────────────────────────

    private function getConfigInt(string $key, int $fallback): int
    {
        try {
            $row = $this->firstPrepared(
                'SELECT value FROM sys_configs WHERE `key` = ? LIMIT 1',
                [$key],
            );
            if (!empty($row) && isset($row->value) && is_numeric((string) $row->value)) {
                return (int) $row->value;
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return $fallback;
    }

    private function getSettingInt(string $key, int $fallback): int
    {
        try {
            $row = $this->firstPrepared(
                'SELECT value FROM sys_settings WHERE `key` = ? LIMIT 1',
                [$key],
            );
            if (!empty($row) && isset($row->value) && is_numeric((string) $row->value)) {
                return (int) $row->value;
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return $fallback;
    }

    private function getConfigString(string $key, string $fallback): string
    {
        try {
            $row = $this->firstPrepared(
                'SELECT value FROM sys_configs WHERE `key` = ? LIMIT 1',
                [$key],
            );
            if (!empty($row) && isset($row->value)) {
                return trim((string) $row->value);
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return $fallback;
    }

    /**
     * @return array<string, mixed>
     */
    private function getAppPwaConfig(): array
    {
        $appConfig = $this->appConfig();
        $raw = $appConfig['pwa'] ?? [];

        return is_array($raw) ? $raw : [];
    }

    private function normalizeFlag($value): int
    {
        if ($value === true) {
            return 1;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value === 1) ? 1 : 0;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    private function normalizePwaText($value, int $maxLength): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/', ' ', $text) ?: '';
        if ($text === '') {
            return '';
        }

        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
        }

        return $text;
    }

    private function normalizePwaDescription($value, int $maxLength): string
    {
        return $this->normalizePwaText(strip_tags((string) $value), $maxLength);
    }

    private function normalizePwaPath($value, string $default = '/'): string
    {
        $path = trim((string) $value);
        if ($path === '') {
            return $default;
        }

        return '/' . ltrim($path, '/');
    }

    private function normalizeOptionalPwaAssetPath($value): string
    {
        $path = trim((string) $value);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^(?:https?:)?//#i', $path) === 1) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }

    private function isValidPwaColor(string $value): bool
    {
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1;
    }

    private function isValidPwaPath(string $value): bool
    {
        return preg_match('/^\/[^\s]*$/', $value) === 1;
    }

    private function isValidPwaAssetPath(string $value, bool $allowEmpty = true): bool
    {
        if ($value === '') {
            return $allowEmpty;
        }

        return preg_match('#^(?:https?:)?//#i', $value) === 1
            || preg_match('/^\/[^\s]*$/', $value) === 1;
    }

    public function getAdminSettingsDataset(): array
    {
        $base = $this->getUploadDataset();
        $appConfig = $this->appConfig();
        $pwaConfig = $this->getAppPwaConfig();
        $runtimeConfig = $this->runtimeConfig();
        $inventoryConfigRaw = $runtimeConfig['inventory'] ?? [];
        $inventoryConfig = is_array($inventoryConfigRaw) ? $inventoryConfigRaw : [];

        // Legge da sys_settings con fallback a config
        $inventoryCapacityFallback = isset($inventoryConfig['capacity_max'])
            ? (int) $inventoryConfig['capacity_max'] : 30;
        $inventoryStackFallback = isset($inventoryConfig['stack_max'])
            ? (int) $inventoryConfig['stack_max'] : 50;
        $chatHistoryFallback = isset($runtimeConfig['location_chat_history_hours'])
            ? (int) $runtimeConfig['location_chat_history_hours'] : 3;
        $whisperRetentionFallback = isset($runtimeConfig['location_whisper_retention_hours'])
            ? (int) $runtimeConfig['location_whisper_retention_hours'] : 24;

        $inventoryCapacity = $this->getSettingInt('inventory_capacity_max', $inventoryCapacityFallback);
        $inventoryStack = $this->getSettingInt('inventory_stack_max', $inventoryStackFallback);
        $chatHistory = $this->getSettingInt('location_chat_history_hours', $chatHistoryFallback);
        $whisperRetention = $this->getSettingInt('location_whisper_retention_hours', $whisperRetentionFallback);

        $base['inventory_capacity_max'] = ($inventoryCapacity >= 1) ? $inventoryCapacity : $inventoryCapacityFallback;
        $base['inventory_stack_max'] = ($inventoryStack >= 1) ? $inventoryStack : $inventoryStackFallback;
        $base['location_chat_history_hours'] = ($chatHistory >= 1 && $chatHistory <= 24) ? $chatHistory : $chatHistoryFallback;
        $base['location_whisper_retention_hours'] = ($whisperRetention >= 1 && $whisperRetention <= 168) ? $whisperRetention : $whisperRetentionFallback;
        $base['location_chat_max_chars'] = $this->getConfigInt('location_chat_max_chars', 0);
        $base['default_dice_faces'] = $this->getConfigInt('default_dice_faces', 20);

        $base['narrative_delegation_enabled'] = $this->getConfigInt('narrative_delegation_enabled', 0);
        $base['narrative_delegation_level'] = $this->getConfigInt('narrative_delegation_level', 0);

        // SMTP
        $base['smtp_enabled'] = $this->getConfigInt('mail_enabled', $this->getConfigInt('smtp_enabled', 0));
        $base['smtp_host'] = $this->getConfigString('mail_smtp_host', $this->getConfigString('smtp_host', ''));
        $base['smtp_port'] = $this->getConfigInt('mail_smtp_port', $this->getConfigInt('smtp_port', 587));
        $base['smtp_encryption'] = $this->getConfigString('mail_smtp_encryption', $this->getConfigString('smtp_encryption', 'tls'));
        $base['smtp_username'] = $this->getConfigString('mail_smtp_username', $this->getConfigString('smtp_username', ''));
        $base['smtp_password'] = $this->getConfigString('mail_smtp_password', $this->getConfigString('smtp_password', '')) !== '' ? '********' : '';
        $base['smtp_from_email'] = $this->getConfigString('mail_from_email', $this->getConfigString('smtp_from_email', (string) ($appConfig['support_email'] ?? '')));
        $base['smtp_from_name'] = $this->getConfigString('mail_from_name', $this->getConfigString('smtp_from_name', (string) ($appConfig['support_name'] ?? '')));
        $base['mail_smtp_timeout'] = $this->getConfigInt('mail_smtp_timeout', 30);
        $base['mail_batch_size'] = $this->getConfigInt('mail_batch_size', 10);
        $base['mail_rate_per_minute'] = $this->getConfigInt('mail_rate_per_minute', 60);
        $base['mail_retry_attempts'] = $this->getConfigInt('mail_retry_attempts', 3);

        // Account
        $base['character_delete_days'] = $this->getConfigInt('character_delete_days', 10);
        $base['pwa_enabled'] = $this->getConfigInt('pwa_enabled', $this->normalizeFlag($pwaConfig['enabled'] ?? false));
        $base['pwa_name'] = $this->getConfigString(
            'pwa_name',
            $this->normalizePwaText($pwaConfig['name'] ?? ($appConfig['name'] ?? 'Logeon'), 120),
        );
        $base['pwa_short_name'] = $this->getConfigString(
            'pwa_short_name',
            $this->normalizePwaText($pwaConfig['short_name'] ?? $base['pwa_name'], 60),
        );
        $base['pwa_description'] = $this->getConfigString(
            'pwa_description',
            $this->normalizePwaDescription($pwaConfig['description'] ?? ($appConfig['description'] ?? ''), 255),
        );
        $base['pwa_start_path'] = $this->getConfigString(
            'pwa_start_path',
            $this->normalizePwaPath($pwaConfig['start_path'] ?? '/', '/'),
        );
        $base['pwa_scope'] = $this->getConfigString(
            'pwa_scope',
            $this->normalizePwaPath($pwaConfig['scope'] ?? '/', '/'),
        );
        $base['pwa_display'] = $this->getConfigString(
            'pwa_display',
            $this->normalizePwaText($pwaConfig['display'] ?? 'standalone', 32),
        );
        $base['pwa_orientation'] = $this->getConfigString(
            'pwa_orientation',
            $this->normalizePwaText($pwaConfig['orientation'] ?? 'portrait', 32),
        );
        $base['pwa_theme_color'] = strtolower($this->getConfigString(
            'pwa_theme_color',
            $this->normalizePwaText($pwaConfig['theme_color'] ?? '#0d6efd', 7),
        ));
        $base['pwa_background_color'] = strtolower($this->getConfigString(
            'pwa_background_color',
            $this->normalizePwaText($pwaConfig['background_color'] ?? '#ffffff', 7),
        ));
        $base['pwa_icon_path'] = $this->getConfigString(
            'pwa_icon_path',
            $this->normalizeOptionalPwaAssetPath($pwaConfig['icon_path'] ?? ($appConfig['brand_logo_icon'] ?? '/favicon.ico')),
        );
        $base['pwa_icon_192_path'] = $this->getConfigString(
            'pwa_icon_192_path',
            $this->normalizeOptionalPwaAssetPath($pwaConfig['icon_192_path'] ?? ''),
        );
        $base['pwa_icon_512_path'] = $this->getConfigString(
            'pwa_icon_512_path',
            $this->normalizeOptionalPwaAssetPath($pwaConfig['icon_512_path'] ?? ''),
        );
        $base['pwa_icon_maskable_path'] = $this->getConfigString(
            'pwa_icon_maskable_path',
            $this->normalizeOptionalPwaAssetPath($pwaConfig['icon_maskable_path'] ?? ''),
        );
        $base['pwa_cache_enabled'] = $this->getConfigInt('pwa_cache_enabled', $this->normalizeFlag($pwaConfig['cache_enabled'] ?? true));
        $base['pwa_cache_version'] = FrontendAssetVersion::resolve($appConfig);

        return $base;
    }

    public function updateAdminSettings($payload): array
    {
        if (!is_array($payload)) {
            $this->failValidation('Dati non validi');
        }

        // Valida e aggiorna i settings esistenti
        $dataset = $this->updateUploadSettings($payload);

        // Inventario
        $capacityMax = isset($payload['inventory_capacity_max']) ? (int) $payload['inventory_capacity_max'] : 0;
        if ($capacityMax < 1) {
            $this->failValidation('Capacità inventario non valida', 'inventory_capacity_max_invalid');
        }
        if ($capacityMax > 9999) {
            $this->failValidation('Capacità inventario troppo grande', 'inventory_capacity_max_too_large');
        }

        $stackMax = isset($payload['inventory_stack_max']) ? (int) $payload['inventory_stack_max'] : 0;
        if ($stackMax < 1) {
            $this->failValidation('Stack massimo non valido', 'inventory_stack_max_invalid');
        }
        if ($stackMax > 9999) {
            $this->failValidation('Stack massimo troppo grande', 'inventory_stack_max_too_large');
        }

        // Chat & Messaggi
        $chatHistoryHours = isset($payload['location_chat_history_hours']) ? (int) $payload['location_chat_history_hours'] : 0;
        if ($chatHistoryHours < 1 || $chatHistoryHours > 24) {
            $this->failValidation('Ore storico chat non valide', 'location_chat_history_hours_invalid');
        }

        $whisperRetentionHours = isset($payload['location_whisper_retention_hours']) ? (int) $payload['location_whisper_retention_hours'] : 0;
        if ($whisperRetentionHours < 1 || $whisperRetentionHours > 168) {
            $this->failValidation('Ore retention sussurri non valide', 'location_whisper_retention_hours_invalid');
        }

        $chatMaxChars = isset($payload['location_chat_max_chars']) ? (int) $payload['location_chat_max_chars'] : 0;
        if ($chatMaxChars < 0 || $chatMaxChars > 10000) {
            $this->failValidation('Limite caratteri chat non valido', 'location_chat_max_chars_invalid');
        }

        $defaultDiceFaces = isset($payload['default_dice_faces']) ? (int) $payload['default_dice_faces'] : 20;
        if ($defaultDiceFaces < 2 || $defaultDiceFaces > 1000) {
            $this->failValidation('Facce dado default non valide', 'default_dice_faces_invalid');
        }

        $this->upsertSysSetting('inventory_capacity_max', (string) $capacityMax);
        $this->upsertSysSetting('inventory_stack_max', (string) $stackMax);
        $this->upsertSysSetting('location_chat_history_hours', (string) $chatHistoryHours);
        $this->upsertSysSetting('location_whisper_retention_hours', (string) $whisperRetentionHours);
        $this->upsertSysConfig('location_chat_max_chars', (string) $chatMaxChars, 'number');
        $this->upsertSysConfig('default_dice_faces', (string) $defaultDiceFaces, 'number');

        $dataset['inventory_capacity_max'] = $capacityMax;
        $dataset['inventory_stack_max'] = $stackMax;
        $dataset['location_chat_history_hours'] = $chatHistoryHours;
        $dataset['location_whisper_retention_hours'] = $whisperRetentionHours;
        $dataset['location_chat_max_chars'] = $chatMaxChars;
        $dataset['default_dice_faces'] = $defaultDiceFaces;

        // Multi-personaggio
        $multiCharacterEnabled = isset($payload['multi_character_enabled']) ? (int) $payload['multi_character_enabled'] : 0;
        $multiCharacterEnabled = ($multiCharacterEnabled === 1) ? 1 : 0;
        $multiCharacterMaxPerUser = isset($payload['multi_character_max_per_user']) ? (int) $payload['multi_character_max_per_user'] : 1;
        if ($multiCharacterMaxPerUser < 1 || $multiCharacterMaxPerUser > 10) {
            $multiCharacterMaxPerUser = 1;
        }

        // OAuth Google
        $googleEnabled = isset($payload['auth_google_enabled']) ? (int) $payload['auth_google_enabled'] : 0;
        $googleEnabled = ($googleEnabled === 1) ? 1 : 0;

        $googleClientId = trim((string) ($payload['auth_google_client_id'] ?? ''));
        $googleClientSecret = trim((string) ($payload['auth_google_client_secret'] ?? ''));
        $googleRedirectUri = trim((string) ($payload['auth_google_redirect_uri'] ?? ''));

        if ($googleEnabled === 1 && $googleClientId === '') {
            $this->failValidation('Google Client ID non valido');
        }
        if ($googleEnabled === 1 && $googleClientSecret === '') {
            $this->failValidation('Google Client Secret non valido');
        }
        if ($googleRedirectUri !== '' && filter_var($googleRedirectUri, FILTER_VALIDATE_URL) === false) {
            $this->failValidation('Google Redirect URI non valido');
        }
        if (strlen($googleClientId) > 1024) {
            $this->failValidation('Google Client ID non valido');
        }
        if (strlen($googleClientSecret) > 2048) {
            $this->failValidation('Google Client Secret non valido');
        }
        if (strlen($googleRedirectUri) > 2048) {
            $this->failValidation('Google Redirect URI non valido');
        }

        $this->upsertSysConfig('auth_google_enabled', (string) $googleEnabled, 'number');
        $this->upsertSysConfig('auth_google_client_id', $googleClientId, 'string');
        $this->upsertSysConfig('auth_google_client_secret', $googleClientSecret, 'string');
        $this->upsertSysConfig('auth_google_redirect_uri', $googleRedirectUri, 'string');
        $this->upsertSysConfig('multi_character_enabled', (string) $multiCharacterEnabled, 'number');
        $this->upsertSysConfig('multi_character_max_per_user', (string) $multiCharacterMaxPerUser, 'number');

        $dataset['auth_google_enabled'] = $googleEnabled;
        $dataset['auth_google_client_id'] = $googleClientId;
        $dataset['auth_google_client_secret'] = $googleClientSecret;
        $dataset['auth_google_redirect_uri'] = $googleRedirectUri;
        $dataset['multi_character_enabled'] = $multiCharacterEnabled;
        $dataset['multi_character_max_per_user'] = $multiCharacterMaxPerUser;

        $pwaEnabled = isset($payload['pwa_enabled']) ? (int) $payload['pwa_enabled'] : 0;
        $pwaEnabled = ($pwaEnabled === 1) ? 1 : 0;
        $pwaName = $this->normalizePwaText($payload['pwa_name'] ?? '', 120);
        $pwaShortName = $this->normalizePwaText($payload['pwa_short_name'] ?? '', 60);
        $pwaDescription = $this->normalizePwaDescription($payload['pwa_description'] ?? '', 255);
        $pwaStartPath = $this->normalizePwaPath($payload['pwa_start_path'] ?? '/', '/');
        $pwaScope = $this->normalizePwaPath($payload['pwa_scope'] ?? '/', '/');
        $pwaDisplay = strtolower($this->normalizePwaText($payload['pwa_display'] ?? 'standalone', 32));
        $pwaOrientation = strtolower($this->normalizePwaText($payload['pwa_orientation'] ?? 'portrait', 32));
        $pwaThemeColor = strtolower($this->normalizePwaText($payload['pwa_theme_color'] ?? '#0d6efd', 7));
        $pwaBackgroundColor = strtolower($this->normalizePwaText($payload['pwa_background_color'] ?? '#ffffff', 7));
        $pwaIconPath = $this->normalizeOptionalPwaAssetPath($payload['pwa_icon_path'] ?? '');
        $pwaIcon192Path = $this->normalizeOptionalPwaAssetPath($payload['pwa_icon_192_path'] ?? '');
        $pwaIcon512Path = $this->normalizeOptionalPwaAssetPath($payload['pwa_icon_512_path'] ?? '');
        $pwaIconMaskablePath = $this->normalizeOptionalPwaAssetPath($payload['pwa_icon_maskable_path'] ?? '');
        $pwaCacheEnabled = isset($payload['pwa_cache_enabled']) ? (int) $payload['pwa_cache_enabled'] : 0;
        $pwaCacheEnabled = ($pwaCacheEnabled === 1) ? 1 : 0;

        if (!$this->isValidPwaPath($pwaStartPath)) {
            $this->failValidation('Start path PWA non valido', 'pwa_start_path_invalid');
        }
        if (!$this->isValidPwaPath($pwaScope)) {
            $this->failValidation('Scope PWA non valido', 'pwa_scope_invalid');
        }
        if (!in_array($pwaDisplay, ['fullscreen', 'standalone', 'minimal-ui', 'browser'], true)) {
            $this->failValidation('Display PWA non valido', 'pwa_display_invalid');
        }
        if (!in_array($pwaOrientation, ['any', 'natural', 'landscape', 'landscape-primary', 'landscape-secondary', 'portrait', 'portrait-primary', 'portrait-secondary'], true)) {
            $this->failValidation('Orientamento PWA non valido', 'pwa_orientation_invalid');
        }
        if (!$this->isValidPwaColor($pwaThemeColor)) {
            $this->failValidation('Theme color PWA non valido', 'pwa_theme_color_invalid');
        }
        if (!$this->isValidPwaColor($pwaBackgroundColor)) {
            $this->failValidation('Background color PWA non valido', 'pwa_background_color_invalid');
        }
        if (!$this->isValidPwaAssetPath($pwaIconPath)) {
            $this->failValidation('Icona PWA principale non valida', 'pwa_icon_path_invalid');
        }
        if (!$this->isValidPwaAssetPath($pwaIcon192Path)) {
            $this->failValidation('Icona PWA 192 non valida', 'pwa_icon_192_path_invalid');
        }
        if (!$this->isValidPwaAssetPath($pwaIcon512Path)) {
            $this->failValidation('Icona PWA 512 non valida', 'pwa_icon_512_path_invalid');
        }
        if (!$this->isValidPwaAssetPath($pwaIconMaskablePath)) {
            $this->failValidation('Icona PWA maskable non valida', 'pwa_icon_maskable_path_invalid');
        }
        $this->upsertSysConfig('pwa_enabled', (string) $pwaEnabled, 'number');
        $this->upsertSysConfig('pwa_name', $pwaName, 'string');
        $this->upsertSysConfig('pwa_short_name', $pwaShortName, 'string');
        $this->upsertSysConfig('pwa_description', $pwaDescription, 'string');
        $this->upsertSysConfig('pwa_start_path', $pwaStartPath, 'string');
        $this->upsertSysConfig('pwa_scope', $pwaScope, 'string');
        $this->upsertSysConfig('pwa_display', $pwaDisplay, 'string');
        $this->upsertSysConfig('pwa_orientation', $pwaOrientation, 'string');
        $this->upsertSysConfig('pwa_theme_color', $pwaThemeColor, 'string');
        $this->upsertSysConfig('pwa_background_color', $pwaBackgroundColor, 'string');
        $this->upsertSysConfig('pwa_icon_path', $pwaIconPath, 'string');
        $this->upsertSysConfig('pwa_icon_192_path', $pwaIcon192Path, 'string');
        $this->upsertSysConfig('pwa_icon_512_path', $pwaIcon512Path, 'string');
        $this->upsertSysConfig('pwa_icon_maskable_path', $pwaIconMaskablePath, 'string');
        $this->upsertSysConfig('pwa_cache_enabled', (string) $pwaCacheEnabled, 'number');

        $dataset['pwa_enabled'] = $pwaEnabled;
        $dataset['pwa_name'] = $pwaName;
        $dataset['pwa_short_name'] = $pwaShortName;
        $dataset['pwa_description'] = $pwaDescription;
        $dataset['pwa_start_path'] = $pwaStartPath;
        $dataset['pwa_scope'] = $pwaScope;
        $dataset['pwa_display'] = $pwaDisplay;
        $dataset['pwa_orientation'] = $pwaOrientation;
        $dataset['pwa_theme_color'] = $pwaThemeColor;
        $dataset['pwa_background_color'] = $pwaBackgroundColor;
        $dataset['pwa_icon_path'] = $pwaIconPath;
        $dataset['pwa_icon_192_path'] = $pwaIcon192Path;
        $dataset['pwa_icon_512_path'] = $pwaIcon512Path;
        $dataset['pwa_icon_maskable_path'] = $pwaIconMaskablePath;
        $dataset['pwa_cache_enabled'] = $pwaCacheEnabled;
        $dataset['pwa_cache_version'] = FrontendAssetVersion::resolve($this->appConfig());

        $allowedViewModes = ['navigation', 'monolithic'];
        foreach ($this->docsViewModeDefaults() as $key => $fallback) {
            $incoming = strtolower(trim((string) ($payload[$key] ?? '')));
            $mode = in_array($incoming, $allowedViewModes, true) ? $incoming : $fallback;
            $this->upsertSysConfig($key, $mode, 'string');
            $dataset[$key] = $mode;
        }

        $ndEnabled = isset($payload['narrative_delegation_enabled']) ? (int) $payload['narrative_delegation_enabled'] : 0;
        $ndEnabled = ($ndEnabled === 1) ? 1 : 0;
        $ndLevel = isset($payload['narrative_delegation_level']) ? (int) $payload['narrative_delegation_level'] : 0;
        if (!in_array($ndLevel, [0, 1, 2], true)) {
            $ndLevel = 0;
        }
        $this->upsertSysConfig('narrative_delegation_enabled', (string) $ndEnabled, 'number');
        $this->upsertSysConfig('narrative_delegation_level', (string) $ndLevel, 'number');
        $dataset['narrative_delegation_enabled'] = $ndEnabled;
        $dataset['narrative_delegation_level'] = $ndLevel;
        SessionStore::set('config_narrative_delegation_enabled', $ndEnabled);
        SessionStore::set('config_narrative_delegation_level', $ndLevel);

        // Account deletion days
        $characterDeleteDays = isset($payload['character_delete_days']) ? (int) $payload['character_delete_days'] : 10;
        if ($characterDeleteDays < 1 || $characterDeleteDays > 365) {
            $this->failValidation('Giorni cancellazione personaggio non validi', 'character_delete_days_invalid');
        }
        $this->upsertSysConfig('character_delete_days', (string) $characterDeleteDays, 'number');
        $dataset['character_delete_days'] = $characterDeleteDays;

        // SMTP
        $smtpEnabled = isset($payload['smtp_enabled']) ? ((int) $payload['smtp_enabled'] === 1 ? 1 : 0) : 0;
        $smtpHost = trim((string) ($payload['smtp_host'] ?? ''));
        $smtpPort = isset($payload['smtp_port']) ? (int) $payload['smtp_port'] : 587;
        if ($smtpPort < 1 || $smtpPort > 65535) {
            $this->failValidation('Porta SMTP non valida', 'smtp_port_invalid');
        }
        $smtpEncryption = strtolower(trim((string) ($payload['smtp_encryption'] ?? 'tls')));
        if (!in_array($smtpEncryption, ['none', 'tls', 'ssl'], true)) {
            $smtpEncryption = 'tls';
        }
        $smtpUsername = trim((string) ($payload['smtp_username'] ?? ''));
        $smtpFromEmail = trim((string) ($payload['smtp_from_email'] ?? ''));
        $smtpFromName = trim((string) ($payload['smtp_from_name'] ?? ''));
        $mailSmtpTimeout = isset($payload['mail_smtp_timeout']) ? (int) $payload['mail_smtp_timeout'] : 30;
        $mailBatchSize = isset($payload['mail_batch_size']) ? (int) $payload['mail_batch_size'] : 10;
        $mailRatePerMinute = isset($payload['mail_rate_per_minute']) ? (int) $payload['mail_rate_per_minute'] : 60;
        $mailRetryAttempts = isset($payload['mail_retry_attempts']) ? (int) $payload['mail_retry_attempts'] : 3;

        if ($smtpFromEmail !== '' && filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL) === false) {
            $this->failValidation('Indirizzo mittente SMTP non valido', 'smtp_from_email_invalid');
        }
        if ($smtpEnabled === 1 && $smtpHost === '') {
            $this->failValidation('Host SMTP obbligatorio quando SMTP e abilitato', 'smtp_host_required');
        }
        if ($mailSmtpTimeout < 5 || $mailSmtpTimeout > 120) {
            $this->failValidation('Timeout SMTP non valido', 'mail_smtp_timeout_invalid');
        }
        if ($mailBatchSize < 1 || $mailBatchSize > 100) {
            $this->failValidation('Batch queue non valido', 'mail_batch_size_invalid');
        }
        if ($mailRatePerMinute < 1 || $mailRatePerMinute > 1000) {
            $this->failValidation('Rate limit email non valido', 'mail_rate_per_minute_invalid');
        }
        if ($mailRetryAttempts < 1 || $mailRetryAttempts > 10) {
            $this->failValidation('Numero retry email non valido', 'mail_retry_attempts_invalid');
        }

        $this->upsertSysConfig('smtp_enabled', (string) $smtpEnabled, 'number');
        $this->upsertSysConfig('smtp_host', $smtpHost, 'string');
        $this->upsertSysConfig('smtp_port', (string) $smtpPort, 'number');
        $this->upsertSysConfig('smtp_encryption', $smtpEncryption, 'string');
        $this->upsertSysConfig('smtp_username', $smtpUsername, 'string');
        $this->upsertSysConfig('smtp_from_email', $smtpFromEmail, 'string');
        $this->upsertSysConfig('smtp_from_name', $smtpFromName, 'string');
        $this->upsertSysConfig('mail_enabled', (string) $smtpEnabled, 'number');
        $this->upsertSysConfig('mail_transport', $smtpEnabled === 1 ? 'smtp' : 'native', 'string');
        $this->upsertSysConfig('mail_smtp_host', $smtpHost, 'string');
        $this->upsertSysConfig('mail_smtp_port', (string) $smtpPort, 'number');
        $this->upsertSysConfig('mail_smtp_encryption', $smtpEncryption, 'string');
        $this->upsertSysConfig('mail_smtp_username', $smtpUsername, 'string');
        $this->upsertSysConfig('mail_from_email', $smtpFromEmail, 'string');
        $this->upsertSysConfig('mail_from_name', $smtpFromName, 'string');
        $this->upsertSysConfig('mail_smtp_timeout', (string) $mailSmtpTimeout, 'number');
        $this->upsertSysConfig('mail_batch_size', (string) $mailBatchSize, 'number');
        $this->upsertSysConfig('mail_rate_per_minute', (string) $mailRatePerMinute, 'number');
        $this->upsertSysConfig('mail_retry_attempts', (string) $mailRetryAttempts, 'number');

        // Save password only if not the placeholder
        $smtpPasswordRaw = (string) ($payload['smtp_password'] ?? '');
        if ($smtpPasswordRaw !== '********' && $smtpPasswordRaw !== '') {
            $this->upsertSysConfig('smtp_password', $smtpPasswordRaw, 'string');
            $this->upsertSysConfig('mail_smtp_password', $smtpPasswordRaw, 'string');
        } elseif ($smtpPasswordRaw === '') {
            $this->upsertSysConfig('smtp_password', '', 'string');
            $this->upsertSysConfig('mail_smtp_password', '', 'string');
        }

        $dataset['smtp_enabled'] = $smtpEnabled;
        $dataset['smtp_host'] = $smtpHost;
        $dataset['smtp_port'] = $smtpPort;
        $dataset['smtp_encryption'] = $smtpEncryption;
        $dataset['smtp_username'] = $smtpUsername;
        $dataset['smtp_from_email'] = $smtpFromEmail;
        $dataset['smtp_from_name'] = $smtpFromName;
        $dataset['smtp_password'] = '********';
        $dataset['mail_smtp_timeout'] = $mailSmtpTimeout;
        $dataset['mail_batch_size'] = $mailBatchSize;
        $dataset['mail_rate_per_minute'] = $mailRatePerMinute;
        $dataset['mail_retry_attempts'] = $mailRetryAttempts;

        return $dataset;
    }

    // -------------------------------------------------------------------------
    // Narrative Delegation Settings
    // -------------------------------------------------------------------------

    public function getNarrativeDelegationDataset(): array
    {
        return [
            'narrative_delegation_enabled' => $this->getConfigInt('narrative_delegation_enabled', 0),
            'narrative_delegation_level' => $this->getConfigInt('narrative_delegation_level', 0),
        ];
    }

    public function updateNarrativeDelegationSettings($payload): array
    {
        if (!is_array($payload)) {
            $this->failValidation('Dati non validi');
        }

        $enabled = isset($payload['narrative_delegation_enabled']) ? (int) $payload['narrative_delegation_enabled'] : 0;
        $enabled = ($enabled === 1) ? 1 : 0;

        $level = isset($payload['narrative_delegation_level']) ? (int) $payload['narrative_delegation_level'] : 0;
        if (!in_array($level, [0, 1, 2], true)) {
            $this->failValidation('Livello di delega narrativa non valido', 'narrative_delegation_level_invalid');
        }

        $this->upsertSysConfig('narrative_delegation_enabled', (string) $enabled, 'number');
        $this->upsertSysConfig('narrative_delegation_level', (string) $level, 'number');

        return [
            'narrative_delegation_enabled' => $enabled,
            'narrative_delegation_level' => $level,
        ];
    }
}
