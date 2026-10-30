# Dashboard KPI / Realtime — Contrato de Decisões de Negócio

## Status

STATUS:
BLOCKED_BY_BUSINESS_DECISION

BASELINE:
2c7d94c

DATA:
25/09/2026

Este documento não implementa regras.
Este documento registra decisões ainda pendentes.
O código atual é evidência histórica e não deve ser tratado como regra de negócio definitiva.

## Seção 1 — Princípio Fundamental

1. KPI e Realtime são contratos atualmente distintos.
2. Não existe autorização para unificação automática.
3. Nenhuma coluna deve ser declarada “oficial” apenas porque possui writer.
4. Nenhuma métrica deve ser considerada equivalente apenas porque possui nome semelhante.
5. A implementação somente poderá começar após as decisões necessárias serem preenchidas.
6. O código existente deve ser preservado enquanto as decisões permanecem pendentes.

## Seção 2 — DECISION-001

## DECISION-001 — Valor Oficial de Venda

Status:
PENDING

OFFICIAL_SALE_VALUE_FIELD = PENDING

Perguntas pendentes:

1. O campo representa o preço final efetivamente vendido?
2. Pode ser NULL?
3. Deve entrar em average_ticket?
4. Deve entrar em total_revenue?
5. Deve entrar na receita dos brokers?
6. O mesmo campo será usado no KPI e no Realtime?

Evidência técnica registrada sem transformar em decisão:

- property_sales.property_price possui writer comprovado no código.
- property_sales.sale_price possui readers, mas nenhum writer ativo foi comprovado.
- Isso NÃO determina a decisão de negócio.

Nenhum campo foi escolhido.

## Seção 3 — DECISION-002

## DECISION-002 — Ciclo Comercial Oficial

Status:
PENDING

CYCLE_CONTRACT = PENDING

Perguntas:

1. Qual ciclo aparece no KPI histórico?
2. Qual ciclo aparece no Realtime?
3. Devem continuar separados?
4. Qual unidade?

Os dois conceitos identificados no código atual são:

lead_conversion_cycle:

leads.created_at
→
leads.converted_at

sale_completion_cycle:

leads.created_at
→
property_sales.created_at

O código atual comprova dois conceitos distintos, mas não define qual é o conceito de negócio oficial.

Não foi escolhida nenhuma regra.

## Seção 4 — DECISION-003

## DECISION-003 — Imóvel Parado

Status:
PENDING

OFFICIAL_STALLED_PROPERTY_RULE = PENDING
STALLED_PROPERTY_DAYS = PENDING
MOVEMENT_EVENTS = PENDING

As duas regras históricas encontradas na auditoria são:

- properties.created_at + cutoff
- property_last_movement.days_without_movement

A auditoria não autoriza considerar nenhuma das duas como regra oficial.

Não foi escolhido nenhum evento de movimento.

## Seção 5 — DECISION-004

## DECISION-004 — Escopo Administrativo

Status:
PENDING

ADMIN_SCOPE = PENDING
SUPERADMIN_SCOPE = PENDING
MANAGER_SCOPE = PENDING
USER_SCOPE = PENDING

Perguntas:

- Admin é tenant-wide?
- Superadmin é global ou tenant-selected?
- Manager é tenant-wide ou user-scoped?
- User é sempre user-scoped?
- Qual deve ser o comportamento no Realtime?

A implementação atual não deve ser tomada como contrato definitivo.

Não alterar AuthContext.
Não alterar TenantResolver.
Não alterar autorização.

## Seção 6 — DECISION-005

## DECISION-005 — Contrato de Brokers

Status:
PENDING

BROKER_CONTRACT = PENDING

Documentação dos contratos existentes apenas como evidência:

KPI:

- id
- name
- vendas
- receita
- leads
- taxa_conversao

Realtime:

- name
- sales_count
- total_revenue

Não está autorizado tratar esses payloads como equivalentes.

## Seção 7 — DECISION-006

## DECISION-006 — Total Properties no Realtime

Status:
PENDING

REALTIME_TOTAL_PROPERTIES = PENDING

Pergunta:

O número representa:

- estoque atual;
- atividade no período;
- ou duas métricas distintas?

Não foi escolhida nenhuma interpretação.

## Seção 8 — DECISION-007

## DECISION-007 — Total Leads no Realtime

Status:
PENDING

REALTIME_TOTAL_LEADS = PENDING

Pergunta:

