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

    if (empty($secretKey)) {
        $message = 'Configuração Stripe não encontrada.';
    } else {
        try {
            require_once __DIR__ . '/vendor/autoload.php';
            $config = new \App\Infrastructure\Billing\StripeConfiguration($paymentsConfig);
            $apiClient = new \App\Infrastructure\Billing\StripeApiClient($config);
            $metadataService = new \App\Services\Billing\StripeSessionMetadataService($apiClient);
            $returnService = new \App\Services\Billing\StripeCheckoutReturnService($apiClient, $metadataService);

            $serviceResult = $returnService->handleReturn($sessionId);
            $result = $serviceResult['session'] ?? [];
            $metadata = $serviceResult['metadata'] ?? [];
            $tenantId = $serviceResult['tenant_id'] ?? null;
            $tenantPlan = $serviceResult['tenant_plan'] ?? null;

            if (empty($result) || empty($result['payment_status'])) {
                $message = 'Falha ao verificar pagamento Stripe.';
            } elseif ($result['payment_status'] !== 'paid') {
                $message = 'Pagamento ainda não foi confirmado. Status: ' . htmlspecialchars($result['payment_status']);
            } else {
                $tenantExists = false;

                if (!empty($tenantId)) {
                    $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
                    $tenant = $tenantRepo->getTenantById((int)$tenantId);
                    $tenantExists = $tenant !== null;
                }

                if ($tenantId && $tenantExists) {
                    $message = 'Pagamento confirmado em Stripe. A empresa #' . (int)$tenantId . ' foi identificada pela metadata da sessão. A ativação local permanece sob processamento do webhook autenticado.';
                    $status = 'success';
                } elseif ($tenantId && !$tenantExists) {
                    $message = 'Pagamento confirmado em Stripe, mas a empresa #' . (int)$tenantId . ' não foi encontrada no banco local. A ativação continua pendente do webhook autenticado.';
                    $status = 'warning';
                } else {
                    $message = 'Pagamento confirmado em Stripe, mas a empresa não pôde ser identificada a partir da metadata da sessão. A ativação continua pendente do webhook autenticado.';
                    $status = 'warning';
                }

                if (!empty($tenantPlan)) {
                    $message .= ' Plano detectado: ' . htmlspecialchars($tenantPlan, ENT_QUOTES, 'UTF-8') . '.';
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