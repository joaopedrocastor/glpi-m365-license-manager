<?php
/** Lista de usuários M365 (Search engine nativo do GLPI). */
include('../../../inc/includes.php');

Session::checkRight('plugin_m365_user', READ);

Html::header(PluginM365User::getTypeName(2), $_SERVER['PHP_SELF'], 'management', 'PluginM365User');
Search::show('PluginM365User');
Html::footer();
