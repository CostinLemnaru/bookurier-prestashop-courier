<?php

namespace Bookurier\Install;

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
            'SELECT id_carrier FROM ' . _DB_PREFIX_ . 'carrier WHERE id_reference = ' . (int) $idReference
        );

        if (!is_array($rows)) {
            return false;
        }

        foreach ($rows as $row) {
            $idCarrier = (int) ($row['id_carrier'] ?? 0);
            if ($idCarrier <= 0) {
                continue;
            }

            $carrier = new \Carrier($idCarrier);
            if (\Validate::isLoadedObject($carrier)) {
                $carrier->deleted = 1;
                $carrier->update();
            }
        }

        return true;
    }

    private function getCarrierReference()
    {
        return (int) \Configuration::get(\Bookurier::CONFIG_CARRIER_REFERENCE);
    }

    private function uninstallDatabase()
    {
        $table = _DB_PREFIX_ . 'bookurier_sameday_locker';

        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . $table . '`');
    }
}
