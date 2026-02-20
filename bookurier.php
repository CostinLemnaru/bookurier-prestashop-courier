<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

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
    const CONFIG_BOOKURIER_CARRIER_REFERENCE = 'BOOKURIER_BOOKURIER_CARRIER_REFERENCE';
    const CONFIG_CARRIER_REFERENCE = 'BOOKURIER_CARRIER_REFERENCE';

    const ACTION_SUBMIT_CONFIG = 'submitBookurierConfig';
    const ACTION_SYNC_LOCKERS = 'submitBookurierSyncLockers';

    private $logger;
    private $bookurierClient;
    private $samedayClient;

    public function __construct()
    {
        $this->name = 'bookurier';
        $this->tab = 'shipping_logistics';
        $this->version = '0.1.0';
        $this->author = 'Bookurier';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.8.0', 'max' => '9.99.99');

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
        return (new ConfigFormManager($this))->handle();
    }

    public function hookDisplayBackOfficeHeader($params)
    {
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
            $environment = strtolower((string) Configuration::get(self::CONFIG_SAMEDAY_ENV)) === 'prod' ? 'prod' : 'demo';
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
}
