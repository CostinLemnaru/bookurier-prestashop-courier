<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Bookurier\Awb\AutoAwbService;
use Bookurier\Awb\AwbRepository;
use Bookurier\Admin\Config\ConfigFormManager;
use Bookurier\Admin\Config\SamedayLockerRepository;
use Bookurier\Client\Bookurier\BookurierClient;
use Bookurier\Client\Sameday\SamedayClient;
use Bookurier\Checkout\SamedayLockerCheckoutHelper;
use Bookurier\Checkout\SamedayLockerSelectionRepository;
use Bookurier\Install\Installer;
use Bookurier\Install\Uninstaller;
use Bookurier\Logging\LoggerFactory;

class Bookurier extends CarrierModule
{
    const CONFIG_LOG_LEVEL = 'BOOKURIER_LOG_LEVEL';
    const CONFIG_SAMEDAY_ENV = 'BOOKURIER_SAMEDAY_ENV';
    const CONFIG_API_USER = 'BOOKURIER_API_USER';
    const CONFIG_API_PASSWORD = 'BOOKURIER_API_PASSWORD';
    const CONFIG_API_KEY = 'BOOKURIER_API_KEY';
    const CONFIG_DEFAULT_PICKUP_POINT = 'BOOKURIER_DEFAULT_PICKUP_POINT';
    const CONFIG_DEFAULT_SERVICE = 'BOOKURIER_DEFAULT_SERVICE';
    const CONFIG_SAMEDAY_ENABLED = 'BOOKURIER_SAMEDAY_ENABLED';
    const CONFIG_SAMEDAY_API_USERNAME = 'BOOKURIER_SAMEDAY_API_USERNAME';
    const CONFIG_SAMEDAY_API_PASSWORD = 'BOOKURIER_SAMEDAY_API_PASSWORD';
    const CONFIG_SAMEDAY_PICKUP_POINT = 'BOOKURIER_SAMEDAY_PICKUP_POINT';
    const CONFIG_SAMEDAY_PICKUP_POINTS_CACHE = 'BOOKURIER_SAMEDAY_PICKUP_POINTS_CACHE';
    const CONFIG_SAMEDAY_SERVICES_CACHE = 'BOOKURIER_SAMEDAY_SERVICES_CACHE';
    const CONFIG_SAMEDAY_PACKAGE_TYPE = 'BOOKURIER_SAMEDAY_PACKAGE_TYPE';
    const CONFIG_BOOKURIER_CARRIER_REFERENCE = 'BOOKURIER_BOOKURIER_CARRIER_REFERENCE';
    const CONFIG_CARRIER_REFERENCE = 'BOOKURIER_CARRIER_REFERENCE';
    const CONFIG_AUTO_AWB_ENABLED = 'BOOKURIER_AUTO_AWB_ENABLED';
    const CONFIG_AUTO_AWB_ALLOWED_STATUSES = 'BOOKURIER_AUTO_AWB_ALLOWED_STATUSES';

    const ACTION_SUBMIT_CONFIG = 'submitBookurierConfig';
    const ACTION_SYNC_LOCKERS = 'submitBookurierSyncLockers';

    private $logger;
    private $bookurierClient;
    private $samedayClient;
    private $autoAwbService;
    private $renderedAdminAwbOrderId;

    public function __construct()
    {
        $this->name = 'bookurier';
        $this->tab = 'shipping_logistics';
        $this->version = '0.1.0';
        $this->author = 'Bookurier';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.8.0', 'max' => '9.99.99');
        $this->renderedAdminAwbOrderId = 0;

        parent::__construct();

        $this->displayName = $this->l('Bookurier Courier');
        $this->description = $this->l('Bookurier + SameDay integration settings.');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        return (new Installer($this))->install();
    }

    public function uninstall()
    {
        if (!(new Uninstaller())->uninstall()) {
            return false;
        }

        return parent::uninstall();
    }

