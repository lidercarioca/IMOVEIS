<?php
header("Content-Type: application/json");

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/env_loader.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/InputValidator.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

use App\Observability\RequestTracker;
use App\Security\RateLimitPolicy;
use App\Security\RateLimiter;
use App\Security\TenantContext;
use App\Service\UtmService;

try {
    $tenantId = null;
    if (class_exists(TenantContext::class)) {
        TenantContext::initialize();
        $tenantId = TenantContext::getTenantId();
    } elseif (class_exists('TenantContext')) {
        \TenantContext::initialize();
        $tenantId = \TenantContext::getTenantId();
    }

    $clientIp = RequestTracker::resolveClientIp();
    if ($clientIp === null) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Não foi possível validar sua conexão. Tente novamente.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rateIdentifier = $clientIp;
    if ($tenantId !== null && (int) $tenantId > 0) {
        $rateIdentifier = 'tenant_' . (int) $tenantId . ':utm_tracking';
    }

    $endpoint = 'utm_tracking';
    $config = RateLimitPolicy::getConfig($endpoint);
    $limit = (int) $config['limit'];
    $window = (int) $config['window'];
    $resolvedIdentifier = $endpoint . ':' . $rateIdentifier;
    $rateLimiter = new RateLimiter();
    $headerInfo = $rateLimiter->getHeaderInfo($resolvedIdentifier, $limit, $window);
    if (!$rateLimiter->isAllowed($resolvedIdentifier, $limit, $window)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Muitas requisições. Aguarde alguns segundos.',
            'retry_after' => $headerInfo['reset_in']
        ]);
        exit;
    }

    if (isLoggedIn()) {
        initializeTenantMiddleware($pdo);
        $resolvedTenantId = getCurrentTenantId();
        if ((int) $resolvedTenantId > 0) {
            $tenantId = (int) $resolvedTenantId;
        }
    }

    if ($tenantId === null || (int) $tenantId <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Tenant não identificado para rastreamento UTM.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = [
        'utm_source' => isset($_GET['utm_source']) ? InputValidator::getString($_GET['utm_source'], 'UTM Source', false, 200) : null,
        'utm_medium' => isset($_GET['utm_medium']) ? InputValidator::getString($_GET['utm_medium'], 'UTM Medium', false, 200) : null,
        'utm_campaign' => isset($_GET['utm_campaign']) ? InputValidator::getString($_GET['utm_campaign'], 'UTM Campaign', false, 200) : null,
        'utm_content' => isset($_GET['utm_content']) ? InputValidator::getString($_GET['utm_content'], 'UTM Content', false, 200) : null,
        'utm_term' => isset($_GET['utm_term']) ? InputValidator::getString($_GET['utm_term'], 'UTM Term', false, 200) : null,
        'destination_url' => InputValidator::getUrl($_GET['url'] ?? '', 'URL de destino', true),
    ];

    $userIp = $clientIp;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';

    $service = new \App\Service\UtmService($pdo);
    $tracking = $service->trackClick($tenantId, $payload, $userIp, $userAgent, $referrer);

    $_SESSION['utm_tracking_id'] = $tracking['tracking_id'];
    $_SESSION['utm_source'] = $tracking['source'];
    $_SESSION['utm_medium'] = $tracking['medium'];
    $_SESSION['utm_campaign'] = $tracking['campaign'];
    if (isset($payload['utm_content'])) {
        $_SESSION['utm_content'] = $payload['utm_content'];
    }
    if (isset($payload['utm_term'])) {
        $_SESSION['utm_term'] = $payload['utm_term'];
    }

    if (isset($_GET['json']) && $_GET['json'] == '1') {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Clique rastreado com sucesso",
            "data" => [
                "tracking_id" => $tracking['tracking_id'],
                "campaign" => $tracking['campaign'],
                "source" => $tracking['source'],
                "medium" => $tracking['medium']
            ]
        ]);
    } else {
        header("Location: " . urldecode($payload['destination_url']));
        exit;
    }
} catch (Throwable $e) {
    error_log("Erro ao rastrear clique UTM: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Erro ao rastrear clique",
        "error" => $e->getMessage()
    ]);
}
