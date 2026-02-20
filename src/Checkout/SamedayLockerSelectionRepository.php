<?php

namespace Bookurier\Checkout;

use Bookurier\Install\SamedayLockerSelectionStorage;

class SamedayLockerSelectionRepository
{
    public function ensureTable()
    {
        return SamedayLockerSelectionStorage::ensureTable();
    }

    public function getLockerIdByCart($idCart)
    {
        if ((int) $idCart <= 0 || !$this->ensureTable()) {
            return 0;
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT locker_id FROM `' . $this->getTableName() . '` WHERE id_cart = ' . (int) $idCart
        );
    }

    public function saveForCart($idCart, $lockerId)
    {
        if ((int) $idCart <= 0 || (int) $lockerId <= 0 || !$this->ensureTable()) {
            return false;
        }

        $db = \Db::getInstance();
        $now = date('Y-m-d H:i:s');
        $existingId = (int) $db->getValue(
            'SELECT id_bookurier_sameday_locker_selection FROM `' . $this->getTableName() . '` WHERE id_cart = ' . (int) $idCart
        );

        if ($existingId > 0) {
            return $db->update(
                SamedayLockerSelectionStorage::TABLE,
                array(
                    'locker_id' => (int) $lockerId,
                    'updated_at' => $now,
                ),
                'id_bookurier_sameday_locker_selection = ' . (int) $existingId
            );
        }

        return $db->insert(
            SamedayLockerSelectionStorage::TABLE,
            array(
                'id_cart' => (int) $idCart,
                'id_order' => 0,
                'locker_id' => (int) $lockerId,
                'created_at' => $now,
                'updated_at' => $now,
            )
        );
    }

    public function assignOrder($idCart, $idOrder)
    {
        if ((int) $idCart <= 0 || (int) $idOrder <= 0 || !$this->ensureTable()) {
            return false;
        }

        return \Db::getInstance()->update(
            SamedayLockerSelectionStorage::TABLE,
            array(
                'id_order' => (int) $idOrder,
                'updated_at' => date('Y-m-d H:i:s'),
            ),
            'id_cart = ' . (int) $idCart
        );
    }

    private function getTableName()
    {
        return SamedayLockerSelectionStorage::getPrefixedTableName();
    }
}
