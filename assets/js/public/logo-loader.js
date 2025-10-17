// Gerenciador otimizado de carregamento de logo
const LogoLoader = {
    // Cache para armazenar o caminho da logo
    logoCache: null,
    
    // Função para pré-carregar a imagem
    preloadImage(src) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = reject;
            img.src = src;
        });
    },

    // Função para obter o caminho da logo do cache ou API
    async getLogoPath() {
        if (this.logoCache) {
            return this.logoCache;
        }

        try {
            const cached = localStorage.getItem('logoPath');
            if (cached) {
                this.logoCache = cached;
                return cached;
            }

            const res = await fetch('/api/getCompanySettings.php');
            const data = await res.json();
            
            if (data.success && data.data) {
                let logoPath = data.data.company_logo || '/assets/imagens/logo/logo.png';
                if (!logoPath.startsWith('/')) {
                    logoPath = '/' + logoPath;
                }
                
                // Armazena no cache
                this.logoCache = logoPath;
                localStorage.setItem('logoPath', logoPath);
                
                return logoPath;
            }
        } catch (e) {
            console.error('Erro ao obter caminho da logo:', e);
        }

        return '/assets/imagens/logo/logo.png';
    },

    // Função para aplicar a logo nos elementos
    async applyLogo(element, size = '40px') {
        if (!element) return;

        try {
            const logoPath = await this.getLogoPath();
            const img = await this.preloadImage(logoPath);
            
            element.src = logoPath;
            element.style.maxHeight = size;
            element.style.width = 'auto';
            element.style.opacity = '1';
            element.style.display = 'block';
        } catch (e) {
            console.error('Erro ao aplicar logo:', e);
            element.src = '/assets/imagens/logo/logo.png';
        }
    },

    // Inicializa o sistema de logo
    async init() {
        // Aplica logos iniciais
        const logoNav = document.getElementById('company-logo-img');
        const logoFooter = document.getElementById('company-logo-footer');

        if (logoNav) await this.applyLogo(logoNav, '40px');
        if (logoFooter) await this.applyLogo(logoFooter, '32px');

        // Configura canal de broadcast para atualizações em tempo real
        if ('BroadcastChannel' in window) {
            const canal = new BroadcastChannel('configuracoes_empresa');
            canal.onmessage = async (ev) => {
                if (ev.data && (ev.data.tipo === 'atualizar_cores' || ev.data.tipo === 'atualizar_logo')) {
                    if (ev.data.company_logo) {
                        // Limpa cache
                        this.logoCache = null;
                        localStorage.removeItem('logoPath');
                        
                        // Reaplica logos
                        if (logoNav) await this.applyLogo(logoNav, '40px');
                        if (logoFooter) await this.applyLogo(logoFooter, '32px');
                    }
                }
            };
        }
    }
};

// Inicializa o carregador de logo quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => LogoLoader.init());
} else {
    LogoLoader.init();
}