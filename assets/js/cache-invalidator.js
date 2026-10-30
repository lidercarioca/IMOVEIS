/**
 * Sistema de invalidação de cache entre painel e site público
 * Usa BroadcastChannel para notificar mudanças em tempo real
 */

window.CacheInvalidator = (function() {
  const CHANNEL_NAME = 'data-changes';
  const channel = ('BroadcastChannel' in window) ? new BroadcastChannel(CHANNEL_NAME) : null;
  
  const invalidationCallbacks = {};

  // Listener para mensagens de invalidação
  if (channel) {
    channel.onmessage = function(event) {
      const { type, data } = event.data;
      
      if (invalidationCallbacks[type]) {
        invalidationCallbacks[type].forEach(callback => {
          try {
            callback(data);
          } catch (err) {
            console.error('Erro ao executar callback de invalidação:', err);
          }
        });
      }
    };
  }

  return {
    /**
     * Registra um callback para um tipo de mudança específico
     * @param {string} type - Tipo de mudança (ex: 'properties', 'banners', 'settings')
     * @param {function} callback - Função a executar quando houver mudança
     */
    on: function(type, callback) {
      if (!invalidationCallbacks[type]) {
        invalidationCallbacks[type] = [];
      }
      invalidationCallbacks[type].push(callback);
    },

    /**
     * Notifica uma mudança a todas as abas/janelas
     * @param {string} type - Tipo de mudança
     * @param {object} data - Dados da mudança (opcional)
     */
    notify: function(type, data) {
      if (channel) {
        channel.postMessage({
          type: type,
          data: data || {},
          timestamp: Date.now()
        });
      }
      // Também executa localmente para a mesma aba
      if (invalidationCallbacks[type]) {
        invalidationCallbacks[type].forEach(callback => {
          try {
            callback(data || {});
          } catch (err) {
            console.error('Erro ao executar callback de invalidação:', err);
          }
        });
      }
    },

    /**
     * Remove todos os listeners para um tipo específico
     * @param {string} type - Tipo de mudança
     */
    clear: function(type) {
      if (invalidationCallbacks[type]) {
        invalidationCallbacks[type] = [];
      }
    }
  };
})();
