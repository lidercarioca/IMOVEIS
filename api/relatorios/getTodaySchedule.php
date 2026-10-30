<?php

/**
 * Today Schedule API
 * Retorna agendamentos de hoje com status, comparecimento, etc
 * COM ISOLAMENTO DE TENANT_ID
 */

require_once '../../config/session_manager.php';
require_once '../../auth.php';
require_once '../../config/database.php';
require_once '../../app/utils/error_logger.php';
require_once '../../app/helpers/tenant_isolation_helpers.php';
require_once '../../app/middleware/api_auth.php';

checkAuth();
checkApiAuth();

global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json');

try {
    $userId = \App\Auth\AuthContext::getInstance()->getUserId();
    $isAdmin = \App\Auth\AuthContext::getInstance()->isAdmin();

    $service = new \App\Service\AgendamentoService($pdo);
    $result = $service->getTodayScheduleSummary($tenantId, $userId, $isAdmin);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (SessionAuthException | ApiAuthException $e) {
    $context = [
        'user_id' => \App\Auth\AuthContext::getInstance()->getUserId(),
        'is_admin' => \App\Auth\AuthContext::getInstance()->isAdmin(),
        'auth_error' => $e->getMessage(),
    ];
    log_exception($e, $context);

    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado',
        'details' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // Log detalhado para investigação (arquivo em logs/relatorios_errors.log)
    $context = [
        'user_id' => \App\Auth\AuthContext::getInstance()->getUserId(),
        'is_admin' => \App\Auth\AuthContext::getInstance()->isAdmin()
    ];
    log_exception($e, $context);

    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Erro ao carregar agendamentos'
    ];

    // Em ambiente de desenvolvimento, inclua detalhes no JSON (útil localmente)
    if (getenv('ENVIRONMENT') === 'development') {
        $response['details'] = $e->getMessage();
        $response['trace'] = $e->getTraceAsString();
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
