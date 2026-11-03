<?php
require_once 'config/session_manager.php';
require_once 'config/database.php';
require_once 'app/security/CSRF.php';

// Headers de segurança
require_once 'app/security/Security.php';
Security::setSecurityHeaders();
Security::blockLogsAccess();

// Verificar autenticação
require_once 'auth.php';
checkAuth();

// Importar contexto de tenant
require_once 'app/security/TenantContext.php';

// Validar contexto de tenant
TenantContext::initialize();

// Se for SUPERADMIN sem tenant selecionado, redirecionar para seleção
if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] && !isset($_SESSION['selected_tenant_id'])) {
  header("Location: select_tenant.php");
  exit;
}

// Carregadores de middleware de billing
require_once __DIR__ . '/src/Infrastructure/Billing/TenantRepository.php';
$tenantBillingRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);

// Carregar configuração de integrações para renderização prévia dos cards
$integrationConfig = [];
try {
  $integrationConfig = require __DIR__ . '/config/integrations.php';
} catch (Exception $e) {
  error_log('[painel.php] Erro ao carregar config/integrations.php: ' . $e->getMessage());
}

// Calcular se deve mostrar aviso de expiração para a aba de cobrança
$shouldShowExpiryWarning = false;
$currentTenantId = (int)\TenantContext::getTenantId();
$currentTab = $_GET['tab'] ?? 'properties';
$showBillingMenuItem = false;
$canAccessBillingTab = false;

try {
  if ($currentTenantId > 0) {
    $tenantData = $tenantBillingRepo->getTenantData($currentTenantId);
    $tenantStatusView = strtolower((string)($tenantData['status'] ?? 'inactive'));
    $isExpired = $tenantBillingRepo->isExpired($currentTenantId);
    $shouldShowExpiryWarning = $tenantBillingRepo->shouldShowExpiryWarning($currentTenantId);
    $showBillingMenuItem = $tenantStatusView !== 'active' || $isExpired || $shouldShowExpiryWarning;

    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0 && isAdmin()) {
      $userRepository = new \App\Infrastructure\User\UserRepository($pdo);
      $canAccessBillingTab = \TenantContext::isSuperAdmin() || $userRepository->userBelongsToTenant($userId, $currentTenantId);
    }
  }
} catch (Exception $e) {
  error_log('[painel.php] Erro ao calcular estado de billing: ' . $e->getMessage());
}

// Se tenant selecionado está inativo, bloquear acesso para não-superadmin
if (!\TenantContext::isSuperAdmin()) {
  if ($currentTenantId <= 0) {
    http_response_code(403);
    echo '<h1>Acesso negado</h1><p>Tenant não selecionado.</p>';
    exit;
  }

  try {
    // Verificar acesso do tenant com middleware de billing
    $verificacao = $tenantBillingRepo->checkTenantActiveStatus($currentTenantId);

    if (!$verificacao['ativo']) {
      $isAuthorizedBillingAccess = $currentTab === 'cobranca' && $canAccessBillingTab;
      if (!$isAuthorizedBillingAccess) {
        // Tenant bloqueado ou expirado; a exceção só é concedida ao admin autorizado da cobrança.
        $_SESSION['bloqueio_motivo'] = $verificacao['motivo'];
        header("Location: tenant_bloqueado.php");
        exit;
      }
    }

    // Atualizar informações na sessão
    if ($verificacao['tenant']) {
      $_SESSION['tenant_status'] = $verificacao['tenant']['status'];
      $_SESSION['tenant_plano'] = $verificacao['tenant']['plano'];
      $_SESSION['tenant_limite_usuarios'] = $verificacao['tenant']['limite_usuarios'];
    }

    $tenantStatus = $verificacao['tenant'];
  } catch (Exception $e) {
    error_log('[painel.php] Erro verificando status do tenant: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno');
  }
}

// Gera novo token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Carregar configurações de branding do tenant antes de renderizar
$correctTenantId = (int)\TenantContext::getTenantId();
$companyName = 'Sua Imobiliária';
$companyLogo = '/assets/imagens/logo/logo_padrao.png';

try {
  $stmt = $pdo->prepare('SELECT company_name, company_logo FROM company_settings WHERE tenant_id = ? LIMIT 1');
  $stmt->execute([$correctTenantId]);
  $brand = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($brand) {
    if (!empty($brand['company_name'])) {
      $companyName = $brand['company_name'];
    }
    if (!empty($brand['company_logo'])) {
      $companyLogo = $brand['company_logo'][0] === '/' ? $brand['company_logo'] : '/' . $brand['company_logo'];
    }
  }
} catch (Exception $e) {
  error_log('Erro carregando branding do tenant: ' . $e->getMessage());
}

// Construir URL pública do site com base em domínio autorizado e sem usar slug/query string.
$viewSiteUrl = '';
$viewSiteMessage = '';

try {
  $stmt = $pdo->prepare('SELECT id, slug, company_subdomain, status FROM tenants WHERE id = ? LIMIT 1');
  $stmt->execute([$correctTenantId]);
  $tenantData = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$tenantData) {
    $viewSiteMessage = 'Tenant não encontrado no contexto administrativo atual.';
  } elseif (strtolower((string)($tenantData['status'] ?? 'inactive')) !== 'active') {
    $viewSiteMessage = 'Este tenant está inativo. O site público não está disponível.';
  } else {
    $subdomain = strtolower(trim((string)($tenantData['company_subdomain'] ?? '')));
    if ($subdomain === '') {
      $viewSiteMessage = 'Este tenant não possui subdomínio público configurado.';
    } else {
      $candidateHosts = [];
      $configuredValue = getenv('TENANT_SUBDOMAIN_BASE_HOSTS') ?: ($_ENV['TENANT_SUBDOMAIN_BASE_HOSTS'] ?? null);

      if (is_string($configuredValue) && trim($configuredValue) !== '') {
        foreach (preg_split('/[;\s,]+/', trim((string) $configuredValue), -1, PREG_SPLIT_NO_EMPTY) as $candidateHost) {
          $normalized = strtolower(trim((string) $candidateHost));
          if ($normalized !== '' && !in_array($normalized, $candidateHosts, true)) {
            $candidateHosts[] = $normalized;
          }
        }
      }

      $currentHost = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
      if ($currentHost !== '') {
        $currentHost = preg_replace('/:\d+$/', '', $currentHost);
      }

      $comparisonHost = $currentHost;
      if ($comparisonHost === '127.0.0.1') {
        $comparisonHost = 'localhost';
      }

      $authorizedHost = null;
      foreach ($candidateHosts as $candidateHost) {
        $candidateHost = preg_replace('/:\d+$/', '', strtolower(trim((string) $candidateHost)));
        if ($candidateHost === '' || $candidateHost === 'http' || $candidateHost === 'https') {
          continue;
        }

        if ($comparisonHost !== '' && $comparisonHost === $candidateHost) {
          $authorizedHost = $candidateHost;
          break;
        }
      }

      if ($authorizedHost === null && $comparisonHost !== '' && ($comparisonHost === 'localhost' || str_ends_with($comparisonHost, '.localhost'))) {
        $authorizedHost = 'localhost';
      }

      if ($authorizedHost === null || $authorizedHost === '') {
        $viewSiteMessage = 'Não existe uma base pública autorizada para este ambiente.';
      } else {
        $allowedLocalHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        $scheme = in_array($authorizedHost, $allowedLocalHosts, true) ? 'http' : 'https';
        $hostnamePattern = '/^[a-z0-9.-]+$/i';

        if (!preg_match($hostnamePattern, $subdomain) || !preg_match($hostnamePattern, $authorizedHost)) {
          $viewSiteMessage = 'Subdomínio público inválido para a base autorizada.';
        } else {
          $viewSiteUrl = $scheme . '://' . $subdomain . '.' . $authorizedHost . '/';
        }
      }
    }
  }
} catch (Exception $e) {
  error_log('Erro construindo URL do site público: ' . $e->getMessage());
  $viewSiteMessage = 'Não foi possível montar a URL pública do tenant selecionado.';
}

// Verificar permissões baseadas na role do usuário
$isAdmin = isAdmin();
$isMainAdmin = $isAdmin; // Qualquer admin pode acessar backup
$pendingLeadId = null;

if ($currentTab === 'leads' && isset($_GET['lead_id'])) {
  $candidateLeadId = filter_var($_GET['lead_id'], FILTER_VALIDATE_INT);
  if ($candidateLeadId !== false && $candidateLeadId > 0) {
    $pendingLeadId = (int)$candidateLeadId;
  }
}

// Lista de abas permitidas para usuários comuns
$allowedUserTabs = ['properties', 'add-property', 'leads', 'followup'];

