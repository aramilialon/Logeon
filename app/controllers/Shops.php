<?php

declare(strict_types=1);

use App\Models\Shop;

use App\Services\ShopAdminService;
use Core\Http\ApiResponse;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;

use Core\Logging\LoggerInterface;

class Shops extends Shop
{
    /** @var LoggerInterface|null */
    private $logger = null;
    /** @var ShopAdminService|null */
    private $shopAdminService = null;

    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        return $this;
    }

    public function setShopAdminService(ShopAdminService $shopAdminService = null)
    {
        $this->shopAdminService = $shopAdminService;
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

    protected function trace($message, $context = false): void
    {
        $this->logger()->trace($message, $context);
    }

    private function shopAdminService(): ShopAdminService
    {
        if ($this->shopAdminService instanceof ShopAdminService) {
            return $this->shopAdminService;
        }

        $this->shopAdminService = new ShopAdminService();
        return $this->shopAdminService;
    }

    private function requestDataObject()
    {
        $request = RequestData::fromGlobals();
        return InputValidator::postJsonObject($request, 'data', true);
    }

    private function requireAdmin()
    {
        \Core\AuthGuard::api()->requireAbility('settings.manage');
    }

    public function list($echo = true)
    {
        $this->requireAdmin();
        $payload = $this->shopAdminService()->list($this->requestDataObject());
        ResponseEmitter::emit(ApiResponse::json($payload));
        return $this;
    }

    public function create()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireAdmin();

        $data = $this->requestDataObject();
        $this->shopAdminService()->create($data);
        ResponseEmitter::emit(ApiResponse::json(['success' => true]));

        return $this;
    }

    public function update()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $this->requireAdmin();

        $data = $this->requestDataObject();
        $this->shopAdminService()->update($data);
        ResponseEmitter::emit(ApiResponse::json(['success' => true]));

        return $this;
    }

    public function delete($operator = '=')
    {
        $this->requireAdmin();
        $data = $this->requestDataObject();
        $this->shopAdminService()->delete((int) ($data->id ?? 0));
        ResponseEmitter::emit(ApiResponse::json(['success' => true]));
        return $this;
    }
}
