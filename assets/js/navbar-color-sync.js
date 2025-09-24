// Sincroniza as cores da navbar em tempo real
if ('BroadcastChannel' in window) {
    const canal = new BroadcastChannel('configuracoes_empresa');
    function atualizarNavbar(cores) {
        document.querySelectorAll('.navbar-dynamic, .navbar-dynamic-gradient').forEach(navbar => {
            if (cores.company_color1) {
                navbar.style.backgroundColor = cores.company_color1;
            }
            if (cores.company_font) {
                navbar.style.fontFamily = `'${cores.company_font}', sans-serif`;
            }
        });
    }
    canal.onmessage = function(ev) {
        if (ev.data && ev.data.tipo === 'atualizar_cores') {
            atualizarNavbar(ev.data);
        }
    };
    document.addEventListener('DOMContentLoaded', async function() {
        try {
            const response = await fetch('/api/getCompanySettings.php');
            const data = await response.json();
            if (data.success && data.data) {
                atualizarNavbar(data.data);
            }
        } catch (error) {
            console.error('Erro ao carregar configurações:', error);
        }
    });
}
