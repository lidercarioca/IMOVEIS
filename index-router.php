<?php

/**
 * Router de Subdomínios para Multi-Tenant (VERSÃO DINÂMICA)
 * 
 * Mapeia subdomínios para tenants lendo dinamicamente da tabela 'tenants'.
 * Cada tenant tem um 'slug' que define seu subdomínio único.
 * 
 * Exemplos de acesso:
 * - default.localhost → tenant_id = 1 (slug: default)
 * - imoveis-rio.localhost → tenant_id = 2 (slug: imoveis-rio)
 * - imoveis-sp.localhost → tenant_id = 3 (slug: imoveis-sp)
 * - imoveis-mg.localhost → tenant_id = 4 (slug: imoveis-mg)
 * - localhost → tenant_id = 1 (padrão)
 */

// VERIFICAR SE É ARQUIVO REAL
// Se for um arquivo real (css, js, imagens, etc), servir normalmente
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$script_filename = $_SERVER['SCRIPT_FILENAME'] ?? '';

// Se for arquivo que existe, permitir acesso direto
if ($request_uri !== '/' && file_exists(__DIR__ . substr($request_uri, strpos($request_uri, '/')))) {
    // Arquivo existe, Apache vai servir normalmente
    return false;
}

// =============================================================================
// DETECTAR TENANT_ID A PARTIR DO SUBDOMÍNIO (DINÂMICO)
// =============================================================================

// Carregue a classe Database para conexão
require_once __DIR__ . '/config/database.php';

// Obter o host (ex: empresa4.localhost ou localhost)
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Remover porta se existir (ex: localhost:8080 → localhost)
$hostname = explode(':', $host)[0];

// Determinar o tenant_id baseado no hostname/subdomínio.
// Nenhum override externo por query string, header ou parâmetro pode sobrescrever o tenant público.
$tenant_id = null;
$subdomain = null;
$company_info = null;
$old_subdomain_redirect = false;

require_once __DIR__ . '/vendor/autoload.php';

use App\Tenant\PublicTenantResolver;

try {
    $publicResolver = new PublicTenantResolver($pdo);
    $publicResolution = $publicResolver->resolve($host);
    $tenant_id = isset($publicResolution['tenant_id']) ? (int) $publicResolution['tenant_id'] : null;
    $subdomain = $publicResolution['subdomain'] ?? null;
    $tenantName = $publicResolution['tenant_name'] ?? 'Tenant';

    $status = $publicResolution['status'] ?? 'invalid_host';
    if ($status === 'invalid_host' || $status === 'tenant_not_found') {
        http_response_code(404);
        echo '<h1>Empresa não encontrada</h1><p>O domínio ou subdomínio solicitado não está cadastrado para este site público.</p>';
        exit;
    }

    if ($status === 'tenant_inactive') {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        require __DIR__ . '/views/public/tenant_unavailable.php';
        exit;
    }

    if ($status === 'config_error') {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        require __DIR__ . '/views/public/tenant_unavailable.php';
        exit;
    }

    if ($tenant_id === null || $tenant_id <= 0) {
        http_response_code(404);
        echo '<h1>Empresa não encontrada</h1><p>O domínio ou subdomínio solicitado não está cadastrado para este site público.</p>';
        exit;
    }
} catch (Exception $e) {
    error_log('[ROUTER ERROR] Falha ao resolver tenant pelo hostname: ' . $e->getMessage());
    http_response_code(503);
    $tenantName = 'Tenant';
    require __DIR__ . '/views/public/tenant_unavailable.php';
    exit;
}

if (php_sapi_name() !== 'cli') {
    error_log("[ROUTER] Host: {$hostname} | Subdomínio: {$subdomain} | Tenant: " . ($tenant_id !== null ? $tenant_id : 'NULL') . " | URI: {$request_uri}");
}

if ($old_subdomain_redirect && $subdomain && isset($redirect_map[$subdomain])) {
    $new_subdomain = $redirect_map[$subdomain];
    $new_host = str_replace($subdomain . '.', $new_subdomain . '.', $hostname);
    $new_url = 'https://' . $new_host . $request_uri;

    if (strpos($hostname, 'localhost') !== false || strpos($hostname, '127.0.0.1') !== false) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $new_url = $protocol . '://' . $new_host . $request_uri;
    }

    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $new_url);
    error_log("[ROUTER REDIRECT] 301 Redirect: {$hostname}{$request_uri} → {$new_host}{$request_uri}");
    exit;
}

