<?php

/**
 * SCRIPT PARA NOTIFICAR TENANTS QUE EXPIRAM EM 3 DIAS
 * Pode ser executado por CRON apenas (HTTP desabilitado)
 * 
 * USO:
 * Via CLI: php notificar_expiracao_tenants.php
 * 
 * Recomendação: Executar via CRON uma vez por dia
 * Exemplo cron: 0 8 * * * /usr/bin/php /var/www/html/notificar_expiracao_tenants.php
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once 'config/database.php';

// Verificação de segurança básica
$allow_http = false; // HTTP desabilitado por padrão (NÃO MUDAR)
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

echo "[" . date('Y-m-d H:i:s') . "] Iniciando notificação de tenants por expirar...\n";

try {
    $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
    $userRepo = new \App\Infrastructure\User\UserRepository($pdo);
    $mailer = new \App\Infrastructure\Mail\MailerService();
    $notificationLogRepo = new \App\Infrastructure\Billing\NotificationLogRepository($pdo);
    $billingNotificationService = new \App\Services\Billing\BillingNotificationService($tenantRepo, $userRepo, $mailer, $notificationLogRepo);

    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE status = 'active' AND data_expiracao IS NOT NULL AND data_expiracao > NOW() AND data_expiracao < DATE_ADD(NOW(), INTERVAL 4 DAY) AND data_expiracao > DATE_SUB(NOW(), INTERVAL 1 DAY) AND id NOT IN (SELECT tenant_id FROM tenant_notification_log WHERE tipo = 'expiracao_3dias' AND data_envio > DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'enviado')");
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($tenants);
    $notificados = 0;
    $erros = 0;

    echo "[" . date('Y-m-d H:i:s') . "] Encontrados {$total} tenants para notificar\n";

    foreach ($tenants as $tenant) {
        $resultado = $billingNotificationService->sendExpirationWarning((int)$tenant['id']);

        if ($resultado['success']) {
            $notificados++;
            echo "[" . date('Y-m-d H:i:s') . "] ✓ Tenant ID {$tenant['id']}: " . ($resultado['message'] ?? 'Email enviado com sucesso') . "\n";
        } else {
            $erros++;
            echo "[" . date('Y-m-d H:i:s') . "] ✗ Tenant ID {$tenant['id']}: " . ($resultado['message'] ?? 'Erro ao enviar notificação') . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Processo concluído!\n";
    echo "[" . date('Y-m-d H:i:s') . "] Total encontrados: " . $total . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Notificados com sucesso: " . $notificados . "\n";
    echo "[" . date('Y-m-d H:i:s') . "] Erros: " . $erros . "\n";

    exit(0);
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
