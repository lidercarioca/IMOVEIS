/**
 * Dashboard KPI Variation Renderer
 * Renderiza indicadores de variação nos KPIs
 * Date: 2026-01-16
 */

window.DashboardVariation = {
    /**
     * Renderiza um KPI com indicador de variação
     */
    renderKPIWithVariation(elementId, kpiData, formatFn = null) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        // Se é objeto com variação
        if (typeof kpiData === 'object' && kpiData.current !== undefined) {
            const formattedValue = formatFn ? formatFn(kpiData.current) : kpiData.current;
            element.textContent = formattedValue;
            
            // Adicionar badge de variação
            const card = element.closest('[data-kpi]');
            if (card && kpiData.variation !== undefined) {
                this.addVariationBadge(card, kpiData);
            }
        } else {
            // Se é valor simples
            const formattedValue = formatFn ? formatFn(kpiData) : kpiData;
            element.textContent = formattedValue;
        }
    },
    
    /**
     * Adiciona badge de variação ao card do KPI
     */
    addVariationBadge(card, kpiData) {
        // Remover badge anterior se existir
        const oldBadge = card.querySelector('.variation-badge');
        if (oldBadge) oldBadge.remove();
        
        // Criar novo badge
        const badge = document.createElement('div');
        badge.className = 'variation-badge text-xs font-semibold mt-2 px-3 py-1 rounded inline-block';
        
        const variation = kpiData.variation;
        const trend = kpiData.trend;
        const isCycleTime = card.getAttribute('data-kpi') === 'cycle'; // Para ciclo, menor é melhor
        
        // Cores baseadas em trend
        if (trend === 'up' && !isCycleTime) {
            badge.className += ' bg-green-100 text-green-700';
        } else if (trend === 'down' && !isCycleTime) {
            badge.className += ' bg-red-100 text-red-700';
        } else if (trend === 'down' && isCycleTime) {
            badge.className += ' bg-green-100 text-green-700'; // Ciclo reduzido é bom
        } else {
            badge.className += ' bg-red-100 text-red-700';
        }
        
        // Ícone
        const arrow = trend === 'up' ? '↑' : '↓';
        const sign = variation >= 0 ? '+' : '';
        
        badge.innerHTML = `<span class="mr-1">${arrow}</span>${sign}${variation}%`;
        
        // Inserir badge
        const targetElement = card.querySelector('h3, p');
        if (targetElement) {
            targetElement.parentNode.insertBefore(badge, targetElement.nextSibling);
        } else {
            card.appendChild(badge);
        }
        
        // Animação
        badge.style.animation = 'slideDown 0.3s ease-out';
    },
    
    /**
     * Adiciona CSS para animações
     */
    injectStyles() {
        if (document.getElementById('dashboard-variation-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'dashboard-variation-styles';
        style.textContent = `
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-5px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .variation-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.25rem;
                animation: slideDown 0.3s ease-out;
                font-weight: 600;
                white-space: nowrap;
            }
            
            [data-kpi].updating {
                animation: pulse 0.5s ease-in-out;
            }
            
            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.7;
                }
            }
        `;
        document.head.appendChild(style);
    }
};

// Injetar estilos ao carregar
document.addEventListener('DOMContentLoaded', () => {
    DashboardVariation.injectStyles();
});
