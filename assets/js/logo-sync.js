// Atualiza o logo do painel ao receber mensagem de atualização
if ('BroadcastChannel' in window) {
  const canal = new BroadcastChannel('configuracoes_empresa');
  canal.onmessage = function(ev) {
    if (ev.data && (ev.data.tipo === 'atualizar_cores' || ev.data.tipo === 'atualizar_logo')) {
      if (ev.data.company_logo) {
        const logoEl = document.getElementById('company-logo-painel');
        if (logoEl) logoEl.src = ev.data.company_logo;
      }
    }
  };
}
