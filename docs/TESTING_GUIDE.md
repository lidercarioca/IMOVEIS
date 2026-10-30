# 🧪 Guia de Testes - Sistema de Onboarding

## ✅ Checklist Antes de Usar em Produção

### 1. Verificar Arquivo PHP

- [ ] `cadastro_empresa.php` existe em `/cadastro_empresa.php`
- [ ] `cadastro_empresa_action.php` existe em `/cadastro_empresa_action.php`
- [ ] Ambos os arquivos têm permissão de leitura (644 ou 755)

### 2. Verificar Banco de Dados

Execute os comandos abaixo no phpMyAdmin ou MySQL CLI:

```sql
-- Verificar se tabela 'tenants' existe
SHOW TABLES LIKE 'tenants';
-- Resultado esperado: Uma linha com 'tenants'

-- Verificar estrutura da tabela
DESCRIBE tenants;
-- Campos esperados: id, name, slug, company_subdomain, max_users, status, ...

-- Verificar se tabela 'users' existe
SHOW TABLES LIKE 'users';
-- Resultado esperado: Uma linha com 'users'

-- Verificar estrutura da tabela users
DESCRIBE users;
-- Campos esperados: id, tenant_id, username, email, password, role, active, ...

-- Verificar se há constraint de FK
SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME 
FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
WHERE TABLE_NAME = 'users';
-- Resultado esperado: FK entre users.tenant_id → tenants.id
```

### 3. Verificar Configuração do Sistema

- [ ] `config/database.php` está acessível e conecta corretamente
- [ ] `config/session_manager.php` inicia sessão sem erros
- [ ] `app/security/CSRF.php` existe e tem método `generateToken()` e `verifyToken()`
- [ ] Logs: Pasta `/logs/` existe e é gravável (opcional mas recomendado)

### 4. Testar Conexão com BD

Crie um arquivo `test_connection.php` na raiz:

```php
<?php
require_once 'config/database.php';

try {
    // Testar conexão
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM tenants");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "✅ Conexão com banco OK<br>";
    echo "Total de tenants: " . $result['count'] . "<br>";
    
    // Testar se tables existem
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    echo "✅ Tabela 'users' existe<br>";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
```

Depois acesse `http://localhost/test_connection.php` e navegador deve mostrar:
```
✅ Conexão com banco OK
Total de tenants: X
✅ Tabela 'users' existe
```

## 🧪 Testes Funcionais

### Teste 1: Acessar Página de Cadastro

1. Abra seu navegador
2. Acesse: `http://localhost/cadastro_empresa.php`
3. Esperado:
   - ✅ Página carrega sem erro
   - ✅ Formulário visível com todos os campos
   - ✅ Se já tem usuário logado, será redirecionado para painel.php

### Teste 2: Validação do Formulário (Client-side)

1. Na página de cadastro, tente:
   - [ ] Enviar sem preencher campos → erro "Campo obrigatório"
   - [ ] Email inválido → erro "Email inválido"
   - [ ] Senhas não conferem → erro "Senhas não conferem"
   - [ ] Senha < 8 caracteres → erro "Mínimo 8 caracteres"
   - [ ] Username com caracteres inválidos → erro "Apenas letras, números..."

### Teste 3: Cadastro Bem-Sucedido

1. Preencha o formulário com:
   ```
   - Empresa: "Empresa Teste XYZ"
   - Admin Name: "João Test Silva"
   - Email: "joao_test@gmail.com"
   - Username: "joao_test_001"
   - Senha: "Senha@Teste123"
   - Max Users: 15
   ```

2. Clique em "Criar Minha Empresa"

3. Esperado:
   - ✅ Sem mensagem de erro
   - ✅ Redireciona para `painel.php`
   - ✅ Usuário está logado (pode acessar dashboard)

4. Verificar no BD:
   ```sql
   SELECT * FROM tenants WHERE name = 'Empresa Teste XYZ';
   SELECT * FROM users WHERE email = 'joao_test@gmail.com';
   ```

### Teste 4: Validar Dados Criados no Banco

```sql
-- Query para verificar novo tenant
SELECT 
    t.id,
    t.name,
    t.slug,
    t.max_users,
    t.status,
    t.created_at,
    COUNT(u.id) as total_users
FROM tenants t
LEFT JOIN users u ON u.tenant_id = t.id
WHERE t.name = 'Empresa Teste XYZ'
GROUP BY t.id;

-- Resultado esperado:
-- id: (número), name: Empresa Teste XYZ, status: active, total_users: 1

-- Query para verificar admin criado
SELECT 
    id,
    tenant_id,
    username,
    email,
    role,
    is_superadmin,
    active,
    created_at
FROM users
WHERE email = 'joao_test@gmail.com';

-- Resultado esperado:
-- role: admin, is_superadmin: 0, active: 1
```

