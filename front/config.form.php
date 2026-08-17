<?php
/**
 * Tela de configuração da integração Microsoft Graph.
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$config = PluginM365licenseConfig::getInstance();

// -------------------------------------------------------------------
// POST: salvar / testar conexão
// -------------------------------------------------------------------
if (isset($_POST['update'])) {
    Session::checkCSRF($_POST);
    $config->updateConfig($_POST);
    Session::addMessageAfterRedirect(__('Configuração salva.', 'm365license'), true, INFO);
    Html::back();
}

if (isset($_POST['test'])) {
    Session::checkCSRF($_POST);
    // Salva antes de testar (para usar credenciais recém-digitadas).
    $config->updateConfig($_POST);
    $client = new PluginM365licenseGraphClient(PluginM365licenseConfig::getInstance());
    $result = $client->testConnection();
    Session::addMessageAfterRedirect(
        Html::entities_deep($result['message']),
        true,
        $result['ok'] ? INFO : ERROR
    );
    Html::back();
}

Html::header(
    'M365 License Manager',
    $_SERVER['PHP_SELF'],
    'config',
    'PluginM365licenseConfig'
);

$config = PluginM365licenseConfig::getInstance();
$f = $config->fields;
$hasSecret = !empty($f['client_secret']);

echo "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<div class='card' style='max-width:900px;margin:auto'>";
echo "<div class='card-header'><h3>" . __('Integração Microsoft Graph', 'm365license') . "</h3></div>";
echo "<div class='card-body'>";

echo "<table class='tab_cadre_fixe'>";

echo "<tr class='tab_bg_1'><td width='30%'>Tenant ID (Directory ID)</td>";
echo "<td><input type='text' class='form-control' name='tenant_id' value='" .
     Html::entities_deep($f['tenant_id']) . "' placeholder='00000000-0000-0000-0000-000000000000'></td></tr>";

echo "<tr class='tab_bg_1'><td>Application (Client) ID</td>";
echo "<td><input type='text' class='form-control' name='client_id' value='" .
     Html::entities_deep($f['client_id']) . "'></td></tr>";

echo "<tr class='tab_bg_1'><td>Client Secret</td>";
echo "<td><input type='password' class='form-control' name='client_secret' autocomplete='new-password' placeholder='" .
     ($hasSecret ? '•••••• (deixe em branco para manter)' : 'informe o secret') . "'></td></tr>";

echo "<tr class='tab_bg_2'><td>" . __('Ativar sincronização', 'm365license') . "</td><td>";
Dropdown::showYesNo('is_active', $f['is_active']);
echo "</td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Limite mín. de licenças disponíveis', 'm365license') . "</td>";
echo "<td><input type='number' class='form-control' name='threshold_available' value='" .
     (int)$f['threshold_available'] . "'></td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Dias sem login para alerta', 'm365license') . "</td>";
echo "<td><input type='number' class='form-control' name='threshold_inactive_days' value='" .
     (int)$f['threshold_inactive_days'] . "'></td></tr>";

echo "<tr class='tab_bg_2'><td>" . __('E-mail para alertas', 'm365license') . "</td>";
echo "<td><input type='email' class='form-control' name='alert_email' value='" .
     Html::entities_deep($f['alert_email']) . "'></td></tr>";

echo "<tr class='tab_bg_2'><td>Microsoft Teams Webhook</td>";
echo "<td><input type='url' class='form-control' name='teams_webhook' value='" .
     Html::entities_deep($f['teams_webhook']) . "'></td></tr>";

echo "<tr class='tab_bg_1'><td>" . __('Abrir ticket automático (alertas críticos)', 'm365license') . "</td><td>";
Dropdown::showYesNo('auto_create_ticket', $f['auto_create_ticket']);
echo "</td></tr>";

if (!empty($f['last_sync'])) {
    echo "<tr class='tab_bg_2'><td>" . __('Última sincronização', 'm365license') . "</td>";
    echo "<td>" . Html::convDateTime($f['last_sync']) . "</td></tr>";
}

echo "</table>";
echo "</div>";

echo "<div class='card-footer text-end'>";
echo "<button type='submit' name='test' class='btn btn-outline-primary me-2'>"
   . "<i class='ti ti-plug'></i> " . __('Testar conexão', 'm365license') . "</button>";
echo "<button type='submit' name='update' class='btn btn-primary'>"
   . "<i class='ti ti-device-floppy'></i> " . __('Salvar', 'm365license') . "</button>";
echo "</div>";

echo "</div>";
Html::closeForm();

Html::footer();
