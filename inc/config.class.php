<?php
/**
 * Configuração da integração Microsoft Graph.
 * Linha única (id=1). Guarda credenciais OAuth e parâmetros de auditoria.
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365licenseConfig extends CommonDBTM {

    public static $rightname = 'config';

    protected static $config = null;

    public static function getTypeName($nb = 0) {
        return 'M365 License Manager';
    }

    /**
     * Retorna a instância única de configuração (cache em processo).
     */
    public static function getInstance(): PluginM365licenseConfig {
        if (self::$config === null) {
            self::$config = new self();
            if (!self::$config->getFromDB(1)) {
                self::$config->getEmpty();
            }
        }
        return self::$config;
    }

    /**
     * Chave de criptografia derivada da GLPIKEY do GLPI.
     * Usa o keyfile do próprio GLPI para não guardar chave em código.
     */
    protected static function getCryptoKey(): string {
        // GLPI 10: (new GLPIKey())->get() devolve a chave de aplicação.
        $glpikey = new GLPIKey();
        return hash('sha256', $glpikey->get() . '::m365', true);
    }

    /**
     * Criptografa um valor sensível (Client Secret / token).
     */
    public static function encrypt(string $plain): string {
        if ($plain === '') {
            return '';
        }
        $key = self::getCryptoKey();
        $iv  = random_bytes(16);
        $ct  = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ct);
    }

    /**
     * Descriptografa.
     */
    public static function decrypt(?string $stored): string {
        if (empty($stored)) {
            return '';
        }
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $key = self::getCryptoKey();
        $iv  = substr($raw, 0, 16);
        $ct  = substr($raw, 16);
        $plain = openssl_decrypt($ct, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }

    /**
     * Persiste config a partir do formulário.
     * Só re-criptografa o secret se um novo valor foi digitado.
     */
    public function updateConfig(array $input): bool {
        $data = [
            'id'                      => 1,
            'tenant_id'               => $input['tenant_id'] ?? '',
            'client_id'               => $input['client_id'] ?? '',
            'is_active'               => (int)($input['is_active'] ?? 0),
            'teams_webhook'           => $input['teams_webhook'] ?? '',
            'alert_email'             => $input['alert_email'] ?? '',
            'threshold_available'     => (int)($input['threshold_available'] ?? 10),
            'threshold_inactive_days' => (int)($input['threshold_inactive_days'] ?? 60),
            'auto_create_ticket'      => (int)($input['auto_create_ticket'] ?? 0),
            'sync_frequency'          => (int)($input['sync_frequency'] ?? 24),
        ];

        // Secret: campo vazio = mantém o atual.
        if (!empty($input['client_secret'])) {
            $data['client_secret'] = self::encrypt(trim($input['client_secret']));
        }

        self::$config = null; // invalida cache
        return $this->update($data);
    }

    /**
     * Retorna o Client Secret em claro (uso interno do GraphClient).
     */
    public function getClientSecret(): string {
        return self::decrypt($this->fields['client_secret'] ?? '');
    }

    /**
     * Salva o cache de token (JSON criptografado).
     */
    public function saveTokenCache(array $token): void {
        $this->update([
            'id'          => 1,
            'token_cache' => self::encrypt(json_encode($token)),
        ]);
        self::$config = null;
    }

    /**
     * Recupera o cache de token, se ainda válido.
     */
    public function getCachedToken(): ?array {
        $raw = self::decrypt($this->fields['token_cache'] ?? '');
        if ($raw === '') {
            return null;
        }
        $token = json_decode($raw, true);
        if (!is_array($token) || empty($token['access_token']) || empty($token['expires_at'])) {
            return null;
        }
        // Renova 5 min antes de expirar.
        if ($token['expires_at'] - 300 <= time()) {
            return null;
        }
        return $token;
    }

    public function isConfigured(): bool {
        return !empty($this->fields['tenant_id'])
            && !empty($this->fields['client_id'])
            && !empty($this->fields['client_secret']);
    }
}
