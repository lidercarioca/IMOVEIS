/**
 * GERENCIADOR CENTRALIZADO DE NAVEGAÇÃO - v2.0
 * ============================================
 * Consolida TODAS as funções de navegação e filtragem de imóveis
 * Implementa smooth scroll, controle de menu mobile e gerenciamento de favoritos
 * 
 * IMPORTANTE: Este arquivo deve ser carregado ANTES de main.js para evitar conflitos
 */

(function() {
  'use strict';

  // ===== CONSTANTES E CONFIGURAÇÃO =====
  const SCROLL_OFFSET = 80; // pixels de offset ao rolar
  const SCROLL_DURATION = 300; // milissegundos da animação suave

  // ===== FUNÇÕES DE FILTRO =====
  
  /**
   * Mostra apenas imóveis favoritos
   */
  window.showFavorites = function() {
    const propertyList = document.getElementById('property-list');
    if (!propertyList) return;

    // Atualiza título da página (procura em ambas as estruturas)
    let pageTitle = document.querySelector('h1.display-4');
    if (pageTitle) {
      pageTitle.textContent = 'Imóveis Favoritos';
    }
    
    // Também tenta atualizar h2 se existir (compatibilidade)
    let sectionTitle = document.querySelector('#properties h2');
    if (sectionTitle) {
      sectionTitle.textContent = 'Meus Favoritos';
    }

    // Acessar favoritesManager com verificação segura
    if (!window.favoritesManager) {
      // Tentar novamente em 100ms
      setTimeout(() => window.showFavorites(), 100);
      return;
    }

    // Remove mensagem de favoritos anterior
    const existingMessage = propertyList.querySelector('.no-favorites-message');
    if (existingMessage) {
      existingMessage.remove();
    }

    // Filtra os cards para mostrar apenas os favoritos
    // Procura por qualquer elemento com data-property-id (compatível com ambas as estruturas)
    const allCards = propertyList.querySelectorAll('[data-property-id]');
    let hasVisibleCards = false;

    allCards.forEach(cardElement => {
      const propertyId = cardElement.getAttribute('data-property-id');

      if (propertyId) {
        if (window.favoritesManager.isFavorite(propertyId)) {
          cardElement.style.display = '';
          hasVisibleCards = true;
        } else {
          cardElement.style.display = 'none';
        }
      }
    });

    // Mostra mensagem se não houver favoritos
    if (!hasVisibleCards) {
      const noFavoritesMessage = document.createElement('div');
      noFavoritesMessage.className = 'no-favorites-message col-12 text-center py-5';
      noFavoritesMessage.innerHTML = `
        <div class="text-muted">
          <i class="fas fa-heart-broken fa-3x mb-3"></i>
          <h3>Nenhum imóvel favorito</h3>
          <p>Você ainda não adicionou nenhum imóvel aos favoritos.</p>
        </div>
      `;
      propertyList.appendChild(noFavoritesMessage);
    }

    // Rola suavemente até a seção de propriedades
    performSmoothScroll('properties');
  };

  /**
   * Mostra todos os imóveis (remove filtro de favoritos)
   */
  window.showAllProperties = function() {
    const propertyList = document.getElementById('property-list');
    if (!propertyList) return;

    // Restaura o título original
    let pageTitle = document.querySelector('h1.display-4');
    if (pageTitle) {
      pageTitle.textContent = 'Todos os Imóveis';
    }

    // Também tenta restaurar h2 (compatibilidade todos-imoveis)
    let sectionTitle = document.querySelector('#properties h2');
    if (sectionTitle) {
      sectionTitle.textContent = 'Imóveis em Destaque';
    }

    // Remove mensagem de "nenhum favorito" se existir
    const noFavoritesMessage = propertyList.querySelector('.no-favorites-message');
    if (noFavoritesMessage) {
      noFavoritesMessage.remove();
    }

    // Mostra todos os cards - força display visível removendo estilo inline
    const allCards = propertyList.querySelectorAll('[data-property-id]');
    allCards.forEach(card => {
      card.style.display = '';
      // Remove classes de hidden se existirem
      card.classList.remove('hidden');
    });

    // Se não houver cards visíveis, recarrega os dados
    if (allCards.length === 0 && typeof window.carregarImoveis === 'function') {
      window.carregarImoveis();
      return;
    }

    // Rola até a seção de propriedades
    performSmoothScroll('properties');
  };

  // ===== FUNÇÕES AUXILIARES DE SCROLL SUAVE =====

  /**
   * Realiza scroll suave para um elemento específico
   */
  function performSmoothScroll(elementId) {
    const element = document.getElementById(elementId);
    if (!element) {
      return;
    }

    try {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
      // Fallback para navegadores antigos
      window.scrollTo({
        top: element.offsetTop - 80,
        behavior: 'smooth'
      });
    }
  }

  // ===== GERENCIAMENTO DE NAVEGAÇÃO =====

  /**
   * Inicializa handlers de navegação
   */
  function initializeNavigation() {
    
    // Handler para botão de menu mobile
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    if (mobileMenuButton) {
      mobileMenuButton.addEventListener('click', function(e) {
        e.stopPropagation();
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenu) {
          mobileMenu.classList.toggle('hidden');
        }
      });
    }

    // Adiciona listeners APENAS aos links que NÃO têm onclick
    setupSmoothScrollLinks();

    // Listeners para reset de favoritos ao navegar
    window.addEventListener('hashchange', function() {
      if (window.location.hash === '#home' || window.location.hash === '') {
        // Resetar para mostrar todos os imóveis
        window.showAllProperties();
      }
    });
    

  }

  /**
   * Setup de smooth scroll para links com âncora (simples e direto)
   */
  function setupSmoothScrollLinks() {
    const linksToHandle = [
      { selector: 'a[href="#home"]:not([onclick])', id: 'home' },
      { selector: 'a[href="#properties"]:not([onclick])', id: 'properties' },
      { selector: 'a[href="#about"]:not([onclick])', id: 'about' },
      { selector: 'a[href="#contact"]:not([onclick])', id: 'contact' }
    ];

    linksToHandle.forEach(({ selector, id }) => {
      const links = document.querySelectorAll(selector);
      
      links.forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          
          performSmoothScroll(id);
          
          // Atualiza hash
          if (window.history && window.history.pushState) {
            window.history.pushState(null, null, '#' + id);
          }
          
          // Fecha menu mobile
          const mobileMenu = document.getElementById('mobile-menu');
          if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
            mobileMenu.classList.add('hidden');
          }
        });
      });
    });
  }

  // Inicializa quando DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeNavigation);
  } else {
    initializeNavigation();
  }

  // Aguarda FavoritesManager estar disponível
  if (typeof window.favoritesManager === 'undefined') {
    let checkCount = 0;
    const waitForFavoritesManager = setInterval(() => {
      if (typeof window.favoritesManager !== 'undefined') {

        clearInterval(waitForFavoritesManager);
      }
      checkCount++;
      if (checkCount > 50) {
        clearInterval(waitForFavoritesManager);
      }
    }, 100);
  }

  // ===== AJUSTE DE TENANT_ID EM LINKS =====

  function getTenantIdFromContext() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('tenant_id')) {
      const id = parseInt(urlParams.get('tenant_id'));
      if (id > 0) return id;
    }

    if (typeof window.TENANT_ID_SERVER !== 'undefined' && window.TENANT_ID_SERVER > 0) {
      return parseInt(window.TENANT_ID_SERVER);
    }

    if (typeof window.SUBDOMAIN_TENANT_ID !== 'undefined' && window.SUBDOMAIN_TENANT_ID > 0) {
      return parseInt(window.SUBDOMAIN_TENANT_ID);
    }

    return 1;
  }

  function adjustRootLinksTenantId() {
    const tenantId = getTenantIdFromContext();
    if (!tenantId || tenantId <= 0) return;

    const anchors = document.querySelectorAll('a[href]');
    const targetPaths = new Set(['/', '/views/public/todos-imoveis.html', '/todos-imoveis.html']);

    anchors.forEach(anchor => {
      const href = anchor.getAttribute('href');
      if (!href) return;

      try {
        const url = new URL(href, window.location.origin);
        if (url.origin === window.location.origin && targetPaths.has(url.pathname)) {
          url.searchParams.set('tenant_id', tenantId);
          anchor.setAttribute('href', url.pathname + url.search);
        }
      } catch (e) {
        // link não-URL válido (âncora, mailto etc.)
      }
    });
  }

  // Ajusta links ao carregar
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', adjustRootLinksTenantId);
  } else {
    adjustRootLinksTenantId();
  }

  // ===== EXPORTAR FUNÇÕES GLOBAIS PARA COMPATIBILIDADE =====
  window.performSmoothScroll = performSmoothScroll;
  window.getTenantIdFromContext = getTenantIdFromContext;
  window.adjustRootLinksTenantId = adjustRootLinksTenantId;



})();
