<?php

namespace Bookurier\Install;

class SamedayLockerSelectionStorage
{
    const TABLE = 'bookurier_sameday_locker_selection';

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
            `id_bookurier_sameday_locker_selection` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_cart` INT UNSIGNED NOT NULL,
            `id_order` INT UNSIGNED NOT NULL DEFAULT 0,
            `locker_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_bookurier_sameday_locker_selection`),
            UNIQUE KEY `uniq_cart` (`id_cart`),
            KEY `idx_order` (`id_order`),
            KEY `idx_locker` (`locker_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    }
}
