<?php
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query("DESCRIBE properties");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colunas da tabela properties:\n\n";
    echo str_repeat("-", 80) . "\n";
    
    $newColumnsFound = [];
    foreach ($columns as $col) {
        echo sprintf("%-20s %-20s %-10s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null']
        );
        
        if (in_array($col['Field'], ['condominium', 'iptu', 'suites'])) {
            $newColumnsFound[] = $col['Field'];
        }
    }
    
    echo str_repeat("-", 80) . "\n";
    echo "\nColunas novos encontrados: " . (count($newColumnsFound) > 0 ? implode(", ", $newColumnsFound) : "NENHUM") . "\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
