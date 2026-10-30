<?php

/**
 * PÁGINA DE TENANT BLOQUEADO
 * Exibida quando o acesso ao tenant é bloqueado
 */

require_once 'config/session_manager.php';
require_once 'config/database.php';
require_once 'app/security/Security.php';
require_once 'middleware.php';

Security::setSecurityHeaders();
checkAuth();

// Se não está logado, redirecionar para login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$motivo = $_SESSION['bloqueio_motivo'] ?? 'Acesso bloqueado';
$tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : ((isset($_SESSION['selected_tenant_id']) ? (int)$_SESSION['selected_tenant_id'] : null));
if (!$tenantId && function_exists('getRequestTenantId')) {
    $tenantId = (int)getRequestTenantId();
}
$tenantInfo = null;

if ($tenantId) {
    // Garantir que a classe exista quando a página for carregada diretamente
    if (!class_exists('App\\Infrastructure\\Billing\\TenantRepository')) {
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }
    }

    $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
    $tenantInfo = $tenantRepo->getTenantData($tenantId);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Bloqueado - RR Imóveis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .blocked-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2rem 3rem rgba(0, 0, 0, 0.1);
            max-width: 500px;
            padding: 3rem 2rem;
            text-align: center;
        }

        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .locked-icon {
            color: #dc3545;
        }

        h1 {
            color: #dc3545;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .reason {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1.5rem 0;
            border-left: 4px solid #dc3545;
        }

        .info-box {
            background-color: #e7f3ff;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #0066cc;
        }

        .btn-group {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-primary:hover {
            background-color: #c82333;
            border-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(220, 53, 69, 0.3);
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-2px);
        }

        .highlight {
            background: linear-gradient(120deg, #ffd700 0%, #ffed4e 100%);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 700;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="blocked-container">
        <div class="icon locked-icon">
            🔒
        </div>

        <h1>Acesso Bloqueado</h1>

        <p class="text-muted mb-3">
            Desculpe, não conseguimos acessar sua conta neste momento.
        </p>

        <div class="reason">
            <strong>Motivo:</strong><br>
            <?php echo htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <?php if ($tenantInfo): ?>
            <div class="info-box">
                <strong>Empresa:</strong> <?php echo htmlspecialchars($tenantInfo['name'], ENT_QUOTES, 'UTF-8'); ?><br>
                <strong>Plano:</strong> <?php echo htmlspecialchars($tenantInfo['plano'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?><br>

                <?php if ($tenantInfo['data_expiracao']): ?>
                    <strong>Válido até:</strong>
                    <?php
                    $dataExp = date('d/m/Y H:i', strtotime($tenantInfo['data_expiracao']));
                    $isExpired = $tenantRepo->isExpired($tenantId);
                    if ($isExpired) {
                        echo '<span class="text-danger">' . $dataExp . ' <small>(expirou)</small></span>';
                    } else {
                        echo $dataExp;
                    }
                    ?><br>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p class="text-muted small">
            <strong>O que fazer?</strong><br>
            1. Verifique se seu plano está ativo<br>
            2. Faça um novo pagamento para reativar o acesso<br>
            3. Entre em contato com o suporte
        </p>

        <div class="btn-group">
            <a href="planos.php" class="btn btn-primary">
                💳 Renovar Plano
            </a>
            <a href="logout.php" class="btn btn-secondary">
                Sair
            </a>
        </div>
    </div>
</body>

</html>