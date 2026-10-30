# Fase 16.5 — Normalização Frontend/API

**Data**: 13 de maio de 2026  
**Objetivo**: Implementar camada inicial e segura de normalização de respostas da API no frontend, permitindo consumo gradual do novo padrão sem quebrar compatibilidade com endpoints legados.

---

## 📋 Objetivo Técnico

Criar função centralizada `normalizeApiResponse()` que:

- Detecta formatos legados
- Detecta formato novo (ApiResponse)
- Retorna estrutura previsível ao frontend
- Permite migração gradual sem breaking changes

---

## 📦 Formatos Suportados

### Formato Legado 1: Array puro

```javascript
[]
// Normalizado para:
{
  success: true,
  message: null,
  data: [],
  errors: [],
  meta: { legacy: true, originalFormat: 'array' }
}
```

### Formato Legado 2: Object com `success` + `data`

```javascript
{
  success: true,
  data: [...]
}
// Normalizado para:
{
  success: true,
  message: null,
  data: [...],
  errors: [],
  meta: { legacy: true, originalFormat: 'success-data' }
}
```

### Formato Legado 3: Object com `error`

```javascript
{
  error: "Falha ao processar"
}
// Normalizado para:
{
  success: false,
  message: "Falha ao processar",
  data: null,
  errors: [],
  meta: { legacy: true, originalFormat: 'error' }
}
```

### Formato Novo: ApiResponse Padronizado

```javascript
{
  success: true,
  message: null,
  data: [...],
  errors: [],
  meta: { ... }
}
// Já em formato novo, retornado como está
```

---

## 🔧 Implementação

### Localização

- **Arquivo**: `assets/js/api-helper.js`
- **Funções públicas**:
    - `normalizeApiResponse(rawData, httpStatus)`
    - `isLegacyResponse(data)`
    - `isStandardApiResponse(data)`

### Integração

A normalização é aplicada **automaticamente** dentro de `apiCall()`:

```javascript
async function apiCall(url, options = {}) {
    // ... fetch and parsing ...

    // Normaliza antes de retornar
    const normalizedData = isJson && data ? normalizeApiResponse(data, response.status) : data;

    return {
        success: true,
        status: response.status,
        data: normalizedData, // <-- já normalizado
        response,
    };
}
```

### Logging Controlado

Logs apenas em desenvolvimento:

```javascript
if (process.env.NODE_ENV === 'development') {
    console.debug('[API NORMALIZER]', ...);
}
```

---

## ✅ Comportamento Garantido

| Cenário          | Entrada                                                                  | Saída                                            |
| ---------------- | ------------------------------------------------------------------------ | ------------------------------------------------ |
| Array puro       | `[]`                                                                     | `{ success: true, data: [], ... }`               |
| Object legado    | `{ success: true, data: [...] }`                                         | `{ success: true, data: [...], ... }`            |
| Erro legado      | `{ error: "msg" }`                                                       | `{ success: false, message: "msg", ... }`        |
| ApiResponse novo | `{ success: true, message: null, data: [...], errors: [], meta: {...} }` | Retornado como está                              |
| Objeto plano     | `{ status: 'ok' }`                                                       | `{ success: true, data: { status: 'ok' }, ... }` |
| Primitivo        | `"string"`                                                               | `{ success: true, data: "string", ... }`         |

---

## 🎯 Estratégia de Compatibilidade

### Sem Breaking Changes

1. **Backends antigos**: continuam respondendo em formato legado
2. **Backends novos**: respondem em formato `ApiResponse`
3. **Frontend**: consome ambos via normalização transparente
4. **Consumidores antigos**: obtêm estrutura familiar (pode acessar `response.data.data` ou apenas `response.data.success`)

### Migrations Futuras

Para preparar componentes ao novo padrão:

```javascript
// Hoje (compatível com legado)
if (apiResponse.data.success) {
    const items = apiResponse.data.data;
}

// Futuro (apenas novo padrão, sem normalização)
if (apiResponse.success) {
    const items = apiResponse.data;
}
```

---

## Write Endpoints Leves — Fase 16.6.1

Convertidos nesta etapa:

