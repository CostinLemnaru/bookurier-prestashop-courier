<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Bookurier\Client\Bookurier\BookurierClient;
use Bookurier\Client\Bookurier\BookurierClientInterface;
use Bookurier\Client\Sameday\SamedayClient;
use Bookurier\Client\Sameday\SamedayClientInterface;
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
            && Configuration::updateValue(self::CONFIG_SAMEDAY_ENABLED, '0');
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
        ) as $configKey) {
            Configuration::deleteByName($configKey);
        }

        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit(self::ACTION_SUBMIT_CONFIG)) {
            $output .= $this->processConfigForm();
        }

        return $output . $this->renderConfigForm();
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        return '';
    }

    public function hookActionAdminControllerSetMedia($params)
    {
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return false;
    }

    public function getOrderShippingCostExternal($params)
    {
        return false;
    }

    private function processConfigForm()
    {
        $apiUser = trim((string) Tools::getValue(self::CONFIG_API_USER, Configuration::get(self::CONFIG_API_USER)));
        $apiPasswordInput = (string) Tools::getValue(self::CONFIG_API_PASSWORD, '');
        $apiPassword = $apiPasswordInput !== '' ? $apiPasswordInput : (string) Configuration::get(self::CONFIG_API_PASSWORD);
        $apiKey = trim((string) Tools::getValue(self::CONFIG_API_KEY, Configuration::get(self::CONFIG_API_KEY)));
        $defaultPickupPoint = (int) Tools::getValue(self::CONFIG_DEFAULT_PICKUP_POINT, Configuration::get(self::CONFIG_DEFAULT_PICKUP_POINT));
        $defaultService = (int) Tools::getValue(self::CONFIG_DEFAULT_SERVICE, Configuration::get(self::CONFIG_DEFAULT_SERVICE));

        $samedayEnabled = (int) Tools::getValue(self::CONFIG_SAMEDAY_ENABLED, Configuration::get(self::CONFIG_SAMEDAY_ENABLED));
        $samedayUser = trim((string) Tools::getValue(self::CONFIG_SAMEDAY_API_USERNAME, Configuration::get(self::CONFIG_SAMEDAY_API_USERNAME)));
        $samedayPasswordInput = (string) Tools::getValue(self::CONFIG_SAMEDAY_API_PASSWORD, '');
        $samedayPassword = $samedayPasswordInput !== '' ? $samedayPasswordInput : (string) Configuration::get(self::CONFIG_SAMEDAY_API_PASSWORD);
        $samedayEnv = $this->normalizeSamedayEnvironment((string) Tools::getValue(self::CONFIG_SAMEDAY_ENV, Configuration::get(self::CONFIG_SAMEDAY_ENV)));

        $errors = array();
        if ($apiUser === '') {
            $errors[] = $this->l('Bookurier API username is required.');
        }
        if ($apiPassword === '') {
            $errors[] = $this->l('Bookurier API password is required.');
        }
        if ($defaultPickupPoint <= 0) {
            $errors[] = $this->l('Bookurier default pickup point must be a positive integer.');
        }
        if (!$this->isValidBookurierService($defaultService)) {
            $errors[] = $this->l('Bookurier default service is invalid.');
        }
        if ($samedayEnabled === 1) {
            if ($samedayUser === '') {
                $errors[] = $this->l('SameDay API username is required when SameDay is enabled.');
            }
            if ($samedayPassword === '') {
                $errors[] = $this->l('SameDay API password is required when SameDay is enabled.');
            }
        }
        if (!empty($errors)) {
            return $this->displayError(implode('<br>', $errors));
        }

        Configuration::updateValue(self::CONFIG_API_USER, $apiUser);
        Configuration::updateValue(self::CONFIG_API_KEY, $apiKey);
        Configuration::updateValue(self::CONFIG_DEFAULT_PICKUP_POINT, (string) $defaultPickupPoint);
        Configuration::updateValue(self::CONFIG_DEFAULT_SERVICE, (string) $defaultService);
        Configuration::updateValue(self::CONFIG_SAMEDAY_ENABLED, (string) $samedayEnabled);
        Configuration::updateValue(self::CONFIG_SAMEDAY_API_USERNAME, $samedayUser);
        Configuration::updateValue(self::CONFIG_SAMEDAY_ENV, $samedayEnv);

        if ($apiPasswordInput !== '') {
            Configuration::updateValue(self::CONFIG_API_PASSWORD, $apiPasswordInput);
        }
        if ($samedayPasswordInput !== '') {
            Configuration::updateValue(self::CONFIG_SAMEDAY_API_PASSWORD, $samedayPasswordInput);
        }

        $this->resetClients();

        return $this->displayConfirmation($this->l('Settings saved successfully.'));
    }

    private function renderConfigForm()
    {
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->title = $this->displayName;
        $helper->submit_action = self::ACTION_SUBMIT_CONFIG;
        $helper->fields_value = $this->getConfigFormValues();

        return $helper->generateForm(array($this->getConfigFormDefinition()));
    }

    private function getConfigFormDefinition()
    {
        return array(
            'form' => array(
                'legend' => array('title' => $this->l('Bookurier Courier Settings'), 'icon' => 'icon-cogs'),
                'input' => array(
                    array('type' => 'html', 'name' => 'bookurier_api_separator', 'html_content' => '<h3>' . $this->l('Bookurier API') . '</h3>'),
                    array('type' => 'text', 'label' => $this->l('Bookurier API Username'), 'name' => self::CONFIG_API_USER),
                    array(
                        'type' => 'password',
                        'label' => $this->l('Bookurier API Password'),
                        'name' => self::CONFIG_API_PASSWORD,
                        'desc' => $this->l('Leave empty to keep current password.'),
                    ),
                    array('type' => 'text', 'label' => $this->l('Bookurier API Key (Tracking)'), 'name' => self::CONFIG_API_KEY),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Bookurier Default Pickup Point'),
                        'name' => self::CONFIG_DEFAULT_PICKUP_POINT,
                        'class' => 'fixed-width-md',
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Bookurier Default Service'),
                        'name' => self::CONFIG_DEFAULT_SERVICE,
                        'options' => array('query' => $this->getBookurierServiceOptions(), 'id' => 'id', 'name' => 'name'),
                    ),
                    array('type' => 'html', 'name' => 'sameday_api_separator', 'html_content' => '<hr><h3>' . $this->l('SameDay API') . '</h3>'),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enable SameDay'),
                        'name' => self::CONFIG_SAMEDAY_ENABLED,
                        'is_bool' => true,
                        'values' => array(
                            array('id' => 'sameday_on', 'value' => 1, 'label' => $this->l('Yes')),
                            array('id' => 'sameday_off', 'value' => 0, 'label' => $this->l('No')),
                        ),
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('SameDay Environment'),
                        'name' => self::CONFIG_SAMEDAY_ENV,
                        'options' => array(
                            'query' => array(
                                array('id' => 'demo', 'name' => $this->l('Demo')),
                                array('id' => 'prod', 'name' => $this->l('Production')),
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
                    ),
                    array('type' => 'text', 'label' => $this->l('SameDay API Username'), 'name' => self::CONFIG_SAMEDAY_API_USERNAME),
                    array(
                        'type' => 'password',
                        'label' => $this->l('SameDay API Password'),
                        'name' => self::CONFIG_SAMEDAY_API_PASSWORD,
                        'desc' => $this->l('Leave empty to keep current password.'),
                    ),
                ),
                'submit' => array('title' => $this->l('Save')),
            ),
        );
    }

    private function getConfigFormValues()
    {
        return array(
            self::CONFIG_API_USER => (string) Tools::getValue(self::CONFIG_API_USER, (string) Configuration::get(self::CONFIG_API_USER)),
            self::CONFIG_API_PASSWORD => '',
            self::CONFIG_API_KEY => (string) Tools::getValue(self::CONFIG_API_KEY, (string) Configuration::get(self::CONFIG_API_KEY)),
            self::CONFIG_DEFAULT_PICKUP_POINT => (string) Tools::getValue(
                self::CONFIG_DEFAULT_PICKUP_POINT,
                (string) Configuration::get(self::CONFIG_DEFAULT_PICKUP_POINT)
            ),
            self::CONFIG_DEFAULT_SERVICE => (int) Tools::getValue(
                self::CONFIG_DEFAULT_SERVICE,
                (int) (Configuration::get(self::CONFIG_DEFAULT_SERVICE) ?: 9)
            ),
            self::CONFIG_SAMEDAY_ENABLED => (int) Tools::getValue(
                self::CONFIG_SAMEDAY_ENABLED,
                (int) (Configuration::get(self::CONFIG_SAMEDAY_ENABLED) ?: 0)
            ),
            self::CONFIG_SAMEDAY_ENV => (string) Tools::getValue(
                self::CONFIG_SAMEDAY_ENV,
                (string) (Configuration::get(self::CONFIG_SAMEDAY_ENV) ?: 'demo')
            ),
            self::CONFIG_SAMEDAY_API_USERNAME => (string) Tools::getValue(
                self::CONFIG_SAMEDAY_API_USERNAME,
                (string) Configuration::get(self::CONFIG_SAMEDAY_API_USERNAME)
            ),
            self::CONFIG_SAMEDAY_API_PASSWORD => '',
        );
    }

    private function normalizeSamedayEnvironment($environment)
    {
        return strtolower((string) $environment) === 'prod' ? 'prod' : 'demo';
    }

    private function isValidBookurierService($service)
    {
        foreach ($this->getBookurierServiceOptions() as $option) {
            if ((int) $option['id'] === (int) $service) {
                return true;
            }
        }

        return false;
    }

    private function getBookurierServiceOptions()
    {
        return array(
            array('id' => 1, 'name' => 'Bucuresti 24h (1)'),
            array('id' => 3, 'name' => 'Metropolitan (3)'),
            array('id' => 5, 'name' => 'Ilfov Extins (5)'),
            array('id' => 7, 'name' => 'Bucuresti Today (7)'),
            array('id' => 8, 'name' => 'National Economic (8)'),
            array('id' => 9, 'name' => 'National 24 (9)'),
            array('id' => 11, 'name' => 'National Premium (11)'),
        );
    }

    private function resetClients()
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
            $this->samedayClient = new SamedayClient(
                (string) Configuration::get(self::CONFIG_SAMEDAY_API_USERNAME),
                (string) Configuration::get(self::CONFIG_SAMEDAY_API_PASSWORD),
                $this->normalizeSamedayEnvironment((string) Configuration::get(self::CONFIG_SAMEDAY_ENV)),
                null,
                $this->getLogger()
            );
        }

        return $this->samedayClient;
    }
}
