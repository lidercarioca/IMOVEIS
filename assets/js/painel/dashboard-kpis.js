/**
 * Dashboard KPIs Manager
 * Carrega e atualiza os dados dos KPIs do dashboard executivo
 */

window.DashboardKPIs = {
    currentPeriod: 7,
    updateInterval: null,
    isLoading: false,
    autoRefreshEnabled: true,
    lastDataCache: null,
    lastCacheTime: 0,
    cacheTimeout: 2000, // Cache de 2 segundos
    requestCount: 0,
    lastErrorTime: 0,

    /**
     * Inicializa o sistema de KPIs
     */
    init() {
        // Carrega KPIs ao abrir dashboard
        this.loadKPIs();

        // Auto-refresh a cada 5 minutos
        this.startAutoRefresh();

        // Monitorar visibilidade da página
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // Página voltou ao foco - fazer refresh dos dados
                this.loadKPIs();
            }
        });
    },

    /**
     * Define o período e recarrega dados
     */
    setPeriod(period) {
        this.currentPeriod = period;

        // Recarrega todos os dados
        this.loadKPIs();
    },

    /**
     * Carrega todos os KPIs do backend
     */
    loadKPIs() {
        if (this.isLoading) {
            console.debug('⏳ Request já em progresso, aguardando...');
            return;
        }

        // Verificar cache
        const now = Date.now();
        if (this.lastDataCache && now - this.lastCacheTime < this.cacheTimeout) {
            console.debug(
                '💾 Usando cache de dados (válido por mais',
                Math.round(this.cacheTimeout - (now - this.lastCacheTime)),
                'ms)'
            );
            return;
        }

        this.isLoading = true;
        this.requestCount++;
        const requestId = this.requestCount;

        this.setLoadingState(true);

        apiCall(`/api/relatorios/getDashboardKPIs.php?period=${this.currentPeriod}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache',
            },
        })
            .then((result) => {
                if (!result.success) {
                    throw new Error(result.error || 'Erro ao carregar KPIs');
                }

                const payload = result.data;
                const payloadBody = payload && payload.data ? payload.data : payload;
                if (!payloadBody || typeof payloadBody !== 'object') {
                    throw new Error('Resposta inválida ao carregar KPIs');
                }

                this.lastDataCache = payloadBody;
                this.lastCacheTime = Date.now();

                this.renderKPIs(payloadBody.kpis || {});
                this.renderTopBrokers(payloadBody.top_brokers || payloadBody.topBrokers || []);

                this.isLoading = false;
                this.setLoadingState(false);
                this.lastErrorTime = 0;
            })
            .catch((error) => {
                const now = Date.now();
                if (now - this.lastErrorTime > 5000) {
                    this.showError('Erro ao carregar dados do dashboard. Verifique sua conexão.');
                    this.lastErrorTime = now;
                }

                this.isLoading = false;
                this.setLoadingState(false);
            });
    },

    /**
     * Renderiza os cards de KPI
     */
    renderKPIs(kpis) {
        // Total de Imóveis
        const totalPropertiesCard = document.getElementById('total-properties');
        if (totalPropertiesCard) {
            totalPropertiesCard.textContent = this.formatNumber(kpis.total_properties);
            this.addUpdateAnimation(totalPropertiesCard.closest('[data-kpi]'));
        }

        // Total de Leads (com variação)
        const totalLeadsCard = document.getElementById('total-leads-dashboard');
        if (totalLeadsCard) {
            if (typeof kpis.total_leads === 'object') {
                DashboardVariation.renderKPIWithVariation(
                    'total-leads-dashboard',
                    kpis.total_leads,
                    (v) => this.formatNumber(v)
                );
            } else {
                totalLeadsCard.textContent = this.formatNumber(kpis.total_leads);
            }
            this.addUpdateAnimation(totalLeadsCard.closest('[data-kpi]'));
        }

        // Total de Visitas
        const totalVisitsCard = document.getElementById('total-visits-dashboard');
        if (totalVisitsCard) {
            const visitsValue = parseInt(kpis.total_visits) || 0;
            totalVisitsCard.textContent = this.formatNumber(visitsValue);
            this.addUpdateAnimation(totalVisitsCard.closest('[data-kpi]'));
        }

        // Total de Vendas (com variação)
        const totalSalesCard = document.getElementById('total-sales-dashboard');
        if (totalSalesCard) {
            if (typeof kpis.total_sales === 'object') {
                DashboardVariation.renderKPIWithVariation(
                    'total-sales-dashboard',
                    kpis.total_sales,
                    (v) => this.formatNumber(v)
                );
            } else {
                totalSalesCard.textContent = this.formatNumber(kpis.total_sales);
            }
            this.addUpdateAnimation(totalSalesCard.closest('[data-kpi]'));
        }

        // Taxa de Conversão (com variação)
        const conversionRateCard = document.getElementById('conversion-rate-kpi');
        if (conversionRateCard) {
            if (typeof kpis.conversion_rate === 'object') {
                DashboardVariation.renderKPIWithVariation(
                    'conversion-rate-kpi',
                    kpis.conversion_rate,
                    (v) => v.toFixed(2) + '%'
                );
            } else {
                conversionRateCard.textContent = kpis.conversion_rate.toFixed(2) + '%';
            }
            this.addUpdateAnimation(conversionRateCard.closest('[data-kpi]'));
        }

        // Ticket Médio (com variação)
        const avgTicketCard = document.getElementById('average-ticket-kpi');
        if (avgTicketCard) {
            if (typeof kpis.average_ticket === 'object') {
                DashboardVariation.renderKPIWithVariation(
                    'average-ticket-kpi',
                    kpis.average_ticket,
                    (v) => this.formatCurrency(v)
                );
            } else {
                avgTicketCard.textContent = this.formatCurrency(kpis.average_ticket);
            }
            this.addUpdateAnimation(avgTicketCard.closest('[data-kpi]'));
        }

        // Tempo Médio de Venda (com variação)
        const avgCycleDaysCard = document.getElementById('average-cycle-days-kpi');
        if (avgCycleDaysCard) {
            if (typeof kpis.average_cycle_days === 'object') {
                DashboardVariation.renderKPIWithVariation(
                    'average-cycle-days-kpi',
                    kpis.average_cycle_days,
                    (v) => v + ' dias'
                );
            } else {
                avgCycleDaysCard.textContent = kpis.average_cycle_days + ' dias';
            }
            this.addUpdateAnimation(avgCycleDaysCard.closest('[data-kpi]'));
        }

        // Receita Total (com variação)
        const totalRevenueCard = document.getElementById('total-revenue-kpi');
        if (totalRevenueCard) {
            if (typeof kpis.total_revenue === 'object') {
                DashboardVariation.renderKPIWithVariation(
                    'total-revenue-kpi',
                    kpis.total_revenue,
                    (v) => this.formatCurrency(v)
                );
            } else {
                totalRevenueCard.textContent = this.formatCurrency(kpis.total_revenue);
            }
            this.addUpdateAnimation(totalRevenueCard.closest('[data-kpi]'));
        }

        // Imóveis Paralizados
        const stoppedPropertiesCard = document.getElementById('stopped-properties-kpi');
        if (stoppedPropertiesCard) {
            stoppedPropertiesCard.textContent = this.formatNumber(kpis.stopped_properties);
            this.addUpdateAnimation(stoppedPropertiesCard.closest('[data-kpi]'));
        }
    },

    /**
     * Renderiza a tabela de top vendedores
     */
    renderTopBrokers(brokers) {
        const container = document.getElementById('top-brokers-container');
        if (!container) return;

        if (!brokers || brokers.length === 0) {
            container.innerHTML =
                '<p class="text-gray-500 text-center py-4">Nenhum dado disponível</p>';
            return;
        }

        const html = brokers
            .map(
                (broker, index) => `
            <div class="flex items-center justify-between p-3 hover:bg-gray-50 transition-colors duration-200 border-b last:border-b-0">
                <div class="flex items-center gap-3 flex-1">
                    <div class="flex-shrink-0 text-center">
                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-semibold text-sm">
                            ${index + 1}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">${broker.name}</p>
                        <p class="text-xs text-gray-500">${broker.leads} leads | Taxa: ${broker.taxa_conversao}%</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900">${this.formatNumber(broker.vendas)} vendas</p>
                    <p class="text-xs text-green-600">${this.formatCurrency(broker.receita)}</p>
                </div>
            </div>
        `
            )
            .join('');

        container.innerHTML = html;
    },

    /**
     * Define estado de carregamento
     */
    setLoadingState(loading) {
        const kpiCards = document.querySelectorAll('[data-kpi]');
        kpiCards.forEach((card) => {
            if (loading) {
                card.classList.add('opacity-50');
            } else {
                card.classList.remove('opacity-50');
            }
        });
    },

    /**
     * Adiciona animação ao atualizar um card
     */
    addUpdateAnimation(card) {
        if (!card) return;

        card.style.animation = 'none';
        setTimeout(() => {
            card.style.animation = 'pulse 0.5s ease-in-out';
        }, 10);

        setTimeout(() => {
            card.style.animation = 'none';
        }, 510);
    },

    /**
     * Formata número para padrão brasileiro
     */
    formatNumber(num) {
        return new Intl.NumberFormat('pt-BR').format(num);
    },

    /**
     * Formata valor monetário
     */
    formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value);
    },

    /**
     * Mostra mensagem de erro
     */
    showError(message) {
        // Pode ser expandido para mostrar um toast ou notificação visual
    },

    /**
     * Inicia auto-refresh
     */
    startAutoRefresh() {
        if (this.autoRefreshEnabled) {
            // Atualiza a cada 5 minutos
            this.updateInterval = setInterval(
                () => {
                    this.loadKPIs();
                },
                5 * 60 * 1000
            );
        }
    },

    /**
     * Para auto-refresh
     */
    stopAutoRefresh() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    },

    /**
     * Alterna auto-refresh
     */
    toggleAutoRefresh(enable) {
        this.autoRefreshEnabled = enable;
        if (enable) {
            this.startAutoRefresh();
        } else {
            this.stopAutoRefresh();
        }
    },
};

// Inicializa quando o dashboard fica visível
document.addEventListener('DOMContentLoaded', function () {
    // Aguarda que o DOM esteja totalmente pronto
    setTimeout(() => {
        DashboardKPIs.init();
    }, 500);
});

// Suporta abra/fecha da aba dashboard
document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
        DashboardKPIs.stopAutoRefresh();
    } else {
        DashboardKPIs.startAutoRefresh();
    }
});
