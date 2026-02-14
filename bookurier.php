<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Bookurier\Admin\Config\ConfigFormManager;
use Bookurier\Client\Bookurier\BookurierClient;
use Bookurier\Client\Sameday\SamedayClient;
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

    const ACTION_SUBMIT_CONFIG = 'submitBookurierConfig';

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
        return parent::install()
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionAdminControllerSetMedia')
            && Configuration::updateValue(self::CONFIG_LOG_LEVEL, 'info')
            && Configuration::updateValue(self::CONFIG_SAMEDAY_ENV, 'demo')
            && Configuration::updateValue(self::CONFIG_DEFAULT_SERVICE, '9')
            && Configuration::updateValue(self::CONFIG_SAMEDAY_ENABLED, '0')
            && Configuration::updateValue(self::CONFIG_SAMEDAY_PICKUP_POINT, '0')
            && Configuration::updateValue(self::CONFIG_SAMEDAY_PICKUP_POINTS_CACHE, '[]');
    }

    public function uninstall()
    {
        foreach (array(
            self::CONFIG_LOG_LEVEL,
            self::CONFIG_SAMEDAY_ENV,
            self::CONFIG_API_USER,
            self::CONFIG_API_PASSWORD,
            self::CONFIG_API_KEY,
            self::CONFIG_DEFAULT_PICKUP_POINT,
            self::CONFIG_DEFAULT_SERVICE,
            self::CONFIG_SAMEDAY_ENABLED,
            self::CONFIG_SAMEDAY_API_USERNAME,
            self::CONFIG_SAMEDAY_API_PASSWORD,
            self::CONFIG_SAMEDAY_PICKUP_POINT,
            self::CONFIG_SAMEDAY_PICKUP_POINTS_CACHE,
        ) as $configKey) {
            Configuration::deleteByName($configKey);
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

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return false;
    }

    public function getOrderShippingCostExternal($params)
    {
        return false;
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
}
