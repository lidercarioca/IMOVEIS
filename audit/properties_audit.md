# Auditoria: consultas à tabela `properties`

Observações iniciais:

- Objetivo: identificar locais que consultam `properties` e verificar se mostram `pending` a usuários comuns.
- Fonte: busca por "FROM properties", "assigned_user_id" e "status" no workspace.
- Status: leitura apenas — nenhum arquivo do código foi alterado.

## Resumo executivo (prioridade alta)

- Endpoints CRÍTICOS que retornam `pending` para usuário comum:
    - [api/v1/properties/getPropertiesSimple.php](api/v1/properties/getPropertiesSimple.php#L1): usa SQL direto, `status IN ('active', 'pending')` → CRÍTICO (não-admin pode ver pending)
    - [app/controllers/PropertiesController.php](app/controllers/PropertiesController.php#L1): `SELECT * FROM properties WHERE tenant_id = ?` (sem filtro de status) → CRÍTICO
    - [api/v1/public/properties.php](api/v1/public/properties.php#L1): listagem pública sem filtro por padrão (aceita `status` param) → CRÍTICO (padrão pode expor pending)

- Serviços/implementações centrais:
    - [src/Service/PropertyService.php](src/Service/PropertyService.php#L1): Lógica centralizada; para `isPanel` aplica `p.status = 'active'` para não-admins → OK (serviço protege corretamente quando usado)
    - [src/Repository/PropertyRepository.php](src/Repository/PropertyRepository.php#L1): Repository executa WHERE recebido do Service; por si só não impõe filtros → OK (esperado)

- Frontend relevante:
    - [views/admin/adicionar-imovel.php](views/admin/adicionar-imovel.php#L1): atualmente substitui select por input hidden para não-admin — precisa reverter conforme solicitado → CRÍTICO (quebra de contrato UI/JS)
    - [assets/js/painel/adicionar-imovel.js](assets/js/painel/adicionar-imovel.js#L1): manipula `assigned_user_id` e depende de select existir; foi ajustado para só carregar options para admin → AJUSTAR (precisa suportar select disabled + hidden input)

## Lista A — arquivos que executam SELECT em `properties` (endpoints e scripts)

(arquivo — função/responsável — SQL direto ou via Service — filtra `assigned_user_id`? — filtra `status`? — permite pending para não-admin? — classificação)

- [api/v1/properties/getPropertiesSimple.php](api/v1/properties/getPropertiesSimple.php#L1) — endpoint GET simples — SQL direto — filtra `assigned_user_id` para não-admin — filtra `status IN ('active','pending')` — NÃO-admin vê `pending` → CRÍTICO
- [app/controllers/PropertiesController.php](app/controllers/PropertiesController.php#L1) — `listProperties()` — SQL direto — filtra `assigned_user_id` para não-admin — NÃO filtra por `status` — NÃO-admin pode ver `pending` → CRÍTICO
- [api/v1/public/properties.php](api/v1/public/properties.php#L1) — handler público (GET list) — SQL direto via `PropertyModel` — aceita `status` param; sem parâmetro, não filtra → por padrão pode expor `pending` → CRÍTICO
- [api/relatorios/getActivityFeed.php](api/relatorios/getActivityFeed.php#L1) — relatório — SQL direto com joins em `properties p` — filtra `assigned_user_id` condicionalmente — revisar exposição de `status` → AJUSTAR
- [api/relatorios/exportPDF.php](api/relatorios/exportPDF.php#L1) — relatório/export — SQL direto (`FROM properties p`) — revisar filtros — AJUSTAR
- [api/relatorios/exportExcel.php](api/relatorios/exportExcel.php#L1) — relatório/export — SQL direto — AJUSTAR
- [api/relatorios/exportDashboard.php](api/relatorios/exportDashboard.php#L1) — relatório — SQL direto — AJUSTAR
- [api/relatorios/getDashboardCharts.php](api/relatorios/getDashboardCharts.php#L1) — relatório — SQL direto — AJUSTAR
- [api/relatorios/getPortfolioStats.php](api/relatorios/getPortfolioStats.php#L1) — relatório — usa `AND assigned_user_id = ?` condicional — pode expor pending dependendo do `status` usado — AJUSTAR
- [api/v1/properties/getPropertyById.php](api/v1/properties/getPropertyById.php#L1) — endpoint GET por id — SQL direto com tenant_id check — não filtra `status` → AJUSTAR (deveria bloquear pending para não-admins se necessário)
- [api/v1/properties/getPropertyImages.php](api/v1/properties/getPropertyImages.php#L1) — verifica propriedade por id — ok para tenant check — AJUSTAR
- [src/Repository/PropertyRepository.php](src/Repository/PropertyRepository.php#L1) — Repository — executa WHERE recebido (usa QueryBuilder) — NÃO aplica filtros de negócio — OK

> Nota: a lista acima contém os arquivos mais críticos/centrais inspecionados. Há muitas outras ocorrências (consultas em scripts de relatório, jobs, testes). Ver seção "Hits brutos" abaixo.

## Lista B — arquivos que usam `assigned_user_id` (PHP/JS)

- [views/admin/adicionar-imovel.php](views/admin/adicionar-imovel.php#L200) — form, atualmente substitui select para não-admin — AJUSTAR (reverter para select disabled + hidden)
- [assets/js/painel/adicionar-imovel.js](assets/js/painel/adicionar-imovel.js#L1) — popula select e envia `assigned_user_id` no FormData — AJUSTAR (suportar select disabled)
- [assets/js/painel/painel-main.js](assets/js/painel/painel-main.js#L340) — exibe assigned_user via fetch(`/api/v1/users/show.php?id=...`) — OK
- [assets/js/painel/leads-pagination.js](assets/js/painel/leads-pagination.js#L370) — manipula assigned_user_id em leads — OK
- API `api/v1/properties/addProperty.php` — recebe `assigned_user_id` mas ignora para não-admin (server-side) — OK
- Vários relatórios e services (AlertGeneratorService, LeadRepository, etc.) usam `assigned_user_id` para filtros/joins — precisam de revisão de visibilidade (AJUSTAR)

## Lista C — arquivos que usam `status` em queries/filters

- [api/v1/properties/getPropertiesSimple.php](api/v1/properties/getPropertiesSimple.php#L1) — `status IN ('active','pending')` — CRÍTICO
- [src/Service/PropertyService.php](src/Service/PropertyService.php#L1) — aplica `p.status = 'active'` para não-admin no painel — OK
- [api/v1/public/properties.php](api/v1/public/properties.php#L1) — aceita `status` param; sem parâmetro não filtra → CRÍTICO
- Relatórios: [api/relatorios/getPortfolioStats.php](api/relatorios/getPortfolioStats.php#L1), [api/relatorios/getPropertiesParados.php](api/relatorios/getPropertiesParados.php#L1) e outros — normalmente filtram por `status` em agregações — AJUSTAR (verificar se usam `pending` indevidamente)

## Lista D — arquivos JS que manipulam `assigned_user_id`

- [assets/js/painel/adicionar-imovel.js](assets/js/painel/adicionar-imovel.js#L1)
- [assets/js/painel/painel-main.js](assets/js/painel/painel-main.js#L1)
- [assets/js/painel/leads-pagination.js](assets/js/painel/leads-pagination.js#L1)
- [assets/js/painel/pipeline.js](assets/js/painel/pipeline.js#L1)

## Controllers / Services / Repositories envolvidos

- Controllers: [app/controllers/PropertiesController.php](app/controllers/PropertiesController.php#L1) — CRÍTICO (sem filtro status)
- Services: [src/Service/PropertyService.php](src/Service/PropertyService.php#L1) — OK (aplica status='active' para não-admin no painel)
- Repositories: [src/Repository/PropertyRepository.php](src/Repository/PropertyRepository.php#L1) — OK (executa WHERE do Service)
- Models (legacy): [app/models/PropertyModel.php](app/models/PropertyModel.php#L1) — usa SQL direto em várias rotas — AJUSTAR
- Lead repos/services: [src/Infrastructure/Lead/LeadRepository.php](src/Infrastructure/Lead/LeadRepository.php#L1) — faz subqueries em `properties` para ownership checks — AJUSTAR para garantir visibilidade

## Hits brutos (saída do grep)

- Foram localizadas ~110 ocorrências de "FROM properties" no repositório. Exemplos: `check_status_distribution.php`, `app/controllers/PropertiesController.php`, muitos arquivos em `api/relatorios/*`, `app/services/*`, `api/v1/*`, `src/*`.
- Foram localizadas centenas de ocorrências de `assigned_user_id` e `status` em endpoints, services e JS.

## Recomendações (próximos passos mínimos e centralizados)

1. Reverter frontend conforme solicitado:
    - Em [views/admin/adicionar-imovel.php](views/admin/adicionar-imovel.php#L200) restaurar o `<select id="assigned_user_id">` para todos, mas para usuários comuns renderizar `<select disabled>` contendo apenas a opção do usuário atual **e** adicionar um `<input type="hidden" name="assigned_user_id" value="CURRENT_USER_ID">` para compatibilidade JS.
    - Ajustar `assets/js/painel/adicionar-imovel.js` para não assumir que `#assigned_user_id` é um input hidden; aceitar select disabled e garantir `formData.set('assigned_user_id', hiddenValue)`.

2. Centralizar autorização/visibilidade no backend:
    - Preferência técnica: consolidar visibilidade no `PropertyService::getProperties()` e fazer com que todos os endpoints de listagem (painel e públicos) chamem esse service em vez de SQL direto.
    - Como patch mínimo: onde não for possível refatorar ao Service, aplicar um wrapper helper `apply_property_visibility_filters(&$sql, &$params, $isAdmin, $userId)` (no `app/helpers/tenant_isolation_helpers.php`) que injete `AND assigned_user_id = ? AND status = 'active'` para não-admins. Chamar esse helper em endpoints legados identificados como CRÍTICO.

3. Corrigir endpoints críticos agora:
    - `api/v1/properties/getPropertiesSimple.php` → forçar `status = 'active'` para não-admins (em vez de `IN ('active','pending')`).
    - `app/controllers/PropertiesController.php` → adicionar filtro `status='active'` para não-admins.
    - `api/v1/public/properties.php` → alterar comportamento padrão para excluir `pending` a menos que chamado com token/admin.

4. Auditoria de verificação pós-patch (automática):
    - Executar script que refaz buscas por `FROM properties` e valida que nenhum endpoint público ou de painel monta uma query que inclua `IN ('pending')` ou não contenha filtro `status` e que não chame `PropertyService::getProperties()`.
    - Testes manuais rápidos: criar imóvel como usuário comum (via form) e confirmar resposta e listagem do painel público e painel privado; criar imóvel como admin e confirmar visibilidade.

## Próximo passo que posso executar (aguardando sua confirmação)

- Gerar aqui mesmo patches cirúrgicos apenas nos arquivos CRÍTICOS listados (frontend + `getPropertiesSimple.php` + `app/controllers/PropertiesController.php` + `api/v1/public/properties.php`) e depois rodar a auditoria de verificação automática.
- Ou apenas gerar a auditoria completa detalhada para todos os ~110 arquivos (mais demorado), listando por arquivo os campos solicitados em detalhe.

---

Arquivo gerado automaticamente em: `audit/properties_audit.md`.
Se quiser que eu prossiga aplicando as correções mínimas e centralizadas, confirme e eu seguirei com patches cirúrgicos (um de cada vez), realizando as verificações automáticas solicitadas antes de finalizar.
