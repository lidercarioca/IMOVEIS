/**
 * Portfolio Statistics Manager
 * Carrega e exibe análise de portfolio com tipos, valores e tendências
 */

window.PortfolioStats = {
    isLoading: false,
    
    /**
     * Inicializa o Portfolio Stats
     */
    init() {
        const container = document.getElementById('portfolio-stats-container');
        if (!container) return;
        
        this.loadStats();
    },
    
    /**
     * Carrega estatísticas do servidor
     */
    loadStats() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        fetch(`/api/relatorios/getPortfolioStats.php`, {
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
            
            this.renderStats(data);
            
            this.isLoading = false;
        })
        .catch(error => {
            console.error('Erro ao carregar portfolio:', error);
            this.isLoading = false;
            this.showError('Erro ao carregar estatísticas');
        });
    },
    
    /**
     * Renderiza as estatísticas
     */
    renderStats(data) {
        const container = document.getElementById('portfolio-stats-container');
        if (!container) return;
        
        // Renderizar cards de resumo
        this.renderPortfolioSummary(data.portfolio);
        
        // Renderizar distribuição por tipo
        this.renderByType(data.by_type);
        
        // Renderizar distribuição por bairro
        this.renderByNeighborhood(data.by_neighborhood);
        
        // Renderizar faixas de preço
        this.renderPriceBands(data.by_price_band);
    },
    
    /**
     * Renderiza resumo do portfolio
     */
    renderPortfolioSummary(portfolio) {
        const summaryEl = document.getElementById('portfolio-summary');
        if (!summaryEl) return;
        
        summaryEl.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Total de Imóveis -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border-l-4 border-blue-600">
                    <p class="text-sm text-gray-600">Total de Imóveis</p>
                    <p class="text-3xl font-bold text-blue-600 mt-1">${portfolio.total_properties}</p>
                    <p class="text-xs text-gray-500 mt-2">No seu portfolio</p>
                </div>
                
                <!-- Valor Total -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border-l-4 border-green-600">
                    <p class="text-sm text-gray-600">Valor Total</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">R$ ${this.formatPrice(portfolio.total_value)}</p>
                    <p class="text-xs text-gray-500 mt-2">Investimento em imóveis</p>
                </div>
                
                <!-- Preço Médio -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-4 border-l-4 border-purple-600">
                    <p class="text-sm text-gray-600">Preço Médio</p>
                    <p class="text-2xl font-bold text-purple-600 mt-1">R$ ${this.formatPrice(portfolio.average_price)}</p>
                    <p class="text-xs text-gray-500 mt-2">Por imóvel</p>
                </div>
                
                <!-- Ativos -->
                <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-lg p-4 border-l-4 border-orange-600">
                    <p class="text-sm text-gray-600">Valor Ativo</p>
                    <p class="text-2xl font-bold text-orange-600 mt-1">R$ ${this.formatPrice(portfolio.active_value)}</p>
                    <p class="text-xs text-gray-500 mt-2">Em comercialização</p>
                </div>
                
                <!-- Vendidos -->
                <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-lg p-4 border-l-4 border-emerald-600">
                    <p class="text-sm text-gray-600">Valor Vendido</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">R$ ${this.formatPrice(portfolio.sold_value)}</p>
                    <p class="text-xs text-gray-500 mt-2">Já comercializados</p>
                </div>
                
                <!-- Inativos -->
                <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-4 border-l-4 border-red-600">
                    <p class="text-sm text-gray-600">Imóveis Inativos</p>
                    <p class="text-3xl font-bold text-red-600 mt-1">${portfolio.inactive_properties}</p>
                    <p class="text-xs text-gray-500 mt-2">Fora do catálogo</p>
                </div>
            </div>
        `;
    },
    
    /**
     * Renderiza distribuição por tipo
     */
    renderByType(types) {
        const typeEl = document.getElementById('portfolio-by-type');
        if (!typeEl) return;
        
        if (!types || types.length === 0) {
            typeEl.innerHTML = '<p class="text-center text-gray-500 py-4">Nenhum tipo de imóvel registrado</p>';
            return;
        }
        
        const html = types.map((type, idx) => `
            <div class="bg-white rounded-lg p-4 border border-gray-200 hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <h4 class="font-semibold text-gray-800">${type.type}</h4>
                    <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">${type.quantity}</span>
                </div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <p class="text-gray-500">Preço Médio</p>
                        <p class="font-semibold text-gray-800">R$ ${this.formatPrice(type.average_price)}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Faixa</p>
                        <p class="font-semibold text-gray-800">R$ ${this.formatPrice(type.min_price)} - R$ ${this.formatPrice(type.max_price)}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Ativos</p>
                        <p class="font-semibold text-green-600">${type.active}</p>
                    </div>
                    <div>
                        <p class="text-gray-500">Vendidos</p>
                        <p class="font-semibold text-blue-600">${type.sold}</p>
                    </div>
                </div>
            </div>
        `).join('');
        
        typeEl.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">${html}</div>`;
    },
    
    /**
     * Renderiza distribuição por bairro
     */
    renderByNeighborhood(neighborhoods) {
        const neighborhoodEl = document.getElementById('portfolio-by-neighborhood');
        if (!neighborhoodEl) return;
        
        if (!neighborhoods || neighborhoods.length === 0) {
            neighborhoodEl.innerHTML = '<p class="text-center text-gray-500 py-4">Nenhum bairro registrado</p>';
            return;
        }
        
        const html = neighborhoods.map(n => {
            const percentage = Math.round((n.quantity / neighborhoods.reduce((sum, x) => sum + x.quantity, 0)) * 100);
            return `
                <div class="p-3 border-b last:border-b-0 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-medium text-gray-800">${n.name}</p>
                        <span class="text-sm font-bold text-blue-600">${n.quantity} imóvel${n.quantity !== 1 ? 's' : ''}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full" style="width: ${percentage}%"></div>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-xs text-gray-600">
                        <span>Preço médio: R$ ${this.formatPrice(n.average_price)}</span>
                        <span>Ativos: ${n.active}</span>
                        <span>Vendidos: ${n.sold}</span>
                    </div>
                </div>
            `;
        }).join('');
        
        neighborhoodEl.innerHTML = `<div class="bg-white rounded-lg border border-gray-200">${html}</div>`;
    },
    
    /**
     * Renderiza faixas de preço
     */
    renderPriceBands(bands) {
        const bandEl = document.getElementById('portfolio-price-bands');
        if (!bandEl) return;
        
        if (!bands || bands.length === 0) {
            bandEl.innerHTML = '<p class="text-center text-gray-500 py-4">Nenhum dado disponível</p>';
            return;
        }
        
        const total = bands.reduce((sum, b) => sum + b.quantity, 0);
        const maxQuantity = Math.max(...bands.map(b => b.quantity), 1);
        
        const html = bands.map(band => {
            const percentage = total > 0 ? Math.round((band.quantity / total) * 100) : 0;
            const barWidth = (band.quantity / maxQuantity) * 100;
            
            return `
                <div class="mb-4 last:mb-0">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-sm font-medium text-gray-700">${band.label}</p>
                        <span class="text-xs text-gray-500">${band.quantity} (${percentage}%)</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 h-3 rounded-full transition-all duration-500" 
                             style="width: ${barWidth}%"></div>
                    </div>
                </div>
            `;
        }).join('');
        
        bandEl.innerHTML = `<div class="bg-white rounded-lg p-4 border border-gray-200">${html}</div>`;
    },
    
    /**
     * Formata preço
     */
    formatPrice(price) {
        if (price >= 1000000) {
            return (price / 1000000).toFixed(1).replace('.', ',') + 'M';
        }
        if (price >= 1000) {
            return (price / 1000).toFixed(0) + 'k';
        }
        return price.toFixed(0);
    },
    
    /**
     * Mostra mensagem de erro
     */
    showError(message) {
        const container = document.getElementById('portfolio-stats-container');
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
        PortfolioStats.init();
    }, 500);
});