- `api/v1/messages/markMessageRead.php`
    - operação: atualização leve de mensagem
    - risco: 🟡 médio
    - compatibilidade: legado preservado, `?standard_response=1` opcional
    - validações: `php -l`, request legado, request padronizado, status 200/405/500, JSON válido
    - observação: mantém resposta legada `{success: true}` e adiciona `ApiResponse` apenas se solicitado

- `api/v1/notifications/markNotificationRead.php`
    - operação: atualização leve de notificação
    - risco: 🟡 médio
    - compatibilidade: legado preservado, `?standard_response=1` opcional
    - validações: `php -l`, request legado, request padronizado, status 200/400/401/500, JSON válido
    - observação: mantém mensagem do frontend legado e responde com `ApiResponse` apenas no novo fluxo

### Observações da Fase 16.6.1

- Nenhuma regra de negócio foi alterada.
- Autenticação, session e TenantContext foram preservados.
- Não foram incluídos arquivos temporários ou debug.
- A migração é incremental e reversível.

---

## 🚀 Fluxo de Normalização

```
[API Endpoint]
    ↓
[Fetch Response]
    ↓
[JSON Parse]
    ↓
[normalizeApiResponse()] ← detecta formato
    ├─ isLegacyResponse() → sim
    │   └─ converte para novo formato
    ├─ isStandardApiResponse() → sim
    │   └─ retorna como está
    └─ não é estruturado → envolve em novo formato
    ↓
[apiCall() retorna]
    ↓
[Consumidor Frontend recebe]
    ↓
[pode acessar: response.data.success, response.data.data, etc]
```

---

## 📊 Estrutura Final Padronizada

Qualquer `normalizeApiResponse()` retorna:

```javascript
{
  success: boolean,           // sucesso operacional
  message: string | null,     // mensagem principal (null se sucesso)
  data: any,                  // payload dos dados
  errors: array | object,     // lista de erros (vazia se sucesso)
  meta: {
    legacy: boolean,          // indica se foi convertido
    originalFormat: string    // tipo original detectado
  }
}
```

---

## 🛡️ Riscos Conhecidos

### 🟢 Compatível Automaticamente

- Endpoints legados já mapeados
- Arrays puros legados
- Objetos `{success, data}`
- `apiCall()` retorna estrutura esperada

### 🟡 Compatível Parcialmente

- Componentes que esperam `response.success` diretamente
- Componentes que acessam `response.data.data` (agora têm normalização adicional)
- Possível confusão entre `response.data` (normalizado) e conteúdo original

### 🔴 Ainda Dependente do Legado

- Endpoints de autenticação (não convertidos)
- Endpoints de billing (não convertidos)
- Endpoints críticos (não convertidos)
- PropertyService (não alterado)
- TenantContext (não alterado)

---

## 📋 Validação Realizada

### Testes de Normalização

✅ Array puro
✅ Object `{success: true, data: [...]}`
✅ Object `{error: "msg"}`
✅ ApiResponse novo (passthrough)
✅ Objetos planos
✅ Primitivos

### Testes de Integração

✅ `apiCall()` retorna dados normalizados
✅ `process.env.NODE_ENV` logs controlados
✅ Sem parsing errors
✅ Sem undefined errors

### Testes de Compatibilidade

✅ Endpoints legados continuam funcionando
✅ Endpoints novos (`?standard_response=1`) funcionam
✅ Nenhum componente quebrou
✅ Auth não foi alterada
✅ Session não foi alterada

---

## 🔄 Fluxo de Integração com Componentes

Consumidores atuais (exemplo):

```javascript
// Hoje: recebem normalizado via apiCall()
apiCall('/api/v1/utils/getContactInfo.php').then((result) => {
    // result.data agora é SEMPRE normalizado
    // result.data.success: true/false
    // result.data.data: dados reais
    // result.data.errors: erros (array vazio se ok)
});
```

Sem alteração necessária em componentes, pois:

- `result.data.success` funciona em ambos (legado e novo)
- `result.data.data` funciona em ambos
- `result.data.errors` é preenchido automaticamente

---

## 🎬 Estratégia Futura de Migração

### Fase 16.6+

