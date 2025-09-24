// Objeto para gerenciar o estado do gráfico de desempenho
window.PerformanceChart = {
    resizeTimeout: null,
    currentPeriod: 7,
    isUpdating: false,
    updateInterval: null,
    
    // Gerencia o estado de atualização
    setUpdating(state) {
        this.isUpdating = state;
        const container = document.getElementById('performance-chart');
        if (container) {
            if (state) {
                container.classList.add('updating');
            } else {
                container.classList.remove('updating');
            }
        }
    }
};

/**
 * Carrega e exibe o gráfico de desempenho dos imóveis
 */
function loadPropertyPerformance(period = 7) {
    const container = document.getElementById('performance-chart');
    if (!container || PerformanceChart.isUpdating) return;

    PerformanceChart.currentPeriod = period;
    PerformanceChart.setUpdating(true);

    // Mostra indicador de carregamento de forma suave
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center transition-opacity duration-300';
    loadingDiv.innerHTML = `
        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
    `;
    container.style.position = 'relative';
    container.appendChild(loadingDiv);

    fetch(`api/getDashboardStats.php?period=${period}`, {
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
        // Se a resposta contiver tags HTML, provavelmente é uma página de erro
        if (text.includes('<!DOCTYPE html>') || text.includes('<html>')) {
            throw new Error('Erro de autenticação ou servidor');
        }
        try {
            const json = JSON.parse(text);
            return json;
        } catch (e) {
            throw new Error('Resposta inválida do servidor');
        }
    })
    .then(result => {
        if (!result.success) {
            throw new Error(result.error || 'Erro ao carregar estatísticas');
        }
        if (result.data) {
            renderPerformanceChart(result.data);
        }
    })
    .catch(error => {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full">
                <div class="text-red-500 mb-2">
                    <i class="fas fa-exclamation-circle text-2xl"></i>
                </div>
                <p class="text-gray-600 text-sm">Erro ao carregar dados de desempenho</p>
                <p class="text-xs text-red-600 mt-2">${error.message}</p>
                <button class="mt-2 text-sm text-blue-500 hover:text-blue-600" onclick="loadPropertyPerformance(${PerformanceChart.currentPeriod})">
                    Tentar novamente
                </button>
            </div>
        `;
    })
    .finally(() => {
        PerformanceChart.setUpdating(false);
        const loadingDiv = container.querySelector('.bg-white.bg-opacity-75');
        if (loadingDiv) {
            loadingDiv.style.opacity = '0';
            setTimeout(() => loadingDiv.remove(), 300);
        }
    });
}

/**
 * Renderiza o gráfico de desempenho com os dados recebidos
 */

function renderPerformanceChart(data) {
    const container = document.getElementById('performance-chart');
    if (!container) {
        return;
    }
        // ...existing code...
        // Limpa o conteúdo anterior e cria o container do gráfico
        container.innerHTML = `
            <div class="relative h-[320px] flex flex-col bg-white rounded-lg p-4 shadow-sm">
                <!-- Legenda -->
                <div class="flex justify-end gap-6 mb-6 text-xs px-2">
                    <div class="flex items-center hover:opacity-75 transition-all duration-300 cursor-help group">
                        <div class="w-3 h-3 bg-blue-500 rounded-full mr-2 shadow-sm group-hover:scale-110 transition-transform"></div>
                        <span class="text-gray-700 font-medium">Contatos/Leads</span>
                    </div>
                    <div class="flex items-center hover:opacity-75 transition-all duration-300 cursor-help group">
                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2 shadow-sm group-hover:scale-110 transition-transform"></div>
                        <span class="text-gray-700 font-medium">Novos Imóveis</span>
                    </div>
                    <div class="flex items-center hover:opacity-75 transition-all duration-300 cursor-help group">
                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2 shadow-sm group-hover:scale-110 transition-transform"></div>
                        <span class="text-gray-700 font-medium">Negócios Fechados</span>
                    </div>
                </div>
                <!-- Área do gráfico -->
                <div class="flex-grow position-relative">
                    <!-- Grade de fundo -->
                    <div class="absolute inset-0 flex flex-col justify-between pl-8">
                        <div class="h-px w-full bg-gray-100 bg-opacity-75"></div>
                        <div class="h-px w-full bg-gray-100 bg-opacity-75"></div>
                        <div class="h-px w-full bg-gray-100 bg-opacity-75"></div>
                        <div class="h-px w-full bg-gray-100 bg-opacity-75"></div>
                        <div class="h-px w-full bg-gray-100 bg-opacity-75"></div>
                        <div class="h-px w-full bg-gray-100 bg-opacity-75"></div>
                    </div>
                    <!-- Escala vertical -->
                    <div class="absolute h-full left-0 flex flex-col justify-between py-2">
                        <div class="h-4 flex items-center">
                            <span class="text-[11px] text-gray-400 w-6 text-right pr-2 select-none">5</span>
                        </div>
                        <div class="h-4 flex items-center">
                            <span class="text-[11px] text-gray-400 w-6 text-right pr-2 select-none">4</span>
                        </div>
                        <div class="h-4 flex items-center">
                            <span class="text-[11px] text-gray-400 w-6 text-right pr-2 select-none">3</span>
                        </div>
                        <div class="h-4 flex items-center">
                            <span class="text-[11px] text-gray-400 w-6 text-right pr-2 select-none">2</span>
                        </div>
                        <div class="h-4 flex items-center">
                            <span class="text-[11px] text-gray-400 w-6 text-right pr-2 select-none">1</span>
                        </div>
                        <div class="h-4 flex items-center">
                            <span class="text-xs text-gray-500 w-6 text-right pr-2">0</span>
                        </div>
                    </div>
                    <!-- Container do gráfico -->
                    <div class="absolute inset-0 pl-8 pr-4 pt-4 pb-2 flex items-end justify-between opacity-0 transition-opacity duration-300 min-h-[200px]"></div>
                </div>
            </div>
        `;
        // Remover este bloco pois a lógica foi movida para baixo
        

    // Verifica se todos os valores são zero
    const hasNonZeroValues = data.some(day => 
        parseInt(day.leads || 0) > 0 || 
        parseInt(day.new_properties || 0) > 0 || 
        parseInt(day.closed_deals || 0) > 0
    );

    if (!hasNonZeroValues) {
        container.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full w-full">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-chart-line text-4xl"></i>
                </div>
                <p class="text-gray-500">Sem atividade nos últimos ${data.length} dias</p>
                <p class="text-gray-400 text-sm mt-1">O gráfico será atualizado quando houver novos dados</p>
            </div>
        `;
        return;
    }

    // Ordena os dados por data
    data.sort((a, b) => new Date(a.date) - new Date(b.date));

    // Define valores máximos e escala
    const maxLeads = Math.max(...data.map(day => parseInt(day.leads || 0)), 0);
    const maxProperties = Math.max(...data.map(day => parseInt(day.new_properties || 0)), 0);
    const maxDeals = Math.max(...data.map(day => parseInt(day.closed_deals || 0)), 0);
    
    // Encontra o maior valor entre todos os tipos de dados
    const maxValue = Math.max(maxLeads, maxProperties, maxDeals, 1);
    
    // Escala dinâmica: usa o maior valor ou 5 como mínimo
    const scale = Math.max(maxValue, 5);

    // Limpa o conteúdo anterior e cria o container do gráfico
    container.innerHTML = `
        <div class="relative h-[300px] flex flex-col">
            <!-- Legenda -->
            <div class="flex justify-end gap-6 mb-4 text-xs px-2">
                <div class="flex items-center hover:opacity-75 transition-opacity cursor-help">
                    <div class="w-2.5 h-2.5 bg-blue-500 rounded-full mr-2"></div>
                    <span class="text-gray-600">Contatos/Leads</span>
                </div>
                <div class="flex items-center hover:opacity-75 transition-opacity cursor-help">
                    <div class="w-2.5 h-2.5 bg-green-500 rounded-full mr-2"></div>
                    <span class="text-gray-600">Novos Imóveis</span>
                </div>
                <div class="flex items-center hover:opacity-75 transition-opacity cursor-help">
                    <div class="w-2.5 h-2.5 bg-yellow-500 rounded-full mr-2"></div>
                    <span class="text-gray-600">Negócios Fechados</span>
                </div>
            </div>
            <!-- Área do gráfico -->
            <div class="flex-grow position-relative">
                <!-- Grade de fundo -->
                <div class="absolute inset-0 flex flex-col justify-between pl-8">
                    <div class="h-px w-full bg-gray-100"></div>
                    <div class="h-px w-full bg-gray-100"></div>
                    <div class="h-px w-full bg-gray-100"></div>
                    <div class="h-px w-full bg-gray-100"></div>
                    <div class="h-px w-full bg-gray-100"></div>
                    <div class="h-px w-full bg-gray-100 bg-opacity-100"></div>
                </div>
                
                <!-- Escala vertical -->
                <div class="absolute h-full left-0 flex flex-col justify-between py-2">
                    <div class="h-4 flex items-center opacity-0">
                        <span class="text-[11px] text-gray-400 w-8 text-right pr-2 select-none"></span>
                    </div>
                    <div class="h-4 flex items-center opacity-0">
                        <span class="text-[11px] text-gray-400 w-8 text-right pr-2 select-none"></span>
                    </div>
                    <div class="h-4 flex items-center opacity-0">
                        <span class="text-[11px] text-gray-400 w-8 text-right pr-2 select-none"></span>
                    </div>
                    <div class="h-4 flex items-center opacity-0">
                        <span class="text-[11px] text-gray-400 w-8 text-right pr-2 select-none"></span>
                    </div>
                    <div class="h-4 flex items-center opacity-0">
                        <span class="text-[11px] text-gray-400 w-8 text-right pr-2 select-none"></span>
                    </div>
                    <div class="h-4 flex items-center opacity-0">
                        <span class="text-xs text-gray-500 w-8 text-right pr-2"></span>
                    </div>
                </div>
                
                <!-- Container do gráfico -->
                <div class="absolute inset-0 pl-8 pr-4 pt-8 pb-12 flex items-end transition-all duration-300 min-h-[250px] overflow-x-auto" style="opacity: 1;">
                    <div class="flex-grow d-flex align-items-end" style="min-width: max-content; gap: ${data.length <= 7 ? '8px' : '4px'};">
                        <!-- As barras serão adicionadas aqui -->
                    </div>
                </div>
            </div>
        </div>
    `;

    // Seleciona o container do gráfico
    const chartContainer = container.querySelector('.flex.items-end');
    if (!chartContainer) {
        return;
        return;
    }

    // Para cada dia, cria uma coluna com 3 barras
    data.forEach((day, index) => {
        // Calcula as alturas proporcionais à escala
        const leadsHeight = (parseInt(day.leads || 0) / scale) * 100;
        const propertiesHeight = (parseInt(day.new_properties || 0) / scale) * 100;
        const dealsHeight = (parseInt(day.closed_deals || 0) / scale) * 100;

        // Converte a data para objeto Date
        const date = new Date(day.date + 'T00:00:00-03:00'); // Força o fuso horário de Brasília
        const weekDay = new Intl.DateTimeFormat('pt-BR', { weekday: 'short' }).format(date);
        const dayOfMonth = date.getDate();
        const formattedWeekDay = weekDay.charAt(0).toUpperCase() + weekDay.slice(1).toLowerCase().replace('.', '');
        
        // Cria o grupo de barras para o dia
        const dayGroup = document.createElement('div');
        dayGroup.className = `h-full relative flex flex-col justify-end items-center group ${data.length <= 7 ? 'px-2' : 'px-1'}`;
        
        // Adiciona o dia da semana e dia do mês abaixo das barras
        const dayLabel = document.createElement('div');
        dayLabel.className = 'absolute -bottom-8 flex flex-col items-center';
        dayLabel.innerHTML = `
            <span class="text-xs text-gray-700 font-medium whitespace-nowrap">${formattedWeekDay}</span>
            <span class="text-[10px] text-gray-500 whitespace-nowrap">${dayOfMonth}</span>
        `;

        // Adiciona o tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'absolute bottom-full mb-2 bg-white text-gray-800 text-xs rounded-lg px-4 py-3 opacity-0 group-hover:opacity-100 transition-all duration-300 pointer-events-none whitespace-nowrap z-10 -translate-x-1/2 left-1/2 shadow-lg border border-gray-100 transform group-hover:translate-y-0 translate-y-1';
        tooltip.innerHTML = `
            <div class="text-center font-medium text-[11px] mb-2 text-gray-500">${day.date || ''}</div>
            <div class="flex flex-col gap-2">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-gradient-to-tr from-blue-600 to-blue-400 rounded-full shadow-sm"></span>
                    <span class="font-medium">Leads: <span class="text-blue-600">${day.leads || 0}</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-gradient-to-tr from-green-600 to-green-400 rounded-full shadow-sm"></span>
                    <span class="font-medium">Imóveis: <span class="text-green-600">${day.new_properties || 0}</span></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 bg-gradient-to-tr from-yellow-500 to-yellow-300 rounded-full shadow-sm"></span>
                    <span class="font-medium">Negócios: <span class="text-yellow-600">${day.closed_deals || 0}</span></span>
                </div>
            </div>
        `;
        dayGroup.appendChild(tooltip);

        // Cria o container das barras
        const barsContainer = document.createElement('div');
        barsContainer.className = `h-full w-full flex justify-center items-end ${data.length <= 7 ? 'gap-2' : 'gap-1'}`;
        
        // Cria as barras
        const barWidth = data.length <= 7 ? 'w-[10px]' : 'w-[6px]';
        const barsHTML = `
            <div class="${barWidth} bg-gradient-to-t from-blue-600 to-blue-400 transition-all duration-500 rounded-full hover:opacity-90 transform hover:scale-110 shadow-lg" 
                 style="height: ${leadsHeight}%"></div>
            <div class="${barWidth} bg-gradient-to-t from-green-600 to-green-400 transition-all duration-500 rounded-full hover:opacity-90 transform hover:scale-110 shadow-lg" 
                 style="height: ${propertiesHeight}%"></div>
            <div class="${barWidth} bg-gradient-to-t from-yellow-500 to-yellow-300 transition-all duration-500 rounded-full hover:opacity-90 transform hover:scale-110 shadow-lg" 
                 style="height: ${dealsHeight}%"></div>
        `;
        barsContainer.innerHTML = barsHTML;
        dayGroup.appendChild(barsContainer);

        // Adiciona o label do dia e o grupo ao container do gráfico
        dayGroup.appendChild(dayLabel);
        chartContainer.appendChild(dayGroup);
    });
}

/**
 * Renderiza o gráfico e estatísticas de imóveis por categoria
 */
/**function renderCategoryChart(categories) {
    const container = document.getElementById('property-categories');
    if (!container) return;

    // Limpa o conteúdo anterior
    let html = '<h3 class="text-lg font-semibold mb-4">Imóveis por Categoria</h3>';
    
    // Se não houver categorias, mostra mensagem
    if (!categories || Object.keys(categories).length === 0) {
        html += `
            <div class="flex flex-col items-center justify-center h-full">
                <div class="text-gray-400 mb-2">
                    <i class="fas fa-home text-4xl"></i>
                </div>
                <p class="text-gray-500">Nenhum imóvel cadastrado</p>
            </div>
        `;
        container.innerHTML = html;
        return;
    }

    html += '<div class="vstack gap-2">';

    // Prepara os ícones para cada categoria
    const categoryIcons = {
        'apartment': 'building',
        'house': 'home',
        'commercial': 'store',
        'land': 'map'
    };

    // Cria o container para as categorias
    const categoriesContainer = document.createElement('div');
    categoriesContainer.className = 'space-y-2';
    
    // Cria as barras para cada categoria
    Object.entries(categories).forEach(([category, stats]) => {
        const icon = categoryIcons[category] || 'home';
        const categoryTitle = {
            'apartment': 'Apartamentos',
            'house': 'Casas',
            'commercial': 'Comercial',
            'land': 'Terrenos'
        }[category] || category;

        // Garantir que stats seja um objeto com valores padrão
        const total = stats?.total || 0;

        html += `
            <div class="flex items-center gap-2 p-2 hover:bg-gray-50 rounded-lg">
                <div class="w-8 h-8 flex items-center justify-center rounded bg-blue-100 text-blue-600">
                    <i class="fas fa-${icon}"></i>
                </div>
                <span class="flex-grow">${categoryTitle}</span>
                <span class="text-gray-600">${total}</span>
                <div class="w-24 bg-blue-500 h-1 rounded"></div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}*/

// Adiciona listener para o select de período
// Função para lidar com o redimensionamento da janela
function handleResize() {
    clearTimeout(PerformanceChart.resizeTimeout);
    PerformanceChart.resizeTimeout = setTimeout(() => {
        const periodSelect = document.getElementById('performance-period-select');
        const period = periodSelect ? periodSelect.value.match(/\d+/)[0] : 7;
        loadPropertyPerformance(period);
    }, 250); // Debounce de 250ms
}

// Inicialização quando o DOM estiver pronto
function initializeCharts() {
    const performanceSection = document.querySelector('.performance-section');
    const periodSelect = document.getElementById('performance-period-select');
    
    if (periodSelect) {
        // Atualiza o valor inicial do período
        PerformanceChart.currentPeriod = parseInt(periodSelect.value.match(/\d+/)[0]);
        
        // Listener para mudanças no select
        periodSelect.addEventListener('change', (e) => {
            const period = parseInt(e.target.value.match(/\d+/)[0]);
            if (period !== PerformanceChart.currentPeriod) {
                loadPropertyPerformance(period);
            }
        });
    }

    // Listener para redimensionamento com debounce
    window.addEventListener('resize', () => {
        clearTimeout(PerformanceChart.resizeTimeout);
        PerformanceChart.resizeTimeout = setTimeout(() => {
            if (!PerformanceChart.isUpdating) {
                loadPropertyPerformance(PerformanceChart.currentPeriod);
            }
        }, 250);
    });

    // Carrega o gráfico inicial
    loadPropertyPerformance(PerformanceChart.currentPeriod);

    // Configura atualização automática
    PerformanceChart.updateInterval = setInterval(() => {
        if (document.visibilityState === 'visible' && !PerformanceChart.isUpdating) {
            loadPropertyPerformance(PerformanceChart.currentPeriod);
        }
    }, 5 * 60 * 1000); // 5 minutos

    // Listener para visibilidade da página
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !PerformanceChart.isUpdating) {
            loadPropertyPerformance(PerformanceChart.currentPeriod);
        }
    });

    // Limpar intervalo quando o componente for destruído
    window.addEventListener('beforeunload', () => {
        if (PerformanceChart.updateInterval) {
            clearInterval(PerformanceChart.updateInterval);
        }
    });
}

// Garante que a inicialização ocorra após todos os scripts
document.addEventListener('DOMContentLoaded', () => {
    const chartElement = document.getElementById('performance-chart');
    
    if (!chartElement) return;
    
    // Inicializa os gráficos (isso já inclui o primeiro carregamento)
    initializeCharts();
});
