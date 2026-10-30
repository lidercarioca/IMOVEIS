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
register_shutdown_function(function () {
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
function sendJsonResponse($success, $message = '', $data = null)
{
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

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/helpers/tenant_isolation_helpers.php';
require_once __DIR__ . '/../app/handlers/BackupIntegrityHandler.php';

checkAuth();

// Inicializar tenant isolamento
try {
    initializeTenantMiddleware($pdo);
    $tenantId = getCurrentTenantId();
    $isSuperAdmin = isSuperAdmin();
} catch (Exception $e) {
    // Se não conseguir inicializar, usa tenant 1
    $tenantId = 1;
    $isSuperAdmin = false;
}

if (!isAdmin()) {
    // Não registrar no log - silenciosamente retornar como se não houvesse dados
    // Mantém a segurança sem expor tentativa de acesso negado
    if (!isset($_POST['backup_file'])) {
        // GET - listar backups (retorna lista vazia)
        sendJsonResponse(true, 'Nenhum backup disponível', [
            'backups' => [],
            'total' => 0
        ]);
    } else {
        // POST - restaurar (retorna sucesso falso silencioso)
        sendJsonResponse(true, 'Operação não realizada', [
            'message' => 'Nenhum dado disponível para restauração'
        ]);
    }
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
            // Mostrar apenas backups do tenant onde está logado (seja superadmin ou admin comum)
            $pattern = $backupDir . "db_backup_tenant_{$tenantId}_*.sql";

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

                // Parse do novo formato: db_backup_tenant_X_YYYY-MM-DD_HH-mm-ss.sql
                // ou formato antigo: db_backup_YYYY-MM-DD_HH-mm-ss.sql
                if (preg_match('/db_backup_tenant_(\d+)_(.+)\.sql$/', $filename, $matches)) {
                    $backupTenantId = $matches[1];
                    $date = $matches[2];
                } else {
                    // Formato legado
                    $date = str_replace(['db_backup_', '.sql'], '', $filename);
                    $backupTenantId = 1; // Asumir tenant 1 para backups legados
                }

                $date = str_replace('_', ' ', $date);
                $date = str_replace('-', ':', $date);

                error_log("Encontrado backup: " . $file); // Log para debug

                $backups[] = [
                    'file' => $filename,
                    'date' => $date,
                    'size' => filesize($file),
                    'tenant_id' => $backupTenantId,
                    'env_file' => str_replace('db_backup_', 'env_backup_', str_replace('.sql', '.txt', $filename))
                ];
            }

            // Ordena por data, mais recente primeiro
            usort($backups, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
        }

        sendJsonResponse(true, 'Lista de backups disponíveis', $backups);
    } else {
        // Restauração do backup

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
                    $mediaTargetDir = __DIR__ . '/../assets/imagens/';

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

        // Verificar isolamento de tenant
        if (!$isSuperAdmin) {
            // Usuário comum deve restaurar apenas seus próprios backups
            if (
                !preg_match('/^db_backup_tenant_' . preg_quote($tenantId, '/') . '_/', $backupFile) &&
                !preg_match('/^db_backup_[^_]/', $backupFile)
            ) { // Permite backups antigos para compatibilidade
                throw new Exception('Você não tem permissão para restaurar este backup (tenant mismatch)');
            }
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
        require_once __DIR__ . '/../config/database.php';

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

            $isTenantBackup = false;
            $backupTenantId = null;
            if (preg_match('/^db_backup_tenant_(\d+)_/', $backupFile, $matches)) {
                $isTenantBackup = true;
                $backupTenantId = intval($matches[1]);
            }

            // Divide em statements, preservando delimitadores em stored procedures
            $delimiter = ';';
            $statements = array();
            $current = '';

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

            try {
                if ($isTenantBackup) {
                    // Restauração por tenant: remove apenas dados do tenant e insere os registros do backup
                    $tenantTables = getTenantTables($pdo, $dbname);

                    if (empty($tenantTables)) {
                        throw new Exception('Não foi possível determinar as tabelas com tenant_id');
                    }

                    foreach ($tenantTables as $table) {
                        $safeTable = str_replace('`', '``', $table);
                        error_log("Deletando dados do tenant {$backupTenantId} na tabela {$safeTable}");
                        $pdo->exec("DELETE FROM `{$safeTable}` WHERE tenant_id = " . intval($backupTenantId));
                    }

                    $insertStatements = [];
                    foreach ($statements as $statement) {
                        $trimmed = trim($statement);
                        if (empty($trimmed)) {
                            continue;
                        }

                        if (preg_match('/^(INSERT|REPLACE)\s+INTO\s+`?(\w+)`?/i', $trimmed, $matches)) {
                            $table = $matches[2];
                            if (in_array($table, $tenantTables, true)) {
                                $insertStatements[] = $trimmed;
                            } else {
                                error_log("Ignorando INSERT de tabela não-tenant: {$table}");
                            }
                        } else {
                            error_log("Ignorando statement não-INSERT para restore por tenant: " . substr($trimmed, 0, 100));
                        }
                    }

                    if (empty($insertStatements)) {
                        throw new Exception('Nenhum INSERT encontrado no backup para restaurar');
                    }

                    foreach ($insertStatements as $index => $statement) {
                        try {
                            $statementToExecute = transformInsertForTenantRestore($statement);
                            error_log("Executando INSERT " . ($index + 1) . " de " . count($insertStatements));
                            error_log("Statement SQL: " . substr($statementToExecute, 0, 300));
                            $pdo->exec($statementToExecute);
                        } catch (PDOException $e) {
                            error_log("Erro na execução do INSERT: " . $e->getMessage());
                            error_log("Statement problemático: " . $statementToExecute);
                            throw $e;
                        }
                    }

                    error_log("Todos os INSERTs do restore por tenant foram executados com sucesso");
                } else {
                    // Restauração completa (legacy ou full restore)
                    $stmt = $pdo->query("SHOW TABLES");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
                }
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
                    // Cria usuário admin padrão usando UserService (centralizado)
                    try {
                        require_once __DIR__ . '/../src/Infrastructure/User/UserRepository.php';
                        require_once __DIR__ . '/../src/Application/User/UserService.php';

                        $userRepository = new \App\Infrastructure\User\UserRepository($pdo);
                        $userService = new \App\Application\User\UserService($userRepository);

                        // Cria admin com tenant_id=1 (tenant padrão/sistema)
                        $userCreated = $userService->createUser(
                            1,  // tenantId
                            [   // userData
                                'username' => 'admin',
                                'email' => 'admin@system.local',
                                'password' => 'admin123',
                                'name' => 'Administrador do Sistema',
                                'role' => 'admin',
                                'active' => 1
                            ],
                            true  // isSystemCreation
                        );
                        error_log("✓ Usuário admin criado com sucesso via UserService (ID: {$userCreated['id']}, Username: {$userCreated['username']})");
                    } catch (\Exception $e) {
                        error_log("✗ Erro ao criar admin via UserService: " . $e->getMessage());
                        // Fallback: criar via INSERT direto como último recurso (compatibilidade)
                        $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
                        $pdo->exec("INSERT INTO users (tenant_id, username, email, password, role, name, active, is_superadmin, created_at) 
                                   VALUES (1, 'admin', 'admin@system.local', '$defaultPassword', 'admin', 'Administrador do Sistema', 1, 0, NOW())");
                        error_log("⚠️ Admin criado via fallback (INSERT direto)");
                    }
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
                $configPath = __DIR__ . '/../config/database.php';
                $configBackupPath = __DIR__ . '/../config/database.php.bak';
                if (!copy($configPath, $configBackupPath)) {
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
                if (file_put_contents($configPath, $configContent) === false) {
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

function transformInsertForTenantRestore($statement)
{
    if (stripos($statement, 'ON DUPLICATE KEY UPDATE') !== false) {
        return $statement;
    }

    if (!preg_match('/^\s*(INSERT|REPLACE)\s+INTO\s+`?(\w+)`?\s*\(([^)]+)\)\s+VALUES\s*\(/i', $statement, $matches)) {
        return $statement;
    }

    $columns = array_map(function ($column) {
        return trim(trim($column), "` ");
    }, explode(',', $matches[3]));

    if (empty($columns)) {
        return $statement;
    }

    $updates = [];
    foreach ($columns as $column) {
        $safeColumn = str_replace('`', '``', $column);
        $updates[] = "`{$safeColumn}` = VALUES(`{$safeColumn}`)";
    }

    if (empty($updates)) {
        return $statement;
    }

    $trimmed = rtrim($statement);
    if (substr($trimmed, -1) === ';') {
        $trimmed = substr($trimmed, 0, -1);
    }

    return $trimmed . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates) . ';';
}

function getTenantTables($pdo, $dbName)
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$dbName, 'tenant_id']);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
