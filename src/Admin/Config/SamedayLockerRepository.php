<?php

namespace Bookurier\Admin\Config;

use Bookurier\Install\SamedayLockerStorage;

class SamedayLockerRepository
{
    const TABLE = SamedayLockerStorage::TABLE;

    public function ensureTable()
    {
        return SamedayLockerStorage::ensureTable();
    }

    public function countActive()
    {
        if (!$this->ensureTable()) {
            return 0;
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . $this->getTableName() . '` WHERE `is_active` = 1'
        );
    }

    public function upsertMany(array $lockers)
    {
        if (!$this->ensureTable()) {
            throw new \RuntimeException('Locker storage could not be initialized.');
        }

        $db = \Db::getInstance();
        $now = date('Y-m-d H:i:s');
        $activeLockerIds = array();

        foreach ($lockers as $locker) {
            if (!is_array($locker)) {
                continue;
            }

            $lockerId = (int) ($locker['lockerId'] ?? 0);
            if ($lockerId <= 0) {
                continue;
            }

            $activeLockerIds[$lockerId] = $lockerId;

            $data = array(
                'locker_id' => $lockerId,
                'name' => (string) ($locker['name'] ?? ''),
                'county' => (string) ($locker['county'] ?? ''),
                'city' => (string) ($locker['city'] ?? ''),
                'address' => (string) ($locker['address'] ?? ''),
                'postal_code' => (string) ($locker['postalCode'] ?? ''),
                'lat' => (string) ($locker['lat'] ?? ''),
                'lng' => (string) ($locker['lng'] ?? ''),
                'boxes_count' => (int) ($locker['boxesCount'] ?? 0),
                'country_code' => '',
                'is_active' => 1,
                'updated_at' => $now,
            );

            $existingId = (int) $db->getValue(
                'SELECT `id_bookurier_sameday_locker` FROM `' . $this->getTableName() . '` WHERE `locker_id` = ' . (int) $lockerId
            );

            if ($existingId > 0) {
                $db->update(self::TABLE, $data, '`id_bookurier_sameday_locker` = ' . (int) $existingId);
            } else {
                $data['created_at'] = $now;
                $db->insert(self::TABLE, $data);
            }
        }

        if (empty($activeLockerIds)) {
            throw new \RuntimeException('No valid locker IDs were returned by SameDay.');
        }

        $db->execute(
            'UPDATE `' . $this->getTableName() . '`
                SET `is_active` = 0, `updated_at` = \'' . pSQL($now) . '\'
              WHERE `locker_id` NOT IN (' . implode(',', array_map('intval', $activeLockerIds)) . ')'
        );

        return count($activeLockerIds);
    }

    public function getActiveForCheckout()
    {
        if (!$this->ensureTable()) {
            return array();
        }

        $rows = \Db::getInstance()->executeS(
            'SELECT locker_id, name, city, county, address, postal_code'
            . ' FROM `' . $this->getTableName() . '`'
            . ' WHERE is_active = 1'
            . ' ORDER BY city ASC, name ASC'
        );

        if (!is_array($rows)) {
            return array();
        }

        $lockers = array();
        foreach ($rows as $row) {
            $lockerId = (int) ($row['locker_id'] ?? 0);
            if ($lockerId <= 0) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $city = trim((string) ($row['city'] ?? ''));
            $county = trim((string) ($row['county'] ?? ''));
            $address = trim((string) ($row['address'] ?? ''));
            $postalCode = trim((string) ($row['postal_code'] ?? ''));
            $label = $name !== '' ? $name : ('Locker #' . $lockerId);
            $details = trim(
                $city
                . ($county !== '' ? ', ' . $county : '')
                . ($postalCode !== '' ? ' ' . $postalCode : '')
                . ($address !== '' ? ' - ' . $address : '')
            );

            $lockers[] = array(
                'locker_id' => $lockerId,
                'label' => $label . ($details !== '' ? ' (' . $details . ')' : ''),
                'city' => $city,
                'county' => $county,
                'address' => $address,
                'postal_code' => $postalCode,
                'search_text' => strtolower(trim($label . ' ' . $details)),
            );
        }

        return $lockers;
    }

    public function isActiveLockerId($lockerId)
    {
        $lockerId = (int) $lockerId;
        if ($lockerId <= 0 || !$this->ensureTable()) {
            return false;
        }

        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . $this->getTableName() . '` WHERE locker_id = ' . (int) $lockerId . ' AND is_active = 1'
        ) > 0;
    }

    public function findActiveLockerById($lockerId)
    {
        $lockerId = (int) $lockerId;
        if ($lockerId <= 0 || !$this->ensureTable()) {
            return null;
        }

        $row = \Db::getInstance()->getRow(
            'SELECT locker_id, name, county, city, address, postal_code, boxes_count'
            . ' FROM `' . $this->getTableName() . '`'
            . ' WHERE locker_id = ' . $lockerId . ' AND is_active = 1'
        );

        return is_array($row) ? $row : null;
    }

    public function findBestLockerIdForAddress($deliveryAddress, array $lockers)
    {
        if (!is_object($deliveryAddress) || empty($lockers)) {
            return 0;
        }

        $city = $this->normalizeText((string) ($deliveryAddress->city ?? ''));
        $postcode = $this->normalizeText((string) ($deliveryAddress->postcode ?? ''));
        $street = $this->normalizeText(trim((string) ($deliveryAddress->address1 ?? '') . ' ' . (string) ($deliveryAddress->address2 ?? '')));

        $bestLockerId = 0;
        $bestScore = -1;

        foreach ($lockers as $locker) {
            if (!is_array($locker)) {
                continue;
            }

            $lockerId = (int) ($locker['locker_id'] ?? 0);
            if ($lockerId <= 0) {
                continue;
            }

            $score = 0;
            $lockerCity = $this->normalizeText((string) ($locker['city'] ?? ''));
            $lockerCounty = $this->normalizeText((string) ($locker['county'] ?? ''));
            $lockerAddress = $this->normalizeText((string) ($locker['address'] ?? ''));
            $lockerPostcode = $this->normalizeText((string) ($locker['postal_code'] ?? ''));

            if ($city !== '' && $lockerCity === $city) {
                $score += 100;
            }
            if ($postcode !== '' && $lockerPostcode !== '' && $lockerPostcode === $postcode) {
                $score += 60;
            }
            if ($street !== '' && $lockerAddress !== '' && strpos($lockerAddress, $street) !== false) {
                $score += 40;
            }
            if ($city !== '' && $lockerCounty !== '' && strpos($lockerCounty, $city) !== false) {
                $score += 5;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLockerId = $lockerId;
            }
        }

        return $bestScore > 0 ? $bestLockerId : 0;
    }

    private function normalizeText($value)
    {
        return strtolower(trim((string) $value));
    }

    private function getTableName()
    {
        return SamedayLockerStorage::getPrefixedTableName();
    }
}
