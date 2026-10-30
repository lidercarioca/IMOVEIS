<?php
/**
 * Migration: Criar tabela lead_interactions
 * Rastreia todas as interações com leads (visualizações, chamadas, emails, status changes, etc)
 * 
 * Execução:
 * php migrations/create_lead_interactions_table.php
 */

require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();
    $pdo = $db->connect();
    
    // Criar tabela de interações
    $sql = "
    CREATE TABLE IF NOT EXISTS lead_interactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        lead_id INT NOT NULL,
        tenant_id INT NOT NULL,
        user_id INT,
        interaction_type VARCHAR(50) NOT NULL COMMENT 'viewed, called, emailed, sms, status_change, assigned, notes_added',
        title VARCHAR(255) NOT NULL COMMENT 'Título resumido da interação',
        description TEXT COMMENT 'Descrição detalhada',
        old_value VARCHAR(255) COMMENT 'Valor anterior (para status changes)',
        new_value VARCHAR(255) COMMENT 'Valor novo (para status changes)',
        metadata JSON COMMENT 'Dados adicionais (duração de chamada, assunto email, etc)',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        -- Constraints
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        
        -- Índices para performance
        KEY idx_lead_id (lead_id),
        KEY idx_tenant_id (tenant_id),
        KEY idx_user_id (user_id),
        KEY idx_type (interaction_type),
        KEY idx_created_at (created_at),
        KEY idx_lead_tenant (lead_id, tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT='Histórico de interações com leads';
    ";
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabela lead_interactions criada com sucesso',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    error_log("[MIGRATION] ✓ Tabela lead_interactions criada com sucesso");
    
} catch (PDOException $e) {
    // Verificar se já existe
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo json_encode([
            'success' => true,
            'message' => 'Tabela lead_interactions já existe',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        error_log("[MIGRATION] ℹ Tabela lead_interactions já existe");
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        error_log("[MIGRATION] ✗ Erro ao criar tabela: " . $e->getMessage());
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    error_log("[MIGRATION] ✗ Erro inesperado: " . $e->getMessage());
}
?>
