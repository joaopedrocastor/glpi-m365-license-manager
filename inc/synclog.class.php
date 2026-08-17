<?php
/**
 * Registro de execução de sincronização (auditoria/telemetria).
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365SyncLog extends CommonDBTM {

    public static $rightname = 'plugin_m365_dashboard';

    public static function getTypeName($nb = 0) {
        return _n('Log de sincronização', 'Logs de sincronização', $nb, 'm365');
    }

    /**
     * Abre um log 'running' e retorna o id + timestamp de início.
     *
     * @return array{0:int,1:float}
     */
    public static function start(string $type): array {
        $log = new self();
        $id = $log->add([
            'sync_type'     => $type,
            'status'        => 'running',
            'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
        return [(int)$id, microtime(true)];
    }

    /**
     * Finaliza o log com resultado.
     */
    public static function finish(int $id, float $startedAt, string $status, array $counters = [], string $message = ''): void {
        $log = new self();
        if (!$log->getFromDB($id)) {
            return;
        }
        $log->update([
            'id'              => $id,
            'status'          => $status,
            'items_processed' => (int)($counters['processed'] ?? 0),
            'items_created'   => (int)($counters['created'] ?? 0),
            'items_updated'   => (int)($counters['updated'] ?? 0),
            'message'         => Toolbox::substr($message, 0, 60000),
            'duration_ms'     => (int)round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
