<?php

namespace Bookurier\Awb;

use Bookurier\Admin\Config\SamedayLockerRepository;
use Bookurier\Checkout\SamedayLockerSelectionRepository;
use Bookurier\DTO\Bookurier\CreateAwbRequestDto as BookurierCreateAwbRequestDto;
use Bookurier\DTO\Sameday\CreateAwbRequestDto as SamedayCreateAwbRequestDto;

class AutoAwbService
{
    const COURIER_BOOKURIER = 'bookurier';
    const COURIER_SAMEDAY = 'sameday';
    const SAMEDAY_LOCKER_SERVICE = 15;
    const SAMEDAY_PUDO_SERVICE = 17;

    private $module;
    private $awbRepository;
    private $lockerSelectionRepository;
    private $lockerRepository;
    private $columnExistsCache;

    public function __construct(
        $module,
        AwbRepository $awbRepository = null,
        SamedayLockerSelectionRepository $lockerSelectionRepository = null,
        SamedayLockerRepository $lockerRepository = null
    ) {
        $this->module = $module;
        $this->awbRepository = $awbRepository ?: new AwbRepository();
        $this->lockerSelectionRepository = $lockerSelectionRepository ?: new SamedayLockerSelectionRepository();
        $this->lockerRepository = $lockerRepository ?: new SamedayLockerRepository();
        $this->columnExistsCache = array();
    }

