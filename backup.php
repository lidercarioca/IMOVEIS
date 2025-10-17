<?php
// Configurações de erro - log apenas, sem exibição
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/backup_error.log');

// Garante que não há saída antes do JSON
while (ob_get_level()) ob_end_clean();
ob_start();

// Registra função de limpeza para garantir que nada será enviado após o JSON
register_shutdown_function(function() {
    while (ob_get_level()) ob_end_clean();
});

// Aumenta o tempo limite de execução para arquivos grandes
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '256M');

// Headers
header('Content-Type: application/json; charset=utf-8');

// Função para enviar resposta JSON
function sendJsonResponse($success, $message = '', $data = null) {
    while (ob_get_level()) ob_end_clean();
    
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data) $response['files'] = $data;
    
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$success) {
            header('HTTP/1.1 500 Internal Server Error');
        }
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Verifica autenticação
require_once 'auth.php';

// Carrega configuração do banco de dados
require_once 'config/database.php';
if (!isset($pdo)) {
    sendJsonResponse(false, 'Erro ao conectar ao banco de dados: Configuração não encontrada');
    exit;
}

// Carrega os handlers necessários
require_once 'app/handlers/MediaBackupHandler.php';
require_once 'app/handlers/BackupRotationHandler.php';
require_once 'app/handlers/BackupCompressionHandler.php';
require_once 'app/handlers/BackupNotificationHandler.php';
require_once 'app/handlers/SystemBackupHandler.php';
checkAuth();

// Inicializa o handler de notificações com o email do administrador
$notificationHandler = new BackupNotificationHandler($ADMIN_EMAIL);

if (!isAdmin()) {
    sendJsonResponse(false, 'Acesso não autorizado');
    exit;
}

// Carrega configurações de notificação
require_once 'config/notification.php';

// Inicializa os handlers necessários

$rotationHandler = new BackupRotationHandler(__DIR__ . '/backups', 10, 3);
$compressionHandler = new BackupCompressionHandler(7); // Nível de compressão 7 (bom equilíbrio entre velocidade e tamanho)
$notificationHandler = new BackupNotificationHandler(
    $ADMIN_EMAIL,
    $FROM_NAME . ' <noreply@' . $_SERVER['SERVER_NAME'] . '>',
    $SMTP_CONFIG
);

// Configurações
$backupDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
$date = date('Y-m-d_H-i-s');
$dbBackupFile = "db_backup_{$date}.sql";
$envBackupFile = "env_backup_{$date}.txt";

// Inicializa o handler de mídia com o caminho correto
$mediaHandler = new MediaBackupHandler($backupDir);

// Função para fazer backup completo do banco de dados
/**
 * Realiza o backup completo do banco de dados
 * @param PDO $pdo - Conexão com o banco de dados
 * @param string $dbname - Nome do banco de dados
 * @return string SQL com o backup do banco
 */
function backupDatabase($pdo, $dbname) {
    try {
        error_log("Iniciando backup completo do banco de dados: " . $dbname);
        
        // Obtém lista de todas as tabelas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $output = "-- Backup do banco de dados " . $dbname . "\n";
        $output .= "-- Data: " . date('Y-m-d H:i:s') . "\n";
        $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        
        foreach ($tables as $table) {
            error_log("Processando tabela: " . $table);
            
            // Obtém a estrutura da tabela
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $row[1] . ";\n\n";
            
            // Obtém os dados da tabela
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $output .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } else {
                            $rowValues[] = $pdo->quote($value);
                        }
                    }
                    $values[] = "(" . implode(', ', $rowValues) . ")";
                }
                $output .= implode(",\n", $values) . ";\n\n";
            }
            
            error_log("Tabela " . $table . " processada com sucesso");
        }
        
        $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
        error_log("Backup completo do banco de dados concluído");
        
        return $output;
    } catch (Exception $e) {
        error_log("Erro ao fazer backup do banco de dados: " . $e->getMessage());
        throw $e;
    }
}

// Função para fazer backup de uma tabela
function backupTable($pdo, $table) {
    try {
        // Obtém a estrutura da tabela
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        $create = "DROP TABLE IF EXISTS `$table`;\n" . $row[1] . ";\n\n";
        
        // Obtém os dados da tabela
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $data = '';
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $values = array_map(function($value) use ($pdo) {
                if ($value === null) return 'NULL';
                return $pdo->quote($value);
            }, $row);
            
            $data .= "INSERT INTO `$table` VALUES (" . implode(",", $values) . ");\n";
        }
        
        return $create . $data . "\n";
    } catch (PDOException $e) {
        throw new Exception("Erro ao fazer backup da tabela $table: " . $e->getMessage());
    }
}

try {
    // Garante que o diretório de backup existe
    if (!file_exists($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            throw new Exception('Não foi possível criar o diretório de backup');
        }
    }

    // Verifica permissões do diretório
    if (!is_writable($backupDir)) {
        throw new Exception('Diretório de backup não tem permissão de escrita');
    }

    // Carrega configurações do banco de dados
    require_once 'config/database.php';
    
    // Usa a conexão PDO já estabelecida
    if (!isset($pdo)) {
        throw new Exception('Conexão PDO não está disponível');
    }

    // Usa a função backupDatabase para fazer backup completo
    $backup = backupDatabase($pdo, $dbname);

    $backup .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

    // Salva o arquivo de backup
    if (file_put_contents($backupDir . $dbBackupFile, $backup) === false) {
        throw new Exception('Erro ao salvar arquivo de backup');
    }

    // Backup das configurações do ambiente
    
    // Faz backup completo do arquivo database.php
    $databasePhpPath = __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
    if (!file_exists($databasePhpPath)) {
        throw new Exception('Arquivo database.php não encontrado');
    }
    
    // Copia o arquivo database.php completo
    if (!copy($databasePhpPath, $backupDir . 'database.php.backup')) {
        throw new Exception('Erro ao fazer backup do arquivo database.php');
    }
    
    // Faz backup completo do arquivo .env se existir, ou cria um novo com configurações básicas
    if (file_exists(__DIR__ . '/.env')) {
        // Copia o arquivo .env completo
        if (!copy(__DIR__ . '/.env', $backupDir . $envBackupFile)) {
            throw new Exception('Erro ao fazer backup do arquivo .env');
        }
    } else {
        // Cria um arquivo com as configurações principais
        $envContent = sprintf(
            "ENVIRONMENT=production\n" .
            "DB_HOST=%s\n" .
            "DB_USER=%s\n" .
            "DB_PASS=%s\n" .
            "DB_NAME=%s\n",
            $host,
            $user,
            $password,
            $dbname
        );
        if (!file_put_contents($backupDir . $envBackupFile, $envContent)) {
            throw new Exception('Erro ao criar arquivo de configurações resumido');
        }
    }


    // Realiza backup dos arquivos de mídia
    $mediaBackupFile = $mediaHandler->backup();

    // Retorna sucesso através da função sendJsonResponse
    sendJsonResponse(true, 'Backup realizado com sucesso', [
        'database' => $dbBackupFile,
        'env' => $envBackupFile,
        'database_config' => 'database.php.backup',
        'media' => $mediaBackupFile
    ]);

} catch (Exception $e) {
    // Em caso de erro, tenta limpar os arquivos criados
    @unlink($backupDir . $dbBackupFile);
    @unlink($backupDir . $envBackupFile);
    @unlink($backupDir . 'database.php.backup');
    
    sendJsonResponse(false, $e->getMessage());
}
?>
