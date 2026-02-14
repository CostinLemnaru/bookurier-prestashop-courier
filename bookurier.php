<?php
/**
 * Bookurier Courier Module - development scaffold.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Bookurier extends CarrierModule
{
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
            && $this->registerHook('actionAdminControllerSetMedia');
    }

    public function uninstall()
    {
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
}
