<?php
/**
 * Licença / SKU do Microsoft 365 contratada no tenant.
 * Espelha /subscribedSkus do Graph e agrega custo unitário.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365licenseLicense extends CommonDBTM {

    public static $rightname = 'plugin_m365license_license';

    public static function getTypeName($nb = 0) {
        return _n('Licença M365', 'Licenças M365', $nb, 'm365license');
    }

    /**
     * Mapa skuPartNumber => nome amigável dos principais planos.
     */
    public static function getKnownSkus(): array {
        return [
            'O365_BUSINESS_ESSENTIALS' => 'Microsoft 365 Business Basic',
            'O365_BUSINESS_PREMIUM'    => 'Microsoft 365 Business Standard',
            'SPB'                      => 'Microsoft 365 Business Premium',
            'STANDARDPACK'             => 'Office 365 E1',
            'ENTERPRISEPACK'          => 'Office 365 E3',
            'ENTERPRISEPREMIUM'        => 'Office 365 E5',
            'SPE_E3'                   => 'Microsoft 365 E3',
            'SPE_E5'                   => 'Microsoft 365 E5',
            'POWER_BI_PRO'             => 'Power BI Pro',
            'PBI_PREMIUM_PER_USER'     => 'Power BI Premium (por usuário)',
            'EXCHANGESTANDARD'         => 'Exchange Online (Plano 1)',
            'EXCHANGEENTERPRISE'       => 'Exchange Online (Plano 2)',
            'TEAMS_ESSENTIALS'         => 'Teams Essentials',
            'MCOEV'                    => 'Teams Phone',
            'FLOW_FREE'                => 'Power Automate (Free)',
        ];
    }

    /**
     * Insere/atualiza o catálogo com nomes amigáveis conhecidos
     * (chamado na instalação; SKUs reais são preenchidos na sincronização).
     */
    public static function seedKnownSkus(): void {
        // Nada a inserir sem sync real; método existe para extensão futura.
    }

    /**
     * Traduz um skuPartNumber para nome legível.
     */
    public static function friendlyName(string $skuPartNumber): string {
        return self::getKnownSkus()[$skuPartNumber] ?? $skuPartNumber;
    }

    /**
     * Sincroniza um SKU do Graph para a tabela local.
     * $sku = item de /subscribedSkus.
     *
     * @return string 'created'|'updated'
     */
    public function syncFromGraph(array $sku): string {
        $skuId       = $sku['skuId'] ?? '';
        $partNumber  = $sku['skuPartNumber'] ?? '';
        $total       = (int)($sku['prepaidUnits']['enabled'] ?? 0);
        $warning     = (int)($sku['prepaidUnits']['warning'] ?? 0);
        $suspended   = (int)($sku['prepaidUnits']['suspended'] ?? 0);
        $consumed    = (int)($sku['consumedUnits'] ?? 0);

        $existing = new self();
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        if ($existing->getFromDBByCrit(['sku_id' => $skuId])) {
            $existing->update([
                'id'             => $existing->getID(),
                'sku_part_number'=> $partNumber,
                'total_units'    => $total,
                'consumed_units' => $consumed,
                'warning_units'  => $warning,
                'suspended_units'=> $suspended,
                'is_deleted'     => 0,
                'date_mod'       => $now,
            ]);
            return 'updated';
        }

        $this->add([
            'sku_id'          => $skuId,
            'sku_part_number' => $partNumber,
            'name'            => self::friendlyName($partNumber),
            'total_units'     => $total,
            'consumed_units'  => $consumed,
            'warning_units'   => $warning,
            'suspended_units' => $suspended,
            'unit_cost'       => 0,
            'currency'        => 'BRL',
            'date_creation'   => $now,
        ]);
        return 'created';
    }

    /** Unidades disponíveis (não consumidas). */
    public function getAvailable(): int {
        return max(0, (int)$this->fields['total_units'] - (int)$this->fields['consumed_units']);
    }

    /** Percentual de utilização. */
    public function getUsagePercent(): float {
        $total = (int)$this->fields['total_units'];
        return $total > 0 ? round(((int)$this->fields['consumed_units'] / $total) * 100, 1) : 0.0;
    }

    /** Custo mensal (consumidas x unitário). */
    public function getMonthlyCost(): float {
        return (float)$this->fields['unit_cost'] * (int)$this->fields['consumed_units'];
    }

    /** Custo ocioso (disponíveis x unitário) = economia potencial. */
    public function getWastedCost(): float {
        return (float)$this->fields['unit_cost'] * $this->getAvailable();
    }

    /**
     * Totalizadores para o dashboard.
     */
    public static function getGlobalStats(): array {
        global $DB;
        $stats = [
            'total_contracted' => 0,
            'total_consumed'   => 0,
            'total_available'  => 0,
            'monthly_cost'     => 0.0,
            'wasted_cost'      => 0.0,
            'sku_count'        => 0,
        ];
        foreach ($DB->request(['FROM' => 'glpi_plugin_m365license_licenses', 'WHERE' => ['is_deleted' => 0]]) as $row) {
            $available = max(0, (int)$row['total_units'] - (int)$row['consumed_units']);
            $stats['total_contracted'] += (int)$row['total_units'];
            $stats['total_consumed']   += (int)$row['consumed_units'];
            $stats['total_available']  += $available;
            $stats['monthly_cost']     += (float)$row['unit_cost'] * (int)$row['consumed_units'];
            $stats['wasted_cost']      += (float)$row['unit_cost'] * $available;
            $stats['sku_count']++;
        }
        return $stats;
    }

    public function rawSearchOptions() {
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
        $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'name',
                  'name' => __('Nome', 'm365license'), 'datatype' => 'itemlink'];
        $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'sku_part_number',
                  'name' => 'SKU', 'datatype' => 'string'];
        $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'total_units',
                  'name' => __('Contratadas', 'm365license'), 'datatype' => 'number'];
        $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'consumed_units',
                  'name' => __('Em uso', 'm365license'), 'datatype' => 'number'];
        $tab[] = ['id' => 5, 'table' => self::getTable(), 'field' => 'unit_cost',
                  'name' => __('Custo unitário', 'm365license'), 'datatype' => 'decimal'];
        return $tab;
    }
}
