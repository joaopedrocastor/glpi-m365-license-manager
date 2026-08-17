<?php
/**
 * Financeiro: edição de custo unitário por licença e KPIs de custo.
 */
include('../../../inc/includes.php');

Session::checkRight('plugin_m365license_license', UPDATE);

if (isset($_POST['save_costs'])) {
    Session::checkCSRF($_POST);
    $license = new PluginM365licenseLicense();
    foreach (($_POST['unit_cost'] ?? []) as $id => $value) {
        $value = str_replace(['.', ','], ['', '.'], (string)$value); // "1.234,56" -> 1234.56
        $license->update(['id' => (int)$id, 'unit_cost' => (float)$value]);
    }
    Session::addMessageAfterRedirect(__('Custos atualizados.', 'm365license'), true, INFO);
    Html::back();
}

Html::header(__('Financeiro', 'm365license'), $_SERVER['PHP_SELF'], 'management', 'PluginM365licenseLicense');

global $DB;
$stats = PluginM365licenseLicense::getGlobalStats();
$fmt = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

echo "<div class='row row-cards mb-3'>";
$kpi = function ($label, $value, $color) {
    echo "<div class='col-sm-3'><div class='card'><div class='card-body'>
            <div class='text-muted'>$label</div>
            <div class='h2 text-$color'>$value</div></div></div></div>";
};
$kpi(__('Custo mensal', 'm365license'), $fmt($stats['monthly_cost']), 'primary');
$kpi(__('Custo anual', 'm365license'), $fmt($stats['monthly_cost'] * 12), 'info');
$kpi(__('Desperdício/mês', 'm365license'), $fmt($stats['wasted_cost']), 'danger');
$kpi(__('Economia potencial/ano', 'm365license'), $fmt($stats['wasted_cost'] * 12), 'success');
echo "</div>";

echo "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
echo "<div class='card'><div class='card-header'>" . __('Custo unitário por licença (R$)', 'm365license') . "</div>";
echo "<div class='card-body'><table class='tab_cadre_fixe'>";
echo "<tr><th>" . __('Licença', 'm365license') . "</th><th>" . __('Em uso', 'm365license') . "</th>"
   . "<th>" . __('Disponíveis', 'm365license') . "</th><th>" . __('Custo unitário', 'm365license') . "</th>"
   . "<th>" . __('Custo mensal', 'm365license') . "</th></tr>";

foreach ($DB->request(['FROM' => 'glpi_plugin_m365license_licenses', 'WHERE' => ['is_deleted' => 0],
                       'ORDER' => 'name']) as $l) {
    $available = max(0, (int)$l['total_units'] - (int)$l['consumed_units']);
    $monthly = (float)$l['unit_cost'] * (int)$l['consumed_units'];
    echo "<tr class='tab_bg_1'>";
    echo "<td>" . Html::entities_deep($l['name']) . "</td>";
    echo "<td class='text-center'>" . (int)$l['consumed_units'] . "</td>";
    echo "<td class='text-center'>$available</td>";
    echo "<td><input type='text' class='form-control' style='width:120px' name='unit_cost[" . (int)$l['id'] . "]' value='"
       . number_format((float)$l['unit_cost'], 2, ',', '.') . "'></td>";
    echo "<td class='text-end'>" . $fmt($monthly) . "</td>";
    echo "</tr>";
}
echo "</table></div>";
echo "<div class='card-footer text-end'><button type='submit' name='save_costs' class='btn btn-primary'>"
   . "<i class='ti ti-device-floppy'></i> " . __('Salvar custos', 'm365license') . "</button></div>";
echo "</div>";
Html::closeForm();

Html::footer();
