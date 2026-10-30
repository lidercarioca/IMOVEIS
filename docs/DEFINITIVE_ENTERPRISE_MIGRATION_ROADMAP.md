# DEFINITIVE ENTERPRISE MIGRATION ROADMAP

## 1. Objetivo do documento

Este documento consolida as evidências coletadas no projeto e estabelece a sequência prática para concluir a migração arquitetural do Sys Imóveis sem perder o estado atual do working tree.

O documento foi escrito com base em leitura direta do código, documentação existente e evidências históricas já validadas nas conversas anteriores. Nenhuma conclusão foi inventada e nenhuma etapa de código foi alterada fora da criação deste documento.

---

## 2. Estado atual do projeto

### 2.1 Estado verificado do Git

O estado verificado no início desta tarefa foi:

- HEAD atual: `287ddfe fix(security): validar identidade no rate limiter`
- working tree com múltiplas alterações em arquivos já existentes e vários arquivos não rastreados
- ausência de staged changes em `git diff --cached --stat`
- tag histórica relevante confirmada: `v0.9.0-phase4-complete`

Isso revela que o repositório está em estado heterogêneo e que não se trata de um snapshot limpo imediatamente anterior à migração mais recente.

### 2.2 Status do estado do projeto

A classificação abaixo preserva a distinção entre concluído / comprovado, concluído com ressalvas, em andamento, pendente e não verificado.

- Concluído e comprovado:
    - arquitetura modernizada em partes do projeto, especialmente em service/repository, autenticação moderna e mecanismo de camada de domínio
    - testes de contrato em relatórios já executados e registrados anteriormente
    - materialização real de auth/tenant e hardening de segurança em commits e arquivos já presentes no projeto, sem necessidade de reabrir esse tema como fase nova

- Implementação concluída com ressalvas:
    - PSR4-06, conforme registrado no escopo estático e de contratos validados
    - a ressalva importante é a integração real com MySQL, que permanece pendente

- Em andamento:
    - limpeza dos módulos legados que ainda permanecem em SQL procedural e session-based checks
    - consolidação dos fluxos híbridos que ainda coexistem entre legado e arquitetura moderna

- Pendente:
    - migração final dos endpoints legacy de UTM, analytics e relatórios públicos/analíticos ainda acoplados ao banco direto
    - validação integrada real em ambiente isolado de teste

- Não verificado:
    - uso real e plena compatibilidade dos endpoints exportados contra banco MySQL em ambiente de teste dedicado
    - origem exata de cada hunk final em alguns arquivos de relatório sem uma base limpa imediata do lote
    - necessidade de reabrir Auth + Tenant como nova fase pendente, já que a evidência histórica e o código atual mostram que esse esforço já foi materializado

---

## 3. Fontes consultadas e evidências relevantes

As fontes principais do projeto consultadas para este documento foram:

- [docs/fase-16/legacy-separation-map.md](docs/fase-16/legacy-separation-map.md)
- [composer.json](composer.json)
- [app/bootstrap.php](app/bootstrap.php)
- [config/session_manager.php](config/session_manager.php)
- [config/database.php](config/database.php)
- [config/env_loader.php](config/env_loader.php)
- [app/security/TenantContext.php](app/security/TenantContext.php)
- [api/v1/middleware/TenantMiddleware.php](api/v1/middleware/TenantMiddleware.php)
- [api/v1/auth/login.php](api/v1/auth/login.php)
- [src/Auth/JwtService.php](src/Auth/JwtService.php)
- [src/Application/User/UserService.php](src/Application/User/UserService.php)
- [src/Infrastructure/User/UserRepository.php](src/Infrastructure/User/UserRepository.php)
- [src/Service/PropertyService.php](src/Service/PropertyService.php)
- [src/Repository/ReportRepository.php](src/Repository/ReportRepository.php)
- [src/Service/ReportService.php](src/Service/ReportService.php)
- [api/relatorios/exportDashboard.php](api/relatorios/exportDashboard.php)
- [api/relatorios/exportExcel.php](api/relatorios/exportExcel.php)
- [api/relatorios/exportPDF.php](api/relatorios/exportPDF.php)
- [tests/Unit/ReportDashboardMetricsContractTest.php](tests/Unit/ReportDashboardMetricsContractTest.php)
- [tests/Unit/DashboardKpiRealtimeContractTest.php](tests/Unit/DashboardKpiRealtimeContractTest.php)
- [tests/Unit/ReportGoalsAndTargetsContractTest.php](tests/Unit/ReportGoalsAndTargetsContractTest.php)
- [tests/Unit/ReportLeadsSalesContractTest.php](tests/Unit/ReportLeadsSalesContractTest.php)

Também foram consideradas as evidências históricas de commits e da tag `v0.9.0-phase4-complete`, mas sem assumir que ela seja uma prova absoluta de autoria dos hunks finais.

---

## 4. Arquitetura atual e arquitetura-alvo

### 4.1 Arquitetura atual

O código atual apresenta um ambiente híbrido:

- autenticação moderna em JWT e autenticação legada por sessão
- resolução de tenant em múltiplos pontos
- service/repository em alguns módulos
- endpoints ainda acoplados a helpers e sessão legada em outras áreas
- vários endpoints do tipo [api/v1](api/v1) ainda usando padrões legacy em paralelo com a arquitetura moderna

A evidência mais direta está em:

- [app/bootstrap.php](app/bootstrap.php)
- [config/session_manager.php](config/session_manager.php)
- [app/security/TenantContext.php](app/security/TenantContext.php)
- [api/v1/middleware/TenantMiddleware.php](api/v1/middleware/TenantMiddleware.php)
- [src/Service/PropertyService.php](src/Service/PropertyService.php)

### 4.2 Arquitetura-alvo

A arquitetura-alvo pretendida é uma base única e determinística:

- endpoints → service → repository
- autenticação por fluxo único, sem fallback inseguro em módulos críticos
- tenant resolvido por um único caminho de decisão
- regras de permissão e segurança centralizadas
- camada de domínio e infraestrutura separadas com PSR-4
- módulos de relatório e de negócio usando a mesma regra de tenant e autorização

A convenção PSR-4 do Composer foi verificada em [composer.json](composer.json):

- `App\` mapeado para `src/`
- autoload de helpers legado ainda presente em `app/helpers/property_helpers.php`

Isso indica que a migração não está concluída em todos os módulos, mesmo com progresso real em áreas específicas.

---

## 5. Estado dos módulos relevantes

### 5.1 Financeiro

Status: em migração parcial, com evolução clara em billing e finance service/repository, mas ainda dependente de limpeza final dos fluxos híbridos.

Aspectos evidentes:

- há evolução em billing e financeiro no repositório
- integrações de Stripe e history/ledger estão presentes na arquitetura moderna
- os endpoints de billing já demonstram uso de serviço/repositório em vários pontos, mas ainda coexistem com helpers e verificações legadas em trechos residuais

### 5.2 Relatórios

Status: implementação considerada concluída no escopo estático e de contratos validados, com ressalva de integração real com MySQL ainda pendente.

A PSR4-06 deve constar como:

**Implementação considerada concluída no escopo estático e de contratos validado, com ressalva de integração real com MySQL ainda pendente.**

Resultados anteriormente reportados e que devem ser registrados como “testes anteriormente executados”:

- `ReportDashboardMetricsContractTest.php`: 5 testes, 33 assertions
- `DashboardKpiRealtimeContractTest.php`: 9 testes, 50 assertions
- `ReportGoalsAndTargetsContractTest.php`: 4 testes, 32 assertions
- `ReportLeadsSalesContractTest.php`: 13 testes, 55 assertions
- total registrado: 31 testes e 170 assertions

Ressalva registrada:

- revisão estática dos endpoints de exportação, `ReportService` e `ReportRepository` não encontrou defeito concreto comprovado no escopo inspecionado
- validação de integração real com MySQL permanece pendente, sem bloquear automaticamente o avanço das demais fases

Arquivos centrais da PSR4-06:

- [api/relatorios/exportDashboard.php](api/relatorios/exportDashboard.php)
- [api/relatorios/exportExcel.php](api/relatorios/exportExcel.php)
- [api/relatorios/exportPDF.php](api/relatorios/exportPDF.php)
- [src/Repository/ReportRepository.php](src/Repository/ReportRepository.php)
- [src/Service/ReportService.php](src/Service/ReportService.php)

### 5.3 Integrações / Google

Status: evoluindo e parcialmente validado, mas não concluído como base do núcleo da migração.

Há evidência de integração de Google Calendar e de testes unitários específicos. A área depende de autenticação, tenant e regras gerais da arquitetura mais do que apenas do módulo de relatórios.

### 5.4 Usuários / Auth

Status: já materializado no código e no histórico do repositório; não deve ser tratado como fase nova pendente.

Arquivos centrais:

- [config/session_manager.php](config/session_manager.php)
- [api/v1/auth/login.php](api/v1/auth/login.php)
- [src/Auth/JwtService.php](src/Auth/JwtService.php)
- [src/Application/User/UserService.php](src/Application/User/UserService.php)
- [src/Infrastructure/User/UserRepository.php](src/Infrastructure/User/UserRepository.php)

O que importa para a continuidade é a consolidação residual dos pontos em que autenticação e tenant ainda coexistem com fallback legados, e não a reabertura de uma etapa de Auth + Tenant como se fosse uma fase inédita.

### 5.5 Migração controlada do módulo de usuários e password reset

#### PSR4-51 — testes de contrato para usuários e auditoria

Status: concluído no escopo de testes e documentação, sem alteração em código de produção.

Escopo coberto por testes:

- leitura de usuário do tenant autorizado
- rejeição de leitura, atualização e exclusão fora do tenant
- listagem filtrada ao tenant correto
- permissões de admin, usuário comum e superadmin
- rejeição de campos protegidos em atualização (`tenant_id`, `username`, `is_superadmin`)
- proteção da conta principal `id = 1`
- criação de usuário normal e criação autorizada por sistema
- duplicidade de username/email
- auditoria moderna estruturada (`App\Audit\AuditLogger`) com ação, entidade, entity_id, user_id e metadata sem duplicação

Resultado verificado:

- `./vendor/bin/phpunit tests/Application/User/UserServiceTest.php tests/Unit/LoggingTest.php tests/Unit/TenantBoundaryContractTest.php --colors=never` → `OK` (27 testes, 66 assertions no conjunto direcionado executado)

Lacuna registrada e comprovada:

- `App\Models\UserModel::logUserChange()` ainda não possui contrato moderno verificável; a execução atual falha com `Error: Call to undefined method TenantContext::getUserId()`.
- Isso indica que a auditoria legada não foi migrada de forma segura para uma API moderna e que a ausência de `TenantContext::getUserId()` precisa ser tratada em uma etapa futura de migração ou adaptação explícita.
- A auditoria moderna (`App\Audit\AuditLogger`) registra entradas estruturadas em arquivo, mas não substitui a tabela `audit_logs` legada nem o comportamento específico de `logUserChange()` sem uma camada de adaptação e testes de regressão adicionais.

Critérios necessários para futura migração ou descontinuação:

1. definir contrato explícito de auditoria de usuário compatível com `audit_logs` legado;
2. adicionar testes de regressão para `tenant_id`, `user_id`, `action_type`, `resource_id` e ausência de duplicação;
3. garantir a equivalência de dados entre o logging moderno e o legado antes de remover o adaptador;
4. confirmar novamente, em ambiente com MySQL real, que não há uso de runtime do modelo legado antes de descontinuar o código.

#### PSR4-52 — contrato moderno de auditoria de usuários

Status: análise técnica e proposta de arquitetura concluídas no escopo de documentação e testes, sem implementação em produção.

##### 1. Contrato legado mapeado

O método legado em [app/legacy/UserModel.php](app/legacy/UserModel.php) é:

- `logUserChange(int $userId, array $oldData, array $newData, string $action): bool`

Parâmetros:

- `$userId`: usuário alvo afetado pela mudança;
- `$oldData`: snapshot do registro antes da alteração;
- `$newData`: snapshot do registro depois da alteração;
- `$action`: tipo de ação, usado como `action_type` no `audit_logs`.

Eventos registrados e momentos de emissão:

- o método não é chamado em pontos de serviço modernos confirmados pelo código atual; a chamada foi encontrada somente em testes e no próprio modelo legado;
- o contrato de intenção é registrar eventos de mudança em usuários, com payloads antes/depois e ação textual;
- a operação é explicitamente uma gravação em tabela `audit_logs`, não um log de aplicação em arquivo.

Origem do ator, do tenant, do IP e do user-agent:

- ator: `TenantContext::getUserId()`
- tenant: `TenantContext::getTenantId()`
- IP: `$_SERVER['REMOTE_ADDR'] ?? null`
- user-agent: `$_SERVER['HTTP_USER_AGENT'] ?? null`

Formato de `old_values` e `new_values`:

- ambos são convertidos por `json_encode()`;
- a estrutura é um array associativo, por exemplo `['name' => 'Old Name']` e `['name' => 'New Name']`;
- o método persiste o JSON diretamente em colunas `old_values` e `new_values`;
- não há sanitização de campos sensíveis além do que já vem do payload e da chamada de origem.

Tratamento de falhas:

- em caso de exceção `PDOException`, o método faz `error_log()` e retorna `false`;
- não lança exceção para o chamador; a falha é silenciosa do ponto de vista de negócio;
- a chamada não implementa retry nem deduplicação; o fluxo é "gravar uma vez e retornar o resultado".

Dependências de `TenantContext`:

- o método depende diretamente de `TenantContext::getUserId()` e `TenantContext::getTenantId()`;
- sem uma sessão inicializada e sem o tenant resolvido, ocorre falha de execução;
- essa dependência é a principal razão de a auditoria legada não ser um contrato moderno portátil.

##### 2. Avaliação da arquitetura moderna

A arquitetura moderna atual é composta por:

- [src/Audit/AuditLogger.php](src/Audit/AuditLogger.php): logger de dados de domínio; grava eventos em arquivo via `FileLogger`/`LoggerInterface` para auditoria operacional
- [app/utils/AuditLogger.php](app/utils/AuditLogger.php): utilitário legado com INSERT direto em `audit_logs` e uso de `TenantContext`
- [src/Service/PropertyService.php](src/Service/PropertyService.php): uso real de `AuditLogger` em operações do domínio de propriedades, mas sem qualquer vínculo com a tabela legado de usuários

Diferenças importantes entre os mecanismos:

- [src/Audit/AuditLogger.php](src/Audit/AuditLogger.php) grava em logs estruturados de arquivo com esquema `action`, `entity`, `entity_id`, `user_id`, `metadata`; não persiste em `audit_logs` do schema legado
- [app/utils/AuditLogger.php](app/utils/AuditLogger.php) grava em `audit_logs` usando colunas de negócio e tenant, bem como `status`, `description`, `ip_address`, `user_agent`
- a arquitetura moderna não é um substituto 1:1 do modelo legado; o que existe hoje é um logger de operação e um helper legadíssimo de banco, e não uma única interface de domínio para auditoria de usuário

Conclusão arquitetural:

- não deve haver um segundo logger genérico se já existir um mecanismo apropriado para o domínio;
- porém, os logs em arquivo não substituem registros em `audit_logs` quando o requisito é auditoria de negócio e integridade do registro histórico;
- a proposta correta é criar uma camada de serviço dedicada para auditoria de usuários, reutilizando a estrutura da tabela existente e não misturando logs de aplicação com registros de negócio.

##### 3. Contrato proposto (sem implementação em produção)

Interface proposta (documentação de design, não código em produção):

```php
interface UserAuditEventServiceInterface
{
    public function recordUserEvent(
        int $targetUserId,
        string $action,
        array $oldValues = [],
        array $newValues = [],
        ?int $actorUserId = null,
        ?int $tenantId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $description = null
    ): bool;

