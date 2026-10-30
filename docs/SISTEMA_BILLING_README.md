# Sistema SaaS de Billing - Documentação

## 📋 Visão Geral

Sistema de billing para aplicação SaaS multi-tenant em PHP puro, com suporte a:

- trial de clientes
- planos dinâmicos carregados do banco de dados
- limites de usuários e imóveis por plano
- seleção de tenant pelo Superadmin
- verificação de expiração e bloqueio automático
- integração Stripe Checkout em modo de assinatura mensal
- webhook local para eventos Stripe de cobrança e assinatura
- notificações de expiração por e-mail

---

## 🎯 Funcionalidades Implementadas

### 1. **TRIAL AUTOMÁTICO**

- Novo cliente pode iniciar trial pelo fluxo de planos
- Acesso liberado pelo prazo definido em `plans.trial_days`
- `tenantJaTeveTrial()` impede novo trial para tenants que já usaram trial ou já pagaram
- Estado: **IMPLEMENTADO**

### 2. **PLANOS DINÂMICOS NO BANCO**

- Planos são carregados da tabela `plans`
- Inclui campos como `max_users`, `max_properties`, `monthly_price`, `trial_days`, `is_active` e `display_order`
- Estado: **IMPLEMENTADO**

### 3. **LIMITES DE USUÁRIOS E IMÓVEIS**

- Cada plano define limites de usuários e imóveis
- `podeCriarUsuario()` impede criação além do limite de usuários
- `podeCriarImovel()` valida limite de imóveis antes de cadastrar
- Estado: **IMPLEMENTADO**

### 4. **GERENCIAMENTO DE PLANOS PELO SUPERADMIN**

- `select_tenant.php` permite que o Superadmin escolha o tenant e gerencie planos
- Downgrade só é permitido se o tenant não ultrapassar os limites do novo plano
- Verifica limites antes de aplicar downgrade ou mudança de plano
- Estado: **IMPLEMENTADO**

### 5. **BLOQUEIO POR EXPIRAÇÃO**

- `bloquear_tenants_expirados.php` marca tenants expirados como `inactive`
- `middleware.php` valida tenant ativo em páginas protegidas
- Usuários inativos são redirecionados para `tenant_bloqueado.php`
- Estado: **IMPLEMENTADO**

### 6. **CADASTRO DE IMÓVEIS COM LIMITE DE PLANO**

- `api/v1/properties/addProperty.php` checa limite de imóveis do tenant antes de inserir
- Mensagem de erro clara quando o limite é atingido
- Estado: **IMPLEMENTADO**

### 7. **UPLOAD DE IMAGENS ROBUSTO**

- `api/v1/properties/addProperty.php` trata erros de upload por arquivo
- Warnings são retornados ao frontend quando imagens não são salvas
- Estado: **IMPLEMENTADO**

### 8. **MENSAGEM DE LIMITE NO PAINEL DE IMÓVEIS**

- `views/admin/adicionar-imovel.php` exibe total de imóveis e vagas restantes do plano atual
- Usuário vê quantos imóveis ainda pode cadastrar
- Estado: **IMPLEMENTADO**

### 9. **PAGAMENTO SIMPLIFICADO**

- Stripe Checkout em `subscription` mode para planos mensais
- `api/v1/billing/createStripeCheckoutSession.php` cria sessão com metadados de tenant e plano
- `stripe_success.php` valida o retorno e registra pagamento com deduplicação
- Tenant é ativado e `data_expiracao` é atualizada
- Estado: **IMPLEMENTADO**

### 10. **NOTIFICAÇÃO DE EXPIRAÇÃO**

- `notificar_expiracao_tenants.php` identifica tenants que expiram em breve
- Garante que não haja duplicação de envio via `tenant_notification_log`
- Estado: **IMPLEMENTADO**

### 11. **STRIPE CHECKOUT E WEBHOOK**

