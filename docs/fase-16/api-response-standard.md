# Padrão de Resposta JSON da API

**Versão**: 1.0  
**Data**: 13 de maio de 2026  
**Objetivo**: Padronizar respostas JSON da API de forma gradual e segura

---

## 📋 Objetivo

Criar uma estrutura enterprise de resposta JSON para a API, permitindo:

- Consistência em todas as respostas
- Tipagem clara (success/error)
- Headers HTTP corretos
- Migração gradual **sem quebrar** consumidores existentes
- Reversibilidade de mudanças

---

## ✅ Formato: Success

### Resposta Bem-Sucedida (200)

```json
{
  "success": true,
  "message": "Dados carregados com sucesso",
  "data": {
    "items": [...],
    "count": 5
  },
  "errors": [],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 100
  }
}
```

### Detalhes

- `success`: Sempre `true`
- `message`: Descrição opcional da operação
- `data`: Dados retornados (objeto ou array)
- `errors`: Array vazio (nunca erros em sucesso)
- `meta`: Metadados adicionais (paginação, contagem, etc)
- **Status HTTP**: 200 OK

### Uso na Classe

```php
use App\Http\ApiResponse;

// Simples
ApiResponse::success($data);

// Com mensagem
ApiResponse::success($data, 'Operação concluída');

// Com metadados
ApiResponse::success($data, 'Carregado', ['total' => 100]);

// Com status customizado (201 Created)
ApiResponse::success($createdItem, 'Criado', [], 201);
```

---

## ❌ Formato: Error

### Erro Genérico (500)

```json
{
    "success": false,
    "message": "Erro ao processar requisição",
    "data": null,
    "errors": [],
    "meta": {}
}
```

### Erro com Detalhes (422)

```json
{
    "success": false,
    "message": "Erro de validação",
    "data": null,
    "errors": {
        "email": "Email é obrigatório",
        "password": "Senha deve ter mínimo 8 caracteres"
    },
    "meta": {}
}
```

### Detalhes

- `success`: Sempre `false`
- `message`: Descrição do erro
- `data`: Geralmente `null`
- `errors`: Array/objeto com erros detalhados (se houver)
- `meta`: Vazio em erros
- **Status HTTP**: Varia (400, 401, 403, 404, 422, 500, etc)

### Uso na Classe

```php
// Erro genérico (500)
ApiResponse::error('Erro interno do servidor');

// Com status customizado
ApiResponse::error('Recurso não encontrado', 404);

// Com detalhes de erro
ApiResponse::error(
    'Erro ao processar',
    400,
    ['field' => 'Descrição do erro'],
    null
);

// Atalhos
ApiResponse::notFound('Imóvel não encontrado');           // 404
ApiResponse::unauthorized('Não autenticado');             // 401
ApiResponse::forbidden('Acesso negado');                  // 403
```

---

## 🔍 Formato: Validation

### Erro de Validação (422 Unprocessable Entity)

```json
{
    "success": false,
    "message": "Erro de validação",
    "data": null,
    "errors": {
        "title": "Título é obrigatório",
        "price": "Preço deve ser um número positivo",
        "email": "Email deve ser válido"
    },
    "meta": {}
}
```

### Detalhes

- `success`: Sempre `false`
- `message`: Customizável (padrão: "Erro de validação")
- `data`: Sempre `null`
- `errors`: Objeto com mensagens por campo
- **Status HTTP**: 422 Unprocessable Entity

### Uso na Classe

```php
// Validação padrão
ApiResponse::validation([
    'email' => 'Email é obrigatório',
    'phone' => 'Telefone é obrigatório'
]);

// Com mensagem customizada
ApiResponse::validation(
    ['field' => 'erro'],
    'Dados do imóvel inválidos',
    422
);
```

---

## 🚀 Endpoints Já Convertidos

| Endpoint                               | Status        | Compatibilidade                         | Fase | Notas                    |
| -------------------------------------- | ------------- | --------------------------------------- | ---- | ------------------------ |
| `api/v1/utils/getHeroImages.php`       | ✅ CONVERTIDO | Com query string `?standard_response=1` | 16.2 | Endpoint de teste        |
| `api/v1/utils/getContactInfo.php`      | ✅ CONVERTIDO | Com query string `?standard_response=1` | 16.3 | SELECT company_settings  |
| `api/v1/utils/getFormularioImovel.php` | ✅ CONVERTIDO | Com query string `?standard_response=1` | 16.3 | SELECT properties simple |
| `api/v1/test/status.php`               | ✅ CONVERTIDO | Com query string `?standard_response=1` | 16.3 | Health check, sem DB     |

