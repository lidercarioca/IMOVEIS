/**
 * Activity Feed Manager
 * Carrega e exibe o feed de últimas atividades do dashboard
 */

window.ActivityFeed = {
    currentPage: 0,
    pageSize: 5,
    isLoading: false,
    
    /**
     * Inicializa o Activity Feed
     */
    init() {
        const container = document.getElementById('activity-feed-container');
        if (!container) return;
        
        this.loadActivities(0);
        
        // Auto-refresh a cada 30 segundos
        setInterval(() => this.loadActivities(0), 30000);
    },
    
    /**
     * Carrega atividades do servidor
     */
    loadActivities(page = 0) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        const offset = page * this.pageSize;
        
        fetch(`/api/relatorios/getActivityFeed.php?limit=${this.pageSize}&offset=${offset}`, {
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
            
            this.renderActivities(data.data);
            this.currentPage = page;
            
            this.isLoading = false;
        })
        .catch(error => {
            console.error('Erro ao carregar feed:', error);
            this.isLoading = false;
            this.showError('Erro ao carregar atividades');
        });
    },
    
    /**
     * Renderiza as atividades na tela
     */
    renderActivities(activities) {
        const container = document.getElementById('activity-feed-container');
        if (!container) return;
        
        if (!activities || activities.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3 opacity-50"></i>
                    <p>Nenhuma atividade recente</p>
                </div>
            `;
            return;
        }
        
        const html = activities.map(activity => this.renderActivityItem(activity)).join('');
        container.innerHTML = html;
    },
    
    /**
     * Renderiza um item individual de atividade
     */
    renderActivityItem(activity) {
        const activityLabels = {
            'property_created': '🏠 Novo Imóvel',
            'lead_created': '👤 Novo Lead',
            'sale_created': '✅ Venda/Aluguel',
            'agendamento_created': '📅 Novo Agendamento'
        };
        
        const label = activityLabels[activity.type] || 'Atividade';
        
        return `
            <div class="flex items-start gap-3 pb-4 mb-4 border-b last:border-b-0">
                <!-- Avatar com ícone -->
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                         style="background-color: ${activity.color};">
                        <i class="fas fa-${activity.icon}"></i>
                    </div>
                </div>
                
                <!-- Conteúdo -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-800">
                            ${label}
                        </p>
                        <span class="text-xs text-gray-500">${activity.time_ago}</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1 truncate">
                        ${activity.description}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">
                        Por: <span class="font-medium">${activity.user_name}</span>
                    </p>
                </div>
            </div>
        `;
    },
    
    /**
     * Mostra mensagem de erro
     */
    showError(message) {
        const container = document.getElementById('activity-feed-container');
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
        ActivityFeed.init();
    }, 500);
});
