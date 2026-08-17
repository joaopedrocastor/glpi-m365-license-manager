<?php
/**
 * Dashboard executivo M365.
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_m365license_dashboard', READ);

Html::header(
    __('Dashboard M365', 'm365license'),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginM365licenseDashboard'
);

$config = PluginM365licenseConfig::getInstance();
if (!$config->isConfigured()) {
    echo "<div class='alert alert-warning'>"
       . __('Configure a integração Microsoft Graph antes de usar o dashboard.', 'm365license')
       . " <a href='" . Plugin::getWebDir('m365license') . "/front/config.form.php'>"
       . __('Ir para configuração', 'm365license') . "</a></div>";
    Html::footer();
    exit;
}

PluginM365licenseDashboard::show();

Html::footer();
