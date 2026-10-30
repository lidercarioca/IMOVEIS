/**
 * Goals & Targets Manager
 * Carrega e exibe metas e objetivos com progresso visual
 */

window.GoalsAndTargets = {
    isLoading: false,
    currentPeriod: 30,

    getCssColor(name, fallback) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    },
    
    /**
     * Inicializa o Goals Manager
     */
    init() {
        const container = document.getElementById('goals-targets-container');
        if (!container) return;
        
        // Apply initial styling to period buttons
        this.updateButtonStyles();
        
        this.loadGoals();
        
        // Event listeners para seletor de período
        const periodButtons = document.querySelectorAll('[data-goals-period]');
        periodButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const period = parseInt(btn.getAttribute('data-goals-period'));
                this.setPeriod(period);
            });
        });
    },
    
    /**
     * Atualiza estilos dos botões com base no período selecionado
     */
    updateButtonStyles() {
        const primaryColor = this.getCssColor('--cor-primaria', '#111827');
        const textColor = '#ffffff';
        const inactiveBg = '#e5e7eb';
        const inactiveText = '#4b5563';

        document.querySelectorAll('[data-goals-period]').forEach(btn => {
            btn.classList.remove('bg-blue-600', 'text-white', 'bg-gray-200', 'text-gray-700');
            btn.style.backgroundColor = '';
            btn.style.color = '';
            btn.style.borderColor = '';

            if (parseInt(btn.getAttribute('data-goals-period')) === this.currentPeriod) {
                btn.style.backgroundColor = primaryColor;
                btn.style.color = textColor;
            } else {
                btn.style.backgroundColor = inactiveBg;
                btn.style.color = inactiveText;
            }
        });
    },
    
    /**
     * Define período e recarrega
     */
    setPeriod(period) {
        this.currentPeriod = period;
        
        // Atualizar visual dos botões
        this.updateButtonStyles();
        
        this.loadGoals();
    },
    
    /**
     * Carrega metas do servidor
     */
    loadGoals() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        fetch(`/api/relatorios/getGoalsAndTargets.php?period=${this.currentPeriod}`, {
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
            
            this.renderGoals(data);
            
            this.isLoading = false;
        })
        .catch(error => {
            console.error('Erro ao carregar metas:', error);
            this.isLoading = false;
            this.showError('Erro ao carregar metas');
        });
    },
    
    /**
     * Renderiza as metas na tela
     */
    renderGoals(data) {
        const container = document.getElementById('goals-targets-container');
        if (!container) return;
        
        const goals = data.goals;
        const overall = data.overall;
        
        // Renderizar performance geral
        this.renderOverallPerformance(overall);
        
        // Renderizar cards de metas
        const goalsGrid = document.getElementById('goals-grid');
        if (goalsGrid) {
            if (!goals || goals.length === 0) {
                goalsGrid.innerHTML = '<div class="text-center py-8 text-gray-500">Nenhuma meta disponível</div>';
            } else {
                const html = goals.map(goal => this.renderGoalCard(goal)).join('');
                goalsGrid.innerHTML = html;
            }
        }
    },
    
    /**
     * Renderiza performance geral
     */
    renderOverallPerformance(overall) {
        const overallEl = document.getElementById('goals-overall-progress');
        if (!overallEl) return;
        
        const statusText = {
            'completed': '🎉 Metas Atingidas!',
            'on_track': '✅ No Caminho Certo',
            'at_risk': '⚠️ Atenção Necessária'
        };
        
        const statusColor = {
            'completed': this.getCssColor('--cor-secundaria', '#10b981'),
            'on_track': this.getCssColor('--cor-primaria', '#111827'),
            'at_risk': this.getCssColor('--cor-destaque', '#d97706')
        };
        
        overallEl.innerHTML = `
            <div class="text-center">
                <div style="position: relative; width: 120px; height: 120px; margin: 0 auto;">
                    <svg width="120" height="120" style="transform: rotate(-90deg);">
                        <!-- Background circle -->
                        <circle cx="60" cy="60" r="50" fill="none" stroke="#e5e7eb" stroke-width="8"/>
                        <!-- Progress circle -->
                        <circle cx="60" cy="60" r="50" fill="none" stroke="${statusColor[overall.status]}" 
                                stroke-width="8" stroke-dasharray="${overall.percent * 3.14}" 
                                stroke-dashoffset="0" stroke-linecap="round"/>
                    </svg>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                        <p style="font-size: 32px; font-weight: bold; color: ${statusColor[overall.status]};">${overall.percent}%</p>
                    </div>
                </div>
                <p class="text-lg font-semibold text-gray-800 mt-4">${statusText[overall.status]}</p>
                <p class="text-sm text-gray-600 mt-2">
                    <span style="color: var(--cor-secundaria); font-weight: 600;">${overall.completed_goals} atingidas</span> • 
                    <span style="color: var(--cor-primaria); font-weight: 600;">${overall.on_track_goals} no caminho</span> • 
                    <span style="color: var(--cor-destaque); font-weight: 600;">${overall.at_risk_goals} em risco</span>
                </p>
            </div>
        `;
    },
    
    /**
     * Renderiza um card individual de meta
     */
    renderGoalCard(goal) {
        const statusIcons = {
            'completed': 'check-circle',
            'on_track': 'arrow-up',
            'at_risk': 'exclamation-triangle'
        };
        
        const statusTexts = {
            'completed': 'Meta Atingida!',
            'on_track': 'No Caminho',
            'at_risk': 'Em Risco'
        };
        
        const statusColors = {
            'completed': { bg: 'rgba(16, 185, 129, 0.1)', text: 'var(--cor-secundaria)', border: 'var(--cor-secundaria)' },
            'on_track': { bg: 'rgba(17, 24, 39, 0.05)', text: 'var(--cor-primaria)', border: 'var(--cor-primaria)' },
            'at_risk': { bg: 'rgba(217, 119, 6, 0.1)', text: 'var(--cor-destaque)', border: 'var(--cor-destaque)' }
        };
        
        const colors = statusColors[goal.status];
        const icon = statusIcons[goal.status];
        const statusText = statusTexts[goal.status];
        
        const realDisplay = goal.unit === 'currency' ? goal.real_formatted : 
                            goal.unit === 'percent' ? goal.real.toFixed(2) + '%' : 
                            goal.real;
        const targetDisplay = goal.unit === 'currency' ? goal.target_formatted : 
                              goal.unit === 'percent' ? goal.target.toFixed(2) + '%' : 
                              goal.target;
        
        return `
            <div class="bg-white rounded-lg shadow-md p-6 border-t-4" style="border-top-color: ${goal.color};">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center text-white"
                             style="background-color: ${goal.color};">
                            <i class="fas fa-${goal.icon}"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800">${goal.title}</p>
                            <p class="text-xs text-gray-500">Meta do período</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-bold" 
                          style="background-color: ${colors.bg}; color: ${colors.text};">
                        <i class="fas fa-${icon} mr-1"></i> ${statusText}
                    </span>
                </div>
                
                <!-- Valores -->
                <div class="mb-4">
                    <div class="flex justify-between mb-2">
                        <span class="text-sm text-gray-600">Realizado</span>
                        <span class="text-sm font-bold text-gray-800">${realDisplay}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Meta</span>
                        <span class="text-sm font-bold text-gray-800">${targetDisplay}</span>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="w-full bg-gray-200 rounded-full h-2 mb-3 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500" 
                         style="width: ${Math.min(goal.percent, 100)}%; background-color: ${goal.color};">
                    </div>
                </div>
                
                <!-- Percentual -->
                <div class="text-center">
                    <p class="text-lg font-bold" style="color: ${goal.color};">${goal.percent}%</p>
                </div>
            </div>
        `;
    },
    
    /**
     * Mostra mensagem de erro
     */
    showError(message) {
        const container = document.getElementById('goals-targets-container');
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
        GoalsAndTargets.init();
    }, 500);
});
