<?php

/**
 * API para executar follow-ups pendentes
 * Executa follow-ups agendados que já passaram da hora
 */

// MUITO IMPORTANTE: ob_start() ANTES DE QUALQUER OUTPUT
ob_start();

// Definir header JSON
header('Content-Type: application/json; charset=utf-8');

// ⚠️ TIMEOUT GLOBAL - máximo 100 segundos (PHP default é 120)
set_time_limit(100);

// Rastrear tempo de início para cancelamento manual se necessário
$startTime = microtime(true);
$maxExecutionTime = 95; // segundos

// Configurar tratamento de TODOS os erros
error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    global $response;
    error_log("[$errno] $errstr in $errfile:$errline");
    $response = [
        'success' => false,
        'error' => "Erro PHP [$errno]: $errstr",
        'file' => $errfile,
        'line' => $errline
    ];
    return true; // Previne erro padrão de PHP
});

// Tratar fatal errors e parse errors
register_shutdown_function(function () {
    global $response, $startTime, $maxExecutionTime;
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);

        // Se foi timeout, retorna mensagem específica
        $elapsed = microtime(true) - $startTime;
        if ($elapsed > $maxExecutionTime) {
            $response = [
                'success' => false,
                'error' => "Operação excedeu timeout ({$elapsed}s > {$maxExecutionTime}s)",
                'error_type' => 'TIMEOUT',
                'elapsed_time' => round($elapsed, 2)
            ];
        } else {
            $response = [
                'success' => false,
                'error' => "Erro Fatal: " . $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ];
        }
        ob_end_clean();
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
});

$response = [
    'success' => false,
    'error' => 'Erro não inicializado'
];

try {
    // Limpar qualquer output anterior
    ob_clean();

    // Carregar AUTOLOAD PRIMEIRO (antes de qualquer classe que dependa dele)
    require_once __DIR__ . '/../../../../vendor/autoload.php';

    require_once __DIR__ . '/../../../../config/database.php';
    require_once __DIR__ . '/../../../../config/session_manager.php';
    require_once __DIR__ . '/../../../../config/notification.php';
    require_once __DIR__ . '/../../../../app/services/LeadFollowupService.php';

    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        $response['error'] = 'Método não permitido. Use POST.';
        throw new Exception('Método HTTP inválido');
    }

    // Verificar autenticação
    require_once __DIR__ . '/../../../../app/middleware/api_auth.php';
    checkApiAuth();

    // Obter tenant_id via helper central de resolução
    $tenantId = getRequestTenantId();

    // Parâmetro opcional para forçar execução
    $forceExecute = (isset($_POST['force']) && $_POST['force'] === 'true') ? true : false;

    // Criar serviço
    if (!isset($pdo)) {
        throw new Exception('Conexão com banco de dados não inicializada');
    }

    $followupService = new App\Services\LeadFollowupService($pdo, $tenantId);

    // Executar follow-ups pendentes
    $result = $followupService->executePendingFollowups($forceExecute);

    if ($result['success']) {
        $response = [
            'success' => true,
            'message' => 'Follow-ups executados com sucesso',
            'data' => $result
        ];
    } else {
        http_response_code(500);
        $response = [
            'success' => false,
            'error' => $result['error'] ?? 'Erro desconhecido'
        ];
    }
} catch (ApiAuthException $e) {
    // Erros de autenticação (já tem http_response_code definido)
    error_log("[FOLLOWUP API AUTH ERROR] " . $e->getMessage());

    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
} catch (SessionAuthException $e) {
    // Erros de autenticação da sessão (já tem http_response_code definido)
    error_log("[FOLLOWUP API SESSION ERROR] " . $e->getMessage());

    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
} catch (Exception $e) {
    error_log("[FOLLOWUP API ERROR] " . $e->getMessage());
    http_response_code(500);

    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
} catch (Throwable $t) {
    error_log("[FOLLOWUP API THROWABLE] " . $t->getMessage());
    http_response_code(500);

    $response = [
        'success' => false,
        'error' => $t->getMessage()
    ];
}

// Limpar buffer e enviar JSON
ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
exit;
