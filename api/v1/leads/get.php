<?php

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

use App\Application\Lead\LeadService;
use App\Infrastructure\Lead\LeadRepository;
use App\Http\Response;
use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

AuthMiddleware::handle();
$tenantId = TenantMiddleware::resolveTenantId();

// Use AuthContext as the exclusive source of identity and authorization.
$authContext = \App\Auth\AuthContext::getInstance();
$userId = $authContext->getUserId();
$isAdmin = $authContext->isAdmin();

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
$limit = min($limit, 1000);

$leadIdFromQuery = isset($_GET['lead_id']) ? filter_var($_GET['lead_id'], FILTER_VALIDATE_INT) : false;

$queryParams = [
    'status' => trim((string)($_GET['status'] ?? 'all')),
    'q' => trim((string)($_GET['q'] ?? '')),
    'period' => trim((string)($_GET['period'] ?? 'all')),
];

$repository = new LeadRepository($GLOBALS['pdo']);
$service = new LeadService($repository);

try {
    if ($leadIdFromQuery !== false && $leadIdFromQuery > 0) {
        $leadId = (int)$leadIdFromQuery;
        if (!$isAdmin && !$repository->canUserAccessLead($tenantId, $leadId, $userId)) {
            Response::forbidden('Acesso negado ao lead');
        }

        $lead = $service->getLead($tenantId, $leadId);
        if ($lead === null) {
            Response::error('Lead não encontrado', 404);
        }

        Response::success($lead->toArray(), 'Lead carregado com sucesso', 200, false);
    }

    $result = $service->listLeads($tenantId, $userId, $isAdmin, $queryParams, $page, $limit);
    Response::success(
        $result['data'],
        '',
        200,
        false,
        [
            'pagination' => $result['pagination'],
            'tenant_isolation' => $result['tenant_isolation'],
        ]
    );
} catch (Throwable $throwable) {
    error_log('Lead list error: ' . $throwable->getMessage());
    Response::serverError('Erro ao carregar leads');
}
