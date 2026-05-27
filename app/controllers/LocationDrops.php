<?php

declare(strict_types=1);

use App\Services\LocationDropService;
use App\Services\LocationMessageService;
use Core\Filter;
use Core\Http\ApiResponse;
use Core\Http\AppError;
use Core\Http\InputValidator;
use Core\Http\RequestData;
use Core\Http\ResponseEmitter;

use Core\Logging\LoggerInterface;
use Core\SessionStore;

class LocationDrops
{
    private const TYPE_SYSTEM = 3;

    /** @var LoggerInterface|null */
    private $logger = null;
    /** @var LocationDropService|null */
    private $locationDropService = null;
    /** @var LocationMessageService|null */
    private $locationMessageService = null;

    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        return $this;
    }

    public function setLocationDropService(LocationDropService $locationDropService = null)
    {
        $this->locationDropService = $locationDropService;
        return $this;
    }

    public function setLocationMessageService(LocationMessageService $locationMessageService = null)
    {
        $this->locationMessageService = $locationMessageService;
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

    private function locationDropService(): LocationDropService
    {
        if ($this->locationDropService instanceof LocationDropService) {
            return $this->locationDropService;
        }

        $this->locationDropService = new LocationDropService();
        return $this->locationDropService;
    }

    private function locationMessageService(): LocationMessageService
    {
        if ($this->locationMessageService instanceof LocationMessageService) {
            return $this->locationMessageService;
        }

        $this->locationMessageService = new LocationMessageService();
        return $this->locationMessageService;
    }

    protected function trace($message, $context = false): void
    {
        $this->logger()->trace($message, $context);
    }

    private function failValidation($message, string $errorCode = '')
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
            'Location non valida' => 'location_invalid',
            'Oggetto non valido' => 'item_invalid',
            'Rimuovi prima l\'equipaggiamento' => 'item_equipped_remove_first',
            'Oggetto non rilasciabile' => 'item_not_droppable',
            'Quantita non valida' => 'quantity_invalid',
            'Oggetto non disponibile' => 'drop_not_available',
            'Oggetto non disponibile in questa location' => 'drop_not_in_location',
        ];

        return $map[$message] ?? 'validation_error';
    }

    private function requestDataObject()
    {
        $request = RequestData::fromGlobals();
        return InputValidator::postJsonObject($request, 'data', true);
    }

    private function getSessionValue($key)
    {
        return SessionStore::get($key);
    }

    private function getSessionLastLocationId(): int
    {
        return (int) $this->getSessionValue('character_last_location');
    }

    private function resolveLocationId($character_id, $location_id = null)
    {
        return $this->locationDropService()->resolveLocationId(
            (int) $character_id,
            !empty($location_id) ? (int) $location_id : null,
            $this->getSessionLastLocationId(),
        );
    }

    private function publishDropSystemMessage(int $locationId, int $characterId, string $itemName, int $quantity): void
    {
        if ($locationId <= 0 || $characterId <= 0) {
            return;
        }

        $label = trim($itemName);
        if ($label === '') {
            $label = 'Oggetto';
        }
        $qty = $quantity > 0 ? $quantity : 1;
        $qtyLabel = $qty > 1 ? ' (x' . $qty . ')' : '';

        $body = '<div class="text-center">'
            . '<p class="mb-1"><b>Oggetto a terra</b></p>'
            . '<p class="mb-0">' . Filter::html($label) . Filter::html($qtyLabel) . ' lasciato a terra.</p>'
            . '</div>';

        $meta = json_encode([
            'command' => 'drop',
            'source' => 'inventory',
            'item_name' => $label,
            'quantity' => $qty,
            'location_id' => $locationId,
        ], JSON_UNESCAPED_UNICODE);

        $this->locationMessageService()->insertMessage(
            $locationId,
            $characterId,
            self::TYPE_SYSTEM,
            $body,
            $meta,
        );
    }

    private function publishPickupSystemMessage(int $locationId, int $characterId, string $itemName, int $quantity): void
    {
        if ($locationId <= 0 || $characterId <= 0) {
            return;
        }

        $label = trim($itemName);
        if ($label === '') {
            $label = 'Oggetto';
        }
        $qty = $quantity > 0 ? $quantity : 1;
        $qtyLabel = $qty > 1 ? ' (x' . $qty . ')' : '';

        $body = '<div class="text-center">'
            . '<p class="mb-1"><b>Oggetto raccolto</b></p>'
            . '<p class="mb-0">' . Filter::html($label) . Filter::html($qtyLabel) . ' raccolto da terra.</p>'
            . '</div>';

        $meta = json_encode([
            'command' => 'pickup',
            'source' => 'location_drop',
            'item_name' => $label,
            'quantity' => $qty,
            'location_id' => $locationId,
        ], JSON_UNESCAPED_UNICODE);

        $this->locationMessageService()->insertMessage(
            $locationId,
            $characterId,
            self::TYPE_SYSTEM,
            $body,
            $meta,
        );
    }

    public function list()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $me = \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        $location_id = $this->resolveLocationId($me, $data->location_id ?? null);
        if (empty($location_id)) {
            ResponseEmitter::emit(ApiResponse::json(['dataset' => []]));
            return;
        }

        $dataset = $this->locationDropService()->listByLocation((int) $location_id);

        ResponseEmitter::emit(ApiResponse::json([
            'dataset' => $dataset,
        ]));
    }

    public function drop()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $me = \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        $location_id = $this->resolveLocationId($me, $data->location_id ?? null);
        if (empty($location_id)) {
            $this->failValidation('Location non valida');
        }

        $character_item_id = !empty($data->character_item_id) ? (int) $data->character_item_id : null;
        $character_item_instance_id = !empty($data->character_item_instance_id) ? (int) $data->character_item_instance_id : null;

        if (!empty($character_item_instance_id)) {
            $row = $this->locationDropService()->findCharacterItemInstance((int) $character_item_instance_id, (int) $me);

            if (empty($row)) {
                $this->failValidation('Oggetto non valido');
            }
            if (!empty($row->is_equipped)) {
                $this->failValidation('Rimuovi prima l\'equipaggiamento');
            }
            if (isset($row->droppable) && (int) $row->droppable !== 1) {
                $this->failValidation('Oggetto non rilasciabile');
            }

            $this->locationDropService()->dropCharacterItemInstance((int) $location_id, (int) $me, $row);
            $this->publishDropSystemMessage(
                (int) $location_id,
                (int) $me,
                (string) ($row->item_name ?? ''),
                1,
            );

            ResponseEmitter::emit(ApiResponse::json(['status' => 'ok']));
            return;
        }

        if (!empty($character_item_id)) {
            $qty = !empty($data->quantity) ? (int) $data->quantity : 1;
            if ($qty < 1) {
                $qty = 1;
            }

            $row = $this->locationDropService()->findCharacterItemStack((int) $character_item_id, (int) $me);

            if (empty($row)) {
                $this->failValidation('Oggetto non valido');
            }

            $available = (int) $row->quantity;
            if ($qty > $available) {
                $this->failValidation('Quantita non valida');
            }
            if (isset($row->droppable) && (int) $row->droppable !== 1) {
                $this->failValidation('Oggetto non rilasciabile');
            }

            $this->locationDropService()->dropCharacterItemStack((int) $location_id, (int) $me, $row, (int) $qty);
            $this->publishDropSystemMessage(
                (int) $location_id,
                (int) $me,
                (string) ($row->item_name ?? ''),
                (int) $qty,
            );

            ResponseEmitter::emit(ApiResponse::json(['status' => 'ok']));
            return;
        }

        $this->failValidation('Oggetto non valido');
    }

    public function pickup()
    {
        $this->trace('Richiamato il metodo: ' . __METHOD__);
        $me = \Core\AuthGuard::api()->requireCharacter();
        $data = $this->requestDataObject();

        if (empty($data->drop_id)) {
            $this->failValidation('Oggetto non valido');
        }

        $location_id = $this->resolveLocationId($me, $data->location_id ?? null);
        if (empty($location_id)) {
            $this->failValidation('Location non valida');
        }

        $drop_id = (int) $data->drop_id;
        $drop = $this->locationDropService()->findDropById((int) $drop_id);

        if (empty($drop)) {
            $this->failValidation('Oggetto non disponibile');
        }

        if ((int) $drop->location_id !== (int) $location_id) {
            $this->failValidation('Oggetto non disponibile in questa location');
        }

        if (!empty($drop->is_stackable)) {
            $this->locationDropService()->pickupDropStack((int) $me, $drop);
        } else {
            $this->locationDropService()->pickupDropInstance((int) $me, $drop);
        }

        $this->publishPickupSystemMessage(
            (int) $location_id,
            (int) $me,
            (string) ($drop->item_name ?? ''),
            (int) ($drop->quantity ?? 1),
        );

        $this->locationDropService()->deleteDrop((int) $drop->id);

        ResponseEmitter::emit(ApiResponse::json(['status' => 'ok']));
    }
}
