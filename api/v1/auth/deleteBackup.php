<?php
// Configurações de erro - log apenas, sem exibição
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/delete_backup_error.log');

// Headers
header('Content-Type: application/json; charset=utf-8');

require_once dirname(dirname(dirname(__DIR__))) . '/auth.php';
require_once __DIR__ . '/../middleware/TenantMiddleware.php';

// Authentication: Bearer-first via central middleware. If no Bearer, fallback para sessão legacy.
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$hasBearer = is_string($authHeader) && str_starts_with(trim($authHeader), 'Bearer ');
$authContext = \App\Auth\AuthContext::getInstance();

if ($hasBearer) {
    // Delegar autenticação ao middleware central; rejeitar se falhar.
    $request = ['headers' => ['Authorization' => $authHeader], 'query' => $_GET, 'path' => $_SERVER['REQUEST_URI'] ?? ''];
    $middleware = new \App\Middleware\AuthMiddleware();
    $res = $middleware->handle($request, function ($r) {
        return ['ok' => true];
    });
    if (isset($res['status']) && $res['status'] !== 200 && isset($res['error'])) {
        http_response_code((int)$res['status']);
        echo json_encode(['success' => false, 'message' => $res['error']]);
        exit;
    }
    // Autorização via AuthContext (não usar sessão quando Bearer presente)
    $isAuthorized = $authContext->isAdmin() || $authContext->isSuperAdmin();
} else {
    // Fallback legado: materializar sessão e usar checagens existentes
    checkAuth();
    $isAuthorized = false;
    if (function_exists('isAdmin') && isAdmin()) {
        $isAuthorized = true;
    } elseif (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin']) {
        $isAuthorized = true;
    } elseif (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'superadmin'])) {
        $isAuthorized = true;
    } elseif (isset($_SESSION['type']) && in_array($_SESSION['type'], ['admin', 'super_admin', 'superadmin'])) {
        $isAuthorized = true;
    }
}

if (!$isAuthorized) {
    http_response_code(403);
    error_log('Acesso negado: usuário não é admin/superadmin. Session: ' . json_encode($_SESSION));
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Apenas administradores podem deletar backups.']);
    exit;
}

