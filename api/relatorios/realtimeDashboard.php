<?php
require_once dirname(dirname(__DIR__)) . '/api/check_auth.php';
checkApiAuth();
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(__DIR__)) . '/app/middleware/api_auth.php';

checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

// Server-Sent Events para atualização em tempo real
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

$userId = \App\Auth\AuthContext::getInstance()->getUserId();
$period = (int)($_GET['period'] ?? 7);

if (!$userId) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Unauthorized']) . "\n\n";
    exit;
}

$reportRepository = new \App\Repository\ReportRepository($pdo);
$reportService = new \App\Service\ReportService($pdo, $reportRepository);

function sendSSEEvent($eventName, $data)
{
    echo "event: {$eventName}\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

$lastDataHash = null;
$connectionTimeout = time() + 300;

while (time() < $connectionTimeout) {
    try {
        $snapshot = $reportService->getRealtimeDashboardSnapshot($tenantId, $userId, $period);
        $data = [
            'kpis' => $snapshot['kpis'],
            'brokers' => $snapshot['brokers'],
            'recentLeads' => $snapshot['recentLeads'],
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $dataHash = md5(json_encode($data));

        if ($dataHash !== $lastDataHash) {
            sendSSEEvent('dashboard_update', $data);
            sendSSEEvent('kpi_update', $snapshot['kpis']);
            sendSSEEvent('brokers_update', $snapshot['brokers']);
            sendSSEEvent('leads_update', $snapshot['recentLeads']);
            $lastDataHash = $dataHash;
        } else {
            sendSSEEvent('ping', ['timestamp' => date('Y-m-d H:i:s')]);
        }

        sleep(5);
    } catch (Exception $e) {
        sendSSEEvent('error', ['message' => $e->getMessage()]);
        break;
    }
}