    public function generateForOrder($order, $idCart = 0, $carrierReference = 0)
    {
        if (!is_object($order) || !\Validate::isLoadedObject($order)) {
            return null;
        }

        $idOrder = (int) $order->id;
        if ($idOrder <= 0) {
            return null;
        }

        if ($this->awbRepository->hasCreatedAwb($idOrder)) {
            $existing = $this->awbRepository->findByOrderId($idOrder);
            if (is_array($existing)) {
                $this->syncPrestaShopTrackingNumber($order, (string) ($existing['awb_code'] ?? ''));
            }

            return $existing;
        }

        $idCart = (int) $idCart > 0 ? (int) $idCart : (int) $order->id_cart;
        $carrierReference = (int) $carrierReference > 0 ? (int) $carrierReference : $this->resolveCarrierReference($order);
        $courier = $this->resolveCourier($carrierReference);
        if ($courier === '') {
            return null;
        }

        $lockerId = $this->resolveLockerId($idOrder, $idCart);
        $requestPayload = '';
        $responsePayload = '';

        try {
            if ($courier === self::COURIER_SAMEDAY) {
                $result = $this->createSamedayAwb($order, $lockerId);
            } else {
                $result = $this->createBookurierAwb($order);
            }

            $requestPayload = (string) ($result['request_payload'] ?? '');
            $responsePayload = (string) ($result['response_payload'] ?? '');

            $this->awbRepository->saveSuccess(
                $idOrder,
                $idCart,
                (string) ($result['courier'] ?? $courier),
                (string) ($result['awb_code'] ?? ''),
                (int) ($result['locker_id'] ?? $lockerId),
                $requestPayload,
                $responsePayload
            );

            $this->syncPrestaShopTrackingNumber($order, (string) ($result['awb_code'] ?? ''));

            return $this->awbRepository->findByOrderId($idOrder);
        } catch (\Exception $exception) {
            $this->awbRepository->saveError(
                $idOrder,
                $idCart,
                $courier,
                $lockerId,
                $requestPayload,
                $responsePayload,
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    private function syncPrestaShopTrackingNumber($order, $awbCode)
    {
        $awbCode = trim((string) $awbCode);
        if ($awbCode === '' || !is_object($order) || !\Validate::isLoadedObject($order)) {
            return;
        }

        $idOrder = (int) $order->id;
        if ($idOrder <= 0) {
            return;
        }

        $idCarrier = (int) $order->id_carrier;
        $db = \Db::getInstance();
        $escapedAwb = pSQL($awbCode);
        $ok = true;
        $didUpdate = false;

        if ($this->hasColumn('orders', 'shipping_number')) {
            $didUpdate = true;
            $ok = $ok && (bool) $db->update('orders', array(
                'shipping_number' => $escapedAwb,
            ), 'id_order = ' . $idOrder);
        }

        if ($this->hasColumn('order_carrier', 'tracking_number')) {
            $didUpdate = true;
            $carrierWhere = 'id_order = ' . $idOrder;
            if ($idCarrier > 0) {
                $carrierWhere .= ' AND id_carrier = ' . $idCarrier;
            }

            $ok = $ok && (bool) $db->update('order_carrier', array(
                'tracking_number' => $escapedAwb,
            ), $carrierWhere);
        }

        if (!$didUpdate) {
            return;
        }

        if ($ok) {
            if (property_exists($order, 'shipping_number')) {
                $order->shipping_number = $awbCode;
            }

            return;
        }

        if (is_object($this->module) && method_exists($this->module, 'getLogger')) {
            $this->module->getLogger()->warning('Could not sync AWB into PrestaShop tracking fields.', array(
                'id_order' => $idOrder,
                'id_carrier' => $idCarrier,
                'awb_code' => $awbCode,
            ));
        }
    }

    private function hasColumn($baseTableName, $columnName)
    {
        $baseTableName = trim((string) $baseTableName);
        $columnName = trim((string) $columnName);
        if ($baseTableName === '' || $columnName === '') {
            return false;
        }

        $cacheKey = $baseTableName . '.' . $columnName;
        if (isset($this->columnExistsCache[$cacheKey])) {
            return (bool) $this->columnExistsCache[$cacheKey];
        }

        $tableName = _DB_PREFIX_ . pSQL($baseTableName);
        $exists = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE()'
            . ' AND TABLE_NAME = \'' . pSQL($tableName) . '\''
            . ' AND COLUMN_NAME = \'' . pSQL($columnName) . '\''
        ) > 0;
        $this->columnExistsCache[$cacheKey] = $exists;

        return $exists;
    }

    private function resolveCourier($carrierReference)
    {
        $carrierReference = (int) $carrierReference;
        if ($carrierReference === (int) \Configuration::get(\Bookurier::CONFIG_BOOKURIER_CARRIER_REFERENCE)) {
            return self::COURIER_BOOKURIER;
        }

        if (
            $carrierReference === (int) \Configuration::get(\Bookurier::CONFIG_CARRIER_REFERENCE)
            && (int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENABLED) === 1
        ) {
            return self::COURIER_SAMEDAY;
        }

        return '';
    }

    private function createBookurierAwb($order)
    {
        $payload = $this->buildBookurierPayload($order);
        $requestPayload = (string) json_encode($payload);

        $response = $this->module->getBookurierClient()->createAwb(
            BookurierCreateAwbRequestDto::fromArray($payload)
        );

        $responsePayload = (string) json_encode($response->getRawResponse());
        $awbCode = trim((string) ($response->getAwbCodes()[0] ?? ''));
        if (!$response->isSuccess() || $awbCode === '') {
            throw new \RuntimeException(
                'Bookurier AWB missing. message=' . (string) $response->getMessage()
                . ' response=' . $responsePayload
            );
        }

        return array(
            'courier' => self::COURIER_BOOKURIER,
            'awb_code' => $awbCode,
            'locker_id' => 0,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
        );
    }

    private function createSamedayAwb($order, $lockerId)
    {
        if ((int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_ENABLED) !== 1) {
            throw new \RuntimeException('SameDay integration is disabled.');
        }

        $lockerId = (int) $lockerId;
        if ($lockerId <= 0) {
            throw new \RuntimeException('No SameDay locker selected for order.');
        }

        $locker = $this->lockerRepository->findActiveLockerById($lockerId);
        if (!is_array($locker)) {
            throw new \RuntimeException('Selected locker is not active.');
        }

        $serviceCandidates = $this->resolveSamedayServiceCandidates($locker);
        $lastException = null;

        foreach ($serviceCandidates as $index => $serviceId) {
            $isLastCandidate = $index === count($serviceCandidates) - 1;
            $payload = $this->buildSamedayPayload($order, $lockerId, $locker, (int) $serviceId);
            $requestPayload = (string) json_encode($payload);

            try {
                $response = $this->module->getSamedayClient()->createAwb(
                    SamedayCreateAwbRequestDto::fromArray($payload)
                );

                $responsePayload = (string) json_encode($response->getRawResponse());
                $awbCode = trim((string) $response->getAwbNumber());
                if (!$response->isSuccess() || $awbCode === '') {
                    throw new \RuntimeException(
                        'SameDay AWB missing. message=' . (string) $response->getMessage()
                        . ' response=' . $responsePayload
                    );
                }

                return array(
                    'courier' => self::COURIER_SAMEDAY,
                    'awb_code' => $awbCode,
                    'locker_id' => $lockerId,
                    'request_payload' => $requestPayload,
                    'response_payload' => $responsePayload,
                );
            } catch (\Exception $exception) {
                $lastException = $exception;

                if ($isLastCandidate || !$this->isSamedayValidationFailure($exception)) {
                    throw new \RuntimeException(
                        $exception->getMessage() . ' | request=' . $requestPayload,
                        (int) $exception->getCode(),
                        $exception
                    );
                }
            }
        }

        if ($lastException instanceof \Exception) {
            throw $lastException;
        }

        throw new \RuntimeException('SameDay AWB could not be generated.');
    }

    private function buildBookurierPayload($order)
    {
        $address = new \Address((int) $order->id_address_delivery);
        if (!\Validate::isLoadedObject($address)) {
            throw new \RuntimeException('Delivery address is missing.');
        }

        $customer = new \Customer((int) $order->id_customer);
        $pickupPoint = (int) \Configuration::get(\Bookurier::CONFIG_DEFAULT_PICKUP_POINT);
        if ($pickupPoint <= 0) {
            throw new \RuntimeException('Bookurier default pickup point is missing.');
        }

        $phone = $this->resolvePhone($address, $order);
        if ($phone === '') {
            throw new \RuntimeException('Recipient phone is missing.');
        }

        $name = trim((string) $address->firstname . ' ' . (string) $address->lastname);
        if ($name === '') {
            $name = trim((string) ($customer->firstname ?? '') . ' ' . (string) ($customer->lastname ?? ''));
        }
        if ($name === '') {
            $name = 'Client';
        }

        $service = (int) \Configuration::get(\Bookurier::CONFIG_DEFAULT_SERVICE);
        if ($service <= 0) {
            $service = 9;
        }

        return array(
            'pickup_point' => (string) $pickupPoint,
            'unq' => 'PS-' . (int) $order->id . '-' . date('YmdHis'),
            'recv' => $name,
            'phone' => $phone,
            'email' => (string) ($customer->email ?? ''),
            'country' => 'Romania',
            'city' => trim((string) $address->city),
            'zip' => trim((string) $address->postcode),
            'district' => $this->resolveDistrict($address),
            'street' => trim((string) $address->address1),
            'no' => trim((string) $address->address2),
            'service' => (string) $service,
            'packs' => '1',
            'weight' => number_format($this->resolveOrderWeight($order), 2, '.', ''),
            'rbs_val' => number_format($this->isCodOrder($order) ? (float) $order->total_paid : 0.0, 2, '.', ''),
            'insurance_val' => '0',
            'ret_doc' => '0',
            'weekend' => '0',
            'unpack' => '0',
            'exchange_pack' => '0',
            'confirmation' => '0',
            'notes' => 'Order #' . (int) $order->id,
            'ref1' => (string) $order->reference,
            'ref2' => (string) $order->id,
        );
    }

    private function buildSamedayPayload($order, $lockerId, array $locker, $serviceId = self::SAMEDAY_LOCKER_SERVICE)
    {
        $address = new \Address((int) $order->id_address_delivery);
        if (!\Validate::isLoadedObject($address)) {
            throw new \RuntimeException('Delivery address is missing.');
        }

        $customer = new \Customer((int) $order->id_customer);
        $phone = $this->resolvePhone($address, $order);
        if ($phone === '') {
            throw new \RuntimeException('Recipient phone is missing.');
        }

        $pickupPoint = (int) \Configuration::get(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINT);
        if ($pickupPoint <= 0) {
            throw new \RuntimeException('SameDay pickup point is missing.');
        }

        $name = trim((string) $address->firstname . ' ' . (string) $address->lastname);
        if ($name === '') {
            $name = trim((string) ($customer->firstname ?? '') . ' ' . (string) ($customer->lastname ?? ''));
        }
        if ($name === '') {
            $name = 'Client';
        }

        return array(
            'pickupPoint' => $pickupPoint,
            'packageType' => 2,
            'packageNumber' => 1,
            'packageWeight' => (float) $this->resolveOrderWeight($order),
            'service' => (int) $serviceId,
            'awbPayment' => 1,
            'cashOnDelivery' => $this->isCodOrder($order) ? (float) $order->total_paid : 0.0,
            'cashOnDeliveryReturns' => 0.0,
            'insuredValue' => 0.0,
            'thirdPartyPickup' => 0,
            'lockerLastMile' => (int) $lockerId,
            'awbRecipient' => array(
                'name' => $name,
                'phoneNumber' => $phone,
                'personType' => 0,
                'postalCode' => trim((string) ($locker['postal_code'] ?? $address->postcode)),
                'cityString' => trim((string) ($locker['city'] ?? $address->city)),
                'countyString' => trim((string) ($locker['county'] ?? $address->city)),
                'address' => trim((string) ($locker['address'] ?? $address->address1)),
                'email' => (string) ($customer->email ?? ''),
            ),
            'observation' => 'Order #' . (int) $order->id,
            'clientInternalReference' => 'PS-' . (int) $order->id . '-' . date('YmdHis'),
            'parcels' => array(
                array('weight' => (float) $this->resolveOrderWeight($order)),
            ),
        );
    }

    private function resolveSamedayServiceCandidates(array $locker)
    {
        $lockerName = strtolower(trim((string) ($locker['name'] ?? '')));
        $isPudo = strpos($lockerName, 'pudo') !== false;

        if ($isPudo) {
            return array(self::SAMEDAY_PUDO_SERVICE, self::SAMEDAY_LOCKER_SERVICE);
        }

        return array(self::SAMEDAY_LOCKER_SERVICE, self::SAMEDAY_PUDO_SERVICE);
    }

    private function isSamedayValidationFailure(\Exception $exception)
    {
        $message = strtolower((string) $exception->getMessage());

        return strpos($message, 'http 400') !== false
            && strpos($message, 'validation failed') !== false;
    }

    private function resolveCarrierReference($order)
    {
        $carrier = new \Carrier((int) $order->id_carrier);

        return \Validate::isLoadedObject($carrier) ? (int) $carrier->id_reference : 0;
    }

    private function resolveLockerId($idOrder, $idCart)
    {
        $lockerId = $this->lockerSelectionRepository->getLockerIdByOrder((int) $idOrder);
        if ($lockerId > 0) {
            return $lockerId;
        }

        return $this->lockerSelectionRepository->getLockerIdByCart((int) $idCart);
    }

    private function resolvePhone(\Address $deliveryAddress, $order)
    {
        $phone = trim((string) ($deliveryAddress->phone_mobile ?: $deliveryAddress->phone));
        if ($phone !== '') {
            return $phone;
        }

        if ((int) $order->id_address_invoice <= 0 || (int) $order->id_address_invoice === (int) $order->id_address_delivery) {
            return '';
        }

        $invoiceAddress = new \Address((int) $order->id_address_invoice);
        if (!\Validate::isLoadedObject($invoiceAddress)) {
            return '';
        }

        return trim((string) ($invoiceAddress->phone_mobile ?: $invoiceAddress->phone));
    }

    private function resolveDistrict(\Address $address)
    {
        if ((int) $address->id_state > 0) {
            $state = new \State((int) $address->id_state);
            if (\Validate::isLoadedObject($state) && trim((string) $state->name) !== '') {
                return trim((string) $state->name);
            }
        }

        $city = trim((string) $address->city);
        if ($city === '') {
            return '';
        }

        $normalized = strtolower(str_replace(array('ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'), array('a', 'a', 'i', 's', 's', 't', 't'), $city));
        $map = array(
            'cluj-napoca' => 'Cluj',
            'cluj napoca' => 'Cluj',
            'bucuresti' => 'Bucuresti',
            'sector 1' => 'Bucuresti',
            'sector 2' => 'Bucuresti',
            'sector 3' => 'Bucuresti',
            'sector 4' => 'Bucuresti',
            'sector 5' => 'Bucuresti',
            'sector 6' => 'Bucuresti',
            'targu mures' => 'Mures',
            'tirgu mures' => 'Mures',
            'baia mare' => 'Maramures',
            'piatra neamt' => 'Neamt',
        );

        if (isset($map[$normalized])) {
            return $map[$normalized];
        }

        if (strpos($city, '-') !== false) {
            $parts = explode('-', $city);
            if (!empty($parts[0]) && trim((string) $parts[0]) !== '') {
                return trim((string) $parts[0]);
            }
        }

        return $city;
    }

    private function resolveOrderWeight($order)
    {
        if (method_exists($order, 'getTotalWeight')) {
            $weight = (float) $order->getTotalWeight();
            if ($weight > 0) {
                return $weight;
            }
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT product_weight, product_quantity FROM `' . _DB_PREFIX_ . 'order_detail` WHERE id_order = ' . (int) $order->id
        );

        $weight = 0.0;
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $weight += (float) ($row['product_weight'] ?? 0.0) * (int) ($row['product_quantity'] ?? 1);
            }
        }

        return $weight > 0 ? $weight : 1.0;
    }

    private function isCodOrder($order)
    {
        $module = strtolower(trim((string) $order->module));

        return in_array($module, array('ps_cashondelivery', 'cashondelivery', 'cod', 'ramburs'), true);
    }
}
