# 📡 API Pública - Documentação

## ✅ O que foi implementado

- ✅ **OpenAPI/Swagger Specification** - openapi.json com definição completa
- ✅ **Endpoints Públicos** - Recursos RESTful protegidos por API Key
- ✅ **API Key Manager** - Geração, validação e gerenciamento de chaves
- ✅ **Webhook Manager** - Sistema de webhooks para eventos críticos
- ✅ **Autenticação** - Bearer Token + API Key
- ✅ **Rate Limiting** - Controle de requisições por IP
- ✅ **Logging** - Rastreamento completo de requisições

---

## 🚀 Começando

### 1. Gerar uma API Key

```bash
# No painel, acesse: Configurações > API Pública > Gerar Chave
```

Você receberá uma chave similar a:
```
a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

### 2. Usar a API

Todos os endpoints requerem header `X-API-Key`:

```bash
curl -H "X-API-Key: sua_chave_aqui" \
     https://api.sistemaimoveis.com/v1/public/properties
```

---

## 📚 Recursos Disponíveis

### Imóveis (Properties)

#### Listar
```bash
GET /api/v1/public/properties
Headers:
  X-API-Key: your_key

Query Parameters:
  ?page=1
  ?limit=20
  ?status=Disponível
  ?tipo=Apartamento

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "titulo": "Apartamento Luxo",
      "tipo": "Apartamento",
      "endereco": "Rua Principal, 123",
      "preco": 450000,
      "descricao": "...",
      "dormitorios": 3,
      "banheiros": 2,
      "area": 120,
      "status": "Disponível",
      "created_at": "2025-01-28T10:30:00Z"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 100,
    "pages": 5
  }
}
```

#### Obter Detalhes
```bash
GET /api/v1/public/properties/{id}
Headers:
  X-API-Key: your_key
```

#### Criar
```bash
POST /api/v1/public/properties
Headers:
  X-API-Key: your_key
  Content-Type: application/json

Body:
{
  "titulo": "Novo Apartamento",
  "tipo": "Apartamento",
  "endereco": "Rua Nova, 456",
  "preco": 350000,
  "descricao": "Descrição do imóvel",
  "dormitorios": 2,
  "banheiros": 1,
  "area": 80,
  "status": "Disponível"
}

Response:
{
  "success": true,
  "message": "Imóvel criado com sucesso",
  "property_id": 123
}
```

#### Atualizar
```bash
PUT /api/v1/public/properties/{id}
Headers:
  X-API-Key: your_key
  Content-Type: application/json

Body:
{
  "preco": 360000,
  "status": "Alugado"
}
```

#### Deletar
```bash
DELETE /api/v1/public/properties/{id}
Headers:
  X-API-Key: your_key

Response: 204 No Content
```

---

## 🔔 Webhooks

### Registrar Webhook

```bash
POST /api/v1/public/webhooks
Headers:
  X-API-Key: your_key
  Content-Type: application/json

Body:
{
  "url": "https://seu-servidor.com/webhook",
  "eventos": [
    "property.created",
    "property.updated",
    "lead.created",
    "agendamento.confirmado"
  ]
}

Response:
{
  "success": true,
  "webhook_id": 1
}
```

### Eventos Disponíveis

- `property.created` - Novo imóvel criado
- `property.updated` - Imóvel atualizado
- `property.deleted` - Imóvel deletado
- `lead.created` - Novo lead criado
- `agendamento.confirmado` - Agendamento confirmado
- `agendamento.cancelado` - Agendamento cancelado

### Payload do Webhook

```json
{
  "event": "property.created",
  "timestamp": "2025-01-28T10:30:00Z",
  "data": {
    "property_id": 123,
    "titulo": "Novo Apartamento",
    "tipo": "Apartamento"
  }
}
```

### Segurança do Webhook

Cada webhook inclui assinatura HMAC no header `X-Webhook-Signature`:

```python
# Python
import hmac
import hashlib

signature = hmac.new(
    b'webhook_secret',
    request.body,
    hashlib.sha256
).hexdigest()

