<?php
/**
 * Script de utilidade para executar migrations SQL
 * Uso restrito a administradores
 */

require_once __DIR__ . '/../config/database.php';

// Se recebido via CLI, permite execução direta
// Se recebido via HTTP, verifica autenticação
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../auth.php';
    
    // Apenas admin pode executar
    if (!isAdmin() || !isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
        die('Acesso negado');
    }
}

// Arquivo de migração a executar
$migrationFile = __DIR__ . '/../sql/migrations/001_create_security_tables.sql';

if (!file_exists($migrationFile)) {
    die('Arquivo de migração não encontrado: ' . htmlspecialchars($migrationFile));
}

try {
    $sql = file_get_contents($migrationFile);
    
    // Divide as queries por ponto-e-vírgula e remove comentários corretamente
    $queries = preg_split('/;[\s]*\n/', $sql);
    
    $successCount = 0;
    $errors = [];
    
    foreach ($queries as $query) {
        // Remove espaços em branco
        $query = trim($query);
        
        // Pula linhas vazias e comentários
        if (empty($query)) continue;
        
        // Remove comentários SQL
        $query = preg_replace('/--.*$/m', '', $query);
        $query = preg_replace('/\/\*.*?\*\//s', '', $query);
        $query = trim($query);
        
        if (empty($query)) continue;
        
        try {
            $pdo->exec($query);
            $successCount++;
            echo "✓ Query executada com sucesso<br>";
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            echo "✗ Erro: " . htmlspecialchars($e->getMessage()) . "<br>";
        }
    }
    
    echo "<hr>";
    echo "<strong>Resultado:</strong> $successCount queries executadas com sucesso<br>";
    
    if (!empty($errors)) {
        echo "<strong>Erros encontrados:</strong><br>";
        foreach ($errors as $error) {
            echo "- " . htmlspecialchars($error) . "<br>";
        }
    } else {
        echo "<span style='color: green;'><strong>✓ Todas as queries foram executadas sem erros!</strong></span><br>";
    }
    
} catch (Exception $e) {
    echo "Erro ao ler arquivo de migração: " . htmlspecialchars($e->getMessage());
}

?>