### Teste 5: Testar Rejeição de Duplicatas

1. Tente cadastrar novamente com o mesmo email
   - Esperado: ❌ Erro "Email já cadastrado no sistema"

2. Tente cadastrar com mesmo username
   - Esperado: ❌ Erro "Nome de usuário já está em uso"

### Teste 6: Login Automático Após Cadastro

Após cadastro bem-sucedido:

1. Verificar se você está logado (token de sessão criado)
2. Carregar página `painel.php`
3. Esperado:
   - ✅ Dashboard carrega sem redirecionar para login
   - ✅ Seu nome aparece no painel (se houver)

### Teste 7: Verificar Slug Único

```sql
-- Todos os slugs devem ser únicos
SELECT slug, COUNT(*) as count FROM tenants GROUP BY slug HAVING count > 1;

-- Resultado esperado: Uma linha com "0 rows"
```

### Teste 8: Verificar Integridade Referencial

```sql
-- Não deve haver user orfão (tenant não existente)
SELECT COUNT(*) FROM users WHERE tenant_id NOT IN (SELECT id FROM tenants);

-- Resultado esperado: 0 linhas
```

### Teste 9: Hashear de Senha

```powershell
# No PowerShell, verificar se a senha foi hashada com bcrypt
# (não está em plain text no banco)

# SQL Query:
SELECT username, email, password FROM users WHERE email = 'joao_test@gmail.com';

# Resultado esperado:
# password deve ser algo como: $2y$10$... (começa com $2y$10 = bcrypt)
# Nunca deve ser a senha em plain text
```

### Teste 10: Testar Transação (Rollback)

Simular um erro durante a criação:

1. Acessar `cadastro_empresa_action.php` diretamente com GET
   - Esperado: ❌ Redireciona para `cadastro_empresa.php`

2. Enviar POST sem CSRF token
   - Esperado: ❌ Erro "Token de segurança ausente"
   - Verificar BD: Nenhum tenant ou user foi criado parcialmente

## 🐛 Testes de Erro Esperados

### Erro 1: Email Duplicado
```
POST para cadastro_empresa_action.php com email já existente
Esperado: 
  - Redirecionamento para cadastro_empresa.php?error=...
  - Nenhum novo tenant criado no BD
```

### Erro 2: Username Duplicado
```
POST com username já existente
Esperado:
  - Erro em cadastro_empresa.php
  - Nenhum novo tenant criado
```

### Erro 3: CSRF Token Inválido
```
Remover ou alterar csrf_token no formulário e enviar
Esperado:
  - Erro "Token de segurança inválido"
  - Nenhum novo tenant criado
```

### Erro 4: Dados de Formulário Inválidos
```
Enviar max_users = -5 ou max_users = 2000
Esperado:
  - Erro no servidor
  - Nenhum novo tenant criado
```

## 📊 Teste de Carga (Opcional)

Se quiser testar múltiplos cadastros simultâneos:

```php
<?php
// test_bulk_registration.php
require_once 'config/database.php';

for ($i = 1; $i <= 5; $i++) {
    $email = "teste_user_{$i}@example.com";
    $username = "teste_user_{$i}";
    
    // Simular validações
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    
    if (!$stmt->fetch()) {
        echo "✅ Email {$email} pode ser criado<br>";
    } else {
        echo "❌ Email {$email} já existe<br>";
    }
}
?>
```

## 🔍 Debugging

Se algo der errado:

1. **Ver erro no navegador**:
   - F12 → Console → Ver mensagens de erro
   - Verificar Network tab para requests/responses

2. **Ver erro no servidor**:
   - Arquivo `/logs/application.log`
   - `error_log` do PHP (var_log/error.log)
   - phpMyAdmin → Variáveis → error_log

3. **Debug SQL**:
   ```php
   // Adicionar temporariamente em cadastro_empresa_action.php:
   echo '<pre>';
   var_dump($_POST);
   echo '</pre>';
   exit;
   ```

4. **Verificar Sessão**:
   ```php
   echo '<pre>';
   var_dump($_SESSION);
   echo '</pre>';
   ```

## ✅ Checklist Final de Produção

- [ ] Teste 1-10 todos passando
- [ ] Nenhuma mensagem de erro não esperada
- [ ] Dados criados corretamente no BD
- [ ] Login automático funciona
- [ ] Slug é sempre único
- [ ] Senhas estão hashadas (bcrypt)
- [ ] Log de auditoria funciona
- [ ] Performance aceitável (< 2 segundos)
- [ ] Não há SQL injection
- [ ] CSRF token funciona
- [ ] Paginação/limites funcionam

---

**Versão**: 1.0  
**Data**: 08 de Abril de 2026  
**Status**: 🚀 Pronto para Testar
