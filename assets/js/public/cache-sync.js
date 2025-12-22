/**
 * Integração do cache-invalidator com o site público
 * Re-carrega dados quando há mudanças no painel
 */

document.addEventListener('DOMContentLoaded', function() {
  // Aguarda o carregamento do CacheInvalidator
  if (typeof CacheInvalidator === 'undefined') {
    console.warn('CacheInvalidator não carregado');
    return;
  }

  /**
   * Callback para recarregar propriedades
   */
  CacheInvalidator.on('properties', function(data) {
    console.log('Cache invalidado: propriedades foram alteradas', data);
    
    // Recarrega as propriedades via API
    if (typeof window.carregarPropriedades === 'function') {
      window.carregarPropriedades();
    } else if (typeof window.loadProperties === 'function') {
      window.loadProperties();
    } else {
      // Fallback: re-busca os dados e renderiza
      fetch('/api/getProperties.php')
        .then(res => res.json())
        .then(props => {
          if (typeof window.renderizarImoveis === 'function') {
            window.dadosImoveis = props;
            window.renderizarImoveis(props);
          }
        })
        .catch(err => console.error('Erro ao recarregar propriedades:', err));
    }
  });

  /**
   * Callback para recarregar banners
   */
  CacheInvalidator.on('banners', function(data) {
    console.log('Cache invalidado: banners foram alterados', data);
    
    if (typeof window.carregarBanners === 'function') {
      window.carregarBanners();
    } else {
      // Tenta recarregar os carousels
      location.reload();
    }
  });

  /**
   * Callback para recarregar configurações da empresa
   */
  CacheInvalidator.on('company-settings', function(data) {
    console.log('Cache invalidado: configurações da empresa foram alteradas', data);
    
    // Remove cache local
    localStorage.removeItem('logoPath');
    localStorage.removeItem('companySettings');
    
    // Recarrega a página para aplicar novas configurações
    location.reload();
  });

  /**
   * Callback para recarregar leads/mensagens
   */
  CacheInvalidator.on('leads', function(data) {
    console.log('Cache invalidado: leads foram alterados', data);
    // Não é necessário recarregar a página publica para leads
  });

  /**
   * Callback para recarregar agendamentos
   */
  CacheInvalidator.on('agendamentos', function(data) {
    console.log('Cache invalidado: agendamentos foram alterados', data);
    // Não é necessário recarregar a página publica para agendamentos
  });
});
