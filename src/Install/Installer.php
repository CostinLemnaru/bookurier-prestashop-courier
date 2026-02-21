<?php

namespace Bookurier\Install;

use Bookurier\Install\SamedayLockerSelectionStorage;
use Bookurier\Install\SamedayLockerStorage;
use Bookurier\Install\AwbStorage;

class Installer
{
    const BOOKURIER_MAX_WEIGHT = 100000;
    const BOOKURIER_MAX_WIDTH = 1000;
    const BOOKURIER_MAX_HEIGHT = 1000;
    const BOOKURIER_MAX_DEPTH = 1000;
    const BOOKURIER_RANGE_MAX_WEIGHT = 100000;

    const LOCKER_MAX_WEIGHT = 20;
    const LOCKER_MAX_WIDTH = 200;
    const LOCKER_MAX_HEIGHT = 100;
    const LOCKER_MAX_DEPTH = 100;
    const LOCKER_RANGE_MAX_WEIGHT = 20;

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
            && $this->module->registerHook('actionAdminControllerSetMedia')
            && $this->module->registerHook('actionFrontControllerSetMedia')
            && $this->module->registerHook('displayCarrierExtraContent')
            && $this->module->registerHook('actionValidateOrder')
            && $this->module->registerHook('actionOrderStatusPostUpdate')
            && $this->module->registerHook('displayAdminOrderMain')
            && $this->module->registerHook('displayAdminOrder');
    }

    private function installConfiguration()
    {
        return \Configuration::updateValue(\Bookurier::CONFIG_LOG_LEVEL, 'info')
            && \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_ENV, 'demo')
            && \Configuration::updateValue(\Bookurier::CONFIG_DEFAULT_SERVICE, '9')
            && \Configuration::updateValue(\Bookurier::CONFIG_AUTO_AWB_ENABLED, '1')
            && \Configuration::updateValue(\Bookurier::CONFIG_AUTO_AWB_ALLOWED_STATUSES, $this->getDefaultAutoAwbStatusIds())
            && \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_ENABLED, '0')
            && \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINT, '0')
            && \Configuration::updateValue(\Bookurier::CONFIG_SAMEDAY_PICKUP_POINTS_CACHE, '[]');
    }

    private function getDefaultAutoAwbStatusIds()
    {
        $statusIds = array();

        foreach (array('PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING') as $configKey) {
            $statusId = (int) \Configuration::get($configKey);
            if ($statusId > 0) {
                $statusIds[] = $statusId;
            }
        }

        $statusIds = array_values(array_unique($statusIds));

        return implode(',', $statusIds);
    }

    private function installDatabase()
    {
        return SamedayLockerStorage::ensureTable()
            && SamedayLockerSelectionStorage::ensureTable()
            && AwbStorage::ensureTable();
    }

    private function installCarrier()
    {
        return $this->installSingleCarrier(
            \Bookurier::CONFIG_BOOKURIER_CARRIER_REFERENCE,
            array(
                'name' => 'Bookurier',
                'delay' => 'Bookurier courier',
                'max_weight' => self::BOOKURIER_MAX_WEIGHT,
                'max_width' => self::BOOKURIER_MAX_WIDTH,
                'max_height' => self::BOOKURIER_MAX_HEIGHT,
                'max_depth' => self::BOOKURIER_MAX_DEPTH,
                'range_max_weight' => self::BOOKURIER_RANGE_MAX_WEIGHT,
            )
        ) && $this->installSingleCarrier(
            \Bookurier::CONFIG_CARRIER_REFERENCE,
            array(
                'name' => 'Sameday Locker',
                'delay' => 'Sameday locker delivery',
                'max_weight' => self::LOCKER_MAX_WEIGHT,
                'max_width' => self::LOCKER_MAX_WIDTH,
                'max_height' => self::LOCKER_MAX_HEIGHT,
                'max_depth' => self::LOCKER_MAX_DEPTH,
                'range_max_weight' => self::LOCKER_RANGE_MAX_WEIGHT,
            )
        );
    }

    private function installSingleCarrier($configKey, array $profile)
    {
        $carrier = $this->getActiveCarrierByReference((int) \Configuration::get($configKey));

        if ($carrier === null) {
            $carrier = new \Carrier();
            $this->populateCarrier($carrier, $profile);

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

            if (!\Configuration::updateValue($configKey, (string) $carrierReference)) {
                return false;
            }
        } else {
            $this->populateCarrier($carrier, $profile);
            if (!$carrier->update()) {
                return false;
            }

            if (!\Configuration::updateValue($configKey, (string) $carrier->id_reference)) {
                return false;
            }
        }

        return $this->syncCarrierGroups($carrier)
            && $this->syncCarrierZonesAndDelivery($carrier, (float) $profile['range_max_weight']);
    }

    private function getActiveCarrierByReference($idReference)
    {
        $idReference = (int) $idReference;
        if ($idReference <= 0) {
            return null;
        }

        $idCarrier = (int) \Carrier::getCarrierByReference($idReference);
        if ($idCarrier <= 0) {
            return null;
        }

        $carrier = new \Carrier($idCarrier);
        if (!\Validate::isLoadedObject($carrier) || (int) $carrier->deleted !== 0) {
            return null;
        }

        return $carrier;
    }

    private function populateCarrier(\Carrier $carrier, array $profile)
    {
        $carrier->name = (string) $profile['name'];
        $carrier->active = true;
        $carrier->deleted = 0;
        $carrier->is_module = true;
        $carrier->external_module_name = $this->module->name;
        $carrier->shipping_external = true;
        $carrier->shipping_handling = false;
        $carrier->need_range = true;
        $carrier->shipping_method = \Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->range_behavior = 0;
        $carrier->max_weight = (float) $profile['max_weight'];
        $carrier->max_width = (float) $profile['max_width'];
        $carrier->max_height = (float) $profile['max_height'];
        $carrier->max_depth = (float) $profile['max_depth'];
        $carrier->delay = array();

        foreach (\Language::getLanguages(false) as $language) {
            $carrier->delay[(int) $language['id_lang']] = (string) $profile['delay'];
        }
    }

    private function syncCarrierGroups(\Carrier $carrier)
    {
        $idCarrier = (int) $carrier->id;
        if ($idCarrier <= 0) {
            return false;
        }

        $db = \Db::getInstance();
        if (!$db->delete('carrier_group', 'id_carrier = ' . $idCarrier)) {
            return false;
        }

        $groups = \Group::getGroups((int) \Configuration::get('PS_LANG_DEFAULT'));
        if (!is_array($groups)) {
            return false;
        }

        foreach ($groups as $group) {
            if (!$db->insert('carrier_group', array(
                'id_carrier' => $idCarrier,
                'id_group' => (int) $group['id_group'],
            ))) {
                return false;
            }
        }

        return true;
    }

    private function syncCarrierZonesAndDelivery(\Carrier $carrier, $rangeMaxWeight)
    {
        $zoneIds = $this->getRomaniaZoneIds();
        if (empty($zoneIds)) {
            return false;
        }

        $idCarrier = (int) $carrier->id;
        if ($idCarrier <= 0) {
            return false;
        }

        $db = \Db::getInstance();
        if (!$db->delete('carrier_zone', 'id_carrier = ' . $idCarrier)) {
            return false;
        }
        if (!$db->delete('delivery', 'id_carrier = ' . $idCarrier)) {
            return false;
        }
        if (!$db->delete('range_weight', 'id_carrier = ' . $idCarrier)) {
            return false;
        }

        foreach ($zoneIds as $zoneId) {
            if (!$carrier->addZone((int) $zoneId)) {
                return false;
            }
        }

        $rangeWeight = new \RangeWeight();
        $rangeWeight->id_carrier = $idCarrier;
        $rangeWeight->delimiter1 = 0;
        $rangeWeight->delimiter2 = (float) $rangeMaxWeight;
        if (!$rangeWeight->add()) {
            return false;
        }

        foreach ($zoneIds as $zoneId) {
            if (!$db->insert('delivery', array(
                'id_carrier' => $idCarrier,
                'id_range_weight' => (int) $rangeWeight->id,
                'id_range_price' => 0,
                'id_zone' => (int) $zoneId,
                'price' => '0.000000',
            ))) {
                return false;
            }
        }

        return true;
    }

    private function getRomaniaZoneIds()
    {
        $idCountry = (int) \Country::getByIso('RO');
        if ($idCountry <= 0) {
            return array();
        }

        $country = new \Country($idCountry);
        if (!\Validate::isLoadedObject($country)) {
            return array();
        }

        $idZone = (int) $country->id_zone;
        if ($idZone <= 0) {
            return array();
        }

        return array($idZone);
    }
}
