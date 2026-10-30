# 📋 Guia de Implementação - Sistema de Onboarding de Empresas

## 🎯 Visão Geral

O sistema de onboarding foi implementado para permitir que novas empresas se cadastrem automaticamente no SaaS multi-tenant sem necessidade de intervenção manual.

## 📁 Arquivos Criados

### 1. **cadastro_empresa.php**
- **Função**: Formulário de cadastro para novas empresas
- **Localização**: `/cadastro_empresa.php`
- **Acesso**: `http://seu-dominio.com/cadastro_empresa.php` ou via link na página de login
- **Campos do Formulário**:
  - Nome da Empresa (obrigatório, 3-100 caracteres)
  - Quantidade de Usuários - Limite do Plano (obrigatório, 1-1000)
  - Nome Completo do Admin (obrigatório, 3-100 caracteres)
  - Email do Admin (obrigatório, formato válido)
  - Nome de Usuário (obrigatório, 3-50 caracteres, apenas alfanuméricos, traço e underscore)
  - Senha (obrigatório, mínimo 8 caracteres)
  - Confirmação de Senha (obrigatório, deve conferir com a senha)

### 2. **cadastro_empresa_action.php**
- **Função**: Processamento do formulário e criação de empresa + admin
- **Localização**: `/cadastro_empresa_action.php`
- **Responsabilidades**:
  - Validar dados do formulário
  - Verificar duplicidade de email e username
  - Gerar slug único para o tenant
  - Criar tenant na tabela `tenants`
  - Criar usuário administrador na tabela `users`
  - Fazer login automático do novo admin
  - Redirecionar para o dashboard

## 🔄 Fluxo de Funcionamento

```
┌─────────────────────────────────────────┐
│  Usuário acessa cadastro_empresa.php    │
└──────────────────┬──────────────────────┘
                   │
                   ▼
  ┌─────────────────────────────────────┐
  │  Preenche formulário com dados      │
  │  - Empresa, Admin, Email, Senha     │
  └──────────────────┬──────────────────┘
                     │
                     ▼
  ┌─────────────────────────────────────┐
  │  Envia POST para                    │
  │  cadastro_empresa_action.php        │
  └──────────────────┬──────────────────┘
                     │
                     ▼
  ┌─────────────────────────────────────────┐
  │  Validações:                            │
  │  ✓ CSRF Token                           │
  │  ✓ Dados do Formulário                  │
  │  ✓ Email não duplicado                  │
  │  ✓ Username não duplicado               │
  └──────────────────┬──────────────────────┘
                     │
                     ▼
  ┌─────────────────────────────────────────┐
  │  Gera slug único:                       │
  │  empresa_nome + hash de email           │
  │  ou tenant_RANDOM_TIMESTAMP             │
  └──────────────────┬──────────────────────┘
                     │
                     ▼
        ┌────────────┴────────────┐
        │   🔄 TRANSAÇÃO SQL      │
        │                         │
        ▼                         ▼
    INSERT                    INSERT
    TENANTS                   USERS
    - name                    - tenant_id
    - slug                    - username
    - max_users               - email
    - status='active'         - password (hash)
                              - role='admin'
        │                         │
        └────────────┬────────────┘
                     │
                     ▼
  ┌─────────────────────────────────────────┐
  │  Cria Sessão do Usuário:                │
  │  $_SESSION['user_id']                   │
  │  $_SESSION['tenant_id']                 │
  │  $_SESSION['role'] = 'admin'            │
  │  $_SESSION['username']                  │
  │  $_SESSION['email']                     │
  └──────────────────┬──────────────────────┘
                     │
                     ▼
  ┌─────────────────────────────────────────┐
  │  ✅ Sucesso!                            │
  │  Redireciona para painel.php            │
  │  (User já logado e autenticado)         │
  └─────────────────────────────────────────┘
```

## 📊 Dados Criados no Banco

### Tabela `tenants`
```sql
INSERT INTO tenants (
    name,              -- Nome da empresa (ex: "Imóveis Solutions")
    slug,              -- Slug único (ex: "imoveis_solutions_a1b2c3")
    company_subdomain, -- Subdomínio (mesmo valor do slug)
    description,       -- Descrição (vazio por padrão)
    max_users,         -- Limite de usuários (do formulário)
    status,            -- 'active' (padrão)
    created_at,        -- Timestamp de criação
    updated_at         -- Timestamp de atualização
)
```

