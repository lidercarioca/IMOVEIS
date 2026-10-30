class RealtimeDashboard {
    constructor(containerId, period = 7) {
        this.container = document.getElementById(containerId);
        this.period = period;
        this.eventSource = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.init();
    }

    init() {
        this.connect();
        this.setupReconnectLogic();
    }

    connect() {
        const url = `/api/relatorios/realtimeDashboard.php?period=${this.period}`;
        
        try {
            this.eventSource = new EventSource(url);
            
            this.eventSource.addEventListener('dashboard_update', (e) => {
                const data = JSON.parse(e.data);
                this.updateDashboard(data);
            });
            
            this.eventSource.addEventListener('kpi_update', (e) => {
                const kpis = JSON.parse(e.data);
                this.updateKPIs(kpis);
            });
            
            this.eventSource.addEventListener('brokers_update', (e) => {
                const brokers = JSON.parse(e.data);
                this.updateBrokers(brokers);
            });
            
            this.eventSource.addEventListener('leads_update', (e) => {
                const leads = JSON.parse(e.data);
                this.updateLeads(leads);
            });
            
            this.eventSource.addEventListener('ping', (e) => {
                // Heartbeat silencioso
            });
            
            this.eventSource.addEventListener('error', (e) => {
                console.error('SSE Error:', e);
                this.handleDisconnect();
            });
            
            this.eventSource.onerror = () => {
                this.handleDisconnect();
            };
            
            this.isConnected = true;
            this.reconnectAttempts = 0;
        // Dashboard conectado
            
        } catch (error) {
            console.error('Failed to connect to realtime:', error);
            this.handleDisconnect();
        }
    }

    handleDisconnect() {
        this.isConnected = false;
        if (this.eventSource) {
            this.eventSource.close();
        }
        this.scheduleReconnect();
    }

    scheduleReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            const delay = Math.pow(2, this.reconnectAttempts) * 1000;
            // Reconectando...
            setTimeout(() => this.connect(), delay);
            this.reconnectAttempts++;
        }
    }

    setupReconnectLogic() {
        // Reconectar quando aba ganhar foco
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isConnected) {
                this.connect();
            }
        });
    }

    updateDashboard(data) {
        // Atualização recebida
        // Disparar eventos customizados
        window.dispatchEvent(new CustomEvent('dashboardUpdate', { detail: data }));
    }

    updateKPIs(kpis) {
        const elements = {
            'total_properties': `${kpis.total_properties || 0}`,
            'total_leads': `${kpis.total_leads || 0}`,
            'total_sales': `${kpis.total_sales || 0}`,
            'avg_ticket': `R$ ${(kpis.avg_ticket || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}`,
            'cycle_days': `${kpis.cycle_days || 0} dias`,
            'stopped_count': `${kpis.stopped_count || 0}`,
            'today_conversions': `${kpis.today_conversions || 0}`
        };

        for (const [key, value] of Object.entries(elements)) {
            const el = this.container.querySelector(`[data-kpi="${key}"]`);
            if (el) {
                this.animateUpdate(el, value);
            }
        }
    }

    updateBrokers(brokers) {
        const brokersList = this.container.querySelector('[data-component="brokers"]');
        if (!brokersList) return;

        let html = `
            <div class="grid grid-cols-1 gap-4">
                <h3 class="text-lg font-semibold">Top Brokers</h3>
        `;

        brokers.forEach(broker => {
            html += `
                <div class="p-3 border rounded-lg hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold">${broker.name}</p>
                            <p class="text-sm text-gray-500">${broker.sales_count} vendas</p>
                        </div>
                        <span class="text-green-600 font-semibold">R$ ${(broker.total_revenue || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        brokersList.innerHTML = html;
    }

    updateLeads(leads) {
        const leadsList = this.container.querySelector('[data-component="recent-leads"]');
        if (!leadsList) return;

        let html = `
            <div class="space-y-2">
                <h3 class="text-lg font-semibold">Leads Recentes</h3>
        `;

        leads.forEach(lead => {
            const statusColor = {
                'new': 'bg-blue-100 text-blue-800',
                'contacted': 'bg-yellow-100 text-yellow-800',
                'interested': 'bg-purple-100 text-purple-800',
                'converted': 'bg-green-100 text-green-800',
                'lost': 'bg-red-100 text-red-800'
            }[lead.status] || 'bg-gray-100 text-gray-800';

            html += `
                <div class="p-2 border rounded flex justify-between items-center hover:bg-gray-50">
                    <div>
                        <p class="font-medium text-sm">${lead.contact_name}</p>
                        <p class="text-xs text-gray-500">${lead.title}</p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded ${statusColor}">${lead.status}</span>
                </div>
            `;
        });

        html += '</div>';
        leadsList.innerHTML = html;
    }

    animateUpdate(element, newValue) {
        const oldValue = element.textContent;
        if (oldValue === newValue) return;

        element.style.transition = 'all 0.3s ease';
        element.style.opacity = '0.5';
        
        setTimeout(() => {
            element.textContent = newValue;
            element.style.opacity = '1';
            element.classList.add('updated-value');
            
            setTimeout(() => {
                element.classList.remove('updated-value');
            }, 1000);
        }, 150);
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.isConnected = false;
        }
    }

    getConnectionStatus() {
        return {
            connected: this.isConnected,
            reconnectAttempts: this.reconnectAttempts
        };
    }
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    window.realtimeDashboard = new RealtimeDashboard('dashboard-container', 7);
});

// CSS para animação
const style = document.createElement('style');
style.textContent = `
    .updated-value {
        animation: pulse 0.6s ease-in-out;
    }
    
    @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }
`;
document.head.appendChild(style);
