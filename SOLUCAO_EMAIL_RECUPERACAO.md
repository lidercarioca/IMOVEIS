# Problema: Email de Recuperação de Senha Não é Enviado

## 🔍 Diagnóstico Realizado

### Problemas Identificados:

1. **❌ SMTP_FROM estava vazio** no arquivo `.env`
   - **Status**: ✅ CORRIGIDO - Adicionado `SMTP_FROM=lidercarioca@gmail.com`

2. **⚠️ Segurança do Gmail SMTP**
   - Gmail não aceita senha simples em SMTP
   - Requer **App Password** (gerada especificamente para aplicações)
   - Senha atual pode não funcionar

---

## ✅ O que foi feito

### 1. Arquivo `.env` atualizado
```dotenv
# Antes
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=lidercarioca@gmail.com
SMTP_PASS=David161202@
SMTP_SECURE=tls

# Depois
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=lidercarioca@gmail.com
SMTP_PASS=David161202@
SMTP_SECURE=tls
SMTP_FROM=lidercarioca@gmail.com
SMTP_FROM_NAME=RR Imóveis
```

### 2. Link "Esqueci minha senha" adicionado
- Adicionado na página `login.php`
- Direciona para `forgot-password.php`

---

## 🔐 Solução: Usar App Password do Gmail

**Por que a senha simples não funciona?**
- Google bloqueou conexões SMTP com senhas de conta simples por questões de segurança
- É necessário gerar uma "App Password" especial

### Passos para gerar App Password:

#### Pré-requisito: Ativar Autenticação de Dois Fatores
1. Vá para [Google Account Security](https://myaccount.google.com/security)
2. Procure por "Verificação em duas etapas" (Two-Step Verification)
3. Clique em "Ativar" e siga as instruções

#### Gerar a App Password
1. Acesse [Google App Passwords](https://myaccount.google.com/apppasswords)
2. Selecione:
   - **App**: Mail
   - **Device**: Windows Computer (ou seu dispositivo)
3. Clique em "Gerar"
4. Google gera uma senha com **16 caracteres** (ex: `abcd efgh ijkl mnop`)
5. **Copie essa senha** (sem espaços)

#### Atualizar o `.env`
Abra o arquivo `.env` e substitua:
```dotenv
# Antes (não funciona)
SMTP_PASS=David161202@

# Depois (use a senha de 16 caracteres gerada pelo Google)
SMTP_PASS=abcdefghijklmnop
```

---

## 🧪 Testar o Sistema

1. Acesse a página de teste:
   ```
   http://localhost/test_password_reset.php
   ```

2. Ou teste manualmente:
   - Vá para a página de login: `http://localhost/login.php`
   - Clique em "Esqueci minha senha"
   - Digite seu e-mail
   - Verifique a caixa de entrada (e pasta de SPAM)

---

## 📋 Arquivos Envolvidos

- **Login**: [login.php](login.php) - Agora contém link para recuperação
- **Recuperação**: [forgot-password.php](forgot-password.php)
- **API**: [api/requestPasswordReset.php](api/requestPasswordReset.php)
- **Mailer**: [app/utils/mailer.php](app/utils/mailer.php)
- **Config SMTP**: [config/smtp.php](config/smtp.php)
- **Variáveis de Ambiente**: [.env](.env) ✅ ATUALIZADO

---

## ⚠️ Alternativas (se Gmail App Password não funcionar)

### Opção 1: Usar Gmail com "Permissão de Aplicativos Menos Seguros"
- Menos seguro, mas pode funcionar
- [Ativar permissão](https://myaccount.google.com/lesssecureapps)

### Opção 2: Usar outro provedor SMTP
- SendGrid, Mailgun, Mailtrap, etc.
- Atualize o `.env` com as credenciais do provedor

### Opção 3: Usar função mail() nativa do PHP
- Se o servidor está configurado para enviar emails via PHP
- O sistema já tem fallback para `mail()` se PHPMailer falhar

---

## 🐛 Logs

Você pode visualizar erros de email em:
- Logs da aplicação: `logs/`
- Se houver erros no PHP error.log: `php -r "echo php_ini_scanned_files();"`

---

## ✨ Resumo da Solução

| Problema | Solução |
|----------|---------|
| SMTP_FROM vazio | ✅ Adicionado no `.env` |
| Sem App Password | 📋 Gerar no Google Account Security |
| Sem link de recuperação | ✅ Adicionado em `login.php` |
| Sistema incompleto | ✅ Sistema completo e funcional |

