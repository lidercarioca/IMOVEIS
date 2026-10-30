# 🔵 Google Calendar Integration - Setup Guide

## ✅ O que foi implementado

- ✅ **GoogleCalendarService.php** - Classe para gerenciar integração com Google Calendar
- ✅ **API Endpoints** - 6 endpoints para sincronização
- ✅ **Tabelas de Banco de Dados** - Rastreamento de sincronização
- ✅ **OAuth Callback** - Processamento de autorização do Google
- ✅ **Frontend JavaScript** - Interface para sincronização

## 🚀 Setup Inicial

### 1. Configurar Google Cloud Console

1. Acesse [Google Cloud Console](https://console.cloud.google.com)
2. Crie um novo projeto
3. Ative a API de Google Calendar
4. Crie credenciais OAuth 2.0 (tipo: Aplicação Web)
5. Adicione como URI autorizado de redirecionamento:
   ```
   http://localhost/google/calendar_callback.php
   ```
6. Baixe as credenciais em formato JSON

### 2. Salvar Credenciais

Coloque o arquivo JSON das credenciais em:
```
/google/google-service-account.json
```

### 3. Criar Tabelas de Banco de Dados

Execute o SQL:
```sql
-- Executar: sql/create_google_calendar_sync.sql
```

Ou via CLI:
```bash
mysql -u root -p database_name < sql/create_google_calendar_sync.sql
```

### 4. Adicionar Script ao Painel

No arquivo `painel.php`, adicione antes de `</body>`:
```html
<script src="/assets/js/painel/google-calendar-sync.js"></script>
```

## 📱 Como Usar

### Para Usuários

#### 1. Autenticar com Google
```
1. Clique em "Conectar Google Calendar"
2. Autorize o acesso
3. Será redirecionado de volta ao painel
```

#### 2. Sincronizar Agendamentos
```
Opção 1: Sincronizar Tudo
- Clique em "Sincronizar Todos os Agendamentos"

Opção 2: Sincronizar Individual
- Ao criar/editar agendamento, marque "Sincronizar com Google Calendar"
```

#### 3. Remover Sincronização
```
- Acesse o agendamento
- Clique em "Remover sincronização"
- O evento será deletado do Google Calendar
```

### Para Desenvolvedores

#### API Endpoints

**1. Autenticar**
```
GET /api/v1/google_calendar.php?action=authenticate

Resposta:
{
  "success": true,
  "auth_url": "https://accounts.google.com/o/oauth2/auth?...",
  "message": "Redirecione para esta URL..."
}
```

**2. Sincronizar Todos**
```
POST /api/v1/google_calendar.php?action=sync-all
[?user_id=123] (admin only)

Body: (vazio)

Resposta:
{
  "success": true,
  "synced": 15,
  "total": 20,
  "errors": []
}
```

**3. Sincronizar Evento Específico**
```
POST /api/v1/google_calendar.php?action=sync-event

Body:
{
  "agendamento_id": 123
}

Resposta:
{
  "success": true,
  "action": "created",
  "event_id": "abc123xyz",
  "event_link": "https://calendar.google.com/calendar/u/0/r/eventedit/..."
}
```

**4. Remover Sincronização**
```
POST /api/v1/google_calendar.php?action=unsync-event

Body:
{
  "agendamento_id": 123
}

Resposta:
{
  "success": true,
  "message": "Sincronização removida com sucesso"
}
```

**5. Status de Autenticação**
```
GET /api/v1/google_calendar.php?action=get-status

Resposta:
{
  "authenticated": true,
  "synced_events": 15
}
```

**6. Listar Eventos**
```
GET /api/v1/google_calendar.php?action=list-events
?start_date=2025-01-01&end_date=2025-01-31&max_results=100

Resposta:
{
  "success": true,
  "events": [...],
  "count": 5
}
```

## 🗄️ Banco de Dados

### Tabela: `agendamentos_google_sync`
Rastreia quais agendamentos estão sincronizados com Google Calendar

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT | ID único |
| agendamento_id | INT | FK para agendamentos |
| google_event_id | VARCHAR(500) | ID do evento no Google |
| synced_at | TIMESTAMP | Quando foi sincronizado |
| last_updated | TIMESTAMP | Última atualização |

### Tabela: `usuarios_google_calendar_config`
Armazena tokens e configurações por usuário

| Campo | Tipo | Descrição |
|-------|------|-----------|
| id | INT | ID único |
| user_id | INT | FK para users |
| google_calendar_id | VARCHAR(500) | ID do calendário do Google |
| access_token | LONGTEXT | Token de acesso (JWT) |
| refresh_token | LONGTEXT | Token de refresh |
| token_expires_at | DATETIME | Quando o token expira |
| auto_sync | BOOLEAN | Auto-sincronizar novos agendamentos |
| created_at | TIMESTAMP | Criação |
| updated_at | TIMESTAMP | Última atualização |

## 🔐 Segurança

- ✅ Tokens OAuth salvos criptografados no banco
- ✅ Verificação de permissões por usuário
- ✅ Apenas admins podem sincronizar para outros usuários
- ✅ Refresh automático de tokens expirados

## 🐛 Troubleshooting

### "Erro: Código de autorização não recebido"
- Verificar se URL de redirect está correta no Google Cloud Console
- Limpar cache do navegador

### "Acesso negado ao Google Calendar"
- Confirmar que a API de Google Calendar está ativada
- Verificar escopo de permissões: `Calendar::CALENDAR`

### "Token expirou"
- Sistema automaticamente tenta refresh
- Se falhar, user precisa se autenticar novamente

## 📊 Próximos Passos

- [ ] Webhook para sincronização bidirecional
- [ ] Sincronização automática de alterações
- [ ] Suporte a múltiplos calendários
- [ ] Integração com Google Meet para video-chamadas
- [ ] Dashboard de eventos no painel

---

**Status:** ✅ Phase 8 - Google Calendar Integration (Completa)
