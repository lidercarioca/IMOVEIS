<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * PÁGINA DE PLANOS E PAGAMENTO
 */

require_once 'config/session_manager.php';
require_once 'config/database.php';
require_once 'config/payments.php';
require_once 'app/security/Security.php';
require_once 'app/security/CSRF.php';
require_once 'middleware.php';
require_once __DIR__ . '/src/Infrastructure/Billing/TenantRepository.php';

$tenantBillingRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);

Security::setSecurityHeaders();
checkAuth();

// Permitir acesso a planos.php mesmo com tenant expirado (para renovação)
// Apenas verifica autenticação, não status do tenant
verificarAcessoTenant($redirectIfBlocked = false);

// Verificar se está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Se for superadmin e não tenha tenant selecionado, redirecionar
if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] && !isset($_SESSION['tenant_id'])) {
    header("Location: select_tenant.php");
    exit;
}

$tenantId = (int)(function_exists('getRequestTenantId') ? getRequestTenantId() : ($_SESSION['tenant_id'] ?? 0));
$isSuperAdmin = isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'];
$tenantInfo = $tenantBillingRepo->getTenantData($tenantId);
$planos = $tenantBillingRepo->getActivePlanCatalog();
$csrfToken = CSRF::generateToken();

$paymentsConfig = require 'config/payments.php';
$stripeConfig = $paymentsConfig['stripe'] ?? [];
$stripePortalAvailable = !empty($stripeConfig['secret_key']) && $tenantBillingRepo->hasStripeCustomer($tenantId, $stripeConfig);
$showStripePortalHelp = !empty($stripeConfig['secret_key']) && !$stripePortalAvailable;

// Processar seleção de plano
$mensagem = '';
$tipo_mensagem = '';

