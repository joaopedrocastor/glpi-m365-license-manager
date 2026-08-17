<?php
/**
 * Gestão dos rightnames do plugin nos perfis do GLPI.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365Profile extends CommonDBTM {

    public static $rightname = 'profile';

    const RIGHTS = [
        'plugin_m365_dashboard',
        'plugin_m365_user',
        'plugin_m365_license',
    ];

    /**
     * Concede direitos totais ao perfil do usuário que instalou (super-admin).
     */
    public static function initProfile(): void {
        global $DB;
        $profileId = (int)($_SESSION['glpiactiveprofile']['id'] ?? 4); // 4 = Super-Admin

        foreach (self::RIGHTS as $right) {
            // Cria a definição do direito para todos os perfis (0) e concede ao instalador.
            $exists = $DB->request([
                'FROM'  => 'glpi_profilerights',
                'WHERE' => ['profiles_id' => $profileId, 'name' => $right],
                'LIMIT' => 1,
            ]);
            if (iterator_count($exists) === 0) {
                $DB->insert('glpi_profilerights', [
                    'profiles_id' => $profileId,
                    'name'        => $right,
                    'rights'      => ALLSTANDARDRIGHT,
                ]);
            }
        }
    }

    /**
     * Remove os direitos na desinstalação.
     */
    public static function removeRights(): void {
        global $DB;
        foreach (self::RIGHTS as $right) {
            $DB->delete('glpi_profilerights', ['name' => $right]);
        }
    }

    /**
     * Declara os direitos para a tela "Administração > Perfis".
     */
    public static function getAllRights(): array {
        return [
            ['itemtype' => 'PluginM365User',    'label' => __('Dashboard M365', 'm365'),
             'field' => 'plugin_m365_dashboard', 'rights' => [READ => __('Ler')]],
            ['itemtype' => 'PluginM365User',    'label' => __('Usuários M365', 'm365'),
             'field' => 'plugin_m365_user'],
            ['itemtype' => 'PluginM365License', 'label' => __('Licenças M365', 'm365'),
             'field' => 'plugin_m365_license'],
        ];
    }
}
