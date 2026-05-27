<?php

declare(strict_types=1);

use App\Services\MailService;
use App\Services\SettingsService;
use Core\Http\ApiResponse;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;

class Settings
{
    /** @var SettingsService */
    private $settingsService;

    public function __construct(SettingsService $settingsService = null)
    {
        $this->settingsService = $settingsService ?: new SettingsService();
    }

    private function requireAdmin()
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
    }

    public function upload()
    {
        \Core\AuthGuard::api()->requireUser();

        $dataset = $this->settingsService->getUploadDataset();

        return ResponseEmitter::emit(ApiResponse::json([
            'dataset' => $dataset,
        ]));
    }

    public function updateUpload()
    {
        $this->requireAdmin();
        $request = RequestData::fromGlobals();
        $data = $request->postJson('data', [], true);
        $dataset = $this->settingsService->updateUploadSettings($data);

        return ResponseEmitter::emit(ApiResponse::json([
            'dataset' => $dataset,
        ]));
    }

    public function adminGet()
    {
        $this->requireAdmin();
        $dataset = $this->settingsService->getAdminSettingsDataset();
        return ResponseEmitter::emit(ApiResponse::json(['dataset' => $dataset]));
    }

    public function adminUpdate()
    {
        $this->requireAdmin();
        $request = RequestData::fromGlobals();
        $data = $request->postJson('data', [], true);
        $dataset = $this->settingsService->updateAdminSettings($data);
        return ResponseEmitter::emit(ApiResponse::json(['dataset' => $dataset]));
    }

    public function testMail()
    {
        $this->requireAdmin();
        $request = RequestData::fromGlobals();
        $to = trim((string) ($request->postJson('to', '') ?? ''));

        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return ResponseEmitter::emit(ApiResponse::json([
                'error' => ['message' => 'Indirizzo email non valido.'],
            ], 422));
        }

        $mailer = new MailService();
        $config = $mailer->getSmtpConfig();
        $method = ((int) $config['smtp_enabled'] === 1 && $config['smtp_host'] !== '') ? 'SMTP' : 'mail()';
        $sent = $mailer->send($to, 'Test email - ' . (defined('APP') ? constant('APP')['name'] : 'Logeon'), '<h3>Test email</h3><p>Se ricevi questa email, la configurazione di invio tramite <strong>' . htmlspecialchars($method) . '</strong> e funzionante.</p>');

        if (!$sent) {
            return ResponseEmitter::emit(ApiResponse::json([
                'error' => ['message' => 'Invio non riuscito. Controlla la configurazione SMTP e i log del server.'],
            ], 500));
        }

        return ResponseEmitter::emit(ApiResponse::json([
            'success' => true,
            'method' => $method,
        ]));
    }

    public function narrativeDelegationGet()
    {
        $this->requireAdmin();
        $dataset = $this->settingsService->getNarrativeDelegationDataset();
        return ResponseEmitter::emit(ApiResponse::json(['dataset' => $dataset]));
    }

    public function narrativeDelegationUpdate()
    {
        $this->requireAdmin();
        $request = RequestData::fromGlobals();
        $data = $request->postJson('data', [], true);
        $dataset = $this->settingsService->updateNarrativeDelegationSettings($data);
        return ResponseEmitter::emit(ApiResponse::json(['dataset' => $dataset]));
    }
}


