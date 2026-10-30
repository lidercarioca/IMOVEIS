<?php
require_once 'config/session_manager.php';
require_once 'config/database.php';
require_once 'config/load_env.php';
require_once 'config/payments.php';
require_once 'app/security/Security.php';
require_once 'app/security/CSRF.php';
require_once 'auth.php';
require_once 'app/security/TenantContext.php';

Security::setSecurityHeaders();
// Não forçamos o checkAuth aqui, pois a sessão pode ser perdida no retorno do Stripe Checkout.
// Vamos validar diretamente a sessão Stripe e restabelecer a sessão do usuário se possível.

function getProcessedStripeSessionFile(): string
{
    return __DIR__ . '/logs/stripe-processed-sessions.log';
}

function isStripeSessionAlreadyProcessed(string $sessionId): bool
{
    $file = getProcessedStripeSessionFile();
    if (!file_exists($file)) {
        return false;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($sessionId, $lines, true);
}

function markStripeSessionAsProcessed(string $sessionId): bool
{
    $file = getProcessedStripeSessionFile();
    if (isStripeSessionAlreadyProcessed($sessionId)) {
        return true;
    }
    return (bool)@file_put_contents($file, $sessionId . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function restoreSessionFromStripeMetadata(array $metadata): bool
{
    global $pdo;

    if (empty($metadata['user_id'])) {
        return false;
    }

    $userId = (int)$metadata['user_id'];
    $stmt = $pdo->prepare('SELECT id, username, email, role, tenant_id, is_superadmin FROM users WHERE id = ? AND active = 1 LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return false;
    }

    session_unset();
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['tenant_id'] = $user['tenant_id'];
    $_SESSION['is_superadmin'] = (int)($user['is_superadmin'] ?? 0);
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();

    TenantContext::initialize();
    return true;
}

TenantContext::initialize();

$sessionId = trim($_GET['session_id'] ?? '');
$message = '';
$status = 'danger';

if (empty($sessionId)) {
    $message = 'Sessão Stripe não encontrada.';
} else {
    $paymentsConfig = require __DIR__ . '/config/payments.php';
    $stripeConfig = $paymentsConfig['stripe'] ?? [];
    $secretKey = $stripeConfig['secret_key'] ?? '';
    $siteUrl = rtrim($stripeConfig['site_url'] ?? '', '/');

    if (empty($secretKey)) {
        $message = 'Configuração Stripe não encontrada.';
    } else {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $paymentsConfig = require __DIR__ . '/config/payments.php';
            $config = new \App\Infrastructure\Billing\StripeConfiguration($paymentsConfig);
            $apiClient = new \App\Infrastructure\Billing\StripeApiClient($config);
            $metadataService = new \App\Services\Billing\StripeSessionMetadataService($apiClient);
            $returnService = new \App\Services\Billing\StripeCheckoutReturnService($apiClient, $metadataService);

            $serviceResult = $returnService->handleReturn($sessionId);
            $result = $serviceResult['session'] ?? [];
            $metadata = $serviceResult['metadata'] ?? [];

            if (empty($result) || empty($result['payment_status'])) {
                $message = 'Falha ao verificar pagamento Stripe.';
            } elseif ($result['payment_status'] !== 'paid') {
                $message = 'Pagamento ainda não foi confirmado. Status: ' . htmlspecialchars($result['payment_status']);
            } else {
                $tenantId = TenantContext::getTenantId();

                if (!$tenantId && !empty($metadata)) {
                    restoreSessionFromStripeMetadata($metadata);
                    $tenantId = TenantContext::getTenantId();
                }

                if (!$tenantId) {
                    $message = 'Tenant não identificado.';
                } else {
                    try {
                        $paymentRepo = new \App\Infrastructure\Billing\PaymentRepository(null, $pdo);
                        $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
                        $tenantBillingService = new \App\Services\Billing\TenantBillingService($paymentRepo, $tenantRepo, null, $pdo);
                        $tenantBillingService->syncStripeIdentifiers($tenantId, $result['customer'] ?? null, $result['subscription'] ?? null);
                    } catch (\Throwable $e) {
                        error_log('Stripe success sync identifiers error: ' . $e->getMessage());
                    }

                    $message = 'Pagamento confirmado. A ativação será concluída em alguns instantes após o processamento do webhook Stripe.';
                    $status = 'success';
                }
            }
        } catch (\Throwable $e) {
            $message = 'Erro de comunicação com Stripe: ' . $e->getMessage();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento Stripe - RR Imóveis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h1 class="h3 mb-4">Retorno de Pagamento</h1>
                        <div class="alert alert-<?php echo $status === 'success' ? 'success' : 'danger'; ?>">
                            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <a href="planos.php" class="btn btn-primary">Voltar para Planos</a>
                        <a href="painel.php" class="btn btn-secondary ms-2">Ir para Painel</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>