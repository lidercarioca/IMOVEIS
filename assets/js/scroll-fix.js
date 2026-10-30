/**
 * scroll-fix.js
 * Corrige problemas de scroll bloqueado causados por modals
 * Garante que o scroll funcione corretamente mesmo quando modals estão abertos/fechados
 */

(function() {
  'use strict';

  // Função para garantir que o scroll está ativado
  function enableScroll() {
    document.documentElement.style.overflow = '';
    document.documentElement.style.overscrollBehavior = '';
    document.body.style.overflow = '';
    document.body.style.overscrollBehavior = '';
    document.body.style.paddingRight = '';
    
    // Remove qualquer classe que possa blocar scroll
    document.body.classList.remove('modal-open');
    document.documentElement.classList.remove('modal-open');
  }

  // Função para se certificar de que não há backdrops de modal presos
  function removeStuckBackdrops() {
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
      // Se há um backdrop mas nenhum modal aberto, remova-o
      const openModals = document.querySelectorAll('.modal.show');
      if (openModals.length === 0) {
        backdrop.remove();
      }
    });
  }

  // Monitora mudanças no DOM para detectar modals sendo abertos/fechados
  const observer = new MutationObserver(function(mutations) {
    removeStuckBackdrops();
    
    // Verifica se há modals abertos
    const openModals = document.querySelectorAll('.modal.show');
    
    // Se não há modals abertos, garanta que o scroll esteja habilitado
    if (openModals.length === 0) {
      setTimeout(enableScroll, 100);
    }
  });

  // Inicia observação quando o DOM estiver pronto
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      observer.observe(document.body, {
        attributes: true,
        attributeFilter: ['class'],
        subtree: true,
        childList: true
      });
      
      // Garanta que o scroll funcione no carregamento inicial
      enableScroll();
    });
  } else {
    observer.observe(document.body, {
      attributes: true,
      attributeFilter: ['class'],
      subtree: true,
      childList: true
    });
    enableScroll();
  }

  // Limpa backdrops a cada 500ms para garantir que não fiquem presos
  setInterval(removeStuckBackdrops, 500);

  // Expõe globalmente para debug
  window.scrollFixEnabled = true;
  window.enableScroll = enableScroll;

})();