### Tabela `users`
```sql
INSERT INTO users (
    tenant_id,               -- ID do tenant criado
    username,                -- Nome de usuário (ex: "joao_silva")
    email,                   -- Email (ex: "admin@empresa.com")
    password,                -- Hash bcrypt da senha
    name,                    -- Nome completo (ex: "João Silva")
    role,                    -- Sempre 'admin' para novo cadastro
    is_superadmin,           -- 0 (não é superadmin)
    active,                  -- 1 (ativo por padrão)
    created_at,              -- Timestamp de criação
    last_login,              -- NULL (ainda não fez login)
    force_password_change,   -- 0 (não forçar troca na próxima sessão)
    login_attempts           -- 0 (sem tentativas de login falhas)
)
```

## 🔒 Segurança Implementada

### ✅ Validações
- **CSRF Token**: Validação automaticamente para prevenir ataques CSRF
- **SQL Injection**: Uso de Prepared Statements em todas as queries
- **Validação de Email**: Verificar se email já existe no sistema
- **Validação de Username**: Verificar se username já existe no sistema
- **Validação de Força de Senha**: Mínimo 8 caracteres
- **Sanitização de Entrada**: Usando `Security::sanitizeInput()`

### 🔐 Criptografia
- **Senhas**: Armazenadas com `password_hash()` (algoritmo PHP_PASSWORD_BCRYPT)
- **Verificação**: `password_verify()` no login

### 🛡️ Proteção de Sessão
- **Session Regeneration**: ID da sessão regenerado após login
- **Session Timeout**: 1 hora de inatividade (configurado no session_manager.php)
- **HTTPOnly Cookies**: Cookies não acessíveis por JavaScript
- **Secure Cookie**: Apenas em HTTPS quando disponível
- **SameSite Strict**: Proteção contra CSRF em nível de cookie

### 🔄 Transação de Banco
- Se algo falhar ao criar o tenant ou o usuário, a transação faz rollback
- Garante consistência de dados (não cria tenant sem admin ou vice-versa)

## 🚀 Como Usar

### Para Usuários Finais

1. **Acesse a página de cadastro**:
   - Link direto: `http://seu-dominio.com/cadastro_empresa.php`
   - Ou clique no link "Cadastre aqui" na página de login

2. **Preencha o formulário** com:
   - Nome da sua empresa
   - Quantidade de usuários que precisa
   - Seus dados pessoais (nome, email, usuário, senha)

3. **Clique em "Criar Minha Empresa"**

4. **Sucesso!** Você será automaticamente logado e levado ao dashboard

### Para Administradores/Desenvolvedores

#### Verificar Novo Cadastro no Banco
```sql
-- Ver empresas recém-criadas
SELECT * FROM tenants ORDER BY created_at DESC LIMIT 10;

-- Ver admins de uma empresa
SELECT * FROM users WHERE tenant_id = (SELECT id FROM tenants WHERE slug = 'sua_empresa') AND role = 'admin';

-- Ver estatísticas de um tenant
SELECT 
    t.name,
    t.max_users,
    COUNT(u.id) as active_users,
    t.created_at
FROM tenants t
LEFT JOIN users u ON u.tenant_id = t.id AND u.active = 1
GROUP BY t.id
ORDER BY t.created_at DESC;
```

#### URLs de Interação
- **Formulário de Cadastro**: `/cadastro_empresa.php`
- **Action de Processamento**: `/cadastro_empresa_action.php` (POST apenas)
- **Página de Login**: `/login.php` (agora com link para cadastro)
- **Dashboard (após login)**: `/painel.php`

## ⚙️ Configuração e Customização

### Modificar Limite de Usuários Padrão
No arquivo `cadastro_empresa.php`, linha contendo:
```html
<input type="number" id="maxUsers" name="max_users" value="10" min="1" max="1000">
```
Altere o `value="10"` para o padrão desejado.

### Modificar Mensagens de Erro
As mensagens estão em:
- **cadastro_empresa_action.php**: Linhas 70-110 (validações)
- **cadastro_empresa.php**: Linhas 255-265 (mensagens de erro)

