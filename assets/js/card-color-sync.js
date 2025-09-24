// Sincroniza as cores dos botões dos cards em tempo real no painel (aba Imóveis)
if ('BroadcastChannel' in window) {
  const canal = new BroadcastChannel('configuracoes_empresa');
  canal.onmessage = function(ev) {
    if (ev.data && ev.data.tipo === 'atualizar_cores') {
      // Aguarda DOM pronto
      setTimeout(() => {
        // Seleciona todos os botões de detalhes dos cards
        document.querySelectorAll('.ver-detalhes').forEach(btn => {
          // Se cor de destaque estiver definida, ela tem prioridade
          if (ev.data.company_color3) {
            btn.style.backgroundColor = ev.data.company_color3;
            btn.style.borderColor = ev.data.company_color3;
          } else if (ev.data.company_color2) {
            btn.style.backgroundColor = ev.data.company_color2;
            btn.style.borderColor = ev.data.company_color2;
          }
          if (ev.data.company_font) {
            btn.style.fontFamily = `'${ev.data.company_font}', sans-serif`;
          }
        });
      }, 100);
    }
  };
}
// Aplica cor ao carregar a página (caso já esteja salva)
document.addEventListener('DOMContentLoaded', async function() {
  try {
    const res = await fetch('api/getCompanySettings.php');
    const json = await res.json();
    if (json.success && json.data && json.data.company_color2) {
      document.querySelectorAll('.ver-detalhes').forEach(btn => {
        if (json.data.company_color3) {
          btn.style.backgroundColor = json.data.company_color3;
          btn.style.borderColor = json.data.company_color3;
        } else if (json.data.company_color2) {
          btn.style.backgroundColor = json.data.company_color2;
          btn.style.borderColor = json.data.company_color2;
        }
        if (json.data.company_font) {
          btn.style.fontFamily = `'${json.data.company_font}', sans-serif`;
        }
      });
    }
  } catch (e) {}
});