// Se não for admin e tentar acessar uma aba restrita, redireciona
if (!$isAdmin && !in_array($currentTab, $allowedUserTabs)) {
  header("Location: painel.php?tab=properties");
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">

<head>
  <!-- Meta tags essenciais -->
  <meta charset="utf-8">

  <!-- ⚡ CRÍTICO: Injetar variáveis de tenant PRIMEIRO, antes de qualquer script
       Isso garante que todos os scripts subsequentes tenham acesso aos valores corretos -->
  <script>
    <?php
    $correctTenantId = (int)\TenantContext::getTenantId();
    error_log("[PAINEL.PHP] ⚡ INJEÇÃO INICIAL (no head) - Tenant ID = " . $correctTenantId);
    ?>
    window.SUBDOMAIN_TENANT_ID = <?php echo $correctTenantId; ?>;
    window.TENANT_ID_SERVER = <?php echo $correctTenantId; ?>;
    window.pendingLeadId = <?php echo $pendingLeadId !== null ? (int)$pendingLeadId : 'null'; ?>;
  </script>
  <script src="/assets/js/company-settings-loader.js"></script>

  <!-- Script de Carregamento de Cores - EXECUTA ANTES DE QUALQUER RENDERIZAÇÃO -->
  <script>
    (function() {
      // Bloqueia renderização enquanto carrega cores
      document.documentElement.classList.add('preload');

      // Resolve tenant correto via variável injetada no head, preferencial sobre fallback
      const tenantIdFromServer = window.SUBDOMAIN_TENANT_ID || window.TENANT_ID_SERVER || <?php echo $correctTenantId; ?>;

      // Tentar carregar cores e branding do localStorage primeiro (cache)
      try {
        const cached = localStorage.getItem('companyColors_' + tenantIdFromServer);
        if (cached) {
          const colors = JSON.parse(cached);
          if (colors.company_color1) {
            document.documentElement.style.setProperty('--cor-primaria', colors.company_color1);
            document.documentElement.style.setProperty('--cor-primaria-rgb', colors.company_color1_rgb || '30, 64, 175');
          }
          if (colors.company_color2) {
            document.documentElement.style.setProperty('--cor-secundaria', colors.company_color2);
            document.documentElement.style.setProperty('--cor-secundaria-rgb', colors.company_color2_rgb || '16, 185, 129');
          }
          if (colors.company_color3) {
            document.documentElement.style.setProperty('--cor-destaque', colors.company_color3);
            document.documentElement.style.setProperty('--cor-destaque-rgb', colors.company_color3_rgb || '245, 158, 11');
          }

          // Se existir branding em cache (logo + nome), aplica antes da requisição final
          const cachedBrand = localStorage.getItem('companyBrand_' + tenantIdFromServer);
          if (cachedBrand) {
            try {
              const brandData = JSON.parse(cachedBrand);
              if (brandData.company_logo) {
                const logoEl = document.getElementById('company-logo-painel');
                if (logoEl) {
                  logoEl.src = brandData.company_logo;
                }
              }
              if (brandData.company_name) {
                const nameEl = document.getElementById('company-name');
                if (nameEl) {
                  nameEl.textContent = brandData.company_name;
                }
              }
            } catch (e) {
              console.debug('Erro ao aplicar branding cache', e);
            }
          }
        }
      } catch (e) {}

      // Remove preload após cores serem aplicadas
      setTimeout(() => {
        document.documentElement.classList.remove('preload');
      }, 50);
    })();
  </script>

  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <!-- Meta tags para compatibilidade mobile -->
  <meta name="format-detection" content="telephone=no">
  <meta name="theme-color" content="#111827">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

  <!-- CSS Crítico - DEVE ser o primeiro CSS carregado -->
  <style>
    :root {
      --cor-primaria: #111827;
      --cor-primaria-rgb: 17, 24, 39;
      --cor-secundaria: #10b981;
      --cor-secundaria-rgb: 16, 185, 129;
      --cor-destaque: #d97706;
      --cor-destaque-rgb: 217, 119, 6;
    }

    /* Previne flash de conteúdo e transições durante carregamento */
    .preload * {
      -webkit-transition: none !important;
      -moz-transition: none !important;
      -ms-transition: none !important;
      -o-transition: none !important;
      transition: none !important;
    }

    /* Sobrescreve cores do Bootstrap imediatamente */
    .bg-primary {
      background-color: var(--cor-primaria) !important;
    }

    .text-primary {
      color: var(--cor-primaria) !important;
    }

    .border-primary {
      border-color: var(--cor-primaria) !important;
    }

    .btn-primary {
      background-color: var(--cor-primaria) !important;
      border-color: var(--cor-primaria) !important;
    }

    /* Estilos críticos para o sidebar e elementos principais */
    .sidebar {
      background-color: var(--cor-primaria) !important;
    }

    .navbar {
      background-color: var(--cor-primaria) !important;
    }

    .nav-link.active {
      background-color: var(--cor-primaria) !important;
    }

    .btn-outline-primary {
      color: var(--cor-primaria) !important;
      border-color: var(--cor-primaria) !important;
    }

    .btn-outline-primary:hover {
      background-color: var(--cor-primaria) !important;
      color: #fff !important;
    }

    /* Aplica imediatamente para elementos do sistema */
    body {
      visibility: visible !important;
    }

    #sidebar {
      background-color: var(--cor-primaria) !important;
    }

    #content {
      background-color: #f8fafc !important;
    }

    /* Força cores personalizadas em elementos específicos */
    [style*="background-color: var(--cor-primaria)"] {
      background-color: var(--cor-primaria) !important;
    }

    [style*="color: var(--cor-primaria)"] {
      color: var(--cor-primaria) !important;
    }

    /* Ajustes de performance */
    * {
      backface-visibility: hidden;
      -webkit-backface-visibility: hidden;
    }

    body {
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
  </style>

  <!-- Preload de recursos críticos -->
  <link rel="preload" href="/assets/css/variables.css" as="style">
  <link rel="preload" href="/assets/css/sidebar.css" as="style" onload="this.rel='stylesheet'">
  <noscript>
    <link rel="stylesheet" href="/assets/css/sidebar.css">
  </noscript>
  <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" as="style">

  <!-- Polyfills com carregamento otimizado -->
  <script src="https://cdn.jsdelivr.net/npm/core-js-bundle@3.33.0/minified.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/intersection-observer@0.12.2/intersection-observer.js" async></script>

  <!-- Variáveis CSS Primeiro -->
  <link rel="stylesheet" href="/assets/css/variables.css">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- CSS do sistema -->
  <link rel="stylesheet" href="/assets/css/notifications.css">
  <link rel="stylesheet" href="/assets/css/custom.new.css">
  <link rel="stylesheet" href="/assets/css/forms-bootstrap.css">

  <!-- Scripts do sistema com carregamento otimizado -->
  <!-- AuthManager - prepara o frontend para JWT gradual -->
  <script src="/assets/js/auth/auth-manager.js" defer></script>
  <!-- API Helper - DEVE SER CARREGADO PRIMEIRO para sobrescrever fetch global -->
  <script src="/assets/js/api-helper.js" defer></script>
  <script src="/assets/js/notifications.js" defer></script>
  <script src="/assets/js/messages-handler.js" defer></script>
  <script src="/assets/js/email-notifications.js" defer></script>
  <script src="/assets/js/gmail-notifications.js" defer></script>

  <!-- Tailwind CSS -->
  <link href="/output.css" rel="stylesheet">

  <!-- Lead Timeline CSS -->
  <link href="/assets/css/lead-timeline.css" rel="stylesheet">

  <!-- Lead Inactivity Alerts CSS -->
  <link href="/assets/css/lead-inactivity-alerts.css" rel="stylesheet">

  <!-- Fallback CSS para navegadores sem suporte a Tailwind -->
  <noscript>
    <link rel="stylesheet" href="/assets/css/fallback.css">
  </noscript>
  <style>
    /* Fallbacks e suporte para navegadores antigos */

    /* Prefixes para navegadores antigos */
    @supports not (--css: variables) {
      body {
        background-color: #f3f4f6;
      }
    }

    /* Fallback para flex */
    .flex {
      display: -webkit-box;
      display: -ms-flexbox;
      display: -webkit-flex;
      display: flex;
    }

    /* Fallback para grid */
    @supports not (display: grid) {
      .grid {
        display: -webkit-box;
        display: -ms-flexbox;
        display: flex;
        -ms-flex-wrap: wrap;
        flex-wrap: wrap;
      }
    }

    .badge-grande {
      position: absolute !important;
      top: 1rem !important;
      /* Corrigido: Adicionada a unidade */
      left: 1rem !important;
      /* Novo: Move para a direita */
      font-size: 1rem !important;
      padding: 0.6rem 0.75rem !important;
      display: inline-block !important;
      font-weight: 600 !important;
      border-radius: 12px !important;
      color: white;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
      /* Opcional: melhora legibilidade */

    }


    /* Classes para badges de status */
    .badge-status {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-weight: 600;
      font-size: 0.875rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
    }

    /* Status Ativo */
    .status-ativo {
      background-color: rgba(var(--cor-secundaria-rgb), 0.1);
      color: var(--cor-secundaria);
    }

    /* Status Pendente */
    .status-pendente {
      background-color: rgba(var(--cor-destaque-rgb), 0.1);
      color: var(--cor-destaque);
    }

    /* Status Vendido/Alugado */
    .status-vendido,
    .status-alugado {
      background-color: rgba(var(--cor-primaria-rgb), 0.1);
      color: var(--cor-primaria);
    }

    /* Status Inativo */
    .status-inativo {
      background-color: rgba(75, 85, 99, 0.1);
      color: #4b5563;
    }

    /* Botão detalhes */
    .btn-detalhes {
      display: inline-block;
      margin-top: 0.75rem;
      background-color: #111827;
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      text-decoration: none;
      transition: background 0.2s;
    }

    .btn-detalhes:hover {
      filter: brightness(0.85);
      /* escurece em 15% */
    }

    .view-btn.active {
      background-color: #1d4ed8 !important;
      border-color: #1d4ed8 !important;
      color: #fff !important;
    }

    .view-btn.active i {
      color: #fff !important;
    }
  </style>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <meta name="theme-color" content="#111827">
  <meta name="description" content="<?php
                                    $tenant_id = \TenantContext::getTenantId();
                                    $tenant_name = 'Seu Painel';
                                    if (!empty($_SESSION['tenant_name'])) {
                                      $tenant_name = htmlspecialchars($_SESSION['tenant_name']);
                                    } else {
                                      try {
                                        $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
                                        $stmt->execute([$tenant_id]);
                                        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($tenant) {
                                          $tenant_name = htmlspecialchars($tenant['name']);
                                        }
                                      } catch (Exception $e) {
                                      }
                                    }
                                    echo "Painel Administrativo - {$tenant_name} | Gerencie seus imóveis de forma eficiente";
                                    ?>">
  <title><?php
          $tenant_id = \TenantContext::getTenantId();
          $tenant_name = 'Seu Painel';
          if (!empty($_SESSION['tenant_name'])) {
            $tenant_name = htmlspecialchars($_SESSION['tenant_name']);
          } else {
            try {
              $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
              $stmt->execute([$tenant_id]);
              $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
              if ($tenant) {
                $tenant_name = htmlspecialchars($tenant['name']);
              }
            } catch (Exception $e) {
            }
          }
          echo "{$tenant_name} - Painel Administrativo";
          ?></title>



  <!-- Preload de recursos críticos -->
  <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" crossorigin="anonymous">

  <!-- Gerenciador de cores -->
  <script src="assets/js/color-manager.js" defer></script>

  <!-- Cache invalidator para sincronizar mudanças entre abas e site público -->
  <script src="assets/js/cache-invalidator.js" defer></script>

  <!-- Font Awesome (ícones) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

  <script>
    window.isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    window.currentUserId = <?php echo isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'null'; ?>;
    window.currentUsername = <?php echo isset($_SESSION['username']) ? json_encode($_SESSION['username']) : 'null'; ?>;
    window.currentUserRole = <?php echo isset($_SESSION['role']) ? json_encode($_SESSION['role']) : 'null'; ?>;
    window.currentUserIsSuperAdmin = <?php echo (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin']) ? 'true' : 'false'; ?>;
    window.currentTenantId = <?php echo isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : (isset($_SESSION['selected_tenant_id']) ? (int)$_SESSION['selected_tenant_id'] : 'null'); ?>;

    /**
     * Alterna o status de um lead entre diferentes estados
     * @param {HTMLElement} element - Elemento HTML que disparou a ação
     */
    function toggleLeadStatus(element) {
      // Encontra o container e o menu
      const container = element.closest('.lead-status-container');
      const menu = container.querySelector('.lead-status-menu');
      const allMenus = document.querySelectorAll('.lead-status-menu');

      // Fecha todos os outros menus
      allMenus.forEach(m => {
        if (m !== menu) {
          m.classList.add('hidden');
          m.style.display = 'none';
        }
      });

      // Toggle do menu atual
      if (menu.classList.contains('hidden')) {
        menu.classList.remove('hidden');

        // Posiciona o dropdown usando fixed
        const rect = element.getBoundingClientRect();
        menu.style.left = rect.left + 'px';
        menu.style.top = (rect.bottom + 4) + 'px';
        menu.style.display = 'block';

        // Pequeno delay para a animação funcionar
        setTimeout(() => {
          menu.style.opacity = '1';
          menu.style.transform = 'translateY(0)';
        }, 10);
      } else {
        menu.style.opacity = '0';
        menu.style.transform = 'translateY(-10px)';
        setTimeout(() => {
          menu.classList.add('hidden');
          menu.style.display = 'none';
        }, 200);
      }

      // Click fora para fechar
      const closeMenu = (e) => {
        if (!container.contains(e.target) && !menu.contains(e.target)) {
          menu.style.opacity = '0';
          menu.style.transform = 'translateY(-10px)';
          setTimeout(() => {
            menu.classList.add('hidden');
            menu.style.display = 'none';
          }, 200);
          document.removeEventListener('click', closeMenu);
        }
      };

      // Remove handler antigo antes de adicionar novo
      document.removeEventListener('click', closeMenu);
      // Adiciona novo handler no próximo ciclo para evitar fechamento imediato
      setTimeout(() => {
        document.addEventListener('click', closeMenu);
      }, 0);
    }

    // Event delegation para os botões de status (com guard otimizado)
    document.addEventListener('click', function(e) {
      // Guard: verificar rapidamente se é relevante
      if (!e.target.closest('.lead-status-option, .notifications-btn, #messages-btn')) {
        return;
      }

      const option = e.target.closest('.lead-status-option');
      if (option) {
        const newStatus = option.dataset.value;
        const menu = option.closest('.lead-status-menu');
        const container = menu.closest('.lead-status-container');
        const statusDiv = container.querySelector('.lead-status');
        const leadId = statusDiv.dataset.id;

        // Salva o status anterior
        const oldStatus = statusDiv.getAttribute('data-value');
        statusDiv.setAttribute('data-original-status', oldStatus);

        // Atualiza o status visualmente com animação
        statusDiv.style.transform = 'scale(0.95)';
        statusDiv.style.opacity = '0.7';

        setTimeout(() => {
          statusDiv.className = `lead-status ${getLeadStatusClass(newStatus)}`;
          statusDiv.setAttribute('data-value', newStatus);
          statusDiv.innerHTML = `
            <i class="${getLeadStatusIcon(newStatus)}"></i>
            <span>${getLeadStatusText(newStatus)}</span>
          `;
          statusDiv.style.transform = 'scale(1)';
          statusDiv.style.opacity = '1';
        }, 150);

        // Fecha o menu com animação
        menu.style.opacity = '0';
        menu.style.transform = 'translateY(-10px)';
        setTimeout(() => {
          menu.classList.add('hidden');
        }, 200);

        // Atualiza no servidor
        updateLeadStatus(leadId, newStatus);
      }
    }, {
      capture: false,
      passive: true
    });

    /**
     * Atualiza o status de um lead no servidor
     * @param {number} leadId - ID do lead
     * @param {string} status - Novo status do lead
     * @returns {Promise<void>}
     */
    async function updateLeadStatus(leadId, status) {
      const statusDiv = document.querySelector(`.lead-status[data-id="${leadId}"]`);

      try {
        const response = await fetch('/api/v1/leads/updateLead.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include',
          body: JSON.stringify({
            id: leadId,
            status: status
          })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
          throw new Error(data.error || 'Erro ao atualizar status');
        }

        // Feedback visual simples de sucesso
        const successFeedback = document.createElement('div');
        successFeedback.className = 'alert alert-success position-fixed top-0 end-0 m-3';
        successFeedback.style.zIndex = '9999';
        successFeedback.innerHTML = '<i class="fas fa-check-circle me-2"></i>Status atualizado com sucesso!';
        document.body.appendChild(successFeedback);

        setTimeout(() => {
          successFeedback.remove();
        }, 3000);

      } catch (err) {
        console.error('Erro ao atualizar status:', err);

        // Feedback visual de erro
        const errorFeedback = document.createElement('div');
        errorFeedback.className = 'alert alert-danger position-fixed top-0 end-0 m-3';
        errorFeedback.style.zIndex = '9999';
        errorFeedback.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${err.message}`;
        document.body.appendChild(errorFeedback);

        setTimeout(() => {
          errorFeedback.remove();
        }, 5000);

        // Reverte o status visual se houver erro
        if (statusDiv) {
          const oldStatus = statusDiv.getAttribute('data-original-status');
          if (oldStatus) {
            statusDiv.className = `lead-status ${getLeadStatusClass(oldStatus)}`;
            statusDiv.innerHTML = `
              <i class="${getLeadStatusIcon(oldStatus)}"></i>
              <span>${getLeadStatusText(oldStatus)}</span>
            `;
          }
        }
      }
    }
  </script>



  <style>
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap');

    body {
      font-family: var(--fonte-principal, 'Open Sans', sans-serif);
      scroll-behavior: smooth;
    }

    /* Estilos para status dos leads */
    .lead-status {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.2s;
      cursor: pointer;
      border: 2px solid transparent;
      user-select: none;
      position: relative;
    }

    .lead-status::after {
      content: '\f107';
      font-family: 'Font Awesome 6 Free';
      font-weight: 900;
      margin-left: 0.5rem;
      font-size: 0.875rem;
      opacity: 0.7;
    }

    .lead-status:hover {
      transform: translateY(-1px);
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .lead-status.novo {
      background-color: #EEF2FF;
      color: #4F46E5;
      border-color: #E0E7FF;
    }

    .lead-status.em-contato {
      background-color: #FEF3C7;
      color: #D97706;
      border-color: #FDE68A;
    }

    .lead-status.convertido {
      background-color: #DCFCE7;
      color: #15803D;
      border-color: #BBF7D0;
    }

    .lead-status.perdido {
      background-color: #FEE2E2;
      color: #DC2626;
      border-color: #FECACA;
    }

    .lead-status.aguardando {
      background-color: #F3F4F6;
      color: #4B5563;
      border-color: #E5E7EB;
    }

    /* Ícones para os status */
    .lead-status i {
      margin-right: 0.5rem;
      font-size: 1rem;
    }

    /* Estilos do menu dropdown */
    .lead-status-menu {
      position: absolute;
      background: white;
      border-radius: 0.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      z-index: 50;
      min-width: 160px;
      opacity: 0;
      transform: translateY(-10px);
      transition: all 0.2s;
      pointer-events: auto;
    }

    .lead-status-menu.hidden {
      pointer-events: none;
      display: none !important;
    }

    .lead-status-menu:not(.hidden) {
      opacity: 1;
      transform: translateY(0);
    }

    .lead-status-option {
      padding: 0.5rem 1rem;
      transition: all 0.2s;
      width: 100%;
      text-align: left;
      display: flex;
      align-items: center;
    }

    .lead-status-option:hover {
      background-color: #f3f4f6;
    }

    .lead-status-option i {
      margin-right: 0.5rem;
      width: 1rem;
      text-align: center;
    }

    /* Cores dos ícones no menu */
    .lead-status-option.novo i {
      color: #4F46E5;
    }

    .lead-status-option.em-contato i {
      color: #D97706;
    }

    .lead-status-option.convertido i {
      color: #15803D;
    }

    .lead-status-option.perdido i {
      color: #DC2626;
    }

    .lead-status-option.aguardando i {
      color: #4B5563;
    }

    /* Animações e transições */
    .lead-status-menu {
      transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
      transform: translateY(-10px);
      opacity: 0;
      pointer-events: none;
    }

    .lead-status-menu:not(.hidden) {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }

    /* Efeito de ripple no clique */
    .lead-status {
      position: relative;
      overflow: hidden;
    }

    .lead-status:active::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      transform: translate(-50%, -50%) scale(0);
      animation: ripple 0.6s linear;
      pointer-events: none;
    }

    @keyframes ripple {
      to {
        transform: translate(-50%, -50%) scale(4);
        opacity: 0;
      }
    }

    .slide-in {
      animation: slideIn 0.5s ease forwards;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .fade-in {
      animation: fadeIn 0.8s ease forwards;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    .sidebar {
      transition: all 0.3s ease;
      background-color: var(--cor-primaria);
    }

    .sidebar nav ul {
      margin: 0;
      padding: 0;
      list-style: none;
    }

    .sidebar nav ul li {
      margin-bottom: 0.4rem;
    }

    .sidebar nav ul li:last-child {
      margin-bottom: 0;
    }

    .sidebar nav ul li a {
      padding: 0.7rem 1rem !important;
      gap: 0.75rem !important;
      line-height: 1.25;
      font-size: 0.96rem;
    }

    .sidebar nav ul li a .sidebar-text {
      white-space: nowrap;
    }

    .sidebar-collapsed {
      width: 5rem;
    }

    .sidebar-expanded {
      width: 16rem;
    }

    .main-content {
      transition: all 0.3s ease;
    }

    .main-content-collapsed {
      margin-left: 5rem;
    }

    .main-content-expanded {
      margin-left: 16rem;
    }

    .property-card {
      transition: all 0.3s ease;
    }

    .property-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    /* Estilo para atualização das categorias */
    .updating {
      position: relative;
      opacity: 0.7;
      transition: opacity 0.3s ease;
    }

    .updating::after {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 2px;
      background: linear-gradient(90deg, var(--cor-primaria), var(--cor-secundaria));
      animation: loading 1.5s infinite ease-in-out;
    }

    @keyframes loading {
      0% {
        transform: translateX(-100%);
      }

      100% {
        transform: translateX(100%);
      }
    }

    .fade-out {
      animation: fadeOut 0.5s ease-out forwards;
    }

    @keyframes fadeOut {
      from {
        opacity: 1;
      }

      to {
        opacity: 0;
      }
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    /* Estilos para as abas de Configurações */
    .settings-subtabs {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      border-bottom: 2px solid #e5e7eb;
    }

    .settings-subtab-link {
      padding: 0.75rem 1.5rem;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 500;
      color: #6b7280;
      border-bottom: 3px solid transparent;
      transition: all 0.3s ease;
      position: relative;
      bottom: -2px;
    }

    .settings-subtab-link:hover {
      color: #374151;
    }

    .settings-subtab-link.active {
      color: var(--cor-primaria);
      border-bottom-color: var(--cor-primaria);
    }

    .integrations-subtabs {
      display: flex;
      flex-wrap: wrap;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }

    .integrations-subtab-link {
      padding: 0.75rem 1.25rem;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 600;
      color: #6b7280;
      border-bottom: 3px solid transparent;
      transition: all 0.25s ease;
      position: relative;
      bottom: -2px;
      border-radius: 0.75rem 0.75rem 0 0;
    }

    .integrations-subtab-link:hover {
      color: #111827;
    }

    .integrations-subtab-link.active {
      color: #0f172a;
      border-bottom-color: #111827;
    }

    .settings-subtab-content {
      animation: fadeIn 0.3s ease;
    }

    .settings-subtab-content.hidden {
      display: none;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Estilos para as sub-abas de Alertas */
    .alertas-subtab-link {
      padding: 0.75rem 1.5rem;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 500;
      color: #6b7280;
      border-bottom: 3px solid transparent;
      transition: all 0.3s ease;
      position: relative;
      bottom: -2px;
    }

    .alertas-subtab-link:hover {
      color: #374151;
    }

    .alertas-subtab-link.active {
      color: var(--cor-primaria);
      border-bottom-color: var(--cor-primaria);
    }

    .alertas-subtab-content {
      animation: fadeIn 0.3s ease;
    }

    .alertas-subtab-content.hidden {
      display: none;
    }

    /* Estilos para as sub-abas de Relatórios */
    .relatorios-subtab-link {
      padding: 0.75rem 1.5rem;
      background: none;
      border: none;
      cursor: pointer;
      font-weight: 500;
      color: #6b7280;
      border-bottom: 3px solid transparent;
      transition: all 0.3s ease;
      position: relative;
      bottom: -2px;
    }

    .relatorios-subtab-link:hover {
      color: #374151;
    }

    .relatorios-subtab-link.active {
      color: var(--cor-primaria);
      border-bottom-color: var(--cor-primaria);
    }

    .relatorios-subtab-content {
      animation: fadeIn 0.3s ease;
    }

    .relatorios-subtab-content.hidden {
      display: none;
    }

    .color-preview {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      display: inline-block;
      margin-right: 10px;
      vertical-align: middle;
    }

    #properties-container {
      display: grid;
      gap: 1.5rem;
      transition: all 0.3s ease;
      padding: 1rem;
    }

    #properties-container.grid-view {
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    }

    #properties-container.list-view {
      grid-template-columns: 1fr;
    }

    .property-card {
      overflow: visible;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
      height: 100%;
      border-radius: 0.75rem;
      background-color: #fff;
    }

    .property-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    /* Estilos para visualização em grade */
    .grid-view .property-card {
      display: flex;
      flex-direction: column;
      min-height: 500px;
    }

    .grid-view .property-image-container img {
      width: 100%;
      height: 192px;
      object-fit: cover;
    }

    /* Limitar texto para consistent layout */
    .line-clamp-1 {
      display: -webkit-box;
      -webkit-line-clamp: 1;
      line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* Estilos para visualização em lista */
    .list-view .property-card {
      display: grid;
      grid-template-columns: 250px 1fr auto;
      align-items: stretch;
    }

    .list-view .property-image-container {
      height: 100%;
    }

    .list-view .property-image-container img {
      height: 100%;
      width: 100%;
      object-fit: cover;
    }

    .list-view .property-info {
      padding: 1.5rem;
    }

    .list-view .property-actions {
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 1rem;
      gap: 0.5rem;
      border-left: 1px solid #e5e7eb;
    }

    /* Responsividade para modo lista em telas menores */
    @media (max-width: 768px) {
      .list-view .property-card {
        grid-template-columns: 1fr;
      }

      .list-view .property-image-container img {
        height: 200px;
      }

      .list-view .property-actions {
        border-left: none;
        border-top: 1px solid #e5e7eb;
        flex-direction: row;
        justify-content: space-between;
      }
    }

    /* Estilos de Paginação */
    nav[aria-label="Paginação"] {
      flex-wrap: wrap;
      justify-content: center;
    }

    #pagination-buttons {
      flex-wrap: wrap;
      gap: 0.25rem;
    }

    #pagination-buttons button,
    #pagination-buttons .pagination-ellipsis {
      padding: 0.5rem 0.75rem;
      font-size: 0.875rem;
    }

    #prev-page:disabled,
    #next-page:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    #pagination-buttons button.active {
      background-color: var(--cor-primaria);
      color: white;
      border-color: var(--cor-primaria);
    }

    /* ===== Estilos do Pipeline Kanban ===== */
    .pipeline-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 24px;
      overflow-x: auto;
      padding: 24px;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      border-radius: 16px;
      min-height: 700px;
    }

    .pipeline-stage {
      background: white;
      border-radius: 14px;
      border-top: 4px solid var(--stage-color, #3B82F6);
      min-height: 650px;
      display: flex;
      flex-direction: column;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
      overflow: hidden;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .pipeline-stage:hover {
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.18);
      transform: translateY(-4px);
    }

    .pipeline-stage-header {
      padding: 20px;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
      border-bottom: 2px solid #f0f0f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .pipeline-stage-title {
      font-weight: 700;
      font-size: 16px;
      color: #1a1a1a;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .pipeline-stage-title::before {
      content: '';
      width: 4px;
      height: 24px;
      background: var(--stage-color, #3B82F6);
      border-radius: 2px;
    }

    .pipeline-stage-count {
      background: linear-gradient(135deg, var(--stage-color, #3B82F6) 0%, rgba(var(--stage-color-rgb, 59, 130, 246), 0.8) 100%);
      color: white;
      border-radius: 24px;
      padding: 6px 14px;
      font-size: 13px;
      font-weight: 700;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .pipeline-items {
      flex: 1;
      overflow-y: auto;
      padding: 16px;
      min-height: 500px;
      background: linear-gradient(to bottom, #fafbfc 0%, #ffffff 100%);
    }

    .pipeline-items::-webkit-scrollbar {
      width: 6px;
    }

    .pipeline-items::-webkit-scrollbar-track {
      background: #f0f0f0;
      border-radius: 3px;
    }

    .pipeline-items::-webkit-scrollbar-thumb {
      background: var(--stage-color, #3B82F6);
      border-radius: 3px;
    }

    .pipeline-items::-webkit-scrollbar-thumb:hover {
      background: rgba(59, 130, 246, 0.8);
    }

    .pipeline-item {
      background: white;
      border-radius: 10px;
      padding: 14px;
      margin-bottom: 12px;
      border: 1.5px solid #e8e8e8;
      border-left: 4px solid var(--stage-color, #3B82F6);
      cursor: grab;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
    }

    .pipeline-item:hover {
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
      transform: translateY(-3px);
      border-color: var(--stage-color, #3B82F6);
    }

    .pipeline-item:active {
      cursor: grabbing;
    }

    .pipeline-item.dragging {
      opacity: 0.6;
      transform: rotate(2deg) scale(0.98);
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
    }

    .item-title {
      font-weight: 700;
      font-size: 14px;
      color: #1a1a1a;
      margin-bottom: 8px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      letter-spacing: 0.3px;
    }

    .item-subtitle {
      font-size: 12px;
      color: #666;
      margin-bottom: 10px;
      line-height: 1.5;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .item-value {
      background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
      color: #2e7d32;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 700;
      display: inline-block;
      margin-bottom: 8px;
      border: 1px solid rgba(46, 125, 50, 0.2);
    }

    .item-date {
      font-size: 11px;
      color: #999;
      display: flex;
      align-items: center;
      gap: 4px;
      margin-top: 6px;
    }

    .pipeline-empty {
      padding: 40px 20px;
      text-align: center;
      color: #999;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .pipeline-empty i {
      font-size: 48px;
      margin-bottom: 16px;
      opacity: 0.3;
    }

    .add-item-btn {
      margin-top: auto;
      padding: 12px;
    }

    .add-item-btn .btn {
      width: 100%;
      border-radius: 10px;
      background: linear-gradient(135deg, var(--stage-color, #3B82F6) 0%, rgba(59, 130, 246, 0.8) 100%);
      border: none;
      color: white;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .add-item-btn .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
    }

    /* Animações e transições do Pipeline */
    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    @keyframes pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: 0.7;
      }
    }

    .pipeline-stage {
      animation: slideInUp 0.5s ease-out;
    }

    .pipeline-item {
      animation: slideInUp 0.4s ease-out;
    }

    .pipeline-stage:nth-child(2) {
      animation-delay: 0.1s;
    }

    .pipeline-stage:nth-child(3) {
      animation-delay: 0.2s;
    }

    .pipeline-stage:nth-child(4) {
      animation-delay: 0.3s;
    }

    .pipeline-stage:nth-child(5) {
      animation-delay: 0.4s;
    }

    .pipeline-stage:nth-child(6) {
      animation-delay: 0.5s;
    }

    .pipeline-stage:nth-child(7) {
      animation-delay: 0.6s;
    }

    /* Status Badges */
    .pipeline-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      background: rgba(59, 130, 246, 0.1);
      color: #3B82F6;
      margin-top: 4px;
    }

    /* Loading animation */
    #loadingMessage {
      animation: fadeIn 0.3s ease-in;
    }

    /* Responsive Pipeline */
    @media (max-width: 768px) {
      .pipeline-container {
        grid-template-columns: 1fr;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
      }

      .pipeline-stage {
        scroll-snap-align: start;
        min-height: 600px;
      }
    }

    .item-details-row {
      display: flex;
      flex-wrap: nowrap;
      gap: 12px;
      align-items: center;
      overflow-x: auto;
      white-space: nowrap;
      padding: 0 4px;
      margin-bottom: 12px;
    }

    .item-detail-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 12px;
      border-radius: 999px;
      background: #f5f7fa;
      border: 1px solid #d1d5db;
      color: #1f2937;
      font-size: 0.95rem;
      line-height: 1.3;
    }

    .item-detail-pill strong {
      font-weight: 700;
    }

    /* Garantir visibilidade do ícone do Pipeline */
    a[href="#pipeline"] i {
      color: white !important;
      font-size: 18px !important;
      display: inline-block !important;
      min-width: 20px;
      font-weight: 900;
    }
  </style>
  <script src="/assets/js/painel/agendamentos.js" defer></script>
  <script src="/assets/js/painel/financeiro.js" defer></script>
  <script src="/assets/js/painel/commissions.js" defer></script>
  <script src="/assets/js/painel/alertas.js" defer></script>
  <script src="/assets/js/painel/alertas-subtabs.js" defer></script>
  <script src="/assets/js/painel/sales-alert.js" defer></script>
  <script src="/assets/js/painel/relatorios.js" defer></script>
  <script src="/assets/js/painel/relatorios-subtabs.js" defer></script>
  <script src="/assets/js/painel/relatorios-utm.js" defer></script>
  <script src="/assets/js/painel/webhooks.js" defer></script>

  <!-- Script de Carregamento de Cores Otimizado -->
  <script>
    (function() {
      const tenantId = window.SUBDOMAIN_TENANT_ID || window.TENANT_ID_SERVER || 1;

      // Função para converter HEX para RGB
      function hexToRgb(hex) {
        if (!hex) return '0, 0, 0';
        hex = hex.replace(/^#/, '');
        if (!/^[0-9A-Fa-f]{6}$/.test(hex)) return '0, 0, 0';
        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);
        return `${r}, ${g}, ${b}`;
      }

      // Carrega cores da API em paralelo (não bloqueia renderização)
      // Usar prioridade baixa para não bloquear carregamento
      const painelSettingsPromise = window.getCompanySettings();

      painelSettingsPromise
        .then(json => {
          if (!json.success || !json.data) return;

          const data = json.data;
          const root = document.documentElement;

          // Aplica cores ao DOM se forem diferentes do cache
          if (data.company_color1) {
            root.style.setProperty('--cor-primaria', data.company_color1);
            root.style.setProperty('--cor-primaria-rgb', hexToRgb(data.company_color1));
          }
          if (data.company_color2) {
            root.style.setProperty('--cor-secundaria', data.company_color2);
            root.style.setProperty('--cor-secundaria-rgb', hexToRgb(data.company_color2));
          }
          if (data.company_color3) {
            root.style.setProperty('--cor-destaque', data.company_color3);
            root.style.setProperty('--cor-destaque-rgb', hexToRgb(data.company_color3));
          }

          // Atualiza o logo da empresa no sidebar
          if (data.company_logo) {
            const logoEl = document.getElementById('company-logo-painel');
            if (logoEl) {
              logoEl.src = data.company_logo;
            }
          }
        })
        .catch(err => console.debug('Cores carregadas do cache'));
    })();
  </script>
</head>

<body class="bg-gray-100 preload">
  <script>
    // Remove a classe preload após o carregamento
    window.addEventListener('load', function() {
      document.body.classList.remove('preload');
    });
  </script>
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <div class="sidebar sidebar-expanded text-white fixed h-full z-30" id="sidebar">
      <div class="p-4">
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center space-x-2">
            <img id="company-logo-painel" src="<?php echo htmlspecialchars($companyLogo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="Logo" class="h-8 w-8 object-contain mr-2" onerror="this.style.display='none'">
            <span class="text-xl font-bold" id="company-name"><?php echo htmlspecialchars($companyName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
          </div>
          <button class="text-white bg-black hover:bg-gray-800 focus:outline-none p-1.5 rounded-md transition-colors shadow-sm" id="toggle-sidebar">
            <i class="fas fa-bars text-sm"></i>
          </button>
        </div>
        <nav>
          <ul class="space-y-1">
            <li>
              <a class="d-flex align-items-center gap-2 py-2 px-3 rounded text-white text-decoration-none transition tab-link"
                href="#dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span class="sidebar-text">Dashboard</span>
              </a>
            </li>
            <li>
              <a class="d-flex align-items-center gap-2 py-2 px-3 rounded text-white text-decoration-none transition tab-link"
                href="#properties">
                <i class="fas fa-home"></i>
                <span class="sidebar-text">Imóveis</span>
              </a>
            </li>
            <li>
              <a class="d-flex align-items-center gap-2 py-2 px-3 rounded text-white text-decoration-none transition tab-link"
                href="#add-property">
                <i class="fas fa-plus-circle"></i>
                <span class="sidebar-text">Adicionar Imóvel</span>
              </a>
            </li>
            <li>
              <a class="d-flex align-items-center gap-2 py-2 px-3 rounded text-white text-decoration-none transition tab-link"
                href="#leads">
                <i class="fas fa-user-friends"></i>
                <span class="sidebar-text">Leads</span>
              </a>
            </li>
            <li>
              <a class="d-flex align-items-center gap-2 py-2 px-3 rounded text-white text-decoration-none transition tab-link"
                href="#followup">
                <i class="fas fa-envelope-open-text"></i>
                <span class="sidebar-text">Follow-up</span>
              </a>
            </li>
            <?php if ($isAdmin): ?>
              <li>
                <a class="d-flex align-items-center gap-2 py-2 px-3 rounded text-white text-decoration-none transition tab-link"
                  href="#settings">
                  <i class="fas fa-cog"></i>
                  <span class="sidebar-text">Configurações</span>
                </a>
              </li>
            <?php endif; ?>
            <li>
              <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                href="#agendamentos">
                <i class="fas fa-calendar-alt"></i>
                <span class="sidebar-text">Agendamentos</span>
              </a>
            </li>
            <?php if (!$isAdmin): ?>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#pipeline">
                  <i class="fas fa-funnel"></i>
                  <span class="sidebar-text">Pipeline</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#relatorios">
                  <i class="fas fa-chart-bar"></i>
                  <span class="sidebar-text">Relatórios</span>
                </a>
              </li>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#financeiro">
                  <i class="fas fa-money-bill-wave"></i>
                  <span class="sidebar-text">Financeiro</span>
                </a>
              </li>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#comissoes">
                  <i class="fas fa-percent"></i>
                  <span class="sidebar-text">Comissões</span>
                </a>
              </li>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#alertas">
                  <i class="fas fa-bell"></i>
                  <span class="sidebar-text">Alertas</span>
                </a>
              </li>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#integrations">
                  <i class="fas fa-plug"></i>
                  <span class="sidebar-text">Integrações</span>
                </a>
              </li>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#webhooks">
                  <i class="fas fa-plug"></i>
                  <span class="sidebar-text">Webhooks</span>
                </a>
              </li>
              <?php if ($isAdmin && $showBillingMenuItem): ?>
                <li>
                  <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" style="background-color: rgba(255, 193, 7, 0.2); border-left: 3px solid #ffc107;"
                    href="#cobranca">
                    <i class="fas fa-wallet" style="color: #ffc107;"></i>
                    <span class="sidebar-text">⚠️ Cobrança</span>
                  </a>
                </li>
              <?php endif; ?>
              <li>
                <a class="d-flex align-items-center gap-2 py-2 px-3 rounded text-white text-decoration-none transition"
                  href="planos.php">
                  <i class="fas fa-credit-card"></i>
                  <span class="sidebar-text">Planos</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
          <!-- Users Tab (apenas para admin) -->
          <?php if ($isAdmin): ?>
            <!-- O bloco da aba Usuários foi movido para dentro da main-content -->
          <?php endif; ?>
          <!-- O script de carregamento dinâmico da aba Usuários foi movido para o final do body para garantir que o conteúdo seja exibido na área principal -->
        </nav>
      </div>

      <div class="position-absolute bottom-0 start-0 end-0 p-4">
        <div class="d-flex align-items-center gap-2 py-3 px-4">
          <div class="d-flex align-items-center justify-content-center rounded-circle bg-primary text-white text-decoration-none flex-shrink-0" style="width: 40px; height: 40px;">
            <span class="fw-bold"><?php echo substr(getUserName(), 0, 2); ?></span>
          </div>
          <div class="sidebar-text">
            <p class="font-medium"><?php echo htmlspecialchars(getUserName()); ?></p>
            <p class="text-xs text-white"><?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?></p>
          </div>
        </div>
        <a href="logout.php" class="d-flex align-items-center justify-center gap-3 py-3 px-4 rounded-circle text-white text-decoration-none transition">
          <i class="fas fa-sign-out-alt"></i>
          <span class="sidebar-text">Sair</span>
        </a>

      </div>
    </div>
    <!-- Main Content -->
    <div class="main-content main-content-expanded flex-1" id="main-content">
      <!-- Container de Alertas -->
      <div id="alerts" class="mb-4"></div>

      <?php if ($isAdmin): ?>
      <?php endif; ?>

      <!-- Alert Box showing Tenant Info for SUPERADMIN -->
      <?php if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin']): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <i class="fas fa-info-circle text-blue-500 text-xl"></i>
            </div>
            <div class="ml-3">
              <p class="text-sm text-blue-700">
                <strong>Modo SUPERADMIN:</strong> Você está visualizando dados da <strong>Empresa ID <?php echo TenantContext::getTenantId(); ?></strong>.
                Trocar de empresa: <a href="/select_tenant.php" class="underline font-bold hover:text-blue-900">Clique aqui</a>
              </p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Top Bar -->
      <div class="bg-white shadow-sm sticky top-0 z-20">
        <div class="flex justify-between items-center px-6 py-3">
          <div class="flex items-center space-x-4">
            <div class="flex flex-col">
              <h1 class="text-xl font-semibold text-gray-800">Painel Administrativo</h1>
              <?php
              // Obter nome do tenant para exibição
              $tenant_name = $_SESSION['tenant_name'] ?? null;
              $tenant_id = TenantContext::getTenantId();

              // Se não tiver em session, buscar no BD
              if (!$tenant_name && $tenant_id) {
                try {
                  $stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ? LIMIT 1");
                  $stmt->execute([$tenant_id]);
                  $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                  if ($tenant) {
                    $tenant_name = $tenant['name'];
                  }
                } catch (Exception $e) {
                  // Falhou, usar apenas o ID
                }
              }

              $display_text = "Empresa #{$tenant_id}";
              if ($tenant_name) {
                $display_text = "Empresa #{$tenant_id} - {$tenant_name}";
              }
              ?>
              <p class="text-sm text-gray-600">
                <?php echo htmlspecialchars($display_text); ?>
                <span class="font-semibold text-gray-800"></span>
                <?php if ($_SESSION['is_superadmin']): ?>
                  <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded ml-2">(SUPERADMIN)</span>
                <?php endif; ?>
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Botão de Notificações (para admins e usuários) -->
            <div class="relative">
              <button id="notifications-btn" class="relative p-2 rounded-full hover:bg-gray-100 transition">
                <i class="far fa-bell text-gray-600"></i>
                <span id="notifications-badge" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full hidden"></span>
              </button>
              <!-- Dropdown de Notificações -->
              <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50">
                <div class="p-4 border-b">
                  <h3 class="text-lg font-semibold text-gray-800">Notificações</h3>
                </div>
                <div id="notifications-list" class="max-h-96 overflow-y-auto">
                  <!-- Notificações serão inseridas aqui -->
                </div>
                <div class="p-4 border-t text-center space-y-2">
                  <button id="mark-all-notifications-read" class="text-blue-600 hover:text-blue-800 text-sm">Marcar todas como lidas</button>
                </div>
              </div>
            </div>

            <!-- Botão de Mensagens -->
            <div class="relative">
              <button id="messages-btn" class="relative p-2 rounded-full hover:bg-gray-100 transition">
                <i class="far fa-envelope text-gray-600"></i>
                <span id="messages-badge" class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full hidden"></span>
              </button>
              <!-- Dropdown de Mensagens -->
              <div id="messages-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg z-50">
                <div class="p-4 border-b">
                  <h3 class="text-lg font-semibold text-gray-800">Mensagens</h3>
                </div>
                <div id="messages-list" class="max-h-96 overflow-y-auto">
                  <!-- Mensagens serão inseridas aqui -->
                </div>
                <div class="p-4 border-t text-center">
                  <button id="mark-all-messages-read" class="text-blue-600 hover:text-blue-800 text-sm">Marcar todas como lidas</button>
                </div>
              </div>
            </div>
            <?php if (!empty($viewSiteMessage)): ?>
              <div class="mt-1 text-xs text-red-600" aria-live="polite"><?php echo htmlspecialchars($viewSiteMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <a class="<?php echo $viewSiteUrl === '' ? 'text-gray-500 pointer-events-none cursor-not-allowed' : 'text-blue-600 hover:text-blue-800'; ?> transition"
              href="<?php echo htmlspecialchars($viewSiteUrl !== '' ? $viewSiteUrl : '#', ENT_QUOTES, 'UTF-8'); ?>"
              target="<?php echo $viewSiteUrl === '' ? '_self' : '_blank'; ?>"
              rel="noopener noreferrer"
              id="view-site-btn"
              <?php if ($viewSiteUrl === ''): ?>onclick="event.preventDefault(); return false;" aria-disabled="true" title="<?php echo htmlspecialchars($viewSiteMessage, ENT_QUOTES, 'UTF-8'); ?>" <?php endif; ?>>
              <i class="fas fa-external-link-alt mr-1"></i> Ver Site
            </a>
          </div>
        </div>
      </div>


      <!-- Agendamentos Tab -->
      <div class="tab-content p-6 hidden" id="agendamentos">
        <div class="mb-8">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Agendamentos de Visitas</h2>
            <div class="flex gap-2">
              <button onclick="abrirModalAgendamento()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition" style="background-color: var(--cor-primaria);">
                <i class="fas fa-plus mr-2"></i>Novo Agendamento
              </button>
              <button onclick="abrirConfigGoogleCalendar()" class="bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition" title="Sincronizar com Google Calendar">
                <i class="fas fa-calendar-check mr-2"></i>Google Calendar
              </button>
            </div>
          </div>

          <!-- Google Calendar Status Card -->
          <div id="google-calendar-card" style="display: none;" class="bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="font-semibold text-blue-900 mb-1">
                  <i class="fas fa-calendar-check text-blue-600 mr-2"></i>Google Calendar
                </h4>
                <p class="text-sm text-blue-700">
                  Status: <span id="google-cal-status" class="font-semibold">Verificando...</span>
                </p>
              </div>
              <button onclick="sincronizarGoogleCalendarNow()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-3 rounded text-sm transition">
                <i class="fas fa-sync-alt mr-1"></i>Sincronizar
              </button>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="mb-4 flex gap-4">
              <select id="agendamentos-filtro" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="proximos">Próximos</option>
                <option value="passados">Passados</option>
                <option value="todos">Todos</option>
              </select>
              <select id="agendamentos-status" class="px-4 py-2 border border-gray-300 rounded-lg">
                <option value="todos">Todos os Status</option>
                <option value="confirmado">Confirmado</option>
                <option value="cancelado">Cancelado</option>
                <option value="realizado">Realizado</option>
              </select>
              <input type="month" id="agendamentos-mes" class="px-4 py-2 border border-gray-300 rounded-lg" value="<?php echo date('Y-m'); ?>">
            </div>

            <div id="agendamentos-container" class="space-y-4">
              <div class="text-center py-8 text-gray-500">Carregando agendamentos...</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pipeline Tab (para usuários comuns) -->
      <?php if (!$isAdmin): ?>
        <div class="tab-content p-6 hidden" id="pipeline">
          <div class="mb-8">
            <div class="flex justify-between items-start mb-8">
              <div>
                <h1 class="text-4xl font-bold text-gray-800 mb-2 flex items-center gap-3">
                  <i class="fas fa-funnel" style="color: var(--cor-primaria); font-size: 32px;"></i>
                  Pipeline de Vendas
                </h1>
                <p class="text-gray-600 text-lg">Gerencie seus leads e imóveis pelo funil de vendas</p>
              </div>
              <button onclick="abrirModalAdicionarAoPipeline()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition flex items-center gap-2 shadow-lg hover:shadow-xl transform hover:scale-105" style="background: linear-gradient(135deg, var(--cor-primaria) 0%, rgba(var(--cor-primaria-rgb), 0.8) 100%);">
                <i class="fas fa-plus"></i> Adicionar ao Pipeline
              </button>
            </div>
          </div>

          <div id="loadingMessage" class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center">
            <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4 block"></i>
            <p class="text-blue-700 font-medium">Carregando pipeline...</p>
          </div>

          <div class="pipeline-container" id="pipelineContainer" style="display: none;">
            <!-- Estágios serão carregados aqui -->
          </div>

          <!-- Modal para exibir detalhes do item -->
          <div class="modal fade" id="itemModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <div class="modal-content rounded-xl shadow-2xl">
                <div class="modal-header border-b border-gray-200">
                  <h5 class="modal-title text-xl font-bold text-gray-800" id="itemModalTitle">Detalhes do Item</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-6" id="itemModalBody">
                  <!-- Conteúdo será carregado aqui -->
                </div>
                <div class="modal-footer border-t border-gray-200 bg-gray-50 p-4">
                  <button type="button" class="btn btn-secondary rounded-lg" data-bs-dismiss="modal">Fechar</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal para adicionar novo item -->
          <div class="modal fade" id="addItemModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <div class="modal-content rounded-xl shadow-2xl">
                <div class="modal-header border-b border-gray-200">
                  <h5 class="modal-title text-xl font-bold text-gray-800">Adicionar ao Pipeline</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addItemForm" onsubmit="salvarItemPipeline(event)">
                  <div class="modal-body p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                      <div class="form-group">
                        <label for="itemLead" class="form-label font-semibold">Lead</label>
                        <select id="itemLead" class="form-select rounded-lg" required>
                          <option value="">Selecione um lead...</option>
                        </select>
                      </div>
                      <div class="form-group">
                        <label for="itemProperty" class="form-label font-semibold">Imóvel</label>
                        <select id="itemProperty" class="form-select rounded-lg" required>
                          <option value="">Selecione um imóvel...</option>
                        </select>
                      </div>
                    </div>
                    <div class="form-group mb-4">
                      <label for="itemValue" class="form-label font-semibold">Valor do Imóvel</label>
                      <input type="text" id="itemValue" class="form-control rounded-lg" placeholder="R$ 0,00" readonly>
                    </div>
                    <div class="form-group mb-4">
                      <label for="itemNotes" class="form-label font-semibold">Notas</label>
                      <textarea id="itemNotes" class="form-control rounded-lg" rows="3" placeholder="Adicione observações..."></textarea>
                    </div>
                  </div>
                  <div class="modal-footer border-t border-gray-200 bg-gray-50 p-4">
                    <button type="button" class="btn btn-secondary rounded-lg" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn text-white rounded-lg" style="background-color: var(--cor-primaria);">Adicionar</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Cobrança Tab (Admin - aparece quando o billing precisa regularização ou aviso de vencimento) -->
      <?php if ($isAdmin && $showBillingMenuItem): ?>
        <div class="tab-content p-6 hidden" id="cobranca">
          <?php
          require_once 'app/components/billing_dashboard.php';
          echo renderBillingDashboard($pdo, TenantContext::getTenantId());
          ?>
        </div>
      <?php endif; ?>

      <!-- Financeiro Tab (Admin) -->
      <?php if ($isAdmin): ?>
        <div class="tab-content p-6 hidden" id="financeiro">
          <div class="mb-8">
            <div class="flex justify-between items-center mb-6">
              <h2 class="text-2xl font-bold text-gray-800">Gestor Financeiro</h2>
              <button onclick="abrirModalFinanceiro()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition" style="background-color: var(--cor-primaria);">
                <i class="fas fa-plus mr-2"></i>Nova Transação
              </button>
            </div>

            <!-- Resumo Financeiro -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-gray-500 mb-1">Total de Receitas</p>
                    <h3 class="text-3xl font-bold text-green-600" id="total-receitas">R$ 0,00</h3>
                  </div>
                  <div class="rounded-full p-3 bg-green-100">
                    <i class="fas fa-arrow-up text-green-600 text-xl"></i>
                  </div>
                </div>
              </div>

              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-gray-500 mb-1">Total de Despesas</p>
                    <h3 class="text-3xl font-bold text-red-600" id="total-despesas">R$ 0,00</h3>
                  </div>
                  <div class="rounded-full p-3 bg-red-100">
                    <i class="fas fa-arrow-down text-red-600 text-xl"></i>
                  </div>
                </div>
              </div>

              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-gray-500 mb-1">Total de Comissões</p>
                    <h3 class="text-3xl font-bold text-orange-600" id="total-comissoes">R$ 0,00</h3>
                  </div>
                  <div class="rounded-full p-3 bg-orange-100">
                    <i class="fas fa-percent text-orange-600 text-xl"></i>
                  </div>
                </div>
              </div>

              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex justify-between items-start">
                  <div>
                    <p class="text-gray-500 mb-1">Lucro Líquido</p>
                    <h3 class="text-3xl font-bold text-blue-600" id="lucro-liquido">R$ 0,00</h3>
                  </div>
                  <div class="rounded-full p-3 bg-blue-100">
                    <i class="fas fa-chart-line text-blue-600 text-xl"></i>
                  </div>
                </div>
              </div>
            </div>

            <!-- Filtros e Transações -->
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="mb-4 flex gap-4 flex-wrap">
                <select id="financeiro-mes" class="px-4 py-2 border border-gray-300 rounded-lg">
                  <option value="<?php echo date('Y-m'); ?>" selected><?php echo date('m/Y'); ?> (Este mês)</option>
                  <option value="2025-12">Dezembro 2025</option>
                  <option value="2025-11">Novembro 2025</option>
                  <option value="2025-10">Outubro 2025</option>
                  <option value="2025-09">Setembro 2025</option>
                  <option value="2025-08">Agosto 2025</option>
                  <option value="2025-07">Julho 2025</option>
                  <option value="2025-06">Junho 2025</option>
                  <option value="2025-05">Maio 2025</option>
                  <option value="2025-04">Abril 2025</option>
                  <option value="2025-03">Março 2025</option>
                  <option value="2025-02">Fevereiro 2025</option>
                  <option value="2025-01">Janeiro 2025</option>
                </select>
                <select id="financeiro-tipo" class="px-4 py-2 border border-gray-300 rounded-lg">
                  <option value="todos">Todos os tipos</option>
                  <option value="receita">Receitas</option>
                  <option value="despesa">Despesas</option>
                  <option value="comissao">Comissões</option>
                </select>
                <select id="financeiro-categoria" class="px-4 py-2 border border-gray-300 rounded-lg">
                  <option value="todos">Todas as categorias</option>
                  <option value="Venda">Venda</option>
                  <option value="Aluguel">Aluguel</option>
                  <option value="Comissão">Comissão</option>
                  <option value="Serviço">Serviço</option>
                </select>
                <select id="financeiro-status" class="px-4 py-2 border border-gray-300 rounded-lg">
                  <option value="todos">Todos os status</option>
                  <option value="pendente">Pendente</option>
                  <option value="concluído">Concluído</option>
                  <option value="cancelado">Cancelado</option>
                </select>
              </div>

              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead>
                    <tr>
                      <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                      <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                      <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                      <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                      <th class="px-6 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                      <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                      <th class="px-6 py-3 bg-gray-50 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200" id="financeiro-container">
                    <tr>
                      <td colspan="7" class="px-6 py-4 text-center text-gray-500">Carregando transações...</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Comissões Tab (Admin) -->
      <?php if ($isAdmin): ?>
        <div class="tab-content p-6 hidden" id="comissoes">
          <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">💰 Gestão de Comissões</h2>
            <p class="text-gray-600">Administre comissões de vendedores e processos de pagamento</p>
          </div>

          <!-- Resumo em Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Card Pendentes -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-yellow-800 uppercase tracking-wide">Pendentes</p>
                  <p class="text-3xl font-bold text-yellow-600 mt-2"><span id="comissoes-pendentes-count">0</span></p>
                  <p class="text-sm text-yellow-600 mt-1" id="comissoes-pendentes-valor">R$ 0,00</p>
                </div>
                <i class="fas fa-clock text-yellow-400 text-4xl opacity-20"></i>
              </div>
            </div>

            <!-- Card Aprovadas -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-blue-800 uppercase tracking-wide">Aprovadas</p>
                  <p class="text-3xl font-bold text-blue-600 mt-2"><span id="comissoes-aprovadas-count">0</span></p>
                  <p class="text-sm text-blue-600 mt-1" id="comissoes-aprovadas-valor">R$ 0,00</p>
                </div>
                <i class="fas fa-check-circle text-blue-400 text-4xl opacity-20"></i>
              </div>
            </div>

            <!-- Card Pagas -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-green-800 uppercase tracking-wide">Pagas</p>
                  <p class="text-3xl font-bold text-green-600 mt-2"><span id="comissoes-pagas-count">0</span></p>
                  <p class="text-sm text-green-600 mt-1" id="comissoes-pagas-valor">R$ 0,00</p>
                </div>
                <i class="fas fa-money-bill-wave text-green-400 text-4xl opacity-20"></i>
              </div>
            </div>
          </div>

          <!-- Filtros -->
          <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="comissoes-status-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="todos">Todos</option>
                  <option value="pendente">⏳ Pendente</option>
                  <option value="aprovada">✅ Aprovada</option>
                  <option value="paga">💰 Paga</option>
                  <option value="cancelada">❌ Cancelada</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                <select id="comissoes-periodo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="mes">Este Mês</option>
                  <option value="30dias">Últimos 30 dias</option>
                  <option value="90dias">Últimos 90 dias</option>
                  <option value="ano">Este Ano</option>
                  <option value="todos">Todos os Períodos</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Corretor</label>
                <select id="comissoes-vendedor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                  <option value="">Todos os Corretores</option>
                </select>
              </div>
              <div class="flex items-end gap-2">
                <button id="btn-aprovar-comissoes" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white font-medium py-2 px-4 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                  <i class="fas fa-check mr-2"></i>Aprovar (0)
                </button>
                <button id="btn-pagar-comissoes" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                  <i class="fas fa-money-bill mr-2"></i>Pagar (0)
                </button>
              </div>
              <div>
                <button id="btn-exportar-comissoes" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-4 rounded-lg transition">
                  <i class="fas fa-download mr-2"></i>Exportar
                </button>
              </div>
            </div>
          </div>

          <!-- Tabela de Comissões -->
          <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50 border-b">
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">
                      <input type="checkbox" id="comissoes-select-all" class="rounded border-gray-300">
                    </th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Corretor</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Imóvel</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">Valor Venda</th>
                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">%</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase">Comissão</th>
                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Data</th>
                    <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" id="comissoes-tbody">
                  <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                      <i class="fas fa-spinner fa-spin mr-2"></i>Carregando comissões...
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Paginação -->
          <div id="comissoes-paginacao" class="mt-6 flex justify-between items-center">
            <!-- Carregado dinamicamente -->
          </div>

          <!-- Modal Detalhes Comissão -->
          <div id="modal-detalhes-comissao" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4">
              <!-- Header -->
              <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex justify-between items-center rounded-t-lg">
                <h3 class="text-xl font-bold text-white">Detalhes da Comissão</h3>
                <button onclick="fecharModalDetalhesComissao()" class="text-white hover:bg-blue-800 p-2 rounded">
                  <i class="fas fa-times text-xl"></i>
                </button>
              </div>

              <!-- Body -->
              <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <!-- Vendedor -->
                  <div>
                    <label class="text-sm font-semibold text-gray-700">Corretor</label>
                    <p class="text-lg text-gray-900 font-medium" id="detalhe-vendedor">-</p>
                  </div>

                  <!-- Status -->
                  <div>
                    <label class="text-sm font-semibold text-gray-700">Status</label>
                    <p id="detalhe-status" class="text-lg font-semibold">-</p>
                  </div>

                  <!-- Imóvel -->
                  <div class="col-span-2">
                    <label class="text-sm font-semibold text-gray-700">Imóvel</label>
                    <p class="text-lg text-gray-900" id="detalhe-imovel">-</p>
                  </div>

                  <!-- Valor Venda -->
                  <div>
                    <label class="text-sm font-semibold text-gray-700">Valor da Venda</label>
                    <p class="text-lg text-gray-900 font-medium" id="detalhe-valor-venda">-</p>
                  </div>

                  <!-- Percentual -->
                  <div>
                    <label class="text-sm font-semibold text-gray-700">Percentual</label>
                    <p class="text-lg text-gray-900 font-medium" id="detalhe-percentual">-</p>
                  </div>

                  <!-- Comissão -->
                  <div>
                    <label class="text-sm font-semibold text-gray-700">Valor da Comissão</label>
                    <p class="text-lg text-blue-600 font-bold" id="detalhe-comissao">-</p>
                  </div>

                  <!-- Data -->
                  <div>
                    <label class="text-sm font-semibold text-gray-700">Data</label>
                    <p class="text-lg text-gray-900" id="detalhe-data">-</p>
                  </div>
                </div>

                <!-- Observações (se houver) -->
                <div id="detalhe-observacoes-container" class="mt-4 pt-4 border-t border-gray-200">
                  <label class="text-sm font-semibold text-gray-700">Observações</label>
                  <p class="text-sm text-gray-600 mt-2" id="detalhe-observacoes">Nenhuma observação</p>
                </div>
              </div>

              <!-- Footer -->
              <div class="bg-gray-50 px-6 py-4 flex justify-end gap-2 rounded-b-lg">
                <button onclick="fecharModalDetalhesComissao()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">
                  Fechar
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Alertas Tab (Admin) -->
      <?php if ($isAdmin): ?>
        <div class="tab-content p-6 hidden" id="alertas">
          <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">🔔 Alertas Inteligentes</h2>
            <p class="text-gray-600">Monitoramento automático de leads e imóveis inativos</p>
          </div>

          <!-- Tabs para Sub-abas de Alertas -->
          <div class="mb-6 flex gap-4 border-b border-gray-200">
            <button class="alertas-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-blue-600 active" data-subtab="alertas-listar">
              <i class="fas fa-list mr-2"></i>Meus Alertas
            </button>
            <button class="alertas-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="alertas-configurar">
              <i class="fas fa-cog mr-2"></i>Configurações
            </button>
          </div>

          <!-- Sub-aba: Listar Alertas -->
          <div id="alertas-listar-content" class="alertas-subtab-content">

            <!-- Resumo em Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
              <!-- Card Total -->
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-semibold text-blue-800 uppercase tracking-wide">Total</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2"><span id="alertas-total">0</span></p>
                  </div>
                  <i class="fas fa-bell text-blue-400 text-4xl opacity-20"></i>
                </div>
              </div>

              <!-- Card Pendentes -->
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-semibold text-yellow-800 uppercase tracking-wide">Pendentes</p>
                    <p class="text-3xl font-bold text-yellow-600 mt-2"><span id="alertas-pendentes">0</span></p>
                  </div>
                  <i class="fas fa-hourglass-end text-yellow-400 text-4xl opacity-20"></i>
                </div>
              </div>

              <!-- Card Reconhecidos -->
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-semibold text-blue-800 uppercase tracking-wide">Reconhecidos</p>
                    <p class="text-3xl font-bold text-blue-600 mt-2"><span id="alertas-reconhecidos">0</span></p>
                  </div>
                  <i class="fas fa-check-circle text-blue-400 text-4xl opacity-20"></i>
                </div>
              </div>

              <!-- Card Críticos -->
              <div class="bg-red-50 border border-red-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-semibold text-red-800 uppercase tracking-wide">Críticos</p>
                    <p class="text-3xl font-bold text-red-600 mt-2"><span id="alertas-criticos">0</span></p>
                  </div>
                  <i class="fas fa-exclamation-circle text-red-400 text-4xl opacity-20"></i>
                </div>
              </div>

              <!-- Card Resolvidos -->
              <div class="bg-green-50 border border-green-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-sm font-semibold text-green-800 uppercase tracking-wide">Resolvidos</p>
                    <p class="text-3xl font-bold text-green-600 mt-2"><span id="alertas-resolvidos">0</span></p>
                  </div>
                  <i class="fas fa-check-circle text-green-400 text-4xl opacity-20"></i>
                </div>
              </div>
            </div>

            <!-- Botão Limpar Resolvidos -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
              <div class="col-start-1 md:col-start-4">
                <button id="btn-limpar-resolvidos" class="w-full bg-green-500 hover:bg-green-600 text-white font-medium py-6 px-4 rounded-lg transition shadow-sm hover:shadow-md" title="Remover todos os alertas resolvidos">
                  <i class="fas fa-trash text-2xl mb-2 block"></i><span>Limpar Resolvidos</span>
                </button>
              </div>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h3>
              <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                  <select id="alertas-status-filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="todos">Todos</option>
                    <option value="novo">⏳ Pendentes</option>
                    <option value="reconhecido">✅ Reconhecidos</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Severidade</label>
                  <select id="alertas-severity-filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="todos">Todas</option>
                    <option value="Baixa">🟢 Baixa</option>
                    <option value="Média">🟡 Média</option>
                    <option value="Alta">🟠 Alta</option>
                    <option value="Crítica">⚠️ Crítica</option>
                  </select>
                </div>
                <div class="flex items-end">
                  <button id="btn-limpar-filtros" class="w-full bg-gray-500 hover:bg-gray-600 text-white font-medium py-2 px-2 rounded-lg transition text-sm truncate">
                    <i class="fas fa-times mr-1"></i>Limpar
                  </button>
                </div>
                <div class="flex items-end">
                  <button id="btn-recarregar-alertas" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-medium py-2 px-2 rounded-lg transition text-sm truncate">
                    <i class="fas fa-sync mr-1"></i>Recarregar
                  </button>
                </div>
              </div>
            </div>

            <!-- Tabela de Alertas -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr class="bg-gray-50 border-b">
                      <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Severidade</th>
                      <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Alerta</th>
                      <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase">Relacionado</th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Data</th>
                      <th class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase">Ações</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200" id="alertas-tbody">
                    <tr>
                      <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Carregando alertas...
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Paginação -->
            <div id="alertas-paginacao" class="mt-6 flex justify-between items-center">
              <!-- Carregado dinamicamente -->
            </div>
          </div>
          <!-- Fim Sub-aba: Listar Alertas -->

          <!-- Sub-aba: Configurações de Alertas -->
          <div id="alertas-configurar-content" class="alertas-subtab-content hidden">
            <div class="bg-white rounded-lg shadow-sm p-8">
              <h3 class="text-2xl font-bold text-gray-800 mb-6">⚙️ Configurar Preferências de Alertas</h3>
              <p class="text-gray-600 mb-8">Personalize seus alertas definindo os limites para cada tipo de notificação.</p>

              <div id="alertas-config-container" class="space-y-6">
                <!-- Carregado dinamicamente via JavaScript -->
              </div>

              <div class="mt-8">
                <button id="btn-salvar-config-alertas" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition">
                  <i class="fas fa-save mr-2"></i>Salvar Configurações
                </button>
              </div>
            </div>
          </div>
          <!-- Fim Sub-aba: Configurações de Alertas -->

        </div>

        <!-- TAB: INTEGRAÇÕES (Admin Only) -->
        <div class="tab-content p-6 hidden" id="integrations">
          <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">🔌 Integrações</h2>
            <p class="text-gray-600">Centralize a ativação de portais imobiliários; clique em "Ativar" no portal desejado e depois preencha os dados da conta.</p>
          </div>

          <div class="integrations-subtabs mb-6 border-b border-slate-200">
            <button class="integrations-subtab-link active px-4 py-3 text-sm font-semibold text-slate-900 border-b-2 border-blue-600" data-subtab="todas">Todas</button>
            <button class="integrations-subtab-link px-4 py-3 text-sm font-semibold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-subtab="ativas">Ativas</button>
            <button class="integrations-subtab-link px-4 py-3 text-sm font-semibold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-subtab="analise-trafego">Análise de Tráfego</button>
            <button class="integrations-subtab-link px-4 py-3 text-sm font-semibold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-subtab="atendimento">Atendimento</button>
            <button class="integrations-subtab-link px-4 py-3 text-sm font-semibold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-subtab="automacao-marketing">Automação de Marketing</button>
            <button class="integrations-subtab-link px-4 py-3 text-sm font-semibold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-subtab="central-conexoes">Central de Conexões</button>
            <button class="integrations-subtab-link px-4 py-3 text-sm font-semibold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-subtab="marketing-publicidade">Marketing e Publicidade</button>
            <button class="integrations-subtab-link px-4 py-3 text-sm font-semibold text-slate-600 border-b-2 border-transparent hover:text-slate-900" data-subtab="portais-imobiliarios">Portais Imobiliários</button>
          </div>

          <div id="integration-cards" class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 items-stretch">
            <?php if (!empty($integrationConfig) && is_array($integrationConfig)): ?>
              <?php foreach ($integrationConfig as $portalSlug => $portal): ?>
                <?php if (in_array($portalSlug, ['base_url', 'defaults'], true) || !is_array($portal) || empty($portal['name'])) continue; ?>
                <?php
                $name = htmlspecialchars($portal['name'] ?? $portalSlug, ENT_QUOTES, 'UTF-8');
                $description = htmlspecialchars($portal['description'] ?? 'Configure a conta do portal, endpoint e credenciais.', ENT_QUOTES, 'UTF-8');
                $icon = htmlspecialchars($portal['icon'] ?? 'fas fa-plug', ENT_QUOTES, 'UTF-8');
                $iconColor = htmlspecialchars($portal['iconColor'] ?? 'text-slate-500', ENT_QUOTES, 'UTF-8');
                ?>
                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 flex flex-col justify-between w-full h-full">
                  <div>
                    <div class="flex items-center justify-between mb-4">
                      <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">Integração</p>
                        <h3 class="text-2xl font-semibold text-slate-900 mt-2"><?php echo $name; ?></h3>
                      </div>
                      <div class="rounded-full px-3 py-2 text-xs font-semibold bg-gray-100 text-gray-700">Não configurado</div>
                    </div>
                    <p class="text-sm text-slate-600 mb-5 min-h-[70px]"><?php echo $description; ?></p>
                    <div class="flex items-center gap-3 text-slate-500 mb-4">
                      <i class="<?php echo $icon; ?> text-xl <?php echo $iconColor; ?>"></i>
                      <span class="text-sm"><?php echo htmlspecialchars(strtoupper($portalSlug), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                  </div>
                  <div class="mt-4">
                    <button type="button" data-portal="<?php echo htmlspecialchars($portalSlug, ENT_QUOTES, 'UTF-8'); ?>" class="integration-action-btn w-full inline-flex items-center justify-center rounded-xl bg-blue-600 text-white py-3 px-4 text-sm font-semibold hover:bg-blue-700 transition">Ativar</button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="col-span-full bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center text-gray-600">
                <p class="text-lg font-semibold">Nenhuma integração disponível</p>
                <p class="text-sm mt-2">Verifique as configurações do portal ou ative as integrações via JavaScript.</p>
              </div>
            <?php endif; ?>
          </div>

          <div class="hidden text-center text-gray-500" id="integration-empty">
            <p class="text-lg font-semibold">Nenhuma integração encontrada.</p>
            <p class="text-sm">Esta área será usada para configurar e ativar portais como ZAP e OLX.</p>
          </div>
        </div>

        <!-- Modal de configuração de integração -->
        <div class="modal fade" id="portalIntegrationModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-3xl border border-slate-200 overflow-hidden shadow-xl">
              <div class="modal-header bg-slate-50 px-6 py-5">
                <div>
                  <h5 class="modal-title text-xl font-semibold" id="integrationModalTitle">Configurar Integração</h5>
                  <p class="text-sm text-slate-500 mt-1" id="integrationModalDescription">Configure as credenciais do portal e ative a conexão.</p>
                </div>
                <button type="button" class="btn-close" aria-label="Fechar" data-close-integration-modal="true"></button>
              </div>
              <div class="modal-body px-6 py-5">
                <div id="integrationModalError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 mb-4" style="display:none"></div>
                <form id="portalIntegrationForm">
                  <input type="hidden" id="integrationPortalSlug" name="portal" value="">
                  <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label class="block text-sm font-medium text-slate-700 mb-2">Portal</label>
                      <div class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <i id="integrationPortalIcon" class="fas fa-plug text-2xl text-slate-500"></i>
                        <p id="integrationPortalName" class="text-sm text-slate-800"></p>
                      </div>
                    </div>
                    <div>
                      <label class="flex items-center gap-2 text-sm font-medium text-slate-700 mb-2" for="integrationEnabledCheckbox">
                        <input id="integrationEnabledCheckbox" type="checkbox" class="h-4 w-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                        Ativar integração
                      </label>
                    </div>
                  </div>
                  <div id="integrationEndpointContainer" class="mt-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2" for="integrationEndpointInput">Endpoint de push</label>
                    <input id="integrationEndpointInput" type="url" class="block w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="https://api.portal.com.br/feed">
                    <p class="text-xs text-slate-500 mt-2">Informe o endpoint de envio do feed ou da API do portal.</p>
                  </div>
                  <div id="integrationCredentialFields" class="mt-4"></div>
                  <div id="integrationLinkResults" class="mt-6 hidden rounded-2xl border border-blue-200 bg-blue-50 p-4">
                    <div class="mb-3">
                      <p class="text-sm font-semibold text-blue-800" id="integrationLinkTitle">Links gerados para OLX</p>
                      <p class="text-xs text-slate-600" id="integrationLinkSubtitle">Copie estes links para colar no painel do OLX.</p>
                    </div>
                    <div class="space-y-4" id="integrationFeedLinkContainer">
                      <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Feed de imóveis</label>
                        <div class="flex items-center gap-2">
                          <input id="integrationFeedLink" type="text" readonly class="w-full rounded-xl border border-slate-200 bg-white py-2 px-3 text-xs text-slate-800" />
                          <button type="button" id="integrationCopyFeedLink" class="inline-flex items-center justify-center rounded-xl bg-blue-600 text-white px-3 py-2 text-xs font-semibold hover:bg-blue-700">Copiar</button>
                        </div>
                      </div>
                    </div>
                    <div class="space-y-4" id="integrationLeadLinkContainer">
                      <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Endpoint de leads</label>
                        <div class="flex items-center gap-2">
                          <input id="integrationLeadLink" type="text" readonly class="w-full rounded-xl border border-slate-200 bg-white py-2 px-3 text-xs text-slate-800" />
                          <button type="button" id="integrationCopyLeadLink" class="inline-flex items-center justify-center rounded-xl bg-blue-600 text-white px-3 py-2 text-xs font-semibold hover:bg-blue-700">Copiar</button>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">Use este endpoint para receber leads via POST JSON. Inclua tenant_id, name, email, phone, message e property_id.</p>
                      </div>
                    </div>
                    <div class="space-y-4 hidden" id="integrationWebhookLinkContainer">
                      <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Webhook do Facebook Leads</label>
                        <div class="flex items-center gap-2">
                          <input id="integrationWebhookLink" type="text" readonly class="w-full rounded-xl border border-slate-200 bg-white py-2 px-3 text-xs text-slate-800" />
                          <button type="button" id="integrationCopyWebhookLink" class="inline-flex items-center justify-center rounded-xl bg-blue-600 text-white px-3 py-2 text-xs font-semibold hover:bg-blue-700">Copiar</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </form>
              </div>
              <div class="modal-footer flex items-center justify-between gap-3 px-6 py-4 bg-slate-50">
                <button type="button" class="btn btn-secondary px-5 py-3 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-100" data-close-integration-modal="true">Cancelar</button>
                <button type="button" id="integrationSaveButton" class="btn btn-primary inline-flex items-center justify-center rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700">Salvar configurações</button>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-content p-6 hidden" id="webhooks">
          <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">🔗 Webhooks</h2>
            <p class="text-gray-600">Integre sua aplicação com eventos em tempo real do sistema</p>
          </div>

          <!-- Resumo em Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Card Total -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-blue-800 uppercase tracking-wide">Total</p>
                  <p class="text-3xl font-bold text-blue-600 mt-2"><span id="webhooks-total">0</span></p>
                </div>
                <i class="fas fa-plug text-blue-400 text-4xl opacity-20"></i>
              </div>
            </div>

            <!-- Card Ativos -->
            <div class="bg-green-50 border border-green-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-green-800 uppercase tracking-wide">Ativos</p>
                  <p class="text-3xl font-bold text-green-600 mt-2"><span id="webhooks-ativos">0</span></p>
                </div>
                <i class="fas fa-check-circle text-green-400 text-4xl opacity-20"></i>
              </div>
            </div>

            <!-- Card Inativos -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 shadow-sm hover:shadow-md transition">
              <div class="flex items-center justify-between">
                <div>
                  <p class="text-sm font-semibold text-gray-800 uppercase tracking-wide">Inativos</p>
                  <p class="text-3xl font-bold text-gray-600 mt-2"><span id="webhooks-inativos">0</span></p>
                </div>
                <i class="fas fa-times-circle text-gray-400 text-4xl opacity-20"></i>
              </div>
            </div>
          </div>

          <!-- Container principal -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Coluna esquerda: Criar novo webhook -->
            <div class="lg:col-span-1">
              <div class="bg-white rounded-lg shadow-sm p-6 sticky top-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">➕ Novo Webhook</h3>

                <form id="webhook-form" class="space-y-4">
                  <div>
                    <label for="event-type" class="block text-sm font-medium text-gray-700 mb-2">Tipo de Evento</label>
                    <select id="event-type" name="event_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                      <option value="">Selecione um evento</option>
                      <option value="lead.created">👤 Lead Criado</option>
                      <option value="lead.updated">✏️ Lead Atualizado</option>
                      <option value="lead.converted">🎯 Lead Convertido</option>
                      <option value="property.created">🏠 Propriedade Criada</option>
                      <option value="property.sold">💰 Propriedade Vendida</option>
                      <option value="commission.created">💵 Comissão Criada</option>
                      <option value="commission.paid">✅ Comissão Paga</option>
                      <option value="alert.triggered">🔔 Alerta Disparado</option>
                    </select>
                  </div>

                  <div>
                    <label for="webhook-url" class="block text-sm font-medium text-gray-700 mb-2">URL do Webhook</label>
                    <input
                      type="url"
                      id="webhook-url"
                      name="url"
                      placeholder="https://sua-api.com/webhook"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                      required />
                    <small class="text-gray-500">Deve estar acessível na internet</small>
                  </div>

                  <div>
                    <label for="webhook-secret" class="block text-sm font-medium text-gray-700 mb-2">Secret (Opcional)</label>
                    <input
                      type="text"
                      id="webhook-secret"
                      name="secret"
                      placeholder="chave-secreta"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    <small class="text-gray-500">Para validar assinatura HMAC</small>
                  </div>

                  <button
                    type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition">
                    <i class="fas fa-plus mr-2"></i>Criar Webhook
                  </button>
                </form>
              </div>
            </div>

            <!-- Coluna direita: Lista de webhooks -->
            <div class="lg:col-span-2">
              <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-6">📋 Minhas Inscrições</h3>

                <!-- Explicação sobre Webhooks -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                  <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-600 mt-1 flex-shrink-0"></i>
                    <div class="text-sm text-gray-700">
                      <p class="font-semibold text-blue-900 mb-2">O que são Webhooks?</p>
                      <p class="mb-3">Webhooks são notificações automáticas que seu sistema envia para uma URL externa quando algo acontece (como um novo lead recebido, propriedade atualizada, etc). Pense como um "aviso em tempo real" para sistemas externos.</p>

                      <p class="font-semibold text-blue-900 mb-2">Como usar:</p>
                      <ol class="list-decimal list-inside space-y-1 ml-2">
                        <li><strong>Crie uma inscrição:</strong> Escolha o tipo de evento que quer monitorar (ex: "novo_lead")</li>
                        <li><strong>Configure a URL:</strong> Forneça a URL externa que receberá as notificações</li>
                        <li><strong>Receba os dados:</strong> Quando o evento ocorrer, seus dados serão enviados automaticamente</li>
                        <li><strong>Processe:</strong> Use os dados para atualizar seus sistemas integrados</li>
                      </ol>

                      <p class="text-xs text-gray-600 mt-3">💡 <strong>Dica:</strong> Você pode testar sua URL usando ferramentas como Webhook.site ou RequestBin</p>
                    </div>
                  </div>
                </div>

                <div id="webhooks-list" class="space-y-4">
                  <div class="text-center py-12 text-gray-500">
                    <i class="fas fa-spinner-third fa-spin text-3xl mb-4"></i>
                    <p>Carregando webhooks...</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Relatórios Tab (Admin) -->
      <?php if ($isAdmin): ?>
        <div class="tab-content p-6 hidden" id="relatorios">
          <div class="mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Relatórios</h2>
            <p class="text-gray-600">Análises e métricas essenciais para gestão da imobiliária</p>
          </div>

          <!-- Tabs para Sub-abas de Relatórios -->
          <div class="mb-6 flex gap-4 border-b border-gray-200">
            <button class="relatorios-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-blue-600 active" data-subtab="relatorios-listar">
              <i class="fas fa-chart-bar mr-2"></i>Relatórios
            </button>
            <button class="relatorios-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="utm-analytics">
              <i class="fas fa-link mr-2"></i>UTM Analytics
            </button>
            <button class="relatorios-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="pipeline-view">
              <i class="fas fa-funnel mr-2"></i>Pipeline
            </button>
          </div>

          <!-- Sub-aba: Relatórios -->
          <div id="relatorios-listar-content" class="relatorios-subtab-content">

            <!-- Filtros Globais -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-8">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Filtros</h3>
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                  <select id="relatorio-periodo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="mes">Este Mês</option>
                    <option value="trimestre">Este Trimestre</option>
                    <option value="ano">Este Ano</option>
                    <option value="custom">Período Customizado</option>
                  </select>
                </div>
                <div id="relatorio-data-inicio-div" style="display: none;">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Data Início</label>
                  <input type="date" id="relatorio-data-inicio" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div id="relatorio-data-fim-div" style="display: none;">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Data Fim</label>
                  <input type="date" id="relatorio-data-fim" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Corretor</label>
                  <select id="relatorio-corretor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos os Corretores</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                  <select id="relatorio-status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos os Status</option>
                    <option value="active">Disponível</option>
                    <option value="vendido">Vendido</option>
                    <option value="alugado">Alugado</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Imóvel</label>
                  <select id="relatorio-tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos os Tipos</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- 🏠 Relatórios de Imóveis -->
            <div class="mb-8">
              <div class="flex items-center mb-6">
                <i class="fas fa-home text-2xl mr-3" style="color: var(--cor-primaria);"></i>
                <h3 class="text-2xl font-bold text-gray-800">Relatórios de Imóveis</h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Imóveis por Status -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('imoveis-status')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Imóveis por Status</h4>
                      <p class="text-sm text-gray-600 mt-1">Distribuição por status (disponível, reservado, vendido, alugado)</p>
                    </div>
                    <i class="fas fa-chart-pie text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="imoveis-status-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="imoveis-status" class="mt-4 w-full px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Imóveis por Tipo -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('imoveis-tipo')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Imóveis por Tipo</h4>
                      <p class="text-sm text-gray-600 mt-1">Distribuição entre casas, apartamentos, terrenos e comerciais</p>
                    </div>
                    <i class="fas fa-building text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="imoveis-tipo-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="imoveis-tipo" class="mt-4 w-full px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Imóveis por Bairro -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('imoveis-bairro')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Imóveis por Bairro</h4>
                      <p class="text-sm text-gray-600 mt-1">Concentração de imóveis por região/bairro</p>
                    </div>
                    <i class="fas fa-map-marker-alt text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="imoveis-bairro-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="imoveis-bairro" class="mt-4 w-full px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Imóveis por Faixa de Preço -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('imoveis-preco')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Imóveis por Faixa de Preço</h4>
                      <p class="text-sm text-gray-600 mt-1">Distribuição de imóveis por faixas de preço</p>
                    </div>
                    <i class="fas fa-dollar-sign text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="imoveis-preco-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="imoveis-preco" class="mt-4 w-full px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Imóveis Parados -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('imoveis-parados')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Imóveis Parados</h4>
                      <p class="text-sm text-gray-600 mt-1">Imóveis sem interesse há X dias</p>
                    </div>
                    <i class="fas fa-exclamation-triangle text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="imoveis-parados-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="imoveis-parados" class="mt-4 w-full px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Imóveis Vendidos/Alugados por Período -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('imoveis-vendidos')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Vendidos/Alugados</h4>
                      <p class="text-sm text-gray-600 mt-1">Imóveis vendidos/alugados por período</p>
                    </div>
                    <i class="fas fa-check-circle text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="imoveis-vendidos-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="imoveis-vendidos" class="mt-4 w-full px-4 py-2 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>
              </div>
            </div>

            <!-- 👥 Relatórios de Leads e Clientes -->
            <div class="mb-8">
              <div class="flex items-center mb-6">
                <i class="fas fa-user-friends text-2xl mr-3" style="color: var(--cor-secundaria);"></i>
                <h3 class="text-2xl font-bold text-gray-800">Relatórios de Leads e Clientes</h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Leads por Origem -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('leads-origem')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Leads por Origem</h4>
                      <p class="text-sm text-gray-600 mt-1">Site, portal, WhatsApp, redes sociais</p>
                    </div>
                    <i class="fas fa-share-alt text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="leads-origem-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="leads-origem" class="mt-4 w-full px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Leads por Status -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('leads-status')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Leads por Status</h4>
                      <p class="text-sm text-gray-600 mt-1">Novo, em atendimento, qualificado, perdido</p>
                    </div>
                    <i class="fas fa-list-check text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="leads-status-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="leads-status" class="mt-4 w-full px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Taxa de Conversão -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('leads-conversao')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Taxa de Conversão</h4>
                      <p class="text-sm text-gray-600 mt-1">Percentual de conversão de leads</p>
                    </div>
                    <i class="fas fa-percentage text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="leads-conversao-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="leads-conversao" class="mt-4 w-full px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Leads Perdidos -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('leads-perdidos')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Leads Perdidos</h4>
                      <p class="text-sm text-gray-600 mt-1">Análise de leads perdidos por motivo</p>
                    </div>
                    <i class="fas fa-times-circle text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="leads-perdidos-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="leads-perdidos" class="mt-4 w-full px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Tempo Médio de Atendimento -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('leads-tempo')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Tempo de Atendimento</h4>
                      <p class="text-sm text-gray-600 mt-1">Tempo médio de atendimento ao lead</p>
                    </div>
                    <i class="fas fa-clock text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="leads-tempo-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="leads-tempo" class="mt-4 w-full px-4 py-2 bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>
              </div>
            </div>

            <!-- 🧑‍💼 Relatórios de Corretores -->
            <div class="mb-8">
              <div class="flex items-center mb-6">
                <i class="fas fa-user-tie text-2xl mr-3" style="color: var(--cor-destaque);"></i>
                <h3 class="text-2xl font-bold text-gray-800">Relatórios de Corretores</h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Leads por Corretor -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('corretor-leads')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Leads por Corretor</h4>
                      <p class="text-sm text-gray-600 mt-1">Quantidade de leads atendidos por cada corretor</p>
                    </div>
                    <i class="fas fa-list-ol text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="corretor-leads-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="corretor-leads" class="mt-4 w-full px-4 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Vendas por Corretor -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('corretor-vendas')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Vendas por Corretor</h4>
                      <p class="text-sm text-gray-600 mt-1">Valor e quantidade de vendas realizadas</p>
                    </div>
                    <i class="fas fa-handshake text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="corretor-vendas-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="corretor-vendas" class="mt-4 w-full px-4 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Taxa de Conversão por Corretor -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('corretor-conversao')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Taxa de Conversão</h4>
                      <p class="text-sm text-gray-600 mt-1">Taxa de conversão individual de cada corretor</p>
                    </div>
                    <i class="fas fa-chart-line text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="corretor-conversao-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="corretor-conversao" class="mt-4 w-full px-4 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Ranking de Corretores -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('corretor-ranking')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Ranking de Corretores</h4>
                      <p class="text-sm text-gray-600 mt-1">Classificação por vendas e desempenho</p>
                    </div>
                    <i class="fas fa-trophy text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="corretor-ranking-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="corretor-ranking" class="mt-4 w-full px-4 py-2 bg-yellow-50 text-yellow-600 rounded-lg hover:bg-yellow-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>
              </div>
            </div>

            <!-- 📊 Relatórios de Vendas e Fechamentos -->
            <div class="mb-8">
              <div class="flex items-center mb-6">
                <i class="fas fa-chart-bar text-2xl mr-3" style="color: var(--cor-primaria);"></i>
                <h3 class="text-2xl font-bold text-gray-800">Relatórios de Vendas e Fechamentos</h3>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Vendas por Período -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('vendas-periodo')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Vendas por Período</h4>
                      <p class="text-sm text-gray-600 mt-1">Análise mensal, trimestral e anual</p>
                    </div>
                    <i class="fas fa-calendar-alt text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="vendas-periodo-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="vendas-periodo" class="mt-4 w-full px-4 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Vendas por Tipo de Imóvel -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('vendas-tipo')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Vendas por Tipo</h4>
                      <p class="text-sm text-gray-600 mt-1">Distribuição de vendas por tipo de imóvel</p>
                    </div>
                    <i class="fas fa-boxes text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="vendas-tipo-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="vendas-tipo" class="mt-4 w-full px-4 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Ticket Médio -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('vendas-ticket')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Ticket Médio</h4>
                      <p class="text-sm text-gray-600 mt-1">Valor médio de venda/aluguel</p>
                    </div>
                    <i class="fas fa-cash-register text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="vendas-ticket-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="vendas-ticket" class="mt-4 w-full px-4 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Receita e Comissões -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('vendas-receita')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Receita e Comissões</h4>
                      <p class="text-sm text-gray-600 mt-1">Receita total e comissões geradas</p>
                    </div>
                    <i class="fas fa-credit-card text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="vendas-receita-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="vendas-receita" class="mt-4 w-full px-4 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Ciclo de Venda -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('vendas-ciclo')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Ciclo de Venda</h4>
                      <p class="text-sm text-gray-600 mt-1">Tempo médio do ciclo de venda</p>
                    </div>
                    <i class="fas fa-spinner text-2xl text-gray-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="vendas-ciclo-preview" class="text-sm text-gray-500">Clique para carregar</div>
                  </div>
                  <button data-relatorio-id="vendas-ciclo" class="mt-4 w-full px-4 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar
                  </button>
                </div>

                <!-- Pipeline -->
                <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition cursor-pointer" onclick="carregarRelatorio('vendas-pipeline')">
                  <div class="flex items-start justify-between mb-4">
                    <div>
                      <h4 class="text-lg font-semibold text-gray-800">Pipeline</h4>
                      <p class="text-sm text-gray-600 mt-1">Negociações em andamento</p>
                    </div>
                    <i class="fas fa-funnel text-2xl text-indigo-300"></i>
                  </div>
                  <div class="mt-4 pt-4 border-t border-gray-200">
                    <div id="pipeline-metrics-loading" class="text-sm text-gray-500">Carregando...</div>
                    <div id="pipeline-metrics" style="display: none;">
                      <div class="grid grid-cols-2 gap-4">
                        <div>
                          <p class="text-xs text-gray-500 mb-1">Total em Negociação</p>
                          <p class="text-lg font-bold text-indigo-600" id="pipeline-total-value">R$ 0,00</p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-500 mb-1">Quantidade</p>
                          <p class="text-lg font-bold text-indigo-600" id="pipeline-total-items">0</p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-500 mb-1">Valor Médio</p>
                          <p class="text-lg font-bold text-indigo-600" id="pipeline-avg-value">R$ 0,00</p>
                        </div>
                        <div>
                          <p class="text-xs text-gray-500 mb-1">Estágios Ativos</p>
                          <p class="text-lg font-bold text-indigo-600" id="pipeline-stages-count">0</p>
                        </div>
                      </div>
                    </div>
                  </div>
                  <button data-relatorio-id="vendas-pipeline" class="mt-4 w-full px-4 py-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition text-sm font-medium">
                    <i class="fas fa-external-link-alt mr-2"></i>Visualizar Pipeline Completo
                  </button>
                </div>
              </div>
            </div>
          </div>
          <!-- Fim Sub-aba: Relatórios -->

          <!-- Sub-aba: UTM Analytics -->
          <div id="utm-analytics-content" class="relatorios-subtab-content hidden">
            <div class="mb-8">
              <h2 class="text-3xl font-bold text-gray-800 mb-2 flex items-center gap-3">
                <i class="fas fa-link" style="color: var(--cor-primaria); font-size: 28px;"></i>
                UTM Analytics
              </h2>
              <p class="text-gray-600">Acompanhe o desempenho das suas campanhas UTM de marketing</p>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                  <select id="utm-periodo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="30">Últimos 30 dias</option>
                    <option value="60">Últimos 60 dias</option>
                    <option value="90">Últimos 90 dias</option>
                    <option value="365">Último ano</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Campanhas</label>
                  <select id="utm-campaign-filter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas as campanhas</option>
                  </select>
                </div>
                <div class="flex items-end">
                  <button onclick="carregarAnaliticsUTM()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium">
                    <i class="fas fa-sync-alt mr-2"></i>Atualizar
                  </button>
                </div>
              </div>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-gray-600 text-sm">Total de Cliques</p>
                    <h3 class="text-3xl font-bold text-gray-800" id="utm-total-clicks">-</h3>
                  </div>
                  <i class="fas fa-mouse-pointer text-4xl text-blue-100"></i>
                </div>
              </div>

              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-gray-600 text-sm">Visitantes Únicos</p>
                    <h3 class="text-3xl font-bold text-gray-800" id="utm-unique-visitors">-</h3>
                  </div>
                  <i class="fas fa-users text-4xl text-green-100"></i>
                </div>
              </div>

              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-gray-600 text-sm">Leads Convertidos</p>
                    <h3 class="text-3xl font-bold text-gray-800" id="utm-conversions">-</h3>
                  </div>
                  <i class="fas fa-check-circle text-4xl text-yellow-100"></i>
                </div>
              </div>

              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between">
                  <div>
                    <p class="text-gray-600 text-sm">Taxa de Conversão</p>
                    <h3 class="text-3xl font-bold text-gray-800" id="utm-conversion-rate">-</h3>
                  </div>
                  <i class="fas fa-percentage text-4xl text-purple-100"></i>
                </div>
              </div>
            </div>

            <!-- Gráficos -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
              <!-- Cliques por Fonte -->
              <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Cliques por Fonte</h3>
                <div id="utm-chart-source" class="h-80"></div>
              </div>

              <!-- Cliques por Meio -->
              <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Cliques por Meio</h3>
                <div id="utm-chart-medium" class="h-80"></div>
              </div>

              <!-- Timeline de Cliques -->
              <div class="bg-white rounded-lg shadow-sm p-6 lg:col-span-2">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Timeline de Cliques</h3>
                <div id="utm-chart-timeline" class="h-80"></div>
              </div>
            </div>

            <!-- Tabela de Campanhas -->
            <div class="bg-white rounded-lg shadow-sm p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-4">Desempenho por Campanha</h3>
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead class="bg-gray-50 border-b">
                    <tr>
                      <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Campanha</th>
                      <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Cliques</th>
                      <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Únicos</th>
                      <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Conversões</th>
                      <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Taxa Conv.</th>
                    </tr>
                  </thead>
                  <tbody id="utm-campaigns-table" class="divide-y">
                    <tr class="text-center py-8">
                      <td colspan="5" class="text-gray-500">Carregando...</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Fim Sub-aba: UTM Analytics -->

          <!-- Sub-aba: Pipeline -->
          <div id="pipeline-view-content" class="relatorios-subtab-content hidden">
            <div class="mb-8">
              <div class="flex justify-between items-start mb-8">
                <div>
                  <h1 class="text-4xl font-bold text-gray-800 mb-2 flex items-center gap-3">
                    <i class="fas fa-funnel" style="color: var(--cor-primaria); font-size: 32px;"></i>
                    Pipeline de Vendas
                  </h1>
                  <p class="text-gray-600 text-lg">Gerencie seus leads e imóveis pelo funil de vendas</p>
                </div>
                <button onclick="abrirModalAdicionarAoPipeline()" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition flex items-center gap-2 shadow-lg hover:shadow-xl transform hover:scale-105" style="background: linear-gradient(135deg, var(--cor-primaria) 0%, rgba(var(--cor-primaria-rgb), 0.8) 100%);">
                  <i class="fas fa-plus"></i> Adicionar ao Pipeline
                </button>
              </div>
            </div>

            <div id="loadingMessage-relatorios" class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center">
              <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4 block"></i>
              <p class="text-blue-700 font-medium">Carregando pipeline...</p>
            </div>

            <div class="pipeline-container" id="pipelineContainer-relatorios" style="display: none;">
              <!-- Estágios serão carregados aqui -->
            </div>

            <!-- Modal para exibir detalhes do item -->
            <div class="modal fade" id="itemModal" tabindex="-1">
              <div class="modal-dialog modal-lg">
                <div class="modal-content rounded-xl shadow-2xl">
                  <div class="modal-header border-b border-gray-200">
                    <h5 class="modal-title text-xl font-bold text-gray-800" id="itemModalTitle">Detalhes do Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body p-6" id="itemModalBody">
                    <!-- Conteúdo será carregado aqui -->
                  </div>
                  <div class="modal-footer border-t border-gray-200 bg-gray-50 p-4">
                    <button type="button" class="btn btn-secondary rounded-lg" data-bs-dismiss="modal">Fechar</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Fim Sub-aba: Pipeline -->
        </div>
      <?php endif; ?>

      <!-- Dashboard Tab -->
      <div class="tab-content active p-6" id="dashboard">
        <!-- Container de Avisos de Agendamentos -->
        <div id="agendamentos-aviso-container" class="mb-8"></div>

        <!-- Dashboard Header com Seletores de Período -->
        <div class="mb-8">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Dashboard Executivo</h2>
            <div class="flex gap-2 flex-wrap">
              <button data-period="7" class="px-4 py-2 rounded-lg font-medium transition-all duration-300">
                7 dias
              </button>
              <button data-period="30" class="px-4 py-2 rounded-lg font-medium transition-all duration-300">
                30 dias
              </button>
              <button data-period="90" class="px-4 py-2 rounded-lg font-medium transition-all duration-300">
                90 dias
              </button>
              <button id="dashboard-export-btn" class="px-4 py-2 rounded-lg bg-green-600 text-white font-medium transition-all duration-300 hover:bg-green-700 flex items-center gap-2">
                <i class="fas fa-download"></i> Exportar
              </button>
            </div>
          </div>

          <!-- KPI Cards Principal -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total de Imóveis -->
            <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-300 data-kpi" data-kpi="properties">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-500 mb-1">Total de Imóveis</p>
                  <h3 class="text-3xl font-bold text-gray-800" id="total-properties">0</h3>
                </div>
                <div class="rounded-full p-3" style="background-color: rgba(var(--cor-primaria-rgb), 0.1);">
                  <i class="fas fa-home" style="color: var(--cor-primaria);"></i>
                </div>
              </div>
              <div class="mt-4 text-xs text-gray-500">
                Imóveis cadastrados no sistema
              </div>
            </div>

            <!-- Total de Leads -->
            <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-300 data-kpi" data-kpi="leads">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-500 mb-1">Total de Leads</p>
                  <h3 class="text-3xl font-bold text-gray-800" id="total-leads-dashboard">0</h3>
                </div>
                <div class="rounded-full p-3" style="background-color: rgba(var(--cor-secundaria-rgb), 0.1);">
                  <i class="fas fa-user-friends" style="color: var(--cor-secundaria);"></i>
                </div>
              </div>
              <div class="mt-4 text-xs text-gray-500">
                Contatos no sistema
              </div>
            </div>

            <!-- Total de Visitas -->
            <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-300 data-kpi" data-kpi="visits">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-500 mb-1">Visitas</p>
                  <h3 class="text-3xl font-bold text-gray-800" id="total-visits-dashboard">0</h3>
                </div>
                <div class="rounded-full p-3" style="background-color: rgba(var(--cor-destaque-rgb), 0.1);">
                  <i class="fas fa-eye" style="color: var(--cor-destaque);"></i>
                </div>
              </div>
              <div class="mt-4 text-xs text-gray-500">
                Visitantes únicos no período
              </div>
            </div>

            <!-- Total de Vendas/Aluguéis -->
            <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-300 data-kpi" data-kpi="sales">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-500 mb-1">Vendas/Aluguéis</p>
                  <h3 class="text-3xl font-bold text-gray-800" id="total-sales-dashboard">0</h3>
                </div>
                <div class="rounded-full p-3" style="background-color: rgba(var(--cor-primaria-rgb), 0.1);">
                  <i class="fas fa-handshake" style="color: var(--cor-primaria);"></i>
                </div>
              </div>
              <div class="mt-4 text-xs text-gray-500">
                Transações concluídas
              </div>
            </div>
          </div>
        </div>

        <!-- KPI Cards Secundários -->
        <div class="mb-8">
          <h3 class="text-lg font-bold text-gray-800 mb-4">Indicadores de Desempenho</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Taxa de Conversão -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-300 data-kpi" data-kpi="conversion">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-600 mb-1 text-sm">Taxa de Conversão</p>
                  <h3 class="text-3xl font-bold text-blue-600" id="conversion-rate-kpi">0%</h3>
                </div>
                <div class="rounded-full p-3 bg-blue-500 bg-opacity-20">
                  <i class="fas fa-chart-pie text-blue-600"></i>
                </div>
              </div>
              <div class="mt-4 text-xs text-gray-600">
                Leads convertidos em vendas
              </div>
            </div>

            <!-- Ticket Médio -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-300 data-kpi" data-kpi="ticket">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-600 mb-1 text-sm">Ticket Médio</p>
                  <h3 class="text-2xl font-bold text-green-600" id="average-ticket-kpi">R$ 0</h3>
                </div>
                <div class="rounded-full p-3 bg-green-500 bg-opacity-20">
                  <i class="fas fa-money-bill-wave text-green-600"></i>
                </div>
              </div>
              <div class="mt-4 text-xs text-gray-600">
                Valor médio por venda
              </div>
            </div>

            <!-- Ciclo de Venda -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-300 data-kpi" data-kpi="cycle">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-600 mb-1 text-sm">Ciclo de Venda</p>
                  <h3 class="text-3xl font-bold text-purple-600" id="average-cycle-days-kpi">0</h3>
                </div>
                <div class="rounded-full p-3 bg-purple-500 bg-opacity-20">
                  <i class="fas fa-hourglass-half text-purple-600"></i>
                </div>
              </div>
              <div class="mt-4 text-xs text-gray-600">
                Dias médios até fechamento
              </div>
            </div>

            <!-- Receita Total -->
            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow duration-300 data-kpi" data-kpi="revenue">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-600 mb-1 text-sm">Receita Total</p>
                  <h3 class="text-3xl font-bold text-orange-600" id="total-revenue-kpi">R$ 0</h3>
                </div>
                <div class="flex items-center justify-center">
                  <i class="fas fa-money-bill-wave text-5xl text-orange-500 opacity-80"></i>
                </div>
              </div>
              <div class="mt-4 text-xs text-gray-600">
                No período selecionado
              </div>
            </div>
          </div>
        </div>

        <!-- Widget de Alertas de Leads Inativos -->
        <div class="mb-8">
          <div id="inactivity-alerts-widget"></div>
        </div>

        <!-- Top Vendedores & Gráficos -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
          <!-- Top Vendedores -->
          <div class="bg-white rounded-lg shadow-sm p-6 h-[320px] overflow-y-auto">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
              <i class="fas fa-trophy text-yellow-500 mr-2"></i> Top Vendedores
            </h3>
            <div id="top-brokers-container" class="divide-y">
              <!-- Top brokers will be loaded here -->
              <div class="text-gray-500 text-center py-4">Carregando...</div>
            </div>
          </div>

          <!-- Gráfico de Desempenho -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm p-2 performance-section overflow-hidden h-[320px]">
              <div class="flex justify-between items-center mb-2">
                <h3 class="text-base font-semibold text-gray-800">Desempenho de Imóveis</h3>
                <select
                  id="performance-period-select"
                  data-section="performance"
                  class="px-2 py-0.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-xs">
                  <option>Últimos 7 dias</option>
                  <option>Últimos 30 dias</option>
                  <option>Últimos 90 dias</option>
                </select>
              </div>
              <!-- Gráfico Principal (dinâmico conforme período) -->
              <div id="performance-chart" class="w-full overflow-x-auto h-full">
                <div class="flex h-full items-end justify-between px-1 gap-1">
                  <!-- Barras do gráfico serão inseridas aqui via JavaScript -->
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Gráficos Avançados (ApexCharts) -->
        <div class="mt-8 space-y-6">
          <!-- Tendência de Vendas -->
          <div class="bg-gradient-to-br from-white to-gray-50 rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 p-8 border border-gray-100">
            <div class="mb-8">
              <div class="flex justify-between items-start">
                <div>
                  <div class="flex items-center gap-2 mb-2">
                    <span class="text-3xl">📈</span>
                    <h3 class="text-2xl font-bold text-gray-900">Tendência de Vendas</h3>
                  </div>
                  <p class="text-sm text-gray-600 font-medium">Evolução de vendas e receita ao longo do tempo</p>
                </div>
                <div class="flex gap-2">
                  <div class="px-4 py-2 bg-gradient-to-r from-blue-50 to-blue-100 text-blue-700 rounded-lg text-xs font-semibold border border-blue-200 flex items-center gap-2">
                    <i class="fas fa-sync-alt animate-spin"></i><span>Tempo real</span>
                  </div>
                </div>
              </div>
            </div>
            <div id="chart-sales-trend" class="w-full" style="min-height: 420px;">
              <div class="flex h-full items-center justify-center text-gray-400">
                <div class="text-center">
                  <i class="fas fa-spinner fa-spin text-4xl mb-4 opacity-50"></i>
                  <p class="text-gray-500 font-medium">Carregando gráfico de tendência...</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Funil de Leads e Desempenho por Categoria -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Funil de Leads -->
            <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-8 border border-gray-100">
              <div class="mb-6">
                <h3 class="text-2xl font-bold text-gray-800">🎯 Funil de Leads</h3>
                <p class="text-sm text-gray-500 mt-1">Fluxo de conversão de leads</p>
              </div>
              <div id="chart-lead-funnel" class="w-full" style="min-height: 400px;">
                <div class="flex h-full items-center justify-center text-gray-400">
                  <div class="text-center">
                    <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                    <p>Carregando funil...</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Desempenho por Categoria -->
            <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-8 border border-gray-100">
              <div class="mb-6">
                <h3 class="text-2xl font-bold text-gray-800">📊 Desempenho por Categoria</h3>
                <p class="text-sm text-gray-500 mt-1">Análise de imóveis e vendas por tipo</p>
              </div>
              <div id="chart-category-performance" class="w-full" style="min-height: 400px;">
                <div class="flex h-full items-center justify-center text-gray-400">
                  <div class="text-center">
                    <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                    <p>Carregando desempenho...</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Top 5 Bairros com Maior Movimento -->
          <div class="bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-8 border border-gray-100 mt-6">
            <div class="mb-6">
              <h3 class="text-2xl font-bold text-gray-800">🏘️ Top 5 Bairros com Maior Movimento</h3>
              <p class="text-sm text-gray-500 mt-1">Distribuição de vendas por região</p>
            </div>
            <div id="chart-neighborhood-sales" class="w-full" style="min-height: 400px;">
              <div class="flex h-full items-center justify-center text-gray-400">
                <div class="text-center">
                  <i class="fas fa-spinner fa-spin text-3xl mb-3"></i>
                  <p>Carregando bairros...</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity Feed (Últimas Atividades) -->
        <div class="mt-8">
          <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-clock text-blue-600 mr-2"></i> Últimas Atividades
              </h3>
              <span class="text-xs text-gray-500">Atualizado em tempo real</span>
            </div>
            <div id="activity-feed-container" class="divide-y">
              <div class="text-center py-8 text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl"></i>
                <p class="mt-2">Carregando atividades...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Alerts Feed (Alertas Ativos) -->
        <div class="mt-8">
          <div class="bg-white rounded-lg shadow-sm p-6 border-t-4 border-blue-600">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-bell text-orange-500 mr-2"></i>
                Alertas Ativos
                <span id="alerts-badge" class="ml-3 px-2 py-1 rounded-full text-white text-xs font-bold bg-blue-600">0</span>
              </h3>
              <span class="text-xs text-gray-500">Atualizado a cada 1 min</span>
            </div>
            <div id="alerts-feed-container" class="space-y-1">
              <div class="text-center py-8 text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl"></i>
                <p class="mt-2">Carregando alertas...</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Today Schedule (Agendamentos de Hoje) -->
        <div class="mt-8">
          <div class="bg-white rounded-lg shadow-sm p-6 border-t-4 border-green-600">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-calendar-day text-green-600 mr-2"></i>
                Agenda de Hoje
              </h3>
              <span class="text-xs text-gray-500">Em tempo real</span>
            </div>

            <!-- Estatísticas rápidas -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-blue-50 rounded-lg p-3 text-center">
                <i class="fas fa-calendar text-2xl text-blue-600 mb-2"></i>
                <p class="text-sm text-gray-600">Total</p>
                <p class="text-2xl font-bold text-gray-800" id="today-total-agendamentos">0</p>
              </div>
              <div class="bg-green-50 rounded-lg p-3 text-center">
                <i class="fas fa-check-circle text-2xl text-green-600 mb-2"></i>
                <p class="text-sm text-gray-600">Realizados</p>
                <p class="text-2xl font-bold text-gray-800" id="today-completed-agendamentos">0</p>
              </div>
              <div class="bg-yellow-50 rounded-lg p-3 text-center">
                <i class="fas fa-clock text-2xl text-yellow-600 mb-2"></i>
                <p class="text-sm text-gray-600">Pendentes</p>
                <p class="text-2xl font-bold text-gray-800" id="today-pending-agendamentos">0</p>
              </div>
              <div class="bg-purple-50 rounded-lg p-3 text-center">
                <i class="fas fa-chart-pie text-2xl text-purple-600 mb-2"></i>
                <p class="text-sm text-gray-600">Taxa</p>
                <p class="text-2xl font-bold text-gray-800" id="today-attendance-rate">0%</p>
              </div>
            </div>

            <!-- Lista de agendamentos -->
            <div id="today-schedule-list" class="space-y-3">
              <div class="text-center py-8 text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl"></i>
                <p class="mt-2">Carregando agendamentos...</p>
              </div>
            </div>

            <!-- Próximos dias -->
            <div class="mt-6 pt-6 border-t">
              <p class="text-sm font-semibold text-gray-700 mb-3">Próximos Agendamentos</p>
              <div id="today-pending-days" class="space-y-2">
                <!-- Será preenchido dinamicamente -->
              </div>
            </div>
          </div>
        </div>

        <!-- Goals & Targets (Metas e Objetivos) -->
        <div class="mt-8">
          <div class="bg-white rounded-lg shadow-sm p-6 border-t-4 border-purple-600">
            <div class="flex justify-between items-center gap-4 mb-6">
              <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-bullseye text-purple-600 mr-2"></i>
                Metas e Objetivos
              </h3>
              <div class="flex gap-2">
                <button data-goals-period="7" class="px-3 py-1 rounded text-sm transition-all duration-300">
                  7 dias
                </button>
                <button data-goals-period="30" class="px-3 py-1 rounded text-sm transition-all duration-300">
                  30 dias
                </button>
                <button data-goals-period="90" class="px-3 py-1 rounded text-sm transition-all duration-300">
                  90 dias
                </button>
                <button id="edit-goals-btn" class="px-3 py-1 rounded text-sm bg-purple-600 text-gray-700 hover:bg-purple-700 transition">
                  <i class="fas fa-edit mr-1"></i> Editar Metas
                </button>
              </div>
            </div>

            <!-- Container das Metas (para JS) -->
            <div id="goals-targets-container">

              <!-- Performance Geral -->
              <div id="goals-overall-progress" class="mb-8 p-6 bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg">
                <!-- Será preenchido dinamicamente -->
              </div>

              <!-- Grid de Metas -->
              <div id="goals-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-2xl"></i>
                  <p class="mt-2">Carregando metas...</p>
                </div>
              </div>

              <!-- Dicas -->
              <div class="mt-6 pt-6 border-t">
                <p class="text-xs text-gray-500">
                  <i class="fas fa-info-circle mr-2"></i>
                  As metas são atualizadas em tempo real conforme você avança em suas atividades no sistema.
                </p>
              </div>
            </div>

          </div>
        </div>

        <!-- Portfolio Statistics (Estatísticas de Portfolio) -->
        <div class="mt-8">
          <div class="bg-white rounded-lg shadow-sm p-6 border-t-4 border-indigo-600">
            <div class="mb-6">
              <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-chart-line text-indigo-600 mr-2"></i>
                Análise de Portfolio
              </h3>
              <p class="text-sm text-gray-500 mt-1">Análise detalhada de tipos, regiões e valores de imóveis</p>
            </div>

            <!-- Container do Portfolio para o JS -->
            <div id="portfolio-stats-container">

              <!-- Resumo do Portfolio -->
              <div id="portfolio-summary" class="mb-8">
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-2xl"></i>
                  <p class="mt-2">Carregando dados...</p>
                </div>

              </div>
            </div>

            <!-- Distribuição por Tipo -->
            <div class="mt-8 pt-6 border-t">
              <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-th-list text-blue-600 mr-2"></i> Distribuição por Tipo
              </h4>
              <div id="portfolio-by-type">
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-xl"></i>
                </div>
              </div>
            </div>

            <!-- Distribuição por Bairro -->
            <div class="mt-8 pt-6 border-t">
              <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-map-marker-alt text-green-600 mr-2"></i> Top Bairros
              </h4>
              <div id="portfolio-by-neighborhood">
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-xl"></i>
                </div>
              </div>
            </div>

            <!-- Faixas de Preço -->
            <div class="mt-8 pt-6 border-t">
              <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-coins text-yellow-600 mr-2"></i> Distribuição por Faixa de Preço
              </h4>
              <div id="portfolio-price-bands">
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-xl"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Leads Funnel (Funil de Leads por Status) -->
        <div class="mt-8" id="leads-funnel-container">
          <div class="bg-white rounded-lg shadow-sm p-6 border-t-4 border-cyan-600">
            <div class="mb-6">
              <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                <i class="fas fa-funnel text-cyan-600 mr-2"></i>
                Funil de Leads
              </h3>
              <p class="text-sm text-gray-500 mt-1">Distribuição de leads por estágio de conversão</p>
            </div>

            <!-- Resumo -->
            <div id="funnel-summary" class="mb-8">
              <div class="text-center py-8 text-gray-500">
                <i class="fas fa-spinner fa-spin text-2xl"></i>
              </div>
            </div>

            <!-- Gráfico do Funil -->
            <div class="mt-8 pt-6 border-t">
              <h4 class="text-md font-semibold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-layer-group text-blue-600 mr-2"></i> Visualização do Funil
              </h4>
              <div id="funnel-chart" class="bg-gradient-to-br from-gray-50 to-gray-100 p-6 rounded-lg">
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-2xl"></i>
                </div>
              </div>
            </div>

            <!-- Estatísticas por Status -->
            <div class="mt-8 pt-6 border-t">
              <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-bar text-green-600 mr-2"></i> Detalhes por Status
              </h4>
              <div id="funnel-status-stats">
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-xl"></i>
                </div>
              </div>
            </div>

            <!-- Leads por Origem -->
            <div class="mt-8 pt-6 border-t">
              <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-share-alt text-orange-600 mr-2"></i> Leads por Origem
              </h4>
              <div id="funnel-sources">
                <div class="text-center py-8 text-gray-500">
                  <i class="fas fa-spinner fa-spin text-xl"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-6">
          <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-semibold text-gray-800">Imóveis Recentes</h3>
              <a class="text-blue-600 hover:text-blue-800 transition text-sm" href="#" onclick="event.preventDefault(); document.querySelector('a[href=\'#properties\']').click();">Ver Todos</a>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead>
                  <tr>
                    <th
                      class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Imóvel</th>
                    <th
                      class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Tipo</th>
                    <th
                      class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Localização</th>
                    <th
                      class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Preço</th>
                    <th
                      class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200" id="recent-properties">
                  <!-- Recent properties will be loaded here -->
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
      <!-- Properties Tab -->
      <div class="tab-content p-6" id="properties">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800">Gerenciar Imóveis</h2>
          <a class="tab-link bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center"
            href="#add-property" style="background-color: var(--cor-primaria); color: #fff;">
            <i class="fas fa-plus mr-2"></i> Adicionar Imóvel
          </a>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0 mb-6">
            <div class="flex flex-col md:flex-row md:items-center space-y-4 md:space-y-0 md:space-x-4">
              <div class="relative">
                <input class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-full md:w-64"
                  id="property-search" placeholder="Buscar imóveis..." type="text" />
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
              </div>
              <select class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                id="select-tipo">
                <option value="">Todos os tipos</option>
              </select>
              <select class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                id="select-localizacao">
                <option value="Todas as regiões">Todas as regiões</option>
              </select>
              <select class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                id="select-preco">
                <option value="Qualquer preço">Qualquer preço</option>
                <option value="Até R$ 200.000">Até R$ 200.000</option>
                <option value="R$ 200.000 - R$ 500.000">R$ 200.000 - R$ 500.000</option>
                <option value="R$ 500.000 - R$ 1.000.000">R$ 500.000 - R$ 1.000.000</option>
                <option value="Acima de R$ 1.000.000">Acima de R$ 1.000.000</option>
              </select>
              <!-- Botão Limpar Filtros movido para aqui -->
              <button id="btn-limpar-filtros" class="px-4 py-2 rounded-lg transition-colors flex items-center"
                style="background-color: var(--cor-secundaria); color: #fff; border: none;">
                <i class="fas fa-eraser mr-2"></i>Limpar Filtros
              </button>
            </div>
            <div class="flex items-center space-x-2">
              <button id="list-view-btn" class="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition view-btn">
                <i class="fas fa-list text-gray-600"></i>
              </button>
              <button id="grid-view-btn" class="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition view-btn">
                <i class="fas fa-th-large text-gray-600"></i>
              </button>
            </div>
          </div>
          <div class="grid grid-view" id="properties-container">
            <!-- Property cards will be loaded here -->
          </div>
          <div class="mt-8 flex justify-between items-center">
            <p class="text-sm text-gray-600" id="properties-count">Mostrando 0 imóveis</p>
            <nav class="flex items-center justify-center gap-1" aria-label="Paginação">
              <button class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition" id="prev-page" title="Página anterior">
                <i class="fas fa-chevron-left text-gray-600"></i>
              </button>
              <div id="pagination-buttons" class="flex gap-1">
                <!-- Página buttons generated by JavaScript -->
              </div>
              <button class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition" id="next-page" title="Próxima página">
                <i class="fas fa-chevron-right text-gray-600"></i>
              </button>
            </nav>
          </div>
        </div>
      </div>
      <!-- Add Property Tab (fora da aba de properties!) -->
      <div class="tab-content p-6" id="add-property">
        <!-- O formulário de adicionar imóvel será carregado dinamicamente via adicionar-imovel.php -->
      </div>
      <!-- Users Tab -->
      <div class="tab-content p-6" id="users">
        <!-- O conteúdo será carregado dinamicamente via users.html -->
      </div>
      <!-- Leads Tab -->
      <div class="tab-content p-6" id="leads">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800">Gerenciar Leads</h2>
          <div class="flex gap-3">
            <button id="delete-selected-leads"
              class="font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
              disabled>
              <i class="fas fa-trash-alt mr-2"></i> Excluir Selecionados
            </button>
            <button id="export-leads-csv"
              class="font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center" style="background-color: var(--cor-primaria); color: #fff;">
              <i class="fas fa-download mr-2" style="color: #fff;"> Exportar CSV</i>

            </button>
          </div>
        </div>
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0 mb-6">
            <div class="flex flex-col md:flex-row md:items-center space-y-4 md:space-y-0 md:space-x-4">
              <div class="relative">
                <input
                  id="leads-search"
                  class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-full md:w-64"
                  placeholder="Buscar leads..." type="text" />
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
              </div>
              <select id="leads-status-filter"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all">Todos os status</option>
                <option value="new">Novo</option>
                <option value="contacted">Em contato</option>
                <option value="closed">Convertido</option>
                <option value="cancelled">Perdido</option>
              </select>
              <select id="leads-period-filter"
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all">Todos os períodos</option>
                <option value="today">Hoje</option>
                <option value="week">Esta semana</option>
                <option value="month">Este mês</option>
                <option>Este ano</option>
              </select>
            </div>
          </div>
          <div class="overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
              <thead>
                <tr>
                  <th class="px-2 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <div class="flex items-center">
                      <input id="select-all-leads" class="mr-2" type="checkbox" />
                      Nome
                    </div>
                  </th>
                  <th class="px-2 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Contato</th>
                  <th class="px-2 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Interesse</th>
                  <th class="px-2 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Data</th>
                  <th class="px-2 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status</th>
                  <th class="px-2 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Score</th>
                  <th class="px-2 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Atribuir a</th>
                  <th class="px-2 py-2 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Ações</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200" id="leads-container">
                <!-- Leads will be loaded here -->
              </tbody>
            </table>
          </div>
          <div class="mt-8 flex justify-between items-center">
            <p class="text-sm text-gray-600">Mostrando 0 leads</p>
            <!-- Scripts de paginação e gerenciamento de leads -->
            <script src="/assets/js/painel/leads-pagination.js" defer></script>
            <script src="/assets/js/lead-interactions-timeline.js" defer></script>
            <script src="/assets/js/lead-inactivity-alerts.js" defer></script>
            <script src="/assets/js/leads-manager.js" defer></script>
            <div class="flex space-x-1">
              <button class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition">
                <i class="fas fa-chevron-left text-gray-600"></i>
              </button>
              <button
                class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition bg-blue-600 text-white">1</button>
              <button class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition">2</button>
              <button class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition">3</button>
              <button class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition">4</button>
              <button class="px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition">
                <i class="fas fa-chevron-right text-gray-600"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <!-- Follow-up Tab -->
      <div class="tab-content p-6" id="followup">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-2xl font-bold text-gray-800">Follow-up Automático</h2>
          <div class="flex gap-3">
            <button id="create-sequence-btn"
              class="font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center text-white hover:opacity-90" style="background-color: var(--cor-primaria);">
              <i class="fas fa-plus mr-2"></i> Nova Sequência
            </button>
            <button id="execute-followups-btn"
              class="font-medium py-2 px-4 rounded-lg transition duration-300 flex items-center bg-green-600 text-white hover:bg-green-700">
              <i class="fas fa-play mr-2"></i> Executar Pendentes
            </button>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
              <div class="p-3 rounded-full bg-blue-100">
                <i class="fas fa-list text-blue-600"></i>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Sequências Ativas</p>
                <p class="text-2xl font-bold text-gray-900" id="active-sequences-count">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
              <div class="p-3 rounded-full bg-yellow-100">
                <i class="fas fa-clock text-yellow-600"></i>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Follow-ups Pendentes</p>
                <p class="text-2xl font-bold text-gray-900" id="pending-followups-count">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
              <div class="p-3 rounded-full bg-green-100">
                <i class="fas fa-check text-green-600"></i>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Executados Hoje</p>
                <p class="text-2xl font-bold text-gray-900" id="executed-today-count">0</p>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="flex items-center">
              <div class="p-3 rounded-full bg-purple-100">
                <i class="fas fa-envelope-open-text text-purple-600"></i>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-600">Total de Follow-ups</p>
                <p class="text-2xl font-bold text-gray-900" id="total-followups-count">0</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Sequences List -->
        <div class="bg-white rounded-lg shadow-sm p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-800">Sequências de Follow-up</h3>
            <button id="refresh-sequences-btn" class="text-sm text-blue-600 hover:text-blue-800">
              <i class="fas fa-sync-alt mr-1"></i> Atualizar
            </button>
          </div>

          <div id="sequences-container">
            <div class="text-center py-8 text-gray-500">
              <i class="fas fa-spinner fa-spin text-2xl mb-4"></i>
              <p>Carregando sequências...</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal de Criação/Edição de Sequência -->
      <div id="sequence-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
          <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[85vh] flex flex-col">
            <!-- Cabeçalho fixo -->
            <div class="flex justify-between items-center p-6 border-b shrink-0">
              <h3 class="text-xl font-semibold text-gray-900" id="sequence-modal-title">Criar Nova Sequência</h3>
              <button id="close-sequence-modal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
              </button>
            </div>

            <!-- Corpo com scroll interno -->
            <div class="flex-1 overflow-y-auto p-6" style="max-height:60vh;overflow-y:auto;">
              <form id="sequence-form">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Sequência *</label>
                    <input type="text" id="sequence-name" name="name" required
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Ex: Follow-up para leads quentes">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Condição de Disparo *</label>
                    <select id="sequence-trigger" name="trigger_condition" required
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                      <option value="">Selecione uma condição</option>
                      <option value="lead_created">Quando lead for criado</option>
                      <option value="status_changed">Quando status mudar para</option>
                      <option value="score_changed">Quando score for maior ou igual a</option>
                      <option value="inactivity_days">Quando lead estiver inativo há</option>
                    </select>
                  </div>
                </div>

                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Valor da Condição</label>
                  <input type="text" id="sequence-trigger-value" name="trigger_value"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Ex: 80 (para score) ou 7 (para dias)">
                </div>

                <div class="mb-6">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                  <textarea id="sequence-description" name="description" rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Descreva o objetivo desta sequência de follow-up"></textarea>
                </div>

                <!-- Passos da Sequência -->
                <div class="mb-6">
                  <div class="flex justify-between items-center mb-4">
                    <h4 class="text-lg font-medium text-gray-900">Passos da Sequência</h4>
                    <button type="button" id="add-step-btn"
                      class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2">
                      <i class="fas fa-plus"></i> Adicionar Passo
                    </button>
                  </div>

                  <div id="steps-container" class="space-y-4">
                    <!-- Passos serão adicionados dinamicamente -->
                  </div>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                  <button type="button" id="cancel-sequence-btn"
                    class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Cancelar
                  </button>
                  <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-save mr-2"></i> Salvar Sequência
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Settings Tab -->
      <?php if ($isAdmin): ?>
        <div class="tab-content p-6" id="settings">
          <!-- Tabs para Settings -->
          <div class="mb-6 flex gap-4 border-b border-gray-200">
            <button class="settings-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-blue-600 active" data-subtab="banner">
              <i class="fas fa-sliders-h mr-2"></i>Geral
            </button>
            <?php if ($isMainAdmin): ?>
              <button class="settings-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="backup">
                <i class="fas fa-database mr-2"></i>Backup
              </button>
            <?php endif; ?>
            <button class="settings-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="seguranca">
              <i class="fas fa-shield-alt mr-2"></i>Segurança
            </button>
            <button class="settings-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="api-publica">
              <i class="fas fa-key mr-2"></i>API Pública
            </button>
            <button class="settings-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="utm-campaigns">
              <i class="fas fa-link mr-2"></i>UTM Campaigns
            </button>
            <button class="settings-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="google-calendar">
              <i class="fas fa-calendar mr-2"></i>Google Calendar
            </button>
            <button class="settings-subtab-link px-4 py-2 text-gray-700 font-medium border-b-2 border-transparent hover:border-gray-300" data-subtab="users">
              <i class="fas fa-users-cog mr-2"></i>Usuários
            </button>
          </div>

          <!-- Geral Sub-tab -->
          <div id="settings-banner-content" class="settings-subtab-content">
            <div id="settings-dynamic-content">
              <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Imagens do Banner Principal</h3>
                <div id="banner-images-list" class="flex flex-wrap gap-6 mb-4"></div>
                <form id="banner-upload-form" class="flex items-center gap-4">
                  <input type="file" id="banner-image-input" accept="image/*" class="hidden" />
                  <button type="button" id="add-banner-image-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg flex items-center gap-2 hover:bg-blue-700 transition">
                    <i class="fas fa-plus"></i> Adicionar Imagem
                  </button>
                  <input type="text" id="banner-title-input" placeholder="Título (opcional)" class="border rounded px-2 py-1 text-sm" />
                  <input type="text" id="banner-desc-input" placeholder="Descrição (opcional)" class="border rounded px-2 py-1 text-sm" />
                </form>
                <div id="banner-upload-status" class="mt-2 text-sm text-gray-500"></div>
              </div>
            </div>
          </div>

          <!-- Backup Sub-tab -->
          <?php if ($isMainAdmin): ?>
            <div id="settings-backup-content" class="settings-subtab-content hidden">
              <h2 class="text-2xl font-bold text-gray-800 mb-6">Backup do Sistema</h2>
              <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-start mb-6">
                  <div class="bg-blue-100 rounded-full p-3 mr-4">
                    <i class="fas fa-database text-xl" style="color: var(--cor-primaria);"></i>
                  </div>
                  <div>
                    <h3 class="text-xl font-semibold mb-2">Realizar Backup</h3>
                    <p class="text-gray-600 mb-4">
                      Faça backup do banco de dados e das configurações do sistema.
                      Os backups são armazenados de forma segura na pasta 'backups' e podem ser restaurados quando necessário.
                    </p>
                    <div class="flex items-center space-x-4">
                      <button onclick="realizarBackup()" class="flex items-center px-4 py-2 bg-yellow-500 text-white font-semibold rounded-lg hover:bg-yellow-600 transition duration-300 ease-in-out shadow-md">
                        <i class="fas fa-download mr-2"></i>
                        Fazer Backup Agora
                      </button>
                    </div>
                  </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm p-6 mt-8">
                  <div class="flex items-start mb-6">
                    <div class="bg-blue-100 rounded-full p-3 mr-4">
                      <i class="fas fa-history text-xl" style="color: var(--cor-primaria);"></i>
                    </div>
                    <div>
                      <h3 class="text-xl font-semibold mb-2">Restaurar Backup</h3>
                      <p class="text-gray-600 mb-4">
                        Restaure o sistema a partir de um backup anterior.
                        <strong class="text-warning">Atenção:</strong> A restauração irá substituir todos os dados atuais pelos dados do backup selecionado.
                      </p>

                      <div class="overflow-x-auto">
                        <div id="error-message" class="alert alert-danger d-none mb-4"></div>
                        <div id="loading" class="text-center d-none mb-4">
                          <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                          </div>
                        </div>
                        <table class="min-w-full bg-white border rounded-lg">
                          <thead>
                            <tr>
                              <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                              <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Arquivo</th>
                              <th class="px-6 py-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tamanho</th>
                              <th class="px-6 py-3 border-b text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            </tr>
                          </thead>
                          <tbody id="backups-list">
                            <tr>
                              <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                Carregando backups disponíveis...
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="mt-6 pt-6 border-t border-gray-200">
                  <h4 class="text-lg font-semibold mb-3">Informações Importantes</h4>
                  <ul class="list-disc list-inside text-gray-600 space-y-2">
                    <li>O backup inclui todos os dados do banco de dados e configurações do sistema</li>
                    <li>Os arquivos são salvos na pasta 'backups' com data e hora</li>
                    <li>Mantenha cópias dos backups em local seguro</li>
                    <li>Em caso de problemas, contate o administrador do sistema</li>
                  </ul>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Segurança Sub-tab -->
          <div id="settings-seguranca-content" class="settings-subtab-content hidden">
            <div class="flex items-center justify-center min-h-96">
              <div class="text-center">
                <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i>
                <p class="text-gray-600">Carregando dashboard de segurança...</p>
              </div>
            </div>
          </div>

          <!-- API Pública Sub-tab -->
          <div id="settings-api-publica-content" class="settings-subtab-content hidden">
            <div class="bg-white rounded-lg shadow-sm p-6">
              <h3 class="text-2xl font-bold text-gray-800 mb-6">Chaves de API</h3>

              <!-- Seção de Geração de Nova Chave -->
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <h4 class="text-lg font-bold text-blue-900 mb-4">
                  <i class="fas fa-key mr-2"></i>Gerar Nova Chave de API
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nome da Chave</label>
                    <input type="text" id="api-key-name" placeholder="Ex: Integração CRM" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Data de Expiração</label>
                    <select id="api-key-expiration" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                      <option value="never">Nunca expira</option>
                      <option value="30">30 dias</option>
                      <option value="90">90 dias</option>
                      <option value="180">180 dias</option>
                      <option value="365">1 ano</option>
                    </select>
                  </div>
                  <div class="flex items-end">
                    <button onclick="gerarChaveAPI()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition" style="background-color: var(--cor-primaria);">
                      <i class="fas fa-plus mr-2"></i>Gerar Chave
                    </button>
                  </div>
                </div>
              </div>

              <!-- Chaves Existentes -->
              <div>
                <h4 class="text-lg font-bold text-gray-800 mb-4">Suas Chaves Ativas</h4>
                <div id="api-keys-list" class="space-y-3">
                  <div class="flex items-center justify-center py-8">
                    <div class="text-center">
                      <i class="fas fa-spinner fa-spin text-2xl text-blue-600 mb-2"></i>
                      <p class="text-gray-600">Carregando chaves...</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Documentação Rápida -->
              <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 mt-8">
                <h4 class="text-lg font-bold text-gray-800 mb-4">Documentação da API</h4>
                <div class="space-y-2">
                  <p class="text-gray-700"><strong>Documentação Completa:</strong> <a href="/api/v1/public/API_PUBLICA_DOCS.html" target="_blank" class="text-blue-600 hover:underline">Acessar Documentação</a></p>
                  <p class="text-gray-700"><strong>Base URL:</strong> <code class="bg-white px-2 py-1 rounded border border-gray-300">https://seu-dominio/api/v1/public/</code></p>
                  <p class="text-gray-700"><strong>Autenticação:</strong> Utilize o header <code class="bg-white px-2 py-1 rounded border border-gray-300">X-API-Key: sua-chave-aqui</code></p>
                </div>
              </div>
            </div>
          </div>

          <!-- Google Calendar Sub-tab -->
          <div id="settings-google-calendar-content" class="settings-subtab-content hidden">
            <div class="bg-white rounded-lg shadow-sm p-6">
              <h3 class="text-2xl font-bold text-gray-800 mb-6">
                <i class="fas fa-calendar-check mr-2" style="color: #4285F4;"></i>Sincronização com Google Calendar
              </h3>

              <!-- Status de Conexão -->
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-6 border border-blue-200">
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="font-semibold text-gray-800">Status</h4>
                    <i class="fas fa-spinner animate-spin text-blue-600" id="google-status-icon"></i>
                  </div>
                  <p class="text-2xl font-bold text-blue-600" id="google-calendar-status">Carregando...</p>
                  <p class="text-sm text-gray-600 mt-2">Verifica conexão em tempo real</p>
                </div>

                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-6 border border-green-200">
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="font-semibold text-gray-800">Agendamentos Sincronizados</h4>
                  </div>
                  <p class="text-2xl font-bold text-green-600" id="google-sync-count">0</p>
                  <p class="text-sm text-gray-600 mt-2">Total sincronizado</p>
                </div>

                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-6 border border-purple-200">
                  <div class="flex items-center justify-between mb-2">
                    <h4 class="font-semibold text-gray-800">Última Sincronização</h4>
                  </div>
                  <p class="text-sm font-mono text-purple-600 mt-2" id="google-last-sync">Nunca</p>
                  <p class="text-sm text-gray-600 mt-2">Data e hora</p>
                </div>
              </div>

              <!-- Ações -->
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <h4 class="text-lg font-bold text-blue-900 mb-4">
                  <i class="fas fa-cogs mr-2"></i>Configuração
                </h4>
                <div class="space-y-4">
                  <div id="google-auth-section">
                    <p class="text-gray-700 mb-4">Conecte sua conta Google para sincronizar agendamentos automaticamente.</p>
                    <a href="/google/oauth.php" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition shadow-md">
                      <i class="fas fa-sign-in-alt mr-2"></i>Conectar com Google
                    </a>
                  </div>

                  <div id="google-actions-section" class="hidden space-y-3">
                    <button onclick="sincronizarGoogleCalendarNow()" class="flex items-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition shadow-md">
                      <i class="fas fa-sync-alt mr-2"></i>Sincronizar Agora
                    </button>
                    <button onclick="desconectarGoogleCalendar()" class="flex items-center px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition shadow-md">
                      <i class="fas fa-unlink mr-2"></i>Desconectar Google
                    </button>
                  </div>

                  <div id="google-loading" style="display: none;" class="text-center py-4">
                    <i class="fas fa-spinner animate-spin text-blue-600 text-2xl"></i>
                    <p class="text-gray-600 mt-2">Processando...</p>
                  </div>
                </div>
              </div>

              <!-- Informações sobre Sincronização -->
              <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                <h4 class="text-lg font-bold text-gray-800 mb-4">
                  <i class="fas fa-info-circle mr-2"></i>Como Funciona
                </h4>
                <div class="space-y-3 text-sm text-gray-700">
                  <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mr-3 mt-0.5"></i>
                    <span><strong>Bidirecional:</strong> Agendamentos criados no sistema aparecem no Google Calendar e vice-versa.</span>
                  </div>
                  <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mr-3 mt-0.5"></i>
                    <span><strong>Automática:</strong> A sincronização ocorre a cada 30 minutos ou sob demanda.</span>
                  </div>
                  <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mr-3 mt-0.5"></i>
                    <span><strong>Segura:</strong> Utiliza OAuth 2.0 - suas credenciais nunca são armazenadas.</span>
                  </div>
                  <div class="flex items-start">
                    <i class="fas fa-check-circle text-green-600 mr-3 mt-0.5"></i>
                    <span><strong>Multi-Usuário:</strong> Cada usuário pode ter sua própria sincronização.</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Users Sub-tab -->
          <div id="settings-users-content" class="settings-subtab-content hidden">
            <div id="users-dynamic-content">
              <!-- Conteúdo de gerenciamento de usuários será carregado aqui -->
            </div>
          </div>

          <!-- UTM Campaigns Sub-tab -->
          <div id="settings-utm-campaigns-content" class="settings-subtab-content hidden">
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex justify-between items-center mb-6">
                <div>
                  <h3 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-link mr-2" style="color: #FF6B6B;"></i>Campanhas UTM
                  </h3>
                  <p class="text-gray-600 mt-1">Rastreie a origem de seus leads com UTM parameters</p>
                </div>
                <button onclick="abrirModalNovaCampanhaUTM()" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition" style="background-color: var(--cor-primaria);">
                  <i class="fas fa-plus mr-2"></i>Nova Campanha
                </button>
              </div>

              <!-- Info Box -->
              <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-800">
                  <i class="fas fa-info-circle mr-2"></i>
                  <strong>O que é?</strong> UTM parameters são tags adicionadas ao URL para rastrear a origem, meio e conteúdo de seus leads.
                  Crie campanhas para gerar links de rastreamento únicos para cada canal de marketing.
                </p>
              </div>

              <!-- Campanhas List -->
              <div id="utm-campaigns-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="flex items-center justify-center col-span-3 py-12">
                  <div class="text-center">
                    <i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-3"></i>
                    <p class="text-gray-600">Carregando campanhas...</p>
                  </div>
                </div>
              </div>

              <!-- Analytics Preview -->
              <div class="mt-8 pt-8 border-t border-gray-200">
                <h4 class="text-lg font-bold text-gray-800 mb-4">
                  <i class="fas fa-chart-bar mr-2"></i>Performance das Campanhas
                </h4>
                <a href="javascript:showTab('relatorios')" class="text-blue-600 hover:underline">
                  Ver relatório completo de UTM em Relatórios →
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <!-- Success Modal -->
      <!-- <div class="fixed inset-0 z-50 hidden overflow-y-auto" id="success-modal">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center">
      <div aria-hidden="true" class="fixed inset-0 transition-opacity">
        <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
      </div>
      <div
        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="sm:flex sm:items-start">
            <div
              class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
              <i class="fas fa-check text-green-600"></i>
            </div>
            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
              <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                Imóvel Cadastrado com Sucesso!
              </h3>
              <div class="mt-2">
                <p class="text-sm text-gray-500">
                  O imóvel foi adicionado ao sistema e já está disponível para visualização no site.
                </p>
              </div>
            </div>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm"
            id="close-success-modal" type="button">
            OK
          </button>
          <button
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            id="view-property-btn" type="button">
            Ver no Site
          </button>
        </div>
      </div>
    </div>
  </div> -->

      <!-- Website Preview Modal -->
      <div class="fixed inset-0 z-50 hidden overflow-y-auto" id="website-modal">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center">
          <div aria-hidden="true" class="fixed inset-0 transition-opacity">
            <div class="absolute inset-0 bg-gray-900 opacity-75"></div>
          </div>
          <div
            class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
            <div class="bg-white">
              <div class="flex justify-between items-center p-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">
                  Visualização do Site
                </h3>
                <button class="text-gray-400 hover:text-gray-500" id="close-website-modal" type="button">
                  <i class="fas fa-times"></i>
                </button>
              </div>
              <div class="p-0" style="height: 70vh; overflow-y: auto;">
                <iframe class="w-full h-full" id="website-iframe" style="border: none;"></iframe>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Website HTML Template (Hidden) -->
      <template id="website-template">
        <!DOCTYPE html>

        <html lang="pt-BR">

        <head>
          <meta charset="utf-8" />
          <meta content="width=device-width, initial-scale=1.0" name="viewport" />
          <title>RR Imóveis - Encontre seu Imóvel dos Sonhos</title>
          <style>
            @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap');

            body {
              font-family: var(--fonte-principal, 'Open Sans', sans-serif);
            }

            .property-card {
              transition: all 0.3s ease;
            }

            .property-card:hover {
              transform: translateY(-5px);
              box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            }

            .hero-section {
              background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600" preserveAspectRatio="none"><rect width="1200" height="600" fill="%23004080"/><polygon points="0,600 1200,300 1200,600" fill="%23003366"/><circle cx="900" cy="150" r="100" fill="%23002b59"/></svg>');
              background-size: cover;
              background-position: center;
            }
          </style>
        </head>

        <body>

          function carregarCompanySettings() {
          const data = JSON.parse(localStorage.getItem('companySettings') || '{}');
          document.getElementById('company-name').value = data.name || '';
          document.getElementById('company-email').value = data.email || '';
          document.getElementById('company-email2').value = data.email2 || '';
          document.getElementById('company-phone').value = data.phone || '';
          document.getElementById('company-whatsapp').value = data.whatsapp || '';
          document.getElementById('company-address').value = data.address || '';
          document.getElementById('company-description').value = data.description || '';
          document.getElementById('company-facebook').value = data.facebook || '';
          document.getElementById('company-instagram').value = data.instagram || '';
          document.getElementById('company-linkedin').value = data.linkedin || '';
          document.getElementById('company-youtube').value = data.youtube || '';
          document.getElementById('company-logo').value = data.logo || '';
          document.getElementById('company-color1').value = data.color1 || '#111827';
          document.getElementById('company-color2').value = data.color2 || '#10b981';
          document.getElementById('company-color3').value = data.color3 || '#d97706';
          document.getElementById('company_font').value = data.font || '';
          }

          // <script>
            (function() {
              function c() {
                var b = a.contentDocument || a.contentWindow.document;
                if (b) {
                  var d = b.createElement('script');
                  d.innerHTML = "window.__CF$cv$params={r:'9644246345a7daa8',t:'MTc1MzM2NzcwNi4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";
                  b.getElementsByTagName('head')[0].appendChild(d)
                }
              }
              if (document.body) {
                var a = document.createElement('iframe');
                a.height = 1;
                a.width = 1;
                a.style.position = 'absolute';
                a.style.top = 0;
                a.style.left = 0;
                a.style.border = 'none';
                a.style.visibility = 'hidden';
                document.body.appendChild(a);
                if ('loading' !== document.readyState) c();
                else if (window.addEventListener) document.addEventListener('DOMContentLoaded', c);
                else {
                  var e = document.onreadystatechange || function() {};
                  document.onreadystatechange = function(b) {
                    e(b);
                    'loading' !== document.readyState && (document.onreadystatechange = e, c())
                  }
                }
              }
            })();
          </script>

      </template>
      <script>
        function loadRecentProperties() {
          const container = document.getElementById('recent-properties');
          const properties = window.propertyData || [];

          container.innerHTML = '';

          if (properties.length === 0) {
            container.innerHTML = `
      <tr>
        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
          Nenhum imóvel cadastrado ainda.
        </td>
      </tr>
    `;
            return;
          }

          recentProperties.forEach(property => {
            const priceFormatted = Number(property.price).toLocaleString('pt-BR');
            const isRent = property.transactionType === 'rent' || property.type === 'aluguel';
            const priceDisplay = isRent ? `R$ ${priceFormatted}/mês` : `R$ ${priceFormatted}`;

            const location = `${property.neighborhood || property.location || '–'} - ${property.city || ''}, ${property.state || ''}, ${property.zip || ''}`;

            let imageHtml = '';
            let images = property.images;
            if (typeof images === 'string') {
              try {
                images = JSON.parse(images);
              } catch {
                images = [];
              }
            }
            if (Array.isArray(images) && images.length > 0 && images[0]) {
              let imgSrc = images[0];
              // Se já começa com assets/imagens/ ou http(s), use direto
              if (!/^assets\/imagens\//.test(imgSrc) && !/^https?:\/\//.test(imgSrc) && !imgSrc.startsWith('/')) {
                imgSrc = `assets/imagens/${property.id}/${imgSrc}`;
              }
              imageHtml = `<img src="${imgSrc}" alt="${property.title}" class="w-full h-48 object-cover" onerror="this.src='assets/imagens/default.jpg';" />`;
            } else {
              imageHtml = `<img src="assets/imagens/default.jpg" alt="Sem imagem" class="w-full h-48 object-cover" />`;
            }


            const row = document.createElement('tr');
            row.innerHTML = `
  <td class="px-6 py-4 whitespace-nowrap">
    <div class="flex items-center">
      <div class="w-16 h-16 rounded-md overflow-hidden bg-gray-100 mr-3 flex items-center justify-center">
        ${imageHtml}
      </div>
      <div>
        <div class="text-sm font-medium text-gray-900">${property.title}</div>
        <div class="text-sm text-gray-500">ID: #${property.id}</div>
      </div>
    </div>
  </td>
  <td class="px-6 py-4 whitespace-nowrap">
    <div class="text-sm text-gray-900">${getPropertyTypeText(property.type)}</div>
  </td>
  <td class="px-6 py-4 whitespace-nowrap">
    <div class="text-sm text-gray-900">${location}</div>
  </td>
  <td class="px-6 py-4 whitespace-nowrap">
    <div class="text-sm font-medium text-gray-900">${priceDisplay}</div>
  </td>
  <td class="px-6 py-4 whitespace-nowrap">
    <span class="${getStatusClass(property.status)}">
  ${getStatusText(property.status)}
    </span>
  </td>
  `;
            container.appendChild(row);
          });
        }

        function loadPropertiesGrid() {
          const container = document.getElementById('properties-container');
          const counter = document.getElementById('properties-count');
          const properties = window.propertyData || [];

          container.innerHTML = '';

          if (!properties.length) {
            container.innerHTML = `<p class="text-gray-500">Nenhum imóvel encontrado.</p>`;
            counter.textContent = "Mostrando 0 imóveis";
            return;
          }



          // Adiciona CSS da ribbon se não existir
          if (!document.getElementById('property-ribbon-style')) {
            const style = document.createElement('style');
            style.id = 'property-ribbon-style';
            style.innerHTML = `
        .property-ribbon-painel {
          position: absolute;
          top: 18px;
          left: -38px;
          z-index: 20;
          background: #111827;
          color: #fff;
          padding: 6px 48px;
          font-size: 0.95rem;
          font-weight: bold;
          transform: rotate(-25deg);
          box-shadow: 0 2px 8px rgba(0,0,0,0.10);
          letter-spacing: 1px;
          text-shadow: 0 1px 2px rgba(0,0,0,0.10);
          pointer-events: none;
        }
        .property-ribbon-painel.green { background: #10b981; }
        .property-ribbon-painel.orange { background: #d97706; }
      `;
            document.head.appendChild(style);
          }


          properties.forEach(property => {
            let imageHtml = '';
            let images = property.images;
            if (typeof images === 'string') {
              try {
                images = JSON.parse(images);
              } catch {
                images = [];
              }
            }
            if (Array.isArray(images) && images.length > 0 && images[0]) {
              let imgSrc = images[0];
              // Se já começa com assets/imagens/ ou http(s), use direto
              if (!/^assets\/imagens\//.test(imgSrc) && !/^https?:\/\//.test(imgSrc) && !imgSrc.startsWith('/')) {
                imgSrc = `assets/imagens/${property.id}/${imgSrc}`;
              }
              imageHtml = `<img src="${imgSrc}" alt="${property.title}" class="w-full h-48 object-cover" onerror="this.src='assets/imagens/default.jpg';" />`;
            } else {
              imageHtml = `<img src="assets/imagens/default.jpg" alt="Sem imagem" class="w-full h-48 object-cover" />`;
            }

            let priceNumber = Number(property.price);
            if (isNaN(priceNumber)) {
              priceNumber = parseFloat((property.price || '').replace(/\./g, '').replace(/,/g, '.'));
            }
            const priceFormatted = priceNumber.toLocaleString('pt-BR', {
              minimumFractionDigits: 2
            });

            // Ribbon de VENDIDO/ALUGADO
            let ribbon = '';
            const status = (property.status || '').toLowerCase();
            if (status === 'vendido' || status === 'alugado') {
              let color = '';
              if ((property.type || '').toLowerCase() === 'aluguel' || (property.transactionType || '').toLowerCase() === 'aluguel') {
                color = 'green';
              } else if ((property.type || '').toLowerCase() === 'comercial' || (property.transactionType || '').toLowerCase() === 'comercial') {
                color = 'orange';
              }
              ribbon = `<div class="property-ribbon-painel${color ? ' ' + color : ''}">VENDIDO/ALUGADO</div>`;
            }

            const card = document.createElement('div');
            card.className = 'bg-white rounded-lg shadow-md p-4 property-card';
            card.innerHTML = `
        <div class="relative mb-2" style="min-height: 192px;">
          ${imageHtml}
          ${ribbon}
        </div>
        <h4 class="text-lg font-semibold mb-2">${property.title}</h4>
        <p class="text-sm text-gray-600 mb-1">${property.neighborhood}, ${property.city}</p>
        <p class="text-sm text-gray-800 font-medium mb-1">R$ ${priceFormatted}</p>
        <div class="mt-2">
          <span class="${getStatusClass(property.status)}">
            ${getStatusText(property.status)}
          </span>
        </div>
        <p class="text-xs text-gray-500 mt-1">${getPropertyTypeText(property.type)}</p>
      `;
            container.appendChild(card);
          });

          counter.textContent = `Mostrando ${properties.length} imóveis`;
        }

        async function loadLeads() {
          if (typeof window.initLeadsPagination === 'function') {
            window.initLeadsPagination();
          }
        }

        // Função para mostrar modal de detalhes do lead
        function mostrarModalLead(lead) {
          let modal = document.getElementById('leadModal');
          if (!modal) {
            modal = document.createElement('div');
            modal.id = 'leadModal';

            // 👇 ajuste aqui também (melhor responsividade vertical)
            modal.className = 'fixed inset-0 z-50 flex items-start sm:items-center justify-center bg-black bg-opacity-50 p-4';

            modal.innerHTML = `
      <div class="bg-white rounded-lg shadow-lg relative" style="display: flex; flex-direction: column; width: 90%; max-width: 800px; height: 85vh; max-height: calc(100vh - 2rem);">
        
        <!-- Header Fixo -->
        <div class="border-b border-gray-200 p-6" style="flex-shrink: 0; border-bottom: 1px solid #e5e7eb;">
          <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-2xl font-bold" id="close-lead-modal" style="position: absolute; top: 0.5rem; right: 0.5rem; background: none; border: none; cursor: pointer;">&times;</button>
          
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-xl font-semibold">Detalhes do Lead</h3>
            <div>
              <button id="print-lead-btn" class="mr-2 text-sm bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700" title="Imprimir">
                <i class="fas fa-print"></i> Imprimir
              </button>
            </div>
          </div>
          
          <!-- Tabs -->
          <div class="flex gap-4 border-b border-gray-200" style="border-bottom: 1px solid #e5e7eb; margin: 0; padding: 0;">
            <button class="lead-modal-tab active px-4 py-2 font-medium" data-tab="detalhes" style="border: none; background: none; cursor: pointer; padding: 0.5rem 1rem; border-bottom: 2px solid #111827; color: #111827; font-weight: 500;">
              <i class="fas fa-file-alt mr-1"></i> Detalhes
            </button>
            <button class="lead-modal-tab px-4 py-2 font-medium" data-tab="historico" style="border: none; background: none; cursor: pointer; padding: 0.5rem 1rem; border-bottom: 2px solid transparent; color: #4b5563;">
              <i class="fas fa-history mr-1"></i> Histórico
            </button>
          </div>
        </div>

        <!-- Content com Scroll -->
        <div id="lead-modal-content" style="flex: 1; overflow-y: auto; overflow-x: hidden; padding: 1.5rem; min-height: 0;">
        </div>

      </div>
    `;
            document.body.appendChild(modal);
          }

          // Atualizar event listeners das abas com o novo lead ID
          // Remove listeners antigas e adiciona novas para evitar históricos duplicados
          modal.querySelectorAll('.lead-modal-tab').forEach(tab => {
            // Clona o elemento para remover listeners antigas
            const newTab = tab.cloneNode(true);
            tab.parentNode.replaceChild(newTab, tab);
          });

          // Reset visual state das abas para "Detalhes" ativo
          modal.querySelectorAll('.lead-modal-tab').forEach(tab => {
            if (tab.getAttribute('data-tab') === 'detalhes') {
              tab.classList.add('border-blue-600', 'text-blue-600');
              tab.classList.remove('border-transparent', 'text-gray-600');
            } else {
              tab.classList.remove('border-blue-600', 'text-blue-600');
              tab.classList.add('border-transparent', 'text-gray-600');
            }
          });

          // Adiciona listeners novas com o lead.id correto
          modal.querySelectorAll('.lead-modal-tab').forEach(tab => {
            tab.addEventListener('click', function() {
              const tabName = this.getAttribute('data-tab');
              switchLeadModalTab(tabName, lead.id);
            });
          });

          // Registra a interação do usuário (visualização do lead)
          (async () => {
            try {
              await fetch('/api/v1/leads/updateLead.php', {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  id: lead.id,
                  interaction: 'viewed'
                })
              });
            } catch (e) {
              console.error('Erro ao registrar visualização do lead:', e);
            }
          })();

          // Preencher conteúdo (aba Detalhes)
          const content = modal.querySelector('#lead-modal-content');
          renderLeadDetailsTab(content, lead);
          setupLeadDetailsEventListeners(content, lead);

          // Fechar modal
          modal.querySelector('#close-lead-modal').onclick = function() {
            modal.style.display = 'none';
          };
          // Fechar ao clicar fora
          modal.onclick = function(e) {
            if (e.target === modal) modal.style.display = 'none';
          };
          modal.style.display = 'flex';
        }

        // Render aba Detalhes do Lead
        function renderLeadDetailsTab(container, lead) {
          container.innerHTML = `
            <div class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Nome</label>
                  <p class="text-sm text-gray-900">${lead.name || '-'}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Email</label>
                  <p class="text-sm text-gray-900">${lead.email || '-'}</p>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700">Telefone</label>
                  <p class="text-sm text-gray-900">${lead.phone || '-'}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700">Status</label>
                  <p class="text-sm text-gray-900">${getLeadStatusText(lead.status) || '-'}</p>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Interesse</label>
                <p class="text-sm text-gray-900" style="white-space: pre-wrap; overflow-wrap: anywhere;">${lead.message || '-'}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Origem</label>
                <p class="text-sm text-gray-900">${lead.source || '-'}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Data de Criação</label>
                <p class="text-sm text-gray-900">${lead.created_at ? new Date(lead.created_at).toLocaleString('pt-BR') : '-'}</p>
              </div>

              <div>
                <label for="lead-modal-notes" class="block text-sm font-medium text-gray-700 mb-2">Anotações</label>
                <textarea id="lead-modal-notes" rows="4" class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="Digite suas anotações...">${lead.notes ? lead.notes.replace(/</g, '&lt;').replace(/>/g, '&gt;') : ''}</textarea>
                <div id="lead-notes-status" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
              </div>

              <label class="inline-flex items-center">
                <input type="checkbox" id="lead-proposal-sent" class="form-checkbox h-4 w-4 text-blue-600" ${lead.proposal_sent ? 'checked' : ''} />
                <span class="ml-2 text-sm">Proposta Enviada</span>
              </label>
              <div id="lead-proposal-status" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
            </div>
          `;
        }

        // Setup event listeners para aba Detalhes
        function setupLeadDetailsEventListeners(container, lead) {
          const notesTextarea = container.querySelector('#lead-modal-notes');
          const notesStatus = container.querySelector('#lead-notes-status');

          if (notesTextarea) {
            notesTextarea.addEventListener('blur', async function() {
              const newNotes = notesTextarea.value;
              notesStatus.style.display = 'block';
              notesStatus.textContent = 'Salvando...';
              try {
                const res = await fetch('/api/v1/leads/updateLead.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify({
                    id: lead.id,
                    notes: newNotes
                  })
                });
                const json = await res.json();
                if (json.success) {
                  notesStatus.textContent = 'Anotações salvas!';
                  setTimeout(() => {
                    notesStatus.style.display = 'none';
                  }, 1500);
                  if (window.leadsData) {
                    const idx = window.leadsData.findIndex(l => String(l.id) === String(lead.id));
                    if (idx !== -1) window.leadsData[idx].notes = newNotes;
                  }
                  lead.notes = newNotes;
                } else {
                  notesStatus.textContent = 'Erro ao salvar anotações.';
                }
              } catch (e) {
                notesStatus.textContent = 'Erro ao salvar anotações.';
              }
            });
          }

          const proposalCheckbox = container.querySelector('#lead-proposal-sent');
          const proposalStatus = container.querySelector('#lead-proposal-status');

          if (proposalCheckbox) {
            proposalCheckbox.addEventListener('change', async function() {
              const newVal = proposalCheckbox.checked ? 1 : 0;
              proposalStatus.style.display = 'block';
              proposalStatus.textContent = 'Salvando...';
              try {
                const res = await fetch('/api/v1/leads/updateLead.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify({
                    id: lead.id,
                    proposal_sent: newVal
                  })
                });
                const json = await res.json();
                if (json.success) {
                  proposalStatus.textContent = 'Alteração salva!';
                  setTimeout(() => {
                    proposalStatus.style.display = 'none';
                  }, 1500);
                  if (window.leadsData) {
                    const idx = window.leadsData.findIndex(l => String(l.id) === String(lead.id));
                    if (idx !== -1) window.leadsData[idx].proposal_sent = newVal ? 1 : 0;
                  }
                  lead.proposal_sent = newVal ? 1 : 0;
                } else {
                  proposalStatus.textContent = `Erro: ${json.message || 'Desconhecido'}`;
                }
              } catch (e) {
                proposalStatus.textContent = 'Erro ao salvar.';
              }
            });
          }
        }

        // Alternar entre abas do modal
        function switchLeadModalTab(tabName, leadId) {
          const tabs = document.querySelectorAll('.lead-modal-tab');
          const content = document.querySelector('#lead-modal-content');

          tabs.forEach(tab => {
            if (tab.getAttribute('data-tab') === tabName) {
              tab.classList.add('border-blue-600', 'text-blue-600');
              tab.classList.remove('border-transparent', 'text-gray-600');
            } else {
              tab.classList.remove('border-blue-600', 'text-blue-600');
              tab.classList.add('border-transparent', 'text-gray-600');
            }
          });

          if (tabName === 'detalhes') {
            const lead = window.leadsData.find(l => String(l.id) === String(leadId));
            if (lead) {
              renderLeadDetailsTab(content, lead);
              setupLeadDetailsEventListeners(content, lead);
            }
          } else if (tabName === 'historico') {
            content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><p class="mt-2 text-gray-600">Carregando histórico...</p></div>';
            loadLeadInteractionsTimeline(leadId).then(html => {
              content.innerHTML = html;
            });
          }
        }

        // Add new property
        function addProperty(propertyData) {
          const properties = window.propertyData || [];

          // Generate new ID
          const newId = properties.length > 0 ? Math.max(...properties.map(p => p.id)) + 1 : 1;

          // Create new property object
          const newProperty = {
            id: newId,
            ...propertyData
          };

          // Add to properties array
          properties.push(newProperty);

          // Save to localStorage
          localStorage.setItem('properties', JSON.stringify(properties));

          // Update dashboard counts
          updateDashboardCounts();

          return newProperty;
        }

        // Edit property
        function editProperty(propertyId) {
          // This would typically load the property data into the form
          // For this demo, we'll just navigate to the add property tab
          document.querySelector('a[href="#add-property"]').click();
        }

        // Excluir imóvel via PHP
        async function deleteProperty(propertyId) {
          if (!confirm('Tem certeza que deseja excluir este imóvel?')) return;

          try {
            const formData = new FormData();
            formData.append('id', propertyId);

            const res = await fetch('/api/v1/properties/deleteProperty.php', {
              method: 'POST',
              body: formData,
              credentials: 'include',
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            });

            const result = await res.json();

            if (!result.success) {
              throw new Error(result.message || 'Erro ao excluir imóvel.');
            }

            if (typeof carregarImoveisPainel === 'function') carregarImoveisPainel();
            updateDashboardCounts();

            // Recarregar KPIs e gráficos do dashboard
            if (typeof dashboardKPIs !== 'undefined' && dashboardKPIs.loadKPIs) {
              dashboardKPIs.loadKPIs();
            }
            if (typeof dashboardCharts !== 'undefined' && dashboardCharts.loadCharts) {
              dashboardCharts.loadCharts();
            }
          } catch (err) {
            // ...
            // ...
          }
        }

        // Atualiza o total de imóveis e vendidos/alugados no dashboard
        function updateDashboardCounts() {
          fetch('/api/v1/properties/getProperties.php?panel=1')
            .then(res => res.json())
            .then(result => {
              const properties = Array.isArray(result) ?
                result :
                (result && result.success && Array.isArray(result.data)) ?
                result.data :
                (result && Array.isArray(result.data)) ?
                result.data : [];

              document.getElementById("total-properties").textContent = properties.length;
              // Contar imóveis com status 'VENDA' ou 'ALUGUEL' (case-insensitive)
              const soldCount = properties.filter(p => {
                if (!p.status) return false;
                const status = String(p.status).toLowerCase();
                return status === 'vendido' || status === 'alugado';
              }).length;
              const soldElem = document.getElementById("total-sold-properties");
              if (soldElem) soldElem.textContent = soldCount;
            })
            .catch(err => {
              // ...
              document.getElementById("total-properties").textContent = '0';
              const soldElem = document.getElementById("total-sold-properties");
              if (soldElem) soldElem.textContent = '0';
            });
        }

        // Helpers
        function getPropertyTypeText(type) {
          const types = {
            apartment: 'Apartamento',
            house: 'Casa',
            commercial: 'Comercial',
            land: 'Terreno'
          };
          return types[type] || type;
        }

        function getPropertyIcon(type) {
          const icons = {
            apartment: 'building',
            house: 'home',
            commercial: 'store',
            land: 'map-marker-alt'
          };
          return icons[type] || 'home';
        }


        //ja esta no utils.js
        // Usando a função unificada getLeadStatusStyle
        function getLeadStatusClass(status) {
          return getLeadStatusStyle(status);
        }

        function getLeadStatusIcon(status) {
          const icons = {
            new: 'fas fa-star',
            contacted: 'fas fa-phone',
            closed: 'fas fa-check-circle',
            cancelled: 'fas fa-times-circle',
            default: 'fas fa-circle'
          };
          return icons[status] || icons.default;
        }

        function getLeadStatusText(status) {
          const statuses = {
            new: 'Novo',
            contacted: 'Em contato',
            closed: 'Convertido',
            cancelled: 'Perdido',
            negotiating: 'Negociando'
          };
          return statuses[status] || status;
        }

        // Função unificada de status de leads com suporte a múltiplos estilos
        function getLeadStatusStyle(status, style = 'default') {
          const styleMap = {
            default: {
              new: 'novo',
              contacted: 'em-contato',
              closed: 'convertido',
              cancelled: 'perdido',
              default: 'aguardando'
            },
            colors: {
              new: 'bg-yellow-100 text-yellow-800',
              contacted: 'bg-blue-100 text-blue-800',
              negotiating: 'bg-green-100 text-green-800',
              closed: 'bg-purple-100 text-purple-800',
              cancelled: 'bg-red-100 text-red-800',
              default: 'bg-gray-100 text-gray-800'
            }
          };
          return (styleMap[style] && styleMap[style][status]) || styleMap[style].default;
        }

        function formatNumber(num) {
          return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Website preview
        function generateWebsiteHTML() {
          const template = document.getElementById('website-template').innerHTML;
          return template;
        }

        function showWebsitePreview() {
          const websiteModal = document.getElementById('website-modal');
          const iframe = document.getElementById('website-iframe');

          websiteModal.classList.remove('hidden');

          const html = generateWebsiteHTML();
          const blob = new Blob([html], {
            type: 'text/html'
          });
          const url = URL.createObjectURL(blob);
          iframe.src = url;
        }

        // Toggle sidebar gerenciado por assets/js/panel-bootstrap.js (não duplicar evento)
        // Previne transições durante o carregamento inicial
        document.body.classList.add('preload');
        window.addEventListener('load', () => {
          document.body.classList.remove('preload');
        });

        // Função para carregar a aba de usuários
        window.carregarUsers = async function() {
          const container = document.getElementById('users');
          if (!container || container.dataset.loaded === 'true') return;

          container.innerHTML = '<div class="flex items-center justify-center min-h-[200px]"><i class="fas fa-spinner fa-spin text-3xl text-blue-600"></i></div>';

          try {
            const res = await fetch('views/admin/users.php');
            if (!res.ok) throw new Error('Erro ao carregar página de usuários');
            const html = await res.text();
            container.innerHTML = html;
            container.dataset.loaded = 'true';

            // Depois que o HTML for carregado, inicializa os eventos
            if (typeof window.initUsers === 'function') {
              window.initUsers();
            }
          } catch (err) {
            container.innerHTML = '<div class="text-red-500 text-center">Erro ao carregar gerenciamento de usuários</div>';
            // ...
          }
        }

        // Função para carregar a aba de Segurança
        window.carregarSeguranca = async function() {
          const container = document.getElementById('settings-seguranca-content');
          if (!container || container.dataset.loaded === 'true') return;

          container.innerHTML = '<div class="flex items-center justify-center min-h-96"><div class="text-center"><i class="fas fa-spinner fa-spin text-3xl text-blue-600 mb-4"></i><p class="text-gray-600">Carregando dashboard de segurança...</p></div></div>';

          try {
            const res = await fetch('security-monitoring.php', {
              credentials: 'include'
            });
            if (!res.ok) throw new Error('Erro ao carregar página de segurança');
            const html = await res.text();

            // Extrai apenas o conteúdo do body, ignorando headers e estrutura HTML
            const startMarker = html.indexOf('<div class="container-fluid');
            const endMarker = html.lastIndexOf('</body>');

            if (startMarker !== -1 && endMarker !== -1) {
              // Pega o conteúdo principal SEM remover scripts
              let content = html.substring(startMarker, endMarker);
              container.innerHTML = content;
            } else {
              container.innerHTML = html;
            }

            container.dataset.loaded = 'true';

            // Executa os scripts novamente
            const scripts = container.querySelectorAll('script');
            scripts.forEach(script => {
              const newScript = document.createElement('script');
              if (script.src) {
                newScript.src = script.src;
                newScript.async = false;
              } else {
                newScript.textContent = script.textContent;
              }
              document.body.appendChild(newScript);
            });

            // Aguarda um pouco para os scripts se carregarem, depois chama loadSecurityDashboard
            setTimeout(() => {
              if (typeof loadSecurityDashboard === 'function') {
                loadSecurityDashboard();
              }
            }, 500);
          } catch (err) {
            container.innerHTML = '<div class="text-red-500 text-center p-6">Erro ao carregar página de segurança: ' + err.message + '</div>';
          }
        }

        // Função para carregar o formulário de adicionar/editar imóvel
        window.carregarFormularioImovel = async function() {
          const container = document.getElementById('add-property');
          if (!container) return Promise.resolve(null);
          if (window.__propertyFormLoadPromise) return window.__propertyFormLoadPromise;

          const activeForm = document.getElementById('property-form');
          const activeEditId = activeForm ? (activeForm.querySelector('[name="edit_id"]')?.value ?? '').trim() : '';
          const activeEditingPropertyId = activeForm ? (activeForm.dataset.editingPropertyId || '').trim() : '';

          if (activeForm && activeForm.isConnected && (activeEditId !== '' || activeEditingPropertyId !== '')) {
            return Promise.resolve(activeForm);
          }

          if (container.dataset.loaded === 'true') return Promise.resolve(container);

          window.__propertyFormLoadPromise = (async () => {
            container.innerHTML = '<div class="flex items-center justify-center min-h-[200px]"><i class="fas fa-spinner fa-spin text-3xl text-blue-600"></i></div>';

            try {
              const res = await fetch('views/admin/adicionar-imovel.php', {
                headers: {
                  'X-Requested-With': 'XMLHttpRequest'
                }
              });
              if (!res.ok) throw new Error('Erro ao carregar formulário');
              const html = await res.text();
              container.innerHTML = html;
              container.dataset.loaded = 'true';
              return container;
            } catch (err) {
              container.innerHTML = '<div class="text-red-500 text-center">Erro ao carregar formulário de imóvel</div>';
              return container;
            } finally {
              window.__propertyFormLoadPromise = null;
            }
          })();

          return window.__propertyFormLoadPromise;
        }

        // Carregar configs.php dinamicamente na aba Configurações
        let configsLoaded = false; // Flag para evitar carregamento múltiplo
        window.carregarConfigsDinamicamente = async function() {
          // Evita carregamento duplicado
          if (configsLoaded) {
            return;
          }

          const container = document.getElementById('settings-dynamic-content');
          if (!container) return;
          container.innerHTML = '<div class="flex flex-col items-center justify-center min-h-[200px] text-gray-500"><i class="fas fa-cog text-4xl mb-4 animate-spin"></i><p>Carregando configurações...</p></div>';
          try {
            const res = await fetch('configs.php?_t=' + Date.now()); // Cache busting
            if (!res.ok) {
              throw new Error('Erro ao carregar configurações: ' + res.status);
            }
            let html = await res.text();
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const form = temp.querySelector('#company_settings_form');
            if (!form) {
              throw new Error('Formulário não encontrado no HTML retornado');
            }

            // Limpa o container e adiciona o formulário
            container.innerHTML = '';
            container.appendChild(form);

            configsLoaded = true; // Marca como carregado

            // Inicializa as configurações
            if (typeof initializeSettings === 'function') {
              initializeSettings();
            } else {
              // ...
              throw new Error('Erro ao inicializar configurações');
            }
          } catch (err) {
            // ...
            configsLoaded = false; // Reset se houver erro
            container.innerHTML = `<div class="text-red-500">Erro ao carregar configurações: ${err.message}</div>`;
          }
        }

        // Consolidado: Inicialização ao carregar página
        document.addEventListener('DOMContentLoaded', function() {
          // Carregar configs se aba estiver ativa - usando requestIdleCallback
          const settingsTab = document.getElementById('settings');
          if (settingsTab && settingsTab.classList.contains('active')) {
            if ('requestIdleCallback' in window) {
              requestIdleCallback(() => carregarConfigsDinamicamente(), {
                timeout: 1000
              });
            } else {
              setTimeout(() => carregarConfigsDinamicamente(), 100);
            }
          }

          // REMOVIDO: Carregamento duplicado de imóveis
          // O DOMContentLoaded já chama carregarImoveisPainel()

          // Carrega o contador de leads do dashboard - NÃO bloqueante
          if (typeof atualizarContadorLeadsDashboard === 'function') {
            if ('requestIdleCallback' in window) {
              requestIdleCallback(() => atualizarContadorLeadsDashboard(), {
                timeout: 1500
              });
            } else {
              setTimeout(() => atualizarContadorLeadsDashboard(), 300);
            }
          }

          // Adiciona listeners aos botões de tab
          document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function(e) {
              const target = this.getAttribute('href');
              if (target === '#imoveis' && typeof carregarImoveisPainel === 'function') {
                // Proteção contra múltiplas chamadas rápidas
                if (!window.lastImoveisLoad || Date.now() - window.lastImoveisLoad > 1000) {
                  carregarImoveisPainel();
                  window.lastImoveisLoad = Date.now();
                }
              }
              if (target === '#leads' && typeof loadLeads === 'function') {
                loadLeads();
              }
            });
          });

          if (window.pendingLeadId && typeof loadLeads === 'function') {
            loadLeads();
          }

          // Carrega estatísticas de visitas
          carregarEstatisticasVisitas();

          // Carrega métricas do pipeline
          if (typeof carregarMetricasPipeline === 'function') {
            carregarMetricasPipeline();
          }
        });

        // Fecha modal de sucesso
        const closeSuccessModalBtn = document.getElementById('close-propertyModal');
        if (closeSuccessModalBtn) {
          closeSuccessModalBtn.addEventListener('click', function() {
            document.getElementById('propertyModal').classList.add('hidden');

            // Redireciona para aba de propriedades (se existir)
            const propTab = document.querySelector('a[href="#properties"]');
            if (propTab) propTab.click();
          });
        }

        // Botão "Ver imóvel no site"
        const viewPropertyBtn = document.getElementById('view-property-btn');
        if (viewPropertyBtn) {
          viewPropertyBtn.addEventListener('click', function() {
            document.getElementById('propertyModal').classList.add('hidden');
            showWebsitePreview();
          });
        }


        // Fecha modal de preview
        const closeWebsiteModalBtn = document.getElementById('close-website-modal');
        if (closeWebsiteModalBtn) {
          closeWebsiteModalBtn.addEventListener('click', function() {
            document.getElementById('website-modal').classList.add('hidden');
          });
        }

        // Funções auxiliares
        function obterQtdQuartos(features) {
          if (!features) return 0;
          return Array.isArray(features) ? features.filter(f => f.includes('quarto')).length : 0;
        }

        // Função auxiliar para obter número de banheiros
        function obterQtdBanheiros(features) {
          if (!features) return 0;
          return Array.isArray(features) ? features.filter(f => f.includes('banheiro')).length : 0;
        }

        // Função para renderizar um imóvel na tabela
        function renderizarImovelNaTabela(property, recentTable) {
          const row = document.createElement("tr");
          row.innerHTML = `
          <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
              <div class="flex-shrink-0 h-10 w-16">
                <img class="h-10 w-16 object-cover rounded" src="${property.images[0]}" alt="img">
              </div>
              <div class="ml-4">
                <div class="text-sm font-medium text-gray-900">${property.title}</div>
              </div>
            </div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">-</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${property.location}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${property.price}</td>
          <td class="px-6 py-4 whitespace-nowrap">
            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusClass(property.status)}">
              ${getStatusText(property.status)}
            </span>
          </td>
        `;
          recentTable.appendChild(row);

          // Cards de imóveis
          const card = document.createElement("div");
          card.className = "bg-white rounded-lg shadow-sm overflow-hidden";
          const priceFormatted = Number(property.price).toLocaleString('pt-BR', {
            minimumFractionDigits: 2
          });
          card.innerHTML = `
          <img src="${property.images[0]}" class="w-full h-48 object-cover" alt="Imagem do imóvel">
          <div class="p-4">
            <h4 class="text-lg font-semibold text-gray-800">${property.title}</h4>
            <p class="text-gray-500 text-sm">${property.location}</p>
            <p class="text-blue-600 font-semibold mt-2">R$ ${priceFormatted}</p>
            <div class="flex justify-between text-sm text-gray-600 mt-3">
              <div class="flex items-center gap-1">
                <i class="fas fa-bed text-blue-600"></i>
                <span>${obterQtdQuartos(property.features)}</span>
              </div>
              <div class="flex items-center gap-1">
                <i class="fas fa-bath text-blue-600"></i>
                <span>${obterQtdBanheiros(property.features)}</span>
              </div>
              <div class="flex items-center gap-1">
                <i class="fas fa-ruler-combined text-blue-600"></i>
                <span>${property.area}</span>
              </div>
            </div>
            <div class="mt-2">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusClass(property.status)}">
                ${getStatusText(property.status)}
              </span>
            </div>
            <button class="mt-4 bg-blue-600 hover:bg-blue-700 text-white text-sm py-2 px-4 rounded transition">
              Ver Detalhes
            </button>
          </div>
        `;
          const botaoDetalhes = card.querySelector("button");
          botaoDetalhes.addEventListener("click", () => {
            abrirModalDetalhes(property);
          });
          cardContainer.appendChild(card);
        }
      </script>
      <!-- Modal de Detalhes do Imóvel -->
      <div class="modal fade" id="propertyModal" tabindex="-1" aria-labelledby="modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content">

            <!-- Cabeçalho do Modal -->
            <div class="modal-header">

              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <!-- Corpo do Modal -->
            <div class="modal-body">
              <div class="row g-4">

                <!-- Carrossel de Imagens -->
                <div class="col-12">
                  <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner rounded shadow-sm" id="modal-carousel-images">
                      <!-- Imagens via JS -->
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel"
                      data-bs-slide="prev">
                      <span class="carousel-control-prev-icon"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel"
                      data-bs-slide="next">
                      <span class="carousel-control-next-icon"></span>
                    </button>
                  </div>
                </div>

                <!-- Detalhes -->
                <div class="col-12">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 id="modal-title" class="modal-title fw-bold">Título do Imóvel</h5>
                    <span id="modal-price" class="fs-5 fw-bold text-primary">R$ 0.000.000</span>
                  </div>


                  <p id="modal-location" class="text-muted mb-3">
                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                    Localização
                  </p>

                  <!-- Usuário Atribuído -->
                  <div id="modal-assigned-user-section" class="alert alert-info py-2 px-3 mb-3" style="display: none;">
                    <i class="fas fa-user-check me-2"></i>
                    <span class="fw-semibold">Atribuído a:</span>
                    <span id="modal-assigned-user-name" class="ms-2"></span>
                  </div>

                  <!-- Bloco de Área e Ano de Construção -->
                  <div class="d-flex gap-5 py-2 px-3 rounded mb-4" style="background-color: #f0f7ff;">
                    <!-- Área -->
                    <div class="d-flex align-items-center gap-2">
                      <span id="modal-area" class="fw-semibold">N/A</span>
                    </div>
                    <!-- Ano de Construção -->
                    <div class="d-flex align-items-center gap-2">
                      <div class="d-flex align-items-center">
                        <span class="text-muted small me-2">Ano de Construção</span>
                        <span id="modal-yearBuilt" class="fw-semibold">N/A</span>
                      </div>
                    </div>
                  </div>
                  <h6 class="fw-semibold mb-2">Descrição do Imóvel</h6>
                  <p id="modal-description" class="mb-3 text-muted small">Descrição do imóvel</p>
                  <div>
                    <h6 class="fw-semibold mb-2">Características</h6>
                    <ul id="modal-features" class="row row-cols-1 row-cols-sm-2 g-2 ps-0 text-success small list-unstyled">
                      <!-- Características via JS -->
                    </ul>
                  </div>
                </div>

              </div>
            </div>

            <!-- Rodapé -->
            <div class="modal-footer justify-content-between">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i>
                Fechar
              </button>
            </div>
          </div>
        </div>
      </div>


      <!-- Bootstrap JS Bundle com carregamento otimizado -->
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
      <!-- ApexCharts - Necessário antes de dashboard-charts.js -->
      <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>

      <!-- Scripts da aplicação -->
      <!-- Utils primeiro, pois outros scripts dependem dele -->
      <script src="/assets/js/utils/browser-support.js" defer></script>
      <script src="/assets/js/utils/utils.js" defer></script>
      <script src="/assets/js/utils/color-utils.js" defer></script>
      <script src="/assets/js/painel/ui-manager.js" defer></script>

      <!-- Scripts específicos do painel -->
      <script src="/assets/js/painel/settings.js" defer></script>
      <script src="/assets/js/painel/utm-campaigns.js" defer></script>
      <script src="/assets/js/painel/painel-main.js" defer></script>
      <script src="/assets/js/painel/performance-chart.js" defer></script>
      <script src="/assets/js/painel/dashboard-kpis.js" defer></script>
      <script src="/assets/js/painel/dashboard-charts.js" defer></script>
      <script src="/assets/js/painel/dashboard-period-controller.js" defer></script>
      <script src="/assets/js/painel/dashboard-variation.js" defer></script>
      <script src="/assets/js/painel/dashboard-filters.js" defer></script>
      <script src="/assets/js/painel/activity-feed.js" defer></script>
      <script src="/assets/js/painel/alerts-feed.js" defer></script>
      <script src="/assets/js/painel/today-schedule.js" defer></script>
      <script src="/assets/js/painel/goals-and-targets.js" defer></script>
      <script src="/assets/js/painel/goals-editor.js" defer></script>
      <script src="/assets/js/painel/portfolio-stats.js" defer></script>
      <script src="/assets/js/painel/leads-funnel.js" defer></script>
      <script src="/assets/js/painel/export-manager.js" defer></script>
      <script src="/assets/js/painel/api-key-manager.js" defer></script>
      <script src="/assets/js/painel/users.js" defer></script>
      <script src="/assets/js/painel/followup.js"></script>
      <script src="/assets/js/public/card-color-sync.js" defer></script>
      <script src="/assets/js/painel/filtro-painel.js" defer></script>
      <script src="/assets/js/visual_lista_grade.js" defer></script>
      <script src="/assets/js/company-description.js" defer></script>
      <script src="/assets/js/public/company-name.js" defer></script>
      <script src="/assets/js/panel-bootstrap.js" defer></script>

      <!-- Script do formulário -->
      <script src="assets/js/painel/adicionar-imovel.js" defer></script>

      <!-- Scripts de backup e restore por último para evitar conflitos -->
      <script src="assets/js/backupModule.js"></script>
      <script defer src="assets/js/backup.js"></script>
      <script defer src="assets/js/restore.js"></script>
      <script>
        // Aplica as cores e fonte do backend no painel
        function aplicarCoresPainel(data) {
          // Atualiza as cores com suporte a RGB
          atualizarCoresComRGB(data);
          if (data.company_font) {
            document.documentElement.style.setProperty('--fonte-principal', `'${data.company_font}', sans-serif`);
            if (!['Open Sans', 'Arial', 'sans-serif'].includes(data.company_font)) {
              const link = document.createElement('link');
              link.rel = 'stylesheet';
              link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(data.company_font)}:wght@300;400;500;600;700&display=swap`;
              document.head.appendChild(link);
            }
          }
          const root = document.documentElement;
          root.style.setProperty('--color-primary', data.company_color1 || '#111827');
          root.style.setProperty('--color-secondary', data.company_color2 || '#10b981');
          root.style.setProperty('--color-accent', data.company_color3 || '#d97706');

          // Otimização: Atrasar aplicação de cores para não bloquear renderização
          if ('requestAnimationFrame' in window) {
            requestAnimationFrame(() => {
              const colorMap = {
                '.bg-blue-600': data.company_color1 || '#111827',
                '.bg-green-600': data.company_color2 || '#10b981',
                '.bg-orange-500': data.company_color3 || '#d97706',
                '.text-blue-600': data.company_color1 || '#111827',
                '.text-green-600': data.company_color2 || '#10b981',
                '.text-orange-500': data.company_color3 || '#d97706'
              };

              Object.entries(colorMap).forEach(([selector, color]) => {
                const isBg = selector.startsWith('.bg-');
                document.querySelectorAll(selector).forEach(el => {
                  if (isBg) el.style.backgroundColor = color;
                  else el.style.color = color;
                });
              });
            });
          }
        }

        document.addEventListener('DOMContentLoaded', function() {
          // Carregar configurações de forma não-bloqueante
          // Usar requestIdleCallback para executar após o navegador estar ocioso
          if ('requestIdleCallback' in window) {
            requestIdleCallback(function() {
              const settingsPromise = window.getCompanySettings();

              settingsPromise
                .then(json => {
                  if (json.success && json.data) {
                    aplicarCoresPainel(json.data);
                  }
                })
                .catch(() => {});
            }, {
              timeout: 2000
            }); // Timeout de 2s para não esperar muito
          } else {
            // Fallback para navegadores sem requestIdleCallback
            setTimeout(() => {
              const settingsPromise = window.getCompanySettings();

              settingsPromise
                .then(json => {
                  if (json.success && json.data) {
                    aplicarCoresPainel(json.data);
                  }
                })
                .catch(() => {});
            }, 100);
          }

          // Atualização instantânea entre abas usando BroadcastChannel
          if ('BroadcastChannel' in window) {
            const canal = new BroadcastChannel('configuracoes_empresa');
            canal.onmessage = function(ev) {
              if (ev.data && ev.data.tipo === 'atualizar_cores') {
                aplicarCoresPainel(ev.data);
              }
            };
          }
        });

        // Google Calendar Functions
        async function carregarGoogleCalendar() {
          const cardEl = document.getElementById('google-calendar-card');
          if (!cardEl) return;

          try {
            // Simples verificação: Se a card existe, mostra
            cardEl.style.display = 'block';
            document.getElementById('google-cal-status').textContent = '✅ Pronto para sincronizar';
          } catch (err) {
            console.error('Erro ao verificar Google Calendar:', err);
          }
        }

        // Função para carregar status do Google Calendar na aba Settings
        async function carregarStatusGoogleCalendarSettings() {
          const statusEl = document.getElementById('google-calendar-status');
          const syncCountEl = document.getElementById('google-sync-count');
          const lastSyncEl = document.getElementById('google-last-sync');
          const authSection = document.getElementById('google-auth-section');
          const actionsSection = document.getElementById('google-actions-section');

          if (!statusEl) return;

          try {
            const response = await fetch('/api/v1/agendamentos/getAgendamentos.php', {
              credentials: 'include',
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            });
            const data = await response.json();

            const checkResponse = await fetch('/api/v1/google_calendar.php?action=get-status', {
              credentials: 'include',
              headers: {
                'X-Requested-With': 'XMLHttpRequest'
              }
            });

            const checkData = await checkResponse.json();
            const isConnected = !!(checkData && (checkData.connected === true || checkData.authenticated === true || checkData.status === 'active'));

            if (isConnected) {
              statusEl.textContent = '✅ Conectado';
              statusEl.style.color = '#10b981';
              statusEl.parentElement.parentElement.style.borderColor = '#10b981';
              document.getElementById('google-status-icon').className = 'fas fa-check-circle text-green-600';
              authSection.style.display = 'none';
              actionsSection.style.display = 'block';

              if (data.data && data.data.length > 0) {
                syncCountEl.textContent = data.data.length;
              }
            } else {
              statusEl.textContent = '⚠️ Desconectado';
              statusEl.style.color = '#ef4444';
              statusEl.parentElement.parentElement.style.borderColor = '#ef4444';
              document.getElementById('google-status-icon').className = 'fas fa-exclamation-circle text-red-600';
              authSection.style.display = 'block';
              actionsSection.style.display = 'none';
            }

            lastSyncEl.textContent = new Date().toLocaleString('pt-BR');
          } catch (err) {
            console.error('Erro ao carregar status Google Calendar:', err);
            statusEl.textContent = '✅ Conectado';
            statusEl.style.color = '#10b981';
            document.getElementById('google-status-icon').className = 'fas fa-check-circle text-green-600';
            authSection.style.display = 'none';
            actionsSection.style.display = 'block';
          }
        }

        async function sincronizarGoogleCalendarNow() {
          const btn = event.target.closest('button');
          btn.disabled = true;
          const originalText = btn.innerHTML;
          btn.innerHTML = '<i class="fas fa-spinner animate-spin mr-1"></i>Sincronizando...';

          try {
            const response = await fetch('/api/v1/google/syncCalendar.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              }
            });

            const data = await response.json();

            if (data.success) {
              alert(`✓ Sucesso!\n${data.message}`);
              if (data.errors && data.errors.length > 0) {
                console.warn('Erros durante sincronização:', data.errors);
              }
            } else {
              alert('✗ Erro: ' + data.message);
            }
          } catch (err) {
            alert('Erro na sincronização: ' + err.message);
          } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
          }
        }

        function abrirConfigGoogleCalendar() {
          window.location.href = '/google/oauth.php';
        }

        async function desconectarGoogleCalendar(event) {
          const sourceEvent = event || window.event;
          if (sourceEvent && sourceEvent.preventDefault) {
            sourceEvent.preventDefault();
          }

          if (!confirm('Desconectar Google Calendar? Os agendamentos não serão mais sincronizados.')) {
            return;
          }

          const csrfToken = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
          const disconnectButton = sourceEvent && sourceEvent.currentTarget ?
            sourceEvent.currentTarget :
            document.querySelector('#google-actions-section button:last-of-type');

          if (disconnectButton) {
            disconnectButton.disabled = true;
            disconnectButton.dataset.originalText = disconnectButton.innerHTML;
            disconnectButton.innerHTML = '<i class="fas fa-spinner animate-spin mr-2"></i>Desconectando...';
          }

          try {
            const response = await fetch('/api/v1/google_calendar.php?action=disconnect', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
              },
              body: JSON.stringify({
                csrf_token: csrfToken
              })
            });

            let data = null;
            try {
              data = await response.json();
            } catch (jsonError) {
              data = null;
            }

            const fallbackMessage = 'Falha ao processar a desconexão do Google Calendar.';
            const statusMessage = data && data.message ? data.message : fallbackMessage;

            if (!response.ok) {
              if (data && typeof data.error === 'string') {
                alert('✗ ' + data.error);
              } else {
                alert('✗ ' + statusMessage);
              }
              await carregarStatusGoogleCalendarSettings();
              return;
            }

            const success = !!(data && data.success);
            const localInactive = !!(data && data.local_connection_inactive);
            const partialFailure = !!(data && data.partial_failure);

            if (success && localInactive && !partialFailure) {
              sessionStorage.removeItem('google_calendar_connected');
              await carregarStatusGoogleCalendarSettings();
              alert('✅ Google Calendar desconectado com sucesso.');
              return;
            }

            if (localInactive && partialFailure) {
              sessionStorage.removeItem('google_calendar_connected');
              await carregarStatusGoogleCalendarSettings();
              alert('⚠️ Conexão local foi removida, mas a revogação externa falhou: ' + statusMessage);
              return;
            }

            if (data && data.local_connection_inactive === false) {
              alert('✗ ' + statusMessage);
              await carregarStatusGoogleCalendarSettings();
              return;
            }

            alert('✗ ' + statusMessage);
            await carregarStatusGoogleCalendarSettings();
          } catch (error) {
            console.error('Erro ao desconectar Google Calendar:', error);
            alert('✗ Erro de comunicação ao desconectar Google Calendar.');
            await carregarStatusGoogleCalendarSettings();
          } finally {
            if (disconnectButton) {
              disconnectButton.disabled = false;
              disconnectButton.innerHTML = disconnectButton.dataset.originalText || '<i class="fas fa-unlink mr-2"></i>Desconectar Google';
              delete disconnectButton.dataset.originalText;
            }
          }
        }
      </script>
      <!-- Portal export modal and launcher -->
      <?php if ($isAdmin): ?>
        <button id="portal-export-launcher" title="Exportar para Portais"
          style="position:fixed;right:1rem;bottom:1rem;z-index:9999;border-radius:50%;width:56px;height:56px;display:flex;align-items:center;justify-content:center;background:#111827;color:#fff;border:none;box-shadow:0 6px 18px rgba(0,0,0,0.15);">
          <i class="fas fa-bolt"></i>
        </button>
      <?php endif; ?>

      <!-- Export Dashboard Modal -->
      <div id="export-dashboard-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center;" class="flex">
        <div style="background: white; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0; font-size: 20px; font-weight: 600; color: #1f2937;">
              <i class="fas fa-download text-green-600 mr-2"></i>Exportar Dashboard
            </h4>
            <button onclick="ExportManager.closeExportModal()" style="background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer;">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <form id="export-dashboard-form" style="margin-bottom: 20px;">
            <div style="margin-bottom: 16px;">
              <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151;">Tipo de Relatório</label>
              <select id="export-report-type" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                <option value="summary">Resumo Geral</option>
                <option value="kpis">KPIs e Indicadores</option>
                <option value="portfolio">Análise de Portfolio</option>
                <option value="leads">Lista de Leads</option>
                <option value="goals">Metas e Objetivos</option>
              </select>
            </div>

            <div style="margin-bottom: 16px;">
              <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151;">Período</label>
              <select id="export-period" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                <option value="7">Últimos 7 dias</option>
                <option value="30" selected>Últimos 30 dias</option>
                <option value="90">Últimos 90 dias</option>
              </select>
            </div>

            <div style="display: flex; gap: 8px;">
              <button type="button" id="export-csv-btn" onclick="event.preventDefault(); ExportManager.export('csv');" style="flex: 1; padding: 10px; background: #3b82f6; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background 0.3s;">
                <i class="fas fa-file-csv mr-2"></i>Exportar CSV
              </button>
              <button type="button" id="export-excel-btn" onclick="event.preventDefault(); ExportManager.export('excel');" style="flex: 1; padding: 10px; background: #10b981; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer; transition: background 0.3s;">
                <i class="fas fa-file-excel mr-2"></i>Exportar Excel
              </button>
            </div>
          </form>

          <div style="text-align: center; color: #6b7280; font-size: 12px;">
            <p>Os dados serão exportados no formato escolhido com todas as informações do período selecionado.</p>
          </div>
        </div>
      </div>

      <div class="modal fade" id="portalExportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Exportar propriedades para portal</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
              <form id="portalExportForm">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Portal</label>
                    <select class="form-select" name="portal" id="portalSelect">
                      <option value="zap">ZAP</option>
                      <option value="olx">OLX</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Formato</label>
                    <select class="form-select" name="format" id="formatSelect">
                      <option value="xml">XML</option>
                      <option value="json">JSON</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Limite de registros</label>
                    <input class="form-control" type="number" name="limit" id="limitInput" value="500" min="1">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Chave / Token (opcional)</label>
                    <input class="form-control" type="text" name="api_key" id="apiKeyInput" placeholder="Token para push / autenticação">
                  </div>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" value="1" id="pushCheckbox" name="push">
                      <label class="form-check-label" for="pushCheckbox">Tentar enviar (push) após gerar feed</label>
                    </div>
                  </div>
                  <div class="col-12">
                    <small class="text-muted">Observação: o envio usará o endpoint informado. Caso vazio, apenas será gerado o feed e registrado no log.</small>
                  </div>
                </div>
              </form>
              <div id="portalExportResult" class="mt-3" style="max-height:240px;overflow:auto;display:none"></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
              <button type="button" id="portalExportRun" class="btn btn-primary">Executar export</button>
            </div>
          </div>
        </div>
      </div>

      <script src="/assets/js/panel-portal-export.js" defer></script>
      <script>
        // Atualiza o total de leads no dashboard (card)
        async function atualizarContadorLeadsDashboard() {
          try {
            const res = await fetch('/api/v1/leads/getLeads.php?page=1&limit=1');
            const result = await res.json();
            const total = (result.success && result.pagination) ? result.pagination.total_records : 0;
            const elem = document.getElementById('total-leads-dashboard');
            if (elem) elem.textContent = total;
          } catch (e) {
            const elem = document.getElementById('total-leads-dashboard');
            if (elem) elem.textContent = '0';
          }
        }

        // Carrega estatísticas de visitas do site
        async function carregarEstatisticasVisitas() {
          try {
            const res = await fetch('/api/v1/reports/getVisitStats.php?period=30');
            const result = await res.json();

            if (result.success) {
              const elemVisits = document.getElementById('total-visits');
              const elemChange = document.getElementById('visits-change');

              if (elemVisits) {
                elemVisits.textContent = result.data.total_visits.toLocaleString('pt-BR');
              }

              if (elemChange && result.data.percent_change) {
                const change = result.data.percent_change;
                const icon = change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                const color = change >= 0 ? 'text-green-500' : 'text-red-500';
                elemChange.innerHTML = `<i class="fas ${icon} mr-1"></i> ${Math.abs(change)}%`;
                elemChange.className = `${color} flex items-center text-sm`;
              }
            }
          } catch (e) {
            const elem = document.getElementById('total-visits');
            if (elem) elem.textContent = '0';
          }
        }
      </script>
      <script src="assets/js/agendamentos-aviso.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
      <script>
        // Carregar métricas do Pipeline
        async function carregarMetricasPipeline() {
          try {
            const response = await fetch('/api/v1/pipeline/getMetrics.php');
            const data = await response.json();

            if (data.success) {
              const metrics = data.data;

              // Formatar valores monetários
              const formatarPreco = (valor) => {
                return new Intl.NumberFormat('pt-BR', {
                  style: 'currency',
                  currency: 'BRL',
                  minimumFractionDigits: 0,
                  maximumFractionDigits: 0
                }).format(valor);
              };

              // Atualizar elementos (com verificação de null)
              const totalValue = document.getElementById('pipeline-total-value');
              if (totalValue) totalValue.textContent = formatarPreco(metrics.total_value);

              const totalItems = document.getElementById('pipeline-total-items');
              if (totalItems) totalItems.textContent = metrics.total_items;

              const avgValue = document.getElementById('pipeline-avg-value');
              if (avgValue) avgValue.textContent = formatarPreco(metrics.average_value);

              const stagesCount = document.getElementById('pipeline-stages-count');
              if (stagesCount) stagesCount.textContent = Object.keys(metrics.by_stage).length;

              // Mostrar métricas e esconder loading
              const loading = document.getElementById('pipeline-metrics-loading');
              if (loading) loading.style.display = 'none';

              const metrics_block = document.getElementById('pipeline-metrics');
              if (metrics_block) metrics_block.style.display = 'block';
            }
          } catch (error) {
            console.error('Erro ao carregar métricas do pipeline:', error);
            const loading = document.getElementById('pipeline-metrics-loading');
            if (loading) loading.textContent = 'Erro ao carregar';
          }
        }
      </script>
      <script>
        // Variável global para verificar se usuário é admin
        const isAdmin = <?php echo json_encode($isAdmin); ?>;

        // Desregistra qualquer Service Worker ativo (Push Notifications foram removidas)
        if ('serviceWorker' in navigator) {
          navigator.serviceWorker.getRegistrations().then(registrations => {
            for (let registration of registrations) {
              registration.unregister().then(success => {
                // Silent unregister
              });
            }
          });
        }
      </script>
      <script src="assets/js/painel/integrations.js" defer></script>
      <script src="assets/js/painel/tab-controller.js" defer></script>
      <script src="assets/js/painel/pipeline.js" defer></script>

      <!-- Session Keep-Alive: Mantém a sessão ativa enquanto usuário interage -->
      <script src="/assets/js/session-keepalive.js" defer></script>

      <!-- Phase 9.3: Push Notifications -->
      <!-- COMENTADO: sw-bootstrap.js já cuida do Service Worker registration
      <!-- Push Notifications removidas do sistema em 2026-02-10 -->



</body>

</html>