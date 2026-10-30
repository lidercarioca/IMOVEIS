<?php

/**
 * API endpoint para buscar banners ativos do sistema
 * Retorna a lista de banners ordenados por posição e ID COM ISOLAMENTO DE TENANT
 * 
 * @return JSON Lista de banners com suas informações
 */

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

header('Content-Type: application/json');

try {
    $tenantId = getRequestTenantId();
    $bannerService = new \App\Service\BannerService($pdo);
    $banners = $bannerService->listBanners($tenantId);

    echo json_encode($banners);
} catch (Exception $e) {
    error_log("Erro ao buscar banners: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar banners'
    ]);
}
