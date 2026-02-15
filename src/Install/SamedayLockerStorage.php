<?php

namespace Bookurier\Install;

class SamedayLockerStorage
{
    const TABLE = 'bookurier_sameday_locker';

    public static function ensureTable()
    {
        return \Db::getInstance()->execute(self::getCreateTableSql());
    }

    public static function dropTable()
    {
        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . self::getPrefixedTableName() . '`');
    }

    public static function getPrefixedTableName()
    {
        return _DB_PREFIX_ . self::TABLE;
    }

    private static function getCreateTableSql()
    {
        return 'CREATE TABLE IF NOT EXISTS `' . self::getPrefixedTableName() . '` (
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
    }
}
