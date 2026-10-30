<?php
require_once __DIR__ . '/config/database.php';

// Verificar se as tabelas existem
$result = $pdo->query("SHOW TABLES LIKE 'system_alerts'");
$alertsTable = $result->fetch();

$result = $pdo->query("SHOW TABLES LIKE 'scheduled_jobs'");
$jobsTable = $result->fetch();

echo "=== Migration Status ===\n";
echo "system_alerts: " . ($alertsTable ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
echo "scheduled_jobs: " . ($jobsTable ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";

if (!$alertsTable || !$jobsTable) {
    echo "\n⚠️  Migrations NOT run yet. Run: php migrations/run_migrations.php\n";
} else {
    echo "\n✅ Migrations already completed!\n";
}
