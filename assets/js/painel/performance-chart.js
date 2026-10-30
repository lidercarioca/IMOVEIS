// Objeto para gerenciar o estado do gráfico de desempenho
window.PerformanceChart = {
    resizeTimeout: null,
    currentPeriod: 7,
    isUpdating: false
};

/**
 * Carrega e exibe o gráfico de desempenho dos imóveis
 */
function loadPropertyPerformance(period = 7) {
    const container = document.getElementById('performance-chart');
    if (!container || PerformanceChart.isUpdating) return;

    PerformanceChart.currentPeriod = period;
    PerformanceChart.isUpdating = true;

    // Mostra indicador de carregamento
    container.innerHTML = `
        <div class="flex items-center justify-center h-full w-full">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        </div>
    `;

    fetch(`/api/v1/reports/getDashboardStats.php?period=${period}`, {
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        const text = await response.text();
        if (text.includes('<!DOCTYPE html>') || text.includes('<html>')) {
            throw new Error('Erro de autenticação');
        }
        return JSON.parse(text);
    })
    .then(result => {
        if (!result.success) {
            throw new Error(result.error || 'Erro ao carregar dados');
        }
        renderPerformanceChart(result.data);
    })
    .catch(error => {
        console.error('Erro ao carregar gráfico:', error);
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full w-full py-8">
                <p class="text-red-500 text-sm mb-2">Erro ao carregar dados</p>
                <p class="text-gray-500 text-xs mb-4">${error.message}</p>
                <button class="text-blue-500 hover:text-blue-600 text-sm" onclick="loadPropertyPerformance(${PerformanceChart.currentPeriod})">
                    Tentar novamente
                </button>
            </div>
        `;
    })
    .finally(() => {
        PerformanceChart.isUpdating = false;
    });
}

/**
 * Renderiza o gráfico de desempenho com dias da semana e do mês
 */
function renderPerformanceChart(data) {
    const container = document.getElementById('performance-chart');
    if (!container) return;

    // Verifica se há dados
    const hasData = data && data.length > 0 && data.some(day => 
        parseInt(day.leads || 0) > 0 || 
        parseInt(day.new_properties || 0) > 0 || 
        parseInt(day.closed_deals || 0) > 0
    );

    if (!hasData) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full w-full py-12">
                <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <p class="text-gray-600 font-medium">Sem atividade</p>
                <p class="text-gray-400 text-sm">O gráfico será atualizado com novos dados</p>
            </div>
        `;
        return;
    }

    // Ordena dados por data
    const sortedData = [...data].sort((a, b) => new Date(a.date) - new Date(b.date));

    // Calcula valor máximo para escala
    const maxValue = Math.max(
        ...sortedData.map(d => Math.max(
            parseInt(d.leads || 0),
            parseInt(d.new_properties || 0),
            parseInt(d.closed_deals || 0)
        ))
    );
    const scale = Math.max(maxValue, 5);

    // Cores das barras
    const colors = {
        leads: '#111827',        // Cinza escuro (cor primária)
        properties: '#10b981',   // Verde
        deals: '#d97706'         // Laranja (cor destaque)
    };

    // Cria container do gráfico
    const chartHtml = `
        <div style="display: flex; flex-direction: column; height: 100%; width: 100%; background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%); padding: 16px 8px; border-radius: 8px;">
            <!-- Área de barras -->
            <div style="display: flex; align-items: flex-end; justify-content: flex-start; gap: 6px; flex: 1; overflow-x: auto; padding-bottom: 80px; border-bottom: 2px solid #e2e8f0;">
                ${sortedData.map((day, idx) => {
                    const leads = parseInt(day.leads || 0);
                    const properties = parseInt(day.new_properties || 0);
                    const deals = parseInt(day.closed_deals || 0);

                    const h1 = (leads / scale) * 100 || 0;
                    const h2 = (properties / scale) * 100 || 0;
                    const h3 = (deals / scale) * 100 || 0;

                    const date = new Date(day.date + 'T00:00:00-03:00');
                    const weekday = new Intl.DateTimeFormat('pt-BR', { weekday: 'short' }).format(date);
                    const dayNum = date.getDate();

                    return `
                        <div style="display: flex; flex-direction: column; align-items: center; flex: 0 0 auto; position: relative; gap: 0; min-width: 60px;" class="chart-bar-group" title="${day.date}">
                            <!-- Barras -->
                            <div style="display: flex; gap: 2px; align-items: flex-end; height: 220px;">
                                ${h1 > 0 ? `<div style="width: 12px; height: ${h1}%; background: ${colors.leads}; border-radius: 2px 2px 0 0; box-shadow: 0 4px 8px rgba(59, 130, 246, 0.3); transition: all 0.2s ease; cursor: pointer;" class="bar-leads" title="Leads: ${leads}"></div>` : ''}
                                ${h2 > 0 ? `<div style="width: 12px; height: ${h2}%; background: ${colors.properties}; border-radius: 2px 2px 0 0; box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3); transition: all 0.2s ease; cursor: pointer;" class="bar-properties" title="Imóveis: ${properties}"></div>` : ''}
                                ${h3 > 0 ? `<div style="width: 12px; height: ${h3}%; background: ${colors.deals}; border-radius: 2px 2px 0 0; box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3); transition: all 0.2s ease; cursor: pointer;" class="bar-deals" title="Negócios: ${deals}"></div>` : ''}
                            </div>
                            
                            <!-- Dia da semana e número do mês -->
                            <div style="text-align: center; margin-top: 8px; width: 100%;">
                                <div style="font-size: 11px; font-weight: 600; color: #334155; text-transform: uppercase; line-height: 1.2;">${weekday}</div>
                                <div style="font-size: 10px; font-weight: 500; color: #94a3b8; line-height: 1.2;">${dayNum}</div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>

            <!-- Legenda -->
            <div style="display: flex; justify-content: center; flex-wrap: wrap; gap: 16px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 6px;">
                    <div style="width: 8px; height: 8px; background: ${colors.leads}; border-radius: 2px;"></div>
                    <span style="font-size: 11px; color: #64748b;">Contatos/Leads</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <div style="width: 8px; height: 8px; background: ${colors.properties}; border-radius: 2px;"></div>
                    <span style="font-size: 11px; color: #64748b;">Novos Imóveis</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <div style="width: 8px; height: 8px; background: ${colors.deals}; border-radius: 2px;"></div>
                    <span style="font-size: 11px; color: #64748b;">Negócios Fechados</span>
                </div>
            </div>
        </div>
    `;

    container.innerHTML = chartHtml;

    // Adiciona interatividade às barras
    const bars = container.querySelectorAll('.chart-bar-group');
    bars.forEach((group, idx) => {
        const day = sortedData[idx];
        const barElements = group.querySelectorAll('[class^="bar-"]');
        
        barElements.forEach(bar => {
            bar.addEventListener('mouseenter', function() {
                this.style.transform = 'scaleY(1.1)';
                this.style.boxShadow = '0 8px 16px rgba(0, 0, 0, 0.2)';
            });
            
            bar.addEventListener('mouseleave', function() {
                this.style.transform = 'scaleY(1)';
                if (this.classList.contains('bar-leads')) {
                    this.style.boxShadow = '0 4px 8px rgba(59, 130, 246, 0.3)';
                } else if (this.classList.contains('bar-properties')) {
                    this.style.boxShadow = '0 4px 8px rgba(16, 185, 129, 0.3)';
                } else {
                    this.style.boxShadow = '0 4px 8px rgba(245, 158, 11, 0.3)';
                }
            });
        });
    });

    // Adiciona animação CSS
    if (!document.getElementById('chart-animations')) {
        const style = document.createElement('style');
        style.id = 'chart-animations';
        style.textContent = `
            .chart-bar-group .bar-leads,
            .chart-bar-group .bar-properties,
            .chart-bar-group .bar-deals {
                animation: chartBarGrow 0.5s ease-out forwards;
            }
            @keyframes chartBarGrow {
                from {
                    height: 0 !important;
                    opacity: 0;
                }
                to {
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    const periodSelect = document.getElementById('performance-period-select');
    
    if (periodSelect) {
        // Carrega dados iniciais
        loadPropertyPerformance(7);
        
        // Listener para mudanças de período
        periodSelect.addEventListener('change', (e) => {
            const period = parseInt(e.target.value.match(/\d+/)[0]);
            loadPropertyPerformance(period);
        });
    }
});

// Recarrega ao redimensionar a janela
window.addEventListener('resize', () => {
    clearTimeout(PerformanceChart.resizeTimeout);
    PerformanceChart.resizeTimeout = setTimeout(() => {
        if (!PerformanceChart.isUpdating) {
            loadPropertyPerformance(PerformanceChart.currentPeriod);
        }
    }, 500);
});