    public function getContent()
    {
        $this->ensureRequiredHooks();

        return (new ConfigFormManager($this))->handle();
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->ensureRequiredHooks();

        return '';
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        if ((string) Tools::getValue('configure') !== $this->name) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        if (!$this->isSamedayCheckoutEnabled()) {
            return;
        }

        $phpSelf = isset($this->context->controller->php_self) ? (string) $this->context->controller->php_self : '';
        if (!in_array($phpSelf, array('order', 'checkout'), true)) {
            return;
        }

        $this->context->controller->registerStylesheet(
            'bookurier-tomselect',
            'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css',
            array('server' => 'remote', 'priority' => 150)
        );

        $this->context->controller->registerJavascript(
            'bookurier-tomselect',
            'https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js',
            array('server' => 'remote', 'position' => 'bottom', 'priority' => 150)
        );

        $this->context->controller->addJS($this->_path . 'views/js/checkout-locker.js');
    }

    public function hookDisplayCarrierExtraContent($params)
    {
        if (!$this->isSamedayCheckoutEnabled()) {
            return '';
        }

        $checkoutHelper = new SamedayLockerCheckoutHelper();
        if (!$checkoutHelper->isLockerCarrierSelected($params, $this->context, (int) Configuration::get(self::CONFIG_CARRIER_REFERENCE))) {
            return '';
        }

        $idCart = (int) $this->context->cart->id;
        if ($idCart <= 0) {
            return '';
        }

        $lockerRepository = new SamedayLockerRepository();
        $lockers = $lockerRepository->getActiveForCheckout();
        if (empty($lockers)) {
            return '<p class="alert alert-warning">' . $this->l('No SameDay lockers available. Please contact the store administrator.') . '</p>';
        }

        $selectionRepository = new SamedayLockerSelectionRepository();
        $selectedLockerId = $selectionRepository->getLockerIdByCart($idCart);
        if ($selectedLockerId <= 0) {
            $selectedLockerId = $checkoutHelper->resolvePreferredLockerByDeliveryAddress($this->context, $lockerRepository, $lockers);
            if ($selectedLockerId > 0) {
                $selectionRepository->saveForCart($idCart, $selectedLockerId);
            }
        }
        $saveUrl = $this->context->link->getModuleLink($this->name, 'locker');

        $this->context->smarty->assign(array(
            'bookurier_locker_save_url' => $saveUrl,
            'bookurier_lockers' => $lockers,
            'bookurier_selected_locker_id' => $selectedLockerId,
        ));

        return $this->fetch('module:' . $this->name . '/views/templates/hook/carrier_extra.tpl');
    }

    public function hookActionValidateOrder($params)
    {
        if (!isset($params['cart']) || !isset($params['order'])) {
            return;
        }

        $cart = $params['cart'];
        $order = $params['order'];
        $idCart = (int) (is_object($cart) ? $cart->id : 0);
        $idOrder = (int) (is_object($order) ? $order->id : 0);
        if ($idCart <= 0 || $idOrder <= 0) {
            return;
        }

        $selectionRepository = new SamedayLockerSelectionRepository();
        $selectionRepository->assignOrder($idCart, $idOrder);

        $this->maybeGenerateAutoAwb($order, $idCart);
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $order = $this->resolveOrderFromStatusParams($params);
        if ($order === null) {
            return;
        }

        $this->maybeGenerateAutoAwb($order, (int) $order->id_cart);
    }

    public function hookDisplayAdminOrderMain($params)
    {
        return $this->renderAdminAwbLink($params);
    }

    public function hookDisplayAdminOrder($params)
    {
        return $this->renderAdminAwbLink($params);
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return 0.0;
    }

    public function getOrderShippingCostExternal($params)
    {
        return 0.0;
    }

    public function resetClients()
    {
        $this->bookurierClient = null;
        $this->samedayClient = null;
        $this->autoAwbService = null;
    }

    public function getLogger()
    {
        if ($this->logger === null) {
            $level = (string) Configuration::get(self::CONFIG_LOG_LEVEL);
            $this->logger = LoggerFactory::create($this->name, $level !== '' ? $level : 'info');
        }

        return $this->logger;
    }

    public function getBookurierClient()
    {
        if ($this->bookurierClient === null) {
            $this->bookurierClient = new BookurierClient(
                (string) Configuration::get(self::CONFIG_API_USER),
                (string) Configuration::get(self::CONFIG_API_PASSWORD),
                null,
                $this->getLogger()
            );
        }

        return $this->bookurierClient;
    }

