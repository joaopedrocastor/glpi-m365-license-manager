# Roadmap — M365 License Manager

## ✅ MVP (v1.0) — incluído neste repositório

- [x] Estrutura oficial de plugin GLPI 10 (setup/hook/install/uninstall)
- [x] 7 tabelas MySQL + Migration
- [x] Integração Graph: OAuth2 client_credentials, teste de conexão, renovação e cache de token
- [x] Client Secret e token criptografados (AES-256)
- [x] Sincronização de usuários (streaming/paginação) e licenças (SKUs)
- [x] Vínculo N:N usuário ⇄ licença
- [x] Auditoria: inativos (30/60/90), desabilitados licenciados, sem licença, múltiplas licenças
- [x] Dashboard executivo (cards + 3 gráficos Chart.js)
- [x] Financeiro: custo unitário, custo mensal/anual, desperdício/economia
- [x] Alertas → GLPI/e-mail/Teams Webhook + ticket automático
- [x] Relatórios CSV/Excel/PDF
- [x] Cron: sync usuários, sync licenças, alertas, consolidação mensal
- [x] Controle de permissões por rightname + logs de sync

## 🔜 v1.1 — Robustez

- [ ] Fila nativa `QueuedNotification` (em vez de `mail()` direto)
- [ ] Aba M365 dentro da ficha do `User` do GLPI (tab hook)
- [ ] SearchOptions completas + massive actions (revogar licença → ticket)
- [ ] i18n completo (`locales/*.po`) pt_BR/en_GB/es_ES
- [ ] Testes (PHPUnit) do GraphClient com mock HTTP
- [ ] Tela de histórico de `synclogs` com filtros

## 💎 Premium (v2.x) — comercialização

- [ ] **Recomendações de otimização** (right-sizing): sugerir downgrade E5→E3, remoção de ociosas, com economia estimada
- [ ] **Simulador de cenários** de custo (o que acontece se remover X licenças)
- [ ] **Provisionamento** (Graph write): atribuir/remover licença a partir do GLPI (com aprovação/ticket)
- [ ] **Multi-tenant** (MSPs): vários tenants Entra ID em uma instância GLPI
- [ ] **Mapa de planos**: SKU → serviços (Exchange, Teams, Intune...) e detecção de sobreposição
- [ ] **Câmbio/moeda** automática e rateio por centro de custo / entidade GLPI
- [ ] **Dashboard nativo GLPI** (Grid API) e widgets exportáveis
- [ ] **Alertas preditivos**: projeção de esgotamento de estoque por tendência
- [ ] **Conector de billing** (Microsoft Cost Management / CSP) para custo real
- [ ] **Relatórios agendados** por e-mail (PDF executivo mensal)
- [ ] **Licenciamento do plugin** + updater e suporte

## Precificação sugerida (referência)

| Edição | Público | Recursos |
|---|---|---|
| Community | Grátis (GPL) | MVP v1.x |
| Pro | Assinatura anual/tenant | Otimização, provisionamento, relatórios agendados |
| MSP | Por tenant gerenciado | Multi-tenant, rateio, billing real |
