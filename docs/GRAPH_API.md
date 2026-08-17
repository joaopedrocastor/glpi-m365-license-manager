# Microsoft Graph API — chamadas e exemplos

## 1. Obter token (OAuth 2.0 client_credentials)

```http
POST https://login.microsoftonline.com/{tenant_id}/oauth2/v2.0/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
&client_id={client_id}
&client_secret={client_secret}
&scope=https://graph.microsoft.com/.default
```

**Resposta:**
```json
{
  "token_type": "Bearer",
  "expires_in": 3599,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOi..."
}
```

## 2. Testar conexão / identificar tenant

```http
GET https://graph.microsoft.com/v1.0/organization?$select=displayName,id
Authorization: Bearer {access_token}
```
```json
{ "value": [ { "id": "aaaa-...", "displayName": "Contoso Ltda" } ] }
```

## 3. Licenças contratadas (SKUs)

```http
GET https://graph.microsoft.com/v1.0/subscribedSkus
Authorization: Bearer {access_token}
```
```json
{
  "value": [
    {
      "skuId": "05e9a617-0261-4cee-bb44-138d3ef5d965",
      "skuPartNumber": "SPE_E3",
      "consumedUnits": 142,
      "prepaidUnits": { "enabled": 150, "suspended": 0, "warning": 0 }
    },
    {
      "skuId": "cbdc14ab-d96c-4c30-b9f4-6ada7cdc1d46",
      "skuPartNumber": "SPB",
      "consumedUnits": 47,
      "prepaidUnits": { "enabled": 50, "suspended": 0, "warning": 0 }
    }
  ]
}
```
> `disponível = prepaidUnits.enabled − consumedUnits` · `%uso = consumedUnits / enabled`

## 4. Usuários + licenças + último login

```http
GET https://graph.microsoft.com/v1.0/users
    ?$select=id,displayName,userPrincipalName,mail,department,jobTitle,
             accountEnabled,createdDateTime,assignedLicenses,signInActivity
    &$top=999
Authorization: Bearer {access_token}
ConsistencyLevel: eventual
```
```json
{
  "@odata.nextLink": "https://graph.microsoft.com/v1.0/users?$skiptoken=...",
  "value": [
    {
      "id": "48d31887-5fad-4d73-a9f5-3c356e68a038",
      "displayName": "Ana Souza",
      "userPrincipalName": "ana.souza@contoso.com",
      "mail": "ana.souza@contoso.com",
      "department": "Financeiro",
      "jobTitle": "Analista",
      "accountEnabled": true,
      "createdDateTime": "2021-03-11T13:20:11Z",
      "assignedLicenses": [
        { "skuId": "05e9a617-0261-4cee-bb44-138d3ef5d965", "disabledPlans": [] }
      ],
      "signInActivity": { "lastSignInDateTime": "2026-08-01T09:14:52Z" }
    }
  ]
}
```

> **Permissões (Application):** `User.Read.All`, `Directory.Read.All`, `Organization.Read.All`.
> **`signInActivity`** exige `AuditLog.Read.All` **e** licença Entra ID P1/P2 no tenant. Sem isso, o campo vem ausente e o plugin trata `last_signin` como `NULL`.

## 5. Paginação

O plugin segue `@odata.nextLink` até o fim (`PluginM365licenseGraphClient::getAll()`), com processamento opcional em *streaming* por página para tenants grandes.

## 6. Throttling

Respostas **429** trazem `Retry-After` (segundos). O cliente aguarda e repete (até 4x). Boas práticas: `$select` para reduzir payload, `$top=999`, evitar chamadas por usuário.