    public function getSamedayClient()
    {
        if ($this->samedayClient === null) {
            $environment = strtolower((string) Configuration::get(self::CONFIG_SAMEDAY_ENV)) === 'demo' ? 'demo' : 'prod';
            $this->samedayClient = new SamedayClient(
                (string) Configuration::get(self::CONFIG_SAMEDAY_API_USERNAME),
                (string) Configuration::get(self::CONFIG_SAMEDAY_API_PASSWORD),
                $environment,
                null,
                $this->getLogger()
            );
        }

        return $this->samedayClient;
    }

    private function isSamedayCheckoutEnabled()
    {
        return (int) Configuration::get(self::CONFIG_SAMEDAY_ENABLED) === 1;
    }

    public function isAutoAwbEnabled()
    {
        $value = Configuration::get(self::CONFIG_AUTO_AWB_ENABLED);
        if ($value === false || $value === null || $value === '') {
            return true;
        }

        return (int) $value === 1;
    }

    public function getAutoAwbAllowedStatusIds()
    {
        $statusIds = $this->parseStatusIds((string) Configuration::get(self::CONFIG_AUTO_AWB_ALLOWED_STATUSES));
        if (!empty($statusIds)) {
            return $statusIds;
        }

        return $this->getDefaultAutoAwbStatusIds();
    }

    public function generateAwbForOrderId($idOrder)
    {
        $order = new Order((int) $idOrder);
        if (!\Validate::isLoadedObject($order)) {
            throw new \RuntimeException('Order not found.');
        }

        $carrierReference = $this->resolveCarrierReference($order);
        if (!$this->isManagedCarrierReference($carrierReference)) {
            throw new \RuntimeException('Order carrier is not managed by Bookurier module.');
        }

        return $this->getAutoAwbService()->generateForOrder($order, (int) $order->id_cart, $carrierReference);
    }

    private function getAutoAwbService()
    {
        if ($this->autoAwbService === null) {
            $this->autoAwbService = new AutoAwbService($this);
        }

        return $this->autoAwbService;
    }

    private function maybeGenerateAutoAwb($order, $idCart)
    {
        if (!is_object($order) || !\Validate::isLoadedObject($order)) {
            return;
        }

        $carrierReference = $this->resolveCarrierReference($order);
        if (!$this->isManagedCarrierReference($carrierReference)) {
            return;
        }

        if (!$this->canAutoGenerateAwbForOrder($order)) {
            return;
        }

        try {
            $this->getAutoAwbService()->generateForOrder($order, (int) $idCart, $carrierReference);
        } catch (\Exception $exception) {
            $this->getLogger()->error('Automatic AWB generation failed.', array(
                'id_order' => (int) $order->id,
                'id_cart' => (int) $idCart,
                'carrier_reference' => $carrierReference,
                'message' => $exception->getMessage(),
            ));
        }
    }

    private function resolveOrderFromStatusParams($params)
    {
        if (isset($params['order']) && is_object($params['order']) && \Validate::isLoadedObject($params['order'])) {
            return $params['order'];
        }

        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return null;
        }

        $order = new Order($idOrder);

