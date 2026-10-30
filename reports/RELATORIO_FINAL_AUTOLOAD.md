# AUDITORIA DE RUNTIME E AUTOLOAD — SYS IMÓVEIS

## 1. Situação atual

- Composer PSR-4: `App\` => `src/`.
- Existência de código `App\` em `app/` (services/models/helpers) que não é coberto pelo autoload do Composer.

## 2. Evidências técnicas

- `app/services/FinanceService.php` existe, mas `class_exists` via Composer retorna NOT FOUND.
- `api/v1/financeiro/getFinanceiro.php` instancia `App\Services\FinanceService` e falha em runtime com `Class not found`.

## 3. Quantificação do problema

- Total endpoints `api/v1`: 32118.
- Endpoints que referenciam `app/services` ou `app/models`: 33.
- Endpoints que incluem `vendor/autoload.php`: 52.
- Classes `App\` encontradas fora do PSR-4 (em `app/`): 29.

## 4. Mapa de dependências

Os arquivos CSV gerados contêm o inventário completo e as matrizes de dependência: `namespace_inventory.csv`, `endpoint_dependency_matrix.csv`, `service_resolution_report.csv`, `migration_priority_matrix.csv`.

## 5. Endpoints críticos

Endereços marcados como CRITICO: 

- api/v1/alertas/getAlerts.php
- api/v1/alertas/getInactivityAlerts.php
- api/v1/alertas/handleInactivityAlert.php
- api/v1/alertas/processAlerts.php
- api/v1/financeiro/addFinanceiro.php
- api/v1/financeiro/deleteCommission.php
- api/v1/financeiro/deleteFinanceiro.php
- api/v1/financeiro/getFinanceiro.php
- api/v1/financeiro/updateFinanceiro.php
- api/v1/leads/getActivityStats.php
- api/v1/leads/getInteractions.php
- api/v1/leads/recordInteraction.php
- api/v1/leads/recordView.php
- api/v1/leads/scoring/calculate.php
- api/v1/leads/scoring/stats.php

## 6. Estratégia recomendada de migração (resumo)

1. Prioridade: `Financeiro` (mover classes de `app/services` para `src/` e validar via Composer).
2. Em paralelo, alinhar `Alertas`, `Autenticação`, `Billing`, `Leads`.
3. Criar bootstrap temporário único e tests de smoke para endpoints críticos antes de qualquer deploy.

## 7. Riscos de produção

- Deploys que alterem o autoload sem migrar `app/` quebrarão endpoints que dependem de classes fora do PSR-4.

## 8. Critérios para arquitetura estabilizada

- Todas as classes `App\` resolvidas via Composer (PSR-4) ou documentadas e carregadas por bootstrap central.
- 100% dos endpoints críticos passam em smoke tests.
