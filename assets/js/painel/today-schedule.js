/**
 * Today Schedule Manager
 * Carrega e exibe agendamentos de hoje com estatísticas
 */

window.TodaySchedule = {
    isLoading: false,
    
    /**
     * Inicializa o Today Schedule
     */
    init() {
        const container = document.getElementById('today-schedule-list');
        if (!container) return;
        
        this.loadSchedule();
        
        // Auto-refresh a cada 2 minutos
        setInterval(() => this.loadSchedule(), 120000);
    },
    
    /**
     * Carrega agendamentos do servidor
     */
    loadSchedule() {
        if (this.isLoading) return;
        
        this.isLoading = true;
        
        fetch(`/api/relatorios/getTodaySchedule.php`, {
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
            
            this.renderSchedule(data);
            
            this.isLoading = false;
        })
        .catch(error => {
            console.error('Erro ao carregar agendamentos:', error);
            this.isLoading = false;
            this.showError('Erro ao carregar agendamentos');
        });
    },
    
    /**
     * Renderiza o schedule na tela
     */
    renderSchedule(data) {
        const container = document.getElementById('today-schedule-list');
        if (!container) return;
        
        const today = data.today;
        const stats = today.statistics;
        const agendamentos = today.agendamentos;
        
        // Renderizar estatísticas
        this.renderStatistics(stats);
        
        // Renderizar agendamentos
        const scheduleList = document.getElementById('today-schedule-list');
        if (scheduleList) {
            if (!agendamentos || agendamentos.length === 0) {
                scheduleList.innerHTML = `
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-calendar-check text-4xl mb-3 opacity-50"></i>
                        <p>Nenhum agendamento para hoje</p>
                        <p class="text-sm mt-2">Você está livre para outras atividades!</p>
                    </div>
                `;
            } else {
                const html = agendamentos.map(a => this.renderScheduleItem(a)).join('');
                scheduleList.innerHTML = html;
            }
        }
        
        // Renderizar próximos dias
        const pendingList = document.getElementById('today-pending-days');
        if (pendingList && data.pending.by_days && data.pending.by_days.length > 0) {
            const html = data.pending.by_days.map(p => `
                <div class="flex items-center justify-between p-2 bg-blue-50 rounded">
                    <span class="text-sm font-medium text-gray-700">${p.label}</span>
                    <span class="inline-block px-3 py-1 bg-blue-600 text-white rounded-full text-xs font-bold">${p.total}</span>
                </div>
            `).join('');
            pendingList.innerHTML = html;
        }
    },
    
    /**
     * Renderiza as estatísticas
     */
    renderStatistics(stats) {
        // Atualizar badges/cards de estatísticas
        const totalEl = document.getElementById('today-total-agendamentos');
        const completedEl = document.getElementById('today-completed-agendamentos');
        const pendingEl = document.getElementById('today-pending-agendamentos');
        const attendanceEl = document.getElementById('today-attendance-rate');
        
        if (totalEl) totalEl.textContent = stats.total;
        if (completedEl) completedEl.textContent = stats.completed;
        if (pendingEl) pendingEl.textContent = stats.pending;
        if (attendanceEl) attendanceEl.textContent = stats.attendance_rate + '%';
    },
    
    /**
     * Renderiza um item individual de agendamento
     */
    renderScheduleItem(agendamento) {
        const statusColors = {
            'pending': { bg: '#fef3c7', border: '#fcd34d', icon: 'clock', text: '#92400e' },
            'completed': { bg: '#dcfce7', border: '#86efac', icon: 'check-circle', text: '#166534' },
            'cancelled': { bg: '#fee2e2', border: '#fecaca', icon: 'times-circle', text: '#991b1b' },
            'no_show': { bg: '#f3e8ff', border: '#ddd6fe', icon: 'exclamation-circle', text: '#6b21a8' }
        };
        
        const colors = statusColors[agendamento.status] || statusColors['pending'];
        const isPassed = new Date() > new Date(agendamento.appointment_date);
        
        return `
            <div class="p-4 rounded-lg border-l-4 mb-3 transition-all duration-200 hover:shadow-md"
                 style="background-color: ${colors.bg}; border-left-color: ${colors.border};">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold"
                                 style="background-color: ${colors.border};">
                                <i class="fas fa-${colors.icon} text-sm"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800">${agendamento.time} - ${agendamento.contact_name}</p>
                                <p class="text-xs text-gray-600 mt-1">${agendamento.property_title}</p>
                            </div>
                        </div>
                        <div class="ml-10 text-sm text-gray-700">
                            <p><strong>Telefone:</strong> ${agendamento.contact_phone || 'N/A'}</p>
                            <p><strong>Local:</strong> ${agendamento.neighborhood}</p>
                            <p><strong>Agente:</strong> ${agendamento.agent_name}</p>
                            ${agendamento.notes ? '<p><strong>Notas:</strong> ' + agendamento.notes + '</p>' : ''}
                        </div>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold"
                              style="background-color: ${colors.border}; color: ${colors.text};">
                            ${agendamento.status_label}
                        </span>
                        ${agendamento.price ? '<p class="text-sm font-semibold text-gray-800 mt-2">R$ ' + this.formatPrice(agendamento.price) + '</p>' : ''}
                    </div>
                </div>
            </div>
        `;
    },
    
    /**
     * Retorna label do tipo de agendamento
     */
    getAppointmentTypeLabel(type) {
        const labels = {
            'visit': 'Visita',
            'inspection': 'Inspeção',
            'meeting': 'Reunião',
            'demo': 'Demonstração',
            'other': 'Outro'
        };
        return labels[type] || type;
    },
    
    /**
     * Formata preço
     */
    formatPrice(price) {
        return new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(price);
    },
    
    /**
     * Mostra mensagem de erro
     */
    showError(message) {
        const container = document.getElementById('today-schedule-list');
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
        TodaySchedule.init();
    }, 500);
});
