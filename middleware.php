<?php

/**
 * MIDDLEWARE DE VERIFICAÇÃO DE TENANT
 * Verifica acesso e status do tenant em páginas protegidas
 */

require_once __DIR__ . '/config/session_manager.php';
require_once __DIR__ . '/src/Infrastructure/Billing/TenantRepository.php';
require_once __DIR__ . '/src/Infrastructure/User/UserRepository.php';

function verificarTenantAtivo($tenantId)
{
    global $pdo;

    try {
        $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
        $tenant = $tenantRepo->getTenantData((int)$tenantId);

        if (!$tenant) {
            return ['ativo' => false, 'motivo' => 'Empresa não encontrada', 'tenant' => null];
        }

        if ($tenant['status'] === 'trial') {
            $tenantRepo->activateTrial((int)$tenantId);
            $tenant['status'] = 'active';
        }

        if (!empty($tenant['data_expiracao']) && strtotime($tenant['data_expiracao']) < time()) {
            if ($tenant['status'] !== 'inactive') {
                $tenantRepo->markInactive((int)$tenantId);
                $tenant['status'] = 'inactive';
            }

            return ['ativo' => false, 'motivo' => 'Plano expirado', 'tenant' => $tenant];
        }

        if ($tenant['status'] === 'inactive') {
            return ['ativo' => false, 'motivo' => 'Acesso à empresa bloqueado', 'tenant' => $tenant];
        }

        if ($tenant['status'] === 'active') {
            return ['ativo' => true, 'motivo' => 'Acesso permitido', 'tenant' => $tenant];
        }

        return ['ativo' => false, 'motivo' => 'Status do tenant inválido: ' . $tenant['status'], 'tenant' => $tenant];
    } catch (Exception $e) {
        error_log('Erro ao verificar tenant ativo: ' . $e->getMessage());
        return ['ativo' => false, 'motivo' => 'Erro interno ao verificar acesso', 'tenant' => null];
    }
}

function contarUsuariosTenant($tenantId)
{
    global $pdo;

    try {
        $userRepo = new \App\Infrastructure\User\UserRepository($pdo);
        return (int)$userRepo->countUsers((int)$tenantId);
    } catch (Exception $e) {
        error_log('Erro ao contar usuários: ' . $e->getMessage());
        return 0;
    }
}

function podeCriarUsuario($tenantId)
{
    $tenantCheck = verificarTenantAtivo($tenantId);

    if (!$tenantCheck['ativo']) {
        return ['pode' => false, 'motivo' => 'Empresa inativa: ' . $tenantCheck['motivo']];
    }

    $tenant = $tenantCheck['tenant'];
    $usuariosAtuais = contarUsuariosTenant($tenantId);
    $limiteUsuarios = (int)($tenant['limite_usuarios'] ?? 0);

    if ($usuariosAtuais >= $limiteUsuarios) {
        return ['pode' => false, 'motivo' => "Limite de usuários do plano atingido ({$usuariosAtuais}/{$limiteUsuarios}). Upgradear plano para criar mais usuários."];
    }

    return ['pode' => true, 'motivo' => 'OK'];
}

/**
 * Verifica se o usuário tem acesso ao tenant
 * Deve ser chamado no topo de todas as páginas protegidas
 * 
 * @param bool $redirectIfBlocked Se true, redireciona para página de bloqueio
 * @return bool true se tem acesso, false caso contrário
 */
function verificarAcessoTenant($redirectIfBlocked = true)
{
    // Verificar se está logado
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }

    $hasExplicitTenant = isset($_SESSION['tenant_id']) || isset($_SESSION['selected_tenant_id']);
    if (!$hasExplicitTenant) {
        // Usuário superadmin sem tenant selecionado é OK
        if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin']) {
            return true;
        }

        header("Location: /login.php");
        exit;
    }

    $tenantId = function_exists('getRequestTenantId')
        ? (int)getRequestTenantId()
        : (int)($_SESSION['tenant_id'] ?? 0);

    // Verificar status do tenant
    $verificacao = verificarTenantAtivo($tenantId);

    // Se não está ativo
    if (!$verificacao['ativo']) {
        if ($redirectIfBlocked) {
            // Salvar motivo do bloqueio na sessão
            $_SESSION['bloqueio_motivo'] = $verificacao['motivo'];
            header("Location: /tenant_bloqueado.php");
            exit;
        }
        return false;
    }

    // Tenant ativo - atualizar informações na sessão
    if ($verificacao['tenant']) {
        $_SESSION['tenant_status'] = $verificacao['tenant']['status'];
        $_SESSION['tenant_plano'] = $verificacao['tenant']['plano'];
        $_SESSION['tenant_limite_usuarios'] = $verificacao['tenant']['limite_usuarios'];
    }

    return true;
}

/**
 * Verifica se pode criar novo usuário (bloqueio por limite)
 * 
 * @return array ['pode' => bool, 'motivo' => string]
 */
function verificarLimiteUsuarios()
{
    if (!isset($_SESSION['tenant_id'])) {
        return ['pode' => false, 'motivo' => 'Empresa não definida'];
    }

    return podeCriarUsuario((int)$_SESSION['tenant_id']);
}

/**
 * Log de uma ação do usuário com informações do tenant
 * 
 * @param string $acao
 * @param array $dados
 * @return void
 */
function logAcaoTenant($acao, $dados = [])
{
    $logData = [
        'data' => date('Y-m-d H:i:s'),
        'acao' => $acao,
        'user_id' => $_SESSION['user_id'] ?? null,
        'tenant_id' => $_SESSION['tenant_id'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'dados_adicionais' => $dados
    ];

    error_log(json_encode($logData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/**
 * Obtém o status do tenant da sessão
 * 
 * @return array
 */
function getStatusTenant()
{
    return [
        'ativo' => isset($_SESSION['tenant_id']),
        'id' => $_SESSION['tenant_id'] ?? null,
        'status' => $_SESSION['tenant_status'] ?? null,
        'plano' => $_SESSION['tenant_plano'] ?? null,
        'limite_usuarios' => $_SESSION['tenant_limite_usuarios'] ?? null,
        'bloqueado' => isset($_SESSION['bloqueio_motivo'])
    ];
}

/**
 * Função helper para verificar autenticação + acesso ao tenant
 * Deve ser chamada no topo de todas as páginas protegidas que precisam de tenant ativo
 */
function checkAuthAndTenantAccess()
{
    // Verificar autenticação básica
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }

    // Verificar acesso ao tenant (bloqueia se expirado)
    verificarAcessoTenant();
}
