<?php
/**
 * Cliente Microsoft Graph API.
 * Autenticação OAuth 2.0 (client_credentials) com renovação automática de token,
 * paginação (@odata.nextLink) e tratamento de throttling (429 / Retry-After).
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginM365licenseGraphClient {

    private PluginM365licenseConfig $config;
    private ?string $accessToken = null;

    public function __construct(?PluginM365licenseConfig $config = null) {
        $this->config = $config ?? PluginM365licenseConfig::getInstance();
    }

    // -----------------------------------------------------------------
    // Autenticação
    // -----------------------------------------------------------------

    /**
     * Garante um access token válido (usa cache; renova quando necessário).
     *
     * @throws RuntimeException
     */
    public function getAccessToken(): string {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $cached = $this->config->getCachedToken();
        if ($cached !== null) {
            return $this->accessToken = $cached['access_token'];
        }

        return $this->accessToken = $this->requestNewToken();
    }

    /**
     * Fluxo client_credentials contra o endpoint de token do tenant.
     */
    private function requestNewToken(): string {
        $tenant = $this->config->fields['tenant_id'] ?? '';
        $client = $this->config->fields['client_id'] ?? '';
        $secret = $this->config->getClientSecret();
        $login  = rtrim($this->config->fields['login_endpoint'] ?? 'https://login.microsoftonline.com', '/');

        if ($tenant === '' || $client === '' || $secret === '') {
            throw new RuntimeException('M365: credenciais OAuth incompletas.');
        }

        $url  = "$login/$tenant/oauth2/v2.0/token";
        $body = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $client,
            'client_secret' => $secret,
            'scope'         => 'https://graph.microsoft.com/.default',
        ]);

        [$httpCode, $resp, $err] = $this->rawCurl('POST', $url, $body, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if ($err) {
            throw new RuntimeException("M365: erro cURL no token: $err");
        }
        $data = json_decode($resp, true);
        if ($httpCode !== 200 || empty($data['access_token'])) {
            $msg = $data['error_description'] ?? $resp;
            throw new RuntimeException("M365: falha ao obter token ($httpCode): $msg");
        }

        $token = [
            'access_token' => $data['access_token'],
            'expires_at'   => time() + (int)($data['expires_in'] ?? 3599),
        ];
        $this->config->saveTokenCache($token);

        return $data['access_token'];
    }

    /**
     * Teste de conexão para a tela de configuração.
     *
     * @return array{ok:bool, message:string, org?:string}
     */
    public function testConnection(): array {
        try {
            $org = $this->get('/organization?$select=displayName,id');
            $name = $org['value'][0]['displayName'] ?? 'desconhecido';
            return ['ok' => true, 'message' => "Conectado ao tenant: $name", 'org' => $name];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------
    // Chamadas Graph
    // -----------------------------------------------------------------

    /**
     * GET em um recurso do Graph. $path relativo ao endpoint base.
     *
     * @throws RuntimeException
     */
    public function get(string $path): array {
        $base = rtrim($this->config->fields['graph_endpoint'] ?? 'https://graph.microsoft.com/v1.0', '/');
        $url  = (str_starts_with($path, 'http')) ? $path : $base . '/' . ltrim($path, '/');

        $attempt = 0;
        do {
            $attempt++;
            [$httpCode, $resp, $err, $headers] = $this->rawCurl('GET', $url, null, [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'Accept: application/json',
                'ConsistencyLevel: eventual',
            ]);

            if ($err) {
                throw new RuntimeException("M365 Graph cURL: $err");
            }

            // Token expirado no meio do caminho -> força renovação e repete 1x.
            if ($httpCode === 401 && $attempt === 1) {
                $this->accessToken = null;
                $this->config->saveTokenCache(['access_token' => '', 'expires_at' => 0]);
                continue;
            }

            // Throttling -> respeita Retry-After.
            if ($httpCode === 429 && $attempt <= 4) {
                $wait = (int)($headers['retry-after'] ?? 2);
                sleep(min($wait, 30));
                continue;
            }

            break;
        } while ($attempt <= 5);

        $data = json_decode($resp, true) ?? [];
        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $data['error']['message'] ?? substr((string)$resp, 0, 300);
            throw new RuntimeException("M365 Graph HTTP $httpCode: $msg");
        }
        return $data;
    }

    /**
     * GET com paginação automática, retornando o array acumulado de `value`.
     * $onPage (opcional) recebe cada página para processamento em streaming.
     */
    public function getAll(string $path, ?callable $onPage = null): array {
        $items = [];
        $next  = $path;
        do {
            $page  = $this->get($next);
            $value = $page['value'] ?? [];
            if ($onPage) {
                $onPage($value);
            } else {
                $items = array_merge($items, $value);
            }
            $next = $page['@odata.nextLink'] ?? null;
        } while ($next);

        return $items;
    }

    // -----------------------------------------------------------------
    // Helpers de domínio
    // -----------------------------------------------------------------

    /** Assinaturas/SKUs contratados no tenant. */
    public function getSubscribedSkus(): array {
        return $this->getAll('/subscribedSkus');
    }

    /** Usuários com campos relevantes + licenças atribuídas. */
    public function fetchUsers(?callable $onPage = null): array {
        $select = 'id,displayName,userPrincipalName,mail,department,jobTitle,'
                . 'accountEnabled,createdDateTime,assignedLicenses,signInActivity';
        return $this->getAll('/users?$select=' . $select . '&$top=999', $onPage);
    }

    // -----------------------------------------------------------------
    // cURL base
    // -----------------------------------------------------------------

    /**
     * @return array{0:int,1:string,2:string,3:array<string,string>}  [code, body, err, headers]
     */
    private function rawCurl(string $method, string $url, ?string $body, array $headers): array {
        $ch = curl_init($url);
        $respHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$respHeaders) {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($header);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        return [(int)$code, (string)$resp, $err, $respHeaders];
    }
}
