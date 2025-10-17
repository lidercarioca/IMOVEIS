<?php
// Configurações de erro - log apenas, sem exibição
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/restore_error.log');

// Garante que não há saída antes do JSON
while (ob_get_level()) ob_end_clean();
ob_start();

// Registra função de limpeza para garantir que nada será enviado após o JSON
register_shutdown_function(function() {
    while (ob_get_level()) ob_end_clean();
});

// Headers
header('Content-Type: application/json; charset=utf-8');

// Função para enviar resposta JSON e encerrar
/**
 * Envia uma resposta JSON padronizada
 * @param bool $success - Indica se a operação foi bem-sucedida
 * @param string $message - Mensagem descritiva
 * @param mixed $data - Dados adicionais para retornar
 */
function sendJsonResponse($success, $message = '', $data = null) {
    // Limpa qualquer output buffering
    while (ob_get_level()) ob_end_clean();
    
    $response = ['success' => $success];
    if ($message) $response['message'] = $message;
    if ($data) $response['data'] = $data;
    
    // Define os headers corretos
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        // Define o código de status HTTP apropriado
        if (!$success && isset($response['message']) && strpos($response['message'], 'não autorizado') !== false) {
            header('HTTP/1.1 403 Forbidden');
        } elseif (!$success) {
            header('HTTP/1.1 400 Bad Request');
        }
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once 'auth.php';
require_once 'app/handlers/BackupIntegrityHandler.php';
checkAuth();

if (!isAdmin()) {
    sendJsonResponse(false, 'Acesso não autorizado', [
        'type' => 'auth_error'
    ]);
    exit;
}

// Inicializa o validador de integridade
$integrityHandler = new BackupIntegrityHandler();

// Configurações
$backupDir = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, __DIR__ . DIRECTORY_SEPARATOR . 'backups'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

// Verifica se o diretório de backups existe
if (!is_dir($backupDir)) {
    error_log("Diretório de backup não encontrado: " . $backupDir);
    mkdir($backupDir, 0777, true);
}

try {
    // Verifica se foi enviado um arquivo SQL
    if (!isset($_POST['backup_file'])) {
        // Lista os backups disponíveis
        $backups = [];
        if (is_dir($backupDir)) {
            // Usa GLOB_NOSORT para melhor performance
            $pattern = $backupDir . "db_backup_*.sql";
            error_log("Procurando backups com padrão: " . $pattern);
            
            $files = glob($pattern, GLOB_NOSORT);
            error_log("Arquivos encontrados: " . print_r($files, true));
            
            foreach ($files as $file) {
                // Normaliza o caminho do arquivo
                $file = realpath($file);
                
                if (!file_exists($file)) {
                    continue;
                }
                
                $filename = basename($file);
                $date = str_replace(['db_backup_', '.sql'], '', $filename);
                $date = str_replace('_', ' ', $date);
                $date = str_replace('-', ':', $date);
                
                error_log("Encontrado backup: " . $file); // Log para debug
                
                $backups[] = [
                    'file' => $filename,
                    'date' => $date,
                    'size' => filesize($file),
                    'env_file' => str_replace('db_backup_', 'env_backup_', str_replace('.sql', '.txt', $filename))
                ];
            }
            
            // Ordena por data, mais recente primeiro
            usort($backups, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        }
        
        sendJsonResponse(true, 'Lista de backups disponíveis', $backups);
    } else {
        // Restauração do backup

        // Log para debug
        error_log("Recebido request para restaurar backup: " . print_r($_POST, true));
        
        $backupFile = basename($_POST['backup_file']); // Usa apenas o nome do arquivo
        
        // Valida a integridade do backup antes de prosseguir
        try {
            $fullPath = $backupDir . $backupFile;
            $integrityHandler->validateSqlBackup($fullPath);
            
            // Se houver backup de mídia associado
            $mediaBackupFile = str_replace('db_backup_', 'media_backup_', str_replace('.sql', '.zip', $backupFile));
            $mediaBackupPath = $backupDir . $mediaBackupFile;
            error_log("Verificando arquivo de mídia: " . $mediaBackupPath);
            
            if (file_exists($mediaBackupPath)) {
                error_log("Arquivo de mídia encontrado: " . $mediaBackupPath);
                error_log("Tamanho do arquivo: " . filesize($mediaBackupPath) . " bytes");
                
                // Valida a integridade do backup de mídia
                error_log("Iniciando validação do backup de mídia...");
                $integrityHandler->validateMediaBackup($mediaBackupPath);

                // Extrai o backup de mídia para a pasta correta
                $zip = new ZipArchive();
                if ($zip->open($backupDir . $mediaBackupFile) === TRUE) {
                    $mediaTargetDir = __DIR__ . '/assets/imagens/';
                    
                    // Verifica se o diretório existe e tem permissões corretas
                    if (!is_dir($mediaTargetDir)) {
                        if (!mkdir($mediaTargetDir, 0777, true)) {
                            throw new Exception("Não foi possível criar o diretório de mídia: " . $mediaTargetDir);
                        }
                    }
                    
                    // Verifica permissões de escrita
                    if (!is_writable($mediaTargetDir)) {
                        throw new Exception("Diretório de mídia não tem permissão de escrita: " . $mediaTargetDir);
                    }
                    
                    // Tenta extrair o arquivo ZIP
                    // Lista arquivos no ZIP antes da extração
                    error_log("Arquivos no ZIP:");
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        error_log(" - " . $filename);
                    }
                    
                    $extractResult = $zip->extractTo($mediaTargetDir);
                    
                    if ($extractResult === false) {
                        $errorMsg = "Falha ao extrair arquivo ZIP. Código de erro: " . $zip->getStatusString();
                        error_log($errorMsg);
                        throw new Exception($errorMsg);
                    }
                    
                    // Verifica se os arquivos foram extraídos
                    if ($handle = opendir($mediaTargetDir)) {
                        error_log("Conteúdo do diretório após extração:");
                        while (false !== ($entry = readdir($handle))) {
                            if ($entry != "." && $entry != "..") {
                                error_log(" - " . $entry);
                            }
                        }
                        closedir($handle);
                    }
                    $zip->close();
                    error_log("Backup de mídia restaurado em: " . $mediaTargetDir);
                } else {
                    error_log("Erro ao abrir o arquivo ZIP de mídia para restauração.");
                }
            }
            
            error_log("Validação de integridade concluída com sucesso");
        } catch (Exception $e) {
            throw new Exception('Falha na validação do backup: ' . $e->getMessage());
        }
        if (!preg_match('/^db_backup_.*\.sql$/', $backupFile)) {
            throw new Exception('Nome do arquivo de backup inválido: ' . $backupFile);
        }
        
        $fullPath = $backupDir . $backupFile;
        error_log("Caminho completo do backup: " . $fullPath);
        
        // Verifica se o arquivo existe e é legível
        if (!file_exists($fullPath)) {
            throw new Exception('Arquivo de backup não encontrado no caminho: ' . $fullPath);
        }
        
        if (!is_readable($fullPath)) {
            throw new Exception('Arquivo de backup não pode ser lido: ' . $fullPath);
        }
        
        // Verifica o tamanho do arquivo
        $fileSize = filesize($fullPath);
        if ($fileSize === 0) {
            throw new Exception('Arquivo de backup está vazio');
        }
        
        error_log("Arquivo de backup encontrado e válido: " . $fullPath . " (Tamanho: " . $fileSize . " bytes)");
        
        // Normaliza os paths para comparação
        $normalizedBackupDir = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, realpath($backupDir)), DIRECTORY_SEPARATOR);
        $normalizedFilePath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, realpath($fullPath));
        $normalizedFileDir = rtrim(dirname($normalizedFilePath), DIRECTORY_SEPARATOR);
        
        error_log("Verificando paths normalizados:");
        error_log("Dir esperado: " . $normalizedBackupDir);
        error_log("Dir atual: " . $normalizedFileDir);
        
        // Verifica se o arquivo está dentro do diretório de backups
        if ($normalizedFileDir !== $normalizedBackupDir) {
            throw new Exception('Arquivo de backup está fora do diretório permitido');
        }
        
        // Carrega as configurações do banco
        require_once 'config/database.php';
        
        try {
            error_log("Iniciando conexão com o banco de dados...");
            
            // Conecta ao banco
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            error_log("Conexão estabelecida. Iniciando transação...");
            
            // Verifica se não há transação ativa
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Inicia transação
            $pdo->beginTransaction();
            error_log("Transação iniciada com sucesso");
            
            // Desativa verificação de chaves estrangeiras
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            error_log("Chaves estrangeiras desativadas");
            
            // Lê o arquivo SQL
            $sql = file_get_contents($fullPath);
            if ($sql === false) {
                throw new Exception('Erro ao ler arquivo de backup');
            }
            
            error_log("Iniciando processamento do arquivo SQL...");
            
            // Primeiro, vamos desativar as chaves estrangeiras
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            
            // Obtém lista de todas as tabelas existentes
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Remove todas as tabelas existentes
            foreach ($tables as $table) {
                try {
                    $pdo->exec("DROP TABLE IF EXISTS `$table`");
                    error_log("Tabela $table removida com sucesso");
                } catch (PDOException $e) {
                    error_log("Erro ao remover tabela $table: " . $e->getMessage());
                    throw $e;
                }
            }
            
            error_log("Todas as tabelas existentes foram removidas");
            
            // Divide em statements, preservando delimitadores em stored procedures
            $delimiter = ';';
            $statements = array();
            $current = '';
            $inTransaction = true;
            
            foreach (explode("\n", $sql) as $line) {
                if (preg_match('/DELIMITER\s+(\S+)/', $line, $matches)) {
                    $delimiter = $matches[1];
                    continue;
                }
                
                $current .= $line . "\n";
                
                if (substr(trim($line), -strlen($delimiter)) === $delimiter) {
                    $statements[] = $current;
                    $current = '';
                }
            }
            
            // Adiciona último statement se houver
            if (trim($current) !== '') {
                $statements[] = $current;
            }
            
            error_log("Processando " . count($statements) . " statements SQL...");
            
            // Executa os statements
            try {
                foreach ($statements as $index => $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        try {
                            error_log("Executando statement " . ($index + 1) . " de " . count($statements));
                            $pdo->exec($statement);
                        } catch (PDOException $e) {
                            error_log("Erro na execução do SQL: " . $e->getMessage());
                            error_log("Statement problemático: " . $statement);
                            throw $e;
                        }
                    }
                }
                error_log("Todos os statements executados com sucesso");
            } catch (Exception $e) {
                error_log("Erro durante execução dos statements. Realizando rollback...");
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        
            // Reativa verificação de chaves estrangeiras
            error_log("Reativando verificação de chaves estrangeiras...");
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            
            // Log antes do commit
            error_log("Pronto para fazer commit da restauração");
            
            // Verifica se ainda está em transação
            if ($pdo->inTransaction()) {
                // Commit da transação
                $pdo->commit();
                error_log("Commit realizado com sucesso");
                
                // Verifica se as configurações da empresa foram restauradas
                $stmt = $pdo->query("SELECT COUNT(*) FROM company_settings");
                $configCount = $stmt->fetchColumn();
                if ($configCount == 0) {
                    error_log("AVISO: Tabela company_settings está vazia após restauração");
                    // Insere configurações padrão
                    $pdo->exec("INSERT INTO company_settings (id, company_name, company_description) VALUES (1, 'RR Imóveis', 'Há mais de 15 anos no mercado, a RR Imóveis se destaca pela excelência e compromisso com nossos clientes.')");
                }
                
                // Verifica se há pelo menos um usuário admin
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $adminCount = $stmt->fetchColumn();
                if ($adminCount == 0) {
                    error_log("AVISO: Nenhum usuário admin encontrado após restauração");
                    // Insere usuário admin padrão com senha: admin123
                    $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
                    $pdo->exec("INSERT INTO users (username, password, role, active) VALUES ('admin', '$defaultPassword', 'admin', 1)");
                }
            } else {
                error_log("Aviso: Não há transação ativa para commit");
            }
            
            // Se houver arquivo de configuração, restaura também
            $envFile = str_replace('db_backup_', 'env_backup_', str_replace('.sql', '.txt', $backupFile));
            $envPath = $backupDir . $envFile;
            
            if (file_exists($envPath)) {
                error_log("Encontrado arquivo de configuração para restaurar: " . $envPath);
                // Faz backup do arquivo atual antes de substituir
                if (!copy('config/database.php', 'config/database.php.bak')) {
                    error_log("Erro ao fazer backup de config/database.php");
                    throw new Exception('Erro ao fazer backup do arquivo de configuração');
                }
                // Ao restaurar, sobrescreve config/database.php com o conteúdo completo do backup
                $databasePhpBackup = $backupDir . 'database.php.backup';
                if (!file_exists($databasePhpBackup)) {
                    error_log("Arquivo database.php.backup não encontrado para restauração completa");
                    throw new Exception('Arquivo database.php.backup não encontrado para restauração');
                }
                $configContent = file_get_contents($databasePhpBackup);
                if ($configContent === false) {
                    error_log("Erro ao ler database.php.backup");
                    throw new Exception('Erro ao ler database.php.backup');
                }
                if (file_put_contents('config/database.php', $configContent) === false) {
                    error_log("Erro ao salvar novo arquivo de configuração completo");
                    throw new Exception('Erro ao salvar novo arquivo de configuração completo');
                }
                error_log("Arquivo de configuração restaurado COMPLETO com sucesso");
            }
            
            error_log("Processo de restauração concluído com sucesso");
            sendJsonResponse(true, 'Backup restaurado com sucesso');
            
        } catch (PDOException $e) {
            // Em caso de erro, faz rollback
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new Exception('Erro ao restaurar backup: ' . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Erro ao restaurar backup: ' . $e->getMessage());
}
?>
