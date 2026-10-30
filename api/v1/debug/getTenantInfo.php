<?php

/**
 * API de Debug - Verifica qual tenant_id está sendo usado
 * GET /api/v1/debug/getTenantInfo.php
 */

require_once dirname(dirname(dirname(__DIR__))) . '/app/bootstrap.php';

use App\Http\Response;
use App\Service\TenantInfoService;

try {
    api_v1_require_auth();
    api_v1_resolve_tenant();

    $service = new TenantInfoService();
    $tenantInfo = $service->getTenantInfo();

    Response::success($tenantInfo, 'Tenant resolvido com sucesso');
} catch (Throwable $e) {
    Response::serverError('Erro ao processar debug de tenant');
}
