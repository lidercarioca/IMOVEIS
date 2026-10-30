<?php

/**
 * Migration Runner
 * Executa migrações de banco de dados para o sistema de monitoramento
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

echo "=== Database Migration Runner ===\n";

try {
    // Verificar conexão com banco
    $stmt = $pdo->query("SELECT 1");
    echo "✓ Database connection successful\n";

    // Lista de migrações a executar
    $migrations = [
        'create_system_alerts_table.sql',
        'create_scheduled_jobs_table.sql',
        'add_missing_columns_to_scheduled_jobs.sql',
        'add_event_hash_to_webhook_events.sql',
        'create_webhook_audit_logs.sql',
        '2026_10_23_create_idempotency_requests_table.sql',
    ];

    foreach ($migrations as $migration) {
        $migrationPath = __DIR__ . '/../migrations/' . $migration;

        if (!file_exists($migrationPath)) {
            echo "✗ Migration file not found: {$migration}\n";
            continue;
        }

        echo "Executing migration: {$migration}\n";

        $sql = file_get_contents($migrationPath);

        // Executar múltiplas statements se houver
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    echo "  ✓ Statement executed\n";
                } catch (Exception $e) {
                    // Ignorar erros de "table already exists", "Duplicate entry", "Duplicate column name", "Duplicate key name" etc.
                    if (
                        strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate entry') === false &&
                        strpos($e->getMessage(), 'Duplicate column name') === false &&
                        strpos($e->getMessage(), 'Duplicate key name') === false
                    ) {
                        echo "  ✗ Error: " . $e->getMessage() . "\n";
                    } else {
                        echo "  - Statement skipped (already exists)\n";
                    }
                }
            }
        }

        echo "✓ Migration {$migration} completed\n\n";
    }

    echo "=== All migrations completed ===\n";
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
