<?php

/**
 * Backup automático do banco de dados usando mysqldump
 * Script seguro e confiável para fazer backup de dados críticos
 */

// Limpar qualquer espaço em branco ou saída anterior (CRÍTICO!)
if (ob_get_level()) {
    ob_end_clean();
}

// Se chamado via AJAX, retorna JSON
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
// Também verifica parâmetro GET como fallback
if (empty($isAjax) && !empty($_GET['ajax'])) {
    $isAjax = true;
}

// Para AJAX, capturar toda saída e retornar JSON
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    ob_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_manager.php';
require_once dirname(__DIR__) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(__DIR__) . '/auth.php';

function getRequestedTenantId()
{
    if (php_sapi_name() === 'cli' && isset($GLOBALS['argv'])) {
        foreach ($GLOBALS['argv'] as $arg) {
            if (strpos($arg, '--tenant-id=') === 0) {
                return intval(substr($arg, strlen('--tenant-id=')));
            }
            if (strpos($arg, '--tenant=') === 0) {
                return intval(substr($arg, strlen('--tenant=')));
            }
        }
    }

    if (function_exists('getRequestTenantId')) {
        $resolvedTenantId = (int)getRequestTenantId();
        if ($resolvedTenantId > 0) {
            return $resolvedTenantId;
        }
    }

    return null;
}

$requestedTenantId = getRequestedTenantId();
$tenantId = null;
$isSuperAdmin = false;

if ($requestedTenantId !== null) {
    $tenantId = $requestedTenantId;
} else {
    try {
        if (isset($pdo) && isset($_SESSION)) {
            initializeTenantMiddleware($pdo);
            $tenantId = getCurrentTenantId();
            $isSuperAdmin = isSuperAdmin();
        }
    } catch (Exception $e) {
        // Se não conseguir inicializar tenant, deixa o tenantId nulo e usa fallback
    }
}

if (empty($tenantId)) {
    $tenantId = 1;
}

$timestamp = date('Y-m-d_H-i-s');
$backupDir = dirname(__DIR__) . '/tools/backups';
// Incluir tenant_id no nome do arquivo para isolamento
$backupFile = $backupDir . '/db_backup_tenant_' . $tenantId . '_' . $timestamp . '.sql';

$result = [
    'success' => false,
    'message' => '',
    'backup_file' => '',
    'backup_size' => 0,
    'total_registros' => 0
];

// Criar diretório se não existir
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

if (!$isAjax) {
    echo "=== BACKUP DO BANCO DE DADOS ===" . PHP_EOL . PHP_EOL;
    echo "Data/Hora: " . date('d/m/Y H:i:s') . PHP_EOL;
    echo "Banco: " . getenv('DB_NAME') . PHP_EOL;
    echo "Local: " . $backupFile . PHP_EOL . PHP_EOL;
}

try {
    $dbHost = getenv('DB_HOST');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASS');
    $dbName = getenv('DB_NAME');

    backupManual($pdo, $backupFile, $dbName, $tenantId, $result);

    if (!file_exists($backupFile)) {
        throw new Exception("Arquivo de backup não foi criado");
    }

    $fileSize = filesize($backupFile);

    if ($fileSize === 0) {
        throw new Exception("Arquivo de backup está vazio");
    }

    if (!$isAjax) {
        echo PHP_EOL . "=== LIMPEZA DE BACKUPS ANTIGOS ===" . PHP_EOL;
    }

    $backups = glob($backupDir . '/db_backup_*.sql');
    rsort($backups);

    $mantidos = 0;
    $deletados = 0;

    foreach ($backups as $key => $backup) {
        if ($key >= 10) { // Manter apenas últimos 10
            unlink($backup);
            $deletados++;
            if (!$isAjax) {
                echo "✗ Deletado: " . basename($backup) . PHP_EOL;
            }
        } else {
            $mantidos++;
        }
    }

    if (!$isAjax) {
        echo PHP_EOL . "Backups mantidos: $mantidos" . PHP_EOL;
        echo "Backups deletados: $deletados" . PHP_EOL;
        echo PHP_EOL . "✓ Backup concluído com sucesso!" . PHP_EOL;
    }
} catch (Exception $e) {
    if (!$isAjax) {
        echo PHP_EOL . "=== ERRO ===" . PHP_EOL;
        echo "Status: ✗ FALHOU" . PHP_EOL;
        echo "Erro: " . $e->getMessage() . PHP_EOL;
    }

    $result['success'] = false;
    $result['message'] = $e->getMessage();
}

