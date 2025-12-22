<?php
// Executar manualmente: php scripts/migrate_add_assigned_user.php
require_once __DIR__ . '/../config/database.php';
try {
    $res = $pdo->query("SHOW COLUMNS FROM properties LIKE 'assigned_user_id'")->fetchAll();
    if (count($res) === 0) {
        $pdo->exec("ALTER TABLE properties ADD COLUMN assigned_user_id INT(11) DEFAULT NULL AFTER zip");
        echo "Coluna assigned_user_id adicionada com sucesso.\n";
    } else {
        echo "Coluna assigned_user_id já existe.\n";
    }
} catch (PDOException $e) {
    echo "Erro ao migrar: " . $e->getMessage() . "\n";
}
?>
