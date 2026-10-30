/**
 * Dashboard Period Controller
 * Sincroniza o período entre KPIs e Gráficos
 * Usa inline styles para evitar conflitos com Tailwind
 */

window.DashboardPeriodController = {
    currentPeriod: 7,
    
    init() {
        // Event listeners para botões de período
        const periodButtons = document.querySelectorAll('[data-period]');
        periodButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const period = parseInt(btn.getAttribute('data-period'));
                this.changePeriod(period);
            });
        });
        
        // Aplicar estilos iniciais (7 dias é o padrão)
        this.updateButtonStyles(7);
    },
    
    updateButtonStyles(activePeriod) {
        // Atualiza estilos de todos os botões usando inline styles com contraste máximo
        document.querySelectorAll('[data-period]').forEach(btn => {
            const btnPeriod = parseInt(btn.getAttribute('data-period'));
            
            if (btnPeriod === activePeriod) {
                // Botão ativo - azul MUITO vibrante com destaque
                btn.style.backgroundColor = '#0066ff'; // Azul vibrante
                btn.style.color = '#ffffff';
                btn.style.borderRadius = '0.5rem';
                btn.style.fontWeight = '700';
                btn.style.padding = '0.625rem 1.25rem';
                btn.style.cursor = 'pointer';
                btn.style.transition = 'all 0.2s';
                btn.style.border = '2px solid #0052cc'; // Border mais escuro
                btn.style.boxShadow = '0 4px 12px rgba(240, 236, 248, 0.99)';
                btn.style.fontSize = '0.95rem';
                btn.style.letterSpacing = '0.5px';
            } else {
                // Botão inativo - cinza bem definido
                btn.style.backgroundColor = '#ecf0f5f5';
                btn.style.color = '#1e0161'; // Preto/cinza ESCURO para melhor contraste
                btn.style.borderRadius = '0.5rem';
                btn.style.fontWeight = '500';
                btn.style.padding = '0.625rem 1.25rem';
                btn.style.cursor = 'pointer';
                btn.style.transition = 'all 0.2s';
                btn.style.border = '2px solid #d1d5db'; // gray-300 border mais definido
                btn.style.fontSize = '0.95rem';
            }
        });
    },
    
    changePeriod(period) {
        // Evita múltiplas chamadas
        if (this.currentPeriod === period) return;
        
        this.currentPeriod = period;
        
        // Atualiza estilos dos botões PRIMEIRO
        this.updateButtonStyles(period);
        
        // Notifica os sistemas de mudança de período
        // Aguardar um pouco para garantir que o DOM foi atualizado
        setTimeout(() => {
            if (window.DashboardKPIs) {
                window.DashboardKPIs.setPeriod(period);
            }
            if (window.DashboardCharts) {
                window.DashboardCharts.setPeriod(period);
            }
        }, 50);
    }
};

// Inicializa quando o documento está pronto
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        DashboardPeriodController.init();
    }, 300);
});