- Webhook local em `api/v1/billing/stripeWebhook.php`
- Processa eventos Stripe de cobrança e assinatura
- Suporta `checkout.session.completed`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.updated` e `customer.subscription.deleted`
- Atualiza status do tenant e envia notificações de falha/cancelamento
- Estado: **IMPLEMENTADO**

### 12. **DASHBOARD DE COBRANÇA NO PAINEL**

- Componente de cobrança disponível em `painel.php`
- Exibe aviso de expiração e histórico de pagamentos
- Estado: **IMPLEMENTADO**

---

## 📁 Arquivos Criados/Modificados

### Principais arquivos adicionados/atualizados

1. **`app/helpers/tenant_billing_helpers.php` — REMOVIDO (commit `ed6a72a`)**
    - (Histórico) Carregava planos dinâmicos do banco
    - (Histórico) Buscava dados do tenant
    - (Histórico) Verificava trial, expiração e limites
    - (Histórico) Contava usuários e imóveis ativos
    - (Histórico) Registrava pagamentos e atualizava tenant
    - (Histórico) Enviava notificações de expiração e falha de pagamento

    Nota: Este helper foi removido do código-base. A implementação atual das responsabilidades de billing está distribuída principalmente em `src/Infrastructure/Billing/TenantRepository` e `src/Services/Billing/TenantBillingService` (veja commits `9b63b6d`, `9057078`, `a33b65f` para o histórico da migração).

2. **`select_tenant.php`**
    - Seleção de tenant para Superadmin
    - Aba de gerenciamento de planos
    - Valida downgrade com limites do tenant

3. **`planos.php`**
    - Mostra planos ativos
    - Permite iniciar trial, escolher ou renovar plano
    - Inicia checkout Stripe para planos pagos

4. **`api/v1/billing/createStripeCheckoutSession.php`**
    - Cria sessão Stripe Checkout para o plano selecionado
    - Configura `subscription` mode com metadados do tenant

5. **`api/v1/billing/createStripeBillingPortalSession.php`**
    - Cria sessão do Stripe Customer Portal para o tenant atual
    - Permite autoatendimento de pagamento, atualização de cartão e cancelamento

6. **`stripe_success.php`**
    - Valida retorno do Stripe Checkout
    - Restaura sessão do usuário a partir dos metadados, quando necessário
    - Registra pagamento e ativa o tenant

7. **`api/v1/billing/stripeWebhook.php`**
    - Recebe eventos Stripe via webhook
    - Valida assinatura com `STRIPE_WEBHOOK_SECRET`
    - Trata eventos de assinatura, pagamento e cancelamento
    - Registra logs em `logs/stripe-webhook.log`

8. **`api/v1/properties/addProperty.php`**
    - Valida limite de imóveis antes de inserir
    - Upload de imagens com warnings por arquivo

9. **`views/admin/adicionar-imovel.php`**
    - Exibe mensagem de limite de imóveis
    - Mostra vagas restantes do plano atual

10. **`assets/js/painel/adicionar-imovel.js`**

- Exibe avisos de upload retornados pelo backend

11. **`middleware.php`**
    - Verifica tenant ativo em páginas protegidas
    - Redireciona tenants bloqueados para `tenant_bloqueado.php`

12. **`tenant_bloqueado.php`**
    - Página de bloqueio com link para renovação

13. **`bloquear_tenants_expirados.php`**
    - Inativa tenants expirados via CLI/CRON
    - HTTP desabilitado por padrão

14. **`notificar_expiracao_tenants.php`**
    - Notifica tenants com expiração próxima
    - HTTP desabilitado por padrão

15. **`payment-history.php`**
    - Exibe histórico de pagamentos do tenant

16. **`test_billing_system.php`**
    - Script de validação da implementação

---

## 🔄 Fluxo de Uso

### Usuário novo:

```
1. Cria empresa em cadastro_empresa.php
2. Tenant entra em trial
3. Usa sistema por prazo definido
4. Após expiração, tenant é bloqueado
5. Acessa tenant_bloqueado.php
6. Renova plano em planos.php
7. Tenant volta para status active
```

### Superadmin:

```
1. Acessa select_tenant.php
2. Seleciona tenant alvo
3. Gerencia plano do tenant
4. Pode fazer downgrade se limites permitirem
```

---

## 💻 Como Usar

### 1. Testar o sistema

```bash
http://localhost/test_billing_system.php
```

### 2. Bloquear tenants expirados

```bash
php bloquear_tenants_expirados.php
```

### 3. Notificar tenants próximos da expiração

```bash
php notificar_expiracao_tenants.php
```

### 4. Configurar CRON

Consulte o guia completo de cron jobs em `docs/CRON_JOBS_PRODUCTION.md` para todos os scripts recomendados e exemplos de agendamento.

```bash
0 8 * * * /usr/bin/php /var/www/html/bloquear_tenants_expirados.php
0 9 * * * /usr/bin/php /var/www/html/notificar_expiracao_tenants.php
```

### 5. Testar alterações no banco

```sql
UPDATE tenants SET data_expiracao = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id = 1;
php bloquear_tenants_expirados.php

