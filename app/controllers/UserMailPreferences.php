<?php

declare(strict_types=1);

use App\Services\MailConsentService;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;

class UserMailPreferences
{
    private ?MailConsentService $service = null;

    private function service(): MailConsentService
    {
        if ($this->service === null) {
            $this->service = new MailConsentService();
        }
        return $this->service;
    }

    private function requireUser(): int
    {
        return (int) \Core\AuthGuard::api()->requireUser();
    }

    private function requestData(): object
    {
        return InputValidator::postJsonObject(RequestData::fromGlobals(), 'data', true);
    }

    private function emit(array $payload): void
    {
        ResponseEmitter::emit(ApiResponse::json($payload));
    }

    public function getPreferences(): void
    {
        $userId = $this->requireUser();
        $prefs = $this->service()->getPreferences($userId);

        $this->emit(['success' => true, 'preferences' => $prefs]);
    }

    public function updatePreferences(): void
    {
        $userId = $this->requireUser();
        $data = $this->requestData();

        if (!isset($data->newsletter_opt_in)) {
            throw AppError::validation('newsletter_opt_in è obbligatorio.', [], 'field_required');
        }

        $value = (bool) $data->newsletter_opt_in;
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null;

        $this->service()->setNewsletterOptIn($userId, $value, 'user_settings', $ip, $ua);

        $this->emit(['success' => true]);
    }
}
