<?php
/**
 * Bookurier Courier Module - development scaffold.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Bookurier\Client\Bookurier\BookurierClient;
use Bookurier\Client\Bookurier\BookurierClientInterface;
use Bookurier\Client\Sameday\SamedayClient;
use Bookurier\Client\Sameday\SamedayClientInterface;
use Bookurier\Logging\LoggerFactory;
use Bookurier\Logging\LoggerInterface;

class Bookurier extends CarrierModule
{
    /**
     * @var string
     */
    const CONFIG_LOG_LEVEL = 'BOOKURIER_LOG_LEVEL';

    /**
     * @var string
     */
    const CONFIG_SAMEDAY_ENV = 'BOOKURIER_SAMEDAY_ENV';

    /**
     * @var string
     */
    const CONFIG_API_USER = 'BOOKURIER_API_USER';

    /**
     * @var string
     */
    const CONFIG_API_PASSWORD = 'BOOKURIER_API_PASSWORD';

    /**
     * @var string
     */
    const CONFIG_SAMEDAY_API_USERNAME = 'BOOKURIER_SAMEDAY_API_USERNAME';

    /**
     * @var string
     */
    const CONFIG_SAMEDAY_API_PASSWORD = 'BOOKURIER_SAMEDAY_API_PASSWORD';

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @var BookurierClientInterface|null
     */
    private $bookurierClient;

    /**
     * @var SamedayClientInterface|null
     */
    private $samedayClient;

    public function __construct()
    {
        $this->name = 'bookurier';
        $this->tab = 'shipping_logistics';
        $this->version = '0.1.0';
        $this->author = 'Bookurier';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.8.0',
            'max' => '9.99.99',
        );

        parent::__construct();

        $this->displayName = $this->l('Bookurier Courier');
        $this->description = $this->l('Base scaffold for Bookurier courier integration.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionAdminControllerSetMedia')
            && Configuration::updateValue(self::CONFIG_LOG_LEVEL, 'info')
            && Configuration::updateValue(self::CONFIG_SAMEDAY_ENV, 'demo');
    }

    public function uninstall()
    {
        Configuration::deleteByName(self::CONFIG_LOG_LEVEL);
        Configuration::deleteByName(self::CONFIG_SAMEDAY_ENV);

        return parent::uninstall();
    }

    public function getContent()
    {
        return $this->displayConfirmation(
            $this->l('Bookurier scaffold installed. Start implementing features from here.')
        );
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        return '';
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        // Kept intentionally empty for scaffold compatibility.
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        return false;
    }

    public function getOrderShippingCostExternal($params)
    {
        return false;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if ($this->logger === null) {
            $level = (string) Configuration::get(self::CONFIG_LOG_LEVEL);
            if ($level === '') {
                $level = 'info';
            }

            $this->logger = LoggerFactory::create($this->name, $level);
        }

        return $this->logger;
    }

    /**
     * @return BookurierClientInterface
     */
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

    /**
     * @return SamedayClientInterface
     */
    public function getSamedayClient()
    {
        if ($this->samedayClient === null) {
            $this->samedayClient = new SamedayClient(
                (string) Configuration::get(self::CONFIG_SAMEDAY_API_USERNAME),
                (string) Configuration::get(self::CONFIG_SAMEDAY_API_PASSWORD),
                (string) (Configuration::get(self::CONFIG_SAMEDAY_ENV) ?: 'demo'),
                null,
                $this->getLogger()
            );
        }

        return $this->samedayClient;
    }
}
