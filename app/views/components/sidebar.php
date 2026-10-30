<?php
/**
 * Sidebar Component
 * Navegação lateral do painel administrativo
 */
?>
<!-- Sidebar Component -->
<div class="sidebar sidebar-expanded text-white fixed h-full z-30" id="sidebar">
  <div class="p-4">
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center space-x-2">
        <img id="company-logo-painel" src="assets/imagens/logo/logo_padrao.png" alt="Logo" class="h-8 w-8 object-contain mr-2" onerror="this.style.display='none'">
        <span class="text-xl font-bold" id="company-name">Carregando...</span>
      </div>
      <button class="text-white bg-black hover:bg-gray-800 focus:outline-none p-1.5 rounded-md transition-colors shadow-sm" id="toggle-sidebar">
        <i class="fas fa-bars text-sm"></i>
      </button>
    </div>
    
    <nav>
      <ul class="space-y-2">
        <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#dashboard"><i class="fas fa-tachometer-alt"></i><span class="sidebar-text">Dashboard</span></a></li>
        <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#properties"><i class="fas fa-home"></i><span class="sidebar-text">Imóveis</span></a></li>
        <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#add-property"><i class="fas fa-plus-circle"></i><span class="sidebar-text">Adicionar Imóvel</span></a></li>
        <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#leads"><i class="fas fa-user-friends"></i><span class="sidebar-text">Leads</span></a></li>
        <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#agendamentos"><i class="fas fa-calendar-alt"></i><span class="sidebar-text">Agendamentos</span></a></li>
        <?php if (!isset($isAdmin) || !$isAdmin): ?>
          <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#pipeline"><i class="fas fa-funnel"></i><span class="sidebar-text">Pipeline</span></a></li>
        <?php endif; ?>
        <?php if (isset($isAdmin) && $isAdmin): ?>
          <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#settings"><i class="fas fa-cog"></i><span class="sidebar-text">Configurações</span></a></li>
          <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#relatorios"><i class="fas fa-chart-bar"></i><span class="sidebar-text">Relatórios</span></a></li>
          <li><a class="d-flex align-items-center gap-3 py-3 px-4 rounded text-white text-decoration-none transition tab-link" href="#financeiro"><i class="fas fa-money-bill-wave"></i><span class="sidebar-text">Financeiro</span></a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
  <div class="position-absolute bottom-0 start-0 end-0 p-4">
    <div class="d-flex align-items-center gap-2 py-3 px-4">
      <div class="d-flex align-items-center justify-content-center rounded-circle bg-primary text-white text-decoration-none flex-shrink-0" style="width: 40px; height: 40px;">
        <i class="fas fa-user-circle"></i>
      </div>
      <div class="flex-grow-1">
        <p class="text-white text-sm mb-0"><?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Usuário'; ?></p>
        <p class="text-gray-300 text-xs mb-0"><?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?></p>
      </div>
      <a href="logout.php" class="text-white hover:text-gray-300 transition">
        <i class="fas fa-sign-out-alt"></i>
      </a>
    </div>
  </div>
</div>
