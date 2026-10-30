/**
 * Leads Funnel Manager
 * Carrega e exibe funil de leads com análise por status
 */

window.LeadsFunnel = {
    isLoading: false,
    
    /**
     * Inicializa o Leads Funnel
     */
    init() {
        const container = document.getElementById('leads-funnel-container');
        if (!container) return;
        
        this.loadFunnel();
        
        // Auto-refresh a cada 5 minutos
        setInterval(() => this.loadFunnel(), 5 * 60 * 1000);
    },
    
    /**
     * Carrega funil do servidor
     */
    loadFunnel() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        fetch(`/api/relatorios/getLeadsFunnel.php`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (!data.success) throw new Error(data.error);
            
            this.renderFunnel(data);
            
            this.isLoading = false;
        })
        .catch(error => {
            console.error('Erro ao carregar funil:', error);
            this.isLoading = false;
            this.showError('Erro ao carregar funil de leads');
        });
    },
    
    /**
     * Renderiza o funil na tela
     */
    renderFunnel(data) {
        const container = document.getElementById('leads-funnel-container');
        if (!container) return;
        
        // Renderizar resumo
        this.renderSummary(data.summary);
        
        // Renderizar funil visual
        this.renderFunnelChart(data.funnel, data.summary.total_leads);
        
        // Renderizar estatísticas por status
        this.renderStatusStats(data.funnel);
        
        // Renderizar leads por origem
        if (data.sources && data.sources.length > 0) {
            this.renderSources(data.sources);
        }
    },
    
    /**
     * Renderiza resumo do funil
     */
    renderSummary(summary) {
        const summaryEl = document.getElementById('funnel-summary');
        if (!summaryEl) return;
        
        summaryEl.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-blue-600 mb-2">Total de Leads</p>
                            <p class="text-4xl font-bold text-blue-700">${summary.total_leads}</p>
                        </div>
                        <div class="w-14 h-14 rounded-full bg-blue-200 flex items-center justify-center">
                            <i class="fas fa-users text-2xl text-blue-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 border border-green-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-green-600 mb-2">Ativos</p>
                            <p class="text-4xl font-bold text-green-700">${summary.active_leads}</p>
                        </div>
                        <div class="w-14 h-14 rounded-full bg-green-200 flex items-center justify-center">
                            <i class="fas fa-check-circle text-2xl text-green-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-6 border border-emerald-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-emerald-600 mb-2">Convertidos</p>
                            <p class="text-4xl font-bold text-emerald-700">${summary.converted_leads}</p>
                        </div>
                        <div class="w-14 h-14 rounded-full bg-emerald-200 flex items-center justify-center">
                            <i class="fas fa-star text-2xl text-emerald-600"></i>
                        </div>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6 border border-purple-200 shadow-sm hover:shadow-md transition-shadow">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-purple-600 mb-2">Taxa Conversão</p>
                            <p class="text-4xl font-bold text-purple-700">${summary.conversion_rate_label}</p>
                        </div>
                        <div class="w-14 h-14 rounded-full bg-purple-200 flex items-center justify-center">
                            <i class="fas fa-chart-line text-2xl text-purple-600"></i>
                        </div>
                    </div>
                </div>
            </div>
        `;
    },
    
    /**
     * Renderiza gráfico visual do funil
     */
    renderFunnelChart(funnel, total) {
        const chartEl = document.getElementById('funnel-chart');
        if (!chartEl) return;
        
        if (!funnel || funnel.length === 0 || total === 0) {
            chartEl.innerHTML = '<div class="text-center py-8 text-gray-500"><i class="fas fa-inbox text-2xl mb-3"></i><p>Nenhum lead no funil</p></div>';
            return;
        }
        
        let html = '<div class="space-y-4">';
        
        funnel.forEach((stage, index) => {
            if (!stage.total || total === 0) return;
            
            const percentage = (stage.total / total) * 100;
            const width = Math.max(percentage, 8);
            const positioning = ((100 - percentage) / 2);
            
            // Converter hex para rgba para o gradiente
            const hexToRgba = (hex, alpha = 1) => {
                const r = parseInt(hex.slice(1, 3), 16);
                const g = parseInt(hex.slice(3, 5), 16);
                const b = parseInt(hex.slice(5, 7), 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            };
            
            const colorRgba = hexToRgba(stage.color, 0.7);
            
            html += `
                <div class="flex flex-col items-center group">
                    <div style="position: relative; width: 100%; text-align: center; margin-bottom: 8px;">
                        <div style="width: ${width}%; margin-left: ${positioning}%; background: linear-gradient(135deg, ${stage.color} 0%, ${colorRgba} 100%);
                                   border-radius: 8px; padding: 16px 12px; cursor: pointer; transition: all 0.3s; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                             class="hover:shadow-xl hover:scale-105 group-hover:brightness-110"
                             title="${stage.label}">
                            <div style="color: white; font-weight: 600; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="fas fa-${stage.icon}"></i> ${stage.label}
                            </div>
                            <div style="color: rgba(255,255,255,0.95); font-size: 13px; margin-top: 6px; font-weight: 500;">
                                ${stage.total} leads · ${Math.round(percentage)}%
                            </div>
                        </div>
                    </div>
                    <div style="color: #888; font-size: 12px; font-weight: 500;">
                        ⏱ ${stage.average_days_in_status} dias
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        chartEl.innerHTML = html;
    },
    
    /**
     * Renderiza estatísticas por status
     */
    renderStatusStats(funnel) {
        const statsEl = document.getElementById('funnel-status-stats');
        if (!statsEl) return;
        
        const html = funnel.map(stage => `
            <div class="bg-white rounded-xl p-5 border border-gray-200 hover:shadow-lg transition-all" 
                 style="border-left: 4px solid ${stage.color};">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-lg flex items-center justify-center text-white font-bold text-lg"
                             style="background: linear-gradient(135deg, ${stage.color}, rgba(${stage.color.replace('#', '')}, 0.7));">
                            <i class="fas fa-${stage.icon}"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 text-base">${stage.label}</h4>
                            <p class="text-xs text-gray-500">Estágio do funil</p>
                        </div>
                    </div>
                    <span class="text-3xl font-bold" style="color: ${stage.color};">${stage.total}</span>
                </div>
                <div class="flex items-center gap-4 pt-3 border-t border-gray-100">
                    <div class="flex items-center text-gray-600 text-sm">
                        <i class="fas fa-clock text-gray-400 mr-2"></i>
                        <span>Tempo médio: <strong>${stage.average_days_in_status} dias</strong></span>
                    </div>
                </div>
                </p>
                ${stage.last_lead ? `
                    <p class="text-xs text-gray-500 mt-2">
                        Último: ${new Date(stage.last_lead).toLocaleDateString('pt-BR')}
                    </p>
                ` : ''}
            </div>
        `).join('');
        
        statsEl.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">${html}</div>`;
    },
    
    /**
     * Renderiza leads por origem
     */
    renderSources(sources) {
        const sourcesEl = document.getElementById('funnel-sources');
        if (!sourcesEl) return;
        
        const html = sources.map(source => `
            <div class="p-4 border-b last:border-b-0 hover:bg-gray-50 transition-colors">
                <div class="flex items-center justify-between mb-2">
                    <p class="font-medium text-gray-800">${source.name}</p>
                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-sm font-bold">${source.total}</span>
                </div>
                <div class="grid grid-cols-3 gap-2 text-sm text-gray-600 mb-2">
                    <span>% Total: <span class="font-semibold">${source.percentage}%</span></span>
                    <span>Conv: <span class="font-semibold text-green-600">${source.converted}</span></span>
                    <span>Taxa: <span class="font-semibold text-purple-600">${source.conversion_rate}%</span></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full" 
                         style="width: ${source.percentage}%"></div>
                </div>
            </div>
        `).join('');
        
        sourcesEl.innerHTML = `
            <div class="bg-white rounded-lg border border-gray-200">
                ${html}
            </div>
        `;
    },
    
    /**
     * Mostra mensagem de erro
     */
    showError(message) {
        const container = document.getElementById('leads-funnel-container');
        if (container) {
            container.innerHTML = `
                <div class="text-center py-8 text-red-500">
                    <i class="fas fa-exclamation-circle text-4xl mb-3"></i>
                    <p>${message}</p>
                </div>
            `;
        }
    }
};

// Inicializa quando o DOM está pronto
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        LeadsFunnel.init();
    }, 500);
});
