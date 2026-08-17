<?php
/**
 * Motor de tarefas automáticas (CRON) do plugin.
 * Cada método cronXxx() é registrado em hook.php e executado pelo GLPI.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365CronTask extends CommonDBTM {

    public static function getTypeName($nb = 0) {
        return 'M365 License Manager';
    }

    /**
     * Descrições exibidas em Configurar > Ações automáticas.
     */
    public static function cronInfo($name) {
        switch ($name) {
            case 'syncUsers':      return ['description' => __('Sincroniza usuários do Entra ID', 'm365')];
            case 'syncLicenses':   return ['description' => __('Sincroniza SKUs/licenças', 'm365')];
            case 'generateAlerts': return ['description' => __('Gera e envia alertas de auditoria', 'm365')];
            case 'monthlyReport':  return ['description' => __('Consolida custos mensais', 'm365')];
        }
        return [];
    }

    // -----------------------------------------------------------------
    // Sincronização de licenças (executar antes de usuários)
    // -----------------------------------------------------------------
    public static function cronSyncLicenses(CronTask $task): int {
        $config = PluginM365Config::getInstance();
        if (!$config->fields['is_active'] || !$config->isConfigured()) {
            return 0;
        }

        [$logId, $t0] = PluginM365SyncLog::start('licenses');
        $counters = ['processed' => 0, 'created' => 0, 'updated' => 0];

        try {
            $client = new PluginM365GraphClient($config);
            $license = new PluginM365License();
            foreach ($client->getSubscribedSkus() as $sku) {
                $res = $license->syncFromGraph($sku);
                $counters['processed']++;
                $counters[$res]++;
                $task->addVolume(1);
            }
            PluginM365SyncLog::finish($logId, $t0, 'success', $counters,
                "{$counters['created']} criadas, {$counters['updated']} atualizadas");
        } catch (Throwable $e) {
            PluginM365SyncLog::finish($logId, $t0, 'error', $counters, $e->getMessage());
            return 0;
        }
        return 1;
    }

    // -----------------------------------------------------------------
    // Sincronização de usuários (+ vínculo de licenças + último login)
    // -----------------------------------------------------------------
    public static function cronSyncUsers(CronTask $task): int {
        $config = PluginM365Config::getInstance();
        if (!$config->fields['is_active'] || !$config->isConfigured()) {
            return 0;
        }

        [$logId, $t0] = PluginM365SyncLog::start('users');
        $counters = ['processed' => 0, 'created' => 0, 'updated' => 0];

        try {
            $client = new PluginM365GraphClient($config);

            // Processa por página para não estourar memória em tenants grandes.
            $client->fetchUsers(function (array $page) use (&$counters, $task) {
                $user = new PluginM365User();
                foreach ($page as $gu) {
                    $res = $user->syncFromGraph($gu);
                    $counters['processed']++;
                    $counters[$res]++;
                    $task->addVolume(1);
                }
            });

            $config->update(['id' => 1, 'last_sync' => date('Y-m-d H:i:s')]);
            PluginM365SyncLog::finish($logId, $t0, 'success', $counters,
                "{$counters['processed']} usuários processados");
        } catch (Throwable $e) {
            PluginM365SyncLog::finish($logId, $t0, 'error', $counters, $e->getMessage());
            return 0;
        }
        return 1;
    }

    // -----------------------------------------------------------------
    // Geração de alertas de auditoria + despacho
    // -----------------------------------------------------------------
    public static function cronGenerateAlerts(CronTask $task): int {
        $config = PluginM365Config::getInstance();
        if (!$config->fields['is_active']) {
            return 0;
        }

        [$logId, $t0] = PluginM365SyncLog::start('alerts');
        $created = 0;

        try {
            $days = (int)$config->fields['threshold_inactive_days'];

            // 1) Estoque baixo por SKU
            $threshold = (int)$config->fields['threshold_available'];
            foreach (PluginM365License::getGlobalStats() ? [] : [] as $x) {}
            $lic = new PluginM365License();
            global $DB;
            foreach ($DB->request(['FROM' => 'glpi_plugin_m365_licenses', 'WHERE' => ['is_deleted' => 0]]) as $row) {
                $available = max(0, (int)$row['total_units'] - (int)$row['consumed_units']);
                if ((int)$row['total_units'] > 0 && $available < $threshold) {
                    if (PluginM365Alert::raise(PluginM365Alert::TYPE_LOW_STOCK, 'warning',
                        "Estoque baixo: {$row['name']}",
                        "Restam apenas $available licenças de {$row['name']} (limite: $threshold).",
                        (int)$row['id'], 'PluginM365License')) {
                        $created++;
                    }
                }
                // Licenças ociosas relevantes
                if ($available > 0 && (float)$row['unit_cost'] > 0) {
                    $waste = $available * (float)$row['unit_cost'];
                    if ($waste > 0) {
                        if (PluginM365Alert::raise(PluginM365Alert::TYPE_IDLE_LICENSE, 'info',
                            "Licenças ociosas: {$row['name']}",
                            "$available unidades ociosas de {$row['name']} (~R$ " . number_format($waste, 2, ',', '.') . "/mês).",
                            (int)$row['id'], 'PluginM365License')) {
                            $created++;
                        }
                    }
                }
            }

            // 2) Usuários inativos com licença
            foreach (PluginM365User::findInactive($days) as $u) {
                if (PluginM365Alert::raise(PluginM365Alert::TYPE_INACTIVE_USER, 'warning',
                    "Usuário inativo há +{$days} dias: {$u['display_name']}",
                    "{$u['user_principal_name']} possui {$u['license_count']} licença(s) e não faz login há mais de $days dias.",
                    (int)$u['id'], 'PluginM365User')) {
                    $created++;
                }
            }

            // 3) Contas desabilitadas com licença
            foreach (PluginM365User::findDisabledLicensed() as $u) {
                if (PluginM365Alert::raise(PluginM365Alert::TYPE_DISABLED_LICENSED, 'critical',
                    "Conta desabilitada licenciada: {$u['display_name']}",
                    "{$u['user_principal_name']} está desabilitada mas mantém {$u['license_count']} licença(s) ativa(s).",
                    (int)$u['id'], 'PluginM365User')) {
                    $created++;
                }
            }

            $sent = PluginM365Alert::dispatchPending();
            $task->addVolume($created);
            PluginM365SyncLog::finish($logId, $t0, 'success',
                ['processed' => $created], "$created alertas gerados, $sent notificados");
        } catch (Throwable $e) {
            PluginM365SyncLog::finish($logId, $t0, 'error', [], $e->getMessage());
            return 0;
        }
        return $created > 0 ? 1 : 0;
    }

    // -----------------------------------------------------------------
    // Consolidação mensal de custos
    // -----------------------------------------------------------------
    public static function cronMonthlyReport(CronTask $task): int {
        global $DB;
        $config = PluginM365Config::getInstance();
        if (!$config->fields['is_active']) {
            return 0;
        }
        $period = date('Y-m');
        $now = date('Y-m-d H:i:s');

        foreach ($DB->request(['FROM' => 'glpi_plugin_m365_licenses', 'WHERE' => ['is_deleted' => 0]]) as $row) {
            $available = max(0, (int)$row['total_units'] - (int)$row['consumed_units']);
            $monthly = (float)$row['unit_cost'] * (int)$row['consumed_units'];
            $wasted  = (float)$row['unit_cost'] * $available;

            $data = [
                'plugin_m365_licenses_id' => (int)$row['id'],
                'period'                  => $period,
                'unit_cost'               => (float)$row['unit_cost'],
                'total_units'             => (int)$row['total_units'],
                'consumed_units'          => (int)$row['consumed_units'],
                'monthly_cost'            => $monthly,
                'wasted_cost'             => $wasted,
                'date_creation'           => $now,
            ];
            // upsert por (licenca, periodo)
            $existing = $DB->request([
                'SELECT' => 'id', 'FROM' => 'glpi_plugin_m365_costs',
                'WHERE' => ['plugin_m365_licenses_id' => (int)$row['id'], 'period' => $period], 'LIMIT' => 1,
            ]);
            $existingId = null;
            foreach ($existing as $e) { $existingId = (int)$e['id']; }
            if ($existingId) {
                $DB->update('glpi_plugin_m365_costs', $data, ['id' => $existingId]);
            } else {
                $DB->insert('glpi_plugin_m365_costs', $data);
            }
            $task->addVolume(1);
        }
        return 1;
    }
}