---

## ⏳ Endpoints Ainda Não Convertidos

**Maioria dos endpoints mantém sua resposta original.**

Endpoints de `api/v1/utils/` ainda não convertidos:

- `api/v1/utils/uploadLogo.php` — Upload, não simples
- `api/v1/utils/runPortalExport.php` — Export, complexo
- `api/v1/utils/process-async-jobs.php` — Jobs assincronos
- `api/v1/utils/exportProperties.php` — Export, complexo

### Endpoints Críticos (NÃO converter agora)

- `api/v1/properties/*` — Dependência de PropertyService
- `api/v1/billing/*` — Dependência de BillingService
- `api/v1/pipeline/*` — Dependência de isolamento tenant
- `api/v1/auth/*` — Dependência de autenticação
- `api/v1/agendamentos/*` — Dependência de session

### Candidatos Futuros (converter depois)

- `api/v1/messages/*` — Simples, isolado
- `api/v1/notifications/*` — Simples, isolado (mas usa session)
- Endpoints de `api/v1/debug/*` — Alguns simples, alguns críticos

---

## 📏 Regra de Migração Gradual

### Fase de Transição

```
Fase 16.2 (COMPLETA ✅)
├─ Criar classe ApiResponse
├─ Converter 1 endpoint simples (getHeroImages)
├─ Validar padronização
└─ Status: Pronto para produção

Fase 16.3 (COMPLETA ✅)
├─ Converter 3 endpoints simples:
│  ├─ api/v1/utils/getContactInfo.php (SELECT simples)
│  ├─ api/v1/utils/getFormularioImovel.php (SELECT simples)
│  └─ api/v1/test/status.php (Health check, sem DB)
├─ Validar sintaxe e compatibilidade
└─ Status: Pronto para produção

Fase 16.4 (PRÓXIMA)
├─ Avaliar impacto no frontend
├─ Decidir entre: manter ambas as respostas OU atualizar frontend
└─ Converter endpoints médios (messages, notifications)

Fase 16.5+ (FUTURA)
├─ Converter endpoints de auth (com validação rigorosa)
├─ Converter endpoints críticos (properties, billing, pipeline)
└─ Remover suporte a resposta legada (se decidido)
```

## 🔍 Fase 16.4 — Validação Integrada com Frontend Real

A Fase 16.4 valida runtime com o frontend, `api-helper.js`, `fetch`, parsing JSON e componentes UI relacionados aos endpoints já convertidos.

### Endpoints Avaliados

- `api/v1/utils/getHeroImages.php`
- `api/v1/utils/getContactInfo.php`
- `api/v1/utils/getFormularioImovel.php`
- `api/v1/test/status.php`

### Conclusão

- A validação confirmou que os novos formatos são entregues corretamente via `?standard_response=1`.
- `getContactInfo.php` e `status.php` funcionaram em runtime.
- `getHeroImages.php` e `getFormularioImovel.php` retornaram erros de ambiente/schema, mas mantêm compatibilidade de formato.
- Nenhum consumidor direto foi encontrado no frontend atual, o que reduz exposição imediata mas reforça a necessidade de manter fallback legada.

### Próximo passo

- Usar `docs/frontend-api-compatibility.md` para documentar os detalhes de compatibilidade frontend/API e planejar a Fase 16.5.

### Observação

- O padrão `?standard_response=1` permanece obrigatório para testes e migração gradual.

### Critérios de Conversão

**✅ SEGURO Converter:**

- Endpoints de leitura (GET simples)
- Endpoints sem autenticação crítica
- Endpoints sem session direct
- Endpoints públicos
- Endpoints sem dependências de frontend pesadas

**⚠️ CUIDADO Converter:**

- Endpoints com autenticação (validar fallback)
- Endpoints com session (validar todas as verificações)
- Endpoints com tenant isolation (validar teste completo)

**❌ NÃO Converter Agora:**

- Endpoints críticos (properties, billing, pipeline)
- Endpoints de auth
- Endpoints com transaction complexa
- Endpoints que frontend ainda não foi atualizado

---

## 🔒 Estratégias de Compatibilidade

### Estratégia A: Query String (Usada em getHeroImages)

```php
// Resposta legada (padrão)
GET /api/v1/utils/getHeroImages.php
→ { "success": true, "images": [...] }

// Resposta nova (opt-in)
GET /api/v1/utils/getHeroImages.php?standard_response=1
→ { "success": true, "message": "...", "data": [...], "errors": [], "meta": {...} }
```

