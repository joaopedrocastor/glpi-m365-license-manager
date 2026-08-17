<?php
/**
 * Dashboard executivo: cards, KPIs financeiros e dados para gráficos.
 * Renderiza no padrão GLPI 10 (cards Bootstrap + Chart.js via js/m365.js).
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365Dashboard extends CommonGLPI {

    public static function getTypeName($nb = 0) {
        return __('Dashboard M365', 'm365');
    }

    /**
     * Coleta todos os indicadores para a tela.
     */
    public static function collect(): array {
        global $DB;
        $lic = PluginM365License::getGlobalStats();

        $totalUsers = 0;
        foreach ($DB->request(['SELECT' => ['COUNT' => '* AS c'], 'FROM' => 'glpi_plugin_m365_users',
                               'WHERE' => ['is_deleted' => 0]]) as $r) {
            $totalUsers = (int)$r['c'];
        }

        $openAlerts = 0;
        foreach ($DB->request(['SELECT' => ['COUNT' => '* AS c'], 'FROM' => 'glpi_plugin_m365_alerts',
                               'WHERE' => ['is_resolved' => 0]]) as $r) {
            $openAlerts = (int)$r['c'];
        }

        return [
            'cards' => [
                'total_users'      => $totalUsers,
                'total_licenses'   => $lic['total_contracted'],
                'used_licenses'    => $lic['total_consumed'],
                'free_licenses'    => $lic['total_available'],
                'idle_licenses'    => $lic['total_available'],
                'open_alerts'      => $openAlerts,
            ],
            'financial' => [
                'monthly_cost' => $lic['monthly_cost'],
                'annual_cost'  => $lic['monthly_cost'] * 12,
                'wasted_month' => $lic['wasted_cost'],
                'wasted_year'  => $lic['wasted_cost'] * 12,
            ],
            'by_type'       => self::licensesByType(),
            'by_department' => PluginM365User::countByDepartment(),
            'consumption'   => self::consumptionTrend(),
        ];
    }

    /** Licenças em uso por tipo (para gráfico de pizza/barras). */
    public static function licensesByType(): array {
        global $DB;
        $out = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_m365_licenses',
                               'WHERE' => ['is_deleted' => 0], 'ORDER' => 'consumed_units DESC']) as $row) {
            $out[$row['name']] = (int)$row['consumed_units'];
        }
        return $out;
    }

    /** Evolução do custo mensal consolidado (últimos 12 períodos). */
    public static function consumptionTrend(): array {
        global $DB;
        $out = [];
        $it = $DB->request([
            'SELECT'  => ['period', 'SUM' => 'monthly_cost AS total'],
            'FROM'    => 'glpi_plugin_m365_costs',
            'GROUPBY' => 'period',
            'ORDER'   => 'period ASC',
        ]);
        foreach ($it as $row) {
            $out[$row['period']] = (float)$row['total'];
        }
        return array_slice($out, -12, 12, true);
    }

    /**
     * Renderiza a tela do dashboard.
     */
    public static function show(): void {
        $d = self::collect();
        $c = $d['cards'];
        $f = $d['financial'];
        $fmt = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

        echo "<div class='m365-dashboard'>";
        echo "<div class='row row-cards'>";
        self::card(__('Usuários', 'm365'), $c['total_users'], 'ti-users', 'primary');
        self::card(__('Licenças totais', 'm365'), $c['total_licenses'], 'ti-license', 'info');
        self::card(__('Em uso', 'm365'), $c['used_licenses'], 'ti-circle-check', 'success');
        self::card(__('Disponíveis', 'm365'), $c['free_licenses'], 'ti-circle-dashed', 'secondary');
        self::card(__('Ociosas', 'm365'), $c['idle_licenses'], 'ti-alert-triangle', 'warning');
        self::card(__('Alertas abertos', 'm365'), $c['open_alerts'], 'ti-bell', 'danger');
        echo "</div>";

        // KPIs financeiros
        echo "<div class='row row-cards mt-3'>";
        self::card(__('Custo mensal', 'm365'), $fmt($f['monthly_cost']), 'ti-currency-dollar', 'primary');
        self::card(__('Custo anual', 'm365'), $fmt($f['annual_cost']), 'ti-calendar', 'info');
        self::card(__('Desperdício/mês', 'm365'), $fmt($f['wasted_month']), 'ti-trash', 'danger');
        self::card(__('Economia potencial/ano', 'm365'), $fmt($f['wasted_year']), 'ti-pig-money', 'success');
        echo "</div>";

        // Gráficos (dados via data-attrs consumidos por js/m365.js)
        $byType = htmlspecialchars(json_encode($d['by_type']), ENT_QUOTES);
        $byDept = htmlspecialchars(json_encode($d['by_department']), ENT_QUOTES);
        $trend  = htmlspecialchars(json_encode($d['consumption']), ENT_QUOTES);

        echo "<div class='row mt-4'>";
        echo "<div class='col-md-6'><div class='card'><div class='card-header'>"
           . __('Licenças por tipo', 'm365') . "</div><div class='card-body'>"
           . "<canvas id='m365ChartType' data-values='$byType'></canvas></div></div></div>";
        echo "<div class='col-md-6'><div class='card'><div class='card-header'>"
           . __('Usuários por departamento', 'm365') . "</div><div class='card-body'>"
           . "<canvas id='m365ChartDept' data-values='$byDept'></canvas></div></div></div>";
        echo "</div>";

        echo "<div class='row mt-4'><div class='col-12'><div class='card'><div class='card-header'>"
           . __('Evolução do consumo (R$)', 'm365') . "</div><div class='card-body'>"
           . "<canvas id='m365ChartTrend' data-values='$trend'></canvas></div></div></div></div>";

        echo "</div>";
    }

    private static function card(string $label, $value, string $icon, string $color): void {
        echo "<div class='col-sm-6 col-lg-2'>
                <div class='card card-sm'>
                  <div class='card-body'>
                    <div class='row align-items-center'>
                      <div class='col-auto'>
                        <span class='bg-$color text-white avatar'><i class='ti $icon'></i></span>
                      </div>
                      <div class='col'>
                        <div class='font-weight-medium'>$value</div>
                        <div class='text-muted'>$label</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>";
    }
}
