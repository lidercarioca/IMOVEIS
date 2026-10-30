<?php
// Hardening: este utilitário deve ser executado apenas via CLI
if (PHP_SAPI !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Access denied. This utility is CLI-only.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    error_log("[PIPELINE FIX] Corrigindo constraint de uniqueness em pipeline_stages");

    // 1. Remover constraint UNIQUE atual (se existir)
    try {
        $pdo->exec("ALTER TABLE pipeline_stages DROP INDEX name");
        error_log("[PIPELINE FIX] ✓ Constraint UNIQUE antiga removida");
    } catch (Exception $e) {
        error_log("[PIPELINE FIX] ℹ Constraint não encontrada ou já removida: " . $e->getMessage());
    }

    // 2. Adicionar constraint UNIQUE que inclui tenant_id
    try {
        $pdo->exec("ALTER TABLE pipeline_stages ADD UNIQUE KEY unique_tenant_name (tenant_id, name)");
        error_log("[PIPELINE FIX] ✓ Nova constraint UNIQUE (tenant_id, name) adicionada");
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            error_log("[PIPELINE FIX] ℹ Constraint já existe");
        } else {
            throw $e;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Constraints de pipeline_stages corrigidas',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    error_log("[PIPELINE FIX] ✗ Erro: " . $e->getMessage());
}
