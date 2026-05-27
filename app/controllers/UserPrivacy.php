<?php

declare(strict_types=1);

use App\Services\GdprService;
use App\Services\GdprRetentionService;
use App\Services\AdminComplianceNoticeService;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;
use Core\SessionStore;

class UserPrivacy
{
    /** @var GdprService|null */
    private $service = null;
    /** @var GdprRetentionService|null */
    private $retentionService = null;
    /** @var AdminComplianceNoticeService|null */
    private $adminComplianceNoticeService = null;

    private function service(): GdprService
    {
        if ($this->service instanceof GdprService) {
            return $this->service;
        }

        $this->service = new GdprService();
        return $this->service;
    }

    private function retentionService(): GdprRetentionService
    {
        if ($this->retentionService instanceof GdprRetentionService) {
            return $this->retentionService;
        }

        $this->retentionService = new GdprRetentionService();
        return $this->retentionService;
    }

    private function adminComplianceNoticeService(): AdminComplianceNoticeService
    {
        if ($this->adminComplianceNoticeService instanceof AdminComplianceNoticeService) {
            return $this->adminComplianceNoticeService;
        }

        $this->adminComplianceNoticeService = new AdminComplianceNoticeService();
        return $this->adminComplianceNoticeService;
    }

    private function requestDataObject($default = null)
    {
        $request = RequestData::fromGlobals();
        return InputValidator::postJsonObject($request, 'data', true) ?? $default;
    }

    private function emit(array $payload): void
    {
        ResponseEmitter::emit(ApiResponse::json($payload));
    }

    private function isSuperuser(): bool
    {
        return \Core\AppContext::authContext()->isSuperuser();
    }

    private function requireGdprSensitiveAccess(): void
    {
        if ($this->isSuperuser()) {
            return;
        }

        throw AppError::unauthorized(
            'Operazione consentita solo ai superuser.',
            [],
            'gdpr_sensitive_access_forbidden',
        );
    }

    private function requireCreatorAccess(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage', [], 'Operazione non autorizzata');

        $creatorFlag = SessionStore::get('user_is_superuser_creator');
        if ($creatorFlag === null) {
            $creatorFlag = SessionStore::get('user_is_superuser');
        }

        $isCreator = ((int) ($creatorFlag ?? 0) === 1);
        if (!$isCreator) {
            \Core\AuthGuard::api()->requireAbility('system.update', [], 'Operazione consentita solo al creator');
        }
    }

    public function context(): void
    {
        \Core\AuthGuard::api()->requireUser();
        $this->emit([
            'success' => true,
            'context' => $this->service()->legalContext(),
        ]);
    }

    public function requestsList(): void
    {
        $userId = \Core\AuthGuard::api()->requireUser();
        $this->emit([
            'success' => true,
            'dataset' => $this->service()->listRequestsByUser((int) $userId),
        ]);
    }

    public function requestCreate(): void
    {
        $userId = \Core\AuthGuard::api()->requireUser();
        $data = $this->requestDataObject((object) []);
        $requestType = InputValidator::string($data, 'request_type', '');
        $note = InputValidator::string($data, 'note', '');
        $created = $this->service()->createRequest((int) $userId, $requestType, $note, 'settings');

        $this->emit([
            'success' => true,
            'dataset' => $created,
        ]);
    }

    public function exportData(): void
    {
        $userId = \Core\AuthGuard::api()->requireUser();
        $dataset = $this->service()->exportDatasetForUser((int) $userId);
        $this->emit([
            'success' => true,
            'dataset' => $dataset,
        ]);
    }

    public function adminList(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
        $data = $this->requestDataObject((object) []);
        $query = (isset($data->query) && is_object($data->query)) ? $data->query : (object) [];

        $status = InputValidator::string($query, 'status', 'all');
        $requestType = InputValidator::string($query, 'request_type', '');
        $page = max(1, InputValidator::integer($data, 'page', 1));
        $results = max(1, InputValidator::integer($data, 'results', 20));

        $payload = $this->service()->listRequestsForAdmin(
            $status,
            $requestType,
            $page,
            $results,
            $this->isSuperuser(),
        );
        $this->emit([
            'dataset' => $payload['dataset'],
            'properties' => [
                'query' => $payload['query'],
                'page' => $payload['page'],
                'results_page' => $payload['results_page'],
                'tot' => ['count' => $payload['tot']],
            ],
        ]);
    }

