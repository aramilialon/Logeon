<?php

declare(strict_types=1);

use App\Services\MailDistributionListService;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;

class MailDistributionLists
{
    private ?MailDistributionListService $service = null;

    private function service(): MailDistributionListService
    {
        if ($this->service === null) {
            $this->service = new MailDistributionListService();
        }
        return $this->service;
    }

    private function requireAdmin(): void
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
    }

    private function requestData(): object
    {
        return InputValidator::postJsonObject(RequestData::fromGlobals(), 'data', true);
    }

    private function emit(array $payload): void
    {
        ResponseEmitter::emit(ApiResponse::json($payload));
    }

    public function adminList(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $filterObj = isset($data->query) && is_object($data->query) ? $data->query : (object) [];
        $query = InputValidator::string($filterObj, 'query', '');
        $page = max(1, InputValidator::integer($data, 'page', 1));
        $results = max(1, InputValidator::integer($data, 'results', 20));
        $orderBy = InputValidator::string($data, 'orderBy', 'id|DESC');

        $result = $this->service()->list($query, $page, $results, $orderBy);

        $this->emit([
            'success' => true,
            'dataset' => $result['dataset'],
            'properties' => [
                'query' => $result['query'],
                'page' => $result['page'],
                'results_page' => $result['results_page'],
                'orderBy' => $result['orderBy'],
                'tot' => $result['tot'],
            ],
        ]);
    }

    public function adminCreate(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $name = trim(InputValidator::string($data, 'name', ''));
        $description = trim(InputValidator::string($data, 'description', ''));
        $type = InputValidator::string($data, 'type', 'manual');
        $segmentKey = InputValidator::string($data, 'segment_key', '') ?: null;

        if ($name === '') {
            throw AppError::validation('Il nome è obbligatorio.', [], 'name_required');
        }

        $id = $this->service()->create($name, $description, $type, $segmentKey);
        $this->emit(['success' => true, 'id' => $id]);
    }

    public function adminUpdate(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');
        $name = trim(InputValidator::string($data, 'name', ''));
        $description = trim(InputValidator::string($data, 'description', ''));
        $type = InputValidator::string($data, 'type', 'manual');
        $segmentKey = InputValidator::string($data, 'segment_key', '') ?: null;

        if ($name === '') {
            throw AppError::validation('Il nome è obbligatorio.', [], 'name_required');
        }

        $this->service()->update($id, $name, $description, $type, $segmentKey);
        $this->emit(['success' => true]);
    }

    public function adminDelete(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');

        $this->service()->delete($id);
        $this->emit(['success' => true]);
    }

    public function adminMembersList(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $filterObj = isset($data->query) && is_object($data->query) ? $data->query : (object) [];
        $listId = InputValidator::positiveInt($filterObj, 'list_id', 'Lista non valida', 'list_id_invalid');
        $page = max(1, InputValidator::integer($data, 'page', 1));
        $results = max(1, InputValidator::integer($data, 'results', 50));

        $result = $this->service()->getMembers($listId, $page, $results);

        $this->emit([
            'success' => true,
            'dataset' => $result['dataset'],
            'properties' => [
                'page' => $result['page'],
                'results_page' => $result['results_page'],
                'tot' => $result['tot'],
            ],
        ]);
    }

    public function adminMembersAdd(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $listId = InputValidator::positiveInt($data, 'list_id', 'Lista non valida', 'list_id_invalid');
        $email = trim(InputValidator::string($data, 'email', ''));
        $displayName = trim(InputValidator::string($data, 'display_name', ''));
        $userId = InputValidator::integer($data, 'user_id', 0) ?: null;

        if ($email === '') {
            throw AppError::validation('Email obbligatoria.', [], 'email_required');
        }

        $this->service()->addMember($listId, $email, $displayName, $userId);
        $this->emit(['success' => true]);
    }

    public function adminMembersRemove(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $memberId = InputValidator::positiveInt($data, 'member_id', 'Membro non valido', 'member_id_invalid');

        $this->service()->removeMember($memberId);
        $this->emit(['success' => true]);
    }

    public function adminUsersSearch(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $q = InputValidator::string($data, 'q', '');
        $limit = max(1, min(50, InputValidator::integer($data, 'limit', 20)));

        $users = $this->service()->searchUsers($q, $limit);
        $this->emit(['success' => true, 'dataset' => $users]);
    }

    public function adminMembersAddBulk(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $listId = InputValidator::positiveInt($data, 'list_id', 'Lista non valida', 'list_id_invalid');
        $userIds = InputValidator::arrayOfValues($data, 'user_ids', []);

        $added = $this->service()->addMembersBulk($listId, $userIds);
        $this->emit(['success' => true, 'added' => $added]);
    }
}
