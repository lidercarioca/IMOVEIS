<?php

use App\Services\AlertGeneratorService;
use App\Services\AlertService;

/**
 * GET /api/v1/alertas/getAlerts.php
 * 
 * Obtém lista de alertas com filtros
 * Usa AlertGeneratorService para consistência com dashboard
 * Query params:
 * - status: pending|acknowledged|resolved|dismissed (opcional)
 * - severity: low|medium|high|critical (opcional)
 * - alert_type: inactive_lead|inactive_property|custom (opcional)
 * - page: número da página (padrão: 1)
 * - limit: registros por página (padrão: 20)
 */

header('Content-Type: application/json');

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config/database.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/auth.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/bootstrap.php';
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/app/helpers/tenant_isolation_helpers.php';

checkAuth();

try {
    global $pdo;

    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin();

    $alertGenerator = new AlertGeneratorService($pdo, $userId, $isAdmin, $tenantId);
    $alertGenerator->generateAndPersistAlerts();

    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(intval($_GET['limit']), 100) : 20;
    $statusFilter = $_GET['status'] ?? 'todos';
    $severityFilter = $_GET['severity'] ?? null;
    $alertTypeFilter = $_GET['alert_type'] ?? null;

    $service = new AlertService($pdo);
    $result = $service->getAlerts($tenantId, $userId, [
        'status' => $statusFilter,
        'severity' => $severityFilter,
        'alert_type' => $alertTypeFilter
    ], $page, $limit);

    http_response_code(200);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (ApiAuthException $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar alertas',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
