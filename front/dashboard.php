<?php
/**
 * Dashboard executivo M365.
 */

include('../../../inc/includes.php');

Session::checkRight('plugin_m365_dashboard', READ);

Html::header(
    __('Dashboard M365', 'm365'),
    $_SERVER['PHP_SELF'],
    'management',
    'PluginM365Dashboard'
);

$config = PluginM365Config::getInstance();
if (!$config->isConfigured()) {
    echo "<div class='alert alert-warning'>"
       . __('Configure a integração Microsoft Graph antes de usar o dashboard.', 'm365')
       . " <a href='" . Plugin::getWebDir('m365') . "/front/config.form.php'>"
       . __('Ir para configuração', 'm365') . "</a></div>";
    Html::footer();
    exit;
}

PluginM365Dashboard::show();

Html::footer();
