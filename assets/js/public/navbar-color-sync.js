// Sincroniza as cores da navbar em tempo real
(function() {
    // Cache das cores para carregamento inicial rápido
    let coresCacheadas = null;
    
    // Tenta carregar cores do localStorage
    try {
        const cached = localStorage.getItem('companySettings');
        if (cached) {
            coresCacheadas = JSON.parse(cached);
        }
    } catch (e) {
        console.warn('Erro ao carregar cores do cache:', e);
    }

    function aplicarCoresNavbar(cores) {
        // Atualiza variáveis CSS
        const root = document.documentElement;
        if (cores.company_color1) {
            root.style.setProperty('--cor-primaria', cores.company_color1);
            // Converte hex para RGB para efeitos de transparência
            const rgb = cores.company_color1.replace(/^#/, '').match(/.{2}/g)
                .map(x => parseInt(x, 16)).join(', ');
            root.style.setProperty('--cor-primaria-rgb', rgb);
        }

        // Aplica diretamente na navbar apenas se necessário
        document.querySelectorAll('.navbar-dynamic, .navbar-dynamic-gradient, .navbar').forEach(navbar => {
            if (cores.company_color1) {
                navbar.style.backgroundColor = cores.company_color1;
                navbar.style.setProperty('--navbar-bg', cores.company_color1);
            }
            if (cores.company_font) {
                navbar.style.fontFamily = `'${cores.company_font}', var(--fonte-principal), sans-serif`;
            }
        });

        // Cache as cores para uso futuro
        try {
            localStorage.setItem('companySettings', JSON.stringify(cores));
        } catch (e) {
            console.warn('Erro ao cachear cores:', e);
        }
    }

    // Aplica cores cacheadas imediatamente se disponíveis
    if (coresCacheadas) {
        aplicarCoresNavbar(coresCacheadas);
    }

    // Configura o canal de broadcast para atualizações em tempo real
    if ('BroadcastChannel' in window) {
        const canal = new BroadcastChannel('configuracoes_empresa');
        canal.onmessage = function(ev) {
            if (ev.data && ev.data.tipo === 'atualizar_cores') {
                aplicarCoresNavbar(ev.data);
            }
        };
    }

    // Carrega as configurações mais recentes do servidor
    document.addEventListener('DOMContentLoaded', async function() {
        try {
            const response = await fetch('/api/getCompanySettings.php');
            const data = await response.json();
            if (data.success && data.data) {
                aplicarCoresNavbar(data.data);
            }
        } catch (error) {
            console.error('Erro ao carregar configurações:', error);
        } finally {
            // Remove a classe preload após as cores serem aplicadas
            setTimeout(() => {
                document.documentElement.classList.remove('preload');
            }, 100);
        }
    });
})();
