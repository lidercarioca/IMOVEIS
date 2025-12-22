<?php
require_once __DIR__ . '/../config/database.php';

$sql = "ALTER TABLE properties
  ADD COLUMN condominium DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN iptu DECIMAL(10,2) DEFAULT NULL,
  ADD COLUMN suites INT(11) DEFAULT NULL;";

try {
    $pdo->exec($sql);
    echo "Colunas adicionadas com sucesso\n";
} catch (Exception $e) {
    echo "Erro ao executar migration: " . $e->getMessage() . "\n";
}
