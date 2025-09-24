<?php
require_once __DIR__ . '/../config/database.php';

try {
    // 1. Teste de Conexão
    echo "=== Teste de Conexão ===\n";
    $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
    echo "✓ Conexão OK\n\n";

    // 2. Teste de Consulta
    echo "=== Teste de Consulta ===\n";
    $sql = "SELECT * FROM banners WHERE active = 1 ORDER BY order_position, id DESC";
    $stmt = $pdo->query($sql);
    $total = $stmt->rowCount();
    echo "✓ Query OK - {$total} banners encontrados\n\n";

    // 3. Teste de Dados
    echo "=== Teste de Dados ===\n";
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($banners as $banner) {
        echo "ID: {$banner['id']} - {$banner['title']}\n";
        echo "- Image: {$banner['image_path']}\n";
        echo "- Order: {$banner['order_position']}\n";
        echo "-------------------\n";
    }

} catch (PDOException $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Todos os testes passaram com sucesso!\n";