// Se é AJAX, retorna JSON
if ($isAjax) {
    ob_end_clean();
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Método alternativo de backup manual (fallback)
 */
function backupManual($pdo, $backupFile, $dbName, $tenantId, &$result)
{
    global $isAjax;

    $tenantTables = getTenantTables($pdo, $dbName);
    $tables = getAllTables($pdo, $dbName);

    $backupContent = "-- Backup do banco: " . $dbName . PHP_EOL;
    $backupContent .= "-- Data: " . date('d/m/Y H:i:s') . PHP_EOL;
    $backupContent .= "-- Tenant ID: " . $tenantId . PHP_EOL;
    $backupContent .= "-- Método: PHP Fallback (tenant-filtered)" . PHP_EOL;
    $backupContent .= "-- Total de tabelas: " . count($tables) . PHP_EOL;
    $backupContent .= "-- Charset: utf8mb4" . PHP_EOL;
    $backupContent .= "SET NAMES utf8mb4;" . PHP_EOL;
    $backupContent .= "SET CHARACTER_SET_CLIENT = utf8mb4;" . PHP_EOL;
    $backupContent .= "SET CHARACTER_SET_RESULTS = utf8mb4;" . PHP_EOL;
    $backupContent .= "SET FOREIGN_KEY_CHECKS = 0;" . PHP_EOL . PHP_EOL;

    $totalRegistros = 0;

    foreach ($tables as $table) {
        $safeTable = str_replace('`', '``', $table);

        // DROP TABLE
        $backupContent .= "DROP TABLE IF EXISTS `{$safeTable}`;" . PHP_EOL . PHP_EOL;

        // CREATE TABLE
        $createStmt = $pdo->query("SHOW CREATE TABLE `{$safeTable}`");
        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
        $backupContent .= $createRow['Create Table'] . ";" . PHP_EOL . PHP_EOL;

        // INSERT DATA
        $selectSql = "SELECT * FROM `{$safeTable}`";
        $params = [];

        if (in_array($table, $tenantTables, true)) {
            $selectSql .= " WHERE tenant_id = ?";
            $params[] = $tenantId;
        }

        $selectStmt = $pdo->prepare($selectSql);
        $selectStmt->execute($params);
        $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $columns = array_keys($rows[0]);
            $columnList = '`' . implode('`, `', $columns) . '`';

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $col) {
                    $value = $row[$col];
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $escapedValue = str_replace("'", "''", $value);
                        $values[] = "'" . $escapedValue . "'";
                    }
                }

                $backupContent .= "INSERT INTO `{$safeTable}` ($columnList) VALUES (" . implode(', ', $values) . ");" . PHP_EOL;
            }

            $rowCount = count($rows);
            $totalRegistros += $rowCount;
            if (!$isAjax) {
                echo "✓ $table: " . $rowCount . " registros" . PHP_EOL;
            }
        }

        $backupContent .= PHP_EOL;
    }

    $backupContent .= "SET FOREIGN_KEY_CHECKS = 1;" . PHP_EOL;

    file_put_contents($backupFile, $backupContent);
    $fileSize = filesize($backupFile);

    if (!$isAjax) {
        echo PHP_EOL . "=== RESULTADO ===" . PHP_EOL;
        echo "Status: ✓ SUCESSO (backup filtrado por tenant)" . PHP_EOL;
        echo "Arquivo: " . basename($backupFile) . PHP_EOL;
        echo "Tamanho: " . number_format($fileSize / 1024, 2) . " KB" . PHP_EOL;
        echo "Total de registros: " . $totalRegistros . PHP_EOL;
    }

    $result['success'] = true;
    $result['message'] = 'Backup realizado com sucesso (tenant filtered)!';
    $result['backup_file'] = basename($backupFile);
    $result['backup_size'] = $fileSize;
    $result['total_registros'] = $totalRegistros;
}

function getAllTables($pdo, $dbName)
{
    $stmt = $pdo->query('SHOW TABLES');
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getTenantTables($pdo, $dbName)
{
    $stmt = $pdo->prepare(
        'SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$dbName, 'tenant_id']);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
