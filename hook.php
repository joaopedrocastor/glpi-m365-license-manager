<?php
/**
 * M365 License Manager - install / uninstall / cron.
 */

/**
 * Instalação: cria as tabelas e registra permissões e cron.
 */
function plugin_m365license_install() {
    global $DB;

    $default_charset   = DBConnection::getDefaultCharset();
    $default_collation = DBConnection::getDefaultCollation();
    $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

    $migration = new Migration(PLUGIN_M365LICENSE_VERSION);

    // ---------------------------------------------------------------
    // glpi_plugin_m365license_configs
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_m365license_configs')) {
        $query = "CREATE TABLE `glpi_plugin_m365license_configs` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `tenant_id` varchar(255) DEFAULT NULL,
            `client_id` varchar(255) DEFAULT NULL,
            `client_secret` text COMMENT 'Criptografado (AES-256)',
            `token_cache` text COMMENT 'Access token + expiração (JSON criptografado)',
            `graph_endpoint` varchar(255) NOT NULL DEFAULT 'https://graph.microsoft.com/v1.0',
            `login_endpoint` varchar(255) NOT NULL DEFAULT 'https://login.microsoftonline.com',
            `is_active` tinyint NOT NULL DEFAULT '0',
            `sync_frequency` int NOT NULL DEFAULT '24' COMMENT 'horas',
            `teams_webhook` varchar(1024) DEFAULT NULL,
            `alert_email` varchar(255) DEFAULT NULL,
            `threshold_available` int NOT NULL DEFAULT '10',
            `threshold_inactive_days` int NOT NULL DEFAULT '60',
            `auto_create_ticket` tinyint NOT NULL DEFAULT '0',
            `last_sync` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);

        // Linha única de configuração
        $DB->insert('glpi_plugin_m365license_configs', [
            'id'            => 1,
            'is_active'     => 0,
            'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    // ---------------------------------------------------------------
    // glpi_plugin_m365license_licenses  (SKUs contratados no tenant)
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_m365license_licenses')) {
        $query = "CREATE TABLE `glpi_plugin_m365license_licenses` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `sku_id` varchar(255) NOT NULL COMMENT 'skuId do Graph (GUID)',
            `sku_part_number` varchar(255) NOT NULL COMMENT 'Ex: SPB, ENTERPRISEPACK',
            `name` varchar(255) NOT NULL COMMENT 'Nome amigável',
            `total_units` int NOT NULL DEFAULT '0' COMMENT 'prepaidUnits.enabled',
            `consumed_units` int NOT NULL DEFAULT '0',
            `warning_units` int NOT NULL DEFAULT '0',
            `suspended_units` int NOT NULL DEFAULT '0',
            `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
            `currency` varchar(3) NOT NULL DEFAULT 'BRL',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `sku_id` (`sku_id`),
            KEY `sku_part_number` (`sku_part_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    // ---------------------------------------------------------------
    // glpi_plugin_m365license_users  (usuários do Entra ID)
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_m365license_users')) {
        $query = "CREATE TABLE `glpi_plugin_m365license_users` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `azure_id` varchar(255) NOT NULL COMMENT 'id (objectId) do Graph',
            `display_name` varchar(255) DEFAULT NULL,
            `user_principal_name` varchar(255) DEFAULT NULL,
            `mail` varchar(255) DEFAULT NULL,
            `department` varchar(255) DEFAULT NULL,
            `job_title` varchar(255) DEFAULT NULL,
            `account_enabled` tinyint NOT NULL DEFAULT '1',
            `last_signin` timestamp NULL DEFAULT NULL,
            `created_datetime` timestamp NULL DEFAULT NULL,
            `license_count` int NOT NULL DEFAULT '0',
            `users_id` int {$default_key_sign} DEFAULT NULL COMMENT 'FK glpi_users (match por email/UPN)',
            `is_deleted` tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `azure_id` (`azure_id`),
            KEY `user_principal_name` (`user_principal_name`),
            KEY `department` (`department`),
            KEY `account_enabled` (`account_enabled`),
            KEY `last_signin` (`last_signin`),
            KEY `users_id` (`users_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    // ---------------------------------------------------------------
    // glpi_plugin_m365license_userlicenses  (N:N usuário <-> licença)
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_m365license_userlicenses')) {
        $query = "CREATE TABLE `glpi_plugin_m365license_userlicenses` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `plugin_m365license_users_id` int {$default_key_sign} NOT NULL,
            `plugin_m365license_licenses_id` int {$default_key_sign} NOT NULL,
            `assigned_datetime` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_m365license_users_id`,`plugin_m365license_licenses_id`),
            KEY `plugin_m365license_licenses_id` (`plugin_m365license_licenses_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    // ---------------------------------------------------------------
    // glpi_plugin_m365license_costs  (histórico mensal de custo por SKU)
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_m365license_costs')) {
        $query = "CREATE TABLE `glpi_plugin_m365license_costs` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `plugin_m365license_licenses_id` int {$default_key_sign} NOT NULL,
            `period` char(7) NOT NULL COMMENT 'YYYY-MM',
            `unit_cost` decimal(12,2) NOT NULL DEFAULT '0.00',
            `total_units` int NOT NULL DEFAULT '0',
            `consumed_units` int NOT NULL DEFAULT '0',
            `monthly_cost` decimal(14,2) NOT NULL DEFAULT '0.00',
            `wasted_cost` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'unidades ociosas x custo',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_m365license_licenses_id`,`period`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    // ---------------------------------------------------------------
    // glpi_plugin_m365license_alerts
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_m365license_alerts')) {
        $query = "CREATE TABLE `glpi_plugin_m365license_alerts` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `type` varchar(50) NOT NULL COMMENT 'low_stock|inactive_user|disabled_licensed|idle_license',
            `severity` varchar(20) NOT NULL DEFAULT 'info' COMMENT 'info|warning|critical',
            `title` varchar(255) NOT NULL,
            `message` text,
            `reference_id` int DEFAULT NULL COMMENT 'id do usuario/licenca relacionado',
            `reference_type` varchar(100) DEFAULT NULL,
            `tickets_id` int {$default_key_sign} DEFAULT NULL,
            `is_notified` tinyint NOT NULL DEFAULT '0',
            `is_resolved` tinyint NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `type` (`type`),
            KEY `is_resolved` (`is_resolved`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    // ---------------------------------------------------------------
    // glpi_plugin_m365license_synclogs
    // ---------------------------------------------------------------
    if (!$DB->tableExists('glpi_plugin_m365license_synclogs')) {
        $query = "CREATE TABLE `glpi_plugin_m365license_synclogs` (
            `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
            `sync_type` varchar(50) NOT NULL COMMENT 'users|licenses|signin|alerts',
            `status` varchar(20) NOT NULL DEFAULT 'running' COMMENT 'running|success|error',
            `items_processed` int NOT NULL DEFAULT '0',
            `items_created` int NOT NULL DEFAULT '0',
            `items_updated` int NOT NULL DEFAULT '0',
            `message` text,
            `duration_ms` int NOT NULL DEFAULT '0',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `sync_type` (`sync_type`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query);
    }

    $migration->executeMigration();

    // Semeia catálogo de SKUs conhecidos (nomes amigáveis)
    PluginM365licenseLicense::seedKnownSkus();

    // Permissões (profiles)
    PluginM365licenseProfile::initProfile();

    // Tarefas de cron
    CronTask::register('PluginM365licenseCronTask', 'syncUsers', HOUR_TIMESTAMP * 6, [
        'comment' => 'Sincroniza usuários do Microsoft Entra ID',
        'mode'    => CronTask::MODE_EXTERNAL,
    ]);
    CronTask::register('PluginM365licenseCronTask', 'syncLicenses', HOUR_TIMESTAMP * 6, [
        'comment' => 'Sincroniza SKUs/licenças do tenant',
        'mode'    => CronTask::MODE_EXTERNAL,
    ]);
    CronTask::register('PluginM365licenseCronTask', 'generateAlerts', DAY_TIMESTAMP, [
        'comment' => 'Gera alertas de auditoria e envia notificações',
        'mode'    => CronTask::MODE_EXTERNAL,
    ]);
    CronTask::register('PluginM365licenseCronTask', 'monthlyReport', DAY_TIMESTAMP, [
        'comment' => 'Consolida custos mensais e relatório executivo',
        'mode'    => CronTask::MODE_EXTERNAL,
    ]);

    return true;
}

/**
 * Desinstalação: remove tabelas, cron e permissões.
 */
function plugin_m365license_uninstall() {
    global $DB;

    $tables = [
        'glpi_plugin_m365license_configs',
        'glpi_plugin_m365license_licenses',
        'glpi_plugin_m365license_users',
        'glpi_plugin_m365license_userlicenses',
        'glpi_plugin_m365license_costs',
        'glpi_plugin_m365license_alerts',
        'glpi_plugin_m365license_synclogs',
    ];
    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    // Remove cron
    foreach (['syncUsers', 'syncLicenses', 'generateAlerts', 'monthlyReport'] as $name) {
        $cron = new CronTask();
        if ($cron->getFromDBByCrit(['itemtype' => 'PluginM365licenseCronTask', 'name' => $name])) {
            $cron->delete(['id' => $cron->getID()]);
        }
    }

    // Remove direitos dos profiles
    PluginM365licenseProfile::removeRights();

    return true;
}
