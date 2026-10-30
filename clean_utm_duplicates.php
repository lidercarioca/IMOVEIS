<?php
require 'config/database.php';

try {
    echo "=== Verificando dados duplicados em utm_campaigns ===\n\n";
    
    // Encontrar duplicados
    $stmt = $pdo->query("
        SELECT tenant_id, campaign_name, COUNT(*) as total
        FROM utm_campaigns
        GROUP BY tenant_id, campaign_name
        HAVING COUNT(*) > 1
        ORDER BY total DESC
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "✓ Nenhum duplicado encontrado!\n";
        exit(0);
    }
    
    echo "Duplicados encontrados:\n";
    foreach ($duplicates as $dup) {
        echo "  - Tenant ID: {$dup['tenant_id']} | Campaign: {$dup['campaign_name']} | Quantidade: {$dup['total']}\n";
    }
    
    echo "\n=== Removendo duplicados ===\n\n";
    
    // Para cada duplicado, manter apenas o mais recente (maior ID)
    foreach ($duplicates as $dup) {
        $stmt = $pdo->prepare("
            SELECT id FROM utm_campaigns 
            WHERE tenant_id = ? AND campaign_name = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$dup['tenant_id'], $dup['campaign_name']]);
        $keepId = $stmt->fetchColumn();
        
        // Deletar todos os outros
        $stmt = $pdo->prepare("
            DELETE FROM utm_campaigns 
            WHERE tenant_id = ? AND campaign_name = ? AND id != ?
        ");
        $stmt->execute([$dup['tenant_id'], $dup['campaign_name'], $keepId]);
        
        $deleted = $stmt->rowCount();
        echo "  ✓ Campaign '{$dup['campaign_name']}' (Tenant {$dup['tenant_id']}): {$deleted} duplicados removidos\n";
    }
    
    echo "\n=== Verificação final ===\n\n";
    
    // Verificar se ainda há duplicados
    $stmt = $pdo->query("
        SELECT COUNT(*) as total_campaigns FROM utm_campaigns
    ");
    $total = $stmt->fetchColumn();
    echo "Total de campanhas agora: {$total}\n";
    
    $stmt = $pdo->query("
        SELECT tenant_id, campaign_name, COUNT(*) as total
        FROM utm_campaigns
        GROUP BY tenant_id, campaign_name
        HAVING COUNT(*) > 1
    ");
    $stillDuplicates = $stmt->fetchAll();
    
    if (empty($stillDuplicates)) {
        echo "✓ Nenhum duplicado restante!\n";
        echo "\n✅ Problema resolvido! Agora você pode criar campanhas sem erros.\n";
    } else {
        echo "⚠ Ainda há duplicados! Algo deu errado.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
