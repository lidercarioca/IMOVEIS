# Guia Completo: Integração Gmail com Notificações Reais

## 📋 Índice
1. [Pré-requisitos](#pré-requisitos)
2. [Instalação de Dependências](#instalação-de-dependências)
3. [Configuração Google Cloud](#configuração-google-cloud)
4. [Configuração OAuth 2.0](#configuração-oauth-20)
5. [Configuração Variáveis de Ambiente](#configuração-variáveis-de-ambiente)
6. [Teste Local](#teste-local)
7. [Configuração Produção (Pub/Sub)](#configuração-produção-pubsub)
8. [Troubleshooting](#troubleshooting)

---

## 🔧 Pré-requisitos

- **PHP 7.4+** com Composer instalado
- **Conta Google** (Gmail)
- **Google Cloud Console** acesso
- **OpenSSL** habilitado no PHP
- **curl** instalado
- **Acesso administrativo** ao painel

---

## 📦 Instalação de Dependências

### Passo 1: Instalar Google API Client via Composer

```bash
cd c:\XAMPP\htdocs
composer require google/apiclient:^3.0
```

**Output esperado:**
```
Using version ^3.0 for google/apiclient
./composer.json has been updated
Loading composer repositories with package information
Updating dependencies
  - Installing google/apiclient
  ...
Writing lock file
Installing dependencies from lock file
  ...
Successfully installed dependencies
```

---

## 🌍 Configuração Google Cloud

### Passo 1: Criar um Projeto no Google Cloud Console

1. Acesse [Google Cloud Console](https://console.cloud.google.com)
2. Clique em **"Selecionar um projeto"** → **"NOVO PROJETO"**
3. Nome: `rrImoveis` (ou seu nome)
4. Clique em **"Criar"**

### Passo 2: Ativar Gmail API

1. Vá para **APIs e Serviços** → **Biblioteca**
2. Procure por **"Gmail API"**
3. Clique em **"Gmail API"**
4. Clique em **"Ativar"**

### Passo 3: Ativar Pub/Sub API (opcional, para production)

1. Volte para **Biblioteca**
2. Procure por **"Cloud Pub/Sub API"**
3. Clique em **"Cloud Pub/Sub API"**
4. Clique em **"Ativar"**

---

## 🔐 Configuração OAuth 2.0

### Passo 1: Criar Credencial OAuth

1. Vá para **APIs e Serviços** → **Credenciais**
2. Clique em **"+ CRIAR CREDENCIAIS"** → **"ID do Cliente OAuth"**
3. Se pedido, configure a **Tela de Consentimento OAuth**:
   - Tipo de usuário: **Externo**
   - Clique em **"Criar"**
   - Preench com:
     - Nome do app: `rrImoveis Admin`
     - Email suporte: seu email
     - Clique em **"Salvar e continuar"**
   - Permissões: **Adicionar ou remover permissões**
     - Procure por `gmail.readonly`
     - Clique em **Caixa** e **"Adicionar permissões"**
     - Clique em **"Salvar e continuar"**
   - Usuários de teste: Adicione seu email Gmail
   - Clique em **"Salvar e continuar"**

4. De volta em **Credenciais**, clique em **"+ CRIAR CREDENCIAIS"** → **"ID do Cliente OAuth"**
5. Tipo de aplicação: **Aplicativo da Web**
6. Configure:
   - **Nome:** `rrImoveis Web`
   - **URLs de Redirecionamento Autorizados:**
     ```
     http://localhost/google/oauth_callback.php
     https://seu-dominio.com/google/oauth_callback.php
     ```
   - Clique em **"Criar"**

7. **COPIE** o `Client ID` e `Client Secret` (aparecem em um modal)

### Passo 2: Download das Credenciais

1. Clique no ícone de **download** ao lado da credencial criada
2. Salve como `google-credentials.json`
3. Mova para `c:\XAMPP\htdocs\config\`

---

## 🔑 Configuração Variáveis de Ambiente

### Passo 1: Adicionar ao `.env`

Abra `c:\XAMPP\htdocs\.env` e adicione:

```env
# Google OAuth
GOOGLE_CLIENT_ID=YOUR_CLIENT_ID_HERE
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET_HERE
GOOGLE_OAUTH_REDIRECT=http://localhost/google/oauth_callback.php

# Google Cloud (para Pub/Sub em produção)
GOOGLE_PROJECT_ID=seu-project-id
GOOGLE_PUBSUB_TOPIC=projects/seu-project-id/topics/gmail-notifications
GOOGLE_SERVICE_ACCOUNT_JSON=config/google-service-account.json
```

**Substitua:**
- `YOUR_CLIENT_ID_HERE` → Cole seu Client ID
- `YOUR_CLIENT_SECRET_HERE` → Cole seu Client Secret
- `seu-project-id` → ID do seu projeto (visível no console)

### Passo 2: Carregar Variáveis

Verifique que `config/env_loader.php` carrega estas variáveis (já deve estar):

```php
$clientId = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');
```

---

## 🧪 Teste Local

### Passo 1: Iniciar Autorização OAuth

1. Abra seu navegador
2. Acesse: `http://localhost/google/oauth.php`
3. Você será **redirecionado para Google**
4. **Faça login** com sua conta Gmail
5. **Autorize** o acesso em `gmail.readonly`
6. Você será redirecionado para `http://localhost/google/oauth_callback.php`
7. Mensagem esperada: **"Autorização concluída com sucesso. Token salvo."**

### Passo 2: Verificar Token Salvo

Verifique se `config/google_token.json` foi criado:

```bash
dir c:\XAMPP\htdocs\config\google_token.json
```

Conteúdo esperado:
```json
{
  "access_token": "ya29.a0AfH6SMBx...",
  "expires_in": 3599,
  "refresh_token": "1//0gF...",
  "scope": "https://www.googleapis.com/auth/gmail.readonly",
  "token_type": "Bearer",
  "created": 1639075200
}
```

### Passo 3: Testar Leitura de Emails

Execute manualmente:

```bash
cd c:\XAMPP\htdocs
C:\XAMPP\php\php.exe google/poll_unread.php
```

Verifique `logs/gmail_integration.log`:

```bash
type c:\XAMPP\htdocs\logs\gmail_integration.log
```

Esperado:
```
[2025-12-15T14:30:45-03:00] Encontrados 5 mensagens não lidas.
[2025-12-15T14:30:46-03:00] Notificação criada para message=abc123def456
```

---

## ⏱️ Configurar Polling Automático (Cron/Scheduler)

### Windows Task Scheduler

1. Abra **Agendador de Tarefas** (Task Scheduler)
2. **Nova Tarefa** → Configure:
   - **Nome:** `Gmail Polling - rrImoveis`
   - **Descrição:** `Verifica emails não lidos a cada 3 minutos`
3. **Gatilho:** Novo → Repetido a cada `3 minutos` por `1 dia` (ou indefinido)
4. **Ação:** Novo
   - Programa: `C:\XAMPP\php\php.exe`
   - Argumentos: `C:\XAMPP\htdocs\google\poll_unread.php`
   - Iniciar em: `C:\XAMPP\htdocs`
5. Clique em **"OK"**

**Teste:**
```powershell
# Executar manualmente a tarefa
schtasks /run /tn "Gmail Polling - rrImoveis"

# Ver histórico
schtasks /query /tn "Gmail Polling - rrImoveis" /v
```

---

## 🚀 Configuração Produção (Pub/Sub)

Para ambientes de produção, use **Google Pub/Sub** em vez de polling:

### Passo 1: Criar Tópico Pub/Sub

```bash
gcloud pubsub topics create gmail-notifications \
  --project=seu-project-id
```

### Passo 2: Criar Subscription

```bash
gcloud pubsub subscriptions create gmail-notifications-push \
  --topic=gmail-notifications \
  --push-endpoint=https://seu-dominio.com/google/pubsub_push.php \
  --push-auth-service-account=sua-service-account@seu-project-id.iam.gserviceaccount.com \
  --project=seu-project-id
```

### Passo 3: Configurar Service Account

1. Vá para **APIs e Serviços** → **Contas de Serviço**
2. Clique em **"Criar Conta de Serviço"**
3. Configure:
   - **Nome:** `gmail-notifications-service`
   - **ID:** `gmail-notifications-service`
4. Clique em **"Criar e Continuar"**
5. **Concessão de funções:**
   - `Cloud Pub/Sub Editor`
   - `Gmail API`
6. Clique em **"Continuar"** → **"Concluído"**
7. Clique na conta criada
8. **Chaves** → **Adicionar Chave** → **JSON**
9. Salve como `config/google-service-account.json`

### Passo 4: Ativar users.watch() no Gmail

```bash
C:\XAMPP\php\php.exe google/watch.php
```

Esperado:
```
Watch ativado no inbox. HistoryId: 12345...
Notificações serão enviadas via Pub/Sub para: projects/seu-project-id/topics/gmail-notifications
```

---

## 🐛 Troubleshooting

### ❌ "Google API Client não instalado"

```bash
composer require google/apiclient:^3.0
composer dump-autoload
```

### ❌ "Arquivo de token não encontrado"

Execute novamente: `http://localhost/google/oauth.php`

### ❌ "Token expirado"

O sistema renova automaticamente via `refresh_token`. Se não funcionar:

```bash
rm config/google_token.json
# Reauthorize via oauth.php
```

### ❌ "SSL: Certificate verify failed"

Seu certificado SSL está inválido. Para **teste local apenas**:

```php
// config/security.php - APENAS DESENVOLVIMENTO
if (getenv('ENVIRONMENT') === 'development') {
    stream_context_set_default([
        'ssl' => ['verify_peer' => false]
    ]);
}
```

### ❌ "Erro ao criar notificação: Access denied"

Verifique se o usuário é `admin` no painel:

```sql
SELECT id, username, role FROM users WHERE id = 1;
-- role deve ser 'admin'
```

---

## ✅ Checklist Final

- [ ] Composer instalado e `google/apiclient` instalado
- [ ] Projeto criado no Google Cloud
- [ ] Gmail API ativada
- [ ] OAuth Client ID criado
- [ ] `GOOGLE_CLIENT_ID` e `GOOGLE_CLIENT_SECRET` em `.env`
- [ ] Token obtido em `config/google_token.json`
- [ ] `poll_unread.php` testado manualmente
- [ ] Cron/Scheduler configurado para polling automático
- [ ] Notificações aparecem no painel após novo email

---

## 📞 Suporte

Erros nos logs:
- `logs/gmail_integration.log` — Logs de polling
- `logs/api_errors.log` — Erros da API

Teste a API diretamente:
```powershell
$headers = @{ 'Content-Type' = 'application/json' }
Invoke-WebRequest -Uri 'http://localhost/api/getUnreadGmailEmails.php' `
  -Headers $headers -UseBasicParsing
```

---

**Última atualização:** 15 de dezembro de 2025
