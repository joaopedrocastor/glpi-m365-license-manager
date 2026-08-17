<?php
/** Lista de alertas de auditoria. */
include('../../../inc/includes.php');

Session::checkRight('plugin_m365_dashboard', READ);

// Resolver alerta
if (isset($_GET['resolve'])) {
    $alert = new PluginM365Alert();
    if ($alert->getFromDB((int)$_GET['resolve'])) {
        $alert->update(['id' => $alert->getID(), 'is_resolved' => 1]);
        Session::addMessageAfterRedirect(__('Alerta resolvido.', 'm365'), true, INFO);
    }
    Html::redirect($_SERVER['PHP_SELF']);
}

Html::header(PluginM365Alert::getTypeName(2), $_SERVER['PHP_SELF'], 'management', 'PluginM365Alert');

global $DB;
$badge = ['critical' => 'danger', 'warning' => 'warning', 'info' => 'info'];

echo "<div class='card'><div class='card-header'><h3>" . __('Alertas abertos', 'm365') . "</h3></div>";
echo "<div class='card-body'><table class='tab_cadre_fixe'>";
echo "<tr><th>" . __('Severidade', 'm365') . "</th><th>" . __('Título', 'm365') . "</th>"
   . "<th>" . __('Mensagem', 'm365') . "</th><th>" . __('Criado', 'm365') . "</th><th></th></tr>";

$count = 0;
foreach ($DB->request(['FROM' => 'glpi_plugin_m365_alerts', 'WHERE' => ['is_resolved' => 0],
                       'ORDER' => 'date_creation DESC']) as $a) {
    $color = $badge[$a['severity']] ?? 'secondary';
    echo "<tr class='tab_bg_1'>";
    echo "<td><span class='badge bg-$color'>" . strtoupper($a['severity']) . "</span></td>";
    echo "<td>" . Html::entities_deep($a['title']) . "</td>";
    echo "<td>" . Html::entities_deep($a['message']) . "</td>";
    echo "<td>" . Html::convDateTime($a['date_creation']) . "</td>";
    echo "<td><a class='btn btn-sm btn-outline-success' href='?resolve=" . (int)$a['id'] . "'>"
       . __('Resolver', 'm365') . "</a></td>";
    echo "</tr>";
    $count++;
}
if ($count === 0) {
    echo "<tr><td colspan='5' class='text-center text-muted'>" . __('Nenhum alerta aberto.', 'm365') . "</td></tr>";
}
echo "</table></div></div>";

Html::footer();
