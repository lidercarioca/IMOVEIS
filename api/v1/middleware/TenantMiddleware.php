<?php

namespace Api\V1\Middleware;

class TenantMiddleware
{
    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Usar adapter de compatibilidade que delega ao novo TenantResolver
        require_once __DIR__ . '/../../../app/security/TenantContext.php';
        \TenantContext::initialize();
    }

    public static function resolveTenantId(): int
    {
        // Garantir sessão e inicializar contexto/adapter
        self::ensureSession();

        // Obter tenant a partir do adapter compat (que usa TenantResolver internamente)
        $tenantId = \TenantContext::getTenantId();

        if ($tenantId === null || intval($tenantId) <= 0) {
            self::respondForbidden('Tenant não identificado');
        }

        return intval($tenantId);
    }

    public static function respondForbidden(string $message = 'Tenant não identificado'): void
    {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'code' => 'tenant_not_resolved'
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
