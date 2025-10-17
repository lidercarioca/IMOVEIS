<?php
session_start();
require_once 'app/security/CSRF.php';

// Headers de segurança
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://polyfill.io; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; connect-src 'self' https://ka-f.fontawesome.com https://cdnjs.cloudflare.com https://*.jsdelivr.net https://cdn.jsdelivr.net https://polyfill.io;");

// Gera novo token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'auth.php';
checkAuth();

// Verificar permissões baseadas na role do usuário
$isAdmin = isAdmin();
$currentTab = $_GET['tab'] ?? 'properties';

// Lista de abas permitidas para usuários comuns
$allowedUserTabs = ['properties', 'add-property', 'leads'];

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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <!-- Meta tags para compatibilidade mobile -->
  <meta name="format-detection" content="telephone=no">
  <meta name="theme-color" content="#1e40af">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

  <!-- CSS Crítico - DEVE ser o primeiro CSS carregado -->
  <style>
    :root {
      --cor-primaria: #1e40af;
      --cor-primaria-rgb: 37, 99, 235;
      --cor-secundaria: #10b981;
      --cor-secundaria-rgb: 16, 185, 129;
      --cor-destaque: #f59e0b;
      --cor-destaque-rgb: 245, 158, 11;
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
    .bg-primary { background-color: var(--cor-primaria) !important; }
    .text-primary { color: var(--cor-primaria) !important; }
    .border-primary { border-color: var(--cor-primaria) !important; }
    .btn-primary { background-color: var(--cor-primaria) !important; border-color: var(--cor-primaria) !important; }
    
    /* Estilos críticos para o sidebar e elementos principais */
    .sidebar { background-color: var(--cor-primaria) !important; }
    .navbar { background-color: var(--cor-primaria) !important; }
    .nav-link.active { background-color: var(--cor-primaria) !important; }
    .btn-outline-primary { color: var(--cor-primaria) !important; border-color: var(--cor-primaria) !important; }
    .btn-outline-primary:hover { background-color: var(--cor-primaria) !important; color: #fff !important; }
    
    /* Aplica imediatamente para elementos do sistema */
    body { visibility: visible !important; }
    #sidebar { background-color: var(--cor-primaria) !important; }
    #content { background-color: #f8fafc !important; }
    
    /* Força cores personalizadas em elementos específicos */
    [style*="background-color: var(--cor-primaria)"] { background-color: var(--cor-primaria) !important; }
    [style*="color: var(--cor-primaria)"] { color: var(--cor-primaria) !important; }
    
    /* Ajustes de performance */
    * { backface-visibility: hidden; -webkit-backface-visibility: hidden; }
    body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
  </style>

  <!-- Preload de recursos críticos -->
  <link rel="preload" href="/assets/css/variables.css" as="style">
  <link rel="preload" href="/assets/css/sidebar.css" as="style">
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
  <script src="/assets/js/notifications.js" defer></script>

  <!-- Tailwind CSS -->
  <link href="/output.css" rel="stylesheet">

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
      background-color: #1e40af;
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
  </style>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <meta name="theme-color" content="#1e40af">
  <meta name="description" content="Painel Administrativo DJ Imóveis - Gerencie seus imóveis de forma eficiente">
  <title>DJ Imóveis - Painel Administrativo</title>



  <!-- Preload de recursos críticos -->
  <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" crossorigin="anonymous">
  
  
  <!-- PWA Support -->
  <link rel="manifest" href="manifest.json">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <link rel="apple-touch-icon" href="assets/imagens/icon-192x192.png">

  <!-- Gerenciador de cores -->
  <script src="assets/js/color-manager.js"></script>

  <!-- Font Awesome (ícones) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer" />

  <script>
    window.isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

    /**
 * Alterna o status de um lead entre diferentes estados
 * @param {HTMLElement} element - Elemento HTML que disparou a ação
 */
function toggleLeadStatus(element) {
      // Encontra o menu dentro do container relativo
      const container = element.closest('.relative');
      const menu = container.querySelector('.lead-status-menu');
      const allMenus = document.querySelectorAll('.lead-status-menu');

      // Fecha todos os outros menus
      allMenus.forEach(m => {
        if (m !== menu) {
          m.classList.add('hidden');
          m.style.opacity = '0';
        }
      });

      // Toggle do menu atual
      if (menu.classList.contains('hidden')) {
        menu.classList.remove('hidden');
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
        }, 200);
      }

      // Click fora para fechar
      const closeMenu = (e) => {
        if (!container.contains(e.target)) {
          menu.style.opacity = '0';
          menu.style.transform = 'translateY(-10px)';
          setTimeout(() => {
            menu.classList.add('hidden');
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

    // Event delegation para os botões de status
    document.addEventListener('click', function(e) {
      const option = e.target.closest('.lead-status-option');
      if (option) {
        const newStatus = option.dataset.value;
        const menu = option.closest('.lead-status-menu');
        const container = menu.closest('.relative');
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
        const response = await fetch('api/updateLead.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
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
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

    body {
      font-family: var(--fonte-principal, 'Poppins', sans-serif);
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
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .property-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    /* Estilos para visualização em grade */
    .grid-view .property-card {
      display: flex;
      flex-direction: column;
    }

    .grid-view .property-image-container img {
      width: 100%;
      height: 200px;
      object-fit: cover;
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
  </style>
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
            <img id="company-logo-painel" src="assets/imagens/logo/logo.png" alt="Logo RR Imóveis" class="h-8 w-8 object-contain mr-2" onerror="this.style.display='none'">
            <span class="text-xl font-bold" id="company-name">Carregando...</span>
          </div>
          <button class="text-white bg-black hover:bg-gray-800 focus:outline-none p-1.5 rounded-md transition-colors shadow-sm" id="toggle-sidebar">
            <i class="fas fa-bars text-sm"></i>
          </button>
        </div>
        <nav>
          <ul class="space-y-2">
            <li>
              <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                href="#dashboard">
                <i class="fas fa-tachometer-alt"></i>
                <span class="sidebar-text">Dashboard</span>
              </a>
            </li>
            <li>
              <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                href="#properties">
                <i class="fas fa-home"></i>
                <span class="sidebar-text">Imóveis</span>
              </a>
            </li>
            <li>
              <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                href="#add-property">
                <i class="fas fa-plus-circle"></i>
                <span class="sidebar-text">Adicionar Imóvel</span>
              </a>
            </li>
            <li>
              <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                href="#leads">
                <i class="fas fa-user-friends"></i>
                <span class="sidebar-text">Leads</span>
              </a>
            </li>
            <?php if ($isAdmin): ?>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#settings">
                  <i class="fas fa-cog"></i>
                  <span class="sidebar-text">Configurações</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#users">
                  <i class="fas fa-users-cog"></i>
                  <span class="sidebar-text">Usuários</span>
                </a>
              </li>
              <li>
                <a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link"
                  href="#backup">
                  <i class="fas fa-database"></i>
                  <span class="sidebar-text">Backup</span>
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
      <?php if ($isAdmin): ?>
      <?php endif; ?>
      <!-- Top Bar -->
      <div class="bg-white shadow-sm sticky top-0 z-20">
        <div class="flex justify-between items-center px-6 py-3">
          <div class="flex items-center space-x-4">
            <h1 class="text-xl font-semibold text-gray-800">Painel Administrativo</h1>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Botão de Notificações -->
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
                <div class="p-4 border-t text-center">
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
            <a class="text-blue-600 hover:text-blue-800 transition" href="/" target="_blank" rel="noopener noreferrer" id="view-site-btn">
              <i class="fas fa-external-link-alt mr-1"></i> Ver Site
            </a>
          </div>
        </div>
      </div>
      <?php if ($isAdmin): ?>
        <div class="tab-content p-6 hidden" id="users">
          <div id="users-dynamic-content">
            <!-- Conteúdo de gerenciamento de usuários será carregado aqui -->
          </div>
        </div>

        <!-- Backup Tab -->
        <div class="tab-content p-6 hidden" id="backup">
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
      <!-- Dashboard Tab -->
      <div class="tab-content active p-6" id="dashboard">
        <div class="mb-8">
          <h2 class="text-2xl font-bold text-gray-800 mb-4">Dashboard</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-500 mb-1">Total de Imóveis</p>
                  <h3 class="text-3xl font-bold text-gray-800" id="total-properties">0</h3>
                </div>
                <div class="rounded-full p-3" style="background-color: rgba(var(--cor-primaria-rgb), 0.1);">
                  <i class="fas fa-home" style="color: var(--cor-primaria);"></i>
                </div>
              </div>
              <div class="flex items-center mt-4">
                <span class="text-green-500 flex items-center text-sm">
                  <i class="fas fa-arrow-up mr-1"></i> 12%
                </span>
                <span class="text-gray-500 text-sm ml-2">Desde o mês passado</span>
              </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-500 mb-1">Leads</p>
                  <h3 class="text-3xl font-bold text-gray-800" id="total-leads-dashboard">0</h3>
                </div>
                <div class="rounded-full p-3" style="background-color: rgba(var(--cor-secundaria-rgb), 0.1);">
                  <i class="fas fa-user-friends" style="color: var(--cor-secundaria);"></i>
                </div>
              </div>
              <div class="flex items-center mt-4">
                <span class="text-green-500 flex items-center text-sm">
                  <i class="fas fa-arrow-up mr-1"></i> 18%
                </span>
                <span class="text-gray-500 text-sm ml-2">Desde o mês passado</span>
              </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-500 mb-1">Visitas</p>
                  <h3 class="text-3xl font-bold text-gray-800">1,254</h3>
                </div>
                <div class="rounded-full p-3" style="background-color: rgba(var(--cor-destaque-rgb), 0.1);">
                  <i class="fas fa-eye" style="color: var(--cor-destaque);"></i>
                </div>
              </div>
              <div class="flex items-center mt-4">
                <span class="text-green-500 flex items-center text-sm">
                  <i class="fas fa-arrow-up mr-1"></i> 5%
                </span>
                <span class="text-gray-500 text-sm ml-2">Desde o mês passado</span>
              </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6">
              <div class="flex justify-between items-start">
                <div>
                  <p class="text-gray-500 mb-1">Vendas/Aluguéis</p>
                  <h3 class="text-3xl font-bold text-gray-800" id="total-sold-properties">0</h3>
                </div>
                <div class="rounded-full p-3" style="background-color: rgba(var(--cor-primaria-rgb), 0.1);">
                  <i class="fas fa-handshake" style="color: var(--cor-primaria);"></i>
                </div>
              </div>
              <div class="flex items-center mt-4">
                <span class="text-gray-500 text-sm ml-2">Atualizado em tempo real</span>
              </div>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm p-6 performance-section">
              <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-semibold text-gray-800">Desempenho de Imóveis</h3>
                <select
                  id="performance-period-select"
                  data-section="performance"
                  class="px-3 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                  <option>Últimos 7 dias</option>
                  <option>Últimos 30 dias</option>
                  <option>Últimos 90 dias</option>
                </select>
              </div>
              <div id="performance-chart" class="h-64">
                <div class="flex h-full items-end justify-between px-2">
                  <!-- Barras do gráfico serão inseridas aqui via JavaScript -->
                </div>
                <div class="flex justify-between mt-2">
                  <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-blue-500 mr-2"></div>
                    <span class="text-xs text-gray-600">Contatos/Leads</span>
                  </div>
                  <div class="flex items-center mx-4">
                    <div class="w-3 h-3 rounded-full bg-green-500 mr-2"></div>
                    <span class="text-xs text-gray-600">Novos Imóveis</span>
                  </div>
                  <div class="flex items-center">
                    <div class="w-3 h-3 rounded-full bg-yellow-500 mr-2"></div>
                    <span class="text-xs text-gray-600">Negócios Fechados</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div>
            <div class="bg-white rounded-lg shadow-sm p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-6">Imóveis por Categoria</h3>
              <div class="space-y-4" id="property-categories">
                <!-- Categories will be loaded here -->
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
            <i class="fas fa-plus mr-2"></i> Adicionar Imóvellll
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
              <button id="grid-view-btn" class="p-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition view-btn bg-blue-600 text-white">
                <i class="fas fa-th-large text-white"></i>
              </button>
            </div>
          </div>
          <div class="grid grid-view" id="properties-container">
            <!-- Property cards will be loaded here -->
          </div>
          <div class="mt-8 flex justify-between items-center">
            <p class="text-sm text-gray-600" id="properties-count">Mostrando 0 imóveis</p>
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
            <button
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
                  class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-full md:w-64"
                  placeholder="Buscar leads..." type="text" />
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
              </div>
              <select
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option>Todos os status</option>
                <option>Novo</option>
                <option>Em contato</option>
                <option>Negociando</option>
                <option>Fechado</option>
                <option>Cancelado</option>
              </select>
              <select
                class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option>Todos os períodos</option>
                <option>Hoje</option>
                <option>Esta semana</option>
                <option>Este mês</option>
                <option>Este ano</option>
              </select>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead>
                <tr>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <div class="flex items-center">
                      <input id="select-all-leads" class="mr-2" type="checkbox" />
                      Nome
                    </div>
                  </th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Contato</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Interesse</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Data</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
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
            <!-- Adicionando o script de validação -->
            <script src="/assets/js/leads-manager.js"></script>
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
      <!-- Settings Tab -->
      <?php if ($isAdmin): ?>
        <div class="tab-content p-6" id="settings">
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
          <!-- Removido carregamento duplicado de Font Awesome e Tailwind -->
          <style>
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

            body {
              font-family: var(--fonte-principal, 'Poppins', sans-serif);
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




          // Salvar e carregar configurações da empresa (todos os campos)
          /**
 * Carrega as configurações da empresa do servidor
 * e preenche o formulário de configurações
 */
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
          document.getElementById('company-color1').value = data.color1 || '#2563eb';
          document.getElementById('company-color2').value = data.color2 || '#10b981';
          document.getElementById('company-color3').value = data.color3 || '#f59e0b';
          document.getElementById('company_font').value = data.font || '';
          }

          document.getElementById('company_settings_form').addEventListener('submit', function (e) {
          e.preventDefault();
          const formData = {
          name: document.getElementById('company_name').value,
          email: document.getElementById('company_email').value,
          email2: document.getElementById('company_email2').value,
          phone: document.getElementById('company_phone').value,
          whatsapp: document.getElementById('company_whatsapp').value,
          address: document.getElementById('company-address').value,
          description: document.getElementById('company-description').value,
          facebook: document.getElementById('company-facebook').value,
          instagram: document.getElementById('company-instagram').value,
          linkedin: document.getElementById('company-linkedin').value,
          youtube: document.getElementById('company-youtube').value,
          logo: document.getElementById('company-logo').value,
          color1: document.getElementById('company-color1').value,
          color2: document.getElementById('company-color2').value,
          color3: document.getElementById('company-color3').value,
          font: document.getElementById('company-font').value
          };
          localStorage.setItem('companySettings', JSON.stringify(formData));
          // ...
          });

          // Carregar ao ativar a aba de configurações
          document.addEventListener('DOMContentLoaded', function () {
          const settingsTabBtn = document.querySelector('.tab-link[href="#settings"]');
          if (settingsTabBtn) {
          settingsTabBtn.addEventListener('click', function () {
          setTimeout(carregarCompanySettings, 100);
          });
          }
          if (document.getElementById('settings').classList.contains('active')) {
          setTimeout(carregarCompanySettings, 100);
          }
          });

          // Botão cancelar limpa o formulário para o último salvo
          document.getElementById('cancelar-config-btn').addEventListener('click', async function () {
          if (confirm('Deseja realmente cancelar as alterações? Todas as mudanças não salvas serão perdidas.')) {
          // Recarrega as configurações do servidor
          await carregarConfiguracoesDoServidor();

          // Exibe mensagem de feedback
          const infoEl = document.getElementById('settings-info') || document.createElement('div');
          infoEl.id = 'settings-info';
          infoEl.className = 'mt-4 p-4 bg-blue-50 text-blue-700 rounded-lg';
          infoEl.textContent = 'As alterações foram canceladas e as configurações foram restauradas.';

          // Adiciona a mensagem ao formulário se ela não existir
          const form = document.getElementById('company_settings_form');
          if (form && !document.getElementById('settings_info')) {
          form.appendChild(infoEl);

          // Remove a mensagem após 3 segundos
          setTimeout(() => {
          infoEl.remove();
          }, 3000);
          }
          }
          });

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
        // Load recent properties for dashboard
        /**
 * Carrega os imóveis mais recentes do servidor
 * e atualiza a seção de imóveis recentes
 */
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


        /**
 * Carrega a grade de imóveis do servidor
 * e atualiza a exibição na interface
 */
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
          background: #2563eb;
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
        .property-ribbon-painel.orange { background: #f59e0b; }
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



        // Função movida para assets/js/painel/painel-main.js

        // Load leads
        /**
 * Carrega a lista de leads do servidor
 * e atualiza a tabela de leads na interface
 * @returns {Promise<void>}
 */
async function loadLeads() {
          const container = document.getElementById('leads-container');
          container.innerHTML = '';
          // Atualiza contador de leads
          const leadsCountElem = document.querySelector('#leads .text-sm.text-gray-600');
          try {
            const res = await fetch('api/getLeads.php');
            const response = await res.json();
            if (!response.success) {
              throw new Error(response.message || 'Erro ao carregar leads');
            }
            const leads = response.data;
            window.leadsData = leads;
            // Atualiza contador de leads
            if (leadsCountElem) {
              leadsCountElem.textContent = `Mostrando ${Array.isArray(leads) ? leads.length : 0} leads`;
            }
            if (!Array.isArray(leads) || !leads.length) {
              container.innerHTML = `<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">Nenhum lead cadastrado ainda.</td></tr>`;
              return;
            }
            leads.forEach(lead => {
              const row = document.createElement('tr');
              row.dataset.leadId = lead.id; // Adiciona o ID do lead à linha
              // Mostra só o início da mensagem (primeiros 50 caracteres)
              let msgPreview = '';
              if (lead.message) {
                const cleanMsg = lead.message.replace(/\n/g, ' ');
                msgPreview = cleanMsg.length > 50 ? cleanMsg.substring(0, 50) + '…' : cleanMsg;
              }
              row.innerHTML = `
          <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
              <input type="checkbox" class="mr-3 lead-checkbox" data-lead-id="${lead.id}">
              <div><div class="text-sm font-medium text-gray-900">${lead.name || ''}</div></div>
            </div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm text-gray-900">${lead.email || ''}</div>
            <div class="text-sm text-gray-500">${lead.phone || ''}</div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm text-gray-900">${msgPreview}</div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <div class="text-sm text-gray-900">${lead.created_at ? new Date(lead.created_at).toLocaleString('pt-BR') : ''}</div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap">
            <div class="relative inline-block">
              <div class="lead-status ${getLeadStatusClass(lead.status)}" data-id="${lead.id}" data-value="${lead.status}" onclick="toggleLeadStatus(this)" style="min-width: 120px;">
                <i class="${getLeadStatusIcon(lead.status)}"></i>
                <span>${getLeadStatusText(lead.status)}</span>
              </div>
              <div class="lead-status-menu hidden absolute left-0 bg-white shadow-lg rounded-lg mt-1 z-50" style="min-width: 160px;">
              <div class="py-1">
                <button class="w-full text-left px-4 py-2 hover:bg-gray-100 lead-status-option novo" data-value="new">
                  <i class="fas fa-star me-2"></i>Novo
                </button>
                <button class="w-full text-left px-4 py-2 hover:bg-gray-100 lead-status-option em-contato" data-value="contacted">
                  <i class="fas fa-phone me-2"></i>Em contato
                </button>
                <button class="w-full text-left px-4 py-2 hover:bg-gray-100 lead-status-option convertido" data-value="closed">
                  <i class="fas fa-check-circle me-2"></i>Convertido
                </button>
                <button class="w-full text-left px-4 py-2 hover:bg-gray-100 lead-status-option perdido" data-value="cancelled">
                  <i class="fas fa-times-circle me-2"></i>Perdido
                </button>
              </div>
            </div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
            <div class="flex space-x-2">
                <button class="text-blue-600 hover:text-blue-900 btn-ver-lead" data-id="${lead.id}" title="Ver detalhes"><i class="fas fa-eye"></i></button>
              <a class="btn-ver-lead" style="color: var(--cor-secundaria);" title="Enviar e-mail" href="mailto:${lead.email}?subject=Contato RR Imóveis&body=Olá ${lead.name}, vi seu interesse: ${msgPreview}"><i class="fas fa-envelope"></i></a>
              <button class="btn-excluir-lead" style="color: #e53e3e;" data-id="${lead.id}" title="Excluir"><i class="fas fa-trash"></i></button>
            </div>
          </td>
        `;
              container.appendChild(row);
            });
            // Ação de abrir modal de detalhes do lead
            container.querySelectorAll('.btn-ver-lead').forEach(btn => {
              btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const lead = window.leadsData.find(l => String(l.id) === String(id));
                if (!lead) return;
                mostrarModalLead(lead);
              });
            });
            // Ação de exclusão
            container.querySelectorAll('.btn-excluir-lead').forEach(btn => {
              btn.addEventListener('click', async function() {
                if (!confirm('Tem certeza que deseja excluir este lead?')) return;
                const id = this.getAttribute('data-id');
                const res = await fetch(`api/deleteLead.php?id=${id}`);
                const json = await res.json();
                if (json.success) {
                  loadLeads();
                  if (typeof atualizarContadorLeadsDashboard === 'function') atualizarContadorLeadsDashboard();
                } else {
                  // ...
                }
              });
            });
            // Função para mostrar modal de detalhes do lead
            function mostrarModalLead(lead) {
              let modal = document.getElementById('leadModal');
              if (!modal) {
                modal = document.createElement('div');
                modal.id = 'leadModal';
                modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50';
                modal.innerHTML = `
      <div class="bg-white rounded-lg shadow-lg max-w-lg w-full p-6 relative">
        <button class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-2xl font-bold" id="close-lead-modal">&times;</button>
        <h3 class="text-xl font-semibold mb-4">Detalhes do Lead</h3>
        <div id="lead-modal-content"></div>
      </div>
    `;
                document.body.appendChild(modal);
              } else {
                modal.style.display = 'flex';
              }
              // Preencher conteúdo
              const content = modal.querySelector('#lead-modal-content');
              content.innerHTML = `
    <p><strong>Nome:</strong> ${lead.name || ''}</p>
    <p><strong>Email:</strong> ${lead.email || ''}</p>
    <p><strong>Telefone:</strong> ${lead.phone || ''}</p>
    <p><strong>Status:</strong> ${getLeadStatusText(lead.status)}</p>
    <p><strong>Mensagem:</strong><br>${lead.message ? lead.message.replace(/\n/g, '<br>') : ''}</p>
    <p><strong>Data de criação:</strong> ${lead.created_at ? new Date(lead.created_at).toLocaleString('pt-BR') : ''}</p>
    <p><strong>Origem:</strong> ${lead.source || '-'}</p>
    <div class="mt-4">
      <label for="lead-modal-notes" class="block text-sm font-medium text-gray-700 mb-1">Anotações rápidas:</label>
      <textarea id="lead-modal-notes" rows="4" class="w-full border border-gray-300 rounded p-2" placeholder="Digite suas anotações aqui...">${lead.notes ? lead.notes.replace(/</g, '&lt;').replace(/>/g, '&gt;') : ''}</textarea>
      <div id="lead-notes-status" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
    </div>
  `;
              // Salvar anotações rápidas ao sair do campo
              const notesTextarea = content.querySelector('#lead-modal-notes');
              const notesStatus = content.querySelector('#lead-notes-status');
              if (notesTextarea) {
                notesTextarea.addEventListener('blur', async function() {
                  const newNotes = notesTextarea.value;
                  notesStatus.style.display = 'block';
                  notesStatus.textContent = 'Salvando...';
                  try {
                    const res = await fetch('api/updateLead.php', {
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
                      // Atualiza o objeto lead em memória (window.leadsData)
                      if (window.leadsData) {
                        const idx = window.leadsData.findIndex(l => String(l.id) === String(lead.id));
                        if (idx !== -1) window.leadsData[idx].notes = newNotes;
                      }
                      // Atualiza o objeto lead do modal atual
                      lead.notes = newNotes;
                    } else {
                      notesStatus.textContent = 'Erro ao salvar anotações.';
                    }
                  } catch (e) {
                    notesStatus.textContent = 'Erro ao salvar anotações.';
                  }
                });
              }
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
            // Ação de atualização de status
            container.querySelectorAll('.lead-status-select').forEach(sel => {
              sel.addEventListener('change', async function() {
                const id = this.getAttribute('data-id');
                const status = this.value;
                const res = await fetch('api/updateLead.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify({
                    id,
                    status
                  })
                });
                const json = await res.json();
                if (json.success) {
                  loadLeads();
                  if (typeof atualizarContadorLeadsDashboard === 'function') atualizarContadorLeadsDashboard();
                } else {
                  // ...
                }
              });
            });
          } catch (err) {
            if (leadsCountElem) leadsCountElem.textContent = 'Mostrando 0 leads';
            container.innerHTML = `<tr><td colspan='6' class='text-center text-red-500'>Erro ao carregar leads.</td></tr>`;
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

            const res = await fetch('api/deleteProperty.php', {
              method: 'POST',
              body: formData
            });

            const result = await res.json();

            if (!result.success) {
              throw new Error(result.message || 'Erro ao excluir imóvel.');
            }

            if (typeof carregarImoveisPainel === 'function') carregarImoveisPainel();
            updateDashboardCounts();
          } catch (err) {
            // ...
            // ...
          }
        }

        // Atualiza o total de imóveis e vendidos/alugados no dashboard
        function updateDashboardCounts() {
          fetch('api/getProperties.php')
            .then(res => res.json())
            .then(properties => {
              document.getElementById("total-properties").textContent = properties.length;
              // Contar imóveis com status 'VENDA' ou 'ALUGUEL' (case-insensitive)
              const soldCount = Array.isArray(properties) ?
                properties.filter(p => {
                  if (!p.status) return false;
                  const status = String(p.status).toLowerCase();
                  return status === 'vendido' || status === 'alugado';
                }).length :
                0;
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
        /* function getStatusText(status) {
           const statuses = {
             'active': 'Ativo',
             'pending': 'Pendente',
             'sold': 'Vendido',
             'rented': 'Alugado',
             'inactive': 'Inativo'
           };
           return statuses[String(status).toLowerCase()] || status;
         }
         function getStatusClass(status) {
           // Normaliza para minúsculo e aceita português e inglês
           if (!status) return 'badge-status status-inactive';
           const map = {
             'ativo': 'active',
             'active': 'active',
             'pendente': 'pending',
             'pending': 'pending',
             'vendido': 'sold',
             'sold': 'sold',
             'alugado': 'rented',
             'rented': 'rented',
             'inativo': 'inactive',
             'inactive': 'inactive'
           };
           const key = map[String(status).toLowerCase()] || 'inactive';
           const classes = {
             active: 'badge-status status-active',
             pending: 'badge-status status-pending',
             sold: 'badge-status status-sold',
             rented: 'badge-status status-rented',
             inactive: 'badge-status status-inactive'
           };
           return classes[key] || 'badge-status status-inactive';
         }*/

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

                // Toggle sidebar com performance otimizada
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const toggleBtn = document.getElementById('toggle-sidebar');
        
        // Previne transições durante o carregamento inicial
        document.body.classList.add('preload');
        window.addEventListener('load', () => {
            document.body.classList.remove('preload');
        });
        
        toggleBtn.addEventListener('click', function() {
          const sidebar = document.getElementById('sidebar');
          const mainContent = document.getElementById('main-content');
          const logoText = document.getElementById('logo-text');
          const sidebarTexts = document.querySelectorAll('.sidebar-text');

          if (sidebar.classList.contains('sidebar-expanded')) {
            sidebar.classList.remove('sidebar-expanded');
            sidebar.classList.add('sidebar-collapsed');
            mainContent.classList.remove('main-content-expanded');
            mainContent.classList.add('main-content-collapsed');
            logoText.style.display = 'none';
            sidebarTexts.forEach(text => text.style.display = 'none');
          } else {
            sidebar.classList.remove('sidebar-collapsed');
            sidebar.classList.add('sidebar-expanded');
            mainContent.classList.remove('main-content-collapsed');
            mainContent.classList.add('main-content-expanded');
            logoText.style.display = 'block';
            sidebarTexts.forEach(text => text.style.display = 'block');
          }
        });


        // Navegação por abas
        document.querySelectorAll('.tab-link').forEach(tab => {
          tab.addEventListener('click', function(e) {
            e.preventDefault();

            // Trocar aba ativa
            document.querySelectorAll('.tab-link').forEach(t => {
              t.classList.remove('bg-blue-700');
              t.classList.add('hover:bg-blue-600');
            });
            this.classList.add('bg-blue-700');
            this.classList.remove('hover:bg-blue-600');

            // Mostrar conteúdo da aba
            const targetId = this.getAttribute('href').substring(1);
            document.querySelectorAll('.tab-content').forEach(content => {
              content.classList.remove('active');
            });
            document.getElementById(targetId).classList.add('active');

            // Carregar dados conforme aba
            if (targetId === 'properties') {
              carregarImoveisPainel();
            } else if (targetId === 'leads') {
              loadLeads();
            } else if (targetId === 'settings') {
              carregarConfigsDinamicamente();
            } else if (targetId === 'add-property') {
              carregarFormularioImovel();
            } else if (targetId === 'users') {
              carregarUsers();
            }
          });
        });

        // Função para carregar a aba de usuários
        async function carregarUsers() {
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

        // Função para carregar o formulário de adicionar/editar imóvel
        async function carregarFormularioImovel() {
          const container = document.getElementById('add-property');
          if (!container || container.dataset.loaded === 'true') return;

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
          } catch (err) {
            container.innerHTML = '<div class="text-red-500 text-center">Erro ao carregar formulário de imóvel</div>';
            // ...
          }
        }

        // Carregar configs.php dinamicamente na aba Configurações
        async function carregarConfigsDinamicamente() {
          const container = document.getElementById('settings-dynamic-content');
          if (!container) return;
          container.innerHTML = '<div class="flex flex-col items-center justify-center min-h-[200px] text-gray-500"><i class="fas fa-cog text-4xl mb-4 animate-spin"></i><p>Carregando configurações...</p></div>';
          try {
            const res = await fetch('configs.php');
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

            // Inicializa as configurações
            if (typeof initializeSettings === 'function') {
              initializeSettings();
            } else {
              // ...
              throw new Error('Erro ao inicializar configurações');
            }
          } catch (err) {
            // ...
            container.innerHTML = `<div class="text-red-500">Erro ao carregar configurações: ${err.message}</div>`;
          }
        }

        // Carregar configs ao abrir a página se a aba já estiver ativa
        document.addEventListener('DOMContentLoaded', function() {
          const settingsTab = document.getElementById('settings');
          if (settingsTab && settingsTab.classList.contains('active')) {
            carregarConfigsDinamicamente();
          }
        });


        // Salva configurações da empresa no localStorage
        //const formData = new FormData();

        //localStorage.setItem('companySettings', JSON.stringify(formData));
        // ...

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

        // Inicialização ao carregar a página
        window.addEventListener('DOMContentLoaded', function() {
          if (typeof atualizarContadorLeadsDashboard === 'function') atualizarContadorLeadsDashboard();
          // ⚠️ Só chama funções se existirem
          if (typeof carregarImoveisPainel === 'function') carregarImoveisPainel();
          if (typeof loadRecentProperties === 'function') loadRecentProperties();
          if (typeof loadPropertyCategories === 'function') loadPropertyCategories();
          if (typeof loadLeads === 'function') loadLeads();
          if (typeof updateDashboardCounts === 'function') updateDashboardCounts();
        });
      </script>

      <script>
        // Função auxiliar para obter número de quartos
        function obterQtdQuartos(features) {
          if (!features) return 0;
          return Array.isArray(features) ? features.filter(f => f.includes('quarto')).length : 0;
        }

        // Função auxiliar para obter número de banheiros
        function obterQtdBanheiros(features) {
          if (!features) return 0;
          return Array.isArray(features) ? features.filter(f => f.includes('banheiro')).length : 0;
        }

        // Função auxiliar para obter classe CSS do status
        /* function getStatusClass(status) {
           const classes = {
             'active': 'bg-green-100 text-green-800',
             'pending': 'bg-yellow-100 text-yellow-800',
             'inactive': 'bg-red-100 text-red-800',
             'vendido': 'bg-blue-100 text-blue-800',
             'alugado': 'bg-purple-100 text-purple-800'
           };
           return classes[status] || 'bg-gray-100 text-gray-800';
         }

         // Função auxiliar para obter texto do status
         function getStatusText(status) {
           const texts = {
             'active': 'Ativo',
             'pending': 'Pendente',
             'inactive': 'Inativo',
             'vendido': 'Vendido',
             'alugado': 'Alugado'
           };
           return texts[status] || status;
         }*/

        document.addEventListener('DOMContentLoaded', function() {
          // Carrega os imóveis ao inicializar a página
          if (typeof window.carregarImoveisPainel === 'function') {
            window.carregarImoveisPainel();
          }

          // Adiciona listeners aos botões de tab
          document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function(e) {
              const target = this.getAttribute('href');
              if (target === '#imoveis' && typeof carregarImoveisPainel === 'function') {
                carregarImoveisPainel();
              }
            });
          });
        });

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

      <!-- Scripts da aplicação -->
      <!-- Utils primeiro, pois outros scripts dependem dele -->
      <script src="/assets/js/utils/browser-support.js"></script>
      <script src="/assets/js/utils/utils.js"></script>
      <script src="/assets/js/utils/color-utils.js"></script>
      <script src="/assets/js/painel/ui-manager.js"></script>

      <!-- Scripts específicos do painel -->
      <script src="/assets/js/painel/settings.js"></script>
      <script src="/assets/js/painel/painel-main.js"></script>
      <script src="/assets/js/painel/performance-chart.js"></script>
      <script src="/assets/js/painel/users.js"></script>
      <script src="/assets/js/public/card-color-sync.js"></script>
      <script src="/assets/js/painel/filtro-painel.js"></script>
      <script src="/assets/js/visual_lista_grade.js"></script>
      <script src="/assets/js/company-description.js"></script>
      <script src="/assets/js/public/company-name.js"></script>

      <!-- Script do formulário -->
      <script src="assets/js/painel/adicionar-imovel.js"></script>

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
            if (!['Poppins', 'Arial', 'sans-serif'].includes(data.company_font)) {
              const link = document.createElement('link');
              link.rel = 'stylesheet';
              link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(data.company_font)}:wght@300;400;500;600;700&display=swap`;
              document.head.appendChild(link);
            }
          }
          const root = document.documentElement;
          root.style.setProperty('--color-primary', data.company_color1 || '#2563eb');
          root.style.setProperty('--color-secondary', data.company_color2 || '#10b981');
          root.style.setProperty('--color-accent', data.company_color3 || '#f59e0b');
          document.querySelectorAll('.bg-blue-600').forEach(el => el.style.backgroundColor = data.company_color1 || '#2563eb');
          document.querySelectorAll('.bg-green-600').forEach(el => el.style.backgroundColor = data.company_color2 || '#10b981');
          document.querySelectorAll('.bg-orange-500').forEach(el => el.style.backgroundColor = data.company_color3 || '#f59e0b');
          document.querySelectorAll('.text-blue-600').forEach(el => el.style.color = data.company_color1 || '#2563eb');
          document.querySelectorAll('.text-green-600').forEach(el => el.style.color = data.company_color2 || '#10b981');
          document.querySelectorAll('.text-orange-500').forEach(el => el.style.color = data.company_color3 || '#f59e0b');
        }

        document.addEventListener('DOMContentLoaded', async function() {
          try {
            const res = await fetch('api/getCompanySettings.php');
            const json = await res.json();
            if (!json.success || !json.data) return;
            aplicarCoresPainel(json.data);
          } catch (e) {}

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
      </script>
      <script>
        // Atualiza o total de leads no dashboard (card)
        async function atualizarContadorLeadsDashboard() {
          try {
            const res = await fetch('api/getLeads.php');
            const result = await res.json();
            const total = (result.success && Array.isArray(result.data)) ? result.data.length : 0;
            const elem = document.getElementById('total-leads-dashboard');
            if (elem) elem.textContent = total;
          } catch (e) {
            const elem = document.getElementById('total-leads-dashboard');
            if (elem) elem.textContent = '0';
          }
        }
      </script>

</body>

</html>