if (isset($_GET['cancel'])) {
    $mensagem = 'Pagamento cancelado. Nenhuma alteração foi realizada.';
    $tipo_mensagem = 'warning';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plano'])) {
    try {
        if (!isset($_POST['csrf_token']) || !CSRF::verifyToken($_POST['csrf_token'])) {
            throw new Exception('Token de segurança inválido. Atualize a página e tente novamente.');
        }
    } catch (Exception $e) {
        $mensagem = $e->getMessage();
        $tipo_mensagem = 'danger';
    }

    if (empty($mensagem)) {
        $planoSelecionado = Security::sanitizeInput($_POST['plano']);

        if (!isset($planos[$planoSelecionado])) {
            $mensagem = 'Plano inválido selecionado';
            $tipo_mensagem = 'danger';
        } elseif ($planoSelecionado !== 'trial') {
            $mensagem = 'Para planos pagos, utilize o checkout Stripe clicando no botão correspondente.';
            $tipo_mensagem = 'warning';
        } elseif ($tenantBillingRepo->hasTrialHistory($tenantId)) {
            $mensagem = 'Você já utilizou o período de trial. O trial está disponível apenas uma vez por empresa.';
            $tipo_mensagem = 'danger';
        } else {
            $diasTrial = $planos[$planoSelecionado]['dias_trial'] ?? 7;
            $resultado = $tenantBillingRepo->activateTrial($tenantId, $diasTrial);

            if ($resultado) {
                $mensagem = 'Trial iniciado com sucesso! Aproveite o período gratuito.';
                $tipo_mensagem = 'success';
                $_SESSION['tenant_plano'] = $planoSelecionado;
                $_SESSION['tenant_status'] = 'active';
                $tenantInfo = $tenantBillingRepo->getTenantData($tenantId);
            } else {
                $mensagem = 'Erro ao iniciar trial. Tente novamente mais tarde.';
                $tipo_mensagem = 'danger';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planos - RR Imóveis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .container {
            padding: 2rem 0;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        .page-header h1 {
            margin: 0;
            font-weight: 700;
        }

        .tenant-info {
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .plan-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .plan-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .plan-card:hover {
            border-color: #667eea;
            box-shadow: 0 1rem 2rem rgba(102, 126, 234, 0.2);
            transform: translateY(-5px);
        }

        .plan-card.popular {
            border-color: #667eea;
            box-shadow: 0 1rem 2rem rgba(102, 126, 234, 0.2);
        }

        .plan-card.popular::before {
            content: "POPULAR";
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .plan-card.current {
            border-color: #28a745;
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.05) 0%, rgba(40, 167, 69, 0.02) 100%);
        }

        .plan-card.current::after {
            content: "✓ PLANO ATUAL";
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #28a745;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .plan-price {
            font-size: 2.5rem;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 0.5rem;
            white-space: nowrap;
        }

        .plan-price small {
            display: inline-block;
            vertical-align: middle;
            margin-left: 0.35rem;
            font-size: 0.6rem;
            color: #666;
            line-height: 1;
        }

        .plan-desc {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .plan-features {
            text-align: left;
            margin-bottom: 1.5rem;
            flex-grow: 1;
        }

        .plan-features ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .plan-features li {
            padding: 0.5rem 0;
            color: #666;
            border-bottom: 1px solid #f0f0f0;
        }

        .plan-features li:last-child {
            border-bottom: none;
        }

        .plan-features li::before {
            content: "✓ ";
            color: #28a745;
            font-weight: 700;
            margin-right: 0.5rem;
        }

        .plan-action {
            margin-top: auto;
        }

        .btn-select-plan {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-select-plan:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }

        .btn-primary-plan {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary-plan:hover {
            background: linear-gradient(135deg, #5568d3 0%, #653a8a 100%);
            color: white;
        }

        .btn-secondary-plan {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary-plan:hover {
            background: #d0d0d0;
            color: #333;
        }

        .trial-banner {
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }

        .trial-banner strong {
            font-size: 1.1rem;
        }

        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="painel.php" class="back-link mb-3">← Voltar ao Painel</a>

        <div class="page-header">
            <h1>⚡ Escolha o Melhor Plano</h1>
            <p class="mb-0">Selecione um plano e comece a gerenciar seus imóveis</p>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($stripePortalAvailable): ?>
            <div class="mb-4 text-end">
                <button type="button" class="btn btn-outline-primary" onclick="openBillingPortal()">
                    Gerenciar Assinatura
                </button>
            </div>
        <?php elseif ($showStripePortalHelp): ?>
            <div class="mb-4 text-end">
                <div class="alert alert-info p-3" role="alert">
                    Para gerenciar sua assinatura no portal Stripe, realize primeiro um pagamento via checkout do plano.
                </div>
            </div>
        <?php endif; ?>

        <div id="stripeFeedback" class="alert d-none" role="alert" style="display:none;"></div>

        <?php if ($tenantInfo): ?>
            <div class="tenant-info">
                <strong>Empresa:</strong> <?php echo htmlspecialchars($tenantInfo['name'], ENT_QUOTES, 'UTF-8'); ?><br>
                <strong>Plano Atual:</strong>
                <span class="badge bg-info">
                    <?php echo strtoupper($tenantInfo['plano']); ?>
                </span>

                <?php if (($tenantInfo['plano'] ?? '') === 'trial'): ?>
                    <?php
                    $diasRestantes = 0;
                    if ($tenantInfo['data_expiracao']) {
                        $diasRestantes = $tenantBillingRepo->getDaysRemaining($tenantId);
                        if ($diasRestantes === null) {
                            $diasRestantes = 0;
                        }
                    }
                    ?>
                    <div class="trial-banner mt-3">
                        <strong>⏰ Você está aproveitando o período de TRIAL!</strong><br>
                        <small>
                            <?php
                            if ($diasRestantes > 0) {
                                echo "Faltam <strong>$diasRestantes dias</strong> para seu trial expirar.";
                            } else {
                                echo "Seu trial já expirou. Escolha um plano para continuar.";
                            }
                            ?>
                        </small>
                    </div>
                <?php elseif ($tenantInfo['status'] === 'active'): ?>
                    <small class="text-muted">
                        Válido até: <strong><?php echo date('d/m/Y', strtotime($tenantInfo['data_expiracao'])); ?></strong>
                    </small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="plan-grid">
            <?php foreach ($planos as $chave => $plano): ?>
                <?php
                $isCurrent = ($tenantInfo && $tenantInfo['plano'] === $chave);
                $isPopular = ($chave === 'profissional');
                ?>
                <div class="plan-card <?php echo $isPopular ? 'popular' : ''; ?> <?php echo $isCurrent ? 'current' : ''; ?>">
                    <div class="plan-name">
                        <?php echo htmlspecialchars($plano['nome'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>

                    <div class="plan-price">
                        <?php
                        if ($chave === 'trial') {
                            echo 'GRÁTIS';
                        } else {
                            echo 'R$ ' . number_format($plano['valor_mensal'] ?? 0, 2, ',', '.');
                        }
                        ?>
                        <small>/mês</small>
                    </div>

                    <div class="plan-desc">
                        <?php if ($chave === 'trial'): ?>
                            Período de testes
                        <?php else: ?>
                            Renovação mensal
                        <?php endif; ?>
                    </div>

                    <div class="plan-features">
                        <ul>
                            <li>
                                Até <strong><?php echo $plano['limite_usuarios']; ?> usuários</strong>
                            </li>
                            <li>
                                Até <strong><?php echo $plano['limite_imoveis'] ?? 0; ?> imóveis</strong>
                            </li>
                            <?php if ($chave !== 'trial'): ?>
                                <li>Suporte por email</li>
                                <li>Atualizações regulares</li>
                                <li>Backup automático</li>
                            <?php else: ?>
                                <li>7 dias de teste gratuito</li>
                                <li>Acesso completo</li>
                                <li>Sem cartão de crédito</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div class="plan-action">
                        <?php
                        $planoExpirado = $tenantBillingRepo->isExpired($tenantId);
                        $currentPlanActive = ($tenantInfo && $tenantInfo['status'] === 'active' && !$planoExpirado);
                        // Nova regra: permitir renovação do plano atual se o plano expirou OU se o sistema já está emitindo o aviso de vencimento
                        $allowRenewCurrent = false;
                        if ($isCurrent) {
                            $allowRenewCurrent = $planoExpirado || $tenantBillingRepo->shouldShowExpiryWarning($tenantId);
                        }

                        $isDowngrade = false;
                        if (!$isSuperAdmin && $tenantInfo && $tenantInfo['plano'] && $tenantInfo['plano'] !== 'trial' && $chave !== 'trial' && $currentPlanActive) {
                            $isDowngrade = $tenantBillingRepo->isPlanDowngrade($tenantInfo['plano'], $chave, $planos);
                        }

                        $trialJaUtilizado = ($chave === 'trial' && $tenantBillingRepo->hasTrialHistory($tenantId));
                        ?>
                        <?php if ($isCurrent && !$allowRenewCurrent): ?>
                            <button class="btn btn-select-plan btn-secondary-plan w-100" disabled>
                                Plano Atual
                            </button>
                        <?php elseif ($trialJaUtilizado): ?>
                            <button class="btn btn-select-plan w-100" disabled style="background: #6c757d; color: white; cursor: not-allowed;">
                                Trial Já Utilizado
                            </button>
                        <?php elseif ($isDowngrade): ?>
                            <button class="btn btn-select-plan w-100" disabled style="background: #6c757d; color: white; cursor: not-allowed;">
                                Downgrade não permitido enquanto o plano atual estiver ativo
                            </button>
                        <?php else: ?>
                            <?php if ($chave === 'trial'): ?>
                                <form method="POST" style="display: none;" id="form-<?php echo $chave; ?>">
                                    <input type="hidden" name="plano" value="<?php echo htmlspecialchars($chave, ENT_QUOTES); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
                                </form>
                                <button type="button" class="btn btn-select-plan btn-primary-plan w-100"
                                    onclick="document.getElementById('form-<?php echo $chave; ?>').submit();">
                                    <?php echo ($allowRenewCurrent) ? 'Renovar Plano Atual' : 'Iniciar Trial'; ?>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-select-plan btn-primary-plan w-100"
                                    onclick="handlePlanSelection('<?php echo $chave; ?>')">
                                    <?php echo ($allowRenewCurrent) ? 'Renovar Plano Atual' : 'Escolher Plano'; ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center text-muted mt-4">
            <small>
                Todos os planos incluem acesso completo. Você pode fazer upgrades a qualquer momento, mas downgrades não são permitidos. O período de trial está disponível apenas uma vez por empresa.
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const stripeApiUrl = 'api/v1/billing/createStripeCheckoutSession.php';
        const stripeCsrfToken = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
        const stripeFeedback = document.getElementById('stripeFeedback');

        function showStripeFeedback(message, type = 'danger') {
            if (!stripeFeedback) {
                alert(message);
                return;
            }
            stripeFeedback.textContent = message;
            stripeFeedback.className = 'alert alert-' + type;
            stripeFeedback.style.display = 'block';
        }

        async function handlePlanSelection(plan) {
            showStripeFeedback('Redirecionando para o Stripe...', 'info');

            try {
                const response = await fetch(stripeApiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        plan,
                        csrf_token: stripeCsrfToken
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao criar sessão de checkout.');
                }

                if (!data.checkout_url) {
                    throw new Error('URL de checkout não retornada pelo servidor.');
                }

                window.location.href = data.checkout_url;
            } catch (error) {
                showStripeFeedback(error.message || 'Falha ao iniciar pagamento.', 'danger');
            }
        }

        async function openBillingPortal() {
            showStripeFeedback('Abrindo o portal de cobrança Stripe...', 'info');

            try {
                const response = await fetch('api/v1/billing/createStripeBillingPortalSession.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: stripeCsrfToken
                    })
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Erro ao abrir o portal de cobrança.');
                }

                if (!data.portal_url) {
                    throw new Error('URL do portal não retornada pelo servidor.');
                }

                window.location.href = data.portal_url;
            } catch (error) {
                showStripeFeedback(error.message || 'Falha ao abrir o portal de cobrança.', 'danger');
            }
        }
    </script>
</body>

</html>