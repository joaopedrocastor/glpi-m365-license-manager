# Modelo relacional — M365 License Manager

## Diagrama (ER simplificado)

```
                       ┌──────────────────────────┐
                       │   glpi_plugin_m365_configs│  (linha única id=1)
                       │  tenant_id, client_id,    │
                       │  client_secret*, token*,  │
                       │  thresholds, webhook...   │
                       └──────────────────────────┘

┌────────────────────────┐        ┌──────────────────────────────┐
│ glpi_plugin_m365_users │        │  glpi_plugin_m365_licenses    │
│ id (PK)                │        │  id (PK)                      │
│ azure_id (UQ)          │        │  sku_id (UQ)                  │
│ user_principal_name    │        │  sku_part_number              │
│ mail, department, ...  │        │  total_units, consumed_units  │
│ account_enabled        │        │  unit_cost, currency          │
│ last_signin            │        └───────────┬──────────────────┘
│ license_count          │                    │
│ users_id → glpi_users  │                    │
└───────────┬────────────┘                    │
            │                                  │
            │   ┌──────────────────────────────────────────┐
            └──►│  glpi_plugin_m365_userlicenses (N:N)      │◄──┘
                │  plugin_m365_users_id (FK)                │
                │  plugin_m365_licenses_id (FK)             │
                │  UNIQUE(users_id, licenses_id)            │
                └──────────────────────────────────────────┘

┌───────────────────────────┐      ┌──────────────────────────┐
│ glpi_plugin_m365_costs    │      │ glpi_plugin_m365_alerts   │
│ licenses_id (FK) + period │      │ type, severity, message   │
│ monthly_cost, wasted_cost │      │ reference_id/type         │
│ UNIQUE(licenses_id,period)│      │ tickets_id → glpi_tickets │
└───────────────────────────┘      │ is_notified, is_resolved  │
                                   └──────────────────────────┘
┌───────────────────────────┐
│ glpi_plugin_m365_synclogs │  sync_type, status, counters, duration_ms
└───────────────────────────┘

(*) campos criptografados AES-256
```

## Tabelas

### `glpi_plugin_m365_configs`
Configuração única. Guarda credenciais OAuth (secret e token **criptografados**), endpoints, limites de alerta, webhook do Teams e e-mail de notificação.

### `glpi_plugin_m365_licenses`
Espelho de `/subscribedSkus`. Chave natural `sku_id` (GUID). `unit_cost`/`currency` são o overlay financeiro mantido no GLPI.

### `glpi_plugin_m365_users`
Espelho de `/users`. Chave natural `azure_id`. `users_id` faz o match opcional com `glpi_users` por e-mail/UPN. `last_signin` vem de `signInActivity`.

### `glpi_plugin_m365_userlicenses`
Relação N:N usuário ⇄ licença, reconstruída a cada sync a partir de `assignedLicenses[]`.

### `glpi_plugin_m365_costs`
Histórico mensal (`period = YYYY-MM`) por SKU: custo mensal e desperdício, para a série de "evolução de consumo".

### `glpi_plugin_m365_alerts`
Alertas de auditoria com deduplicação por `(type, reference_id)` enquanto não resolvidos; vínculo opcional a `glpi_tickets`.

### `glpi_plugin_m365_synclogs`
Telemetria de cada execução de cron (processados/criados/atualizados, duração, status, mensagem de erro).

> O SQL completo de criação está em [`hook.php`](../hook.php) (`plugin_m365_install()`), seguindo o padrão de `Migration` do GLPI (charset/collation/sign dinâmicos).