if ($tenant_id === null || (int) $tenant_id <= 0) {
    http_response_code(404);
    echo '<h1>Empresa não encontrada</h1><p>O domínio ou subdomínio solicitado não está cadastrado para este site público.</p>';
    exit;
}

// =============================================================================
// EXPOR INFORMAÇÃO DO SUBDOMÍNIO AO RESOLVER (NÃO DEFINIR TENANT GLOBAL)
// =============================================================================

// Fornecer o tenant detectado a partir do subdomínio como input para o TenantResolver.
// NÃO escrever em $_SESSION, $_GET ou $_SERVER['TENANT_ID'] aqui — o TenantResolver
// é o único responsável por decidir o tenant efetivo e escrever as variáveis de compatibilidade.
$_SERVER['SUBDOMAIN_TENANT_ID'] = (int)$tenant_id;

// Garantir que o gerenciador de sessão exista, sem sobrescrever valores de sessão
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/config/session_manager.php';
}

// =============================================================================
// INCLUIR O ARQUIVO APROPRIADO BASEADO NO PATH
// =============================================================================

// Extrair o path do request
$path = parse_url($request_uri, PHP_URL_PATH);
$path = trim($path, '/');

// Remover a query string
if (strpos($path, '?') !== false) {
    $path = substr($path, 0, strpos($path, '?'));
}

// Se path é vazio ou '/', mostrar homepage
if (empty($path) || $path === '' || $path === 'index.html') {
    if (is_file(__DIR__ . '/views/public/index.html')) {
        // INJETAR tenant_id dinamicamente no HTML
        $html = file_get_contents(__DIR__ . '/views/public/index.html');

        // Injetar a variável global JavaScript com o tenant_id correto
        $tenant_script = "<script>\n  window.TENANT_ID_SERVER = " . intval($tenant_id) . ";\n  window.SUBDOMAIN_TENANT_ID = " . intval($tenant_id) . ";\n</script>\n";

        // Estratégia de injeção robusta:
        // 1. Tentar injetar logo após a tag <head>
        $html = preg_replace('/<head(\s[^>]*)?>/', '<head$1>' . $tenant_script, $html, 1);

        // Caso não funcione, como fallback injetar antes do </head>
        if (strpos($html, 'window.TENANT_ID_SERVER') === false) {
            $html = str_replace('</head>', $tenant_script . '</head>', $html);
        }

        // Último fallback: injetar logo após <html>
        if (strpos($html, 'window.TENANT_ID_SERVER') === false) {
            $html = preg_replace('/<html(\s[^>]*)?>/', '<html$1>' . $tenant_script, $html, 1);
        }

        // Log de debug
        error_log("[ROUTER INJECT] Tenant ID: " . $tenant_id . " | Injection check: " . (strpos($html, 'window.TENANT_ID_SERVER') !== false ? 'OK' : 'FAILED'));

        echo $html;
    } else {
        die('Erro: views/public/index.html não encontrado.');
    }
}
// Se for um arquivo PHP, executar
elseif (substr($path, -4) === '.php' && is_file(__DIR__ . '/' . $path)) {
    include __DIR__ . '/' . $path;
}
// Se for um arquivo estático (CSS, JS, imagens, etc), servir
elseif (is_file(__DIR__ . '/' . $path)) {
    $file = __DIR__ . '/' . $path;
    $ext = pathinfo($file, PATHINFO_EXTENSION);

    // Determinar MIME type
    $mime_types = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    header('Content-Type: ' . ($mime_types[$ext] ?? 'application/octet-stream'));
    readfile($file);
}
// Se for um diretório, não fazer nada (Apache vai lidar)
elseif (is_dir(__DIR__ . '/' . $path)) {
    // Let Apache handle directory requests
    return false;
}
// Arquivo não encontrado
else {
    http_response_code(404);
    echo "Página não encontrada: {$path}";
    error_log("[ROUTER 404] Arquivo não encontrado: {$path}");
}

exit;
