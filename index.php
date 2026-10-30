<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// =============================================================================
// 0. CARREGAR CONFIGURAÇÕES ESSENCIAIS
// =============================================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session_manager.php';
require_once __DIR__ . '/vendor/autoload.php';

use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;
use App\Application\Integration\PortalIntegrationService;

// =============================================================================
// 2. DETECTAR TENANT_ID - PRIORIDADE: URL parameter (slug/tenant_id) > subdomínio > padrão
// =============================================================================

$tenant_id_for_injection = 1; // padrão para renderização pública
$trackingTenantId = null;

// O TenantContext centraliza a escolha do tenant.
// Se o core de tenant estiver disponível, delegamos totalmente a ele.
// Se não estiver, mantemos a lógica legada para compatibilidade.
if (file_exists(__DIR__ . '/app/security/TenantContext.php')) {
    require_once __DIR__ . '/app/security/TenantContext.php';
    \TenantContext::initialize();
    $resolved = \TenantContext::getTenantId();
    if ($resolved !== null && intval($resolved) > 0) {
        $tenant_id_for_injection = intval($resolved);
        $trackingTenantId = $tenant_id_for_injection;
    }
} else {
    // MÁXIMA PRIORIDADE: URL parameter ?slug=X ou ?tenant_id=X
    if (isset($_GET['slug']) && !empty($_GET['slug'])) {
        $slug_param = strtolower($_GET['slug']);
        try {
            $stmt = $pdo->prepare('SELECT id FROM tenants WHERE LOWER(slug) = ? AND status = "active" LIMIT 1');
            $stmt->execute([$slug_param]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && $result['id']) {
                $tenant_id_for_injection = intval($result['id']);
                $trackingTenantId = $tenant_id_for_injection;
                error_log("[INDEX.PHP] Tenant detectado via slug parameter: " . $tenant_id_for_injection . " (slug: " . $slug_param . ")");
            }
        } catch (Exception $e) {
            error_log("[INDEX.PHP] Erro ao buscar tenant por slug: " . $e->getMessage());
        }
    } elseif (isset($_GET['tenant_id']) && intval($_GET['tenant_id']) > 0) {
        $tenant_id_for_injection = intval($_GET['tenant_id']);
        $trackingTenantId = $tenant_id_for_injection;
        error_log("[INDEX.PHP] Tenant detectado via tenant_id parameter: " . $tenant_id_for_injection);
    } else {
        // FALLBACK: Detectar do subdomínio
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $hostname = explode(':', $host)[0];
        $parts = explode('.', $hostname);

        if (count($parts) > 1) {
            $subdomain = strtolower($parts[0]);

            try {
                $query = $pdo->prepare('SELECT id, slug FROM tenants WHERE status = "active" ORDER BY id ASC');
                $query->execute();
                $tenants = $query->fetchAll(PDO::FETCH_ASSOC);

                $subdomain_map = [];
                foreach ($tenants as $t) {
                    $slug = strtolower($t['slug']);
                    $subdomain_map[$slug] = $t['id'];

                    // Adiciona também a forma completa (ex: 'imoveis-rio')
                    if (strpos($slug, 'imoveis-') === 0) {
                        // Já adiciona o slug completo acima
                        // Agora adiciona também a forma reduzida (ex: 'rio' para 'imoveis-rio')
                        $state_code = str_replace('imoveis-', '', $slug);
                        $subdomain_map[$state_code] = $t['id'];
                    } elseif ($slug === 'default') {
                        // Para tenant padrão, adiciona múltiplos aliases
                        $subdomain_map['dj'] = $t['id'];
                        $subdomain_map['www'] = $t['id'];
                    }
                }

                if (isset($subdomain_map[$subdomain])) {
                    $tenant_id_for_injection = $subdomain_map[$subdomain];
                    $trackingTenantId = $tenant_id_for_injection;
                    error_log("[INDEX.PHP] Tenant detectado via subdomínio: " . $tenant_id_for_injection);
                }
            } catch (Exception $e) {
                error_log("[INDEX.PHP] Erro ao detectar tenant: " . $e->getMessage());
            }
        }
    }
}

