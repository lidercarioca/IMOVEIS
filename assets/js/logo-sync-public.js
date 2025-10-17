// Função para carregar logos
async function carregarLogos() {
  try {
    
    const res = await fetch('/api/getCompanySettings.php');
    const data = await res.json();
    

    if (data.success && data.data) {
      let logoPath = data.data.company_logo || '/assets/imagens/logo/logo.png';
      
      // Garante que o caminho começa com /
      if (!logoPath.startsWith('/')) {
        logoPath = '/' + logoPath;
      }

      

      // Função auxiliar para atualizar logo
      const atualizarLogo = (elemento, tamanho) => {
        if (elemento) {
          elemento.src = logoPath;
          elemento.style.maxHeight = tamanho;
          elemento.style.width = 'auto';
          elemento.onerror = function() {
            console.error('Erro ao carregar logo:', this.src);
            this.style.display = 'none';
          };
          elemento.onload = function() {
            
            this.style.display = 'block';
          };
        }
      };

      // Atualiza logo do navbar
      const logoEl = document.getElementById('company-logo-img');
      atualizarLogo(logoEl, '40px');
      
      // Atualiza logo do footer
      const logoFooter = document.getElementById('company-logo-footer');
      atualizarLogo(logoFooter, '32px');
    } else {
      console.error('Dados inválidos retornados pela API:', data);
    }
  } catch (e) {
    console.error('Erro ao carregar logos:', e);
  }
}

// Carrega as logos assim que o DOM estiver pronto
document.addEventListener('DOMContentLoaded', carregarLogos);

// Atualiza o logo em tempo real ao receber mensagem de atualização
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