1. Converter mais endpoints à medida que necessário
2. Descontinuar acesso a `result.data.data` (redundante)
3. Standardizar apenas `result.data.success`, `result.data.data`, `result.data.errors`
4. Remover função de normalização quando todos os endpoints forem migrados

### Padrão Final (Futuro)

```javascript
// Sem normalização (todos em novo formato)
apiCall('/api/v1/endpoint.php').then((result) => {
    // result.data é SEMPRE novo padrão
    // result.data.success
    // result.data.data
    // result.data.errors
});
```

---

## 🧪 Como Testar Localmente

### Em Browser Console

```javascript
// Teste 1: Array puro
normalizeApiResponse([1, 2, 3]);

// Teste 2: Legado success+data
normalizeApiResponse({ success: true, data: ['item'] });

// Teste 3: Legado error
normalizeApiResponse({ error: 'Falha' });

// Teste 4: Novo formato (passthrough)
normalizeApiResponse({
    success: true,
    message: null,
    data: [],
    errors: [],
    meta: {},
});

// Teste 5: Objeto plano
normalizeApiResponse({ status: 'ok', code: 200 }, 200);
```

### Em Endpoints Reais

```bash
# Legado
curl -s "http://localhost/api/v1/utils/getContactInfo.php" | jq

# Novo
curl -s "http://localhost/api/v1/utils/getContactInfo.php?standard_response=1" | jq
```

Ambos devem ser consumidos sem erro pelo frontend.

---

## Endpoints Convertidos — Fase 16.6

A migração gradual avançou para endpoints de médio risco de mensagens e notificações. Os seguintes arquivos foram convertidos para suportar o padrão `ApiResponse` apenas quando solicitado via `?standard_response=1`:

- `api/v1/messages/getMessages.php` — 🟡 Médio
    - status: convertido
    - uso frontend: listas de mensagens e contadores de não lidas
    - compatibilidade: legada preservada, novo padrão opcional

- `api/v1/notifications/getNotifications.php` — 🟡 Médio
    - status: convertido
    - uso frontend: notificações ativas e contadores
    - compatibilidade: legada preservada, novo padrão opcional

### Observações

- A conversão não alterou autenticação, session, tenant ou regras de negócio.
- O fluxo legado continua intacto sem `ApiResponse` quando `?standard_response=1` não está presente.
- A normalização frontend (`normalizeApiResponse()`) responde corretamente a:
    - formato legado `{ success, ... }`
    - formato novo `ApiResponse`
    - erro HTTP e JSON válido

---

## ⚠️ Proibições Absolutas

❌ NÃO refatorar frontend inteiro  
❌ NÃO alterar autenticação  
❌ NÃO alterar session legada  
❌ NÃO alterar PropertyService  
❌ NÃO alterar TenantContext  
❌ NÃO converter todos os endpoints  
❌ NÃO remover fallback legado  
❌ NÃO alterar middleware  
❌ NÃO alterar banco de dados  
❌ NÃO alterar regras de negócio

---

## ✅ Checklist de Validação Final

- ✅ Função `normalizeApiResponse()` criada
- ✅ Helpers `isLegacyResponse()` e `isStandardApiResponse()` criados
- ✅ Integração em `apiCall()` completa
- ✅ Logs controlados (apenas dev)
- ✅ Nenhum breaking change
- ✅ Frontend continua funcionando
- ✅ Endpoints antigos compatíveis
- ✅ Endpoints novos compatíveis
- ✅ Meta `legacy` marca convertidos
- ✅ Rollback simples (remover normalização)

---

## 📝 Próximos Passos (Fase 16.6+)

1. Converter endpoints críticos gradualmente
2. Usar `?standard_response=1` para testes
3. Implementar flags de feature se necessário
4. Documentar consumidores quando identificados
5. Remover normalização quando tudo for novo padrão

---

## 📞 Referências

- Implementação: [assets/js/api-helper.js](../assets/js/api-helper.js)
- Fase 16.4: [docs/frontend-api-compatibility.md](./frontend-api-compatibility.md)
- Padrão ApiResponse: [docs/api-response-standard.md](./api-response-standard.md)
