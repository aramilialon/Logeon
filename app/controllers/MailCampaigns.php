<?php

declare(strict_types=1);

use App\Services\MailCampaignService;
use Core\HtmlSanitizer;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;

class MailCampaigns
{
    private ?MailCampaignService $service = null;

    private function service(): MailCampaignService
    {
        if ($this->service === null) {
            $this->service = new MailCampaignService();
        }
        return $this->service;
    }

    private function requireAdmin(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
    }

    private function currentUserId(): int
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

    // ── List ─────────────────────────────────────────────────────────────

    public function adminList(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $filterObj = isset($data->query) && is_object($data->query) ? $data->query : (object) [];
        $query = InputValidator::string($filterObj, 'query', '');
        $statusFilter = InputValidator::string($filterObj, 'status', '');
        $page = max(1, InputValidator::integer($data, 'page', 1));
        $results = max(1, InputValidator::integer($data, 'results', 20));
        $orderBy = InputValidator::string($data, 'orderBy', 'id|DESC');

        $result = $this->service()->list($query, $statusFilter, $page, $results, $orderBy);

        $this->emit([
            'success' => true,
            'dataset' => $result['dataset'],
            'properties' => [
                'query' => $result['query'],
                'status' => $result['status'],
                'page' => $result['page'],
                'results_page' => $result['results_page'],
                'orderBy' => $result['orderBy'],
                'tot' => $result['tot'],
            ],
        ]);
    }

    // ── Get ──────────────────────────────────────────────────────────────

    public function adminGet(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');
        $row = $this->service()->get($id);

        if ($row === null) {
            throw AppError::validation('Campagna non trovata.', [], 'not_found');
        }

        $this->emit(['success' => true, 'campaign' => $row]);
    }

    // ── Create ───────────────────────────────────────────────────────────

    public function adminCreate(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $subject = trim(InputValidator::string($data, 'subject', ''));
        $bodyHtml = HtmlSanitizer::sanitize(InputValidator::string($data, 'body_html', ''), ['allow_images' => true]);
        $bodyText = trim(InputValidator::string($data, 'body_text', ''));
        $templateId = InputValidator::integer($data, 'template_id', 0) ?: null;
        $listId = InputValidator::integer($data, 'distribution_list_id', 0) ?: null;
        $category = InputValidator::string($data, 'category', 'announcement');

        if ($subject === '') {
            throw AppError::validation("L'oggetto è obbligatorio.", [], 'subject_required');
        }

        $id = $this->service()->create(
            $subject,
            $bodyHtml,
            $bodyText,
            $templateId,
            $listId,
            $category,
            $this->currentUserId(),
        );

        $this->emit(['success' => true, 'id' => $id]);
    }

    // ── Update ───────────────────────────────────────────────────────────

    public function adminUpdate(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');
        $subject = trim(InputValidator::string($data, 'subject', ''));
        $bodyHtml = HtmlSanitizer::sanitize(InputValidator::string($data, 'body_html', ''), ['allow_images' => true]);
        $bodyText = trim(InputValidator::string($data, 'body_text', ''));
        $templateId = InputValidator::integer($data, 'template_id', 0) ?: null;
        $listId = InputValidator::integer($data, 'distribution_list_id', 0) ?: null;
        $category = InputValidator::string($data, 'category', 'announcement');

        if ($subject === '') {
            throw AppError::validation("L'oggetto è obbligatorio.", [], 'subject_required');
        }

        $this->service()->update($id, $subject, $bodyHtml, $bodyText, $templateId, $listId, $category);
        $this->emit(['success' => true]);
    }

    // ── Delete ───────────────────────────────────────────────────────────

    public function adminDelete(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');

        $this->service()->delete($id);
        $this->emit(['success' => true]);
    }

    // ── Send Test ────────────────────────────────────────────────────────

    public function adminSendTest(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');
        $testEmail = trim(InputValidator::string($data, 'test_email', ''));

        if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            throw AppError::validation('Indirizzo email di test non valido.', [], 'invalid_email');
        }

        $this->service()->sendTest($id, $testEmail);
        $this->emit(['success' => true]);
    }

    // ── Send Now ─────────────────────────────────────────────────────────

    public function adminSendNow(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');

        $n = $this->service()->sendNow($id);
        $this->emit(['success' => true, 'recipients' => $n]);
    }

    // ── Schedule ─────────────────────────────────────────────────────────

    public function adminSchedule(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');
        $scheduledAt = trim(InputValidator::string($data, 'scheduled_at', ''));

        if ($scheduledAt === '') {
            throw AppError::validation('La data di pianificazione è obbligatoria.', [], 'scheduled_at_required');
        }

        $this->service()->schedule($id, $scheduledAt);
        $this->emit(['success' => true]);
    }

    // ── Cancel ───────────────────────────────────────────────────────────

    public function adminCancel(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');

        $this->service()->cancel($id);
        $this->emit(['success' => true]);
    }

    // ── Queue Tick ───────────────────────────────────────────────────────

    public function adminQueueTick(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $limit = max(1, min(50, InputValidator::integer($data, 'limit', 5)));

        $result = $this->service()->queueTick($limit);
        $this->emit(['success' => true, 'processed' => $result['processed']]);
    }

    // ── Queue List (mail logs) ────────────────────────────────────────────

    public function adminQueueList(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $filterObj = isset($data->query) && is_object($data->query) ? $data->query : (object) [];
        $messageId = InputValidator::integer($filterObj, 'message_id', 0) ?: null;
        $statusFilter = InputValidator::string($filterObj, 'status', '');
        $page = max(1, InputValidator::integer($data, 'page', 1));
        $results = max(1, min(100, InputValidator::integer($data, 'results', 20)));
        $orderBy = InputValidator::string($data, 'orderBy', 'id|DESC');

        $result = $this->service()->queueList($messageId, $statusFilter, $page, $results, $orderBy);

        $this->emit([
            'success' => true,
            'dataset' => $result['dataset'],
            'properties' => [
                'message_id' => $result['message_id'],
                'status' => $result['status'],
                'page' => $result['page'],
                'results_page' => $result['results_page'],
                'orderBy' => $result['orderBy'],
                'tot' => $result['tot'],
            ],
        ]);
    }
}
