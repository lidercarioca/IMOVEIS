<?php

/**
 * Alerts Feed API
 * Retorna alertas ativos do sistema (propriedades com problemas, vendas paradas, etc)
 * Usa AlertGeneratorService para garantir consistência com a aba de Alertas
 * COM ISOLAMENTO DE TENANT_ID
 */

// Usar __DIR__ para caminho absoluto (funciona em CLI e HTTP)
$rootPath = dirname(dirname(dirname(__FILE__)));
require_once $rootPath . '/vendor/autoload.php';
require_once $rootPath . '/auth.php';
require_once $rootPath . '/config/database.php';
require_once $rootPath . '/app/helpers/tenant_isolation_helpers.php';
require_once $rootPath . '/app/middleware/api_auth.php';

checkAuth();
checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

header('Content-Type: application/json');

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $userId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();

    // Usar o serviço centralizado para gerar alertas
    $alertGenerator = new \App\Services\AlertGeneratorService($pdo, $userId, $isAdmin, $tenantId);
    $alerts = $alertGenerator->generateAllAlerts();

    // =====================
    // Processar retorno dos alertas
    // =====================

    // Ordenar por severidade (critical > warning > success > info)
    $severityOrder = ['critical' => 0, 'warning' => 1, 'success' => 2, 'info' => 3];
    usort($alerts, function ($a, $b) use ($severityOrder) {
        $severityA = $severityOrder[$a['severity']] ?? 999;
        $severityB = $severityOrder[$b['severity']] ?? 999;
        return $severityA <=> $severityB;
    });

    $critical_alerts_count = count(array_filter($alerts, function ($a) {
        return $a['severity'] === 'critical';
    }));
    echo json_encode([
        'success' => true,
        'data' => array_slice($alerts, 0, 5),
        'total_alerts' => count($alerts),
        'has_critical' => $critical_alerts_count > 0,
        'critical_alerts_count' => $critical_alerts_count
    ]);
} catch (ApiAuthException $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Não autorizado',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao carregar alertas',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