assert request.headers['X-Webhook-Signature'] == f'sha256={signature}'
```

---

## 🔐 Autenticação & Segurança

### API Key
- Chaves são geradas como hash SHA-256
- Cada chave é única por usuário
- Podem ter data de expiração
- Podem ser revogadas a qualquer momento

### Rate Limiting
- **100 requisições por minuto** por IP
- **1000 requisições por hora** por chave
- Header `X-RateLimit-*` na resposta

### HTTPS
- Todos os endpoints production usam HTTPS
- API Keys devem ser transmitidas apenas via HTTPS

---

## 📊 Exemplos de Integração

### JavaScript / Node.js

```javascript
const API_KEY = 'sua_chave_aqui';
const API_URL = 'https://api.sistemaimoveis.com/v1/public';

// Buscar imóveis
async function listarImoveis() {
  const response = await fetch(`${API_URL}/properties`, {
    headers: {
      'X-API-Key': API_KEY
    }
  });
  return await response.json();
}

// Criar novo imóvel
async function criarImovel(dados) {
  const response = await fetch(`${API_URL}/properties`, {
    method: 'POST',
    headers: {
      'X-API-Key': API_KEY,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(dados)
  });
  return await response.json();
}
```

### Python

```python
import requests

API_KEY = 'sua_chave_aqui'
API_URL = 'https://api.sistemaimoveis.com/v1/public'

headers = {
    'X-API-Key': API_KEY,
    'Content-Type': 'application/json'
}

# Listar imóveis
response = requests.get(f'{API_URL}/properties', headers=headers)
properties = response.json()

# Criar imóvel
data = {
    'titulo': 'Novo Apartamento',
    'tipo': 'Apartamento',
    'endereco': 'Rua Nova, 456',
    'preco': 350000
}

response = requests.post(f'{API_URL}/properties', headers=headers, json=data)
result = response.json()
```

### PHP

```php
<?php
$apiKey = 'sua_chave_aqui';
$apiUrl = 'https://api.sistemaimoveis.com/v1/public';

$ch = curl_init($apiUrl . '/properties');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
$properties = json_decode($response, true);
```

---

## 🔧 Limite de Taxa (Rate Limiting)

Respostas incluem headers informativos:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 87
X-RateLimit-Reset: 1643374800
```

Quando exceder o limite:
```
HTTP 429 Too Many Requests

{
  "error": "Rate limit exceeded",
  "retry_after": 60
}
```

---

## 📝 Logs e Monitoramento

### Ver Uso da Chave

```bash
GET /api/v1/admin/api-keys/{key_id}/stats
```

Resposta:
```json
{
  "total_requests": 1250,
  "successful": 1240,
  "errors": 10,
  "last_used": "2025-01-28T10:30:00Z"
}
```

### Histórico de Webhooks

```bash
GET /api/v1/admin/webhooks/{id}/history
```

---

## ❌ Tratamento de Erros

### Código de Status HTTP

| Status | Significado |
|--------|------------|
| 200 | OK |
| 201 | Criado com sucesso |
| 204 | Deletado com sucesso |
| 400 | Requisição inválida |
| 401 | Não autenticado |
| 403 | Acesso proibido |
| 404 | Recurso não encontrado |
| 429 | Muitas requisições |
| 500 | Erro do servidor |

### Exemplo de Erro

```json
{
  "error": "Campo obrigatório faltando: preco",
  "message": "Verifique os dados enviados",
  "status": 400
}
```

---

## 🎯 Casos de Uso

1. **Sincronização com Portais** - Enviar imóveis para portais imobiliários
2. **Integração CRM** - Importar leads de CRM externo
3. **Aplicativo Móvel** - Backend para app de agendamentos
4. **Automação** - Atualizar status de imóveis automaticamente
5. **Analytics** - Exportar dados para análise

---

## 📞 Suporte

- Email: suporte@sistemaimoveis.com
- Documentação: https://docs.sistemaimoveis.com
- Status: https://status.sistemaimoveis.com

---

**Status:** ✅ Phase 8 - API Pública (Completa)
