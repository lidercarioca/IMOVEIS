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

// Extrair o subdomínio
$parts = explode('.', $hostname);

// Determinar o tenant_id baseado no subdomínio
$tenant_id = 1; // padrão
$subdomain = null;
$company_info = null;
$old_subdomain_redirect = false; // Flag para redirecionamento de URLs antigas

if (count($parts) > 1) {
    // Se houver subdomínio
    $subdomain = strtolower($parts[0]);

    // DINÂMICO: Carregar mapeamento de tenants do banco de dados
    try {
        // Query melhorada: buscar tanto company_subdomain (novo) quanto slug (antigo) para compatibilidade
        // IMPORTANTE: incluir tenants inativos para permitir debug de subdomínio; o middleware restante bloqueia inativos para usuários normais.
        $query = $pdo->prepare('
            SELECT 
                id, 
                slug, 
                name, 
                status,
                COALESCE(company_subdomain, slug) as company_subdomain
            FROM tenants 
            ORDER BY id ASC
        ');
        $query->execute();
        $tenants = $query->fetchAll(PDO::FETCH_ASSOC);

        // Criar mapa dinâmico: company_subdomain → tenant_id (novo) + slug → tenant_id (compatibilidade)
        $subdomain_map = [];
        $slug_map = [];
        $redirect_map = []; // Mapear subdomínios antigos para novos (para 301 redirects)

        foreach ($tenants as $t) {
            $company_subdomain = strtolower($t['company_subdomain']);
            $slug = strtolower($t['slug'] ?? '');

            // Mapear company_subdomain (novo padrão)
            $subdomain_map[$company_subdomain] = $t['id'];

            // Mapear slug antigo como fallback
            if (!empty($slug) && $slug !== $company_subdomain) {
                $slug_map[$slug] = $t['id'];
                // Registrar para reconhecer URLs antigas e fazer redirect se necessário
                $redirect_map[$slug] = $company_subdomain;
            }

            // Gerar aliases comuns (primeiras 3 letras, nomes simplificados, etc.)
            if ($company_subdomain === 'default' || $slug === 'default') {
                $subdomain_map['dj'] = $t['id'];      // DJ Imóveis
                $subdomain_map['www'] = $t['id'];
            } elseif (strpos($company_subdomain, 'imoveis-') === 0) {
                // Remover "imoveis-" para criar aliases
                $state_code = str_replace('imoveis-', '', $company_subdomain);
                $subdomain_map[$state_code] = $t['id']; // rio, sp, mg, etc.
            }
        }

        // Se o subdomínio está no mapa company_subdomain, usar seu tenant_id
        if (isset($subdomain_map[$subdomain])) {
            $tenant_id = $subdomain_map[$subdomain];
        }
        // Verificar se é um subdomínio antigo (slug) que deve ser redirecionado
        elseif (isset($slug_map[$subdomain])) {
            $tenant_id = $slug_map[$subdomain];
            $old_subdomain_redirect = true; // Marcar para redirecionamento
            error_log("[ROUTER] Subdomínio antigo detectado: '{$subdomain}' → redirecionar para '{$redirect_map[$subdomain]}'");
        } else {
            $found = array_key_exists($subdomain, $subdomain_map) || array_key_exists($subdomain, $slug_map);
            error_log("[ROUTER WARN] subdomain '{$subdomain}' não mapeado; map keys: " . implode(', ', array_keys($subdomain_map)) . " / " . implode(', ', array_keys($slug_map)));
        }
    } catch (Exception $e) {
        // Se houver erro na conexão, usar mapa padrão como fallback
        error_log("[ROUTER ERROR] Erro ao carregar tenants do BD: " . $e->getMessage());
        $subdomain_map = [
            'default' => 1,
            'imoveis-rio' => 2,
            'imoveis-sp' => 3,
            'imoveis-mg' => 4,
            'dj' => 1,
            'rio' => 2,
            'sp' => 3,
            'saopaulo' => 3,
            'mg' => 4,
            'minas' => 4,
            'www' => 1,
        ];

        if (isset($subdomain_map[$subdomain])) {
            $tenant_id = $subdomain_map[$subdomain];
        }
    }
}

// Log para debug (apenas em desenvolvimento)
if (php_sapi_name() !== 'cli') {
    error_log("[ROUTER] Host: {$hostname} | Subdomínio: {$subdomain} | Tenant: {$tenant_id} | URI: {$request_uri}");
}

// =============================================================================
// REDIRECIONAMENTO 301: Se for um subdomínio ANTIGO, redirecionar para NOVO
// =============================================================================
if ($old_subdomain_redirect && $subdomain && isset($redirect_map[$subdomain])) {
    // Construir nova URL com o novo subdomínio (company_subdomain)
    $new_subdomain = $redirect_map[$subdomain];
    $new_host = str_replace($subdomain . '.', $new_subdomain . '.', $hostname);
    $new_url = 'https://' . $new_host . $request_uri;

    // Se estiver em desenvolvimento (localhost), não forçar HTTPS
    if (strpos($hostname, 'localhost') !== false || strpos($hostname, '127.0.0.1') !== false) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $new_url = $protocol . '://' . $new_host . $request_uri;
    }

    // Fazer 301 redirect permanente (SEO-friendly)
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $new_url);
    error_log("[ROUTER REDIRECT] 301 Redirect: {$hostname}{$request_uri} → {$new_host}{$request_uri}");
    exit;
}

// =============================================================================
// OVERRIDE: Se houver ?tenant_id=X na URL, esse tem MÁXIMA PRIORIDADE
// =============================================================================
if (isset($_GET['tenant_id']) && intval($_GET['tenant_id']) > 0) {
    $tenant_id_url = intval($_GET['tenant_id']);
    error_log("[ROUTER] 🔄 Override via URL parameter: tenant_id = {$tenant_id_url} (anterior era {$tenant_id})");
    $tenant_id = $tenant_id_url;
}

// =============================================================================
// EXPOR INFORMAÇÃO DO SUBDOMÍNIO AO RESOLVER (NÃO DEFINIR TENANT GLOBAL)
// =============================================================================

// Fornecer o tenant detectado a partir do subdomínio como input para o TenantResolver.
// NÃO escrever em $_SESSION, $_GET ou $_SERVER['TENANT_ID'] aqui — o TenantResolver
// é o único responsável por decidir o tenant efetivo e escrever as variáveis de compatibilidade.
$_SERVER['SUBDOMAIN_TENANT_ID'] = $tenant_id;

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
