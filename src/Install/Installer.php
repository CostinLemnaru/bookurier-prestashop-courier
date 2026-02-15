<?php

namespace Bookurier\Install;

use Bookurier\Install\SamedayLockerStorage;

class Installer
{
    const CARRIER_MAX_WEIGHT = 100000;
    const CARRIER_MAX_WIDTH = 1000;
    const CARRIER_MAX_HEIGHT = 1000;
    const CARRIER_MAX_DEPTH = 1000;

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
        return SamedayLockerStorage::ensureTable();
    }

    private function installCarrier()
    {
        $idReference = $this->getCarrierReference();
        if ($idReference > 0) {
            $idCarrier = (int) \Carrier::getCarrierByReference($idReference);
            if ($idCarrier > 0) {
                $carrier = new \Carrier($idCarrier);
                if (\Validate::isLoadedObject($carrier) && (int) $carrier->deleted === 0) {
                    return $this->applyCarrierLimits($carrier);
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
        $carrier->max_weight = self::CARRIER_MAX_WEIGHT;
        $carrier->max_width = self::CARRIER_MAX_WIDTH;
        $carrier->max_height = self::CARRIER_MAX_HEIGHT;
        $carrier->max_depth = self::CARRIER_MAX_DEPTH;
        $carrier->delay = array();

        foreach (\Language::getLanguages(false) as $language) {
            $carrier->delay[(int) $language['id_lang']] = 'Bookurier courier';
        }

        if (!$carrier->add()) {
            return false;
        }

        $carrierReference = (int) $carrier->id_reference;
        if ($carrierReference <= 0) {
            $carrierReference = (int) $carrier->id;
            $carrier->id_reference = $carrierReference;
            if (!$carrier->update()) {
                return false;
            }
        }

        if (!\Configuration::updateValue(\Bookurier::CONFIG_CARRIER_REFERENCE, (string) $carrierReference)) {
            return false;
        }

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

    private function applyCarrierLimits(\Carrier $carrier)
    {
        $updated = false;

        if ((int) $carrier->max_weight !== self::CARRIER_MAX_WEIGHT) {
            $carrier->max_weight = self::CARRIER_MAX_WEIGHT;
            $updated = true;
        }
        if ((int) $carrier->max_width !== self::CARRIER_MAX_WIDTH) {
            $carrier->max_width = self::CARRIER_MAX_WIDTH;
            $updated = true;
        }
        if ((int) $carrier->max_height !== self::CARRIER_MAX_HEIGHT) {
            $carrier->max_height = self::CARRIER_MAX_HEIGHT;
            $updated = true;
        }
        if ((int) $carrier->max_depth !== self::CARRIER_MAX_DEPTH) {
            $carrier->max_depth = self::CARRIER_MAX_DEPTH;
            $updated = true;
        }

        if (!$updated) {
            return true;
        }

        return $carrier->update();
    }
}
