<?php
/**
 * Script para aplicar migrations ao banco de dados
 * Deve ser executado uma única vez
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    error_log("[MIGRATION] Iniciando aplicação de migrations");
    
    // 1. Adicionar coluna read_at em notifications
    $queries = [
        [
            'name' => 'Adicionar coluna read_at em notifications',
            'sql' => "ALTER TABLE notifications ADD COLUMN read_at DATETIME NULL DEFAULT NULL"
        ],
        [
            'name' => 'Adicionar coluna dismissed_at em notifications',
            'sql' => "ALTER TABLE notifications ADD COLUMN dismissed_at DATETIME NULL DEFAULT NULL"
        ],
        [
            'name' => 'Criar índice em read_at',
            'sql' => "ALTER TABLE notifications ADD INDEX idx_read_at (read_at)"
        ],
        [
            'name' => 'Criar índice em dismissed_at',
            'sql' => "ALTER TABLE notifications ADD INDEX idx_dismissed_at (dismissed_at)"
        ],
        [
            'name' => 'Adicionar coluna stripe_price_id em plans',
            'sql' => "ALTER TABLE plans ADD COLUMN stripe_price_id VARCHAR(255) DEFAULT NULL COMMENT 'Stripe Price ID para billing'"
        ]
    ];

    $results = [];
    foreach ($queries as $query) {
        try {
            $pdo->exec($query['sql']);
            $results[] = [
                'success' => true,
                'migration' => $query['name'],
                'message' => 'Aplicada com sucesso'
            ];
            error_log("[MIGRATION] ✓ " . $query['name']);
        } catch (PDOException $e) {
            // Se for erro de coluna/índice já existente, ignorar
            if (strpos($e->getMessage(), 'Duplicate') !== false || 
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'already exist') !== false) {
                $results[] = [
                    'success' => true,
                    'migration' => $query['name'],
                    'message' => 'Já existe (pulado)'
                ];
                error_log("[MIGRATION] ℹ " . $query['name'] . " (já existe)");
            } else {
                $results[] = [
                    'success' => false,
                    'migration' => $query['name'],
                    'error' => $e->getMessage()
                ];
                error_log("[MIGRATION] ✗ " . $query['name'] . " - " . $e->getMessage());
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Migrations processadas',
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
