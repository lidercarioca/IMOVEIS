// Gerenciador otimizado de carregamento de logo
const LogoLoader = {
    // Cache para armazenar o caminho da logo
    logoCache: null,
    _currentTenantId: null,
    _lastTenantId: null,

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
        try {
            // Obtém tenant_id da URL (máxima prioridade) ou window
            const urlParams = new URLSearchParams(window.location.search);
            const tenantIdFromUrl = urlParams.get('tenant_id');
            const tenantId = tenantIdFromUrl
                ? parseInt(tenantIdFromUrl)
                : window.SUBDOMAIN_TENANT_ID || 1;

            // VERIFICAÇÃO CRÍTICA: Se o tenant mudou, resetar cache em memória
            if (this._currentTenantId && this._currentTenantId !== tenantId) {
                this.logoCache = null;
            }
            this._currentTenantId = tenantId;

            if (this.logoCache) {
                return this.logoCache;
            }

            const json = await window.getCompanySettings();
            const data = json && json.success && json.data ? json.data : null;

            if (data) {
                let logoPath = data.company_logo || '/assets/imagens/logo/logo_padrao.png';
                if (!logoPath.startsWith('/')) {
                    logoPath = '/' + logoPath;
                }

                this.logoCache = logoPath;
                return logoPath;
            }
        } catch (e) {
            // Silencioso - usa default
        }

        return '/assets/imagens/logo/logo_padrao.png';
    },

    // Função para aplicar a logo nos elementos
    async applyLogo(element, size = '40px') {
        if (!element) return;

        try {
            const logoPath = await this.getLogoPath();

            // CACHE BUSTING: Adiciona timestamp para forçar reload da imagem
            const imageUrl = logoPath + (logoPath.includes('?') ? '&' : '?') + '_t=' + Date.now();

            try {
                await this.preloadImage(imageUrl);
            } catch (imgError) {
                // Arquivo não encontrado, usar default silenciosamente
                element.src = '/assets/imagens/logo/logo_padrao.png';
                element.style.maxHeight = size;
                element.style.width = 'auto';
                element.style.opacity = '1';
                element.style.display = 'block';
                return;
            }

            element.dataset.noLazy = '1';
            element.loading = 'eager';
            element.src = imageUrl;
            element.style.maxHeight = size;
            element.style.width = 'auto';
            element.style.opacity = '1';
            element.style.display = 'block';
        } catch (e) {
            // Silencioso - usa default
            element.src = '/assets/imagens/logo/logo_padrao.png';
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
                if (
                    ev.data &&
                    (ev.data.tipo === 'atualizar_cores' || ev.data.tipo === 'atualizar_logo')
                ) {
                    if (ev.data.company_logo) {
                        this.logoCache = null;
                        this._currentTenantId = null;

                        // Reaplica logos com forçação de reload
                        if (logoNav) await this.applyLogo(logoNav, '40px');
                        if (logoFooter) await this.applyLogo(logoFooter, '32px');
                    }
                }
            };
        }
    },
};

// Inicializa o carregador de logo quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => LogoLoader.init());
} else {
    LogoLoader.init();
}

function handleTenantIdChange() {
    const currentTenantId = new URLSearchParams(window.location.search).get('tenant_id') || 1;
    if (
        LogoLoader._lastTenantId !== null &&
        String(LogoLoader._lastTenantId) !== String(currentTenantId)
    ) {
        LogoLoader.logoCache = null;
        LogoLoader._currentTenantId = null;
        LogoLoader.init();
    }
    LogoLoader._lastTenantId = currentTenantId;
}

window.addEventListener('popstate', handleTenantIdChange);

if (history.pushState) {
    const originalPushState = history.pushState;
    history.pushState = function () {
        originalPushState.apply(this, arguments);
        handleTenantIdChange();
    };
}

if (history.replaceState) {
    const originalReplaceState = history.replaceState;
    history.replaceState = function () {
        originalReplaceState.apply(this, arguments);
        handleTenantIdChange();
    };
}