// =============================================================================
// 2.5. DETECTAR MUDANÇA DE TENANT E LIMPAR CACHE
// =============================================================================
// Se houver uma sessão anterior com tenant diferente, limpar dados específicos do tenant antigo
if (isset($_SESSION['index_tenant_id']) && $_SESSION['index_tenant_id'] != $tenant_id_for_injection) {
    error_log("[INDEX.PHP] 🔄 Mudança de tenant detectada: " . $_SESSION['index_tenant_id'] . " → " . $tenant_id_for_injection);

    // Remove dados específicos do tenant antigo da sessão
    $tenant_specific_keys = ['company_name', 'company_logo', 'company_colors', 'company_description', 'current_tenant_id'];
    foreach ($tenant_specific_keys as $key) {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
}

// Armazenar o tenant_id atual na sessão (para detectar mudanças na próxima requisição)
$_SESSION['index_tenant_id'] = $tenant_id_for_injection;

// Verificar status do tenant (se inativo, exibir mensagem e sair)
try {
    $stmt = $pdo->prepare('SELECT status, name FROM tenants WHERE id = ? LIMIT 1');
    $stmt->execute([$tenant_id_for_injection]);
    $tenantStatus = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenantStatus) {
        http_response_code(404);
        echo '<h1>Tenant não encontrado</h1><p>O tenant requisitado não existe.</p>';
        exit;
    }

    if ($tenantStatus['status'] !== 'active') {
        header('Content-Type: text/html; charset=UTF-8');
        http_response_code(503);
        echo '<div style="max-width:720px;margin:60px auto;padding:30px;background:#fff3cd;border:1px solid #ffeeba;color:#856404;border-radius:8px;font-family:Segoe UI, Arial, sans-serif;">';
        echo '<h1 style="margin-top:0;">Site temporariamente indisponível</h1>';
        echo '<p>O tenant <strong>' . htmlspecialchars($tenantStatus['name']) . '</strong> está desabilitado.</p>';
        echo '<p>Por favor, contate o administrador ou superadmin para reativação.</p>';
        echo '</div>';
        exit;
    }
} catch (Exception $e) {
    error_log('[INDEX.PHP] erro ao verificar status do tenant: ' . $e->getMessage());
}

// =============================================================================
header('Content-Type: text/html; charset=UTF-8');
// Não cachear HTML já que o tenant_id pode variar por subdomínio E URL parameter
header('Cache-Control: public, max-age=0'); // Sem cache para garantir sempre usar tenant_id correto
header('Vary: Host, Cookie, Accept-Language'); // Variar por host, cookie (sessão) e idioma
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// =============================================================================
// 4. RASTREAR VISITA (opcional, sem bloquear se falhar)
// =============================================================================
try {
    $tenantIdForVisit = $trackingTenantId !== null ? (int) $trackingTenantId : 0;
    if ($tenantIdForVisit > 0) {
        $visitorIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $visitorUa = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $siteVisitService = new \App\Service\SiteVisitService($pdo);
        $siteVisitService->trackVisit([
            'tenant_id' => $tenantIdForVisit,
            'session_id' => session_id(),
            'visitor_ip' => $visitorIp,
            'visitor_ua' => $visitorUa,
            'page_url' => '/',
            'referrer' => null,
        ]);
    }
} catch (Exception $e) {
    // Falha silenciosa, não bloqueia
}

// =============================================================================
// 5. CARREGAR E INJETAR HTML
// =============================================================================
$publicFile = __DIR__ . '/views/public/index.html';

if (!file_exists($publicFile)) {
    die('<!-- Erro: arquivo index.html não encontrado -->');
}

$html = @file_get_contents($publicFile);

if ($html === false || empty($html)) {
    die('<!-- Erro ao ler arquivo index.html -->');
}

