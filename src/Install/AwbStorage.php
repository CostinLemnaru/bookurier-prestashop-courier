<?php

namespace Bookurier\Install;

class AwbStorage
{
    const TABLE = 'bookurier_awb';

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
            `id_bookurier_awb` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order` INT UNSIGNED NOT NULL,
            `id_cart` INT UNSIGNED NOT NULL DEFAULT 0,
            `courier` VARCHAR(32) NOT NULL DEFAULT \'\',
            `awb_code` VARCHAR(64) NOT NULL DEFAULT \'\',
            `locker_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `status` VARCHAR(16) NOT NULL DEFAULT \'error\',
            `panel_status` VARCHAR(191) NOT NULL DEFAULT \'\',
            `panel_status_checked_at` DATETIME NULL,
            `error_message` VARCHAR(500) NOT NULL DEFAULT \'\',
            `request_payload` LONGTEXT NULL,
            `response_payload` LONGTEXT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id_bookurier_awb`),
            UNIQUE KEY `uniq_order` (`id_order`),
            KEY `idx_status` (`status`),
            KEY `idx_courier` (`courier`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;';
    }

}