Leads sem imóvel relacionado:

- entram;
- não entram;
- ou devem existir métricas separadas?

Evidência registrada:

O Realtime atual utiliza JOIN com properties e, portanto, a implementação atual não deve ser automaticamente tratada como regra de negócio definitiva.

## Seção 9 — DECISION-008

## DECISION-008 — Valor Financeiro / Receita

Status:
PENDING

OFFICIAL_SALE_VALUE = PENDING
OFFICIAL_REVENUE_VALUE = PENDING

Perguntas:

1. Receita é soma do valor bruto das vendas?
2. Comissão entra?
3. Desconto entra?
4. Cancelamento entra?
5. Qual tabela representa o evento financeiro oficial?

Registro conceitual:

sale value
≠
revenue
≠
commission
≠
payment

salvo decisão explícita do negócio.

Não foi feita nenhuma unificação.

## Seção 10 — DECISION-009

## DECISION-009 — Total Visits

Status:
PENDING

TOTAL_VISITS_CONTRACT = PENDING

Perguntas:

- site visits?
- property visits?
- ambos?
- qual unidade?
- sessão?
- visita única?
- page view?

A auditoria encontrou evidências relacionadas a site_visits e property_id, mas isso não define sozinho a regra oficial.

## Seção 11 — DECISION-010

## DECISION-010 — Today Conversions

Status:
PENDING

TODAY_CONVERSIONS_CONTRACT = PENDING
TODAY_WINDOW = PENDING

Perguntas:

1. Conversão significa status converted?
2. Conversão significa converted_at preenchido?
3. Conversão significa venda vinculada?
4. “Hoje” significa:
    - últimas 24 horas
    - dia civil atual 00:00 → 23:59
    - outra janela?

O código atual usa converted_at e janela de 24 horas no Realtime, mas isso é evidência histórica e não decisão de negócio.

## Seção 12 — Matriz Consolidada

| ID  | Decisão                    | Status  | Implementação liberada? |
| --- | -------------------------- | ------- | ----------------------- |
| 001 | Valor oficial de venda     | PENDING | NÃO                     |
| 002 | Ciclo comercial            | PENDING | NÃO                     |
| 003 | Imóvel parado              | PENDING | NÃO                     |
| 004 | Escopo administrativo      | PENDING | NÃO                     |
| 005 | Brokers                    | PENDING | NÃO                     |
| 006 | Total properties realtime  | PENDING | NÃO                     |
| 007 | Total leads realtime       | PENDING | NÃO                     |
| 008 | Receita / valor financeiro | PENDING | NÃO                     |
| 009 | Total visits               | PENDING | NÃO                     |
| 010 | Today conversions          | PENDING | NÃO                     |

## Seção 13 — Gate de Implementação

## Implementation Gate

STATUS:
BLOCKED_BY_BUSINESS_DECISION

A implementação de KPI/Realtime permanece bloqueada até que as decisões de negócio necessárias sejam preenchidas.

Não substituir PENDING por inferência técnica.
Não selecionar automaticamente a regra atualmente usada pelo código.
Não considerar a facilidade de implementação como critério de decisão.

## Seção 14 — Regras para a Futur a Implementação

Quando as decisões forem preenchidas, o próximo fluxo será:

1. consolidar contrato;
2. criar/atualizar testes de contrato;
3. implementar repositories/services;
4. adaptar endpoints;
5. preservar compatibilidade dos consumidores;
6. implementar/adaptar Realtime;
7. validar segurança/tenant;
8. executar regressão;
9. auditoria final READ-ONLY;
10. commit cirúrgico;
11. push somente após autorização.

## Seção 15 — Proibições

Enquanto houver decisões PENDING:

- não implementar;
- não escolher automaticamente;
- não alterar schema;
- não alterar payload;
- não remover campos legados;
- não renomear métricas;
- não substituir KPI pelo Realtime;
- não substituir Realtime pelo KPI;
- não alterar AuthContext;
- não alterar TenantResolver;
- não alterar regras de tenant;
- não fazer migration.

## Validação

Depois de criar o documento:

1. verificar o conteúdo;
2. executar git diff --check;
3. executar git status --short;
4. confirmar:
    - somente o novo documento pertence a este lote;
    - nenhuma alteração de produção;
    - nenhum banco alterado;
    - nenhum frontend alterado;
    - nenhum teste alterado;
    - nenhuma alteração pré-existente removida.

Não foi feito commit ainda.
