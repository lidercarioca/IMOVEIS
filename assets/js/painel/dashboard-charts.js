/**
 * Dashboard Charts Manager
 * Gerencia visualizações de gráficos do dashboard usando ApexCharts
 */

window.DashboardCharts = {
    charts: {},
    currentPeriod: 7,
    isLoading: false,
    loadTimeout: null,
    retryCount: 0,
    maxRetries: 2,

    init() {
        this.retryCount = 0;
        this.loadCharts();
    },

    loadCharts() {
        if (this.isLoading) {
            return;
        }

        this.isLoading = true;

        // Timeout de segurança: se não carregar em 15s, mostra erro
        this.loadTimeout = setTimeout(() => {
            document.querySelectorAll('[id^="chart-"]').forEach((el) => {
                if (el.innerHTML.includes('Carregando')) {
                    el.innerHTML =
                        '<div class="flex flex-col items-center justify-center h-96 text-red-500"><svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><p class="text-center font-medium">Erro ao carregar gráfico<br><small class="text-xs mt-1">Verifique sua conexão ou contato o administrador</small></p></div>';
                }
            });
            this.isLoading = false;
        }, 15000);

        // Adiciona timestamp para evitar cache
        const timestamp = new Date().getTime();
        apiCall(
            `/api/relatorios/getDashboardCharts.php?period=${this.currentPeriod}&t=${timestamp}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                },
            }
        )
            .then((result) => {
                clearTimeout(this.loadTimeout);

                if (!result.success) {
                    throw new Error(result.error || 'Erro ao carregar dados do gráfico');
                }

                // Compatibilidade com api-helper normalizer:
                // - result.data pode vir no formato novo normalizado { success,message,data,... }
                // - ou no formato antigo onde charts está no topo
                let payload = result.data;
                if (payload && payload.data && typeof payload.data === 'object') {
                    // unwrap se estiver encapsulado
                    payload = payload.data;
                }
                const charts = payload && payload.charts ? payload.charts : payload;

                if (
                    charts.sales_trend &&
                    charts.sales_trend.data &&
                    Array.isArray(charts.sales_trend.data)
                ) {
                    this.renderSalesTrendChart(charts.sales_trend.data);
                }

                if (
                    charts.lead_funnel &&
                    charts.lead_funnel.data &&
                    Array.isArray(charts.lead_funnel.data)
                ) {
                    this.renderLeadFunnelChart(charts.lead_funnel.data);
                }

                if (
                    charts.category_performance &&
                    charts.category_performance.data &&
                    Array.isArray(charts.category_performance.data)
                ) {
                    this.renderCategoryPerformanceChart(charts.category_performance.data);
                }

                if (
                    charts.neighborhood_sales &&
                    charts.neighborhood_sales.data &&
                    Array.isArray(charts.neighborhood_sales.data)
                ) {
                    this.renderNeighborhoodChart(charts.neighborhood_sales.data);
                }

                this.isLoading = false;
            })
            .catch((error) => {
                clearTimeout(this.loadTimeout);
                console.error('Erro ao carregar gráficos:', error);

                // Mostra mensagem de erro
                let errorMsg = 'Erro ao carregar gráfico';
                let shouldRetry = true;

                if (error.message.includes('Sessão')) {
                    errorMsg = 'Sessão expirada. Recarregue a página.';
                    shouldRetry = false;
                } else if (error.message.includes('401')) {
                    errorMsg = 'Não autorizado. Recarregue a página.';
                    shouldRetry = false;
                }

                document.querySelectorAll('[id^="chart-"]').forEach((el) => {
                    if (el.innerHTML.includes('Carregando')) {
                        el.innerHTML = `<div class="flex flex-col items-center justify-center h-96 text-yellow-600"><svg class="w-16 h-16 mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><p class="text-center font-medium">${errorMsg}</p></div>`;
                    }
                });

                this.isLoading = false;

                // Tenta novamente apenas se for erro de conexão
                if (shouldRetry && this.retryCount < this.maxRetries) {
                    this.retryCount++;
                    const delay = 2000 * this.retryCount;
                    setTimeout(() => {
                        this.loadCharts();
                    }, delay);
                }
            });
    },

    renderSalesTrendChart(data) {
        try {
            const container = document.getElementById('chart-sales-trend');
            if (!container) {
                return;
            }

            if (!data || data.length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-96 text-gray-400">
                        <svg class="w-24 h-24 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <p class="text-lg font-medium">Nenhuma venda no período</p>
                        <p class="text-sm mt-1">Altere o período para visualizar dados</p>
                    </div>
                `;
                return;
            }

            // Limpa completamente o container antes de renderizar
            container.innerHTML = '';

            const labels = data.map((d) => d.label);
            const vendas = data.map((d) => parseInt(d.vendas) || 0);
            const receita = data.map((d) => parseInt(d.receita) || 0);

            // Calcula valores para exibição
            const maxVendas = Math.max(...vendas, 5);
            const maxReceita = Math.max(...receita, 50000);

            // Formata rótulos para melhor legibilidade
            const formattedLabels = labels.map((label) => {
                const date = new Date(label);
                return date.toLocaleDateString('pt-BR', { month: 'short', day: 'numeric' });
            });

            const options = {
                chart: {
                    type: 'line',
                    height: 420,
                    width: '100%',
                    toolbar: {
                        show: true,
                        tools: {
                            download: true,
                            selection: true,
                            zoom: true,
                            zoomin: true,
                            zoomout: true,
                            pan: true,
                            reset: true,
                        },
                    },
                    animations: {
                        enabled: true,
                        speed: 800,
                        animateGradually: {
                            enabled: true,
                            delay: 150,
                        },
                    },
                    sparkline: { enabled: false },
                    background: 'transparent',
                    foreColor: '#6b7280',
                },
                colors: ['#3b82f6', '#10b981'],
                dataLabels: {
                    enabled: false,
                },
                stroke: {
                    curve: 'smooth',
                    width: [3, 3],
                    lineCap: 'round',
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.45,
                        opacityTo: 0.05,
                        stops: [20, 100, 100, 100],
                    },
                },
                markers: {
                    size: 6,
                    strokeWidth: 2,
                    hover: { size: 8 },
                    fillOpacity: 1,
                    radius: 2,
                },
                grid: {
                    show: true,
                    borderColor: '#e5e7eb',
                    strokeDashArray: 3,
                    xaxis: {
                        lines: {
                            show: false,
                        },
                    },
                },
                xaxis: {
                    categories: formattedLabels,
                    type: 'category',
                    labels: {
                        style: {
                            colors: '#6b7280',
                            fontSize: '12px',
                        },
                    },
                    axisBorder: {
                        color: '#e5e7eb',
                    },
                    axisTicks: {
                        color: '#e5e7eb',
                    },
                },
                yaxis: [
                    {
                        title: {
                            text: 'Número de Vendas',
                            style: {
                                color: '#3b82f6',
                                fontSize: '12px',
                                fontWeight: 600,
                            },
                        },
                        min: 0,
                        max: Math.ceil(maxVendas * 1.1),
                        labels: {
                            style: {
                                colors: '#6b7280',
                            },
                        },
                    },
                    {
                        opposite: true,
                        title: {
                            text: 'Receita (R$)',
                            style: {
                                color: '#10b981',
                                fontSize: '12px',
                                fontWeight: 600,
                            },
                        },
                        min: 0,
                        max: Math.ceil(maxReceita * 1.1),
                        labels: {
                            formatter: (val) => {
                                return 'R$ ' + (val / 1000).toFixed(0) + 'k';
                            },
                            style: {
                                colors: '#6b7280',
                            },
                        },
                    },
                ],
                series: [
                    {
                        name: 'Vendas',
                        data: vendas,
                        yAxisIndex: 0,
                    },
                    {
                        name: 'Receita',
                        data: receita,
                        yAxisIndex: 1,
                    },
                ],
                tooltip: {
                    enabled: true,
                    shared: true,
                    intersect: false,
                    theme: 'light',
                    style: {
                        fontSize: '12px',
                    },
                    y: [
                        {
                            formatter: function (val) {
                                return val + ' venda(s)';
                            },
                        },
                        {
                            formatter: function (val) {
                                return (
                                    'R$ ' +
                                    val.toLocaleString('pt-BR', { maximumFractionDigits: 0 })
                                );
                            },
                        },
                    ],
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    fontSize: '13px',
                    fontFamily: 'inherit',
                    markers: {
                        radius: 0,
                    },
                },
            };

            if (typeof ApexCharts === 'undefined') {
                return;
            }

            if (this.charts.salesTrend) {
                this.charts.salesTrend.destroy();
            }

            this.charts.salesTrend = new ApexCharts(container, options);
            this.charts.salesTrend.render();
        } catch (e) {
            console.error('Erro ao renderizar gráfico de tendência:', e);
        }
    },

    renderLeadFunnelChart(data) {
        try {
            const container = document.getElementById('chart-lead-funnel');
            if (!container) {
                return;
            }

            if (!data || data.length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-96 text-gray-400">
                        <svg class="w-24 h-24 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        <p class="text-lg font-medium">Nenhum lead no período</p>
                        <p class="text-sm mt-1">Altere o período para visualizar dados</p>
                    </div>
                `;
                return;
            }

            // Limpa completamente o container antes de renderizar
            container.innerHTML = '';

            const labels = data.map((d) => d.stage || 'Lead');
            const quantities = data.map((d) => parseInt(d.quantidade) || 0);

            if (typeof ApexCharts === 'undefined') {
                console.error('ApexCharts não definido, tentando novamente em 1s');
                setTimeout(() => this.renderLeadFunnelChart(data), 1000);
                return;
            }

            const options = {
                chart: {
                    type: 'bar',
                    height: 400,
                    toolbar: { show: true },
                    animations: { enabled: true, speed: 800 },
                    sparkline: { enabled: false },
                    background: 'transparent',
                },
                colors: ['#8b5cf6'],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '55%',
                        borderRadius: 6,
                        dataLabels: {
                            position: 'top',
                        },
                    },
                },
                dataLabels: {
                    enabled: true,
                    offsetY: -5,
                    style: {
                        fontSize: '12px',
                        fontWeight: 600,
                    },
                },
                xaxis: {
                    categories: labels,
                    labels: {
                        style: {
                            colors: '#6b7280',
                            fontSize: '12px',
                        },
                    },
                },
                yaxis: {
                    title: {
                        text: 'Quantidade',
                        style: {
                            color: '#6b7280',
                            fontSize: '12px',
                            fontWeight: 600,
                        },
                    },
                    labels: {
                        style: {
                            colors: '#6b7280',
                        },
                    },
                },
                grid: {
                    show: true,
                    borderColor: '#e5e7eb',
                    strokeDashArray: 3,
                },
                tooltip: {
                    theme: 'light',
                    y: {
                        formatter: function (val) {
                            return val + ' lead(s)';
                        },
                    },
                },
                series: [{ name: 'Leads', data: quantities }],
            };

            if (this.charts.leadFunnel) {
                this.charts.leadFunnel.destroy();
            }

            this.charts.leadFunnel = new ApexCharts(container, options);
            this.charts.leadFunnel.render();
        } catch (e) {
            console.error('Erro ao renderizar funil de leads:', e);
        }
    },

    renderCategoryPerformanceChart(data) {
        try {
            const container = document.getElementById('chart-category-performance');
            if (!container) {
                return;
            }

            if (!data || data.length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-96 text-gray-400">
                        <svg class="w-24 h-24 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                        </svg>
                        <p class="text-lg font-medium">Nenhuma categoria</p>
                        <p class="text-sm mt-1">Nenhum imóvel cadastrado neste período</p>
                    </div>
                `;
                return;
            }

            // Limpa completamente o container antes de renderizar
            container.innerHTML = '';

            const map = {
                house: 'Casas',
                apartment: 'Apt.',
                land: 'Terrenos',
                commercial: 'Comercial',
            };
            const labels = data.map((d) => map[d.categoria] || d.categoria);
            const imoveis = data.map((d) => parseInt(d.imoveis) || 0);
            const vendas = data.map((d) => parseInt(d.vendas) || 0);

            const options = {
                chart: {
                    type: 'bar',
                    height: 400,
                    toolbar: { show: true },
                    animations: { enabled: true, speed: 800 },
                    sparkline: { enabled: false },
                    background: 'transparent',
                },
                colors: ['#06b6d4', '#ef4444'],
                plotOptions: {
                    bar: {
                        horizontal: false,
                        columnWidth: '50%',
                        borderRadius: 6,
                        dataLabels: {
                            position: 'top',
                        },
                    },
                },
                dataLabels: {
                    enabled: true,
                    offsetY: -5,
                    style: {
                        fontSize: '11px',
                        fontWeight: 600,
                    },
                },
                xaxis: {
                    categories: labels,
                    labels: {
                        style: {
                            colors: '#6b7280',
                            fontSize: '12px',
                        },
                    },
                },
                yaxis: {
                    title: {
                        text: 'Quantidade',
                        style: {
                            color: '#6b7280',
                            fontSize: '12px',
                            fontWeight: 600,
                        },
                    },
                    labels: {
                        style: {
                            colors: '#6b7280',
                        },
                    },
                },
                grid: {
                    show: true,
                    borderColor: '#e5e7eb',
                    strokeDashArray: 3,
                },
                tooltip: {
                    theme: 'light',
                    y: [
                        {
                            formatter: function (val) {
                                return val + ' imóvel(is)';
                            },
                        },
                        {
                            formatter: function (val) {
                                return val + ' venda(s)';
                            },
                        },
                    ],
                },
                series: [
                    { name: 'Imóveis', data: imoveis },
                    { name: 'Vendas', data: vendas },
                ],
            };

            if (typeof ApexCharts === 'undefined') {
                return;
            }

            if (this.charts.categoryPerf) {
                this.charts.categoryPerf.destroy();
            }

            this.charts.categoryPerf = new ApexCharts(container, options);
            this.charts.categoryPerf.render();
        } catch (e) {
            console.error('Erro ao renderizar desempenho por categoria:', e);
        }
    },

    renderNeighborhoodChart(data) {
        try {
            const container = document.getElementById('chart-neighborhood-sales');
            if (!container) {
                return;
            }

            if (!data || data.length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center h-96 text-gray-400">
                        <svg class="w-24 h-24 mb-4 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 003 16.382V5.618a1 1 0 011.553-.894L9 7m0 13l6.447 3.276A1 1 0 0021 19.382V4.618a1 1 0 00-1.553-.894L15 7m0 13V7m0 0L9 3m6 4l5.447-2.724A1 1 0 0121 4.618v11.764a1 1 0 01-1.553.894L15 13"></path>
                        </svg>
                        <p class="text-lg font-medium">Nenhuma venda no período</p>
                        <p class="text-sm mt-1">Altere o período para visualizar dados</p>
                    </div>
                `;
                return;
            }

            // Limpa completamente o container antes de renderizar
            container.innerHTML = '';

            const labels = data.map((d) => d.bairro || 'Sem bairro');
            const vendas = data.map((d) => parseInt(d.vendas) || 0);

            const options = {
                chart: {
                    type: 'donut',
                    height: 420,
                    toolbar: { show: true },
                    animations: { enabled: true, speed: 800 },
                    sparkline: { enabled: false },
                    background: 'transparent',
                },
                colors: [
                    getComputedStyle(document.documentElement)
                        .getPropertyValue('--cor-primaria')
                        .trim() || '#111827',
                    getComputedStyle(document.documentElement)
                        .getPropertyValue('--cor-secundaria')
                        .trim() || '#10b981',
                    getComputedStyle(document.documentElement)
                        .getPropertyValue('--cor-destaque')
                        .trim() || '#d97706',
                    '#ef4444',
                    '#8b5cf6',
                ],
                labels: labels,
                series: vendas,
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                name: {
                                    show: true,
                                    fontSize: '12px',
                                },
                                value: {
                                    show: true,
                                    fontSize: '14px',
                                    fontWeight: 600,
                                },
                                total: {
                                    show: true,
                                    label: 'Total de Vendas',
                                    fontSize: '12px',
                                    formatter: function () {
                                        return vendas.reduce((a, b) => a + b, 0) + ' vendas';
                                    },
                                },
                            },
                        },
                    },
                },
                dataLabels: {
                    enabled: true,
                    formatter: function (val) {
                        return Math.round(val) + '%';
                    },
                },
                tooltip: {
                    theme: 'light',
                    style: {
                        fontSize: '12px',
                    },
                    y: {
                        formatter: function (val) {
                            return val + ' venda(s)';
                        },
                    },
                },
                legend: {
                    position: 'bottom',
                    fontSize: '12px',
                    fontFamily: 'inherit',
                },
            };

            if (typeof ApexCharts === 'undefined') {
                return;
            }

            if (this.charts.neighborhood) {
                this.charts.neighborhood.destroy();
            }

            this.charts.neighborhood = new ApexCharts(container, options);
            this.charts.neighborhood.render();
        } catch (e) {
            console.error('Erro ao renderizar vendas por bairro:', e);
        }
    },

    setPeriod(period) {
        this.currentPeriod = period;
        this.loadCharts();
    },
};

// Carrega ApexCharts
function initDashboardCharts() {
    if (typeof ApexCharts !== 'undefined') {
        window.DashboardCharts.init();
    } else {
        // Fallback: tenta carregar se ainda não carregado
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js';
        script.async = false;
        script.onerror = function () {
            console.error('Erro ao carregar ApexCharts do CDN');
        };
        script.onload = function () {
            if (window.DashboardCharts) {
                window.DashboardCharts.init();
            }
        };
        document.head.appendChild(script);
    }
}

// Inicializa
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(initDashboardCharts, 500);
    });
} else {
    setTimeout(initDashboardCharts, 500);
}