try {
    // Verifica se o nome do arquivo foi fornecido
    if (!isset($_POST['filename'])) {
        throw new Exception('Nome do arquivo não fornecido');
    }

    $filename = $_POST['filename'];

    // Normaliza o caminho do diretório usando DIRECTORY_SEPARATOR
    $backupDir = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'backups';
    $backupDir = realpath($backupDir);
    if ($backupDir === false) {
        throw new Exception('Diretório de backups não encontrado');
    }
    $backupDir .= DIRECTORY_SEPARATOR;

    // Normaliza o nome do arquivo para segurança
    $filename = basename($filename); // Remove qualquer caminho do nome do arquivo
    error_log("Nome do arquivo após basename: " . $filename);

    // Verifica se o nome do arquivo corresponde ao padrão esperado
    // Padrão: db_backup_tenant_[ID]_YYYY-MM-DD_HH-MM-SS.sql
    //     ou: env_backup_tenant_[ID]_YYYY-MM-DD_HH-MM-SS.txt
    if (!preg_match('/^(db|env)_backup_tenant_\d+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.(sql|txt)$/', $filename)) {
        // Tentar também o padrão antigo (sem tenant)
        if (!preg_match('/^(db|env)_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.(sql|txt)$/', $filename)) {
            throw new Exception('Nome do arquivo inválido: ' . $filename . '. Padrão esperado: db_backup_tenant_[ID]_YYYY-MM-DD_HH-MM-SS.sql');
        }
    }

    // --- Resolução segura de tenant para delete (sem matar a requisição em 403 fake) ---
    $currentTenantId = 0;
    if (isset($_SESSION['selected_tenant_id']) && (int) $_SESSION['selected_tenant_id'] > 0) {
        $currentTenantId = (int) $_SESSION['selected_tenant_id'];
    } elseif (isset($_SESSION['tenant_id']) && (int) $_SESSION['tenant_id'] > 0) {
        $currentTenantId = (int) $_SESSION['tenant_id'];
    } elseif (class_exists('TenantContext')) {
        require_once dirname(dirname(dirname(__DIR__))) . '/app/security/TenantContext.php';
        try {
            \TenantContext::initialize();
            $resolvedTenantId = \TenantContext::getTenantId();
            if (is_numeric($resolvedTenantId) && (int) $resolvedTenantId > 0) {
                $currentTenantId = (int) $resolvedTenantId;
            }
        } catch (Throwable $e) {
            error_log('TenantContext fallback falhou no deleteBackup: ' . $e->getMessage());
        }
    }

    // Extrai tenant do filename se presente (formato tenant-aware)
    $fileTenantId = null;
    if (preg_match('/_tenant_(\d+)_/', $filename, $m)) {
        $fileTenantId = (int)$m[1];
    }

    // Se não for superadmin, aceitar arquivos legados quando o tenant atual puder ser determinado;
    // realizar o bloqueio somente quando o nome do arquivo explicita um tenant diferente do atual.
    if (!($authContext->isSuperAdmin() ?? false)) {
        if ($fileTenantId !== null && $currentTenantId > 0 && $fileTenantId !== $currentTenantId) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Você não tem permissão para deletar este backup (tenant mismatch).']);
            exit;
        }
    }

    // Extrai a data do nome do arquivo
    preg_match('/\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}/', $filename, $matches);
    $dateTime = $matches[0];

    // Constrói os caminhos completos usando DIRECTORY_SEPARATOR
    $sqlFile = $backupDir . $filename;
    $sqlFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $sqlFile);

    // Define o padrão para o arquivo de mídia
    $mediaPattern = $backupDir . 'media_backup_' . $dateTime . '*.zip';

    $envFile = $backupDir . str_replace('db_backup_', 'env_backup_', str_replace('.sql', '.txt', $filename));
    $envFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $envFile);

    // Log para debug
    error_log("Tentando excluir backup:");
    error_log("Filename recebido: " . $filename);
    error_log("Diretório de backup: " . $backupDir);
    error_log("Caminho completo SQL: " . $sqlFile);
    error_log("Caminho completo ENV: " . $envFile);
    error_log("Arquivo existe? " . (file_exists($sqlFile) ? "Sim" : "Não"));
    error_log("Diretório existe? " . (is_dir($backupDir) ? "Sim" : "Não"));
    error_log("Diretório atual: " . __DIR__);
    error_log("Permissões do arquivo: " . (file_exists($sqlFile) ? decoct(fileperms($sqlFile)) : "N/A"));
    error_log("Permissões do diretório: " . decoct(fileperms($backupDir)));
    error_log("Usuário atual: " . get_current_user());
    error_log("Separador de diretório usado: " . DIRECTORY_SEPARATOR);
    error_log("realpath do arquivo: " . (file_exists($sqlFile) ? realpath($sqlFile) : "N/A"));

    // Verifica se o diretório existe
    if (!is_dir($backupDir)) {
        throw new Exception('Diretório de backups não encontrado: ' . $backupDir);
    }

    // Verifica se o arquivo está dentro do diretório de backups e existe
    if (!file_exists($sqlFile)) {
        throw new Exception('Arquivo de backup não encontrado: ' . $sqlFile);
    }

    // Verifica se o arquivo está realmente dentro do diretório de backups (segurança)
    $realBackupDir = realpath($backupDir);
    $realFilePath = realpath($sqlFile);
    if (strpos($realFilePath, $realBackupDir) !== 0) {
        throw new Exception('Caminho do arquivo inválido');
    }

    // Exclui os arquivos
    if (file_exists($sqlFile) && !unlink($sqlFile)) {
        error_log('Falha ao excluir arquivo SQL: ' . $sqlFile);
        throw new Exception('Erro ao excluir arquivo SQL');
    } else {
        error_log('Arquivo SQL excluído: ' . $sqlFile);
    }

    if (file_exists($envFile)) {
        if (!unlink($envFile)) {
            error_log('Falha ao excluir arquivo ENV: ' . $envFile);
        } else {
            error_log('Arquivo ENV excluído: ' . $envFile);
        }
    }

    // Procura e exclui os arquivos de mídia correspondentes
    $mediaFiles = glob($mediaPattern);
    if ($mediaFiles) {
        foreach ($mediaFiles as $mediaFile) {
            if (file_exists($mediaFile)) {
                if (!unlink($mediaFile)) {
                    error_log("Aviso: Não foi possível excluir o arquivo de mídia: " . $mediaFile);
                } else {
                    error_log("Arquivo de mídia excluído com sucesso: " . $mediaFile);
                }
            }
        }
    }

    // Exclui o arquivo database.php.backup se existir
    $databasePhpBackup = $backupDir . 'database.php.backup';
    if (file_exists($databasePhpBackup)) {
        if (!unlink($databasePhpBackup)) {
            error_log('Falha ao excluir database.php.backup: ' . $databasePhpBackup);
        } else {
            error_log('Arquivo database.php.backup excluído: ' . $databasePhpBackup);
        }
    }

    // Limpa o cache de arquivos para garantir que o PHP veja o estado atualizado
    clearstatcache();

    echo json_encode([
        'success' => true,
        'message' => 'Backup excluído com sucesso'
    ]);
} catch (Exception $e) {
    error_log('ERRO ao deletar backup: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao deletar backup: ' . $e->getMessage(),
        'debug_trace' => $e->getTraceAsString()
    ]);
}
