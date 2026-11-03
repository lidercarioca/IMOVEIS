<?php

/**
 * Migration Runner
 * Executa migrações de banco de dados para o sistema de monitoramento
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

echo "=== Database Migration Runner ===\n";

function isSafeMigrationNoop(string $message): bool
{
    $normalized = strtolower($message);
    $safePatterns = [
        'already exists',
        'duplicate entry',
        'duplicate key name',
        'duplicate column name',
        'cannot add duplicate key',
        'key already exists',
        'index already exists',
    ];

    foreach ($safePatterns as $pattern) {
        if (strpos($normalized, $pattern) !== false) {
            return true;
        }
    }

    return false;
}

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
        '2026_11_03_create_tenant_trial_usage_table.sql',
        '2026_11_03_backfill_tenant_trial_usage.sql',
    ];

    foreach ($migrations as $migration) {
        $migrationPath = __DIR__ . '/../migrations/' . $migration;

        if (!file_exists($migrationPath)) {
            echo "✗ Migration file not found: {$migration}\n";
            continue;
        }

        echo "Executing migration: {$migration}\n";

        $sql = file_get_contents($migrationPath);
        $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql)), static fn($value) => $value !== '');

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    echo "  ✓ Statement executed\n";
                } catch (Exception $e) {
                    if (isSafeMigrationNoop($e->getMessage())) {
                        echo "  - Statement skipped (safe idempotent no-op)\n";
                        continue;
                    }

                    echo "  ✗ Error: " . $e->getMessage() . "\n";
                    throw $e;
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
