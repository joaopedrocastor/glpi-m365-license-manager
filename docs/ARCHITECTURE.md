# Arquitetura — M365 License Manager

## Visão de componentes

```
┌────────────────────────────────────────────────────────────────────┐
│                             GLPI 10.x                               │
│                                                                     │
│   ┌───────────────┐   ┌──────────────────┐   ┌──────────────────┐  │
│   │  Front (UI)   │   │   Cron (motor)   │   │  Notificações    │  │
│   │ dashboard.php │   │ PluginM365license       │   │ GLPI / e-mail /  │  │
│   │ config.form   │   │ CronTask         │   │ Teams Webhook    │  │
│   │ user/license  │   └────────┬─────────┘   └────────▲─────────┘  │
│   │ cost/report   │            │                      │            │
│   └──────┬────────┘            │                      │            │
│          │      ┌──────────────┴──────────────┐       │            │
│          ▼      ▼                              ▼       │            │
│   ┌─────────────────────────────────────────────────────────────┐ │
│   │                      Camada de domínio                       │ │
│   │  Config · GraphClient · User · License · UserLicense ·      │ │
│   │  Cost · Alert · SyncLog · Dashboard · Report                │ │
│   └───────┬───────────────────────────────────┬─────────────────┘ │
│           │                                   │                    │
│           ▼                                   ▼                    │
│   ┌───────────────┐                   ┌──────────────────┐         │
│   │  MySQL/MariaDB│                   │  OpenSSL (AES-256)│        │
│   │  glpi_plugin_ │                   │  cripto secret/   │        │
│   │  m365license_*│                   │  token            │        │
│   └───────────────┘                   └──────────────────┘         │
└───────────────────────────────┬────────────────────────────────────┘
                                │ HTTPS (OAuth2 + REST)
                                ▼
              ┌──────────────────────────────────────┐
              │         Microsoft Graph API          │
              │  /oauth2/v2.0/token  /subscribedSkus │
              │  /users  /organization               │
              └──────────────────────────────────────┘
                                │
                                ▼
                    Microsoft Entra ID (Azure AD)
```

## Camadas

- **Front (`front/`)** — telas no padrão GLPI 10 (Bootstrap/Tabler): dashboard, configuração, listas (Search engine nativo), financeiro, relatórios, alertas.
- **Domínio (`inc/`)** — classes `CommonDBTM`/`CommonGLPI`. `PluginM365licenseGraphClient` isola toda a comunicação HTTP; as demais são regras de negócio e persistência.
- **Persistência** — 7 tabelas MySQL (ver [DATABASE.md](DATABASE.md)).
- **Segurança** — `PluginM365licenseConfig` criptografa Client Secret e cache de token com AES-256-CBC; chave derivada da `GLPIKEY`.
- **Cron (`PluginM365licenseCronTask`)** — orquestra sincronização, auditoria e consolidação de custos.

## Fluxo completo de sincronização

```
Cron GLPI dispara cronSyncLicenses
   └─> GraphClient.getAccessToken()  (cache? senão client_credentials)
   └─> GET /subscribedSkus  (paginação @odata.nextLink)
   └─> PluginM365licenseLicense.syncFromGraph()  → upsert por sku_id
   └─> SyncLog(success)

Cron GLPI dispara cronSyncUsers
   └─> GraphClient.fetchUsers()  (streaming por página, $top=999)
        └─> por usuário:
             ├─ PluginM365licenseUser.syncFromGraph()  → upsert por azure_id
             │    └─ match glpi_users (email/UPN)
             └─ PluginM365licenseUserLicense.syncForUser()  → reconstrói N:N
   └─> Config.last_sync = agora
   └─> SyncLog(success)

Cron GLPI dispara cronGenerateAlerts
   └─> regras de auditoria (estoque, inativos, desabilitados, ociosas)
   └─> Alert.raise() (deduplicado por referência)
   └─> Alert.dispatchPending()  → e-mail / Teams / ticket
   └─> SyncLog(success)

Cron GLPI dispara cronMonthlyReport
   └─> upsert glpi_plugin_m365license_costs por (licença, período YYYY-MM)
```

## Tratamento de erros e resiliência

- **401** no meio de uma chamada → invalida token e repete 1x.
- **429** (throttling) → respeita header `Retry-After`, até 4 tentativas.
- Falhas registradas em `glpi_plugin_m365license_synclogs` com `status=error` e mensagem.
- Sincronização de usuários em **streaming por página** para não estourar memória em tenants grandes.