UPDATE tenants SET data_expiracao = DATE_ADD(NOW(), INTERVAL 3 DAY) WHERE id = 1;
php notificar_expiracao_tenants.php
```

---

## 🔐 Segurança

✅ Implementado:

- Prepared statements em todas as queries
- Validação de tenant_id da sessão
- CSRF tokens em formulários importantes
- Sanitização de entradas de usuário
- Transações em operações críticas
- Deduplicação de processamento de Stripe em `logs/stripe-processed-sessions.log`

✅ Garantias:

- Superadmin vê todos os tenants
- Admin comum fica restrito ao tenant selecionado
- Limites de usuários e imóveis são validados no servidor

---

## 📊 Banco de Dados

### Tabela `plans`

- `code`, `name`, `description`
- `max_users`, `max_properties`
- `monthly_price`, `trial_days`, `is_trial`, `is_active`, `display_order`

### Tabela `tenants`

```
id                   INT PRIMARY KEY AUTO_INCREMENT
name                 VARCHAR(100)
slug                 VARCHAR(100) UNIQUE
status               ENUM('active','inactive','trial') DEFAULT 'trial'
plano                VARCHAR(50)                        DEFAULT 'basico'
limite_usuarios      INT                                DEFAULT 2
data_expiracao       DATETIME
stripe_customer_id   VARCHAR(255)                      NULL
stripe_subscription_id VARCHAR(255)                    NULL
created_at           TIMESTAMP
updated_at           TIMESTAMP
```

### Tabela `pagamentos`

```
id                   INT PRIMARY KEY AUTO_INCREMENT
tenant_id            INT FOREIGN KEY
valor                DECIMAL(10,2)
status               ENUM('pendente','pago')
data_pagamento       DATETIME
plano                VARCHAR(50)
created_at           TIMESTAMP
```

### Tabela `tenant_notification_log`

```
id                   INT PRIMARY KEY AUTO_INCREMENT
tenant_id            INT FOREIGN KEY
tipo                 VARCHAR(50)
data_envio           DATETIME
status               VARCHAR(20)
created_at           TIMESTAMP
UNIQUE (tenant_id, tipo)
```

---

## ✅ Validações

1. Trial automático - ✅ Implementado
2. Verificação em middleware - ✅ Implementado
3. Bloqueio por expiração - ✅ Implementado
4. Limite de usuários - ✅ Implementado
5. Limite de imóveis - ✅ Implementado
6. Sistema de planos dinâmico - ✅ Implementado
7. Pagamentos simplificados com Stripe Checkout - ✅ Implementado
8. Webhook Stripe local - ✅ Implementado
9. Tratamento de eventos de assinatura Stripe - ✅ Implementado

---

## 🚀 Próximos Passos

1. Adicionar Stripe Customer Portal / autoatendimento para upgrades, cancelamentos e atualização de cartão - ✅ Implementado (portal criado se houver cliente Stripe associado)
2. Melhorar o fluxo de gestão de preços/planos com Stripe Billing `Prices` e produtos oficiais - ✅ Implementado (sync de Stripe Price IDs e uso de `stripe_price_id` no checkout)
3. Consolidar logging e retry de webhook para falhas e deduplicação - ✅ Implementado (logging, deduplicação e resposta 500 em `api/v1/billing/stripeWebhook.php` para permitir retry do Stripe em falhas de processamento)
4. Refatorar validações para compartilhar mais código entre `planos.php` e `select_tenant.php` - ✅ Implementado (helpers de billing agora centralizam validações de planos e downgrade)
5. Criar API REST de billing e histórico de pagamentos - ✅ Implementado (`api/v1/billing/*` + `payment-history.php`)

---

## 🐛 Troubleshooting

### "Tenant bloqueado" ao fazer login

Causa: Data de expiração passou

Solução:

1. Execute `php bloquear_tenants_expirados.php`
2. Ou ajuste `data_expiracao` no BD
3. Renove plano em `planos.php`

### "Limite de usuários atingido"

Causa: Tenant atingiu o limite definido no plano

Solução:

1. Faça upgrade de plano em `planos.php`
2. Ou ajuste `limite_usuarios` diretamente no banco de dados

### "Limite de imóveis atingido"

Causa: Tenant atingiu o limite de imóveis do plano

Solução:

1. Faça upgrade de plano em `planos.php`
2. Ou ajuste o plano no `select_tenant.php` se for Superadmin

### Webhook Stripe não acionando

Causa: `STRIPE_WEBHOOK_SECRET` incorreto ou endpoint não configurado

Solução:

1. Confirme o segredo em `.env`
2. Verifique `logs/stripe-webhook.log`
3. Reenvie evento via Stripe CLI ou painel

### Scripts de expiração não rodando via HTTP

Causa: `bloquear_tenants_expirados.php` e `notificar_expiracao_tenants.php` são CLI-only por padrão

Solução:

1. Execute pelos comandos PHP
2. Mantenha HTTP desabilitado (`$allow_http = false`)

---

## 📞 Suporte

Para dúvidas ou problemas:

1. Verifique `test_billing_system.php`
2. Verifique os logs do Apache/PHP
3. Consulte banco de dados diretamente
4. Verifique a tabela `plans` e os registros de `tenants`

---

**Versão:** 1.0  
**Criado:** 2026-04-08  
**Status:** ✅ Core de billing implementado e validado: Stripe Checkout, Stripe Webhook, Stripe Customer Portal, notificações de expiração e bloqueio de tenants expirados. Refinamentos de integração e logging continuam em progresso.
