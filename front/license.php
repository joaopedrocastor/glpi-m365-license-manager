<?php
/** Lista de licenças / SKUs. */
include('../../../inc/includes.php');

Session::checkRight('plugin_m365license_license', READ);

Html::header(PluginM365licenseLicense::getTypeName(2), $_SERVER['PHP_SELF'], 'management', 'PluginM365licenseLicense');
Search::show('PluginM365licenseLicense');
Html::footer();
