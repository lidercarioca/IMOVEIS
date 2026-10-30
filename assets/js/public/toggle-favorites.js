/**
 * Este arquivo continha funções duplicadas que foram consolidadas em navigation-handler.js
 * As funções showFavorites() e showAllProperties() agora são gerenciadas
 * centralizadamente em navigation-handler.js para evitar conflitos.
 * 
 * Este arquivo é mantido apenas para compatibilidade retroativa.
 * DEPRECATED: Use window.showFavorites() e window.showAllProperties() do navigation-handler.js
 */

(function() {
  'use strict';

  // Valida se as funções estão disponíveis globalmente
  if (typeof window.showFavorites !== 'function' || typeof window.showAllProperties !== 'function') {
    // Funções em navigation-handler.js
  }
})();

