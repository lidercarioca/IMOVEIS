/**
 * Alerts Feed Manager
 * Carrega e exibe alertas ativos do dashboard
 */

window.AlertsFeed = {
    isLoading: false,
    
    /**
     * Inicializa o Alerts Feed
     */
    init() {
        const container = document.getElementById('alerts-feed-container');
        if (!container) return;
        
        this.loadAlerts();
        
        // Auto-refresh a cada 60 segundos
        setInterval(() => this.loadAlerts(), 60000);
    },
    
    /**
     * Carrega alertas do servidor
     */
    loadAlerts() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        fetch(`/api/relatorios/getAlertsFeed.php`, {
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
            
            this.renderAlerts(data.data, data.total_alerts, data.has_critical);
            
            this.isLoading = false;
        })
        .catch(error => {
            console.error('Erro ao carregar alertas:', error);
            this.isLoading = false;
            this.showError('Erro ao carregar alertas');
        });
    },
    
    /**
     * Renderiza os alertas na tela
     */
    renderAlerts(alerts, totalCount, hasCritical) {
        const container = document.getElementById('alerts-feed-container');
        if (!container) return;
        
        if (!alerts || alerts.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-green-600">
                    <i class="fas fa-check-circle text-4xl mb-3"></i>
                    <p class="font-semibold">Nenhum alerta no momento</p>
                    <p class="text-sm text-gray-500">Tudo funcionando perfeitamente!</p>
                </div>
            `;
            
            // Atualizar badge
            const badge = document.getElementById('alerts-badge');
            if (badge) badge.textContent = '0';
            
            return;
        }
        
        // Atualizar badge
        const badge = document.getElementById('alerts-badge');
        if (badge) {
            badge.textContent = totalCount;
            if (hasCritical) {
                badge.classList.add('bg-red-600');
                badge.classList.remove('bg-blue-600');
            } else {
                badge.classList.remove('bg-red-600');
                badge.classList.add('bg-blue-600');
            }
        }
        
        const html = alerts.map(alert => this.renderAlertItem(alert)).join('');
        container.innerHTML = html;
    },
    
    /**
     * Renderiza um item individual de alerta
     */
    renderAlertItem(alert) {
        const severityIcons = {
            'critical': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'success': 'check-circle',
            'info': 'info-circle'
        };
        
        const icon = severityIcons[alert.severity] || 'info-circle';
        const alertId = alert.id || Math.random().toString(36);
        const titleEscaped = alert.title.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const descEscaped = alert.description.replace(/'/g, "\\'").replace(/"/g, '&quot;');
        const actionUrlEscaped = (alert.action_url || '').replace(/'/g, "\\'");
        
        return `
            <div class="flex items-start gap-3 p-3 mb-3 rounded-lg border-l-4 bg-gray-50 transition-colors duration-200 cursor-pointer hover:bg-gray-100" 
                 style="border-left-color: ${alert.color};" onclick="AlertsFeed.showAlertDetails('${alertId}', '${titleEscaped}', '${descEscaped}', '${alert.color}', '${actionUrlEscaped}')">
                <!-- Ícone -->
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-semibold text-sm"
                         style="background-color: ${alert.color};">
                        <i class="fas fa-${icon}"></i>
                    </div>
                </div>
                
                <!-- Conteúdo -->
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800">
                        ${alert.title}
                    </p>
                    <p class="text-sm text-gray-600 mt-1">
                        ${alert.description}
                    </p>
                    <p class="text-xs text-blue-600 mt-2 cursor-pointer hover:underline">Clique para ver detalhes</p>
                </div>
                
                <!-- Ícone de ação -->
                <i class="fas fa-chevron-right text-gray-400 flex-shrink-0 mt-1"></i>
            </div>
        `;
    },
    
    /**
     * Mostra detalhes do alerta em um modal
     */
    showAlertDetails(alertId, title, description, color, actionUrl) {
        // Remove modal anterior se existir
        const oldModal = document.getElementById('alertDetailsModal');
        if (oldModal) {
            const instance = bootstrap.Modal.getInstance(oldModal);
            if (instance) instance.hide();
            setTimeout(() => oldModal.remove(), 500);
        }
        
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'alertDetailsModal';
        modal.tabIndex = '-1';
        
        const match = actionUrl ? actionUrl.match(/view=(\d+)/) : null;
        const itemId = match ? match[1] : null;
        const isLeads = actionUrl ? actionUrl.includes('leads') : false;
        
        const actionButton = actionUrl && itemId ? `
            <button type="button" class="btn btn-primary alert-action-btn" data-item-id="${itemId}" data-is-leads="${isLeads}">Ver Detalhes Completos</button>
        ` : '';
        
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header border-0" style="background-color: ${color}; color: white; border-radius: 0.5rem 0.5rem 0 0;">
                        <h5 class="modal-title font-semibold">${title}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-6">
                        <div class="mb-6">
                            <h6 class="font-semibold text-gray-800 mb-3">Informações do Alerta</h6>
                            <p class="text-gray-700 text-base leading-relaxed">${description}</p>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                            <p class="text-sm text-gray-700">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <strong>Recomendação:</strong> Verifique os detalhes completos para tomar a ação apropriada.
                            </p>
                        </div>
                    </div>
                    <div class="modal-footer border-top pt-4">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        ${actionButton}
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bs = new bootstrap.Modal(modal);
        bs.show();
        
        // Adiciona event listener para o botão de ação
        const actionBtn = modal.querySelector('.alert-action-btn');
        if (actionBtn) {
            actionBtn.addEventListener('click', async function() {
                const itemId = this.getAttribute('data-item-id');
                const isLeads = this.getAttribute('data-is-leads') === 'true';
                
                // Fecha o modal
                bs.hide();
                
                // Aguarda o fade-out do modal
                setTimeout(async () => {
                    if (isLeads && itemId) {
                        try {
                            // Busca o lead específico por ID usando query com limit grande
                            const response = await fetch(`/api/v1/leads/getLeads.php?limit=10000&page=1`);
                            const json = await response.json();
                            
                            if (json.success && json.data && json.data.length > 0) {
                                const lead = json.data.find(l => String(l.id) === String(itemId));
                                
                                if (lead) {
                                    // Primeiro navega para a página de leads
                                    window.location.hash = '#leads';
                                    
                                    // Aguarda um pouco para a página ser inicializada
                                    await new Promise(resolve => setTimeout(resolve, 600));
                                    
                                    // Abre o modal do lead
                                    if (typeof mostrarModalLead === 'function') {
                                        mostrarModalLead(lead);
                                    }
                                }
                            }
                        } catch (error) {
                            console.error('Erro ao buscar lead:', error);
                        }
                    } else if (!isLeads && itemId) {
                        // Primeiro navega para a página de propriedades
                        window.location.hash = '#properties';
                        
                        // Aguarda um pouco para a página ser inicializada
                        await new Promise(resolve => setTimeout(resolve, 600));
                        
                        // Abre o modal de detalhes do imóvel
                        if (typeof abrirModalDetalhes === 'function') {
                            abrirModalDetalhes(itemId);
                        }
                    }
                }, 300);
            });
        }
        
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    },
    
    /**
     * Mostra mensagem de erro
     */
    showError(message) {
        const container = document.getElementById('alerts-feed-container');
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
        AlertsFeed.init();
    }, 500);
});