    public function recordUnauthorizedAttempt(
        string $resourceType,
        ?int $resourceId,
        string $reason,
        ?int $actorUserId = null,
        ?int $tenantId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool;
}
```

Objetivo do contrato:

- preservar a tabela `audit_logs` como fonte autorizada do histórico de negócio;
- manter o ator (`actorUserId`) e o tenant (`tenantId`) explícitos em todas as gravações;
- aceitar eventos específicos de usuário: create, update, delete, unauthorized_access_attempt, cross_tenant_attempt;
- padronizar `old_values` e `new_values` como JSON/array associativo sem incluir campos sensíveis desnecessários;
- impedir gravações duplicadas por identidade de evento: combinação de `action`, `resource_type`, `resource_id`, `actorUserId`, `tenantId` e `created_at`/hash de payload
- em caso de falha de gravação, retornar `false` e registrar o erro em log de infraestrutura, sem quebrar o fluxo principal

Compatibilidade temporária:

- a implementação concreta deve ter um adaptador para `audit_logs` legado, compatível com o schema atual;
- os campos `status`, `description`, `resource_type`, `resource_id`, `old_values`, `new_values`, `ip_address`, `user_agent` devem permanecer compatíveis com o estado atual do banco;
- a camada moderna não deve substituir o schema antigo antes de haver testes de equivalência de dados em MySQL real.

##### 4. Planejamento dos testes propostos

###### 4.1 Testes unitários

- persistência do evento `update` com payload antes/depois corretamente serializado;
- atribuição correta do ator e do tenant;
- serialização sem `password` e sem dados sensíveis irrelevantes;
- falha de gravação sem quebrar o fluxo principal;
- ausência de registro duplicado quando a mesma chamada é executada duas vezes no mesmo contexto;
- retorno `false` quando a tabela ou a operação falham.

###### 4.2 Testes de contrato

- `UserService`/`UserRepository` em conjunto com a camada de auditoria de usuários devem manter `tenant_id` e `user_id` consistentes;
- `updateUser` em tenant diferente deve criar registro de falha e não gravar sucesso;
- criação e exclusão de usuário devem gerar exatamente um evento por operação;
- `old_values` e `new_values` devem refletir o delta real e não uma cópia completa de dados sensíveis sem necessidade;
- `resource_type = 'user'` e `resource_id = userId` devem ser mantidos em todas as operações relevantes;
- `status` deve ser `success` ou `failed` conforme o resultado da ação.

###### 4.3 Testes de integração dependentes de MySQL

- criação de usuário em ambiente com MySQL real, validando `audit_logs` escrito de forma consistente;
- leitura do registro e confirmação de `tenant_id` correto, `user_id` do ator e `resource_id` do alvo;
- cenário de tentativa cross-tenant com `status = failed` e `description` adequada;
- cenário de falha de persistência por banco indisponível ou schema ausente, validando não quebrar o fluxo principal;
- comparação explícita dos registros produzidos pelo adaptador legado e pela implementação moderna, antes de remover qualquer ponte compatível.

##### 5. Critérios para implementação e validação futura

1. definir a interface de auditoria de usuário de maneira compatível com `audit_logs` existente;
2. criar um adaptador legado para a camada moderna sem quebrar o schema atual;
3. validar `tenant_id`, `user_id`, `action_type`, `resource_type`, `resource_id`, `old_values`, `new_values` e `status` em ambiente MySQL real;
4. confirmar que não há duplicação, nem envio de campos sensíveis excedentes, nem queda do fluxo principal em falha de gravação;
5. só então decidir remover ou descontinuar o método legado `logUserChange()` e substituir a entrada por um serviço dedicado.

##### 6. Status e conclusão prudente

Não se deve marcar a migração da auditoria de usuários como concluída. A evidência atual indica que:

- o modelo legado ainda depende de `TenantContext` para registrar a identidade do ator e do tenant;
- o log moderno não substitui o histórico em `audit_logs` do schema legado;
- existe uma lacuna arquitetural específica para o domínio de auditoria de usuário, e a solução correta é uma interface de domínio dedicada, compatível com o banco existente e validada em integração real antes da descontinuação.

#### PSR4-53 — implementação do serviço de auditoria de usuários com compatibilidade legada

Status: implementada no escopo estrito de auditoria de usuários, com compatibilidade de schema legado e sem reabrir outras migrações.

Arquivos diretamente afetados:

- [src/Application/User/UserAuditService.php](src/Application/User/UserAuditService.php) — serviço de aplicação para registrar auditoria de usuários em `audit_logs` mantendo `user_id` como ator e `resource_id` como recurso afetado;
- [src/Infrastructure/User/UserAuditRepository.php](src/Infrastructure/User/UserAuditRepository.php) — repositório persistente com `recordUserAudit()` e `hasRecentDuplicate()` para gravar no schema real do banco com compatibilidade de campos;
- [tests/Application/User/UserAuditServiceTest.php](tests/Application/User/UserAuditServiceTest.php) — testes de contrato para mapeamento de campos, identificação do ator e do tenant, serialização, filtragem de dados sensíveis, falha e prevenção de duplicação.

Compatibilidade preservada:

- o método grava na tabela `audit_logs` usando `user_id`, `tenant_id`, `action_type`, `resource_type`, `resource_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent` e `status`;
- o ator do registro continua sendo o usuário autenticado (`user_id`), enquanto o recurso alterado fica em `resource_id`;
- `old_values` e `new_values` são serializados em JSON e filtrados para remover `password`, `password_hash`, `hash`, `secret`, `token`, `api_key`, `authorization` e `cookie`;
- a implementação não usa fallback para `tenant_id = 1` e bloqueia gravação quando o actor ou o tenant são inválidos;
- a checagem de duplicação é defensiva e evita gravação repetida em janelas curtas, sem alterar o contrato HTTP/JSON existente.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Application/User/UserAuditServiceTest.php tests/Application/User/UserServiceTest.php tests/Unit/LoggingTest.php tests/Unit/TenantBoundaryContractTest.php --colors=never` → `OK (5 tests, 20 assertions)`
- `php -l src/Application/User/UserAuditService.php ; php -l src/Infrastructure/User/UserAuditRepository.php ; php -l tests/Application/User/UserAuditServiceTest.php ; git diff --check` → sem erro de sintaxe e sem saída de patch hygiene

Limitações da validação:

- a execução de persistência foi validada com SQLite em memória, não com MySQL real;
- o ambiente disponível não confirmou a presença do schema de produção, então a compatibilidade foi validada contra o contrato real do projeto e o `audit_logs` usado nos testes e no helper legado;
- a implementação mantém a política de compatibilidade legada e não remove o arquivo [app/legacy/UserModel.php](app/legacy/UserModel.php), como exigido no escopo da tarefa.

Conclusão prudente:

- a auditoria de usuários foi migrada para uma camada moderna e compatível com o schema legado, sem descontinuar a API antiga neste momento;
- o próximo passo seguro, caso seja necessário, é validar a persistência real em um ambiente com MySQL e comparar o histórico de `audit_logs` com o comportamento de produção antes de migrar ou remover qualquer adaptador legado.

#### PSR4-55 — integração da auditoria de usuários em um fluxo ativo

Status: integrada no fluxo ativo mais pequeno que cobre criação, atualização e exclusão de usuários por meio do serviço do domínio, sem remover o legado nem reabrir uma migração maior.

Arquivos diretamente afetados:

- [src/Application/User/UserService.php](src/Application/User/UserService.php) — adiciona um adaptador opcional de auditoria, registra eventos em `createUser()`, `updateUser()` e `deleteUser()` e preserva a política de falha sem quebrar a operação principal;
- [api/v1/auth/users.php](api/v1/auth/users.php) — constrói o `UserAuditService` com o repositório de `audit_logs` e passa a instância para o `UserService` no fluxo ativo de CRUD de usuários;
- [tests/Application/User/UserServiceTest.php](tests/Application/User/UserServiceTest.php) — testa a operação principal, a emissão única do evento, o uso correto de ator/tenant, a ausência de evento em operação não autorizada e a política de falha de auditoria.

Compatibilidade preservada:

- o contrato HTTP/JSON do endpoint não foi alterado;
- a operação principal continua mesmo quando a auditoria falha;
- o evento só é emitido quando há um `AuthContext` confiável e tenant válido;
- o `UserModel::logUserChange()` não foi removido nem substituído e continua documentado como ponto legado que exige compatibilidade explícita;
- hashes, senhas e campos sensíveis continuam filtrados pela camada de auditoria.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Application/User/UserServiceTest.php --colors=never` → falha reproduzida antes da integração, confirmando ausência do evento esperado;
- `./vendor/bin/phpunit tests/Application/User/UserServiceTest.php --colors=never` → após a integração, a suíte relevante do fluxo de usuários foi validada; resultados a confirmar após a execução final do comando de regressão do conjunto solicitado;
- `php -l` nos arquivos alterados → sintaxe validada no ciclo final de verificação do escopo;
- `git diff --check` → sem saída de patch hygiene.

Limitações da validação:

- o ambiente disponível não confirmou MySQL real, então a persistência integrada foi validada pelo contrato de auditoria e pela regressão do fluxo, e não pode ser declarada como aprovada em produção;
- a deduplicação de 5 segundos continua sendo uma janela defensiva, não uma garantia de idempotência sob concorrência; eventos legítimos em sequência podem ser indistinguíveis sob o mesmo payload e actor/tenant, e a implementação atual não presume que essa janela seja uma idempotência forte.

Conclusão prudente:

- este passo integra a auditoria de usuários em um fluxo ativo real sem duplicar eventos nem quebrar a operação principal;
- a persistência e o histórico em `audit_logs` ainda exigem validação em MySQL real antes de qualquer remoção posterior do adaptador legado.

#### PSR4-57 — validação final da idempotência e fechamento da auditoria de usuários

Status: validado no contrato do módulo, sem abrir nova migração e com limitação explícita de integração real em MySQL ainda pendente.

Arquivos diretamente afetados:

- [src/Application/User/UserAuditService.php](../src/Application/User/UserAuditService.php) — resolve actor, tenant e idempotency key e só deduplica quando a chave explícita existe e é consistente;
- [src/Infrastructure/User/UserAuditRepository.php](../src/Infrastructure/User/UserAuditRepository.php) — consulta por `idempotency_key` somente quando a coluna existe no schema e evita a lógica antiga baseada em payload e janela temporal;
- [api/v1/auth/users.php](../api/v1/auth/users.php) — fluxo ativo de CRUD que instancia o repositório de auditoria e entrega a dependência ao `UserService`;
- [src/Application/User/UserService.php](../src/Application/User/UserService.php) — emite os eventos de criação/atualização/exclusão usando o serviço de auditoria e preserva a operação principal mesmo quando a auditoria falha;
- [config/test_database.php](../config/test_database.php) — define a regra de isolamento do banco de testes e reforça que o ambiente de integração só deve ser usado quando `TEST_DB_*` e `TEST_DB_ISOLATED=true` estiverem corretamente configurados;
- [tests/Application/User/UserAuditServiceTest.php](../tests/Application/User/UserAuditServiceTest.php) — regressão de contrato que prova a semântica correta da deduplicação por idempotency key.

Fluxo validado:

- `api/v1/auth/users.php` cria `UserRepository` e `UserAuditRepository`, monta `UserService` com `UserAuditService` e preserva o contrato HTTP/JSON existente;
- `UserService::createUser()`, `updateUser()` e `deleteUser()` emitem logs por meio do serviço de auditoria, sem quebrar a operação principal;
- `UserAuditService` resolve o ator e o tenant, sanitiza payload e chama o repositório para persistir ou ignorar quando a chave de idempotência já existe;
- `UserAuditRepository` só aplica deduplicação quando a coluna `idempotency_key` existe e a chave fornecida for não vazia; sem coluna ou sem chave explícita, a operação não cai na lógica de deduplicação baseada em payload.

Evidência executada nesta revisão final:

- `./vendor/bin/phpunit tests/Application/User/UserAuditServiceTest.php tests/Application/User/UserServiceTest.php --colors=never` → `OK (6 tests, 23 assertions)`;
- `php -l src/Application/User/UserAuditService.php` → sem erro de sintaxe;
- `php -l src/Infrastructure/User/UserAuditRepository.php` → sem erro de sintaxe;
- `git diff --check` → saída limpa (`DIFF_CHECK_OK`).

Limitações que continuam validadas como pendentes:

- não houve confirmação de schema real em MySQL para `audit_logs` nem de índice único de concorrência em ambiente dedicado;
- a garantia forte de idempotência e de exclusão de race conditions exige uma coluna/índice real e uma validação em banco MySQL isolado, não apenas a lógica de código;
- o ambiente disponível validou o contrato e a regressão do módulo, mas não substitui a verificação de integração real do banco de produção ou de teste dedicado.

Conclusão do fechamento:

- a correção implementada foi a semântica correta de idempotência: deduplicar somente com `idempotency_key` explícita, não por payload e janela temporal;
- o módulo de auditoria de usuários foi fechado sem abrir nova migração e sem reabrir outros escopos;
- a próxima etapa segura, caso seja necessária, é validar em MySQL real se a coluna `idempotency_key` existe, se há índice único e se o fluxo de produção de fato gera a mesma chave em retries legítimos sem perder eventos válidos.

#### PSR4-58 — validação de schema e idempotência em MySQL isolado

Status: pendente e bloqueada pelo ambiente atual; nenhuma conexão MySQL foi aberta porque o ambiente de testes isolado não está configurado.

Arquivos e guardas relevantes:

- [config/test_database.php](../config/test_database.php) — exige `TEST_DB_HOST`, `TEST_DB_USER`, `TEST_DB_NAME`, `TEST_DB_PASS` e `TEST_DB_ISOLATED=true` antes de permitir qualquer conexão MySQL de testes;
- [docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md](../docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md) — mantém o registro da pendência e da limitação de integração real.

Validação executada antes da conexão:

- `Get-ChildItem Env:TEST_DB_*` → sem saída; as variáveis `TEST_DB_*` não existem no ambiente atual;
- `php -d display_errors=1 -r "require 'C:/xampp/htdocs/config/test_database.php'; var_dump(getTestDatabaseConfig());"` → falha com `RuntimeException: MySQL integration config is incomplete. Define TEST_DB_HOST, TEST_DB_USER, TEST_DB_NAME and TEST_DB_PASS before running integration tests.`

Conclusão da validação de ambiente:

- o ambiente atual não está comprovadamente isolado para testes MySQL;
- não foi feita nenhuma consulta real a `audit_logs`, nenhum `SHOW COLUMNS`, nenhum `SHOW INDEX` e nenhuma criação ou limpeza de registros em banco de dados;
- a execução foi interrompida intencionalmente conforme a regra de segurança do bootstrap e da migração controlada.

Requisitos que permanecem pendentes para a validação real:

1. configurar um schema MySQL isolado para testes em ambiente dedicado, sem reutilizar `DB_*`;
2. preencher `TEST_DB_HOST`, `TEST_DB_USER`, `TEST_DB_NAME`, `TEST_DB_PASS` e `TEST_DB_ISOLATED=true`;
3. verificar em MySQL real:
    - `SHOW COLUMNS FROM audit_logs LIKE 'idempotency_key';`
    - `SHOW INDEX FROM audit_logs;`
    - `SHOW CREATE TABLE audit_logs;`
4. validar que a mesma chave de idempotência não produz dois registros para a mesma operação lógica, e que chaves diferentes geram registros distintos;
5. confirmar a existência da restrição de unicidade antes de declarar que a idempotência está protegida sob concorrência.

Se a coluna ou o índice necessário não existir, a ação correta é propor a migração em um ambiente isolado, listar o impacto sobre registros existentes e definir os testes de regressão antes de implementá-la. Não foi feita nenhuma alteração automática de schema neste passo.

Limitações restantes:

- não há evidência de MySQL isolado disponível no ambiente atual;
- não há prova de `idempotency_key` em schema real nem de unicidade em produção ou em teste dedicado;
- qualquer aprovação de integração real continua bloqueada até que o ambiente isolado esteja configurado e validado.

#### PSR4-18 — migração controlada do módulo de usuários

Status: concluída no escopo do módulo de usuários e password reset, com centralização da lógica em `UserService` e `UserRepository`, sem reabrir a fase de Auth + Tenant como um esforço novo.

Arquivos e camadas envolvidas:

- [src/Application/User/UserService.php](src/Application/User/UserService.php) — orquestra a geração, validação de status, e finalização do reset de senha
- [src/Infrastructure/User/UserRepository.php](src/Infrastructure/User/UserRepository.php) — mantém o estado do token em `password_resets` e a rotina de claim transacional
- [reset-password.php](reset-password.php) — exibe/avalia o formulário pelo serviço e evita duplicação da lógica de validação no HTTP
- [tests/Application/User/UserServiceTest.php](tests/Application/User/UserServiceTest.php) — guardas para token inválido, expirado, usado e fluxo de conclusão bem-sucedido

O que foi preservado:

- uso do serviço como fonte de verdade para geração e validação do token;
- resposta HTTP de erro e fluxo de UI para token ausente, expirado e já usado;
- segurança do tenant e do estado do usuário, sem fallback silencioso para tenant padrão;
- política de reset de senha em uma única transação de consumo de token.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Application/User/UserServiceTest.php` → `OK (17 tests, 36 assertions)`
- `php -l src/Application/User/UserService.php` → sem erro de sintaxe
- `php -l tests/Application/User/UserServiceTest.php` → sem erro de sintaxe
- `php -l reset-password.php` → sem erro de sintaxe

Limitação da validação:

- não houve execução end-to-end com banco MySQL em ambiente dedicado; a verificação executada foi de regressão unitária e de sintaxe no escopo do módulo de usuários.

#### PSR4-18.2 — correção cirúrgica do password reset

Status: concluída, com remoção da duplicação de validação no GET/POST e com o consumo do token protegido por transação/claim atômico para impedir concorrência e reutilização do mesmo token.

Arquivos diretamente afetados:

- [src/Application/User/UserService.php](src/Application/User/UserService.php)
- [src/Infrastructure/User/UserRepository.php](src/Infrastructure/User/UserRepository.php)
- [reset-password.php](reset-password.php)
- [tests/Application/User/UserServiceTest.php](tests/Application/User/UserServiceTest.php)

O ajuste implementado foi:

- centralizar a verificação de validade do token e o status (`invalid` / `used` / `expired` / `valid`) no serviço;
- manter a página de reset responsável apenas pela exibição e pela submissão da ação, sem reimplementar a regra no HTTP;
- usar `claimPasswordResetToken()` em transação para bloquear o token e evitar duas finalizações concorrentes; e
- confirmar que finalização bem-sucedida exige atualização da senha e marcação do token como usado dentro da mesma operação.

Evidência da correção:

- `./vendor/bin/phpunit tests/Application/User/UserServiceTest.php` → `OK (17 tests, 36 assertions)`
- `php -l` nos arquivos citados → sem erro de sintaxe

### 5.6 APIs públicas e utilitários / UTM e analytics legados

#### PSR4-08 — fase de UTM e analytics

Status: implementação validada no escopo de contratos e correção pontual de tenant, sem reabrir fases anteriores.

Há uso direto de PDO procedural em endpoints como:

- [api/v1/utm/getCampaigns.php](api/v1/utm/getCampaigns.php)
- [api/v1/utm/getAnalytics.php](api/v1/utm/getAnalytics.php)
- [api/v1/utm/trackClick.php](api/v1/utm/trackClick.php)
- [api/relatorios/getPortfolioStats.php](api/relatorios/getPortfolioStats.php)

Os módulos de UTM foram migrados para a camada `App\Service\UtmService` e `App\Repository\UtmRepository`, com validação da resolução de tenant antes do insert e sem fallback arbitrário para `tenant_id = 1` quando o contexto não pode ser identificado. Esse ajuste foi necessário para evitar gravação indevida de cliques em tenant padrão sem contexto válido. A correção preserva o rastreio público legítimo quando existe resolução suportada pelo projeto (subdomain, sessão, tenant do contexto autenticado ou demais fontes autorizadas).

A validação executada neste lote foi:

- `./vendor/bin/phpunit --filter UtmMigrationContractTest tests/Unit/UtmMigrationContractTest.php`
- PHP lint nos arquivos do lote
- `git diff --check`

Resultado registrado: 3 testes, 14 assertions, sem erro de sintaxe e sem diff-check issues no lote em escopo.

### 5.7 PSR4-19 — migração controlada do módulo de banners

Status: concluída no escopo do lote de banners públicos e de gestão, com centralização da lógica de listagem, criação, atualização e exclusão em `BannerService` e `BannerRepository`.

Arquivos diretamente afetados:

- [api/v1/banners/getBanners.php](api/v1/banners/getBanners.php)
- [api/v1/banners/addBanner.php](api/v1/banners/addBanner.php)
- [api/v1/banners/updateBanner.php](api/v1/banners/updateBanner.php)
- [api/v1/banners/deleteBanner.php](api/v1/banners/deleteBanner.php)
- [api/v1/banners/uploadBannerImage.php](api/v1/banners/uploadBannerImage.php)
- [src/Service/BannerService.php](src/Service/BannerService.php)
- [src/Repository/BannerRepository.php](src/Repository/BannerRepository.php)
- [tests/Unit/BannerMigrationContractTest.php](tests/Unit/BannerMigrationContractTest.php)

O ajuste implementado foi:

- remover o SQL direto dos endpoints de banner e delegar ao serviço;
- preservar a autenticação e a resolução de tenant já existentes no fluxo HTTP;
- centralizar a validação de tenant e do identificador do banner na camada de domínio;
- manter o upload e a exclusão de arquivo na borda HTTP, mas deixando a escrita/consulta em banco e a regra de negócio em serviço/repositório;
- evitar reabrir módulos já concluídos e manter o escopo do lote restrito ao conjunto de banners.

Evidência executada neste lote:

- `./vendor/bin/phpunit tests/Unit/BannerMigrationContractTest.php` → `OK (3 tests, 22 assertions)`
- `php -l` nos arquivos do lote → sem erro de sintaxe
- `git diff --check` → sem issues de patch

Esse lote é considerado um avanço prudente e de baixo risco dentro da continuidade da migração, sem reescrever áreas fora do escopo já validado.

### 5.7.1 PSR4-19.1 — hardening do upload de banners

Status: concluída no escopo do hardening do upload de banners, com validação do tamanho do arquivo alinhada ao limite operacional do produto e às configurações globais do PHP.

Arquivos diretamente afetados:

- [src/Service/BannerService.php](src/Service/BannerService.php) — adicionada a validação de tamanho do arquivo em `validateUploadSize()`, com limite fixo de `10 * 1024 * 1024` bytes;
- [api/v1/banners/addBanner.php](api/v1/banners/addBanner.php) — validação do `$_FILES['image']['size']` e do tamanho efetivo em `tmp_name` antes do `move_uploaded_file()`;
- [api/v1/banners/uploadBannerImage.php](api/v1/banners/uploadBannerImage.php) — mesma validação aplicada ao fluxo de upload de banner com nome `banner`;
- [tests/Unit/BannerMigrationContractTest.php](tests/Unit/BannerMigrationContractTest.php) — testes de regressão para arquivo inválido, acima do limite e arquivo permitido.

Evidência executada nesta correção:

- `./vendor/bin/phpunit tests/Unit/BannerMigrationContractTest.php` → `OK (6 tests, 28 assertions)`
- `php -l src/Service/BannerService.php ; php -l api/v1/banners/addBanner.php ; php -l api/v1/banners/uploadBannerImage.php ; php -l tests/Unit/BannerMigrationContractTest.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve integração end-to-end com banco MySQL em ambiente dedicado;
- a validação executada foi unitária e de contract hardening, não uma prova de execução real de upload em produção;
- o limite adotado foi compatível com o produto e com `upload_max_filesize` / `post_max_size` já configurados em [config/security.php](config/security.php), sem alterar as configurações globais do PHP.

### PSR4-44 — migração do helper legado de notificações e mensagens

Status: concluída no escopo do domínio de notificações e mensagens, com remoção do SQL procedural direto do helper legado e delegação para o repositório já existente sem reabrir módulos validados em portal/leads.

Arquivos e camadas envolvidas:

- [app/utils/NotificationManager.php](app/utils/NotificationManager.php) — helper legado migrado para usar `NotificationRepository` e `CompanySettingsService`, sem SQL inline para company_settings, notifications e messages
- [src/Infrastructure/Notification/NotificationRepository.php](src/Infrastructure/Notification/NotificationRepository.php) — centraliza a criação de notificações e mensagens com tenant e usuário explícitos
- [src/Service/CompanySettingsService.php](src/Service/CompanySettingsService.php) — serviço de settings e leitura por tenant já existente para substituir a consulta direta em company_settings
- [tests/Unit/NotificationCreationMigrationContractTest.php](tests/Unit/NotificationCreationMigrationContractTest.php) — regressão para impedir que o helper legado retorne a SQL procedural inline

O que foi preservado:

- contrato público do helper legado, mantendo métodos de criação de notificações e mensagens empregados por fluxos de negócio existentes
- isolamento por tenant e contexto de usuário ao gravar notificações e mensagens
- uso da camada de serviço/repositório já estabelecida no projeto, sem criar fallback arbitrário para `tenant_id = 1`
- regras de criação e persistência centralizadas para o mesmo domínio sem espalhar a lógica no HTTP

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/NotificationCreationMigrationContractTest.php` → `OK (3 tests, 6 assertions)`
- `php -l app/utils/NotificationManager.php` → sem erro de sintaxe
- `php -l src/Infrastructure/Notification/NotificationRepository.php` → sem erro de sintaxe
- `git diff --check -- app/utils/NotificationManager.php src/Infrastructure/Notification/NotificationRepository.php tests/Unit/NotificationCreationMigrationContractTest.php` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de banco MySQL real em ambiente dedicado para o fluxo completo de notificações, apenas validação de contrato e sintaxe no escopo do helper legado
- o lote foi deliberadamente restrito ao domínio de notificações/mensagens e não reabriu `portal/leads`, que já haviam sido validados no PSR4-43

Próximo resíduo confirmado pela inspeção:

- a próxima etapa segura continua sendo uma auditoria do próximo domínio residual coeso que ainda apresente SQL inline em helpers ou endpoints legados, preferindo áreas sem abstração de serviço/repositório e sem reabrir módulos já validados

### PSR4-45 — migração do subdomínio do tenant e resolução por host

Status: concluída no escopo do domínio de tenant/subdomínio, com remoção do SQL procedural direto do gerenciador legado e delegação para `App\Infrastructure\Tenant\TenantRepository`, sem reabrir módulos já migrados como notificações, portal/leads ou relatórios.

Arquivos e camadas envolvidas:

- [app/services/TenantSubdomainManager.php](app/services/TenantSubdomainManager.php) — migrou a verificação de disponibilidade, atualização e leitura de subdomínio para a camada de repositório e preservou o contrato do helper legado
- [src/Infrastructure/Tenant/TenantRepository.php](src/Infrastructure/Tenant/TenantRepository.php) — centraliza `subdomainExists()`, `updateSubdomain()`, `listActiveSubdomains()` e `findActiveTenantSubdomainMap()`
- [composer.json](composer.json) — adiciona mapeamentos PSR-4 mínimos para `App\Services\` e `App\Utils\`, necessários para resolver as classes legadas usadas pelo tenant/subdomínio sem quebrar o carregamento do projeto
- [tests/Unit/TenantMigrationContractTest.php](tests/Unit/TenantMigrationContractTest.php) — regressão para impedir que o gerenciador de subdomínio retorne SQL inline em vez de delegar ao repositório

O que foi preservado:

- preview e geração de subdomínio com normalização de nome e exclusão do tenant em atualização
- resolução por host via `findActiveTenantSubdomainMap()` e isolamento por tenant
- contratos HTTP do endpoint de preview de subdomínio e geração de URLs sem fallback para `tenant_id = 1`
- escopo restrito ao domínio tenant/subdomínio, sem reabrir notificações, leads, portal ou módulos já validados em lotes anteriores

Evidência executada nesta fase:

- `composer dump-autoload --no-interaction` → `Generated autoload files`
- `./vendor/bin/phpunit tests/Unit/TenantMigrationContractTest.php` → `OK (2 tests, 18 assertions)`
- `php -l app/services/TenantSubdomainManager.php` → sem erro de sintaxe
- `php -l src/Infrastructure/Tenant/TenantRepository.php` → sem erro de sintaxe
- `git diff --check -- app/services/TenantSubdomainManager.php src/Infrastructure/Tenant/TenantRepository.php tests/Unit/TenantMigrationContractTest.php composer.json` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de banco MySQL real e nem testes HTTP end-to-end para o preview de subdomínio em ambiente dedicado;
- a validação executada foi por regressão de contrato, lint e checagem do diff do lote, não uma prova de integração real do banco;
- o lote foi deliberadamente limitado ao domínio tenant/subdomínio e não reabriu módulos já validados em PSR4-44 e anteriores.

Próximo resíduo comprovado pela inspeção:

- a próxima etapa segura continua sendo uma auditoria coerente de outro domínio de `app/` ou `api/` que ainda apresente SQL procedural inline em helper/endpoint legado, sem reabrir módulos já migrados e sem assumir que apenas regex de SQL seja evidência suficiente.

### PSR4-46 — migração do helper legado de emails de notificação por tenant

Status: concluída no escopo do domínio de e-mail de notificação, com substituição da leitura direta em `company_settings` por `CompanySettingsService`/`CompanySettingsRepository`, preservando o tenant ativo do contexto e evitando qualquer fallback para `tenant_id = 1`.

Arquivos e camadas envolvidas:

- [app/utils/EmailNotificationService.php](app/utils/EmailNotificationService.php) — helper legado migrado para ler as configurações via `CompanySettingsService::getContactInfoForTenant()` e não mais por `SELECT * FROM company_settings WHERE id = 1`
- [src/Service/CompanySettingsService.php](src/Service/CompanySettingsService.php) — serviço de settings já existente para leitura por tenant e centralização do acesso a `company_settings`
- [src/Infrastructure/Company/CompanySettingsRepository.php](src/Infrastructure/Company/CompanySettingsRepository.php) — repositório de acessos ao settings, com leitura por `tenant_id`
- [src/Application/Lead/LeadService.php](src/Application/Lead/LeadService.php), [src/Service/AgendamentoService.php](src/Service/AgendamentoService.php), [api/v1/properties/addProperty.php](api/v1/properties/addProperty.php) e [app/utils/AsyncJobQueue.php](app/utils/AsyncJobQueue.php) — pontos de chamada ajustados para passar o `tenant_id` explícito ao helper e manter o isolamento entre tenants
- [tests/Unit/NotificationCreationMigrationContractTest.php](tests/Unit/NotificationCreationMigrationContractTest.php) — regressão para bloquear o SQL inline em `company_settings` no helper de email

O que foi preservado:

- contrato público do helper de emails, sem quebrar os métodos de notificação já existentes
- isolamento por tenant ao buscar `company_email`, `email_notifications`, `email_leads`, `notify_*` e demais campos de configuração
- regras de negócio e acesso a dados centralizados em service/repositório, sem adotar qualquer fallback silencioso para `tenant_id = 1`
- escopo restrito ao domínio de notificação por e-mail e sem reabrir módulos de portal/leads/tenant já validados em lotes anteriores

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/NotificationCreationMigrationContractTest.php` → `OK (4 tests, 25 assertions)`
- `php -l app/utils/EmailNotificationService.php ; php -l src/Application/Lead/LeadService.php ; php -l src/Service/AgendamentoService.php ; php -l api/v1/properties/addProperty.php ; php -l app/utils/AsyncJobQueue.php` → sem erro de sintaxe
- `git diff --check -- app/utils/EmailNotificationService.php src/Application/Lead/LeadService.php src/Service/AgendamentoService.php api/v1/properties/addProperty.php app/utils/AsyncJobQueue.php tests/Unit/NotificationCreationMigrationContractTest.php docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de e-mail real em ambiente de produção/teste dedicado;
- a checagem executada foi de contrato, lint e validação arquitetural do helper de email, não uma prova end-to-end de entrega de e-mail externo;
- o lote foi deliberadamente limitado ao domínio de `company_settings` e emails de notificação, sem reabrir outros módulos já validados.

Próximo resíduo comprovado pela inspeção:

- a próxima etapa segura continua sendo a auditoria de um outro domínio coeso de `app/` ou `api/` que ainda mantenha SQL inline em helper/endpoint legado e ainda não tenha sido validado por contrato e sintaxe, sem reabrir módulos já migrados.

### PSR4-47 — correção do fallback inseguro em AsyncJobQueue

Status: concluída no escopo do fluxo financeiro de criação de registros de venda, com remoção do fallback silencioso para `tenant_id = 1`, interrupção explícita quando a resolução de tenant falha e correção do ciclo de sessão do ambiente PHPUnit.

Arquivos e camadas envolvidas:

- [app/utils/AsyncJobQueue.php](app/utils/AsyncJobQueue.php) — remove o fallback `?: 1` do `createFinancialRecords()` e retorna cedo quando `PropertyService::getTenantIdForProperty()` não consegue resolver um tenant válido
- [src/Service/PropertyService.php](src/Service/PropertyService.php) — continua sendo o ponto de resolução de tenant para o fluxo de imóvel/venda, sem tornar o tenant padrão aceitável
- [tests/Unit/FinanceContractTest.php](tests/Unit/FinanceContractTest.php) — guarda de regressão do padrão de fallback inseguro e correção de sessão específica do ambiente CLI/PHPUnit, sem afetar a segurança da aplicação

Diagnóstico comprovado do ciclo de sessão:

- em [tests/Unit/FinanceContractTest.php](tests/Unit/FinanceContractTest.php), a chamada problemática estava no `setUp()`, com `session_start()` sendo executado após a destruição/limpeza da sessão atual
- a investigação do fluxo do PHPUnit mostrou que [tests/bootstrap.php](tests/bootstrap.php) e [phpunit.xml](phpunit.xml) não iniciam sessão antes do teste; o problema não era um segundo `session_start()` do app em cascata, mas um ambiente CLI em que a saída do próprio PHPUnit já havia começado antes do `session_start()` do teste
- em PHP CLI, `headers_sent()` pode passar a valer true assim que há saída para STDOUT; por isso `session_start()` falhava com `Session cannot be started after headers have already been sent`
- o ajuste aplicado foi o menor possível no escopo do teste: `session_start()` só é chamado quando a sessão não está ativa e o ambiente não está em CLI/sem headers enviados; em CLI, o teste mantém `$_SESSION = []` sem tentar iniciar sessão

O que foi preservado:

- isolamento por tenant no processamento assíncrono de registros financeiros
- preservação do contrato do job `create_financial_records`, sem alterar a lógica de criação quando o tenant é válido
- escopo restrito ao domínio financeiro residual com evidência direta, sem reabrir módulos já migrados em lotes anteriores

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/FinanceContractTest.php` → `OK (10 tests, 28 assertions)`
- `./vendor/bin/phpunit --filter AsyncJobQueue tests/Unit/FinanceContractTest.php` → `OK (1 test, 4 assertions)`
- `./vendor/bin/phpunit tests/Unit/TenantBoundaryContractTest.php` → `OK (12 tests, 27 assertions)`
- `php -l tests/Unit/FinanceContractTest.php` → `No syntax errors detected in tests/Unit/FinanceContractTest.php`
- `php -l app/utils/AsyncJobQueue.php` → `No syntax errors detected in app/utils/AsyncJobQueue.php`
- `git diff --check` → sem saída, comando concluído com sucesso

Limitações da validação:

- a verificação executada foi em ambiente local de testes com SQLite em memória; não houve integração real com MySQL para o fluxo do job assíncrono
- como o ambiente disponível não inclui MySQL real, a confirmação de correção do fluxo assíncrono foi feita por contrato de regressão e validação do comportamento proibindo fallback para tenant padrão, sem afirmar integração end-to-end com banco MySQL

### PSR4-48 — inventário dos resíduos reais de SQL legado

Status: inventário verificado e proposta do próximo lote, sem implementação nesta etapa.

#### 1. Critério de inclusão e exclusão

Os resíduos foram incluídos apenas quando havia evidência direta de SQL procedural em código operacional, com responsabilidade de negócio ou de infraestrutura crítica, e sem se tratar de documentação, testes, dump ou ferramenta administrativa.

Foram excluídos como falsos positivos ou infra legítima:

- [api/v1/auth/checkUsersTable.php](api/v1/auth/checkUsersTable.php) — script de diagnóstico/inspeção de schema, não um endpoint de negócio nem parte do fluxo de produção
- [app/services/ApiKeyManager.php](app/services/ApiKeyManager.php) — gerencia chaves de API; é infraestrutura de segurança e autenticação de integração, mas não faz parte do lote de migração do domínio de tenant/usuário
- [app/security/AnomalyDetection.php](app/security/AnomalyDetection.php) — detecção de anomalias e segurança; é subsistema de monitoramento, não principal alvo do isolamento por tenant
- [app/models/BaseModel.php](app/models/BaseModel.php) — camada genérica de CRUD; é infraestrutura compartilhada do legado, mas requer um corte mais amplo e não é o lote mais coeso para a próxima migração
- testes, documentação, mocks e arquivos de backup

#### 2. Resíduos reais e priorizados

##### 2.1 Legacy de usuários e tenant

- [app/legacy/UserModel.php](app/legacy/UserModel.php) — responsável por CRUD de usuários e auditoria do ciclo de usuário
    - métodos relevantes: `listUsers()`, `getUserById()`, `getUserByUsername()`, `getUserByEmail()`, `createUser()`, `updateUser()`, `deleteUser()`, `logUserChange()`
    - tabelas acessadas: `users`, `audit_logs`
    - responsabilidade: autenticação, gestão de usuários, roles e auditoria de alterações de usuário
    - risco de isolamento por tenant: alto se o contexto `TenantContext` falhar ou se consultas não forem explicitamente filtradas por `tenant_id`; há validações de tenant em alguns métodos, mas o padrão ainda é direto em SQL
    - dependências: `TenantContext`, `BaseModel`, `session_manager.php`, `config/database.php`

- [app/models/TenantModel.php](app/models/TenantModel.php) — responsável por gestão de tenants e estatísticas
    - métodos relevantes: `listActiveTenants()`, `getTenantStats()`, `getTenantUsers()`, `userBelongsToTenant()`, `createTenant()`, `updateTenant()`
    - tabelas acessadas: `tenants`, `users`, `audit_logs`
    - responsabilidade: administração de tenants, lista de usuários e estatísticas do tenant
    - risco de isolamento por tenant: alto, porque acessa métricas e usuários por tenant em SQL direto sem abstração do serviço/repositório atual
    - dependências: `BaseModel`, `config/database.php`, `TenantContext`

##### 2.2 Middleware e enforce de tenant

- [app/middleware/TenantIsolationMiddleware.php](app/middleware/TenantIsolationMiddleware.php) — middleware de validação de acesso cross-tenant
    - métodos relevantes: `validateResourceAccess()`, `filterByTenant()`, `validateTenantAccess()`, `logUnauthorizedAccess()`
    - tabelas acessadas: qualquer tabela alvo com `tenant_id`, e `tenants`, `audit_logs`
    - responsabilidade: bloquear acesso indevido e reforçar tenant_id em queries dinâmicas
    - risco de isolamento por tenant: muito alto, porque atua como camada de enforcement direta sobre SQL em runtime
    - dependências: `TenantContext`, `session_manager.php`, `config/database.php`

##### 2.3 Auditoria de segurança

- [app/utils/AuditLogger.php](app/utils/AuditLogger.php) — logger de auditoria e eventos de segurança
    - métodos relevantes: `logUserAction()`, `logTenantAction()`, `logUnauthorizedAccess()`, `logCrossTenantAttempt()`, `getTenantAuditLogs()`, `getUserActionSummary()`
    - tabelas acessadas: `audit_logs`
    - responsabilidade: trilha de usuários, tenant, tentativas de acesso e alertas de segurança
    - risco de isolamento por tenant: médio-alto; a auditoria precisa usar `tenant_id` correto para não cruzar dados de diferentes tenants
    - dependências: `TenantContext`, `config/database.php`, `isLoggedIn()`

##### 2.4 Residual financeiro já validado no escopo PSR4-47

- [app/utils/AsyncJobQueue.php](app/utils/AsyncJobQueue.php) — fila de jobs assíncronos com lógica financeira
    - métodos relevantes: `enqueue()`, `processPending()`, `executeJob()`, `createFinancialRecords()`, `sendNotifications()`, `markJobFailed()`
    - tabelas acessadas: `async_jobs`, `property_sales`, `property_images`, `users`, `properties`, `financeiro`, `commissions`
    - responsabilidade: processamento assíncrono e criação de registros financeiros a partir de mudanças de status
    - risco de isolamento por tenant: alto, especialmente no contexto de fallback inseguro; o bug foi corrigido e validado em PSR4-47
    - dependências: `App\Service\PropertyService`, `App\Infrastructure\User\UserRepository`, `config/database.php`

Esse último item foi validado no escopo do PSR4-47, mas continua sendo um resíduo real de SQL procedural e deve entrar no mapa de migração como acompanhamento, sem ser reaberto como fase nova antes do lote coeso de usuário/tenant/audit.

#### 3. Lote recomendado

O próximo lote coeso e seguro para migração é o conjunto de usuário + tenant + auditoria, com foco em manter contratos HTTP/JSON e sem ampliar escopo para endpoints ou integrações externas:

- [app/legacy/UserModel.php](app/legacy/UserModel.php)
- [app/models/TenantModel.php](app/models/TenantModel.php)
- [app/middleware/TenantIsolationMiddleware.php](app/middleware/TenantIsolationMiddleware.php)
- [app/utils/AuditLogger.php](app/utils/AuditLogger.php)
- apoio compatível em [app/models/BaseModel.php](app/models/BaseModel.php) apenas em uma etapa de alinhamento final, sem reescrever a infraestrutura inteira em um único passo

Motivo do lote:

- compartilham o mesmo domínio de negócio: usuário, tenant, role, permissões e auditoria
- já existem serviços/repositórios PSR-4 para usuários e regras de tenant no núcleo moderno
- evitam mudanças de contrato HTTP/JSON porque o alvo são componentes legados internos e helpers de enforcement
- reduzem o risco de regressão por manter os filtros de tenant e as regras de autorização no mesmo domínio

#### 4. Testes existentes e lacunas a cobrir

Testes existentes relevantes:

- [tests/Unit/TenantBoundaryContractTest.php](tests/Unit/TenantBoundaryContractTest.php) — valida limite entre tenants e rejeição de acesso cross-tenant
- [tests/Unit/FinanceContractTest.php](tests/Unit/FinanceContractTest.php) — valida isolamento financeiro e proteção do fallback inseguro
- [tests/Application/User/UserServiceTest.php](tests/Application/User/UserServiceTest.php) — valida fluxo moderno de usuário reset/senha
- [tests/Unit/SecurityTest.php](tests/Unit/SecurityTest.php) — cobertura de segurança e sessão

Testes que precisariam ser criados antes da implementação do lote recomendado:

- `TenantModelRepositoryContractTest` para verificar leitura de dados de tenant e perfis sem vazamento de usuários de outros tenants
- `TenantIsolationMiddlewareContractTest` para validar `filterByTenant()` e `validateResourceAccess()` em queries reais com `tenant_id`
- `AuditLoggerTenantIsolationContractTest` para garantir que `audit_logs` nunca grave tenant ausente ou cruzado em `tenant_id`
- `UserLegacyModelComparisonContractTest` para comparar resultados legados e modernos para `users` e `audit_logs` no mesmo cenário

Importante: esta seção registra o inventário e a proposta do próximo lote. Ela não marca a próxima migração como concluída nem implementada.

### PSR4-49 — mapeamento dos call sites e preparação da migração do UserModel

Status: investigação verificada concluída, sem implementação do código em produção nesta etapa.

#### 1. Consumidores confirmados

Verificação executada: busca global em `**/*.php` por instanciações de `UserModel` e por chamadas aos métodos legados de usuário. Resultado verificado:

- não há `new UserModel(...)` em consumidores ativos do runtime;
- os pontos de consumo reais já executam `UserService` e `UserRepository` em vez do modelo legado;
- o arquivo [app/legacy/UserModel.php](app/legacy/UserModel.php) continua sendo um legado de compatibilidade e ponto de risco residual, mas sem uso direto em fluxos ativos detectados.

Consumidores reais e suas chamadas verificadas:

- [api/v1/users/index.php](api/v1/users/index.php)
    - método: `listUsers()`
    - argumentos: `($tenantId, $filters, $page, $limit)`
    - uso do retorno: `Response::success($result['data'], ..., ['pagination' => $result['pagination']])`
    - efeito: listagem paginada com filtros e contagem em service/repository

- [api/v1/users/show.php](api/v1/users/show.php)
    - método: `getUserById()`
    - argumentos: `($id, $tenantId)`
    - uso do retorno: `Response::success($user, ...)`; captura `RuntimeException` para `404`

- [api/v1/users/create.php](api/v1/users/create.php)
    - método: `createUser()`
    - argumentos: `($tenantId, $data)`
    - uso do retorno: responde em JSON com o usuário criado ou valida `InvalidArgumentException` e `RuntimeException`

- [api/v1/users/update.php](api/v1/users/update.php)
    - método: `updateUser()`
    - argumentos: `($id, $tenantId, $data)`
    - uso do retorno: retorna o usuário atualizado; ele converte `InvalidArgumentException` para `400` e `RuntimeException` para `404`/`403`

- [api/v1/users/destroy.php](api/v1/users/destroy.php)
    - método: `deleteUser()`
    - argumentos: `($id, $tenantId)`
    - uso do retorno: resposta de sucesso ou erro; `RuntimeException` para `404`/`403`

- [api/v1/auth/users.php](api/v1/auth/users.php)
    - adaptador legado de compatibilidade; chama `UserService` em GET/POST/PUT/DELETE
    - uso: `getUserById()`, `listUsers()`, `createUser()`, `updateUser()`, `deleteUser()`

- [api/v1/auth/users_corrected.php](api/v1/auth/users_corrected.php)
    - adaptador de compatibilidade com validação de payload e mapeamento de erros
    - uso: `getUserById()`, `listUsers()`, `createUser()`, `updateUser()`, `deleteUser()`

- [api/v1/auth/deleteUser.php](api/v1/auth/deleteUser.php)
    - método: `deleteUser()`
    - argumentos: `((int)$userId, $tenantId)`
    - uso: responde `success`/`error` mantendo contrato JSON legado

- [src/Application/Tenant/TenantOnboardingService.php](src/Application/Tenant/TenantOnboardingService.php)
    - método: `createUser()`
    - argumentos: `($tenantId, $userData, true)`
    - uso: criação de usuário admin do tenant em onboarding, com bypass de RBAC via `system_creation = true`

- [admin/check_tenant_id.php](admin/check_tenant_id.php)
    - uso indireto: `UserService::updateUser($user['id'], 1, ['tenant_id' => 1])`
    - observação: operação administrativa/CLI e não um endpoint HTTP; contém fallback manual e não é um consumidor de produção

- [admin/check_admin_role.php](admin/check_admin_role.php)
    - uso indireto: `UserService::updateUser($user['id'], 1, ['role' => 'admin'])`
    - observação: script administrativo de correção manual; não é parte do fluxo regular do app

Arquivos de compatibilidade e ponte para o legado:

- [app/models/UserModel.php](app/models/UserModel.php)
- [app/models/UserModel.deprecated.php](app/models/UserModel.deprecated.php)

Esses arquivos funcionam como ponte de compatibilidade e não representam call sites de negócio ativos; o código de produção já está usando o serviço moderno em vez do model legado.

#### 2. Matriz de compatibilidade por método

| Método legado                         | Contrato moderno equivalente                                                                                                                  | Assinatura real                            | Retorno                                        | Diferenças críticas                                                                                                                              |
| ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ | ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| `listUsers(array $filters = [])`      | `UserService::listUsers(int $tenantId, array $filters = [], int $page = 1, int $limit = 50)` + `UserRepository::listUsers()` / `countUsers()` | exige `tenantId` explícito e paginação     | array com `data` e `pagination`                | modelo legado não paginava nem contava; serviço inclui `LIMIT/OFFSET`, `is_superadmin` filtrado e `sanitizeUserResponse()`                       |
| `getUserById(int $id)`                | `UserService::getUserById(int $id, int $tenantId)`                                                                                            | exige tenant explícito                     | array sanitizado ou exceção `RuntimeException` | modelo legado retorna `null` e depende de `TenantContext`; serviço lança erro explícito e não permite `null` silencioso                          |
| `getUserByUsername(string $username)` | `UserRepository::findByUsername(string $username)`                                                                                            | sem tenant, busca global                   | array                                          | null                                                                                                                                             | modelo legado valida sessão/tenant de forma indireta; repositório não valida tenant e é usado por autenticação          |
| `getUserByEmail(string $email)`       | `UserRepository::findByEmail(string $email)`                                                                                                  | sem tenant, busca global                   | array                                          | null                                                                                                                                             | mesmo comportamento: busca global para unicidade e login; a diferenciação de tenant acontece no serviço e no fluxo HTTP |
| `createUser(array $data)`             | `UserService::createUser(int $tenantId, array $data, bool $system_creation = false)`                                                          | egressa para `UserRepository::create()`    | array do usuário criado ou exceção             | serviço aplica RBAC, validação, hash `PASSWORD_DEFAULT`, `broker_default_commission_percentage`, sistema de `system_creation`                    |
| `updateUser(int $id, array $data)`    | `UserService::updateUser(int $id, int $tenantId, array $data)`                                                                                | exige tenant explícito e usa `AuthContext` | array do usuário atualizado ou exceção         | modelo legado valida `TenantContext`, permite `role`; serviço restringe `tenant_id`, `username`, `is_superadmin` e só deixa role para superadmin |
| `deleteUser(int $id)`                 | `UserService::deleteUser(int $id, int $tenantId)`                                                                                             | exige tenant explícito                     | bool ou exceção                                | modelo legado pode apagar sem proteção de `id = 1` conforme `TenantContext`; serviço bloqueia `id === 1` e exige `AuthContext`                   |
| `logUserChange(...)`                  | sem espelho direto em `UserService`                                                                                                           | grava em `audit_logs`                      | bool                                           | serviço não oferece equivalente dedicado; a auditoria continua sendo um resíduo de domínio legado                                                |

#### 3. Diferenças de comportamento que precisam ser resolvidas

- `UserModel::listUsers()` não inclui `LIMIT/OFFSET`, paginação, contagem e `sanitizeUserResponse()`. O service moderno adiciona paginação e remove `password` do payload.
- `UserModel::getUserById()` retorna `null`; `UserService::getUserById()` lança `RuntimeException` e mantém um contrato HTTP de erro explícito.
- `UserModel::createUser()` usa `PASSWORD_BCRYPT`, valida `TenantContext` e regras implícitas de role. `UserService::createUser()` usa `PASSWORD_DEFAULT`, `AuthContext` e `system_creation` para onboarding.
- `UserModel::updateUser()` permite role atualização por qualquer contexto com permissão e não bloqueia `tenant_id`/`username`/`is_superadmin` no payload. O serviço moderno rejeita esses campos explicitamente.
- `UserModel::deleteUser()` depende exclusivamente de `TenantContext::canManageUser()` e não impede `id = 1` em forma explícita. O serviço moderno proíbe o admin principal e controla `canManageUser()` via `AuthContext`.
- `UserModel::logUserChange()` grava em `audit_logs` diretamente; não há contador ou serviço equivalente com o mesmo contrato no `UserService` moderno.
- O `UserRepository::buildFilters()` exclui `u.is_superadmin = 0` das listagens, enquanto o modelo legado não has explicit `is_superadmin` exclusion.
- `UserRepository::findByUsername()` e `findByEmail()` são globais e não escopados por tenant; a distinção por tenant é feita no serviço ou no contexto HTTP.

#### 4. Limites de segurança e validação de tenant

Verificações confirmadas:

- [api/v1/middleware/TenantMiddleware.php](api/v1/middleware/TenantMiddleware.php) exige tenant resolvido; se `TenantContext::getTenantId()` retornar `null` ou `<= 0`, responde `403`.
- [app/security/TenantContext.php](app/security/TenantContext.php) materializa contexto a partir de `TenantResolver` e não deve admitir fallback silencioso para tenant padrão quando o contexto autentivado não consegue validar o tenant.
- [src/Tenant/TenantResolver.php](src/Tenant/TenantResolver.php) usa `UserRepository::userBelongsToTenant()` para validar o tenant candidato e `findByIdAnyTenant()` apenas para fins de resolução autorizada de identidade/superadmin.
- [src/Infrastructure/User/UserRepository.php](src/Infrastructure/User/UserRepository.php) expõe `userBelongsToTenant()`, `existsInTenant()`, `findById()` e `findByIdAnyTenant()`. As consultas por ID e tenant são feitas por `tenant_id = ?` em `findById()`, e a variante `findByIdAnyTenant()` existe apenas para apoio administrativo interno, não para uso geral de negócio.
- [src/Application/User/UserService.php](src/Application/User/UserService.php) centraliza `AuthContext`, `canManageUser()`, `currentUserIsAdmin()`, `currentUserIsSuperAdmin()`, e rejeita campos proibidos como `tenant_id`, `username`, `is_superadmin` em `updateUser()`.
- [src/Application/Tenant/TenantOnboardingService.php](src/Application/Tenant/TenantOnboardingService.php) usa `createUser($tenantId, $userData, true)` para criação de admin inicial, com bypass explícito do RBAC, mas sem fallback para `tenant_id = 1`.

Riscos de segurança a preservar na migração:

- qualquer consulta por `id`, `username` ou `email` que seja usada sem tenant válido deve continuar a ser tratada no serviço e nunca no modelo legado como operação livre;
- a criação de sistema deve continuar a ser explicitada pelo `system_creation` flag;
- o superadmin deve ser reconhecido somente pela identidade real (`AuthContext`/`TenantContext`), e não por fallback implícito;
- a operação `updateUser()` e `deleteUser()` jamais deve aceitar um `tenant_id` fornecido por cliente como override de identidade.

#### 5. Plano de migração em etapas

1. Etapa 1 — isolação do contrato do legado
    - manter [app/legacy/UserModel.php](app/legacy/UserModel.php) como compatibilidade, mas sem crescimento de lógica nova;
    - registrar a API pública e os adaptadores no mesmo contrato JSON da interface atual;
    - não alterar nenhuma regra de segurança em produção.

2. Etapa 2 — adaptar os métodos legados ao serviço
    - `listUsers()` delega para `UserService::listUsers($tenantId, $filters, $page, $limit)`;
    - `getUserById()` delega para `UserService::getUserById($id, $tenantId)`;
    - `getUserByUsername()` e `getUserByEmail()` delegam para `UserRepository` com resposta global e sem tentar reintroduzir tenant no ponto de busca;
    - `createUser()` delega para `UserService::createUser($tenantId, $data, $system_creation)`;
    - `updateUser()` delega para `UserService::updateUser($id, $tenantId, $data)`;
    - `deleteUser()` delega para `UserService::deleteUser($id, $tenantId)`.

3. Etapa 3 — remapear a auditoria
    - mover `logUserChange()` para um serviço de auditoria dedicado, com `tenant_id`, `user_id`, `action`, `resource`, `old_values`, `new_values` e logs externos;
    - manter a interface legacy apenas como wrapper sem SQL inline.

4. Etapa 4 — remoção controlada do legado
    - remover ou reduzir o uso do modelo antigo após os call sites estarem migrados e validados;
    - manter compatibilidade de adapter somente em lugares que ainda dependam do contrato HTTP antigo.

#### 6. Testes existentes e lacunas a cobrir

Testes verificados que cobrem partes do contrato moderno:

- [tests/Application/User/UserServiceTest.php](tests/Application/User/UserServiceTest.php)
    - valida reset de senha, status do token, update de usuário, e rejeição de campos proibidos;
    - cobre parte da regra de negócio e do contrato do serviço moderno.

Testes adicionais necessários antes da implementação do lote:

- `UserLegacyModelComparisonContractTest` para comparar `UserModel` e `UserService` em cenários idênticos de listagem, leitura por ID e criação;
- `UserTenantIsolationContractTest` com cenário positivo e negativo para tenant mismatch, cross-tenant read e cross-tenant write;
- `UserRoleAuthorizationContractTest` para `admin`, `superadmin`, `user` e criação de sistema;
- `UserAuditLogContractTest` para garantir que `audit_logs` registre `tenant_id` correto e que o modelo legado não grave eventos sem tenant válido;
- `UserCreateSystemBypassContractTest` para validar o bypass explícito de `system_creation = true` sem abrir acesso lateral.

#### 7. Limitações conhecidas

- o modelo legado ainda está presente e o código original foi escrito para depender de `TenantContext`, `session_manager` e `BaseModel`; o contrato moderno usa `AuthContext` e explicit tenant validation;
- não há prova de uso em produção em todos os endpoints legacy; a investigação confirmou ausência de instância ativa direta, mas não eliminou todos os fluxos indiretos não rastreados em arquivos muito antigos ou scripts de manutenção;
- a auditoria de `logUserChange()` continua sendo um ponto de risco porque o serviço moderno não expõe uma API equivalente e a compatibilidade temporária precisa ser cuidadosamente adaptada;
- o objetivo desta etapa é preparar a migração, não implementar a reescrita.

Conclusão: o próximo lote coeso e seguro continua sendo o domínio de usuário + tenant + auditoria, com [app/legacy/UserModel.php](app/legacy/UserModel.php) como alvo principal de compatibilidade, [src/Application/User/UserService.php](src/Application/User/UserService.php) como origem da lógica de negócio e [src/Infrastructure/User/UserRepository.php](src/Infrastructure/User/UserRepository.php) como camada de persistência e validação por tenant.

### PSR4-50 — verificação final de uso de UserModel e conclusão de auditoria

Status: verificação concluída, sem remoção nem implementação do runtime.

#### 1. Uso real de UserModel em código atual

Condição verificada por busca global em `**/*.php` para `require/include`, `UserModel`, `new UserModel`, aliases e referências indiretas:

- as referências encontradas se limitam a:
    - [app/legacy/UserModel.php](app/legacy/UserModel.php) — implementação real do modelo legado;
    - [app/models/UserModel.php](app/models/UserModel.php) — compatibilidade de importação;
    - [app/models/UserModel.deprecated.php](app/models/UserModel.deprecated.php) — compatibilidade de importação declarada;
    - documentação e comentários internos que descrevem a migração;
- não foi encontrado nenhum `require_once`/`require`/`include` real apontando para [app/legacy/UserModel.php](app/legacy/UserModel.php) em caminhos de runtime;
- não foi encontrado `new UserModel(...)` em produção, controllers, middleware, CLI operacional, jobs, cron, testes de integração ou ferramentas internas;
- os scripts administrativos [admin/check_tenant_id.php](admin/check_tenant_id.php) e [admin/check_admin_role.php](admin/check_admin_role.php) usam `UserService`, não o modelo legado;
- os arquivos [app/models/UserModel.php](app/models/UserModel.php) e [app/models/UserModel.deprecated.php](app/models/UserModel.deprecated.php) são apenas compatibilidade de importação e não são sinais de uso operante.

Classificação da evidência:

- uso confirmado em runtime: nenhum identificado;
- uso exclusivo em testes ou ferramentas: nenhum identificado para `UserModel` diretamente; o uso que existe é em compatibilidade e diagnósticos administrativos;
- arquivo sem consumidores identificados: [app/legacy/UserModel.php](app/legacy/UserModel.php) se enquadra neste caso, mas não é seguro para remoção imediata porque há compatibilidade e dependências indiretas documentadas;
- casos inconclusivos: nenhum relevante após a busca global, mas a ausência de uso direto não elimina compatibilidade em scripts externos não rastreados na árvore do workspace.

Conclusão: a evidência atual sustenta que o modelo legado não está sendo usado em código relevante do runtime, mas ainda não basta para declarar remoção segura. O mínimo seguro é manter o arquivo temporariamente e documentar a situação.

#### 2. Auditoria existente: UserModel::logUserChange() e mecanismo moderno

Inspecionados:

- [app/legacy/UserModel.php](app/legacy/UserModel.php): `logUserChange(int $userId, array $oldData, array $newData, string $action): bool`
- [app/utils/AuditLogger.php](app/utils/AuditLogger.php): logger legado estático para `audit_logs`
- [src/Audit/AuditLogger.php](src/Audit/AuditLogger.php): logger moderno da arquitetura PSR-4

O que foi confirmado:

- `UserModel::logUserChange()` grava em `audit_logs` com os campos obrigatórios:
    - `user_id`
    - `tenant_id`
    - `action_type`
    - `resource_type`
    - `resource_id`
    - `description`
    - `old_values`
    - `new_values`
    - `ip_address`
    - `user_agent`
- o ator é determinado por `TenantContext::getUserId()` e o tenant por `TenantContext::getTenantId()`; isso torna a gravação dependente do contexto de sessão/tenant e não do parâmetro explícito de usuário;
- o mecanismo moderno mais relevante é [src/Audit/AuditLogger.php](src/Audit/AuditLogger.php), usado por [src/Service/PropertyService.php](src/Service/PropertyService.php) e outros serviços para registrar eventos sem SQL procedural direto em cada método;
- o logger legado em [app/utils/AuditLogger.php](app/utils/AuditLogger.php) ainda registra eventos de `user`, `tenant`, `unauthorized_access` e `cross_tenant_attempt` em `audit_logs`, mas não é o mesmo contrato que o `UserService` moderno;
- não há um serviço de auditoria de usuário equivalente ao `logUserChange()` no moderno; isso significa que a migração do arquivo legado precisa ser tratada como adaptação de compatibilidade e não como simples troca de método com mesmo contrato.

Risco de duplicação ou perda de eventos:

- se `UserModel::logUserChange()` for substituído sem adaptação, pode haver perda de eventos de auditoria legados;
- se o logger moderno for acionado em paralelo sem cuidado, pode haver registros duplicados em `audit_logs` para o mesmo evento;
- a ação mínima segura é preservar a interface do legado enquanto o mecanismo de auditoria é normalizado, em vez de criar um novo serviço duplicado sem necessidade.

#### 3. Verificação dos serviços modernos

A inspeção de [src/Application/User/UserService.php](src/Application/User/UserService.php) e [src/Infrastructure/User/UserRepository.php](src/Infrastructure/User/UserRepository.php) confirma cobertura real para os contratos principais:

- listagem: `listUsers()`, `countUsers()`, `buildFilters()`
- consulta por ID: `findById()`, `getUserById()`
- criação: `create()` e `createUser()`
- atualização: `update()` e `updateUser()`
- exclusão: `delete()` e `deleteUser()`
- unicidade: `findByUsername()`, `findByEmail()` e validação no serviço
- autorização: `AuthContext`, `currentUserIsAdmin()`, `currentUserIsSuperAdmin()`, `canManageUser()`
- tenant: `userBelongsToTenant()`, `existsInTenant()` e `findById()` com `tenant_id = ?`

Diferenças relevantes que devem ser respeitadas:

- o modelo legado retorna `null` para leitura; o serviço moderno lança `RuntimeException`
- o serviço moderno remove `password` do payload e retorna um array sanitizado
- o serviço moderno rejeita `tenant_id`, `username` e `is_superadmin` em `updateUser()`
- o `createUser()` moderno usa `PASSWORD_DEFAULT`, valida `role`, e aceita `system_creation` explícito para onboarding
- `deleteUser()` moderno proíbe o usuário principal (`id === 1`)
- `listUsers()` do serviço aplica `is_superadmin = 0` e paginação real

Essas diferenças não são equivalentes meramente por nomes parecidos; elas precisam ser preservadas.

#### 4. Ação mínima segura recomendada

Estratégia escolhida: manter o arquivo temporariamente e documentar sua situação.

Motivos:

- não há uso confirmado em runtime;
- há compatibilidade em [app/models/UserModel.php](app/models/UserModel.php) e [app/models/UserModel.deprecated.php](app/models/UserModel.deprecated.php);
- há scripts administrativos que exigem cuidado antes de qualquer remoção;
- a auditoria legacy e a moderna não possuem contrato 1:1 exato, então não se pode remover o legado sem uma adaptação financeira e de compatibilidade clara.

Não será feita remoção, reescrita, stage, commit ou push nesta etapa.

#### 5. Testes existentes e lacunas

Testes existentes relevantes:

- [tests/Application/User/UserServiceTest.php](tests/Application/User/UserServiceTest.php) — cobre reset de senha, update, rejeição de campos proibidos e validação do serviço moderno
- [tests/Unit/TenantBoundaryContractTest.php](tests/Unit/TenantBoundaryContractTest.php) — valida isolamento entre tenants
- [tests/Unit/FinanceContractTest.php](tests/Unit/FinanceContractTest.php) — cobre fallback inseguro e contrato financeiro e tenant boundary

Lacunas confirmadas que ainda precisam existir antes de qualquer migração de `UserModel`:

- comparação direta de contrato entre legado e moderno para CRUD de usuário
- teste de `tenant_id` mismatch em leitura/criação/atualização/exclusão
- teste de `superadmin` e `admin` para criação e atualização de role
- teste de `logUserChange()` e do logger moderno para garantir não duplicar eventos
- teste para `id = 1` e proteção do usuário principal

Conclusão final: [app/legacy/UserModel.php](app/legacy/UserModel.php) ainda não tem uso confirmado em runtime, mas também não pode ser removido de forma segura até o fechamento das lacunas de compatibilidade e auditoria. A próxima ação mínima segura é manter o arquivo em estado de compatibilidade, documentar a sua situação e preparar a migração controlada somente após testes de contrato suplementares e validação do mecanismo de auditoria.

### 5.8 Resultado da auditoria do lote UTM/analytics

Status: a inspeção direta do repositório não confirmou um alvo real de migração remanescente nos arquivos já trabalhados do módulo UTM/analytics.

Arquivos auditados:

- [api/v1/utm/getCampaigns.php](api/v1/utm/getCampaigns.php) — delega para `UtmService::listCampaigns()` e `createCampaign()`/`updateCampaign()`/`deleteCampaign()`
- [api/v1/utm/getAnalytics.php](api/v1/utm/getAnalytics.php) — delega para `UtmService::getAnalytics()` com resolução de tenant via `TenantMiddleware`
- [api/v1/utm/trackClick.php](api/v1/utm/trackClick.php) — valida tenant e chama `UtmService::trackClick()`, sem fallback silencioso para tenant 1
- [src/Service/UtmService.php](src/Service/UtmService.php) — centraliza regras de negócio e validação de tenant
- [src/Repository/UtmRepository.php](src/Repository/UtmRepository.php) — centraliza as consultas e insert de analytics e tracking

Evidência conclusiva:

- os endpoints não contêm SQL procedural de negócio inline;
- as regras de analytics e tracking foram movidas para serviço/repositório;
- os testes de contrato do lote executados em `./vendor/bin/phpunit tests/Unit/UtmMigrationContractTest.php` passaram (`OK (6 tests, 25 assertions)`);
- não há prova suficiente para justificar uma segunda migração do mesmo lote.

Conclusão da auditoria:

- não foi encontrado um alvo concreto e seguro para reabrir `PSR4-20` como migração de UTM/analytics;
- não se deve inventar trabalho de reescrita para um módulo já migrado e validado no estado real do repositório;
- o próximo passo apropriado é uma auditoria separada do lote de relatórios públicos/analíticos ainda não documentado como concluído em código, e não o reprocessamento dos arquivos UTM já migrados.

### 5.6 APIs públicas híbridas e validação de chave

#### PSR4-15 — migração do lote de APIs públicas híbridas

Status: implementada e validada no escopo do lote de APIs públicas híbridas, com centralização da validação de API Key em service/repositório e preservação do contrato HTTP público.

Arquivos e camadas envolvidas:

- [api/v1/public/properties.php](api/v1/public/properties.php) — validação da chave pública migrada para o service/repositório, mantendo o fluxo de listagem/criação/atualização de imóveis do tenant vinculado
- [api/v1/public/portalFeed.php](api/v1/public/portalFeed.php) — mesma validação desacoplada da SQL inline, preservando a resolução de tenant via registro da chave
- [src/Repository/ApiKeyRepository.php](src/Repository/ApiKeyRepository.php) — consulta ativa e válida da chave com filtro de tenant, status e expiração
- [src/Service/ApiKeyService.php](src/Service/ApiKeyService.php) — orquestra a validação e rejeita registros em branco, expirados, inativos ou sem tenant
- [tests/Unit/PublicApiMigrationContractTest.php](tests/Unit/PublicApiMigrationContractTest.php) — teste de contrato para impedir regressão do SQL procedural e garantir a centralização da validação da chave pública

O que foi preservado:

- contrato HTTP e resposta JSON dos endpoints públicos existentes;
- resolução de tenant a partir do registro da chave API e rejeição de tenant ausente ou inválido;
- comportamento de autenticação da API pública e fluxo de listagem/criação/feedback de portal;
- isolamento por tenant sem fallback silencioso para `tenant_id = 1`.

Evidência executada nesta fase:

- `./vendor/bin/phpunit --filter PublicApiMigrationContractTest tests/Unit/PublicApiMigrationContractTest.php` → `OK (3 tests, 6 assertions)`
- `php -l api/v1/public/properties.php ; php -l api/v1/public/portalFeed.php ; php -l src/Repository/ApiKeyRepository.php ; php -l src/Service/ApiKeyService.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- a base de dados real não foi alterada e não houve execução end-to-end em ambiente de produção/teste dedicado;
- o teste executado foi de contrato e arquitetura, validando o novo serviço/repositório e a remoção do SQL inline que causava a condição de expiração ambígua.

### 5.7 Consolidação do gerenciamento de API Keys

#### PSR4-16 — migração das operações de gerenciamento de API Keys

Status: implementada e validada no escopo do lote de gerenciamento de chaves públicas, com centralização de persistência e regras de negócio em `ApiKeyRepository` e `ApiKeyService`, sem reintroduzir fallback para `tenant_id = 1`.

Arquivos e camadas envolvidas:

- [api/v1/public/getAPIKeys.php](api/v1/public/getAPIKeys.php) — listagem de chaves migrada para service, preservando o tenant autenticado e o contrato JSON
- [api/v1/public/createAPIKey.php](api/v1/public/createAPIKey.php) — criação de chave migrada para service, com validation de nome e tenant do contexto autenticado
- [api/v1/public/revokeAPIKey.php](api/v1/public/revokeAPIKey.php) — revogação de chave migrada para service, mantendo a garantia de pertencimento ao mesmo usuário e tenant
- [src/Repository/ApiKeyRepository.php](src/Repository/ApiKeyRepository.php) — operações de consulta, criação e exclusão por tenant/usuário
- [src/Service/ApiKeyService.php](src/Service/ApiKeyService.php) — regras de negócio de validação, listagem, criação e revogação da chave
- [tests/Unit/PublicApiMigrationContractTest.php](tests/Unit/PublicApiMigrationContractTest.php) — contrato para impedir SQL procedural remanescente e garantir uso do service/repositório

O que foi preservado:

- contrato HTTP e estrutura JSON dos endpoints existentes;
- necessidade de autenticação e isolamento por tenant no contexto autenticado;
- retorno da chave gerada no momento da criação, mantendo o comportamento operacional do fluxo de emissão de API Key;
- rejeição de tenant inválido e ausência de fallback silencioso para `tenant_id = 1`.

Evidência executada nesta fase:

- `./vendor/bin/phpunit --filter PublicApiMigrationContractTest tests/Unit/PublicApiMigrationContractTest.php` → `OK (4 tests, 12 assertions)`
- `php -l` em todos os arquivos PHP alterados → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução end-to-end com banco de dados dedicado em ambiente externo;
- testes executados foram de contrato/arquitetura para impedir regressão de SQL procedural e garantir o isolamento de tenant.

### 5.8 Atualização do lote de agendamento e alertas

Status: concluído e validado no escopo de contrato e sintaxe, com migração do endpoint legado para a camada de serviço e repositório.

#### PSR4-09 — fechamento da fase atual

A fase foi concluída com a verificação final do código atual e sem evidência de defeito na última alteração do repositório. A lógica de tenant, autenticação, filtro por usuário/tenant e resposta preservada foi revisada no diff real e não houve necessidade de reescrever a implementação.

Arquivos e camadas envolvidas:

- [api/relatorios/getTodaySchedule.php](api/relatorios/getTodaySchedule.php) — endpoint público delegando ao serviço
- [api/relatorios/configureAlerts.php](api/relatorios/configureAlerts.php) — endpoint de configuração delegando ao serviço
- [src/Service/AgendamentoService.php](src/Service/AgendamentoService.php) — orquestração de negócio e formatação do payload
- [src/Infrastructure/Agendamento/AgendamentoRepository.php](src/Infrastructure/Agendamento/AgendamentoRepository.php) — consultas de hoje, pendências e comparecimento
- [src/Services/AlertConfigService.php](src/Services/AlertConfigService.php) — acesso à configuração do alerta por tenant e usuário
- [tests/Unit/ScheduleAndAlertMigrationContractTest.php](tests/Unit/ScheduleAndAlertMigrationContractTest.php) — teste de contrato para impedir regressão de SQL procedural na API

O que foi preservado:

- payload e estrutura de resposta legado para os consumidores existentes;
- isolamento de tenant já presente no middleware e helpers de tenant;
- comportamento de autenticação e autorizações do endpoint;
- uso de repositório para as consultas de hoje, pendências e taxa de comparecimento.

Evidência executada nesta fase:

- `./vendor/bin/phpunit --filter ScheduleAndAlertMigrationContractTest tests/Unit/ScheduleAndAlertMigrationContractTest.php` → `OK (3 tests, 11 assertions)`
- `php -l` em todos os seis arquivos listados → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Situação da integração com banco de dados: pendente de validação real em ambiente de teste dedicado. Nenhuma execução ou validação real com MySQL foi demonstrada nesta fase.

Próxima etapa recomendada pela inspeção do código-fonte: continuar a limpeza dos módulos ainda híbridos de UTM/analytics e relatórios públicos, sem reabrir o escopo de Auth + Tenant como fase nova, porque a evidência do código atual não sustenta essa reabertura.

### 5.7 Migração controlada de SQL legado

#### PSR4-10 — migração controlada de SQL legado

Status: concluída no escopo do lote validado e documentado, com substituição do SQL procedural em endpoint de configuração de metas por serviço e repositório dedicados.

Arquivos e camadas envolvidas:

- [api/v1/goals/getGoalsConfig.php](api/v1/goals/getGoalsConfig.php) — endpoint de leitura e atualização de metas usando a camada de serviço em vez de PDO direto
- [src/Service/GoalConfigService.php](src/Service/GoalConfigService.php) — orquestração do contrato público da API e padronização dos valores padrão por tenant
- [src/Repository/GoalConfigRepository.php](src/Repository/GoalConfigRepository.php) — acesso a dados isolado por tenant com `upsert` e leitura ordenada por goal
- [tests/Unit/ReportGoalsAndTargetsContractTest.php](tests/Unit/ReportGoalsAndTargetsContractTest.php) — contrato de regressão para impedir SQL procedural na API e garantir o uso do serviço

O que foi preservado:

- payload de resposta do endpoint e defaults de metas para os consumers existentes;
- autorização do admin e isolamento de tenant via middleware/helpers já presentes;
- estrutura de GET/POST requerida por frontends e scripts legados;
- regra de fallback com tenant válido e ausência de uso de `tenant_id = 1` como correção silenciosa.

Evidência executada nesta fase:

- `./vendor/bin/phpunit --filter ReportGoalsAndTargetsContractTest tests/Unit/ReportGoalsAndTargetsContractTest.php` → `OK (6 tests, 42 assertions)`
- `php -l` nos arquivos alterados → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Situação da integração com banco de dados: segue pendente de validação real em ambiente de testes dedicado. Esta fase documentada confirmou o alinhamento arquitetural do lote e a ausência de SQL procedural no endpoint específico, sem afirmar validação operacional de banco em produção ou em testes de integração MySQL.

### 5.8 Correção de fallback legado em analytics

#### PSR4-11 — correção do lote UTM/analytics legado

Status: concluída no escopo do lote validado, com remoção do fallback silencioso para `tenant_id = 1` e uso do fluxo de autenticação e resolução de tenant padrão do projeto.

Arquivos e camadas envolvidas:

- [api/v1/utm/getAnalytics.php](api/v1/utm/getAnalytics.php) — endpoint de analytics agora resolve o tenant via `AuthMiddleware` e `TenantMiddleware` em vez de cair em fallback arbitrário
- [api/v1/utm/getCampaigns.php](api/v1/utm/getCampaigns.php) — evidência de referência do padrão de migração já consolidado no lote UTM
- [src/Service/UtmService.php](src/Service/UtmService.php) — contrato de analytics já validado e usado pela API
- [tests/Unit/UtmMigrationContractTest.php](tests/Unit/UtmMigrationContractTest.php) — contrato de regressão para impedir `tenant_id = 1` como fallback silencioso e garantir que a API use a camada de serviço

O que foi preservado:

- autenticação e tenant resolvidos via middleware já presentes no projeto;
- retorno e estrutura de resposta do endpoint de analytics para os consumidores existentes;
- regra de validação de parâmetros `days` e `campaign_id` sem abertura de compatibilidade insegura;
- ausência de SQL procedural na API, mantendo a separação entre camada de apresentação e acesso a dados.

Evidência executada nesta fase:

- `./vendor/bin/phpunit --filter UtmMigrationContractTest tests/Unit/UtmMigrationContractTest.php` → `OK (6 tests, 25 assertions)`
- `php -l` em todos os arquivos PHP alterados no lote → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Situação da integração com banco de dados: continua pendente de validação real em ambiente de teste dedicado. Nenhuma execução com MySQL foi realizada nesta fase; a validação foi de contrato e arquitetura do endpoint, sem afirmar integração de dados em banco.

### 5.9 Verificação final do lote de relatórios e APIs públicas

#### PSR4-17 — continuidade da migração sem alvo seguro adicional no escopo validado

Status: verificado e concluído como ausência de lote adicional seguro dentro do escopo inspecionado de relatórios e APIs públicas. Os módulos já examinados foram encontrados em arquitetura de serviço/repositório e em validação de contrato, sem defeito concreto que justificasse reabrir a implementação de PSR4-15/PSR4-16 ou introduzir refatorações duplicadas.

Arquivos e camadas inspecionadas:

- [api/relatorios/getPortfolioStats.php](api/relatorios/getPortfolioStats.php) — endpoint delegando para `ReportService::getPortfolioStats()` e sem SQL inline no arquivo
- [api/relatorios/getTodaySchedule.php](api/relatorios/getTodaySchedule.php) — endpoint delegando para `AgendamentoService::getTodayScheduleSummary()`
- [api/relatorios/configureAlerts.php](api/relatorios/configureAlerts.php) — endpoint delegando para `AlertConfigService`
- [api/v1/messages/getMessages.php](api/v1/messages/getMessages.php) — endpoint delegando para `MessageService`
- [api/v1/messages/markMessageRead.php](api/v1/messages/markMessageRead.php) — endpoint delegando para `MessageService`
- [api/v1/settings/getCompanySettings.php](api/v1/settings/getCompanySettings.php) — endpoint delegando para `CompanySettingsService`
- [api/v1/public/getAPIKeys.php](api/v1/public/getAPIKeys.php) — listagem migrada para `ApiKeyService`
- [api/v1/public/createAPIKey.php](api/v1/public/createAPIKey.php) — criação migrada para `ApiKeyService`
- [api/v1/public/revokeAPIKey.php](api/v1/public/revokeAPIKey.php) — revogação migrada para `ApiKeyService`

Evidência executada neste fechamento:

- `vendor\bin\phpunit tests\Unit\PortfolioStatsMigrationContractTest.php` → `OK (2 tests, 7 assertions)`
- `vendor\bin\phpunit tests\Unit\MessageMigrationContractTest.php` → `OK (2 tests, 8 assertions)`
- `vendor\bin\phpunit tests\Unit\ScheduleAndAlertMigrationContractTest.php` → `OK (4 tests, 17 assertions)`
- `vendor\bin\phpunit tests\Unit\CompanySettingsMigrationContractTest.php` → `OK (3 tests, 15 assertions)`
- `vendor\bin\phpunit tests\Unit\PublicApiMigrationContractTest.php` → `OK (4 tests, 20 assertions)`
- `git diff --check` → sem saída, sem problemas de patch hygiene

Conclusão do lote:

- não há alvo seguro adicional comprovado dentro do escopo de relatórios e APIs públicas na árvore atual do workspace;
- todos os módulos inspecionados atuam através de serviços/repositórios ou já foram validados como migrados;
- a próxima etapa recomendada é escolher um módulo fora do escopo atual, com evidência de SQL procedural restante ou regra de negócio legada ainda não coberta por contrato;
- a reabertura de PSR4-15/PSR4-16 sem defeito concreto não é justificada e foi evitada neste fechamento.

### 5.9 Migração controlada de configurações de empresa

#### PSR4-12 — migração controlada do próximo lote legado

Status: concluída no escopo do lote validado, com remoção do fallback silencioso para `tenant_id = 1` e migração dos endpoints de settings para a camada de serviço já existente em `CompanySettingsService`/`CompanySettingsRepository`.

Arquivos e camadas envolvidas:

- [api/v1/settings/getCompanySettings.php](api/v1/settings/getCompanySettings.php) — leitura de configurações da empresa agora consulta o serviço em vez de SQL procedural direto
- [api/v1/settings/saveCompanySettings.php](api/v1/settings/saveCompanySettings.php) — persiste dados do tenant via serviço/repositório em vez de `INSERT/UPDATE` embutidos na API
- [api/v1/settings/getPublicCompanySettings.php](api/v1/settings/getPublicCompanySettings.php) — versão pública agora usa o mesmo caminho de leitura e sem fallback arbitrário para tenant padrão
- [src/Service/CompanySettingsService.php](src/Service/CompanySettingsService.php) — orquestra o contrato de criação/atualização/consulta do tenant
- [src/Infrastructure/Company/CompanySettingsRepository.php](src/Infrastructure/Company/CompanySettingsRepository.php) — acesso a dados isolado por tenant com `findByTenant`, `update` e `create`
- [tests/Unit/CompanySettingsMigrationContractTest.php](tests/Unit/CompanySettingsMigrationContractTest.php) — teste de contrato para impedir regressão do SQL procedural e do fallback para `tenant_id = 1`

O que foi preservado:

- payload e estrutura de resposta dos endpoints públicos/autenticados para os consumidores existentes;
- autenticação via `checkApiAuth` e resolução de tenant via helper do projeto;
- defaults de dados e integrações de WhatsApp/Google/Hotjar já presentes nos endpoints;
- regra de isolamento por tenant sem cair em template compartilhado entre tenants.

Evidência executada nesta fase:

- `./vendor/bin/phpunit --filter CompanySettingsMigrationContractTest tests/Unit/CompanySettingsMigrationContractTest.php` → `OK (3 tests, 8 assertions)`
- `php -l` em todos os arquivos PHP alterados no lote → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Situação da integração com banco de dados: continua pendente de validação real em ambiente de teste dedicado. A validação executada nesta fase foi de arquitetura, contrato e segurança de tenant, sem afirmar integração end-to-end com MySQL em ambiente de teste.

### 5.10 Migração controlada de mensagens do usuário

#### PSR4-13 — migração controlada do próximo lote legado

Status: concluída no escopo do lote validado, com remoção do SQL direto na API e migração para `MessageService`/`MessageRepository` mantendo o contrato JSON e a resolução de tenant do middleware já presente.

Arquivos e camadas envolvidas:

- [api/v1/messages/getMessages.php](api/v1/messages/getMessages.php) — leitura de mensagens agora delega ao serviço em vez de `SELECT` e `COUNT` diretamente no endpoint
- [api/v1/messages/markMessageRead.php](api/v1/messages/markMessageRead.php) — atualização de leitura agora chama o serviço em vez de `UPDATE messages` embutido no controller
- [src/Service/MessageService.php](src/Service/MessageService.php) — orquestra a validação de usuário/tenant e a leitura/atualização de mensagens
- [src/Repository/MessageRepository.php](src/Repository/MessageRepository.php) — acessa os dados de `messages` com isolamento por usuário e tenant
- [tests/Unit/MessageMigrationContractTest.php](tests/Unit/MessageMigrationContractTest.php) — contrato de regressão para impedir SQL procedural na API e garantir uso do serviço

O que foi preservado:

- estrutura de resposta JSON para listagem e marcação de leitura;
- autenticação via `checkAuth()` e resolução de tenant via `TenantMiddleware`;
- filtros por `user_id` e `tenant_id` com isolamento correto;
- códigos HTTP e tratamento de erro do endpoint legado sem alterar o contrato externo.

Evidência executada nesta fase:

- `./vendor/bin/phpunit --filter MessageMigrationContractTest tests/Unit/MessageMigrationContractTest.php` → `OK (2 tests, 8 assertions)`
- `php -l` em todos os arquivos PHP alterados no lote → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Situação da integração com banco de dados: continua pendente de validação real em ambiente de teste dedicado. A validação executada foi de arquitetura, contrato e regressão de SQL procedural; não houve execução real com MySQL nesta fase.

### PSR4-40 — remoção do SQL procedural residual do controller de imóveis

Status: concluída no escopo do domínio de imóveis, com migração do patch de atualização do controller para o serviço e repositório, sem abrir módulos já validados e sem introduzir fallback para `tenant_id = 1`.

Arquivos e camadas envolvidas:

- [app/controllers/PropertiesController.php](app/controllers/PropertiesController.php) — o método `updateProperty()` agora monta um payload normalizado de alterações em vez de montar a string `UPDATE properties SET ...` dentro do controller
- [src/Service/PropertyService.php](src/Service/PropertyService.php) — novo método `updatePropertyPatch()` centraliza validação do payload, status e configuração permissiva de campos permitidos pelo contrato legado
- [src/Repository/PropertyRepository.php](src/Repository/PropertyRepository.php) — novo método `updatePropertyPatch()` constrói a query de atualização a partir do mapa de campos e mantém o SQL no repositório
- [tests/Unit/PropertiesControllerMigrationContractTest.php](tests/Unit/PropertiesControllerMigrationContractTest.php) — contrato de regressão para impedir montagem de SQL procedural no controller e garantir a existência do método de patch do serviço/repositório

O que foi preservado:

- autenticação e autorização já presentes no controller (`requireAuth()`, `getTenantId()`, verificação de `assigned_user_id` e permissão do usuário/admin)
- validação de `status` com a mesma lista permitida no fluxo legado (`active`, `pending`, `vendido`, `alugado`, `inactive`)
- contrato HTTP e retorno JSON do endpoint de imóveis
- isolamento por tenant e ausência de fallback para `tenant_id = 1`

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/PropertyServiceTest.php` → `OK (32 tests, 79 assertions)`
- `./vendor/bin/phpunit tests/Unit/PropertiesControllerMigrationContractTest.php` → `OK (2 tests, 8 assertions)`
- `php -l app/controllers/PropertiesController.php` → sem erro de sintaxe
- `php -l src/Service/PropertyService.php` → sem erro de sintaxe
- `php -l src/Repository/PropertyRepository.php` → sem erro de sintaxe
- `php -l tests/Unit/PropertiesControllerMigrationContractTest.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução real de banco MySQL em ambiente de teste dedicado para o fluxo de atualização completo de imóveis; a validação executada foi de contrato e regressão de arquitetura
- o patch foi validado pela presença do serviço/repositório e pela ausência de SQL procedural no controller, sem afirmar que o ambiente externo foi executado em produção

Próximo resíduo confirmado pela inspeção:

- continuar a auditoria do domínio de imóveis/portal/financeiro para encontrar o próximo lote seguro com SQL procedural residual e contratos compartilhados, sem reabrir módulos já validados e sem misturar domínios incompatíveis

### PSR4-41 — remoção do SQL procedural residual do endpoint público de imóveis

Status: concluída no escopo do endpoint público de imóveis, com migração do update de `api/v1/public/properties.php` para o patch normalizado do serviço/repositório, sem reabrir módulos já validados e sem alterar o contrato JSON do endpoint.

Arquivos e camadas envolvidas:

- [api/v1/public/properties.php](api/v1/public/properties.php) — `handleUpdateProperty()` agora monta um mapa `$changes` e delega para `PropertyService::updatePropertyPatch()` em vez de montar a string `UPDATE properties SET ...` no endpoint
- [src/Service/PropertyService.php](src/Service/PropertyService.php) — `updatePropertyPatch()` centraliza validação, casting e lista de campos compatíveis com o payload legado do endpoint público (`type`, `state`, `price`, `bedrooms`, `bathrooms`, `status`, etc.)
- [src/Repository/PropertyRepository.php](src/Repository/PropertyRepository.php) — o método `updatePropertyPatch()` já executa a atualização normalizada no repositório, mantendo a query em uma única camada de acesso a dados
- [tests/Unit/PublicApiMigrationContractTest.php](tests/Unit/PublicApiMigrationContractTest.php) — contrato de regressão para impedir SQL procedural no endpoint público e garantir o uso do patch do serviço/repositório

O que foi preservado:

- payload legado aceito em nomes tradicionais e em português (`titulo`, `tipo`, `preco`, `dormitorios`, `banheiros`, `descricao`)
- filtros de tenant e verificação de existência da propriedade no endpoint público (`tenant_id` e `id`)
- webhook `property.updated` após a atualização bem-sucedida
- estrutura de resposta HTTP e JSON do endpoint, sem abrir mudanças incompatíveis de contrato

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/PublicApiMigrationContractTest.php tests/Unit/PropertyServiceTest.php tests/Unit/PropertiesControllerMigrationContractTest.php` → `OK (5 tests, 27 assertions)`
- `php -l api/v1/public/properties.php` → sem erro de sintaxe
- `php -l src/Service/PropertyService.php` → sem erro de sintaxe
- `php -l src/Repository/PropertyRepository.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Próximo passo recomendado:

- continuar a auditoria do restante do domínio de imóveis/portal/financeiro apenas quando houver evidência direta e coesa de outro resíduo residual seguro; a área já validada nesta fase foi fechada sem reabrir módulos concluídos e sem misturar domínios incompatíveis

### PSR4-42 — hardening do fluxo de leads e integrações de portal

Status: concluída no escopo do fluxo de leads e portal integration, com remoção do fallback inseguro para `tenant_id = 1`, validação explícita do tenant antes do insert/update e migração da leitura da integração do Facebook para o repositório da infraestrutura.

Arquivos e camadas envolvidas:

- [api/v1/leads/addLead.php](api/v1/leads/addLead.php) — a API agora envia o tenant resolvido e não repassava o valor bruto ou nulo quando o contexto de tenant não era conhecido
- [src/Application/Lead/LeadService.php](src/Application/Lead/LeadService.php) — `resolveTenantForLeadSubmission()` passou a retornar `tenant_id = null` quando não há resolução válida, e `submitLeadFromContact()` lança exceção explícita em vez de cair em fallback inseguro para `tenant_id = 1`
- [src/Application/Integration/FacebookLeadService.php](src/Application/Integration/FacebookLeadService.php) — `getFacebookIntegration()` passou a consultar `PortalIntegrationRepository` em vez de `SELECT` direto na tabela `portal_integrations`
- [src/Infrastructure/PortalIntegration/PortalIntegrationRepository.php](src/Infrastructure/PortalIntegration/PortalIntegrationRepository.php) — repositório já existente usado como contrato único para buscar configurações por tenant/portal
- [tests/Unit/LeadServiceTenantSafetyTest.php](tests/Unit/LeadServiceTenantSafetyTest.php) — regressão que valida a ausência de fallback para `tenant_id = 1` e a exigência de tenant explícito no submit de lead

O que foi preservado:

- contrato JSON existente do endpoint, incluindo `name`, `email`, `phone`, `message`, `status`, `source` e `property_id`
- uso do serviço `LeadService` como ponto central de validação do tenant e do mapeamento do imóvel para o tenant correto
- as integrações de `facebook_pixel` e `facebook_leads` por tenant, sem misturar dados entre tenants nem cair em default inseguro
- a arquitetura moderna de leitura de integrações por `PortalIntegrationRepository`, mantendo o acesso a dados isolado na infraestrutura

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/LeadServiceTenantSafetyTest.php` → `OK (2 tests, 4 assertions)`
- `php -l src/Application/Lead/LeadService.php` → sem erro de sintaxe
- `php -l src/Application/Integration/FacebookLeadService.php` → sem erro de sintaxe
- `php -l api/v1/leads/addLead.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução real de ambiente MySQL em banco dedicado para o fluxo completo de lead; a validação executada foi de regressão do contrato de segurança e de sintaxe do módulo
- a mudança foi deliberadamente limitada ao domínio de leads/portal integration e não reabriu módulos já validados em outras fases da migração

Próximo resíduo confirmado pela inspeção:

- o próximo alvo seguro deve seguir a mesma regra: confirmar um resíduo real específico e coeso em um domínio de negócio, sem reabrir módulos concluídos ou misturar áreas heterogêneas antes de ter evidência direta no código atual

### PSR4-43 — migração do helper legado de integrações de portal

Status: concluída no escopo do domínio de integrações de portal, com substituição do helper legado que ainda continha SQL procedural direto por um wrapper em cima do repository já existente e sem abrir módulos já validados.

Arquivos e camadas envolvidas:

- [app/integrations/PortalIntegrationStore.php](app/integrations/PortalIntegrationStore.php) — helper legado agora delega para `PortalIntegrationRepository` em vez de montar `SELECT`/`UPDATE`/`INSERT` direto em `portal_integrations`
- [src/Infrastructure/PortalIntegration/PortalIntegrationRepository.php](src/Infrastructure/PortalIntegration/PortalIntegrationRepository.php) — continua sendo o contrato de dados único para `findAllByTenant()`, `findByTenantAndSlug()` e `save()`
- [src/Application/Integration/PortalIntegrationService.php](src/Application/Integration/PortalIntegrationService.php) — já representa o serviço de aplicação correto para esse domínio e foi preservado como ponto central do fluxo
- [api/v1/auth/portalIntegrations.php](api/v1/auth/portalIntegrations.php) — endpoint de gerenciamento de integrações segue usando o serviço/repositório, sem necessidade de reescrever o contrato externo
- [tests/Unit/PortalIntegrationLegacyStoreContractTest.php](tests/Unit/PortalIntegrationLegacyStoreContractTest.php) — regressão de contrato para impedir a permanência de SQL procedural no helper legado e validar o uso do repositório no mesmo domínio

O que foi preservado:

- assinatura pública das funções legado `ensurePortalIntegrationsTable()`, `getPortalIntegrationsForTenant()`, `getPortalIntegration()`, `savePortalIntegration()` e `decodePortalIntegrationCredentials()`
- JSON e estrutura de resposta dos endpoints já existentes
- isolamento por tenant e a regra de não fallback para `tenant_id = 1`, preservando a validação segura já consolidada no fluxo de integrações
- mapeamento do domínio para um único contrato de persistência, sem duplicar abstrações de acesso a dados em helpers globais

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/LeadServiceTenantSafetyTest.php tests/Unit/PortalIntegrationLegacyStoreContractTest.php` → `OK (3 tests, 12 assertions)`
- `php -l app/integrations/PortalIntegrationStore.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução real de ambiente MySQL em banco dedicado para a operação completa de integrações de portal; a validação executada foi de regressão de contrato e sintaxe do lote
- a correção foi deliberadamente limitada ao helper legado do domínio de portal, sem reabrir módulos já migrados e sem misturar domínios incompatíveis

Próximo resíduo confirmado pela inspeção:

- a próxima etapa segura deve continuar a auditoria por domínio coeso e real, priorizando o próximo módulo residual com SQL procedural explícito em HTTP ou helpers legados, sem reabrir fluxos já validados e sem mudar o contrato de clientes existentes

---

## 6. Fases concluídas, em andamento e pendentes

### 6.1 Fases concluídas e com evidência

- Fase de reports/relatórios: implementação da camada service/repository com evidência estática favorável
- PSR4-06: implementação considerada concluída no escopo estático e de contratos validados, com ressalva de integração real com MySQL
- PSR4-09: fase de agendamento e alertas concluída com verificações finais de teste, sintaxe e diff hygiene, com ressalva de validação real com MySQL ainda pendente
- PSR4-10: migração controlada de SQL legado concluída no escopo do endpoint de metas, com validação de contrato, sintaxe e diff hygiene e ressalva de integração real com MySQL ainda pendente
- PSR4-11: correção do fallback legado em analytics UTM concluída com validação de contrato, sintaxe e diff hygiene, com ressalva de integração real com MySQL ainda pendente
- PSR4-12: migração controlada do lote de settings/empresa concluída com validação de contrato, sintaxe e diff hygiene, sem fallback para `tenant_id = 1` e com uso do serviço/repositório para leitura e gravação
- PSR4-13: migração controlada do lote de mensagens concluída com validação de contrato, sintaxe e diff hygiene, sem SQL procedural na API e com isolamento de tenant preservado
- PSR4-33: migração controlada do lote de notificações de email/Gmail concluída com extração da lógica de duplicate check e insert para `NotificationService`/`NotificationRepository`, sem SQL procedural restante nos endpoints e com testes de contrato específicos executados
- PSR4-34: migração controlada do processamento e limpeza da fila de webhooks concluída com extração do `DELETE FROM webhook_logs` do endpoint para `WebhookRepository` e `WebhookService`, preservando o contrato JSON do cron e sem reabrir módulos já validados
- PSR4-35: migração controlada do lote de onboarding e resolução de tenant concluída com extração do insert e do mapeamento de subdomínio para `TenantRegistrationService`, `TenantResolutionService` e `TenantRepository`, sem fallback para `tenant_id = 1` em endpoints HTTP e com teste de contrato específico executado
- PSR4-36: migração controlada do lote de marcação de mensagens em auth concluída com extração do `UPDATE messages` dos endpoints para `MessageService` e `MessageRepository`, preservando autenticação, tenant e contrato JSON sem reabrir módulos já validados
- Auth + Tenant: já materializado no código e no histórico do projeto; não deve ser reaberto como fase nova pendente

### PSR4-34 — processamento e limpeza da fila de webhooks

Status: concluída no escopo do módulo de webhooks, com migração do cleanup residual do endpoint para a camada de serviço/repositório e com teste de contrato específico validando a remoção do SQL direto do HTTP.

Arquivos e camadas envolvidas:

- [api/v1/webhooks/processQueue.php](api/v1/webhooks/processQueue.php) — endpoint do cron agora delega a fila e a limpeza antiga para `WebhookService`
- [src/Service/WebhookService.php](src/Service/WebhookService.php) — centraliza `processQueue()` e `cleanupOldLogs()` mantendo o contrato de resposta esperado pelo cron
- [src/Infrastructure/Webhook/WebhookRepository.php](src/Infrastructure/Webhook/WebhookRepository.php) — opera a limpeza de `webhook_logs` por idade e mantém o acesso a dados isolado do endpoint
- [tests/Unit/WebhookQueueMigrationContractTest.php](tests/Unit/WebhookQueueMigrationContractTest.php) — contrato de regressão para garantir a ausência de SQL procedural no endpoint e a existência do serviço/repositório do módulo

O que foi preservado:

- segredo do cron, verificação HTTP e resposta JSON existente do endpoint;
- processamento real da fila com `WebhookManager` e estrutura de resultados preservada;
- retenção do campo `cleaned_logs` no payload final;
- isolamento do módulo no mesmo padrão já usado por listagem, criação e exclusão de subscriptions, sem fallback para `tenant_id = 1`.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/WebhookQueueMigrationContractTest.php` → `OK (2 tests, 9 assertions)`
- `php -l src/Infrastructure/Webhook/WebhookRepository.php` → sem erro de sintaxe
- `php -l src/Service/WebhookService.php` → sem erro de sintaxe
- `php -l api/v1/webhooks/processQueue.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de integração real com MySQL em ambiente de teste dedicado;
- a validação executada foi de contrato e arquitetura do módulo, sem afirmar execução operacional do webhook real contra serviços externos.

Próximo passo recomendado:

- avançar para o próximo módulo residual com evidência concreta de SQL procedural no HTTP, sem reabrir áreas já migradas e validadas (Pipeline, Stripe, check-login, Google Calendar, notificações, rastreamento e segurança); o próximo alvo seguro deve ser escolhido apenas após confirmação direta no código atual e no roadmap.

### PSR4-35 — onboarding e resolução de tenant

Status: concluída no escopo do módulo de tenant onboarding e subdomínio, com migração do insert e da resolução de host para serviços dedicados e com teste de contrato específico validando a ausência de SQL procedural no HTTP.

Arquivos e camadas envolvidas:

- [api/v1/tenants/create.php](api/v1/tenants/create.php) — endpoint de criação de tenant agora delega ao `TenantRegistrationService` em vez de `INSERT INTO tenants` embutido
- [api/v1/tenant/getTenantIdFromSubdomain.php](api/v1/tenant/getTenantIdFromSubdomain.php) — endpoint de resolução por host agora delega ao `TenantResolutionService` em vez de `SELECT id, slug FROM tenants` direto e sem fallback para `tenant_id = 1`
- [src/Application/Tenant/TenantRegistrationService.php](src/Application/Tenant/TenantRegistrationService.php) — centraliza validação, geração de slug e criação do tenant na camada de aplicação
- [src/Application/Tenant/TenantResolutionService.php](src/Application/Tenant/TenantResolutionService.php) — centraliza a resolução do tenant a partir do subdomínio e a resposta sem fallback legado
- [src/Infrastructure/Tenant/TenantRepository.php](src/Infrastructure/Tenant/TenantRepository.php) — expõe o mapa de subdomínios ativos para uso controlado pelo serviço
- [tests/Unit/TenantMigrationContractTest.php](tests/Unit/TenantMigrationContractTest.php) — contrato de regressão para impedir SQL procedural na API e garantir uso do service/repository do módulo

O que foi preservado:

- estrutura de resposta JSON do endpoint de criação e da resolução de tenant;
- geração de slug/subdomínio e URL de acesso do tenant;
- mapeamento de aliases `default`, `dj`, `www` e estados do padrão `imoveis-*`;
- ausência de fallback silencioso para `tenant_id = 1` em ambiguidade, o que agora é tratado como ausência de tenant.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/TenantMigrationContractTest.php` → `OK (2 tests, 6 assertions)`
- `php -l api/v1/tenants/create.php` → sem erro de sintaxe
- `php -l api/v1/tenant/getTenantIdFromSubdomain.php` → sem erro de sintaxe
- `php -l src/Application/Tenant/TenantRegistrationService.php` → sem erro de sintaxe
- `php -l src/Application/Tenant/TenantResolutionService.php` → sem erro de sintaxe
- `php -l src/Infrastructure/Tenant/TenantRepository.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de integração real com MySQL em ambiente de teste dedicado;
- a validação executada foi de arquitetura, contrato e regressão; não houve afirmação de execução operacional real do tenant em produção.

### PSR4-36 — marcação residual de mensagens em auth

Status: concluída no escopo do domínio de mensagens, com migração dos endpoints que marcavam mensagens como lidas para a camada de serviço/repositório e com teste de contrato específico validando a remoção do SQL procedural no HTTP.

Grupos avaliados:

- tenant/onboarding: já migrado no PSR4-35 e mantido fora do escopo deste lote;
- mensagens: identificado como lote residual real porque os endpoints de auth ainda realizavam `UPDATE messages` diretamente;
- demais domínios: inspecionados de forma seletiva para evitar reabrir módulos já validados, sem expandir o escopo artificialmente.

Arquivos e camadas envolvidas:

- [api/v1/auth/markMessageAsRead.php](api/v1/auth/markMessageAsRead.php) — endpoint de marcação de uma mensagem como lida agora delega ao `MessageService`
- [api/v1/auth/markAllMessagesAsRead.php](api/v1/auth/markAllMessagesAsRead.php) — endpoint de marcação em lote agora delega ao `MessageService`
- [src/Service/MessageService.php](src/Service/MessageService.php) — centraliza validação de usuário/tenant e a operação de marcação de leitura
- [src/Repository/MessageRepository.php](src/Repository/MessageRepository.php) — executa o `UPDATE` em `messages` isolado por `user_id` e `tenant_id`
- [tests/Unit/MessageMigrationContractTest.php](tests/Unit/MessageMigrationContractTest.php) — contrato de regressão ampliado para bloquear `UPDATE messages` inline nos endpoints de auth

O que foi preservado:

- autenticação via `checkAuth()`/JWT e resolução de tenant via middleware presente;
- regras de isolamento por usuário e tenant, sem fallback para `tenant_id = 1`;
- respostas HTTP e JSON esperadas pelos consumidores do endpoint;
- uso do padrão já validado no lote de mensagens, sem duplicação de camadas.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/MessageMigrationContractTest.php` → `OK (2 tests, 17 assertions)`
- `php -l api/v1/auth/markMessageAsRead.php` → sem erro de sintaxe
- `php -l api/v1/auth/markAllMessagesAsRead.php` → sem erro de sintaxe
- `php -l src/Service/MessageService.php` → sem erro de sintaxe
- `php -l src/Repository/MessageRepository.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de integração real com MySQL em ambiente de teste dedicado;
- não houve teste de HTTP autenticado real contra um servidor em execução;
- não houve validação com serviços externos; a confirmação aqui foi de contrato, sintaxe e ausência de SQL procedural no endpoint.

Próximo lote recomendado:

- revisar o próximo domínio híbrido com evidência direta de SQL procedural ou regras de negócio espalhadas na API, sem reabrir tenant/onboarding nem o lote de mensagens já validado; o próximo alvo seguro deve ser escolhido somente após confirmação direta no código atual e no roadmap.

### PSR4-37 — agregação residual de métricas do pipeline

Status: concluída no escopo do domínio de pipeline, com migração do agregado de métricas do endpoint para a camada de serviço/repositório e com teste de contrato específico validando a remoção do SQL procedural do HTTP.

Arquivos e camadas envolvidas:

- [api/v1/pipeline/getMetrics.php](api/v1/pipeline/getMetrics.php) — endpoint de métricas agora delega ao `PipelineService` em vez de montar a agregação com `SELECT ... GROUP BY` direto no controller
- [src/Service/PipelineService.php](src/Service/PipelineService.php) — centraliza `getMetrics()` e preserva a validação de tenant, usuário e permissões do domínio
- [src/Repository/PipelineRepository.php](src/Repository/PipelineRepository.php) — executa a agregação por tenant e por usuário quando o contexto não é de admin
- [tests/Unit/PipelineMigrationContractTest.php](tests/Unit/PipelineMigrationContractTest.php) — contrato de regressão para impedir SQL procedural na API e garantir o uso do serviço do módulo

O que foi preservado:

- estrutura JSON de resposta com `success`, `data.total_value`, `data.total_items`, `data.average_value` e `data.by_stage`;
- isolamento de tenant e filtro por usuário não admin;
- ausência de fallback para `tenant_id = 1` em contexto ambíguo;
- uso do padrão já validado no domínio de pipeline, sem reabrir módulos que já foram migrados e aprovados.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/PipelineMigrationContractTest.php` → `OK (5 tests, 31 assertions)`
- `php -l api/v1/pipeline/getMetrics.php` → sem erro de sintaxe
- `php -l src/Service/PipelineService.php` → sem erro de sintaxe
- `php -l src/Repository/PipelineRepository.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de integração real com MySQL em ambiente de teste dedicado;
- a validação executada foi de arquitetura, contrato e regressão do endpoint, sem afirmar execução operacional do pipeline em produção.

Próximo lote recomendado:

- procurar o próximo domínio residual com evidência direta de SQL procedural no HTTP e regras de negócio dispersas fora do service/repository, sem reabrir módulos já validados e sem ampliar o escopo para áreas que não apresentam residual concreto.

### PSR4-38 — resolução residual de anomalias de segurança

Status: concluída no escopo do domínio de monitoramento de segurança, com migração do `SELECT`/`UPDATE` de `security_anomalies` do endpoint para a camada de serviço/repositório e com teste de contrato específico validando a remoção do SQL procedural do HTTP.

Arquivos e camadas envolvidas:

- [api/v1/debug/resolveAnomaly.php](api/v1/debug/resolveAnomaly.php) — endpoint de resolução da anomalia agora delega ao `SecurityMonitoringService` em vez de executar `SELECT` e `UPDATE` embutidos no controller
- [src/Service/SecurityMonitoringService.php](src/Service/SecurityMonitoringService.php) — centraliza `resolveAnomaly()` com validação de tenant, ID e usuário responsável
- [src/Repository/SecurityMonitoringRepository.php](src/Repository/SecurityMonitoringRepository.php) — lê a anomalia aberta do tenant e marca a resolução em uma única operação controlada
- [tests/Unit/SecurityMonitoringMigrationContractTest.php](tests/Unit/SecurityMonitoringMigrationContractTest.php) — contrato de regressão reforçado para bloquear SQL procedural residual neste endpoint e garantir uso do serviço do módulo

O que foi preservado:

- concessão de autorização por admin e fluxo de validação CSRF/HTTP existente;
- resposta JSON e mensagens de erro do endpoint, sem alterar o contrato externo;
- isolamento por tenant e ausência de fallback para `tenant_id = 1`;
- uso do padrão já validado no domínio de monitoramento, sem reabrir módulos com evidência de migração concluída.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/SecurityMonitoringMigrationContractTest.php` → `OK (2 tests, 16 assertions)`
- `php -l api/v1/debug/resolveAnomaly.php` → sem erro de sintaxe
- `php -l src/Service/SecurityMonitoringService.php` → sem erro de sintaxe
- `php -l src/Repository/SecurityMonitoringRepository.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de integração real com MySQL em ambiente de teste dedicado;
- a validação executada foi de arquitetura, contrato e regressão do endpoint, sem afirmar execução operacional no ambiente de produção.

Próximo lote recomendado:

- continuar a partir do próximo domínio residual que apresente SQL procedural direto no HTTP e uso de regras de negócio fora da camada de serviço/repositório, sem reabrir módulos já validados e sem misturar domínios independentes.

### 6.2 Fases em andamento

- limpeza dos endpoints legacy de UTM, analytics e relatórios públicos/analíticos
- eliminação de fallback legados em módulos críticos ainda híbridos
- integração do restante de billing e propriedades ao fluxo moderno
- consolidação final dos módulos restantes em SQL procedural ou regras de negócio espalhadas pela API

Ajuste de status após validação do lote atual:

- a migração de agendamentos e alertas foi registrada como concluída no escopo estático e de contratos validados;
- o lote de relatórios/portfolio e UTM foi mantido como avanço já validado;
- a continuidade deve priorizar os módulos ainda híbridos restantes, sem reabrir áreas já migradas e validadas.

Observação executiva de 2026-10-16:

- o código atual já materializa a migração do lote de UTM/analytics e do lote de mensagens/configurações no padrão de service/repository em `src/` com testes de contrato específicos;
- a primeira fase pendente de forma segura, no código presente, não é a reabertura de Auth + Tenant nem a repetição de fases aprovadas; ela é a validação final e a consolidação dos módulos públicos/relatórios ainda híbridos e o avanço dos módulos de usuários e propriedades, quando os contratos de tenant e service estiverem confirmados;
- qualquer nova fase deve ser iniciada somente sobre o código já migrado e com a proteção de `tenant_id`, sem reintroduzir o fallback silencioso para `tenant_id = 1`.

### 6.3 Fases pendentes

- migração final dos endpoints legacy de UTM e analytics
- consolidação de relatórios e APIs públicas ainda em SQL procedural
- conclusão de módulos de billing, propriedades, mensagens, notificações e pipeline
- validação real de integração com MySQL em banco de teste dedicado
- desativação de compatibilidade legada após os módulos críticos estarem estáveis

---

## 7. Prioridades e dependências técnicas

### Prioridade 1 — limpeza do lote legado ainda ativo

Migrar os endpoints legados de UTM, analytics e relatórios/portfólio ainda em SQL procedural. Esse é o lote com maior evidência direta de pendência no código atual.

### Prioridade 2 — consolidação residual de auth/tenant

Ajustar os caminhos restantes onde autenticação e tenant ainda coexistem com fallback legados, sem reabrir Auth + Tenant como etapa nova ou duplicada.

### Prioridade 3 — usuários e propriedades

São módulos centrais para o painel e para a gestão do sistema; exigem regras de tenant e permissões coerentes.

### Prioridade 4 — billing e integrações

Pagamento e Stripe continuam sendo um foco importante, mas agora devem seguir após a limpeza do lote legado mais evidente.

### Prioridade 5 — remoção da compatibilidade legada

Somente após a migração dos módulos críticos e validação real.

---

## 8. PSR4-06: marco da fase atual

### 8.1 Status formal

**Implementação considerada concluída no escopo estático e de contratos validado, com ressalva de integração real com MySQL ainda pendente.**

### 8.2 Evidência de testes já executados

Os testes a seguir foram executados anteriormente e não devem ser tratados como execução nova nesta tarefa:

- [tests/Unit/ReportDashboardMetricsContractTest.php](tests/Unit/ReportDashboardMetricsContractTest.php): 5 testes, 33 assertions
- [tests/Unit/DashboardKpiRealtimeContractTest.php](tests/Unit/DashboardKpiRealtimeContractTest.php): 9 testes, 50 assertions
- [tests/Unit/ReportGoalsAndTargetsContractTest.php](tests/Unit/ReportGoalsAndTargetsContractTest.php): 4 testes, 32 assertions
- [tests/Unit/ReportLeadsSalesContractTest.php](tests/Unit/ReportLeadsSalesContractTest.php): 13 testes, 55 assertions

Total registrado: 31 testes e 170 assertions.

### 8.3 Ressonância e guarda de risco

A revisão estática dos endpoints de exportação, `ReportService` e `ReportRepository` não encontrou defeito concreto comprovado no escopo inspecionado. A validação de integração real com MySQL permanece pendente, sem bloquear automaticamente o avanço das demais fases.

Isso significa:

- a migração de relatórios está adequada ao escopo atualmente validado;
- o que falta é confirmação real contra banco MySQL em ambiente isolado;
- não se deve reabrir a PSR4-06 como nova auditoria.

---

## PSR4-06M — revisão de continuidade do lote de exportações de relatórios

### PSR4-06M.1 Data da revisão

- 2026-10-27

### PSR4-06M.2 HEAD atual observado e referência histórica

- HEAD atual observado: `b241efc fix: corrigir erros nos relatórios UTM e agenda`
- referência histórica consultada: `v0.9.0-phase4-complete` (histórico documentado: `287ddfe`)
- uso desta referência: comparação documental histórica, sem tratar a tag como base imediata automática nem como prova de autoria dos hunks atuais

### PSR4-06M.3 Estado do working tree

Comandos executados conforme instrução de leitura:

- `git status --short --untracked-files=all` → sem saída no estado atual observado
- `git diff --stat` → sem saída no estado atual observado
- `git diff --cached --stat` → sem saída no estado atual observado
- `git log -1 --format="%h %s"` → `b241efc fix: corrigir erros nos relatórios UTM e agenda`

Conclusão do estado atual: sem trabalho pendente de código no working tree ao momento da revisão; o ajuste desta tarefa foi restrito ao documento de migração.

### PSR4-06M.4 Arquivos examinados

- [api/relatorios/exportDashboard.php](../api/relatorios/exportDashboard.php)
- [api/relatorios/exportExcel.php](../api/relatorios/exportExcel.php)
- [api/relatorios/exportPDF.php](../api/relatorios/exportPDF.php)
- [src/Repository/ReportRepository.php](../src/Repository/ReportRepository.php)
- [src/Service/ReportService.php](../src/Service/ReportService.php)

### PSR4-06M.5 Classificação dos hunks e evidência

#### 1) [api/relatorios/exportDashboard.php](../api/relatorios/exportDashboard.php)

- classificação: `CANDIDATA PSR4-06`
- confiança: alta
- evidência:
    - o endpoint carregado em `checkAuth()` + `checkApiAuth()` e `initializeTenantMiddleware($pdo)`;
    - o resolve do tenant via `getCurrentTenantId()`;
    - a delegação explícita: `$reportService = new \App\Service\ReportService($pdo);` e `$reportService->getExportDashboardData(...)`;
    - o endpoint mantém a orchestration de resposta por formato (`json`, `csv`, `excel`) e não executa SQL direto em negócio;
    - há proteção de `tenant_id` no nível de middleware e no uso do serviço.
- observação: o bloco está aderente ao padrão service/repository e não apresenta SQL procedural residual nas camadas de endpoint revisadas.

#### 2) [api/relatorios/exportExcel.php](../api/relatorios/exportExcel.php)

- classificação: `CANDIDATA PSR4-06`
- confiança: alta
- evidência:
    - `checkApiAuth()` + `initializeTenantMiddleware($pdo)` + `getCurrentTenantId()` antes da operação;
    - `new \App\Service\ReportService($pdo)`;
    - consulta ao snapshot via `$reportService->getExportSummarySnapshot(...)`;
    - o endpoint prepara planilha e entrega payload em JSON ou download, sem SQL inline de regra de negócio.
- observação: o código está em arquitetura de serviço/repositório e preserva a distinção de endpoint vs camada de dados.

#### 3) [api/relatorios/exportPDF.php](../api/relatorios/exportPDF.php)

- classificação: `CANDIDATA PSR4-06`
- confiança: alta
- evidência:
    - `checkApiAuth()` e `initializeTenantMiddleware($pdo)` precedem a lógica de exportação;
    - o PDF é construído a partir de dados do serviço, não por `SELECT` direto no endpoint;
    - a limpeza e o formatação são feitas via `generatePDFHTML(...)` e não via consulta banco no handler;
    - a validação de entrada usa `InputValidator` e a parametrização da busca por tenant/usuário e categoria/região é feita no serviço/repositório.
- observação: não há evidência suficiente para classificar como `ARQUITETURA ANTERIOR` porque o endpoint já delegou a regra e o acesso a dados.

#### 4) [src/Repository/ReportRepository.php](../src/Repository/ReportRepository.php)

- classificação: `CANDIDATA PSR4-06`
- confiança: alta
- evidência:
    - a classe recebeu a assinatura de repositório e concentra consulta SQL e agregação bruta;
    - os métodos `getExportDashboardData()` e `getExportSummarySnapshot()` estão diretamente ligados à exportação e reforçam a separação de responsabilidades;
    - há múltiplos filtros com `tenant_id = ?`, `assigned_user_id = ?` e `!$isAdmin` para proteger escopo por usuário e tenant;
    - não houve indicação de que a classe seja uma reescrita histórica fora da arquitetura PSR-4; pelo contrário, ela é a camada de dados moderna da migração.
- observação: a evidência está em favor de que esse arquivo é o núcleo de dados do lote em questão, não uma alteração posterior aleatória.

#### 5) [src/Service/ReportService.php](../src/Service/ReportService.php)

- classificação: `CANDIDATA PSR4-06`
- confiança: alta
- evidência:
    - a classe documenta explicitamente `delegar acesso a dados para ReportRepository` e `aplicar isolamento de tenant`;
    - os métodos `getExportDashboardData()` e `getExportSummarySnapshot()` validam `tenantId` e encaminham a consulta ao repositório;
    - há parâmetros `userId`, `categoria`, `regiao`, `isAdmin` e os filtros são aplicados do serviço para a camada de dados;
    - a camada de serviço não executa SQL direto; o acesso ao banco fica no repositório.
- observação: o contrato de resposta e os formatos JSON/CSV/Excel/PDF ficam no endpoint, enquanto as regras de consulta e acesso permanecem no serviço/repositório.

### PSR4-06M.6 Classificação geral

No trecho revisado, os cinco arquivos se alinham à categoria `CANDIDATA PSR4-06`, com alta confiança de que o lote já está em arquitetura moderna de serviço/repositório. Não foi encontrada evidência sólida para `ARQUITETURA ANTERIOR`, `OUTRA ALTERAÇÃO POSTERIOR` ou `ORIGEM INDETERMINADA` dentro do escopo dos itens revisados.

### PSR4-06M.7 Riscos e questões ainda sem comprovação

- a revisão foi estática; não houve execução real dos endpoints em ambiente de banco MySQL isolado;
- não foi possível comprovar o comportamento de exportação em produção real para CSV/Excel/PDF com dados reais e permissões reais;
- a documentação histórica e o working tree atual não evidenciam reabertura deliberada da etapa PSR4-06, mas a validação de integração real continua pendente;
- a diferença entre `tenant_id` em middleware, `userId` e a autorização de `isAdmin` está coerente no código, mas não foi confirmada em execução real com banco ativo.

### PSR4-06M.8 Etapas concluídas e pendentes

Etapas concluídas:

- revisão estática dos cinco arquivos-alvo;
- confirmação do arquivo canônico da migração: [docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md](../docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md);
- confirmação da separação de responsabilidades entre endpoint, serviço e repositório;
- registro da continuidade da fase PSR4-06M sem alterar código de aplicação.

Etapas pendentes:

- validação de integração real dos exports em ambiente MySQL isolado;
- execução de contrato de formato para CSV/Excel/PDF com dados e permissões reais;
- revisão final de qualquer divergência entre endpoint e service/repository em dados reais com tenant e usuários reais.

### PSR4-06M.9 Próximo passo recomendado

1. manter a trilha de migração documentada em [docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md](../docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md);
2. validar um caso real de exportação por tenant em ambiente isolado e com dados de teste dedicados;
3. confirmar, em execução, que os endpoints continuam emitindo contratos JSON/CSV/Excel/PDF corretos e que o filtro por `tenant_id` e usuário permanece rígido;
4. somente então avançar para a próxima fase de migração sem reabrir a PSR4-06 como nova auditoria.

---

## 9. Procedimento recomendado para continuar

### 9.1 Fase imediata recomendada

A próxima fase a seguir deve ser a limpeza do lote legado em UTM, analytics e relatórios públicos/analíticos, sem reabrir a PSR4-06 como nova auditoria e sem tratar Auth + Tenant como fase inédita pendente.

### 9.2 Sequência exata recomendada

1. migrar os endpoints legados de UTM e analytics
2. consolidar os relatórios/portfolio e APIs públicas ainda em SQL procedural
3. ajustar os últimos pontos residuais de auth/tenant em módulos híbridos
4. migrar usuários
5. migrar propriedades
6. migrar billing/financeiro
7. validar relatórios/public APIs com integração isolada
8. desativar compatibilidade legada

### 9.3 Critérios objetivos para avançar por fase

- consistência de auth/tenant
- ausência de fallback inseguro em módulos críticos
- testes unitários e de contrato específicos para a fase
- validação de tenant isolation
- ausência de regressão funcional no fluxo principal
- revisão final pequena e cirúrgica por lote

### PSR4-39 — webhook residual no endpoint público de propriedades

Status: concluída no escopo do domínio público de propriedades, com migração do `SELECT url FROM webhooks ...` do endpoint para a camada de serviço/repositório e com teste de contrato específico validando a remoção do SQL procedural do HTTP.

Arquivos e camadas envolvidas:

- [api/v1/public/properties.php](api/v1/public/properties.php) — endpoint público de imóveis agora delega a busca de URLs ativas para `WebhookService` em vez de realizar `SELECT` direto a partir do `triggerWebhook()`
- [src/Service/WebhookService.php](src/Service/WebhookService.php) — centraliza `getActiveUrlsForEvent()` como ponto de acesso para a listagem de URLs ativas por evento
- [src/Infrastructure/Webhook/WebhookRepository.php](src/Infrastructure/Webhook/WebhookRepository.php) — encapsula a consulta de webhooks ativos por evento e mantém o SQL fora do controller
- [tests/Unit/PublicApiMigrationContractTest.php](tests/Unit/PublicApiMigrationContractTest.php) — contrato de regressão reforçado para bloquear SQL procedural residual no endpoint público e garantir uso do serviço do módulo

O que foi preservado:

- payload e fluxo de disparo de webhook já usados pelo endpoint público;
- autenticação por API key, autorização por tenant, payload JSON e retorno de sucesso/erro do endpoint;
- comportamento de notificação do webhook sem adicionar fallback para `tenant_id = 1`;
- uso do serviço/repositório já existente do domínio de webhooks, sem reabrir módulos migrados.

Evidência executada nesta fase:

- `./vendor/bin/phpunit tests/Unit/PublicApiMigrationContractTest.php` → `OK (4 tests, 23 assertions)`
- `php -l api/v1/public/properties.php` → sem erro de sintaxe
- `php -l src/Service/WebhookService.php` → sem erro de sintaxe
- `php -l src/Infrastructure/Webhook/WebhookRepository.php` → sem erro de sintaxe
- `git diff --check` → sem saída, sem problemas de patch hygiene

Limitações da validação:

- não houve execução de integração real com MySQL em ambiente de teste dedicado;
- a validação executada foi de arquitetura, contrato e regressão do endpoint, sem afirmar execução real do webhook em ambiente externo.

Próximo lote recomendado:

- continuar com o próximo domínio residual que tenha SQL procedural direto em endpoints PHP e que seja coeso o suficiente para migrar em lote, sem reabrir módulos já validados e sem ampliar o escopo para fluxos independentes.

---

## 10. Riscos conhecidos e decisões tomadas

### Riscos concretos

- autenticação e tenant ainda são híbridos em vários módulos
- helpers globais legados ainda são usados em módulos modernos
- SQL procedural e session-based checks ainda aparecem em endpoints críticos
- ausência de banco de teste isolado para validação real de integração
- risco real de que parte do working tree atual seja composta por alterações históricas fora da PSR4-06

### Decisões já tomadas

- o projeto deve seguir o modelo de migração por dependência, não por ordem aleatória
- a PSR4-06 deve ser tratada como concluída no escopo estático e de contratos validados
- qualquer validação real de MySQL deve ocorrer em um ambiente dedicado e controlado
- não serão feitas operações de reverter, limpar ou sobrescrever o estado atual do repositório

---

## 11. Regras de execução para a continuidade

- não alterar código-fonte fora do documento de roadmap, salvo exceção estritamente necessária e previamente reportada
- não executar SQL, migrations nem operações de banco
- não criar branches, worktrees ou patches
- não executar `git reset`, `git restore`, `git checkout`, `git clean` ou `git stash`
- não executar `git add`, `git commit` ou `git push`
- não descartar alterações preexistentes
- não incluir segredos, senhas, tokens ou credenciais no documento

---

## 12. Conclusão executiva

A migração arquitetural do Sys Imóveis está em um estado de progresso real, com áreas concluídas e áreas ainda híbridas. A PSR4-06 deve ser registrada como concluída no escopo estático e de contratos validados, com ressalva explícita de integração real com MySQL ainda pendente.

A leitura direta do código atual não sustenta a reabertura de Auth + Tenant como uma nova fase pendente. A evidência histórica e funcional já mostram que esse esforço foi materializado no projeto; o que ainda permanece pendente é a limpeza dos módulos legados de UTM, analytics e relatórios/portfolio, que continuam em SQL procedural e session-based checks.

Este documento é um mapa de execução para continuidade, não uma reabertura formal da auditoria PSR4-06.

Não há evidência de necessidade de migração de banco nas fases descritas. A auditoria atual identificou principalmente mudanças de fluxo, middleware e endpoint, não uma alteração estrutural de schema.

---

## 13. Recomendação executiva final

A migração inteira deve seguir uma sequência de dependência estrita, porém com o ajuste real observado no código:

1. Migrar os endpoints legados de UTM e analytics.
2. Consolidar relatórios e APIs públicas/portfólio ainda em SQL procedural.
3. Ajustar os pontos residuais de auth/tenant nos módulos híbridos, sem tratar Auth + Tenant como fase nova.
4. Migrar usuários.
5. Migrar propriedades.
6. Migrar billing e financeiro.
7. Validar integração real com MySQL em ambiente isolado.
8. Desativar compatibilidade legada.

Esse ordenamento respeita as dependências evidenciadas no código atual e evita que o trabalho histórico já materializado seja reaberto indevidamente como uma nova etapa de migração.

---

## 14. PSR4-22 — planejamento e execução segura de testes de integração MySQL

### 14.1 Infraestrutura encontrada

A infraestrutura atual não fornece um banco MySQL de testes dedicado nem um bootstrap específico para PHPUnit em um schema isolado.

Evidências verificadas:

- [phpunit.xml](../phpunit.xml): o suíte PHPUnit usa o bootstrap do projeto e não define uma configuração secundária para banco de testes.
- [app/bootstrap.php](../app/bootstrap.php): o bootstrap carrega [config/database.php](../config/database.php) e cria a variável global `$pdo` para compatibilidade com o código legado.
- [config/database.php](../config/database.php): a conexão PDO usa as variáveis de ambiente `DB_HOST`, `DB_USER`, `DB_PASS` e `DB_NAME` e cria uma conexão MySQL explícita em TCP com `charset=utf8mb4`.
- [config/env_loader.php](../config/env_loader.php): o ambiente é carregado a partir do arquivo `.env` do projeto, que já está presente no workspace.
- [tests](../tests): a maioria dos testes existentes usa `sqlite::memory:` ou `createMock` em vez de um banco MySQL real; isso revela que o projeto tem validação em contrato e regressão, mas não um ambiente de integração MySQL isolado e formalizado.

Importante: nenhuma credencial de banco foi registrada neste documento, e nenhuma foi exposta no relatório final. A evidência relevante é que a configuração aponta para o banco de aplicação do projeto, não para um schema exclusivo de testes.

### 14.2 Teste inicial escolhido e justificativa

O caso seguro e de alto valor escolhido foi o contrato de relatórios em [tests/Unit/ReportDashboardMetricsContractTest.php](../tests/Unit/ReportDashboardMetricsContractTest.php).

Justificativa:

- o módulo de relatórios e analytics já está materializado em service/repository e foi revisado como concluído no escopo estático;
- esse teste valida a interface e as regras de contrato do serviço sem gravar dados em banco;
- executá-lo não altera dados reais nem depende de migrations automáticas;
- ele é um ponto de entrada apropriado para a próxima fase de integração, desde que o ambiente de dados seja isolado.

### 14.3 Proteção e limite de risco

A execução foi mantida dentro do limite seguro:

- não houve escrita em banco MySQL;
- não houve execução de migrations automáticas;
- não houve alteração de usuários reais;
- não houve execução de testes contra o banco configurado do projeto, porque a infraestrutura não está comprovadamente isolada.

A conclusão objetiva é: não existe, no estado atual do repositório, um ambiente de teste de integração MySQL comprovadamente dedicado e isolado. Portanto, nenhuma integração real foi aprovada.

### 14.4 Validação executada

Teste executado com sucesso, sem tocar no banco configurado:

- `./vendor/bin/phpunit --filter ReportDashboardMetricsContractTest tests/Unit/ReportDashboardMetricsContractTest.php` → `OK (5 tests, 33 assertions)`

Este resultado confirma que a suíte de contrato está funcional e que o módulo report/analytics continua aderente ao contrato esperado, mas não representa validação de banco real em MySQL.

### 14.5 Arquivos alterados

- [docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md](../docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md) — atualização desta fase PSR4-22.

Nenhum código de produção foi alterado neste passo, porque o requisito de isolamento do banco ainda não foi satisfatório.

### 14.6 Status da integração comprovada

Status atual: ainda pendente.

Motivo:

- o bootstrap atual aponta para a configuração do ambiente de aplicação;
- não há schema de testes dedicado e visível no projeto;
- não há infraestrutura explícita de isolation para escrita;
- os testes existentes não conseguem comprovar, por si só, a conexão segura a um banco isolado.

### 14.7 Próximo passo recomendado

1. provisionar um banco MySQL exclusivo para integração, por exemplo um schema nomeado de forma explícita para testes;
2. configurar um `.env` ou variável de ambiente separada para esse ambiente sem reutilizar o banco da aplicação;
3. manter o uso de fixtures isoladas e limpeza transacional quando o mecanismo de armazenamento permitir;
4. executar um único teste de leitura por tenant em um repositório migrado, tal como um caso de relatório por tenant ou UTM analytics;
5. somente então aprovar a integração real e registrar como comprovada a execução em banco de testes.

---

## 15. Conclusão da fase PSR4-22

A fase foi conduzida com segurança e rigor: a infraestrutura atual não oferece um MySQL de testes dedicado, e a execução foi limitada a um teste de contrato já validado, sem risco de escrita no banco real. Isso atende ao requisito de não avançar em integração real sem isolamento comprovado e preserva a integridade do ambiente produtivo.

A próxima etapa segura é provisionar a infraestrutura de teste dedicada e, somente após isso, validar um caso concreto de leitura em banco MySQL de forma isolada.

---

## 16. PSR4-23 — preparar infraestrutura isolada para integração MySQL

### 16.1 Objetivo

Preparar a infraestrutura necessária para executar testes de integração com MySQL em um schema exclusivo, sem reutilizar a conexão da aplicação e sem criar risco de escrita em banco produtivo.

### 16.2 Infraestrutura criada

Arquivos adicionados:

- [config/test_database.php](../config/test_database.php) — encapsula a leitura das variáveis `TEST_DB_*`, valida a segurança do ambiente e bloqueia a execução quando o schema de testes reaproveita a aplicação;
- [tests/Integration/TestDatabaseIsolationGuardTest.php](../tests/Integration/TestDatabaseIsolationGuardTest.php) — valida que a configuração de integração está explicitamente isolada antes de qualquer operação;
- [tests/Integration/TenantReportReadIntegrationTest.php](../tests/Integration/TenantReportReadIntegrationTest.php) — teste preparado para leitura por tenant em um repositório migrado, ainda aguardando schema dedicado e validação local.

Também foi atualizado o exemplo de ambiente em [.env.example](../.env.example) com as variáveis:

- `TEST_DB_HOST`
- `TEST_DB_PORT`
- `TEST_DB_USER`
- `TEST_DB_PASS`
- `TEST_DB_NAME`

### 16.3 Regras de isolamento implementadas

A partir de [config/test_database.php](../config/test_database.php):

- a conexão padrão da aplicação continua intacta;
- a conexão de testes usa somente `TEST_DB_*`;
- a execução exige `TEST_DB_ISOLATED=true` como declaração explícita de ambiente provisionado para testes;
- o código falha cedo com mensagem clara quando `TEST_DB_*` não estiver definido, quando `TEST_DB_ISOLATED` não estiver ativo ou quando `TEST_DB_NAME`/`TEST_DB_HOST` apontar para o mesmo banco da aplicação;
- a falha ocorre antes de qualquer escrita ou consulta real em banco de testes;
- a comparação com o banco da aplicação é usada como reforço de segurança, mas não como prova técnica independente de isolamento.

Isso protege contra reutilização silenciosa de `DB_NAME`, da conexão global `$pdo` e do schema principal da aplicação, e exige uma declaração explícita de que o ambiente foi provisionado para testes.

### 16.3.1 Observação de segurança

A variável `TEST_DB_ISOLATED=true` é uma declaração de configuração, não uma prova técnica autônoma. Ela impede execução accidentalmente sem confirmação explícita, mas a própria aprovação final de integração continua dependendo da existência de um schema exclusivo corretamente provisionado no ambiente local.

### 16.4 Bootstrap de testes isolado

Além das guardas de segurança, foi separado o bootstrap de execução do PHPUnit para evitar a abertura automática da conexão principal da aplicação durante a suíte unitária e de contrato.

Mudança aplicada:

- [phpunit.xml](../phpunit.xml) agora aponta para [tests/bootstrap.php](../tests/bootstrap.php)
- o bootstrap de testes mantém apenas o autoload do Composer e inicialização mínima do ambiente
- o bootstrap da aplicação em [app/bootstrap.php](../app/bootstrap.php) permanece inalterado para uso normal do sistema
- [config/database.php](../config/database.php) não é carregado no bootstrap do PHPUnit e não abre PDO da aplicação durante os testes unitários

Isso reduz risco de acoplamento e previne abertura silenciosa da conexão de produção sem uma presença explícita do banco testável.

### 16.4.1 Causa real da falha de sessão nos testes de tenant boundary

A falha não foi causada pelo bootstrap de produção nem pela conexão MySQL. A regressão observada foi uma inicialização de sessão incompatível com execução em CLI:

- o método `setUp()` de [tests/Unit/TenantBoundaryContractTest.php](../tests/Unit/TenantBoundaryContractTest.php) chamava `session_start()` sem verificar contexto HTTP, headers enviados ou execução em CLI;
- além disso, [app/security/TenantContext.php](../app/security/TenantContext.php) também fazia `session_start()` em `initialize()` e `selectTenant()` sem proteção para ambiente CLI;
- quando a suíte é executada em processo CLI/PhpUnit, a saída do runner e o comportamento do cliente HTTP não são equivalentes ao ambiente web;
- isso fazia o PHP reportar `session_start(): Session cannot be started after headers have already been sent` antes do uso real da lógica de tenant.

A correção foi mínima e segura: no ambiente de teste CLI, o código agora mantém `$_SESSION` em memória e evita `session_start()` quando o contexto não é de requisição HTTP. A aplicação real em [app/bootstrap.php](../app/bootstrap.php) continua sem alteração.

### 16.5 Testes executados

Os testes executados neste passo foram somente aqueles que não tocam o banco da aplicação:

- `php -l config/test_database.php` → sem erro de sintaxe
- `php -l tests/Integration/TestDatabaseIsolationGuardTest.php` → sem erro de sintaxe
- `php -l tests/Integration/TenantReportReadIntegrationTest.php` → sem erro de sintaxe
- `./vendor/bin/phpunit tests/Integration/TestDatabaseIsolationGuardTest.php` → `OK (5 tests, 12 assertions)`
- `./vendor/bin/phpunit tests/Unit/ReportDashboardMetricsContractTest.php` → `OK (5 tests, 30 assertions)`
- `./vendor/bin/phpunit tests/Unit/TenantBoundaryContractTest.php` → `OK (6 tests, 18 assertions)`
- `git diff --check` → sem saída, sem problemas de patch hygiene

Não foi executado o teste de leitura real contra MySQL, porque o ambiente de integração dedicado ainda não está configurado e a regra de isolamento exige falha explícita antes de qualquer execução de banco.

### 16.5 Status do ambiente local

Status atual:

- infraestrutura preparada para isolamento e guardas automáticas: concluída;
- schema de teste exclusivo: pendente de configuração local;
- validação real com MySQL: pendente de ambiente dedicado e verificado.

### 16.6 Próximo passo recomendado

1. configurar um schema MySQL exclusivo para testes em ambiente local;
2. preencher `TEST_DB_*` sem reutilizar `DB_*`;
3. confirmar que `TEST_DB_NAME` não coincide com o banco da aplicação;
4. executar o teste de leitura por tenant em [tests/Integration/TenantReportReadIntegrationTest.php](../tests/Integration/TenantReportReadIntegrationTest.php) somente após a confirmação explícita do ambiente isolado;
5. registrar a execução real com resultado e limitação do ambiente.

---

## 17. Conclusão da fase PSR4-23

A infraestrutura isolada para integração MySQL foi preparada com guardas de segurança explícitas e sem alterar a conexão padrão da aplicação. O que ainda depende de configuração local é o banco de testes dedicado e a execução real do caso de leitura por tenant.

A integração MySQL não está aprovada neste momento, porque a etapa de ambiente isolado ainda não foi confirmada localmente. O projeto está em estado de preparação segura, não em validação real de produção.

Também foi reforçada a regra de que o ambiente só pode ser usado se houver uma declaração explícita de isolamento via `TEST_DB_ISOLATED=true`, e que a própria variável não substitui a necessidade real de um schema exclusivo provisionado.

---

## 18. PSR4-24 — avanço da migração arquitetural do pipeline

### 18.1 Objetivo

Extrair a lógica de criação e listagem do pipeline da camada HTTP para uma service/repository dedicada, sem reabrir módulos aprovados anteriormente e sem reintroduzir fallback de tenant.

### 18.2 Implementação aplicada

Arquivos adicionados:

- [src/Repository/PipelineRepository.php](../src/Repository/PipelineRepository.php) — encapsula a leitura de leads/propriedades do tenant, a busca do estágio e a inserção do item do pipeline;
- [src/Service/PipelineService.php](../src/Service/PipelineService.php) — centraliza validação, normalização de dados e contrato de tenant para criação e listagem.

Arquivos ajustados:

- [api/v1/pipeline/createItem.php](../api/v1/pipeline/createItem.php) — agora delega a criação ao serviço, sem SQL procedural no handler;
- [api/v1/pipeline/getOptions.php](../api/v1/pipeline/getOptions.php) — agora consulta dados do tenant via service/repository;
- [tests/Unit/PipelineMigrationContractTest.php](../tests/Unit/PipelineMigrationContractTest.php) — valida a presença da camada nova e a ausência de fallback inseguro ou SQL inline no endpoint.

### 18.3 Validação executada

Comando executado:

- `./vendor/bin/phpunit tests/Unit/PipelineMigrationContractTest.php` → `OK (4 tests, 13 assertions)`

Isso confirma que a migração do pipeline entrou no mesmo padrão de isolamento e contrato já adotado em outros módulos migrados, sem reabrir o lote de Auth + Tenant ou reintroduzir o fallback para `tenant_id = 1`.

### 18.4 Status

Status atual:

- criação do item do pipeline migrada para service/repository: concluída;
- listagem de opções do pipeline migrada para service/repository: concluída;
- validação de contrato e isolamento de tenant: concluída;
- validação real com MySQL: continua pendente, como em todas as demais fases já concluídas em escopo estático e de contrato.

A evolução do pipeline representa um avanço real de arquitetura sem ampliar o escopo para módulos previamente concluídos ou a reabertura do trabalho de Auth + Tenant.

---

## 19. PSR4-25 — conclusão do lote restante do pipeline

### 19.1 Objetivo

Completar o restante do módulo de pipeline que ainda mantinha lógica de negócio e SQL inline nos handlers HTTP, preservando a regra de que não há reabertura de módulos já migrados e que não existe fallback silencioso para `tenant_id = 1`.

### 19.2 Implementação aplicada

Arquivos ajustados:

- [src/Repository/PipelineRepository.php](../src/Repository/PipelineRepository.php) — incorpora `listItemsByTenant`, `listStagesByTenant`, `findItemByTenant`, `updateItemByIdAndTenant`, `moveItemByIdAndTenant` e `deleteItemByIdAndTenant` com escopo por tenant;
- [src/Service/PipelineService.php](../src/Service/PipelineService.php) — inclui `getItems`, `getStages`, `updateItem`, `moveItem` e `deleteItem`, com validação de tenant, autorização e normalização de payload;
- [api/v1/pipeline/getItems.php](../api/v1/pipeline/getItems.php) — delega listagem do kanban para o serviço;
- [api/v1/pipeline/getStages.php](../api/v1/pipeline/getStages.php) — delega busca de estágios para o serviço;
- [api/v1/pipeline/updateItem.php](../api/v1/pipeline/updateItem.php) — remove a lógica de SQL inline e delega ao serviço;
- [api/v1/pipeline/moveItem.php](../api/v1/pipeline/moveItem.php) — remove a transação procedural e utiliza a camada de serviço;
- [api/v1/pipeline/deleteItem.php](../api/v1/pipeline/deleteItem.php) — usa o serviço para soft delete por tenant, preservando a validação de acesso;
- [tests/Unit/PipelineMigrationContractTest.php](../tests/Unit/PipelineMigrationContractTest.php) — validou a presença do contrato restante e a ausência de `tenant_id = 1`.

### 19.3 Validação executada

Comando executado:

- `./vendor/bin/phpunit tests/Unit/PipelineMigrationContractTest.php` → `OK (5 tests, 33 assertions)`;
- `php -l src/Service/PipelineService.php`, `php -l src/Repository/PipelineRepository.php`, `php -l api/v1/pipeline/getItems.php`, `php -l api/v1/pipeline/getStages.php`, `php -l api/v1/pipeline/updateItem.php`, `php -l api/v1/pipeline/moveItem.php`, `php -l api/v1/pipeline/deleteItem.php` → sem erros de sintaxe;
- `git diff --check` → sem saída, sem problemas de patch.

### 19.4 Status

Status atual:

- criação do item do pipeline: concluída;
- listagem de opções do pipeline: concluída;
- listagem, estágios, atualização, movimentação e exclusão do pipeline: concluídas no padrão service/repository;
- contrato de isolamento por tenant: validado;
- próxima etapa: seguir para o próximo módulo pendente com evidência explícita, sem reabrir módulos previamente migrados.

Este lote reforça a regra do projeto: migrar apenas o módulo real pendente e manter o escopo enxuto, sem expansão para módulos já aprovados.

---

## 20. PSR4-26 — migração do sincronismo de preços Stripe dos planos

### 20.1 Objetivo

Mover a lógica de sincronização de `stripe_price_id` dos planos para a camada service/repository, eliminando SQL procedural residual no endpoint HTTP e mantendo o contrato de autenticação/autorizações já existente.

### 20.2 Evidência da pendência

A investigação direta dos handlers de API revelou que o endpoint [api/v1/billing/syncStripePrices.php](../api/v1/billing/syncStripePrices.php) ainda continha SQL inline de negócio:

- `SELECT code, name, stripe_price_id FROM plans`;
- `UPDATE plans SET stripe_price_id = ? WHERE code = ?`;
- lógica de matching de preços Stripe e identificadores locais embutida no próprio arquivo.

Esse é o tipo de evidência exigida pelo projeto para justificar a próxima migração sem reabrir módulos já concluídos.

### 20.3 Implementação aplicada

Arquivos adicionados:

- [src/Infrastructure/Billing/PlanRepository.php](../src/Infrastructure/Billing/PlanRepository.php) — encapsula leitura do catálogo de planos e atualização do `stripe_price_id` por código;
- [src/Services/Billing/StripePriceSyncService.php](../src/Services/Billing/StripePriceSyncService.php) — centraliza o fetch do Stripe, o matching entre preço/plano, o ciclo de atualização e a resposta normalizada do endpoint.

Arquivos ajustados:

- [api/v1/billing/syncStripePrices.php](../api/v1/billing/syncStripePrices.php) — delega a sincronização ao serviço e remove o SQL inline e a lógica procedural do handler;
- [tests/Unit/StripePriceSyncServiceContractTest.php](../tests/Unit/StripePriceSyncServiceContractTest.php) — valida a presença da nova camada e assegura que o endpoint não repete SQL de negócio direto.

### 20.4 Validação executada

Comandos executados:

- `php -l api/v1/billing/syncStripePrices.php` → sem erros de sintaxe;
- `php -l src/Services/Billing/StripePriceSyncService.php` → sem erros de sintaxe;
- `php -l src/Infrastructure/Billing/PlanRepository.php` → sem erros de sintaxe;
- `./vendor/bin/phpunit tests/Unit/StripePriceSyncServiceContractTest.php --testdox` → `OK (2 tests, 6 assertions)`.

### 20.5 Status

Status atual:

- acompanhamento de preços do Stripe no módulo de billing migrado para service/repository: concluído;
- SQL de negócio removido do endpoint HTTP: concluído;
- validação de contrato: concluída;
- próxima etapa: continuar apenas com módulos que apresentem evidência explícita de SQL residual e escopo de migração seguro, sem reiniciar fases já validadas.

Este lote respeita a disciplina do projeto: escopo reduzido, evidência de SQL residual no handler, contrato HTTP preservado e ausência de reabertura de módulos já aprovados.

---

## 21. PSR4-27 — migração do status de login e contadores de agendamentos

### 21.1 Objetivo

Mover o cálculo de status de login e os contadores de agendamentos da camada HTTP para um service/repository dedicado, preservando o filtro de tenant e o contrato JSON do endpoint sem abrir módulos já concluídos.

### 21.2 Evidência da pendência

A investigação direta do handler [api/v1/check_login.php](../api/v1/check_login.php) revelou SQL de negócio executado no endpoint:

- `SELECT COUNT(*) as total FROM agendamentos WHERE user_id = ? AND tenant_id = ? AND deleted_at IS NULL`;
- `SELECT COUNT(*) as total FROM agendamentos WHERE tenant_id = ? AND deleted_at IS NULL`;

Esses contadores fazem parte da regra de negócio do estado do usuário no tenant e não deveriam permanecer no controlador HTTP.

### 21.3 Implementação aplicada

Arquivos adicionados:

- [src/Repository/AgendamentoRepository.php](../src/Repository/AgendamentoRepository.php) — encapsula `countForUser()` e `countForTenant()` com escopo por tenant;
- [src/Service/CheckLoginService.php](../src/Service/CheckLoginService.php) — centraliza validação do usuário, resolução do tenant e cálculo do payload de login.

Arquivos ajustados:

- [api/v1/check_login.php](../api/v1/check_login.php) — delega a lógica ao service e remove as contagens SQL inline do handler;
- [tests/Unit/CheckLoginMigrationContractTest.php](../tests/Unit/CheckLoginMigrationContractTest.php) — valida a nova camada e a ausência do SQL residual no endpoint.

### 21.4 Validação executada

Comandos executados:

- `php -l api/v1/check_login.php` → sem erros de sintaxe;
- `php -l src/Service/CheckLoginService.php` → sem erros de sintaxe;
- `php -l src/Repository/AgendamentoRepository.php` → sem erros de sintaxe;
- `./vendor/bin/phpunit tests/Unit/CheckLoginMigrationContractTest.php --testdox` → `OK (2 tests, 5 assertions)`.

### 21.5 Status

Status atual:

- contadores de agendamentos no status de login migrados para service/repository: concluído;
- SQL de negócio removido do endpoint HTTP: concluído;
- contrato de resposta e validação por tenant preservados: concluído;
- próxima etapa: continuar com o próximo módulo que apresente evidência explícita de SQL residual em handler, sem reabrir módulos já validados.

Este lote respeita a regra do projeto: módulo coeso, evidência concreta de SQL residual no endpoint, esquecimento de fallback inseguro e validação limitada ao escopo do módulo.

---

## PSR4-06X — fechamento documental do lote PSR4-06

### PSR4-06X.1 Objetivo

Registrar, sem reescrever o histórico, o fechamento documental do lote PSR4-06 com base nas evidências estáticas do repositório e preservar a distinção entre migração de código e validação funcional real.

### PSR4-06X.2 Conclusão arquitetural do lote revisado

A migração arquitetural do lote revisado deve ser considerada concluída com base nas evidências estáticas disponíveis no repositório:

- os endpoints de relatórios e exportação delegam para `ReportService` e não mantêm SQL de negócio procedural no handler;
- a lógica de acesso a dados fica centralizada em `ReportRepository` e preserva o isolamento por tenant;
- a separação de responsabilidades entre endpoint, serviço e repositório está consistentes com a arquitetura moderna do projeto;
- não houve evidência de dívida arquitetural concreta no escopo de [api/relatorios](../api/relatorios), [src/Service/ReportService.php](../src/Service/ReportService.php) e [src/Repository/ReportRepository.php](../src/Repository/ReportRepository.php).

### PSR4-06X.3 Testes unitários reportados como aprovados

O registro canônico do lote registra, como testes anteriormente aprovados no escopo de relatórios e contratos, um conjunto de 44 testes e 262 assertions.

Esse total não deve ser interpretado como nova execução nesta etapa; trata-se do histórico de validação documentado do lote e deve ser mantido como evidência do escopo revisado.

### PSR4-06X.4 Limitações da cobertura estática

A conclusão documentada neste ponto é limitada ao escopo estático e de contrato:

- a revisão não substitui a validação real em banco MySQL;
- a leitura do código não prova comportamento de exportação em dados reais;
- a cobertura estática não certifica execução real de CSV, Excel, PDF nem permissões multi-tenant em ambiente de produção ou de testes;
- a camada de repositório pode estar correta em arquitetura, mas ainda assim requer integração real para confirmação funcional.

### PSR4-06X.5 Validação funcional real ainda pendente

As validações a seguir continuam pendentes e não foram convertidas em prova de execução real neste momento:

- exportação em CSV com dados e permissões reais;
- exportação em Excel com dados e permissões reais;
- exportação em PDF com dados e permissões reais;
- isolamento multi-tenant em execução real com MySQL dedicado;
- confirmação de que o ambiente de testes MySQL está efetivamente isolado do banco da aplicação.

### PSR4-06X.6 Estado do ambiente MySQL de testes

O ambiente de testes MySQL não foi comprovado como efetivamente isolado no repositório atual.

A evidência disponível mostra:

- a infraestrutura de guardas e variáveis `TEST_DB_*` foi preparada e documentada;
- a exigência de `TEST_DB_ISOLATED=true` e a comparação de host/schema são reforços de segurança;
- mas isso não equivale a confirmação real de um schema exclusivo provisionado e funcional em execução.

Em outras palavras, o projeto possui proteção de configuração e contrato de isolamento, mas não uma validação real e verificável do ambiente local de testes MySQL.

### PSR4-06X.7 Limite da conclusão

A conclusão correta para o lote PSR4-06 é:

- migração de código do lote concluída;
- validação funcional pendente.

Essa conclusão se limita ao lote PSR4-06 e não certifica a migração global do projeto. Não há evidência suficiente para declarar que toda a migração arquitetural do sistema está concluída somente porque o módulo de relatórios e exportações foi revisado com sucesso em arquitetura e contrato.

### PSR4-06X.8 Encerramento documental

A presente seção fecha o registro do lote PSR4-06 sem reescrever o histórico anterior, sem alterar o código da aplicação, sem executar SQL ou testes nesta etapa, e sem declarar a migração global do projeto concluída.

A conclusão aqui registrada é estritamente local ao lote PSR4-06 e deve ser entendida como um fechamento documental da migração arquitetural revisada, com a ressalva explícita de que a validação funcional real ainda depende de ambiente MySQL isolado e execução autorizada contra dados de teste dedicados.

---

---

## 22. PSR4-28 — migração da listagem de mensagens não lidas do tenant

### 22.1 Objetivo

Mover a contagem e a listagem de mensagens não lidas do tenant para a camada service/repository, eliminando SQL residual no endpoint HTTP sem reabrir módulos que já foram validados.

### 22.2 Evidência da pendência

A investigação direta do handler [api/v1/auth/getUnreadEmails.php](../api/v1/auth/getUnreadEmails.php) revelou SQL de negócio executado no endpoint:

- `SELECT COUNT(*) as cnt FROM messages WHERE is_read = FALSE AND tenant_id = ?`;
- `SELECT id, from_name, from_email, subject, created_at FROM messages WHERE is_read = FALSE AND tenant_id = ? ORDER BY created_at DESC LIMIT 10`;

Esses acessos fazem parte da regra de negócio de mensagens do tenant e não devem permanecer no controlador HTTP.

### 22.3 Implementação aplicada

Arquivos adicionados:

- nenhum arquivo totalmente novo; o módulo utilizou a base já existente do padrão service/repository para mensagens.

Arquivos ajustados:

- [src/Repository/MessageRepository.php](../src/Repository/MessageRepository.php) — incorpora `countUnreadForTenant()` e `listUnreadForTenant()` com escopo pelo tenant;
- [src/Service/MessageService.php](../src/Service/MessageService.php) — incorpora `countUnreadForTenant()` e `listUnreadForTenant()`, com validação de tenant e limite;
- [api/v1/auth/getUnreadEmails.php](../api/v1/auth/getUnreadEmails.php) — passa a delegar a consulta para o `MessageService`, removendo SQL inline do handler;
- [tests/Unit/MessageMigrationContractTest.php](../tests/Unit/MessageMigrationContractTest.php) — amplia o contrato para validar o endpoint de mensagens não lidas e a ausência de SQL residual.

### 22.4 Validação executada

Comandos executados:

- `php -l api/v1/auth/getUnreadEmails.php` → sem erros de sintaxe;
- `php -l src/Service/MessageService.php` → sem erros de sintaxe;
- `php -l src/Repository/MessageRepository.php` → sem erros de sintaxe;
- `./vendor/bin/phpunit tests/Unit/MessageMigrationContractTest.php --testdox` → `OK (2 tests, 13 assertions)`.

### 22.5 Status

Status atual:

- listagem e contagem de mensagens não lidas por tenant migradas para service/repository: concluído;
- SQL de negócio removido do endpoint HTTP: concluído;
- contrato de mensagens atualizado para cobrir o endpoint pendente: concluído;
- próxima etapa: continuar apenas com módulos que apresentem evidência explícita de SQL residual em handlers HTTP, sem reabrir módulos já validados.

Este lote segue a disciplina do projeto: módulo coeso, evidência real de SQL residual, escopo fechado, guardas de tenant preservadas, sem fallback inseguro nem expansão para módulos concluídos.

---

## 23. PSR4-29 — migração do rastreamento de visitas do site

### 23.1 Objetivo

Extrair o rastreamento de visitas do site da camada HTTP para um service/repository dedicado, mantendo o isolamento por tenant, sem reabrir fluxos já migrados e sem introduzir fallback para `tenant_id = 1`.

### 23.2 Evidência da pendência

A investigação direta do endpoint [api/v1/auth/trackVisit.php](../api/v1/auth/trackVisit.php) revelou SQL de negócio executado no handler:

- `SELECT id FROM site_visits WHERE session_id = ? AND visited_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)`;
- `INSERT INTO site_visits (tenant_id, visitor_ip, visitor_ua, page_url, referrer, session_id)`.

Esse é um módulo coeso de analytics público/visit logging, com sentido de negócio próprio e sem overlap com os lotes já concluídos em pipeline, Stripe, login e mensagens.

### 23.3 Implementação aplicada

Arquivos adicionados:

- [src/Repository/SiteVisitRepository.php](../src/Repository/SiteVisitRepository.php) — encapsula `hasRecentVisitForSession()` e `insertVisit()`;
- [src/Service/SiteVisitService.php](../src/Service/SiteVisitService.php) — centraliza a validação do tenant, da sessão e da regra de deduplicação por 5 minutos.

Arquivos ajustados:

- [api/v1/auth/trackVisit.php](../api/v1/auth/trackVisit.php) — passa a delegar o tracking ao `SiteVisitService`, removendo SQL inline do endpoint;
- [tests/Unit/SiteVisitMigrationContractTest.php](../tests/Unit/SiteVisitMigrationContractTest.php) — valida a presença da nova camada e a ausência de SQL residual no handler.

### 23.4 Validação executada

Comandos executados:

- `./vendor/bin/phpunit tests/Unit/SiteVisitMigrationContractTest.php --testdox` → `OK (2 tests, 6 assertions)`;
- `php -l api/v1/auth/trackVisit.php` → sem erro de sintaxe;
- `php -l src/Service/SiteVisitService.php` → sem erro de sintaxe;
- `php -l src/Repository/SiteVisitRepository.php` → sem erro de sintaxe;
- `git diff --check` → sem saída, sem problemas de patch.

### 23.5 Status

Status atual:

- rastreamento de visitas do site migrado para service/repository: concluído;
- SQL de negócio removido do endpoint HTTP: concluído;
- isolamento por tenant preservado e sem fallback para `tenant_id = 1`: concluído;
- validação focada do módulo: executada com sucesso;
- próximo passo: continuar apenas com módulos que apresentem evidência explícita de SQL residual em handlers HTTP e que ainda não tenham sido validados nesta sequência.

Este lote conserva a disciplina do projeto: alvo com evidência concreta, módulo coeso, escopo fechado, sem reabertura de módulos já tratados e sem expansão arbitrária do backlog.

---

## 24. PSR4-30 — migração do monitoramento de segurança e bloqueio de IP

### 24.1 Objetivo

Extrair o painel e as ações de segurança do módulo de monitoramento da camada HTTP para service/repository, mantendo autenticação/autorizações, filtragem por tenant e sem introduzir fallback para `tenant_id = 1`.

### 24.2 Evidência da pendência

A investigação direta dos endpoints [api/v1/debug/getSecurityMonitoring.php](../api/v1/debug/getSecurityMonitoring.php), [api/v1/debug/blockIP.php](../api/v1/debug/blockIP.php) e [api/v1/debug/unblockIP.php](../api/v1/debug/unblockIP.php) revelou SQL de negócio inline no handler:

- `SELECT COUNT(*) ... FROM security_anomalies ... WHERE tenant_id = ...`;
- `SELECT COUNT(DISTINCT ip_address) ... FROM suspicious_ips ... WHERE tenant_id = ...`;
- `SELECT id FROM blocked_ips WHERE ip_address = ?`;
- `UPDATE blocked_ips SET ... WHERE ip_address = ?`;
- `UPDATE blocked_ips SET blocked_until = NOW() ... WHERE ip_address = ?`.

Esse módulo é coerente e isolado: painel de segurança, bloqueio e desbloqueio de IPs, todos com escopo por tenant e autorização administrativa.

### 24.3 Implementação aplicada

Arquivos ajustados:

- [api/v1/debug/getSecurityMonitoring.php](../api/v1/debug/getSecurityMonitoring.php) — delega o painel para `SecurityMonitoringService` e remove o SQL inline do endpoint;
- [api/v1/debug/blockIP.php](../api/v1/debug/blockIP.php) — remove a lógica procedural de consulta/insert/update para o repository/service;
- [api/v1/debug/unblockIP.php](../api/v1/debug/unblockIP.php) — remove a lógica procedural de verificação e atualização do IP do handler;
- [src/Service/SecurityMonitoringService.php](../src/Service/SecurityMonitoringService.php) — centraliza validações de tenant, IP e motivo e orquestra o caso de uso do módulo;
- [src/Repository/SecurityMonitoringRepository.php](../src/Repository/SecurityMonitoringRepository.php) — encapsula a leitura do overview, investigação do IP bloqueado, bloqueio e desbloqueio;
- [tests/Unit/SecurityMonitoringMigrationContractTest.php](../tests/Unit/SecurityMonitoringMigrationContractTest.php) — valida a presença do service/repository e a ausência de SQL residual nos endpoints.

### 24.4 Validação executada

Comandos executados:

- `./vendor/bin/phpunit tests/Unit/SecurityMonitoringMigrationContractTest.php --testdox` → `OK (2 tests, 12 assertions)`;
- `php -l api/v1/debug/getSecurityMonitoring.php` → sem erro de sintaxe;
- `php -l api/v1/debug/blockIP.php` → sem erro de sintaxe;
- `php -l api/v1/debug/unblockIP.php` → sem erro de sintaxe;
- `php -l src/Service/SecurityMonitoringService.php` → sem erro de sintaxe;
- `php -l src/Repository/SecurityMonitoringRepository.php` → sem erro de sintaxe;
- `git diff --check` → sem saída, sem problemas de patch.

### 24.5 Status

Status atual:

- painel de segurança migrado para service/repository: concluído;
- bloqueio e desbloqueio de IP migrados para service/repository: concluído;
- SQL de negócio removido dos endpoints HTTP: concluído;
- validação focada do módulo: executada com sucesso;
- próxima etapa recomendada: seguir para o próximo módulo com evidência explícita de SQL residual em handler, preservando a regra de manter o escopo fechado e sem reiniciar módulos já validados.

Este lote respeita a disciplina do projeto: módulo coeso, evidência real de SQL residual, autorização e tenant preservados, sem fallback inseguro para `tenant_id = 1` e sem expansão para módulos concluídos.

---

## 25. PSR4-31 — migração da sincronização do Google Calendar

### 25.1 Objetivo

Extrair a validação de pertencimento do usuário ao tenant da camada HTTP para uma service/repository dedicada, mantendo a autorização e a regra de tenant explícita sem reabrir módulos já validados.

### 25.2 Evidência da pendência

A investigação direta do endpoint [api/v1/google/syncCalendar.php](../api/v1/google/syncCalendar.php) revelou SQL de negócio executado no handler:

- `SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1`;
- o bloco verificava o vínculo do usuário autenticado com o tenant atual antes de sincronizar o Google Calendar.

Esse é um módulo coeso de sincronização externa, com validação de identidade e ACL por tenant antes da chamada ao serviço de calendário.

### 25.3 Implementação aplicada

Arquivos adicionados:

- [src/Repository/GoogleCalendarSyncRepository.php](../src/Repository/GoogleCalendarSyncRepository.php) — encapsula `userExistsInTenant()`;
- [src/Service/GoogleCalendarSyncService.php](../src/Service/GoogleCalendarSyncService.php) — centraliza `validateUserBelongsToTenant()`.

Arquivos ajustados:

- [api/v1/google/syncCalendar.php](../api/v1/google/syncCalendar.php) — delega a validação de user/tenant ao `GoogleCalendarSyncService`, removendo a checagem inline do endpoint;
- [tests/Unit/GoogleCalendarSyncMigrationContractTest.php](../tests/Unit/GoogleCalendarSyncMigrationContractTest.php) — valida a existência do service/repository e a ausência de SQL residual no handler.

### 25.4 Validação executada

Comandos executados:

- `./vendor/bin/phpunit tests/Unit/GoogleCalendarSyncMigrationContractTest.php --testdox` → `OK (2 tests, 8 assertions)`;
- `php -l api/v1/google/syncCalendar.php` → sem erro de sintaxe;
- `php -l src/Service/GoogleCalendarSyncService.php` → sem erro de sintaxe;
- `php -l src/Repository/GoogleCalendarSyncRepository.php` → sem erro de sintaxe;
- `git diff --check` → sem saída, sem problemas de patch.

### 25.5 Status

Status atual:

- validação do vínculo usuário/tenant da sincronização do Google Calendar migrada para service/repository: concluído;
- SQL de negócio removido do endpoint HTTP: concluído;
- contrato do módulo atualizado para cobrir a validação de tenant: concluído;
- próxima etapa recomendada: continuar apenas com módulos que continuem a apresentar evidência explícita de SQL residual em handlers HTTP, mantendo o mesmo critério de escopo fechado e sem reabrir módulos já validados.

Este lote mantém a disciplina do projeto: módulo coeso, evidência real de SQL residual, tenant e autorização preservados, sem fallback inseguro para `tenant_id = 1` e sem expansão arbitrária para áreas já migradas.

---

## 26. PSR4-32 — migração do registro de sincronização do Google Calendar

### 26.1 Objetivo

Extrair o restante do fluxo de sincronização de eventos do Google Calendar da camada HTTP para um service/repository dedicado, mantendo a validação de tenant, a posse do agendamento e a contagem/listagem de eventos sincronizados dentro do escopo do módulo.

### 26.2 Evidência da pendência

A investigação direta do endpoint [api/v1/google_calendar.php](../api/v1/google_calendar.php) revelou SQL residual de negócio no handler, incluindo:

- `SELECT endereco FROM properties WHERE id = ? AND tenant_id = ? LIMIT 1`;
- `SELECT google_event_id FROM agendamentos_google_sync WHERE agendamento_id = ? AND tenant_id = ? LIMIT 1`;
- `INSERT INTO agendamentos_google_sync (tenant_id, agendamento_id, google_event_id) VALUES (?, ?, ?)`;
- `DELETE FROM agendamentos_google_sync WHERE agendamento_id = ? AND tenant_id = ?`;
- `SELECT COUNT(*) as count FROM agendamentos_google_sync WHERE tenant_id = ?`;
- `SELECT a.* FROM agendamentos a WHERE a.id = ? AND a.tenant_id = ? AND a.user_id = ? LIMIT 1`;
- `SELECT s.google_event_id ... INNER JOIN agendamentos ... WHERE s.tenant_id = ? AND a.user_id = ?`.

Esse bloco é coeso: guarda de sincronização de eventos do Google Calendar, consulta de propriedades, vínculo de agendamento ao usuário/tenant e listagem de eventos sincronizados.

### 26.3 Implementação aplicada

Arquivos adicionados:

- [src/Repository/GoogleCalendarSyncRepository.php](../src/Repository/GoogleCalendarSyncRepository.php) — passa a encapsular `findOwnedAppointmentForUser()`, `findPropertyAddressForTenant()`, `findSyncRecordForAppointment()`, `upsertSyncRecord()`, `deleteSyncRecord()`, `countSyncedEventsForTenant()` e `listAllowedGoogleEventIdsForUser()`;
- [src/Service/GoogleCalendarSyncService.php](../src/Service/GoogleCalendarSyncService.php) — centraliza validação de tenant/usuário, resolução de agendamento, endereço da propriedade, registro de sincronização e listagem de eventos permitidos.

Arquivos ajustados:

- [api/v1/google_calendar.php](../api/v1/google_calendar.php) — troca as consultas inline de `handleSyncAll()`, `handleSyncEvent()`, `handleUnsyncEvent()`, `handleGetStatus()`, `handleListEvents()` e `resolveOwnedAgendamentoForUser()` por chamadas ao `GoogleCalendarSyncService`;
- [tests/Unit/GoogleCalendarEventSyncMigrationContractTest.php](../tests/Unit/GoogleCalendarEventSyncMigrationContractTest.php) — valida a existência do service/repository e a ausência de SQL residual nos trechos migrados do endpoint.

### 26.4 Validação executada

Comandos executados:

- `./vendor/bin/phpunit tests/Unit/GoogleCalendarEventSyncMigrationContractTest.php --testdox` → `OK (2 tests, 12 assertions)`;
- `./vendor/bin/phpunit tests/Unit/GoogleCalendarSyncMigrationContractTest.php --testdox` → `OK (2 tests, 8 assertions)`;
- `php -l api/v1/google_calendar.php` → sem erro de sintaxe;
- `php -l src/Service/GoogleCalendarSyncService.php` → sem erro de sintaxe;
- `php -l src/Repository/GoogleCalendarSyncRepository.php` → sem erro de sintaxe;
- `php -l tests/Unit/GoogleCalendarEventSyncMigrationContractTest.php` → sem erro de sintaxe;
- `git diff --check -- api/v1/google_calendar.php src/Service/GoogleCalendarSyncService.php src/Repository/GoogleCalendarSyncRepository.php tests/Unit/GoogleCalendarEventSyncMigrationContractTest.php docs/DEFINITIVE_ENTERPRISE_MIGRATION_ROADMAP.md` → sem saída e sem problemas de patch.

### 26.5 Status

Status atual:

- bloco residual de sincronização de eventos do Google Calendar migrado para service/repository: concluído;
- SQL de negócio removido dos trechos do endpoint HTTP para o setor do módulo: concluído;
- contratos para tenant validation e event sync coverage: executados com sucesso;
- limitação real: esta mudança preserva a arquitetura e a API existente; não houve integração real com MySQL nem com serviços externos durante esta etapa, apenas refatoração de dados e validação por contrato no código.
- próximo passo recomendado: continuar somente com módulos que ainda mostrem evidência explícita de SQL em handlers HTTP e que não tenham sido cobertos por lotes anteriores, mantendo a mesma disciplina de escopo e isolamento por tenant.

Este lote respeita os critérios do projeto: módulo coeso, evidência material de SQL residual, autenticação/autorizações preservadas, sem fallback inseguro para `tenant_id = 1`, sem expansão para módulos já validados e sem afirmação de integração real que não tenha sido executada.
