<?php

/**
 * Session Manager Centralizado
 * Carrega configurações de segurança ANTES de iniciar a sessão
 * Garante consistência e evita erros "Session ini settings cannot be changed"
 */

// Carregar JWT seguro antes de qualquer outra coisa
require_once __DIR__ . '/secure_jwt.php';

// Carregar configurações de timeout
require_once __DIR__ . '/session_timeout.php';

// Produzir o resultado estruturado da sessão sem alterar o mecanismo legado de autenticação
require_once __DIR__ . '/../src/Auth/RequestAuthenticationResult.php';
require_once __DIR__ . '/../src/Auth/Permissions.php';

// Exceção customizada para erros de autenticação em APIs
class SessionAuthException extends Exception {}

// Configurações de segurança da sessão
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_lifetime', '0');

    // Ativa cookie_secure apenas quando a requisição estiver em HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT); // Usa timeout centralizado

    // Inicia a sessão após definir as configurações
    session_start();
}

/**
 * Verifica se o usuário está logado
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Produz um RequestAuthenticationResult para o estado atual da sessão sem
 * substituir a validação/execução legada de autenticação em sessão.
 */
function buildSessionAuthenticationResult(): \App\Auth\RequestAuthenticationResult
{
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return new \App\Auth\RequestAuthenticationResult(
            \App\Auth\RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            \App\Auth\RequestAuthenticationResult::SOURCE_NONE
        );
    }

    $userId = (int)$_SESSION['user_id'];

    if ($userId <= 0) {
        return new \App\Auth\RequestAuthenticationResult(
            \App\Auth\RequestAuthenticationResult::STATUS_NO_CREDENTIAL,
            \App\Auth\RequestAuthenticationResult::SOURCE_NONE
        );
    }

    if (
        isset($_SESSION['last_activity']) &&
        !isSessionValid((int)$_SESSION['last_activity'])
    ) {
        return new \App\Auth\RequestAuthenticationResult(
            \App\Auth\RequestAuthenticationResult::STATUS_AUTHENTICATION_ERROR,
            \App\Auth\RequestAuthenticationResult::SOURCE_NONE,
            null,
            null,
            [],
            false,
            'SESSION_EXPIRED',
            $_SERVER['REQUEST_ID'] ?? null
        );
    }

    $rawRole = isset($_SESSION['role']) ? trim((string)$_SESSION['role']) : 'user';
    $normalizedRole = strtolower(str_replace(['_', '-', ' '], '', $rawRole));
    $resolvedRole = in_array($normalizedRole, ['admin', 'administrator', 'superadmin'], true)
        ? 'admin'
        : $normalizedRole;

    $permissions = \App\Auth\Permissions::getPermissions($resolvedRole);
    $isSuperAdmin = in_array($normalizedRole, ['admin', 'administrator', 'superadmin'], true)
        && (!empty($_SESSION['is_superadmin']) || $normalizedRole === 'superadmin');

    return new \App\Auth\RequestAuthenticationResult(
        \App\Auth\RequestAuthenticationResult::STATUS_SESSION_AUTHENTICATED,
        \App\Auth\RequestAuthenticationResult::SOURCE_SESSION,
        $userId,
        $resolvedRole,
        $permissions,
        $isSuperAdmin,
        null,
        $_SERVER['REQUEST_ID'] ?? null
    );
}

/**
 * Verifica se o usuário é admin
 * Aceita: 'admin', 'administrator' ou 'superadmin'
 */
function isAdmin()
{
    if (!isset($_SESSION['role'])) {
        return false;
    }
    $role = strtolower(str_replace(['_', '-', ' '], '', trim($_SESSION['role'])));
    return $role === 'admin' || $role === 'administrator' || $role === 'superadmin';
}

/**
 * Verifica se o usuário tem um role específico
 * Normaliza a comparação com strtolower e trim
 */
