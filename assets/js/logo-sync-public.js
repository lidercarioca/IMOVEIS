// Função para carregar logos
async function carregarLogos() {
    const defaultLogo = '/assets/imagens/logo/logo_padrao.png';
    let logoPath = defaultLogo;

    try {
        if (typeof window.getCompanySettings === 'function') {
            const data = await window.getCompanySettings();
            if (data && data.success && data.data && data.data.company_logo) {
                logoPath = data.data.company_logo || defaultLogo;
            }
        }
    } catch (e) {
        // Falha ao carregar settings — usar logo padrão
        console.warn('getCompanySettings falhou, usando logo padrão:', e);
        logoPath = defaultLogo;
    }

    // Normalizar caminho
    if (logoPath && !logoPath.startsWith('/')) {
        logoPath = '/' + logoPath;
    }

    // Função auxiliar para atualizar logo usando classes (reduz flicker)
    const atualizarLogo = (elemento, tamanho) => {
        if (!elemento) return;
        elemento.classList.remove('d-none');
        elemento.classList.remove('loaded');
        elemento.dataset.noLazy = '1';
        elemento.loading = 'eager';
        elemento.style.maxHeight = tamanho;
        elemento.style.width = 'auto';
        elemento.style.opacity = '0';

        const aplicarEstadoCarregado = function () {
            this.classList.add('loaded');
            this.classList.remove('d-none');
            this.style.opacity = '1';
            this.style.display = '';
        };

        elemento.onerror = function () {
            console.error('Erro ao carregar logo:', this.src);
            this.classList.add('d-none');
            this.classList.remove('loaded');
        };
        elemento.onload = function () {
            aplicarEstadoCarregado.call(this);
        };

        // Atribuir src por último para disparar eventos corretamente
        elemento.src = logoPath;

        if (elemento.complete && elemento.naturalWidth > 0) {
            aplicarEstadoCarregado.call(elemento);
        }
    };

    const logoEl = document.getElementById('company-logo-img');
    atualizarLogo(logoEl, '40px');
    const logoFooter = document.getElementById('company-logo-footer');
    atualizarLogo(logoFooter, '32px');
}

// Carrega as logos assim que o DOM estiver pronto
document.addEventListener('DOMContentLoaded', carregarLogos);

// Atualiza o logo em tempo real ao receber mensagem de atualização
if ('BroadcastChannel' in window) {
    const canal = new BroadcastChannel('configuracoes_empresa');
    canal.onmessage = function (ev) {
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
