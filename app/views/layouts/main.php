<?php
/**
 * Layout Base - app/views/layouts/main.php
 * 
 * Template principal que inclui sidebar, header, footer e renderiza conteúdo
 * Variáveis: $currentTab, $isAdmin, $content
 */
?>
<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="theme-color" content="#111827">
  <title>Painel Administrativo - DJ Imóveis</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/variables.css">
  <link rel="stylesheet" href="/output.css">
  <style>
    :root {
      --cor-primaria: #111827;
      --cor-primaria-rgb: 17, 24, 39;
      --cor-secundaria: #10b981;
      --cor-destaque: #d97706;
    }
    body { background-color: #f8fafc; }
    .sidebar { background-color: var(--cor-primaria); width: 16rem; }
    .main-content { margin-left: 16rem; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
  </style>
</head>
<body class="bg-gray-100">
<div class="flex min-h-screen">
  <!-- Sidebar -->
  <?php include __DIR__ . '/../components/sidebar.php'; ?>
  
  <!-- Main Content -->
  <div class="main-content flex-1">
    <!-- Header -->
    <?php include __DIR__ . '/../components/header.php'; ?>
    
    <!-- Tab Content -->
    <div class="p-6">
      <?php if (isset($content)) echo $content; ?>
    </div>
    
    <!-- Footer -->
    <?php include __DIR__ . '/../components/footer.php'; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/painel/tabs.js"></script>
<script src="/assets/js/painel/sidebar.js"></script>
</body>
</html>
