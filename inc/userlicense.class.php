<?php
/**
 * Vínculo N:N entre usuário M365 e licença (SKU atribuído).
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365licenseUserLicense extends CommonDBTM {

    public static $rightname = 'plugin_m365license_user';

    public static function getTypeName($nb = 0) {
        return _n('Licença do usuário', 'Licenças do usuário', $nb, 'm365license');
    }

    /**
     * Reconstrói os vínculos de licença de um usuário a partir do
     * assignedLicenses[] do Graph (lista de skuId).
     */
    public static function syncForUser(int $userId, array $assignedLicenses): void {
        global $DB;

        // Mapa skuId -> id local da licença
        $skuMap = [];
        foreach ($DB->request(['SELECT' => ['id', 'sku_id'], 'FROM' => 'glpi_plugin_m365license_licenses']) as $row) {
            $skuMap[$row['sku_id']] = (int)$row['id'];
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $wanted = [];
        foreach ($assignedLicenses as $lic) {
            $skuId = $lic['skuId'] ?? '';
            if (isset($skuMap[$skuId])) {
                $wanted[$skuMap[$skuId]] = true;
            }
        }

        // Remove vínculos que não existem mais
        $DB->delete('glpi_plugin_m365license_userlicenses', [
            'plugin_m365license_users_id' => $userId,
            'NOT' => ['plugin_m365license_licenses_id' => array_keys($wanted) ?: [0]],
        ]);

        // Adiciona os novos
        foreach (array_keys($wanted) as $licId) {
            $exists = $DB->request([
                'FROM'  => 'glpi_plugin_m365license_userlicenses',
                'WHERE' => ['plugin_m365license_users_id' => $userId, 'plugin_m365license_licenses_id' => $licId],
                'LIMIT' => 1,
            ]);
            if (iterator_count($exists) === 0) {
                $DB->insert('glpi_plugin_m365license_userlicenses', [
                    'plugin_m365license_users_id'    => $userId,
                    'plugin_m365license_licenses_id' => $licId,
                    'assigned_datetime'       => $now,
                    'date_creation'           => $now,
                ]);
            }
        }
    }
}
