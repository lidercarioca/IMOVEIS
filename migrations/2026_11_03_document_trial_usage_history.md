# Documentação da migração histórica do TRIAL

## Objetivo

Preservar o histórico de uso do TRIAL em uma tabela dedicada, sem contaminar o financeiro.

## Evidências acceptáveis

A migração cria registros em `tenant_trial_usage` apenas para evidências históricas explícitas:

- tenants atualmente no plano `trial`
- registros de pagamentos com `plano = 'trial'`
- fluxos de onboarding/registro que criam explicitamente o tenant em `trial` e que já estão codificados em:
    - `cadastro_empresa_action.php`
    - `TenantRegistrationService`
    - `TenantOnboardingService`

## Casos ambíguos e revisão manual

Não é possível inferir uso de TRIAL sem evidência persistida. Para qualquer tenant que hoje esteja em plano pago, mas tenha `plano = 'trial'` em alguma linha histórica antiga sem registro confiável em `tenant_trial_usage`, o critério de revisão manual é:

- verificar se existe `pagamentos` com `plano = 'trial'` e `tenant_id` válido;
- verificar se o tenant ainda possui `status = 'trial'` ou `plano = 'trial'` no registro base;
- verificar se há data de criação ou data de expiração confiável no tenant;
- garantir que o tenant não receba um novo TRIAL automaticamente sem histórico explícito.

Quando a evidência for ambígua, a migração não cria uso artificial e não concede novo TRIAL automaticamente. O registro deve permanecer pendente de revisão manual por ID e critério.

## Não alterado

- registros em `pagamentos`
- status dos tenants
- dados financeiros reais

## Checklist de revisão manual recomendada

- `SELECT id, name, plano, status, created_at, data_expiracao FROM tenants WHERE plano = 'trial';`
- `SELECT DISTINCT tenant_id FROM pagamentos WHERE plano = 'trial';`
- `SELECT tenant_id FROM tenant_trial_usage;`
- comparar os dois conjuntos para encontrar `tenant_id` presente em um histórico antigo e ausente em `tenant_trial_usage`.

## Aplicação futura

Executar as migrações em ambiente controlado e validar após a aplicação com uma consulta específica para `tenant_trial_usage`.
