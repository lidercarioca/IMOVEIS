// Sincroniza as cores da navbar em tempo real
(function () {
    function aplicarCoresNavbar(cores) {
        // Atualiza variáveis CSS
        const root = document.documentElement;
        if (cores.company_color1) {
            root.style.setProperty('--cor-primaria', cores.company_color1);
            // Converte hex para RGB para efeitos de transparência
            const rgb = cores.company_color1
                .replace(/^#/, '')
                .match(/.{2}/g)
                .map((x) => parseInt(x, 16))
                .join(', ');
            root.style.setProperty('--cor-primaria-rgb', rgb);
        }

        // Aplica diretamente na navbar apenas se necessário
        document
            .querySelectorAll('.navbar-dynamic, .navbar-dynamic-gradient, .navbar')
            .forEach((navbar) => {
                if (cores.company_font) {
                    navbar.style.fontFamily = `'${cores.company_font}', var(--fonte-principal), sans-serif`;
                }
            });
    }

    // Não depender de variáveis legadas em escopo global.
    // As configurações da empresa são fornecidas por `window.getCompanySettings()` (loader central).
    // A aplicação inicial de cores será feita no DOMContentLoaded quando a promise estiver disponível.

    // Configura o canal de broadcast para atualizações em tempo real
    if ('BroadcastChannel' in window) {
        const canal = new BroadcastChannel('configuracoes_empresa');
        canal.onmessage = function (ev) {
            if (ev.data && ev.data.tipo === 'atualizar_cores') {
                aplicarCoresNavbar(ev.data);
            }
        };
    }

    // Carrega as configurações mais recentes do servidor no momento apropriado
    document.addEventListener('DOMContentLoaded', async function () {
        // Garantir que o loader central esteja presente
        if (typeof window.getCompanySettings !== 'function') {
            console.error(
                'company-settings-loader.js não carregado — não é possível aplicar cores da empresa'
            );
            // Remover preload mesmo se não tivermos configurações
            setTimeout(() => {
                document.documentElement.classList.remove('preload');
            }, 100);
            return;
        }

        try {
            const json = await window.getCompanySettings();
            if (json && json.success && json.data) {
                aplicarCoresNavbar(json.data);
            }
        } catch (error) {
            console.error('Erro ao obter company settings:', error);
        } finally {
            // Remove a classe preload após as cores serem aplicadas
            setTimeout(() => {
                document.documentElement.classList.remove('preload');
            }, 100);
        }
    });
})();
