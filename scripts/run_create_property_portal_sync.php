<?php
/**
 * Executa a migration sql/create_property_portal_sync.sql usando a conexão PDO do projeto.
 * Uso: php run_create_property_portal_sync.php
 */
require __DIR__ . '/../config/database.php';

$sqlFile = __DIR__ . '/../sql/create_property_portal_sync.sql';
if (!file_exists($sqlFile)) {
    echo "SQL file not found: {$sqlFile}\n";
    exit(2);
}

$sql = file_get_contents($sqlFile);
try {
    $pdo->exec($sql);
    echo "OK\n";
    exit(0);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