function hasRole($roleToCheck)
{
    if (!isset($_SESSION['role'])) {
        return false;
    }
    $currentRole = strtolower(str_replace(['_', '-', ' '], '', trim($_SESSION['role'])));
    $checkRole = strtolower(str_replace(['_', '-', ' '], '', trim((string)$roleToCheck)));
    return $currentRole === $checkRole;
}

/**
 * Verifica se o usuário tem uma das roles passadas
 * Aceita um array ou string única
 */
function hasAnyRole($roles)
{
    if (!isset($_SESSION['role'])) {
        return false;
    }
    $currentRole = strtolower(str_replace(['_', '-', ' '], '', trim($_SESSION['role'])));
    $rolesArray = is_array($roles) ? $roles : [$roles];

    foreach ($rolesArray as $role) {
        $normalizedRole = strtolower(str_replace(['_', '-', ' '], '', trim((string)$role)));
        if ($normalizedRole === $currentRole) {
            return true;
        }
    }
    return false;
}

/**
 * Verifica autenticação básica
 * Para APIs, lança SessionAuthException em vez de fazer exit/echo
 */
function checkAuth()
{
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $isApiRequest = strpos($requestUri, '/api/') !== false;

    // Verifica timeout da sessão (configurado em session_timeout.php)
    if (
        isset($_SESSION['last_activity']) &&
        !isSessionValid($_SESSION['last_activity'])
    ) {
        session_unset();
        session_destroy();
        if ($isApiRequest) {
            http_response_code(401);
            throw new SessionAuthException('Sessão expirada');
        }
        header("Location: login.php?error=session_expired");
        exit;
    }

    if (!isLoggedIn()) {
        if ($isApiRequest) {
            http_response_code(401);
            throw new SessionAuthException('Usuário não autenticado');
        }
        header("Location: login.php");
        exit;
    }

    // Gera o resultado estruturado da sessão validada sem mexer no comportamento legado
    $sessionAuthenticationResult = buildSessionAuthenticationResult();

    // Atualiza último acesso
    $_SESSION['last_activity'] = time();

    // Regenera ID da sessão periodicamente (5 minutos)
    if (
        !isset($_SESSION['last_regeneration']) ||
        time() - $_SESSION['last_regeneration'] > 300
    ) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Verifica se é admin
 */
function checkAdmin()
{
    checkAuth();
    if (!isAdmin()) {
        header("Location: unauthorized.php");
        exit;
    }
}

/**
 * Resolve o tenant_id para a requisição atual e o mantém em cache de requisição
 * Prioridade: 1) $_REQUEST['tenant_id'] 2) $_SESSION['tenant_id'] 3) sem fallback forçado
 * Retorna inteiro
 */
function getRequestTenantId()
{
    if (isset($GLOBALS['REQUEST_TENANT_ID'])) {
        return intval($GLOBALS['REQUEST_TENANT_ID']);
    }

    // Delegar resolução ao adapter de compatibilidade (que usa TenantResolver)
    // Não aplicar regras locais de prioridade aqui.
    if (file_exists(__DIR__ . '/../app/security/TenantContext.php')) {
        require_once __DIR__ . '/../app/security/TenantContext.php';
        \TenantContext::initialize();
        $tenantId = \TenantContext::getTenantId();
        if ($tenantId === null || intval($tenantId) <= 0) {
            // Não usar fallback para tenant_id 1: evita isolamento cruzado silencioso
            $tenantId = 0;
        }
    } else {
        // Fallback para compatibilidade se o adapter não existir
        if (isset($_REQUEST['tenant_id']) && intval($_REQUEST['tenant_id']) > 0) {
            $tenantId = intval($_REQUEST['tenant_id']);
        } elseif (isset($_SESSION['tenant_id']) && intval($_SESSION['tenant_id']) > 0) {
            $tenantId = intval($_SESSION['tenant_id']);
        } else {
            $tenantId = 0;
        }
    }

    $GLOBALS['REQUEST_TENANT_ID'] = intval($tenantId);
    return intval($GLOBALS['REQUEST_TENANT_ID']);
}
