<?php
// API para listar arquivos de backup
// COM ISOLAMENTO DE TENANT_ID
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/helpers/tenant_isolation_helpers.php';

require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';


// Resolver autenticação: preferir JWT via AuthMiddleware quando Authorization header presente
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$hasBearer = is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
$authContext = \App\Auth\AuthContext::getInstance();
if ($hasBearer) {
    $request = ['headers' => ['Authorization' => $authHeader], 'query' => $_GET, 'path' => $_SERVER['REQUEST_URI'] ?? ''];
    $middleware = new \App\Middleware\AuthMiddleware();
    $res = $middleware->handle($request, function ($r) {
        return ['ok' => true];
    });
    if (isset($res['status']) && $res['status'] !== 200 && isset($res['error'])) {
        http_response_code((int)$res['status']);
        echo json_encode(['success' => false, 'backups' => [], 'total' => 0, 'message' => $res['error']]);
        exit;
    }
} else {
    // fallback para sessão
    checkAuth();
}

// autorização via AuthContext
$isAdminCtx = $authContext->isAdmin() || $authContext->isSuperAdmin();
if (!$isAdminCtx) {
    // Silenciosamente retornar lista vazia (sem expor tentativa de acesso negado)
    echo json_encode(['success' => true, 'backups' => [], 'total' => 0, 'message' => 'Nenhum backup disponível']);
    exit;
}

// Inicializar middleware de tenant somente quando houver tenant identificado;
// caso contrário, cair para o comportamento seguro de "todos os backups" para superadmin
$tenantId = 0;
if (isset($_SESSION['selected_tenant_id']) && (int) $_SESSION['selected_tenant_id'] > 0) {
    $tenantId = (int) $_SESSION['selected_tenant_id'];
} elseif (isset($_SESSION['tenant_id']) && (int) $_SESSION['tenant_id'] > 0) {
    $tenantId = (int) $_SESSION['tenant_id'];
} elseif (function_exists('initializeTenantMiddleware')) {
    try {
        initializeTenantMiddleware($pdo);
        $resolvedTenantId = getCurrentTenantId();
        if (is_numeric($resolvedTenantId) && (int) $resolvedTenantId > 0) {
            $tenantId = (int) $resolvedTenantId;
        }
    } catch (Throwable $e) {
        error_log('Tenant fallback falhou no listBackups: ' . $e->getMessage());
    }
}
$isSuperAdmin = (bool)($authContext->isSuperAdmin() ?? false);

$backupDir = realpath(dirname(dirname(dirname(__DIR__))) . '/tools/backups');
if ($backupDir === false) {
    echo json_encode(['success' => false, 'message' => 'Diretório de backups não encontrado']);
    exit;
}

// Padrão de backup com tenant_id (ex: db_backup_tenant_2_2026-03-24_13-07-48.sql)
// Se não houver tenant resolvido, superadmin vê todos; usuários comuns ficam restritos ao tenant atual.
if ($isSuperAdmin || $tenantId <= 0) {
    $pattern = $backupDir . '/db_backup_*.sql';
} else {
    $pattern = $backupDir . '/db_backup_tenant_' . $tenantId . '_*.sql';
}

$files = glob($pattern);
if ($files === false) {
    $files = [];
}

$backups = [];
foreach ($files as $file) {
    $backups[] = [
        'file' => basename($file),
        'size' => filesize($file),
        'date' => date('Y-m-d H:i:s', filemtime($file)),
        'timestamp' => filemtime($file),
        'tenant_id' => $tenantId
    ];
}

// Ordenar backups do mais recente para o mais antigo
usort($backups, function ($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Remove o timestamp antes de enviar (foi usado só para ordenação)
foreach ($backups as &$backup) {
    unset($backup['timestamp']);
}

echo json_encode([
    'success' => true,
    'data' => $backups
]);
