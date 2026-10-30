<?php
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/middleware/api_auth.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/AuthMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/api/v1/middleware/TenantMiddleware.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

// Fluxo canônico do projeto: identidade autenticada via AuthContext e tenant via TenantContext/TenantMiddleware.
\Api\V1\Middleware\AuthMiddleware::handle();
$tenantId = \Api\V1\Middleware\TenantMiddleware::resolveTenantId();

header('Content-Type: application/json');

/**
 * Helper: Calcula o período anterior para comparação
 */
function calcularPeriodoAnterior($dataInicio, $diasPeriodo)
{
    $dataFimAnterior = date('Y-m-d', strtotime($dataInicio . " -1 day"));
    $dataInicioAnterior = date('Y-m-d', strtotime($dataFimAnterior . " -{$diasPeriodo} days"));
    return [$dataInicioAnterior, $dataFimAnterior];
}

/**
 * Helper: Calcula variação percentual
 */
function calcularVariacao($valorAtual, $valorAnterior)
{
    if ($valorAnterior == 0) {
        return ($valorAtual > 0) ? 100 : 0;
    }
    return round((($valorAtual - $valorAnterior) / $valorAnterior) * 100, 2);
}

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();
    $period = (int)($_GET['period'] ?? 7);

    if (!$userId && !$isAdmin) {
        throw new Exception('Sessão não inicializada - user_id não encontrado');
    }

    $reportService = new \App\Service\ReportService($pdo);
    $payload = $reportService->getDashboardKPIs([
        'tenantId' => $tenantId,
        'period' => $period,
        'userId' => $userId,
        'isAdmin' => $isAdmin,
    ]);

    echo json_encode($payload);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro em getDashboardKPIs: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar KPIs do dashboard',
        'details' => $e->getMessage()
    ]);
}
