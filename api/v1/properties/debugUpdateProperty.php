<?php
// Endpoint de diagnóstico — não público. Exigir autenticação e
// registrar apenas conteúdo sanitizado para evitar vazamento de secrets.
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/session_manager.php';

try {
    // checkAuth lançará exceção ou validar sessão; protege o endpoint
    checkAuth();
} catch (Exception $e) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Não autorizado'
    ]);
    exit;
}

error_log("\n\n========== DEBUG updateProperty (sanitizado) ==========");
error_log("Data/Hora: " . date('Y-m-d H:i:s'));

// Sanitizar $_POST: truncar valores longos e ocultar chaves sensíveis
$sanitized = [];
foreach ($_POST as $k => $v) {
    $val = (is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE));
    if (preg_match('/pass|token|secret|authorization|senha|password|api[_-]?key/i', $k)) {
        $val = '[REDACTED]';
    }
    if (strlen($val) > 200) {
        $val = substr($val, 0, 200) . '...[truncated]';
    }
    $sanitized[$k] = $val;
}

error_log('POST (sanitizado): ' . json_encode($sanitized, JSON_UNESCAPED_UNICODE));
error_log("========== FIM DO LOG ==========_\n\n");

// Resposta inalterada para compatibilidade com chamadas que esperam JSON
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Dados recebidos. Verifique o error.log'
]);
