<?php
/** Lista de licenças / SKUs. */
include('../../../inc/includes.php');

Session::checkRight('plugin_m365_license', READ);

Html::header(PluginM365License::getTypeName(2), $_SERVER['PHP_SELF'], 'management', 'PluginM365License');
Search::show('PluginM365License');
Html::footer();
