# M365 License Manager — Plugin GLPI 10.x

Governança, auditoria e controle de custos de licenças **Microsoft 365** dentro do GLPI, integrado ao **Microsoft Entra ID (Azure AD)** via **Microsoft Graph API**.

> ⚠️ **Status:** MVP funcional (núcleo de integração, sincronização, auditoria, financeiro, alertas, relatórios e cron). Telas em português.

## ✨ Funcionalidades

| Módulo | Descrição |
|---|---|
| 🔐 Integração Graph | OAuth 2.0 (client_credentials), teste de conexão, renovação automática de token, Client Secret criptografado (AES-256) |
| 👥 Sincronização de usuários | Nome, e-mail, UPN, departamento, cargo, status da conta, último login, data de criação |
| 📦 Inventário de licenças | SKUs contratados (total, em uso, disponível, % utilização) |
| 🔎 Auditoria | Inativos 30/60/90 dias, contas desabilitadas licenciadas, sem licença, múltiplas licenças |
| 📊 Dashboard executivo | Cards + gráficos (por tipo, por departamento, evolução de consumo) |
| 💰 Financeiro | Custo unitário por SKU, custo mensal/anual, economia potencial, desperdício |
| 🔔 Alertas | Estoque baixo, inativos, desabilitados licenciados, ociosas → GLPI/E-mail/Teams Webhook |
| 🎫 Tickets | Abertura automática para alertas críticos |
| 📄 Relatórios | CSV, Excel (PhpSpreadsheet) e PDF (mPDF) |
| ⏱️ Cron | Sync usuários, sync licenças, geração de alertas, consolidação mensal |

## 📋 Requisitos

- GLPI **10.0.x**
- PHP **8.0+** com extensões `curl` e `openssl`
- App registrado no **Microsoft Entra ID** com permissões **Application**:
  - `User.Read.All`
  - `Directory.Read.All`
  - `AuditLog.Read.All` (para `signInActivity` / último login)
  - `Organization.Read.All`
- Consentimento de administrador concedido

## 🚀 Instalação

```bash
cd /var/www/glpi/plugins
git clone https://github.com/joaopedrocastor/glpi-m365-license-manager.git m365
```

1. GLPI → **Configurar → Plugins** → instalar e ativar **M365 License Manager**.
2. Abrir a engrenagem de configuração (ou `front/config.form.php`).
3. Informar **Tenant ID**, **Client ID**, **Client Secret** → **Testar conexão**.
4. Ativar a sincronização e configurar limites/alertas.
5. Rodar o cron do GLPI (ou aguardar a execução automática).

> A pasta do plugin **deve** se chamar `m365`.

## 🗄️ Modelo de dados

`glpi_plugin_m365_configs`, `_licenses`, `_users`, `_userlicenses`, `_costs`, `_alerts`, `_synclogs`.
Ver [docs/DATABASE.md](docs/DATABASE.md) e [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

## 🔑 Segurança

- Client Secret e cache de token **criptografados** com AES-256-CBC (chave derivada da `GLPIKEY`).
- Controle por **rightnames** (`plugin_m365_dashboard`, `_user`, `_license`).
- Logs de sincronização auditáveis (`_synclogs`).

## 🛣️ Roadmap

Ver [docs/ROADMAP.md](docs/ROADMAP.md).

## 📜 Licença

GPLv3+.
