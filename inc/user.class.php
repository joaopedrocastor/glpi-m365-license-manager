<?php
/**
 * Usuário do Microsoft Entra ID sincronizado.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365User extends CommonDBTM {

    public static $rightname = 'plugin_m365_user';

    public static function getTypeName($nb = 0) {
        return _n('Usuário M365', 'Usuários M365', $nb, 'm365');
    }

    /**
     * Sincroniza um usuário do Graph. Atualiza também o vínculo N:N de licenças.
     *
     * @param array $gu  item de /users (com assignedLicenses e signInActivity)
     * @return string 'created'|'updated'
     */
    public function syncFromGraph(array $gu): string {
        $azureId = $gu['id'] ?? '';
        $now     = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

        $lastSignin = null;
        if (!empty($gu['signInActivity']['lastSignInDateTime'])) {
            $lastSignin = self::isoToSql($gu['signInActivity']['lastSignInDateTime']);
        }
        $created = !empty($gu['createdDateTime']) ? self::isoToSql($gu['createdDateTime']) : null;
        $licenses = $gu['assignedLicenses'] ?? [];

        $data = [
            'azure_id'            => $azureId,
            'display_name'        => $gu['displayName'] ?? '',
            'user_principal_name' => $gu['userPrincipalName'] ?? '',
            'mail'                => $gu['mail'] ?? '',
            'department'          => $gu['department'] ?? '',
            'job_title'           => $gu['jobTitle'] ?? '',
            'account_enabled'     => !empty($gu['accountEnabled']) ? 1 : 0,
            'last_signin'         => $lastSignin,
            'created_datetime'    => $created,
            'license_count'       => count($licenses),
            'is_deleted'          => 0,
            'date_mod'            => $now,
        ];

        $existing = new self();
        if ($existing->getFromDBByCrit(['azure_id' => $azureId])) {
            $data['id'] = $existing->getID();
            $this->update($data);
            $result = 'updated';
            $id = $existing->getID();
        } else {
            $data['date_creation'] = $now;
            $data['users_id'] = self::matchGlpiUser($data['mail'], $data['user_principal_name']);
            $id = $this->add($data);
            $result = 'created';
        }

        PluginM365UserLicense::syncForUser((int)$id, $licenses);
        return $result;
    }

    /** Converte data ISO8601 do Graph para DATETIME MySQL (UTC). */
    public static function isoToSql(string $iso): ?string {
        try {
            return (new DateTime($iso))->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Tenta casar com um glpi_users por email ou UPN. */
    public static function matchGlpiUser(string $mail, string $upn): ?int {
        global $DB;
        foreach ([$mail, $upn] as $candidate) {
            if ($candidate === '') {
                continue;
            }
            $it = $DB->request([
                'SELECT' => 'id',
                'FROM'   => 'glpi_users',
                'WHERE'  => ['OR' => ['name' => $candidate]],
                'LIMIT'  => 1,
            ]);
            foreach ($it as $row) {
                return (int)$row['id'];
            }
        }
        return null;
    }

    // -----------------------------------------------------------------
    // Consultas de auditoria
    // -----------------------------------------------------------------

    /** Usuários sem login há N dias (com licença ativa). */
    public static function findInactive(int $days): array {
        global $DB;
        $limit = date('Y-m-d H:i:s', strtotime("-$days days"));
        $rows = [];
        $it = $DB->request([
            'FROM'  => 'glpi_plugin_m365_users',
            'WHERE' => [
                'is_deleted'    => 0,
                'license_count' => ['>', 0],
                'OR' => [
                    ['last_signin' => ['<', $limit]],
                    ['last_signin' => null],
                ],
            ],
        ]);
        foreach ($it as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Contas desabilitadas que ainda possuem licença. */
    public static function findDisabledLicensed(): array {
        global $DB;
        $rows = [];
        $it = $DB->request([
            'FROM'  => 'glpi_plugin_m365_users',
            'WHERE' => ['is_deleted' => 0, 'account_enabled' => 0, 'license_count' => ['>', 0]],
        ]);
        foreach ($it as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Usuários sem nenhuma licença. */
    public static function findUnlicensed(): array {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_m365_users',
            'WHERE' => ['is_deleted' => 0, 'account_enabled' => 1, 'license_count' => 0],
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Usuários com múltiplas licenças. */
    public static function findMultiLicensed(): array {
        global $DB;
        $rows = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_plugin_m365_users',
            'WHERE' => ['is_deleted' => 0, 'license_count' => ['>', 1]],
        ]) as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Contagem de usuários por departamento (para gráficos). */
    public static function countByDepartment(): array {
        global $DB;
        $out = [];
        $it = $DB->request([
            'SELECT'  => ['department', 'COUNT' => '* AS cnt'],
            'FROM'    => 'glpi_plugin_m365_users',
            'WHERE'   => ['is_deleted' => 0],
            'GROUPBY' => 'department',
            'ORDER'   => 'cnt DESC',
        ]);
        foreach ($it as $row) {
            $out[$row['department'] ?: '(sem departamento)'] = (int)$row['cnt'];
        }
        return $out;
    }

    public function rawSearchOptions() {
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
        $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'display_name',
                  'name' => __('Nome', 'm365'), 'datatype' => 'itemlink'];
        $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'user_principal_name',
                  'name' => 'UPN', 'datatype' => 'string'];
        $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'mail',
                  'name' => __('E-mail', 'm365'), 'datatype' => 'email'];
        $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'department',
                  'name' => __('Departamento', 'm365'), 'datatype' => 'string'];
        $tab[] = ['id' => 5, 'table' => self::getTable(), 'field' => 'job_title',
                  'name' => __('Cargo', 'm365'), 'datatype' => 'string'];
        $tab[] = ['id' => 6, 'table' => self::getTable(), 'field' => 'account_enabled',
                  'name' => __('Ativo', 'm365'), 'datatype' => 'bool'];
        $tab[] = ['id' => 7, 'table' => self::getTable(), 'field' => 'last_signin',
                  'name' => __('Último login', 'm365'), 'datatype' => 'datetime'];
        $tab[] = ['id' => 8, 'table' => self::getTable(), 'field' => 'license_count',
                  'name' => __('Nº licenças', 'm365'), 'datatype' => 'number'];
        return $tab;
    }
}
