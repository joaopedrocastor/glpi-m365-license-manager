<?php
/**
 * Geração de relatórios (CSV nativo; PDF/Excel via bibliotecas do GLPI).
 * O GLPI 10 embarca mPDF e PhpSpreadsheet em vendor/ — usados aqui quando disponíveis.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365Report extends CommonGLPI {

    const R_USER_LICENSE = 'user_license';
    const R_BY_DEPT      = 'by_department';
    const R_IDLE         = 'idle';
    const R_COST_MONTH   = 'cost_month';
    const R_COST_YEAR    = 'cost_year';

    public static function getTypeName($nb = 0) {
        return _n('Relatório', 'Relatórios', $nb, 'm365');
    }

    /**
     * Monta as linhas (cabeçalho + dados) de um relatório.
     *
     * @return array{0:array<string>,1:array<array>}  [headers, rows]
     */
    public static function build(string $type): array {
        global $DB;

        switch ($type) {
            case self::R_USER_LICENSE:
                $headers = ['Usuário', 'UPN', 'Departamento', 'Ativo', 'Último login', 'Nº licenças'];
                $rows = [];
                foreach ($DB->request(['FROM' => 'glpi_plugin_m365_users', 'WHERE' => ['is_deleted' => 0],
                                       'ORDER' => 'display_name']) as $u) {
                    $rows[] = [$u['display_name'], $u['user_principal_name'], $u['department'],
                               $u['account_enabled'] ? 'Sim' : 'Não', $u['last_signin'] ?: '-', $u['license_count']];
                }
                return [$headers, $rows];

            case self::R_BY_DEPT:
                $headers = ['Departamento', 'Usuários'];
                $rows = [];
                foreach (PluginM365User::countByDepartment() as $dept => $cnt) {
                    $rows[] = [$dept, $cnt];
                }
                return [$headers, $rows];

            case self::R_IDLE:
                $headers = ['Licença', 'Contratadas', 'Em uso', 'Ociosas', 'Custo unit.', 'Desperdício/mês'];
                $rows = [];
                foreach ($DB->request(['FROM' => 'glpi_plugin_m365_licenses', 'WHERE' => ['is_deleted' => 0]]) as $l) {
                    $idle = max(0, (int)$l['total_units'] - (int)$l['consumed_units']);
                    $rows[] = [$l['name'], $l['total_units'], $l['consumed_units'], $idle,
                               number_format((float)$l['unit_cost'], 2, ',', '.'),
                               number_format($idle * (float)$l['unit_cost'], 2, ',', '.')];
                }
                return [$headers, $rows];

            case self::R_COST_MONTH:
            case self::R_COST_YEAR:
                $mult = $type === self::R_COST_YEAR ? 12 : 1;
                $headers = ['Licença', 'Em uso', 'Custo unit.', $mult === 12 ? 'Custo anual' : 'Custo mensal'];
                $rows = [];
                foreach ($DB->request(['FROM' => 'glpi_plugin_m365_licenses', 'WHERE' => ['is_deleted' => 0]]) as $l) {
                    $cost = (float)$l['unit_cost'] * (int)$l['consumed_units'] * $mult;
                    $rows[] = [$l['name'], $l['consumed_units'],
                               number_format((float)$l['unit_cost'], 2, ',', '.'),
                               number_format($cost, 2, ',', '.')];
                }
                return [$headers, $rows];
        }
        return [[], []];
    }

    /**
     * Exporta como CSV (download direto).
     */
    public static function exportCsv(string $type): void {
        [$headers, $rows] = self::build($type);
        header('Content-Type: text/csv; charset=UTF-8');
        header("Content-Disposition: attachment; filename=m365_{$type}_" . date('Ymd') . '.csv');
        echo "\xEF\xBB\xBF"; // BOM p/ Excel
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers, ';');
        foreach ($rows as $r) {
            fputcsv($out, $r, ';');
        }
        fclose($out);
    }

    /**
     * Exporta como Excel usando PhpSpreadsheet embarcado no GLPI.
     */
    public static function exportXlsx(string $type): void {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            self::exportCsv($type); // fallback
            return;
        }
        [$headers, $rows] = self::build($type);
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows, null, 'A2');
        foreach (range('A', chr(64 + max(1, count($headers)))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=m365_{$type}_" . date('Ymd') . '.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save('php://output');
    }

    /**
     * Exporta como PDF usando mPDF embarcado no GLPI.
     */
    public static function exportPdf(string $type, string $title): void {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            self::exportCsv($type);
            return;
        }
        [$headers, $rows] = self::build($type);
        $html = "<h2>$title</h2><p>Gerado em " . date('d/m/Y H:i') . "</p>";
        $html .= "<table border='1' cellpadding='4' style='border-collapse:collapse;width:100%'>";
        $html .= '<thead><tr>';
        foreach ($headers as $h) {
            $html .= "<th style='background:#0078D4;color:#fff'>" . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>';
            foreach ($r as $cell) {
                $html .= '<td>' . htmlspecialchars((string)$cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        $mpdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L']);
        $mpdf->WriteHTML($html);
        $mpdf->Output("m365_{$type}_" . date('Ymd') . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
    }
}