    public function adminUpdateStatus(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
        $handlerUserId = \Core\AuthGuard::api()->requireUser();
        $data = $this->requestDataObject((object) []);

        $requestId = InputValidator::positiveInt($data, 'id', 'Richiesta non valida', 'gdpr_request_invalid');
        $status = InputValidator::string($data, 'status', '');
        $resolutionNote = InputValidator::string($data, 'resolution_note', '');

        $updated = $this->service()->updateRequestStatus((int) $requestId, (int) $handlerUserId, $status, $resolutionNote);
        $this->emit([
            'success' => true,
            'dataset' => $updated,
        ]);
    }

    public function adminExecute(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
        $this->requireGdprSensitiveAccess();
        $handlerUserId = \Core\AuthGuard::api()->requireUser();
        $data = $this->requestDataObject((object) []);

        $requestId = InputValidator::positiveInt($data, 'id', 'Richiesta non valida', 'gdpr_request_invalid');
        $resolutionNote = InputValidator::string($data, 'resolution_note', '');
        $result = $this->service()->executeRequestAction((int) $requestId, (int) $handlerUserId, $resolutionNote);

        $this->emit([
            'success' => true,
            'dataset' => $result,
        ]);
    }

    public function adminExportPayload(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
        $this->requireGdprSensitiveAccess();
        $data = $this->requestDataObject((object) []);
        $requestId = InputValidator::positiveInt($data, 'id', 'Richiesta non valida', 'gdpr_request_invalid');

        $payload = $this->service()->exportPayloadForAdmin((int) $requestId);
        $this->emit([
            'success' => true,
            'dataset' => $payload,
        ]);
    }

    public function cookieConsent(): void
    {
        $data = $this->requestDataObject((object) []);
        $choice = InputValidator::string($data, 'choice', '');
        $prefsObj = (isset($data->preferences) && is_object($data->preferences)) ? $data->preferences : (object) [];

        $preferences = [
            'preferences' => InputValidator::boolean($prefsObj, 'preferences', false),
            'analytics' => InputValidator::boolean($prefsObj, 'analytics', false),
            'marketing' => InputValidator::boolean($prefsObj, 'marketing', false),
        ];

        $auth = \Core\AppContext::authContext();
        $userId = ($auth && $auth->isAuthenticated()) ? (int) $auth->userId() : null;

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null;

        $result = $this->service()->recordCookieConsent(
            $userId,
            $choice,
            $preferences,
            'public_banner',
            $ip,
            $ua,
        );

        $this->emit([
            'success' => true,
            'dataset' => $result,
        ]);
    }

    public function adminRunRetention(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
        $data = $this->requestDataObject((object) []);
        $force = InputValidator::boolean($data, 'force', true);

        $dataset = $this->retentionService()->run($force);
        $this->emit([
            'success' => true,
            'dataset' => $dataset,
        ]);
    }

    public function adminInstanceComplianceNoticeStatus(): void
    {
        $this->requireCreatorAccess();
        $userId = \Core\AuthGuard::api()->requireUser();
        $dataset = $this->adminComplianceNoticeService()->statusForUser((int) $userId);

        $this->emit([
            'success' => true,
            'dataset' => $dataset,
        ]);
    }

    public function adminInstanceComplianceNoticeAcknowledge(): void
    {
        $this->requireCreatorAccess();
        $userId = \Core\AuthGuard::api()->requireUser();
        $data = $this->requestDataObject((object) []);
        $accepted = InputValidator::boolean($data, 'accepted', false);
        if (!$accepted) {
            throw AppError::validation(
                'Conferma richiesta per salvare l\'accettazione.',
                [],
                'compliance_notice_accept_required',
            );
        }

        $dataset = $this->adminComplianceNoticeService()->acknowledgeForUser((int) $userId);
        $this->emit([
            'success' => true,
            'dataset' => $dataset,
        ]);
    }
}
