<?php

/**
 * SCRIPT PARA BLOQUEAR TENANTS EXPIRADOS
 * Pode ser executado por CRON ou manually
 * 
 * USO:
 * Via CLI: php bloquear_tenants_expirados.php
 * Via HTTP: Visit http://yourdomain.com/bloquear_tenants_expirados.php
 * 
 * Recomendação: Executar via CRON a cada 1 hora
 * Exemplo cron: 0 * * * * /usr/bin/php /var/www/html/bloquear_tenants_expirados.php
 */

require_once 'config/database.php';

// Verificação de segurança básica
$allow_http = false; // HTTP desabilitado por padrão
if (PHP_SAPI !== 'cli') {
    if (!$allow_http) {
        http_response_code(403);
        die("Access denied");
    }

    require_once 'config/session_manager.php';
    if (!isLoggedIn() || !isAdmin() || empty($_SESSION['is_superadmin'])) {
        http_response_code(403);
        die("Access denied");
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Iniciando verificação de tenants expirados...\n";

try {
    // Buscar tenants expirados que ainda não foram bloqueados
    $stmt = $pdo->prepare("
        SELECT id, name, status, data_expiracao
        FROM tenants
        WHERE data_expiracao < NOW()
        AND status != 'inactive'
        ORDER BY data_expiracao DESC
    ");

    $stmt->execute();
    $expirados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($expirados)) {
        echo "[" . date('Y-m-d H:i:s') . "] Nenhum tenant expirado encontrado.\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Encontrados " . count($expirados) . " tenant(s) expirado(s).\n";

        $bloqueadosCount = 0;

        // Executar o bloqueio diretamente com PDO para remover a dependência do helper legacy
        try {
            $blockStmt = $pdo->prepare("
                UPDATE tenants
                SET status = 'inactive'
                WHERE data_expiracao < NOW()
                AND status != 'inactive'
            ");
            $result = $blockStmt->execute();
        } catch (Exception $e) {
            echo "[" . date('Y-m-d H:i:s') . "] ERRO ao executar bloqueio centralizado: " . $e->getMessage() . "\n";
            $result = false;
        }

        // Verificar status atual de cada tenant para produzir logs coerentes
        foreach ($expirados as $tenant) {
            try {
                $statusStmt = $pdo->prepare("SELECT status FROM tenants WHERE id = ? LIMIT 1");
                $statusStmt->execute([$tenant['id']]);
                $row = $statusStmt->fetch(PDO::FETCH_ASSOC);
                if ($row && $row['status'] === 'inactive') {
                    $bloqueadosCount++;
                    echo "[" . date('Y-m-d H:i:s') . "] ✓ Bloqueado: " . $tenant['name'] . " (ID: " . $tenant['id'] . ")\n";
                }
            } catch (Exception $e) {
                echo "[" . date('Y-m-d H:i:s') . "] ✗ Erro ao verificar status de " . $tenant['name'] . ": " . $e->getMessage() . "\n";
            }
        }

        echo "[" . date('Y-m-d H:i:s') . "] Totalmente bloqueados: " . $bloqueadosCount . "\n";
    }

    echo "[" . date('Y-m-d H:i:s') . "] Processo concluído com sucesso!\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
