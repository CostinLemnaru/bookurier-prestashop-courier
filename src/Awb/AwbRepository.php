<?php

namespace Bookurier\Awb;

use Bookurier\Install\AwbStorage;

class AwbRepository
{
    public function ensureTable()
    {
        return AwbStorage::ensureTable();
    }

    public function findByOrderId($idOrder)
    {
        $idOrder = (int) $idOrder;
        if ($idOrder <= 0 || !$this->ensureTable()) {
            return null;
        }

        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . $this->getTableName() . '` WHERE id_order = ' . $idOrder
        );

        return is_array($row) ? $row : null;
    }

    public function hasCreatedAwb($idOrder)
    {
        $row = $this->findByOrderId($idOrder);
        if (!is_array($row)) {
            return false;
        }

        return strtolower((string) ($row['status'] ?? '')) === 'created'
            && trim((string) ($row['awb_code'] ?? '')) !== '';
    }

    public function saveSuccess($idOrder, $idCart, $courier, $awbCode, $lockerId, $requestPayload, $responsePayload)
    {
        return $this->upsert($idOrder, array(
            'id_cart' => (int) $idCart,
            'courier' => (string) $courier,
            'awb_code' => (string) $awbCode,
            'locker_id' => (int) $lockerId,
            'status' => 'created',
            'panel_status' => '',
            'panel_status_checked_at' => null,
            'error_message' => '',
            'request_payload' => (string) $requestPayload,
            'response_payload' => (string) $responsePayload,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function saveError($idOrder, $idCart, $courier, $lockerId, $requestPayload, $responsePayload, $message)
    {
        return $this->upsert($idOrder, array(
            'id_cart' => (int) $idCart,
            'courier' => (string) $courier,
            'awb_code' => '',
            'locker_id' => (int) $lockerId,
            'status' => 'error',
            'panel_status' => '',
            'panel_status_checked_at' => null,
            'error_message' => substr((string) $message, 0, 500),
            'request_payload' => (string) $requestPayload,
            'response_payload' => (string) $responsePayload,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function savePanelStatus($idOrder, $status)
    {
        return $this->updatePanelStatusCache($idOrder, array(
            'panel_status' => (string) $status,
            'panel_status_checked_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function touchPanelStatusCheck($idOrder)
    {
        return $this->updatePanelStatusCache($idOrder, array(
            'panel_status_checked_at' => date('Y-m-d H:i:s'),
        ));
    }

    private function upsert($idOrder, array $data)
    {
        $idOrder = (int) $idOrder;
        if ($idOrder <= 0 || !$this->ensureTable()) {
            return false;
        }

        $db = \Db::getInstance();
        $idAwb = (int) $db->getValue(
            'SELECT id_bookurier_awb FROM `' . $this->getTableName() . '` WHERE id_order = ' . $idOrder
        );

        if ($idAwb > 0) {
            return (bool) $db->update(AwbStorage::TABLE, $data, 'id_bookurier_awb = ' . $idAwb);
        }

        $data['id_order'] = $idOrder;
        $data['created_at'] = date('Y-m-d H:i:s');

        return (bool) $db->insert(AwbStorage::TABLE, $data);
    }

    private function updatePanelStatusCache($idOrder, array $data)
    {
        $idOrder = (int) $idOrder;
        if ($idOrder <= 0 || !$this->ensureTable()) {
            return false;
        }

        return (bool) \Db::getInstance()->update(
            AwbStorage::TABLE,
            $data,
            'id_order = ' . $idOrder
        );
    }

    private function getTableName()
    {
        return AwbStorage::getPrefixedTableName();
    }
}
