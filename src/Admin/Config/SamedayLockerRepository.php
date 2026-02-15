<?php

namespace Bookurier\Admin\Config;

class SamedayLockerRepository
{
    const TABLE = 'bookurier_sameday_locker';

    public function ensureTable()
    {
        $table = $this->getTableName();
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

    private function getTableName()
    {
        return _DB_PREFIX_ . self::TABLE;
    }
}