        return \Validate::isLoadedObject($order) ? $order : null;
    }

    private function resolveCarrierReference($order)
    {
        $idCarrier = (int) (is_object($order) ? $order->id_carrier : 0);
        if ($idCarrier <= 0) {
            return 0;
        }

        $carrier = new Carrier($idCarrier);
        if (!\Validate::isLoadedObject($carrier)) {
            return 0;
        }

        return (int) $carrier->id_reference;
    }

    private function isManagedCarrierReference($carrierReference)
    {
        $carrierReference = (int) $carrierReference;
        if ($carrierReference <= 0) {
            return false;
        }

        return in_array($carrierReference, array(
            (int) Configuration::get(self::CONFIG_BOOKURIER_CARRIER_REFERENCE),
            (int) Configuration::get(self::CONFIG_CARRIER_REFERENCE),
        ), true);
    }

    private function canAutoGenerateAwbForOrder($order)
    {
        if (!$this->isAutoAwbEnabled()) {
            return false;
        }

        $allowedStatusIds = $this->getAutoAwbAllowedStatusIds();
        if (empty($allowedStatusIds)) {
            return false;
        }

        $currentState = (int) (is_object($order) ? $order->current_state : 0);
        if ($currentState <= 0) {
            return false;
        }

        return in_array($currentState, $allowedStatusIds, true);
    }

    private function renderAdminAwbLink($params)
    {
        $idOrder = $this->resolveOrderIdFromHookParams($params);
        if ($idOrder <= 0) {
            return '';
        }

        if ((int) $this->renderedAdminAwbOrderId === $idOrder) {
            return '';
        }

        $order = new Order($idOrder);
        if (!\Validate::isLoadedObject($order)) {
            return '';
        }

        $carrierReference = $this->resolveCarrierReference($order);
        if (!$this->isManagedCarrierReference($carrierReference)) {
            return '';
        }

        $awb = (new AwbRepository())->findByOrderId($idOrder);
        $hasAwb = is_array($awb) && trim((string) ($awb['awb_code'] ?? '')) !== '';
        $awbStatus = is_array($awb) ? $this->resolveAwbStatusLabel($awb) : '';

        $this->renderedAdminAwbOrderId = $idOrder;
        $manualGenerateUrl = (!$this->isAutoAwbEnabled() && !$hasAwb) ? $this->buildAwbGenerateUrl($idOrder) : '';

        $this->context->smarty->assign(array(
            'bookurier_awb_title' => $this->l('Bookurier AWB'),
            'bookurier_awb_code_label' => $this->l('AWB'),
            'bookurier_awb_code' => $hasAwb ? (string) $awb['awb_code'] : '',
            'bookurier_awb_status_label' => $this->l('Status'),
            'bookurier_awb_status' => $awbStatus,
            'bookurier_awb_empty_label' => $this->l('AWB not generated yet.'),
            'bookurier_awb_download_label' => $this->l('Download AWB PDF'),
            'bookurier_awb_download_url' => $hasAwb ? $this->buildAwbDownloadUrl($idOrder) : '',
            'bookurier_awb_generate_label' => $this->l('Generate AWB'),
            'bookurier_awb_generating_label' => $this->l('Generating...'),
            'bookurier_awb_generate_url' => $manualGenerateUrl,
            'bookurier_awb_order_id' => (int) $idOrder,
        ));

        return $this->fetch('module:' . $this->name . '/views/templates/hook/admin_order_awb.tpl');
    }

    public function validateAwbDownloadToken($idOrder, $token)
    {
        return hash_equals($this->buildAwbDownloadToken($idOrder), (string) $token);
    }

    private function resolveOrderIdFromHookParams($params)
    {
        if (isset($params['id_order'])) {
            return (int) $params['id_order'];
        }

        if (isset($params['order']) && is_object($params['order']) && \Validate::isLoadedObject($params['order'])) {
            return (int) $params['order']->id;
        }

        return (int) Tools::getValue('id_order');
    }

    public function validateAwbGenerateToken($idOrder, $token)
    {
        return hash_equals($this->buildAwbGenerateToken($idOrder), (string) $token);
    }

    private function buildAwbDownloadUrl($idOrder)
    {
        return $this->context->link->getModuleLink($this->name, 'awbpdf', array(
            'id_order' => (int) $idOrder,
            'token' => $this->buildAwbDownloadToken($idOrder),
        ));
    }

    private function buildAwbGenerateUrl($idOrder)
    {
        return $this->context->link->getModuleLink($this->name, 'generateawb', array(
            'id_order' => (int) $idOrder,
            'token' => $this->buildAwbGenerateToken($idOrder),
        ));
    }

    private function buildAwbDownloadToken($idOrder)
    {
        return hash_hmac('sha256', 'download|' . (string) (int) $idOrder, _COOKIE_KEY_);
    }

    private function buildAwbGenerateToken($idOrder)
    {
        return hash_hmac('sha256', 'generate|' . (string) (int) $idOrder, _COOKIE_KEY_);
    }

    private function resolveAwbStatusLabel(array $awb)
    {
        $storedStatus = $this->formatStoredAwbStatus((string) ($awb['status'] ?? ''));
        $courier = strtolower(trim((string) ($awb['courier'] ?? '')));
        $awbCode = trim((string) ($awb['awb_code'] ?? ''));
        if ($awbCode === '') {
            return $storedStatus;
        }

        try {
            if ($courier === 'sameday') {
                $payload = $this->getSamedayClient()->getAwbStatus($awbCode);
                $expeditionStatus = isset($payload['expeditionStatus']) && is_array($payload['expeditionStatus'])
                    ? $payload['expeditionStatus']
                    : array();
                $status = trim((string) ($expeditionStatus['statusLabel'] ?? $expeditionStatus['status'] ?? ''));
                if ($status !== '') {
                    return $status;
                }
            }

            if ($courier === 'bookurier') {
                $apiKey = trim((string) Configuration::get(self::CONFIG_API_KEY));
                if ($apiKey !== '') {
                    $payload = $this->getBookurierClient()->getAwbHistory($apiKey, $awbCode);
                    $status = $this->normalizeAwbStatusText((string) ($payload['status_name'] ?? ''));
                    if ($status === '' && isset($payload['data']) && is_array($payload['data']) && !empty($payload['data'])) {
                        $historyPayload = $payload['data'];
                        if (isset($historyPayload['status_name']) || isset($historyPayload['status']) || isset($historyPayload['statusLabel'])) {
                            $status = $this->normalizeAwbStatusText((string) (
                                $historyPayload['status_name']
                                ?? $historyPayload['status']
                                ?? $historyPayload['statusLabel']
                                ?? ''
                            ));
                        } else {
                            $lastStatus = end($historyPayload);
                            if (is_array($lastStatus)) {
                                $status = $this->normalizeAwbStatusText((string) (
                                    $lastStatus['status_name']
                                    ?? $lastStatus['status']
                                    ?? $lastStatus['statusLabel']
                                    ?? ''
                                ));
                            }
                        }
                    }
                    if ($status !== '') {
                        return $status;
                    }
                }
            }
        } catch (\Exception $exception) {
            $this->getLogger()->warning('Could not fetch AWB status for BO panel.', array(
                'id_order' => (int) ($awb['id_order'] ?? 0),
                'courier' => $courier,
                'awb_code' => $awbCode,
                'message' => $exception->getMessage(),
            ));
        }

        return $storedStatus;
    }

    private function formatStoredAwbStatus($status)
    {
        $status = strtolower(trim((string) $status));
        if ($status === 'created') {
            return $this->l('Created');
        }

        if ($status === 'error') {
            return $this->l('Error');
        }

        return $status !== '' ? ucfirst($status) : '';
    }

    private function normalizeAwbStatusText($status)
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $status));
    }

    private function parseStatusIds($rawValue)
    {
        $rawValue = trim((string) $rawValue);
        if ($rawValue === '') {
            return array();
        }

        $parts = explode(',', $rawValue);
        $statusIds = array();
        foreach ($parts as $part) {
            $statusId = (int) trim((string) $part);
            if ($statusId > 0) {
                $statusIds[] = $statusId;
            }
        }

        $statusIds = array_values(array_unique($statusIds));
        sort($statusIds);

        return $statusIds;
    }

    private function getDefaultAutoAwbStatusIds()
    {
        $statusIds = array();
        foreach (array('PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING') as $configKey) {
            $statusId = (int) Configuration::get($configKey);
            if ($statusId > 0) {
                $statusIds[] = $statusId;
            }
        }

        $statusIds = array_values(array_unique($statusIds));
        sort($statusIds);

        return $statusIds;
    }

    private function ensureRequiredHooks()
    {
        if (!method_exists($this, 'isRegisteredInHook')) {
            return;
        }

        foreach (array('actionValidateOrder', 'actionOrderStatusPostUpdate', 'displayAdminOrderMain', 'displayAdminOrder') as $hookName) {
            if (!$this->isRegisteredInHook($hookName)) {
                $this->registerHook($hookName);
            }
        }
    }
}
