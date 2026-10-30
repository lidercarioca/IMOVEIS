<?php
// api/trackVisit.php - Rastreia visitas do site
header('Content-Type: application/json');
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';

try {
    // Obtém informações do visitante
    $visitor_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
    $visitor_ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

    // Se veio JSON, processa
    $body = json_decode(file_get_contents('php://input'), true);
    $page_url = '/';
    $referrer = $_SERVER['HTTP_REFERER'] ?? null;

    if (!empty($body) && is_array($body)) {
        if (!empty($body['page'])) {
            $page_url = parse_url($body['page'], PHP_URL_PATH) ?: '/';
        }
        if (!empty($body['referrer'])) {
            $referrer = $body['referrer'];
        }
    } else {
        $page_url = parse_url($_REQUEST['page'] ?? $_SERVER['HTTP_REFERER'] ?? '/', PHP_URL_PATH);
    }

    $session_id = session_id();
    $tenant_id = getRequestTenantId();

    $service = new \App\Service\SiteVisitService($pdo);
    $tracked = $service->trackVisit([
        'tenant_id' => $tenant_id,
        'visitor_ip' => $visitor_ip,
        'visitor_ua' => $visitor_ua,
        'page_url' => $page_url,
        'referrer' => $referrer,
        'session_id' => $session_id,
    ]);

    echo json_encode(['success' => true, 'tracked' => $tracked]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
