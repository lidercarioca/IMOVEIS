<?php
// Configurações de erro - log apenas, sem exibição
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/delete_backup_error.log');

// Headers
header('Content-Type: application/json; charset=utf-8');

require_once '../auth.php';
checkAuth();

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado']);
    exit;
}

try {
    // Verifica se o nome do arquivo foi fornecido
    if (!isset($_POST['filename'])) {
        throw new Exception('Nome do arquivo não fornecido');
    }

    $filename = $_POST['filename'];
    
    // Normaliza o caminho do diretório usando DIRECTORY_SEPARATOR
    $backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
    $backupDir = realpath($backupDir);
    if ($backupDir === false) {
        throw new Exception('Diretório de backups não encontrado');
    }
    $backupDir .= DIRECTORY_SEPARATOR;
    
    // Normaliza o nome do arquivo para segurança
    $filename = basename($filename); // Remove qualquer caminho do nome do arquivo
    error_log("Nome do arquivo após basename: " . $filename);
    
    // Verifica se o nome do arquivo corresponde ao padrão esperado
    if (!preg_match('/^(db|env)_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.(sql|txt)$/', $filename)) {
        throw new Exception('Nome do arquivo inválido: ' . $filename);
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
