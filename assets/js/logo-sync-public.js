// Atualiza o logo do site público (index.html) em tempo real ao receber mensagem de atualização
if ('BroadcastChannel' in window) {
  const canal = new BroadcastChannel('configuracoes_empresa');
  canal.onmessage = function(ev) {
    if (ev.data && (ev.data.tipo === 'atualizar_cores' || ev.data.tipo === 'atualizar_logo')) {
      if (ev.data.company_logo) {
        const logoEl = document.getElementById('company-logo-img');
        const logoFooter = document.getElementById('company-logo-footer');
        const cacheBuster = '?v=' + Date.now();
        if (logoEl) logoEl.src = ev.data.company_logo + cacheBuster;
        if (logoFooter) logoFooter.src = ev.data.company_logo + cacheBuster;
      }
    }
  };
}
