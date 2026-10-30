<?php

/**
 * admin/reset_rate_limit.php
 * Ferramenta de admin para resetar rate limits específicos ou todos
 * Uso: /admin/reset_rate_limit.php?endpoint=contact_form&email=teste@email.com
 *      /admin/reset_rate_limit.php?action=cleanup
 *      /admin/reset_rate_limit.php?action=clear_all
 */

// Segurança básica - apenas em ambiente de desenvolvimento
if ($_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1' && $_SERVER['HTTP_HOST'] !== 'localhost:80') {
    http_response_code(403);
    die("Acesso negado - apenas localhost");
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Security/RateLimiter.php';
require_once dirname(__DIR__) . '/src/Security/RateLimitPolicy.php';

header("Content-Type: application/json");

try {
    $rateLimiter = new \App\Security\RateLimiter();
    $action = $_GET['action'] ?? '';
    $endpoint = strtolower(trim((string)($_GET['endpoint'] ?? '')));
    $identifier = trim((string)($_GET['identifier'] ?? $_GET['email'] ?? ''));

    if ($action === 'cleanup') {
        $removed = $rateLimiter->cleanupExpiredBuckets();
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Buckets expirados do cache de rate limit limpos",
            "removed" => $removed,
        ]);
    } elseif ($action === 'clear_all') {
        $cleared = $rateLimiter->clearAllBuckets();
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Todos os buckets de rate limit foram resetados",
            "cleared" => $cleared,
        ]);
    } elseif ($endpoint && $identifier) {
        if ($rateLimiter->resetForEndpoint($endpoint, $identifier)) {
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Rate limit resetado",
                "endpoint" => $endpoint,
                "identifier" => $identifier,
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "message" => "Rate limit não encontrado",
                "endpoint" => $endpoint,
                "identifier" => $identifier,
            ]);
        }
    } else {
        $limits = $rateLimiter->listTrackedBuckets();

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Status de rate limits",
            "total_limits" => count($limits),
            "limits" => $limits,
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
    ]);
}
