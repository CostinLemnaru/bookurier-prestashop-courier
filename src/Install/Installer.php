<?php

namespace Bookurier\Install;

class Installer
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    public function install()
    {
        return $this->registerHooks()
            && $this->installCarrier()
            && $this->installDatabase()
            && $this->installConfiguration();
    }

    private function registerHooks()
    {
        return $this->module->registerHook('displayBackOfficeHeader')
            && $this->module->registerHook('actionAdminControllerSetMedia');
    }

    private function installConfiguration()
    {
        return \Configuration::updateValue(\Bookurier::CONFIG_LOG_LEVEL, 'info')
            && \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_ENV, 'demo')
            && \Configuration::updateValue(\Bookurier::CONFIG_DEFAULT_SERVICE, '9')
            && \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_ENABLED, '0')
            && \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINT, '0')
            && \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINTS_CACHE, '[]');
    }

    private function installDatabase()
    {
        $table = _DB_PREFIX_ . 'bookurier_sameday_locker';
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
            `id_bookurier_sameday_locker` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `locker_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) NOT NULL DEFAULT \'\',
            `county` VARCHAR(191) NOT NULL DEFAULT \'\',
            `city` VARCHAR(191) NOT NULL DEFAULT \'\',
            `address` VARCHAR(255) NOT NULL DEFAULT \'\',
            `postal_code` VARCHAR(32) NOT NULL DEFAULT \'\',
            `lat` VARCHAR(32) NOT NULL DEFAULT \'\',
            `lng` VARCHAR(32) NOT NULL DEFAULT \'\',
            `country_code` VARCHAR(8) NOT NULL DEFAULT \'\',
            `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `updated_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_bookurier_sameday_locker`),
            UNIQUE KEY `uniq_locker_id` (`locker_id`),
            KEY `idx_city` (`city`),
            KEY `idx_county` (`county`),
            KEY `idx_active` (`is_active`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';

        return \Db::getInstance()->execute($sql);
    }

    private function installCarrier()
    {
        $idReference = $this->getCarrierReference();
        if ($idReference > 0) {
            $idCarrier = (int) \Carrier::getCarrierByReference($idReference);
            if ($idCarrier > 0) {
                $carrier = new \Carrier($idCarrier);
                if (\Validate::isLoadedObject($carrier) && (int) $carrier->deleted === 0) {
                    return true;
                }
            }
        }

        $carrier = new \Carrier();
        $carrier->name = 'Bookurier';
        $carrier->active = true;
        $carrier->deleted = 0;
        $carrier->is_module = true;
        $carrier->external_module_name = $this->module->name;
        $carrier->shipping_external = true;
        $carrier->shipping_handling = false;
        $carrier->need_range = true;
        $carrier->shipping_method = \Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->range_behavior = 0;
        $carrier->delay = array();

        foreach (\Language::getLanguages(false) as $language) {
            $carrier->delay[(int) $language['id_lang']] = 'Bookurier courier';
        }

        if (!$carrier->add()) {
            return false;
        }

        \Configuration::updateValue(\Bookurier::CONFIG_CARRIER_REFERENCE, (string) $carrier->id_reference);

        $zones = \Zone::getZones(true);
        foreach ($zones as $zone) {
            $carrier->addZone((int) $zone['id_zone']);
        }

        $groups = \Group::getGroups((int) \Configuration::get('PS_LANG_DEFAULT'));
        foreach ($groups as $group) {
            \Db::getInstance()->insert('carrier_group', array(
                'id_carrier' => (int) $carrier->id,
                'id_group' => (int) $group['id_group'],
            ));
        }

        $rangeWeight = new \RangeWeight();
        $rangeWeight->id_carrier = (int) $carrier->id;
        $rangeWeight->delimiter1 = 0;
        $rangeWeight->delimiter2 = 100000;
        $rangeWeight->add();

        foreach ($zones as $zone) {
            \Db::getInstance()->insert('delivery', array(
                'id_carrier' => (int) $carrier->id,
                'id_range_weight' => (int) $rangeWeight->id,
                'id_range_price' => 0,
                'id_zone' => (int) $zone['id_zone'],
                'price' => '0.000000',
            ));
        }

        return true;
    }

    private function getCarrierReference()
    {
        return (int) \Configuration::get(\Bookurier::CONFIG_CARRIER_REFERENCE);
    }
}