// Injetar tenant_id e integrações do tenant
// Buscar configurações de empresa para injetar CSS crítico e evitar FOUC (injeção síncrona no <head>)
$companyColor1 = '#1e40af';
$companyColor2 = '#64748b';
$companyColor3 = '#f59e0b';
$companyLogo = '/assets/imagens/logo/logo_padrao.png';
try {
    $stmt = $pdo->prepare('SELECT company_color1, company_color2, company_color3, company_logo FROM company_settings WHERE tenant_id = ? LIMIT 1');
    $stmt->execute([$tenant_id_for_injection]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($c) {
        if (!empty($c['company_color1'])) $companyColor1 = $c['company_color1'];
        if (!empty($c['company_color2'])) $companyColor2 = $c['company_color2'];
        if (!empty($c['company_color3'])) $companyColor3 = $c['company_color3'];
        if (!empty($c['company_logo'])) $companyLogo = (str_starts_with($c['company_logo'], '/') ? $c['company_logo'] : '/' . $c['company_logo']);
    }
} catch (Exception $e) {
    error_log('[INDEX.PHP] Warning: não foi possível carregar company_settings para injeção crítica: ' . $e->getMessage());
}

// CSS crítico inline: define variáveis de tema e evita FOUC aplicando cor de fundo inicial
$critical_css = "<style>\n" .
    "  /* Critical theme variables - injected server-side to prevent FOUC */\n" .
    "  :root{\n" .
    "    --cor-primaria: " . htmlspecialchars($companyColor1, ENT_QUOTES, 'UTF-8') . ";\n" .
    "    --cor-secundaria: " . htmlspecialchars($companyColor2, ENT_QUOTES, 'UTF-8') . ";\n" .
    "    --cor-destaque: " . htmlspecialchars($companyColor3, ENT_QUOTES, 'UTF-8') . ";\n" .
    "    --cor-primaria-rgb: " . implode(',', array_map('intval', array_map('hexdec', str_split(ltrim($companyColor1, '#'), 2)))) . ";\n" .
    "  }\n" .
    "  /* Apply a safe background to avoid white flash while CSS loads */\n" .
    "  html, body { background-color: var(--cor-primaria); }\n" .
    "  /* Ensure navbar has initial color until JS applies dynamic color */\n" .
    "  .navbar-dynamic, .navbar-dynamic-gradient { background: var(--cor-primaria) !important; }\n" .
    "</style>\n";

$tenant_script = "<script>\n  window.TENANT_ID_SERVER = " . intval($tenant_id_for_injection) . ";\n  window.SUBDOMAIN_TENANT_ID = " . intval($tenant_id_for_injection) . ";\n  window.SERVER_COMPANY_LOGO = '" . addslashes($companyLogo) . "';\n" .
    "</script>\n";

// Inicializar Repository e Service para integrações
$portalRepository = new PortalIntegrationRepository($pdo);
$portalRepository->ensureTableExists();
$portalService = new PortalIntegrationService($portalRepository);

$googleAnalyticsIntegration = $portalService->getPortalIntegration($tenant_id_for_injection, 'google_analytics');
if ($googleAnalyticsIntegration && $googleAnalyticsIntegration['enabled']) {
    $rawAnalyticsScript = trim((string)($googleAnalyticsIntegration['credentials']['tracking_id'] ?? ''));
    if ($rawAnalyticsScript !== '') {
        if (stripos($rawAnalyticsScript, '<script') === false) {
            $rawAnalyticsScript = "<script>\n" . $rawAnalyticsScript . "\n</script>";
        }
        $tenant_script .= $rawAnalyticsScript . "\n";
    }
}

$hotjarIntegration = $portalService->getPortalIntegration($tenant_id_for_injection, 'hotjar');
if ($hotjarIntegration && $hotjarIntegration['enabled']) {
    $rawHotjarScript = trim((string)($hotjarIntegration['credentials']['tracking_code'] ?? ''));
    if ($rawHotjarScript !== '') {
        if (stripos($rawHotjarScript, '<script') === false) {
            $rawHotjarScript = "<script>\n" . $rawHotjarScript . "\n</script>";
        }
        $tenant_script .= $rawHotjarScript . "\n";
    }
}

$jivochatIntegration = $portalService->getPortalIntegration($tenant_id_for_injection, 'jivochat');
if ($jivochatIntegration && $jivochatIntegration['enabled']) {
    $rawJivoScript = trim((string)($jivochatIntegration['credentials']['widget_script'] ?? ''));
    if ($rawJivoScript !== '') {
        if (stripos($rawJivoScript, '<script') === false) {
            $rawJivoScript = "<script>\n" . $rawJivoScript . "\n</script>";
        }
        $tenant_script .= $rawJivoScript . "\n";
    }
}

$leadsterIntegration = $portalService->getPortalIntegration($tenant_id_for_injection, 'leadster');
if ($leadsterIntegration && $leadsterIntegration['enabled']) {
    $rawLeadsterScript = trim((string)($leadsterIntegration['credentials']['widget_script'] ?? ''));
    if ($rawLeadsterScript !== '') {
        if (stripos($rawLeadsterScript, '<script') === false) {
            $rawLeadsterScript = "<script>\n" . $rawLeadsterScript . "\n</script>";
        }
        $tenant_script .= $rawLeadsterScript . "\n";
    }
}

$tawkToIntegration = $portalService->getPortalIntegration($tenant_id_for_injection, 'tawk_to');
if ($tawkToIntegration && $tawkToIntegration['enabled']) {
    $rawTawkToScript = trim((string)($tawkToIntegration['credentials']['widget_script'] ?? ''));
    if ($rawTawkToScript !== '') {
        if (stripos($rawTawkToScript, '<script') === false) {
            $rawTawkToScript = "<script>\n" . $rawTawkToScript . "\n</script>";
        }
        $tenant_script .= $rawTawkToScript . "\n";
    }
}

$facebookPixelIntegration = $portalService->getPortalIntegration($tenant_id_for_injection, 'facebook_pixel');
if ($facebookPixelIntegration && $facebookPixelIntegration['enabled']) {
    $facebookPixelId = trim((string)($facebookPixelIntegration['credentials']['pixel_id'] ?? ''));
    if ($facebookPixelId !== '') {
        $pixelIdEscaped = htmlspecialchars($facebookPixelId, ENT_QUOTES, 'UTF-8');
        $tenant_script .= "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/pt_BR/fbevents.js');fbq('init', '" . $pixelIdEscaped . "');fbq('track', 'PageView');</script>\n";
        $tenant_script .= "<noscript><img height='1' width='1' style='display:none' src='https://www.facebook.com/tr?id=" . rawurlencode($facebookPixelId) . "&ev=PageView&noscript=1' /></noscript>\n";
    }
}

// =============================================================================
// 6. INJETAR SCRIPTS NA HTML E RETORNAR
// =============================================================================
// Injetar CSS crítico e script de tenant_id antes do fechamento da tag </head>
$html = str_replace('</head>', $critical_css . $tenant_script . '</head>', $html);

// Retornar o HTML final
echo $html;
