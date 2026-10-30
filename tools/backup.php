<?php

// Configuração de erro rigorosa
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/backup_error.log');

// Limpa qualquer output anterior
while (ob_get_level()) ob_end_clean();
ob_start();

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

// Handler customizado de erro
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[$errno] $errstr in $errfile:$errline");
    // Não falha silenciosamente, permite que o código continue se possível
    return false;
});

// Handler para fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_PARSE)) !== 0) {
        sendJsonResponse(false, 'Erro Fatal: ' . $error['message']);
    }
    while (ob_get_level()) ob_end_clean();
});

// Set Headers
set_time_limit(300); // 5 minutos
ini_set('memory_limit', '256M');
header('Content-Type: application/json; charset=utf-8');


require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/tenant_isolation_helpers.php';

// Carrega variáveis de ambiente
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '\'"');
            putenv("$key=$value");
        }
    }
}

// Define variáveis globais
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'sys_imoveis';

require_once __DIR__ . '/../config/database.php';
if (!isset($pdo)) {
    // Tenta conectar manualmente
    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        sendJsonResponse(false, 'Erro ao conectar ao banco de dados: ' . $e->getMessage());
        exit;
    }
}


// Tenta carregar handlers, mas não falha se não existirem
@require_once __DIR__ . '/../app/handlers/MediaBackupHandler.php';
@require_once __DIR__ . '/../app/handlers/BackupRotationHandler.php';
@require_once __DIR__ . '/../app/handlers/BackupCompressionHandler.php';
@require_once __DIR__ . '/../app/handlers/BackupNotificationHandler.php';
@require_once __DIR__ . '/../app/handlers/SystemBackupHandler.php';

// Verifica autenticação (se existir função)
if (function_exists('checkAuth')) {
    checkAuth();
}

// Inicializar tenant isolamento
try {
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $isSuperAdmin = isSuperAdmin();
} catch (Exception $e) {
    // Se não conseguir inicializar tenant (e.g., em execução de linha de comando), usa tenant 1
    $tenantId = 1;
    $isSuperAdmin = false;
}

// Verifica se é admin
if (!isset($ADMIN_EMAIL)) {
    $ADMIN_EMAIL = 'admin@' . $_SERVER['SERVER_NAME'];
}

// Configurações
$backupDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
$date = date('Y-m-d_H-i-s');
// Incluir tenant_id no nome do arquivo para isolamento
$dbBackupFile = "db_backup_tenant_{$tenantId}_{$date}.sql";
$envBackupFile = "env_backup_tenant_{$tenantId}_{$date}.txt";

// Inicializa handlers opcionais
$rotationHandler = null;
if (class_exists('BackupRotationHandler')) {
    $rotationHandler = new BackupRotationHandler(__DIR__ . '/backups', 10, 3);
}

$compressionHandler = null;
if (class_exists('BackupCompressionHandler')) {
    $compressionHandler = new BackupCompressionHandler(7);
}

$notificationHandler = null;
if (class_exists('BackupNotificationHandler')) {
    @require_once __DIR__ . '/../config/notification.php';
    $fromName = isset($FROM_NAME) ? $FROM_NAME : 'RR Imóveis';
    $smtpConfig = isset($SMTP_CONFIG) ? $SMTP_CONFIG : [];
    
    $notificationHandler = new BackupNotificationHandler(
        $ADMIN_EMAIL,
        $fromName . ' <noreply@' . $_SERVER['SERVER_NAME'] . '>',
        $smtpConfig
    );
}

$mediaHandler = null;
if (class_exists('MediaBackupHandler')) {
    $mediaHandler = new MediaBackupHandler($backupDir);
}

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
    
    if (!file_exists($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            throw new Exception('Não foi possível criar o diretório de backup');
        }
    }

    // Verifica permissões do diretório
    if (!is_writable($backupDir)) {
        throw new Exception('Diretório de backup não tem permissão de escrita');
    }

    // Faz o backup do banco de dados
    $backup = backupDatabase($pdo, $dbname);

    $backup .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

    // Salva o arquivo de backup
    if (file_put_contents($backupDir . $dbBackupFile, $backup) === false) {
        throw new Exception('Erro ao salvar arquivo de backup');
    }

    // Backup das configurações do ambiente
    
    // Faz backup completo do arquivo database.php
    $databasePhpPath = __DIR__ . '/../config/database.php';
    if (!file_exists($databasePhpPath)) {
        throw new Exception('Arquivo database.php não encontrado');
    }

    // Copia o arquivo database.php completo
    if (!copy($databasePhpPath, $backupDir . 'database.php.backup')) {
        throw new Exception('Erro ao fazer backup do arquivo database.php');
    }
    
    
    $envCandidates = [
        __DIR__ . '/../.env', // um nível acima do webroot
        __DIR__ . '/.env'     // raiz do webroot (caso exista)
    ];
    $envFound = false;
    foreach ($envCandidates as $envPath) {
        if (file_exists($envPath)) {
            if (!copy($envPath, $backupDir . $envBackupFile)) {
                throw new Exception('Erro ao fazer backup do arquivo .env');
            }
            $envFound = true;
            break;
        }
    }
    if (!$envFound) {
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


    // Faz backup de mídias (se handler disponível)
    $mediaBackupFile = null;
    if ($mediaHandler !== null) {
        $mediaBackupFile = $mediaHandler->backup();
    }

    // Rotate/cleanup old backups to maintain storage limits
    if ($rotationHandler !== null) {
        $rotationHandler->rotate();
    }
    
    // Tenta registrar ação (se função existir)
    if (function_exists('log_user_action')) {
        @require_once __DIR__ . '/../app/utils/logger_functions.php';
        $loggedFiles = [
            'database' => $dbBackupFile,
            'env' => $envBackupFile,
            'database_config' => 'database.php.backup'
        ];
        if ($mediaBackupFile) {
            $loggedFiles['media'] = $mediaBackupFile;
        }
        log_user_action('backup_run', ['files' => $loggedFiles]);
    }

    // Resposta de sucesso
    $responseData = [
        'database' => $dbBackupFile,
        'env' => $envBackupFile,
        'database_config' => 'database.php.backup'
    ];
    if ($mediaBackupFile) {
        $responseData['media'] = $mediaBackupFile;
    }
    
    sendJsonResponse(true, 'Backup realizado com sucesso', $responseData);

} catch (Exception $e) {
    
    @unlink($backupDir . $dbBackupFile);
    @unlink($backupDir . $envBackupFile);
    @unlink($backupDir . 'database.php.backup');
    
    sendJsonResponse(false, $e->getMessage());
}
?>
