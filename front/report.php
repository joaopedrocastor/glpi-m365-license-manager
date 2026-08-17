<?php
/**
 * Central de relatórios: seleção de tipo e formato (CSV/Excel/PDF).
 */
include('../../../inc/includes.php');

Session::checkRight('plugin_m365license_dashboard', READ);

$titles = [
    PluginM365licenseReport::R_USER_LICENSE => 'Licenças por usuário',
    PluginM365licenseReport::R_BY_DEPT      => 'Usuários por departamento',
    PluginM365licenseReport::R_IDLE         => 'Licenças ociosas',
    PluginM365licenseReport::R_COST_MONTH   => 'Custos mensais',
    PluginM365licenseReport::R_COST_YEAR    => 'Custos anuais',
];

// Exportação direta (sem layout)
if (isset($_GET['export'], $_GET['type']) && isset($titles[$_GET['type']])) {
    $type = $_GET['type'];
    switch ($_GET['export']) {
        case 'csv':  PluginM365licenseReport::exportCsv($type); exit;
        case 'xlsx': PluginM365licenseReport::exportXlsx($type); exit;
        case 'pdf':  PluginM365licenseReport::exportPdf($type, $titles[$type]); exit;
    }
}

Html::header(__('Relatórios', 'm365license'), $_SERVER['PHP_SELF'], 'management', 'PluginM365licenseReport');

echo "<div class='card' style='max-width:800px;margin:auto'>";
echo "<div class='card-header'><h3>" . __('Relatórios disponíveis', 'm365license') . "</h3></div>";
echo "<div class='card-body'><table class='tab_cadre_fixe'>";
echo "<tr><th>" . __('Relatório', 'm365license') . "</th><th class='text-center'>" . __('Formato', 'm365license') . "</th></tr>";
foreach ($titles as $type => $label) {
    $base = $_SERVER['PHP_SELF'] . "?type=$type&export=";
    echo "<tr class='tab_bg_1'><td>" . __($label, 'm365license') . "</td><td class='text-center'>";
    echo "<a class='btn btn-sm btn-outline-secondary me-1' href='{$base}csv'>CSV</a>";
    echo "<a class='btn btn-sm btn-outline-success me-1' href='{$base}xlsx'>Excel</a>";
    echo "<a class='btn btn-sm btn-outline-danger' href='{$base}pdf'>PDF</a>";
    echo "</td></tr>";
}
echo "</table></div></div>";

Html::footer();
