<?php

/**
 * API para gerenciar backups do banco de dados
 * Endpoints:
 * - GET /api/backups/list - Listar backups
 * - POST /api/backups/create - Criar novo backup
 * - POST /api/backups/restore - Restaurar backup
 * - DELETE /api/backups/delete - Deletar backup
 * COM ISOLAMENTO DE TENANT_ID
 */

require_once dirname(dirname(dirname(__DIR__))) . '/app/bootstrap.php';

use App\Auth\AuthContext;
use Api\V1\Middleware\AuthMiddleware;
use Api\V1\Middleware\TenantMiddleware;

header('Content-Type: application/json; charset=utf-8');

AuthMiddleware::handle();
$tenantId = TenantMiddleware::resolveTenantId();

$isSuperAdmin = AuthContext::getInstance()->isSuperAdmin();
$isAdmin = AuthContext::getInstance()->isAdmin() || $isSuperAdmin;

// Apenas admins podem acessar backups (silenciosamente retorna vazio)
if (!$isAdmin) {
    // Sem erro - simplesmente retorna como se não houvesse dados
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Nenhum dado disponível', 'data' => []]);
    exit;
}

$action = $_GET['action'] ?? null;
$backupDir = dirname(dirname(__DIR__)) . '/tools/backups';

try {
    switch ($action) {
        case 'list':
            // Listar backups disponíveis com filtro por tenant
            // Se superadmin, mostrar todos os backups
            // Se admin comum, mostrar apenas backups do seu tenant
            $pattern = $isSuperAdmin ?
                $backupDir . '/db_backup_*.sql' :
                $backupDir . '/db_backup_tenant_' . $tenantId . '_*.sql';

            $backups = glob($pattern);
            rsort($backups);

            $backupList = [];
            foreach ($backups as $backup) {
                $filename = basename($backup);

                // Parse do novo formato: db_backup_tenant_X_YYYY-MM-DD_HH-mm-ss.sql
                if (preg_match('/db_backup_tenant_(\d+)_(.+)\.sql$/', $filename, $matches)) {
                    $backupTenantId = $matches[1];
                    $date = $matches[2];
                } else {
                    // Formato legado
                    $date = str_replace(['db_backup_', '.sql'], '', $filename);
                    $backupTenantId = 1;
                }

                $backupList[] = [
                    'name' => $filename,
                    'size' => filesize($backup),
                    'date' => date('d/m/Y H:i:s', filemtime($backup)),
                    'tenant_id' => $backupTenantId,
                    'timestamp' => filemtime($backup)
                ];
            }

            echo json_encode([
                'success' => true,
                'backups' => $backupList,
                'total' => count($backupList),
                'current_tenant_id' => $tenantId,
                'is_superadmin' => $isSuperAdmin
            ]);
            break;

        case 'create':
            // Criar novo backup filtrado por tenant
            $exec = 'php ' . escapeshellarg(dirname(__DIR__) . '/tools/backup_database.php') . ' ' . escapeshellarg('--tenant-id=' . $tenantId);
            $output = shell_exec($exec);

            if (strpos(strtolower($output), 'sucesso') !== false) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Backup criado com sucesso',
                    'log' => $output,
                    'tenant_id' => $tenantId
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao criar backup',
                    'log' => $output
                ]);
            }
            break;

        case 'delete':
            // Deletar backup com verificação de tenant
            $filename = $_GET['filename'] ?? null;

            if (!$filename || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nome de arquivo inválido']);
                exit;
            }

            // Verificar isolamento de tenant
            if (!$isSuperAdmin) {
                // Usuário comum deve deletar apenas seus próprios backups
                if (!preg_match('/^db_backup_tenant_' . preg_quote($tenantId, '/') . '_/', $filename)) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para deletar este backup']);
                    exit;
                }
            }

            $filepath = $backupDir . '/' . $filename;

            if (!file_exists($filepath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado']);
                exit;
            }

            unlink($filepath);

            echo json_encode([
                'success' => true,
                'message' => 'Backup deletado com sucesso'
            ]);
            break;

        case 'download':
            // Download do backup
            $filename = $_GET['filename'] ?? null;

            if (!$filename || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nome de arquivo inválido']);
                exit;
            }

            $filepath = $backupDir . '/' . $filename;

            if (!file_exists($filepath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Arquivo não encontrado']);
                exit;
            }

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}
