<?php

namespace App\Service;

class TenantInfoService
{
    public function getTenantInfo(): array
    {
        return [
            'tenant_id' => $_SERVER['TENANT_ID'] ?? null,
            'session_tenant_id' => $_SESSION['tenant_id'] ?? null,
            'server_tenant_id' => $_SERVER['TENANT_ID'] ?? null,
            'session_user_id' => $_SESSION['user_id'] ?? null,
            'session_role' => $_SESSION['role'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
        ];
    }
}
