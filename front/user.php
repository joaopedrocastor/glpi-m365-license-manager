<?php
/** Lista de usuários M365 (Search engine nativo do GLPI). */
include('../../../inc/includes.php');

Session::checkRight('plugin_m365license_user', READ);

Html::header(PluginM365licenseUser::getTypeName(2), $_SERVER['PHP_SELF'], 'management', 'PluginM365licenseUser');
Search::show('PluginM365licenseUser');
Html::footer();
