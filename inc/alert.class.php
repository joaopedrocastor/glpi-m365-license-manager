<?php
/**
 * Alertas de auditoria e canais de notificação
 * (GLPI Notifications, e-mail e Microsoft Teams Webhook).
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365Alert extends CommonDBTM {

    public static $rightname = 'plugin_m365_dashboard';

    const TYPE_LOW_STOCK          = 'low_stock';
    const TYPE_INACTIVE_USER      = 'inactive_user';
    const TYPE_DISABLED_LICENSED  = 'disabled_licensed';
    const TYPE_IDLE_LICENSE       = 'idle_license';

    public static function getTypeName($nb = 0) {
        return _n('Alerta', 'Alertas', $nb, 'm365');
    }

    /**
     * Cria um alerta se ainda não houver um idêntico não resolvido.
     */
    public static function raise(string $type, string $severity, string $title, string $message,
                                 ?int $refId = null, ?string $refType = null): ?int {
        global $DB;

        $crit = ['type' => $type, 'is_resolved' => 0];
        if ($refId !== null) {
            $crit['reference_id']   = $refId;
            $crit['reference_type'] = $refType;
        } else {
            $crit['title'] = $title;
        }
        if (iterator_count($DB->request(['FROM' => self::getTable(), 'WHERE' => $crit, 'LIMIT' => 1])) > 0) {
            return null; // já existe
        }

        $alert = new self();
        return (int)$alert->add([
            'type'           => $type,
            'severity'       => $severity,
            'title'          => $title,
            'message'        => $message,
            'reference_id'   => $refId,
            'reference_type' => $refType,
            'date_creation'  => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Envia notificações pendentes pelos canais configurados.
     */
    public static function dispatchPending(): int {
        global $DB;
        $config = PluginM365Config::getInstance();
        $sent = 0;

        foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => ['is_notified' => 0, 'is_resolved' => 0]]) as $row) {
            $ok = true;

            // Canal: e-mail
            if (!empty($config->fields['alert_email'])) {
                $ok = self::sendEmail($config->fields['alert_email'], $row) && $ok;
            }
            // Canal: Teams Webhook
            if (!empty($config->fields['teams_webhook'])) {
                $ok = self::sendTeams($config->fields['teams_webhook'], $row) && $ok;
            }
            // Canal: ticket automático (crítico)
            if ($config->fields['auto_create_ticket'] && $row['severity'] === 'critical') {
                self::createTicket($row);
            }

            if ($ok) {
                $DB->update(self::getTable(), ['is_notified' => 1], ['id' => $row['id']]);
                $sent++;
            }
        }
        return $sent;
    }

    protected static function sendEmail(string $to, array $alert): bool {
        // Usa a fila de e-mails nativa do GLPI via QueuedNotification seria o ideal;
        // aqui um envio direto simples para MVP.
        $subject = '[M365] ' . $alert['title'];
        return @mail($to, $subject, strip_tags($alert['message']),
            "Content-Type: text/plain; charset=UTF-8\r\n");
    }

    protected static function sendTeams(string $webhook, array $alert): bool {
        $color = ['critical' => 'D13438', 'warning' => 'F2A900', 'info' => '0078D4'][$alert['severity']] ?? '0078D4';
        $card = [
            '@type'      => 'MessageCard',
            '@context'   => 'http://schema.org/extensions',
            'themeColor' => $color,
            'summary'    => $alert['title'],
            'sections'   => [[
                'activityTitle'    => '🔔 ' . $alert['title'],
                'activitySubtitle' => 'M365 License Manager · GLPI',
                'text'             => $alert['message'],
            ]],
        ];
        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($card),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    protected static function createTicket(array $alert): void {
        $ticket = new Ticket();
        $id = $ticket->add([
            'name'    => '[M365] ' . $alert['title'],
            'content' => $alert['message'],
            'type'    => Ticket::INCIDENT_TYPE,
            'urgency' => 4,
            'status'  => Ticket::INCOMING,
        ]);
        if ($id) {
            global $DB;
            $DB->update(self::getTable(), ['tickets_id' => $id], ['id' => $alert['id']]);
        }
    }

    public function rawSearchOptions() {
        $tab = [];
        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];
        $tab[] = ['id' => 1, 'table' => self::getTable(), 'field' => 'title',
                  'name' => __('Título', 'm365'), 'datatype' => 'itemlink'];
        $tab[] = ['id' => 2, 'table' => self::getTable(), 'field' => 'type',
                  'name' => __('Tipo', 'm365'), 'datatype' => 'string'];
        $tab[] = ['id' => 3, 'table' => self::getTable(), 'field' => 'severity',
                  'name' => __('Severidade', 'm365'), 'datatype' => 'string'];
        $tab[] = ['id' => 4, 'table' => self::getTable(), 'field' => 'is_resolved',
                  'name' => __('Resolvido', 'm365'), 'datatype' => 'bool'];
        $tab[] = ['id' => 5, 'table' => self::getTable(), 'field' => 'date_creation',
                  'name' => __('Criado em', 'm365'), 'datatype' => 'datetime'];
        return $tab;
    }
}