### Adicionar Más Validações/Campos Adicionais
Modificar `cadastro_empresa.php` para adicionar novos campos no formulário.
Depois adicionar a validação correspondente em `cadastro_empresa_action.php`.

### Integrar com Sistema de Email
No `cadastro_empresa_action.php`, após linha 220 (sucesso da criação):
```php
// Adicionar após registro bem-sucedido:
if (function_exists('enviarEmailBoasVindas')) {
    enviarEmailBoasVindas($adminEmail, $companyName, $adminUsername);
}
```

## 🐛 Troubleshooting

### Erro: "Email já cadastrado no sistema"
- O email fornecido já está associado a outra conta
- **Solução**: Use um email diferente ou acione o admin para recuperar a conta anterior

### Erro: "Nome de usuário já está em uso"
- O username escolhido já está em uso
- **Solução**: Escolha um username único (ex: adicione números ou underscore)

### Erro: "Token de segurança inválido"
- Sessão expirou ou houve erro no CSRF token
- **Solução**: Recarregue a página e tente novamente

### Erro: "Erro ao processar o cadastro"
- Problema no banco de dados
- **Solução**: Verificar logs de erro em `/logs/` ou `error_log` do PHP

### Usuário criado mas não consegue fazer login
- Verificar se o usuário está ativo (`active = 1`)
- Verificar se o tenant está ativo (`status = 'active'`)
- **Debug**: Execute no phpMyAdmin:
```sql
SELECT u.*, t.status FROM users u 
LEFT JOIN tenants t ON u.tenant_id = t.id
WHERE u.email = 'seu_email@exemplo.com';
```

## 📝 Logs e Auditoria

O sistema registra ações de onboarding em:
- **Arquivo de Log**: `/logs/application.log` (se existir função `log_user_action`)
- **Banco de Dados**: Tabela `audit_logs` (se integrada)
- **Dados Registrados**:
  - `tenant_id` criado
  - `user_id` criado
  - `admin_email` e `admin_username`
  - Timestamp de criação

## 🔗 Integração com Sistema Existente

O código foi desenvolvido para integrar perfeitamente com:

✅ **Sistema de Sessão**: Usa `config/session_manager.php`
✅ **Banco de Dados**: Usa `config/database.php` (global `$pdo`)
✅ **Segurança**: Usa `app/security/CSRF.php` e `Security.php`
✅ **Multi-tenant**: Respeita `app/security/TenantContext.php`
✅ **Autenticação**: Usa mesma lógica que `login.php`
✅ **Dashboard**: Redireciona para `painel.php` existente

Nenhuma estrutura existente foi alterada. Apenas reutilizadas.

## 🎓 Exemplos de Uso

### Exemplo 1: Fluxo Completo de Cadastro
```
1. Usuário: https://exemplo.com/cadastro_empresa.php
2. Preenche:
   - Empresa: "Imóveis XYZ LTDA"
   - Admin Name: "Maria Silva"
   - Email: "maria@imoveisxyz.com"
   - Username: "maria_silva"
   - Senha: "SenhaForte123##"
   - Max Users: 25
3. Sistema cria:
   - Tenant ID: 42
   - Slug: "imoveisxyz_ltda_abc123"
   - User ID: 150
   - Username: "maria_silva"
4. Resultado:
   - Maria é logada automaticamente
   - Redirecionada para /painel.php
   - Pode começar a usar o sistema
```

### Exemplo 2: Criar Tenant Manualmente (PHP)
```php
// Se precisar criar programaticamente:
require_once 'config/database.php';

$stmt = $pdo->prepare("
    INSERT INTO tenants (name, slug, max_users, status, created_at, updated_at)
    VALUES (:name, :slug, :max_users, 'active', NOW(), NOW())
");

$stmt->execute([
    ':name' => 'Empresa Teste',
    ':slug' => 'empresa_teste_' . bin2hex(random_bytes(3)),
    ':max_users' => 10
]);

$tenantId = $pdo->lastInsertId();
```

## 📞 Suporte

Para bugs, melhorias ou dúvidas:
1. Verificar logs em `/logs/`
2. Testar dados diretamente no phpMyAdmin
3. Verificar se `config/database.php` está configurado corretamente
4. Verificar se `config/session_manager.php` está funcionando

---

**Versão**: 1.0
**Data**: 08 de Abril de 2026
**Status**: ✅ Pronto para Produção
