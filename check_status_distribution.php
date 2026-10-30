<?php
require 'config/database.php';
require 'app/middleware/api_auth.php';
require 'app/helpers/tenant_isolation_helpers.php';
require 'auth.php';
require 'vendor/autoload.php';

checkApiAuth();
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

$reportService = new \App\Service\ReportService($pdo);
$statuses = $reportService->getPropertiesByStatusReport($tenantId);

echo "=== DISTRIBUIÇÃO DE STATUS NOS IMÓVEIS ===\n\n";
foreach ($statuses as $s) {
    echo $s['status'] . ": " . $s['count'] . " imóvel(ns)\n";
}