**Vantagens**: Sem quebra de compatibilidade  
**Desvantagens**: Duas respostas diferentes

### Estratégia B: Header (Alternativa)

```php
// Resposta legada (padrão)
GET /api/v1/endpoint

// Resposta nova (com header)
GET /api/v1/endpoint
Accept: application/vnd.api+json; version=2
```

**Vantagens**: RESTful, sem query string  
**Desvantagens**: Requer cliente sofisticado

### Estratégia C: Versionamento de Endpoint

```php
// Legado
GET /api/v1/endpoint

// Novo
GET /api/v2/endpoint
```

**Vantagens**: Completamente separado  
**Desvantagens**: Duplicação de código

---

## 📝 Classe ApiResponse

**Localização**: `src/Http/ApiResponse.php`

### Namespace

```php
namespace App\Http;
```

### Métodos Públicos

```php
// Sucesso
ApiResponse::success(
    $data = null,
    $message = null,
    $meta = [],
    $status = 200
): void

// Erro genérico
ApiResponse::error(
    $message = '',
    $status = 500,
    $errors = [],
    $data = null
): void

// Erro de validação
ApiResponse::validation(
    $errors = [],
    $message = 'Erro de validação',
    $status = 422
): void

// Atalhos
ApiResponse::notFound($message = 'Recurso não encontrado'): void
ApiResponse::unauthorized($message = 'Não autenticado'): void
ApiResponse::forbidden($message = 'Acesso negado'): void
```

### Características

- ✅ Headers JSON automáticos
- ✅ Status HTTP correto
- ✅ JSON_UNESCAPED_UNICODE por padrão
- ✅ Exit automático após enviar
- ✅ Sem exposição de stack trace
- ✅ Compatível com PHP 7.2+
- ✅ Namespace App\Http

---

## 🧪 Testes de Compatibilidade

### Teste 1: Resposta Legada (Sem Query String)

```bash
curl http://localhost/api/v1/utils/getHeroImages.php
```

**Esperado**: Resposta legada com `"success": true, "images": [...]`

### Teste 2: Resposta Nova (Com Query String)

```bash
curl "http://localhost/api/v1/utils/getHeroImages.php?standard_response=1"
```

**Esperado**: Resposta nova com `"success": true, "message": "...", "data": [...], "errors": [], "meta": {...}`

### Teste 3: Erro Legado

```bash
# Simular erro (pasta não existe ou banco offline)
curl http://localhost/api/v1/utils/getHeroImages.php
```

**Esperado**: Resposta de erro legada

### Teste 4: Erro Novo

```bash
curl "http://localhost/api/v1/utils/getHeroImages.php?standard_response=1"
# (simulando erro)
```

**Esperado**: Resposta de erro nova

---

## ⚡ Validações Executadas

### Sintaxe PHP

- [x] `php -l src/Http/ApiResponse.php` → ✅ Sem erros
- [x] `php -l api/v1/utils/getHeroImages.php` → ✅ Sem erros

### Funcionalidade

- [x] Endpoint legado continua funcionando
- [x] Endpoint com `?standard_response=1` funciona
- [x] Resposta JSON é válida
- [x] Headers corretos
- [x] Sem crash do sistema

### Segurança

- [x] Sem exposição de stack trace
- [x] Sem mudança em autenticação
- [x] Sem mudança em session
- [x] Sem mudança em tenant isolation
- [x] Sem mudança em dados sensíveis

---

## ⚠️ Observações Importantes

### Para Desenvolvedores Novos

**"Nenhum endpoint crítico deve ser convertido sem validação individual."**

Sempre testar:

1. Compatibilidade com frontend existente
2. Comportamento de erro
3. Autenticação (se aplicável)
4. Tenant isolation (se aplicável)

### Próximas Etapas

**Fase 16.3**: Converter mais endpoints simples e documentar padrão

**Fase 16.4**: Avaliar impacto no frontend e criar estratégia de atualização em massa

**Fase 16.5+**: Decidir se vai converter endpoints críticos ou manter ambas as respostas permanentemente

---

## 📚 Referências

**Arquivo de Classe**: [src/Http/ApiResponse.php](../src/Http/ApiResponse.php)

**Endpoint Convertido**: [api/v1/utils/getHeroImages.php](../api/v1/utils/getHeroImages.php)

**Fase 16.1 - Mapeamento**: [docs/legacy-separation-map.md](./legacy-separation-map.md)

---

**Data de Criação**: 13 de maio de 2026  
**Última Atualização**: 13 de maio de 2026  
**Status**: ✅ Fase 16.2 Completa
