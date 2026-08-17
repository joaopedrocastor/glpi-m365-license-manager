<?php
/**
 * M365 License Manager - GLPI 10.x Plugin
 * Governança e auditoria de licenças Microsoft 365 via Microsoft Graph API.
 *
 * @author   Joao Pedro
 * @license  GPLv3+
 */

define('PLUGIN_M365LICENSE_VERSION', '1.0.0');

// Faixa de compatibilidade testada
define('PLUGIN_M365LICENSE_MIN_GLPI', '10.0.0');
define('PLUGIN_M365LICENSE_MAX_GLPI', '10.0.99');

/**
 * Init hooks do plugin.
 * Chamado toda vez que o GLPI carrega, para plugins ativos.
 */
function plugin_init_m365license() {
    global $PLUGIN_HOOKS;

    // Necessário para qualquer plugin que renderize telas/CSRF.
    $PLUGIN_HOOKS['csrf_compliant']['m365license'] = true;

    // Assets
    $PLUGIN_HOOKS['add_css']['m365license']     = 'css/m365.css';
    $PLUGIN_HOOKS['add_javascript']['m365license'] = 'js/m365.js';

    $plugin = new Plugin();
    if (!$plugin->isActivated('m365license')) {
        return;
    }

    // Registro explícito das classes (autoloader clássico do GLPI faz o resto,
    // mas o registro garante rightname/searchoptions).
    Plugin::registerClass('PluginM365licenseConfig',      ['addtabon' => []]);
    Plugin::registerClass('PluginM365licenseLicense');
    Plugin::registerClass('PluginM365licenseUser');
    Plugin::registerClass('PluginM365licenseUserLicense');
    Plugin::registerClass('PluginM365licenseAlert');
    Plugin::registerClass('PluginM365licenseSyncLog');

    // Menu principal (Gestão)
    if (Session::haveRight('plugin_m365license_dashboard', READ)) {
        $PLUGIN_HOOKS['menu_toplevel']['m365license'] = [];
        $PLUGIN_HOOKS['menu']['m365license'] = [
            'id'    => 'm365license',
            'title' => 'M365 Licenses',
            'page'  => '/plugins/m365license/front/dashboard.php',
            'icon'  => 'ti ti-brand-windows',
            'content' => [
                'dashboard' => [
                    'title' => __('Dashboard', 'm365license'),
                    'page'  => '/plugins/m365license/front/dashboard.php',
                    'icon'  => 'ti ti-dashboard',
                ],
                'user' => [
                    'title' => __('Usuários M365', 'm365license'),
                    'page'  => '/plugins/m365license/front/user.php',
                    'icon'  => 'ti ti-users',
                ],
                'license' => [
                    'title' => __('Licenças', 'm365license'),
                    'page'  => '/plugins/m365license/front/license.php',
                    'icon'  => 'ti ti-license',
                ],
                'cost' => [
                    'title' => __('Financeiro', 'm365license'),
                    'page'  => '/plugins/m365license/front/cost.php',
                    'icon'  => 'ti ti-currency-dollar',
                ],
                'report' => [
                    'title' => __('Relatórios', 'm365license'),
                    'page'  => '/plugins/m365license/front/report.php',
                    'icon'  => 'ti ti-file-report',
                ],
                'alert' => [
                    'title' => __('Alertas', 'm365license'),
                    'page'  => '/plugins/m365license/front/alert.php',
                    'icon'  => 'ti ti-bell',
                ],
            ],
        ];
    }

    // Configuração (engrenagem em Configurar > Plugins)
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['m365license'] = 'front/config.form.php';
    }

    // Tarefas automáticas (CRON)
    $PLUGIN_HOOKS['csrf_compliant']['m365license'] = true;
}

/**
 * Metadados do plugin (aba Plugins do GLPI).
 */
function plugin_version_m365license() {
    return [
        'name'           => 'M365 License Manager',
        'version'        => PLUGIN_M365LICENSE_VERSION,
        'author'         => 'Joao Pedro',
        'license'        => 'GPLv3+',
        'homepage'       => 'https://github.com/joaopedrocastor/glpi-m365-license-manager',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_M365LICENSE_MIN_GLPI,
                'max' => PLUGIN_M365LICENSE_MAX_GLPI,
            ],
            'php' => [
                'min' => '8.0',
            ],
        ],
    ];
}

/**
 * Pré-requisitos de instalação.
 */
function plugin_m365license_check_prerequisites() {
    if (!function_exists('curl_init')) {
        echo __('A extensão PHP cURL é obrigatória.', 'm365license');
        return false;
    }
    if (!function_exists('openssl_encrypt')) {
        echo __('A extensão PHP OpenSSL é obrigatória (criptografia do Client Secret).', 'm365license');
        return false;
    }
    return true;
}

/**
 * Checagem de configuração antes de ativar.
 */
function plugin_m365license_check_config($verbose = false) {
    return true;
}
