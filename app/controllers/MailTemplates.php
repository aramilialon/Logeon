<?php

declare(strict_types=1);

use App\Services\MailRendererService;
use App\Services\MailTemplateService;
use Core\HtmlSanitizer;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;

class MailTemplates
{
    private ?MailTemplateService $service = null;

    private function service(): MailTemplateService
    {
        if ($this->service === null) {
            $this->service = new MailTemplateService();
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

    public function adminGet(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');
        $row = $this->service()->get($id);

        if ($row === null) {
            throw AppError::validation('Template non trovato.', [], 'not_found');
        }

        $this->emit(['success' => true, 'template' => $row]);
    }

    public function adminCreate(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $name = trim(InputValidator::string($data, 'name', ''));
        $description = trim(InputValidator::string($data, 'description', ''));
        $subject = trim(InputValidator::string($data, 'subject', ''));
        $bodyHtml = HtmlSanitizer::sanitize(InputValidator::string($data, 'body_html', ''), ['allow_images' => true]);
        $category = InputValidator::string($data, 'category', 'transactional');

        if ($name === '') {
            throw AppError::validation('Il nome è obbligatorio.', [], 'name_required');
        }
        if ($subject === '') {
            throw AppError::validation('L\'oggetto è obbligatorio.', [], 'subject_required');
        }

        $id = $this->service()->create($name, $description, $subject, $bodyHtml, $category);
        $this->emit(['success' => true, 'id' => $id]);
    }

    public function adminUpdate(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');
        $name = trim(InputValidator::string($data, 'name', ''));
        $description = trim(InputValidator::string($data, 'description', ''));
        $subject = trim(InputValidator::string($data, 'subject', ''));
        $bodyHtml = HtmlSanitizer::sanitize(InputValidator::string($data, 'body_html', ''), ['allow_images' => true]);
        $category = InputValidator::string($data, 'category', 'transactional');

        if ($name === '') {
            throw AppError::validation('Il nome è obbligatorio.', [], 'name_required');
        }
        if ($subject === '') {
            throw AppError::validation('L\'oggetto è obbligatorio.', [], 'subject_required');
        }

        $this->service()->update($id, $name, $description, $subject, $bodyHtml, $category);
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

    public function adminPreview(): void
    {
        $this->requireAdmin();

        $data = $this->requestData();
        $id = InputValidator::positiveInt($data, 'id', 'ID non valido', 'id_invalid');
        $row = $this->service()->get($id);

        if ($row === null) {
            throw AppError::validation('Template non trovato.', [], 'not_found');
        }

        $vars = MailRendererService::buildVars(
            InputValidator::string($data, 'username', 'Utente di prova'),
            InputValidator::string($data, 'email', 'test@example.com'),
            InputValidator::string($data, 'character_name', 'Personaggio'),
            InputValidator::string($data, 'character_full_name', 'Personaggio di Prova'),
        );

        $this->emit([
            'success' => true,
            'subject' => MailRendererService::renderSubject((string) $row->subject, $vars),
            'body_html' => MailRendererService::renderHtml((string) $row->body_html, $vars),
        ]);
    }
}
