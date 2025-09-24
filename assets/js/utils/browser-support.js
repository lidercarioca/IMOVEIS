// Detecção de recursos do navegador
const BrowserSupport = {
    // Verifica suporte a recursos modernos
    features: {
        grid: typeof CSS !== 'undefined' && CSS.supports('display', 'grid'),
        flexbox: typeof CSS !== 'undefined' && CSS.supports('display', 'flex'),
        cssVars: window.CSS && CSS.supports('color', 'var(--fake-var)'),
        customProperties: window.CSS && CSS.supports('color', 'var(--fake-var)'),
        intersectionObserver: 'IntersectionObserver' in window,
        localStorage: (() => {
            try {
                localStorage.setItem('test', 'test');
                localStorage.removeItem('test');
                return true;
            } catch (e) {
                return false;
            }
        })(),
        webP: false, // Será testado assincronamente
    },

    // Inicializa os testes
    init() {
        // Testa suporte a WebP
        const webP = new Image();
        webP.onload = () => { this.features.webP = true; };
        webP.onerror = () => { this.features.webP = false; };
        webP.src = 'data:image/webp;base64,UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

        // Adiciona classes ao HTML baseado no suporte
        this.updateSupportClasses();

        // Carrega polyfills se necessário
        this.loadPolyfills();
    },

    // Atualiza classes no HTML
    updateSupportClasses() {
        const html = document.documentElement;
        
        Object.entries(this.features).forEach(([feature, supported]) => {
            html.classList.toggle(`supports-${feature}`, supported);
            html.classList.toggle(`no-${feature}`, !supported);
        });
    },

    // Carrega polyfills necessários
    async loadPolyfills() {
        const polyfills = [];

        // Verifica quais polyfills são necessários
        if (!this.features.customProperties) {
            polyfills.push('https://cdn.jsdelivr.net/npm/css-vars-ponyfill@2');
        }
        
        if (!this.features.intersectionObserver) {
            polyfills.push('https://cdn.jsdelivr.net/npm/intersection-observer@0.12.2/intersection-observer.js');
        }

        // Carrega os polyfills
        if (polyfills.length > 0) {
            try {
                await Promise.all(polyfills.map(url => {
                    return new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = url;
                        script.onload = resolve;
                        script.onerror = reject;
                        document.head.appendChild(script);
                    });
                }));

                // Inicializa os polyfills
                if (!this.features.customProperties) {
                    cssVars({});
                }
            } catch (error) {
                console.error('Erro ao carregar polyfills:', error);
            }
        }
    },

    // Verifica se o navegador é muito antigo
    isLegacyBrowser() {
        return !this.features.flexbox || !this.features.localStorage;
    },

    // Redireciona para versão legacy se necessário
    checkAndRedirect() {
        if (this.isLegacyBrowser()) {
            const currentUrl = window.location.href;
            const legacyUrl = currentUrl + (currentUrl.includes('?') ? '&' : '?') + 'legacy=true';
            window.location.href = legacyUrl;
        }
    }
};

// Inicializa o detector de recursos
document.addEventListener('DOMContentLoaded', () => {
    BrowserSupport.init();
});
