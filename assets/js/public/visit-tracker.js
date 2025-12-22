// assets/js/public/visit-tracker.js - Rastreia visitas do site
(function trackPageVisit() {
  // Evita rastreamento múltiplo na mesma página
  if (sessionStorage.getItem('visit_tracked_' + window.location.pathname)) {
    return;
  }

  try {
    // Marca como rastreado para evitar múltiplos envios
    sessionStorage.setItem('visit_tracked_' + window.location.pathname, 'true');

    // Envia a visita para a API
    fetch('/api/trackVisit.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        page: window.location.pathname,
        referrer: document.referrer
      }),
      keepalive: true // Garante que a requisição seja enviada mesmo se a página fechar
    }).catch(err => {
      console.debug('Visit tracking error (não crítico):', err);
    });
  } catch (error) {
    console.debug('Visit tracking error:', error);
  }
})();
