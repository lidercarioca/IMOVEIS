<?php

/**
 * GET /api/v1/leads/getInactiveLeads.php
 * 
 * Detecta leads inativos (sem contato há X dias) usando lead_interactions
 * Query params:
 * - days: número de dias sem contato (padrão: 7)
 * - limit: máximo de leads para retornar (padrão: 20)
 * - user_id: filtrar por usuário atribuído (opcional)
 * 
 * Retorna: lista de leads inativos com última interação e recomendações
 */

header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(dirname(__DIR__))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/security/TenantContext.php';

use App\Application\Lead\LeadService;
use App\Infrastructure\Lead\LeadRepository;
use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

AuthMiddleware::handle();
$tenantId = TenantMiddleware::resolveTenantId();

try {
    // Use AuthContext as the exclusive source of identity and authorization.
    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin();

    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $filterUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

    if ($days < 1 || $days > 365) {
        throw new Exception('Parâmetro days deve estar entre 1 e 365');
    }
    if ($limit < 1 || $limit > 500) {
        throw new Exception('Parâmetro limit deve estar entre 1 e 500');
    }

    $repository = new LeadRepository($GLOBALS['pdo']);
    $service = new LeadService($repository);

    $result = $service->getInactiveLeads($tenantId, $days, $limit, $filterUserId, $userId, $isAdmin);

    echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
