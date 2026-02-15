<?php

namespace Bookurier\Install;

use Bookurier\Install\SamedayLockerStorage;

class Uninstaller
{
    public function uninstall()
    {
        if (!$this->uninstallCarrier()) {
            return false;
        }

        foreach (array(
            \Bookurier::CONFIG_LOG_LEVEL,
            \Bookurier::CONFIG_SAMEDAY_ENV,
            \Bookurier::CONFIG_API_USER,
            \Bookurier::CONFIG_API_PASSWORD,
            \Bookurier::CONFIG_API_KEY,
            \Bookurier::CONFIG_DEFAULT_PICKUP_POINT,
            \Bookurier::CONFIG_DEFAULT_SERVICE,
            \Bookurier::CONFIG_SAMEDAY_ENABLED,
            \Bookurier::CONFIG_SAMEDAY_API_USERNAME,
            \Bookurier::CONFIG_SAMEDAY_API_PASSWORD,
            \Bookurier::CONFIG_SAMEDAY_PICKUP_POINT,
            \Bookurier::CONFIG_SAMEDAY_PICKUP_POINTS_CACHE,
            \Bookurier::CONFIG_CARRIER_REFERENCE,
        ) as $configKey) {
            \Configuration::deleteByName($configKey);
        }

        return $this->uninstallDatabase();
    }

    private function uninstallCarrier()
    {
        $idReference = $this->getCarrierReference();
        if ($idReference <= 0) {
            return true;
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT id_carrier FROM ' . _DB_PREFIX_ . 'carrier WHERE id_reference = ' . (int) $idReference . ' AND deleted = 0'
        );

        if (!is_array($rows)) {
            return false;
        }

        $carrierIds = $this->extractCarrierIds($rows);

        foreach ($carrierIds as $idCarrier) {
            $carrier = new \Carrier($idCarrier);
            if (!\Validate::isLoadedObject($carrier)) {
                continue;
            }

            $carrier->deleted = 1;
            if (!$carrier->update()) {
                return false;
            }
        }

        return true;
    }

    private function getCarrierReference()
    {
        return (int) \Configuration::get(\Bookurier::CONFIG_CARRIER_REFERENCE);
    }

    private function extractCarrierIds(array $rows)
    {
        $carrierIds = array();

        foreach ($rows as $row) {
            $idCarrier = (int) ($row['id_carrier'] ?? 0);
            if ($idCarrier > 0) {
                $carrierIds[] = $idCarrier;
            }
        }

        return array_values(array_unique($carrierIds));
    }

    private function uninstallDatabase()
    {
        return SamedayLockerStorage::dropTable();
    }
}
