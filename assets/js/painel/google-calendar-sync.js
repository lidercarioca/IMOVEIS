/**
 * Google Calendar Integration
 * Gerencia sincronização de agendamentos com Google Calendar
 */

window.GoogleCalendarSync = {
    isAuthenticated: false,
    
    /**
     * Inicializa o sistema
     */
    init() {
        this.checkAuthStatus();
        this.addEventListeners();
    },
    
    /**
     * Verifica status de autenticação
     */
    checkAuthStatus() {
        fetch('/api/v1/google_calendar.php?action=get-status')
            .then(response => response.json())
            .then(data => {
                this.isAuthenticated = data.authenticated;
                this.updateUI();
            })
            .catch(err => console.error('Erro ao verificar status:', err));
    },
    
    /**
     * Adiciona event listeners
     */
    addEventListeners() {
        // Botão de autenticação
        const authBtn = document.getElementById('btn-google-calendar-auth');
        if (authBtn) {
            authBtn.addEventListener('click', () => this.startAuthentication());
        }
        
        // Botão de sincronizar tudo
        const syncAllBtn = document.getElementById('btn-sync-all-google');
        if (syncAllBtn) {
            syncAllBtn.addEventListener('click', () => this.syncAllAgendamentos());
        }
        
        // Checkboxes de sincronizar ao criar/editar agendamento
        document.addEventListener('change', (e) => {
            if (e.target.id === 'sync-to-google-calendar') {
                this.handleSyncCheckbox(e.target.checked);
            }
        });
    },
    
    /**
     * Inicia processo de autenticação
     */
    startAuthentication() {
        fetch('/api/v1/google_calendar.php?action=authenticate')
            .then(response => response.json())
            .then(data => {
                if (data.auth_url) {
                    // Redirecionar para página de autorização do Google
                    window.location.href = data.auth_url;
                } else {
                    this.showError('Erro ao gerar URL de autenticação');
                }
            })
            .catch(err => this.showError('Erro: ' + err.message));
    },
    
    /**
     * Sincronizar todos os agendamentos
     */
    syncAllAgendamentos() {
        const btn = document.getElementById('btn-sync-all-google');
        if (!btn) return;
        
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sincronizando...';
        btn.disabled = true;
        
        fetch('/api/v1/google_calendar.php?action=sync-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccess(
                    `✅ Sincronização concluída!\n` +
                    `Sincronizados: ${data.synced}/${data.total}`
                );
                
                if (data.errors.length > 0) {
                    console.warn('Erros durante sincronização:', data.errors);
                }
            } else {
                this.showError(data.error);
            }
        })
        .catch(err => this.showError('Erro: ' + err.message))
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    },
    
    /**
     * Sincronizar um agendamento específico
     */
    syncAgendamento(agendamentoId) {
        fetch('/api/v1/google_calendar.php?action=sync-event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                agendamento_id: agendamentoId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccess(`Agendamento sincronizado com sucesso!`);
                return true;
            } else {
                this.showError(data.error);
                return false;
            }
        })
        .catch(err => {
            this.showError('Erro: ' + err.message);
            return false;
        });
    },
    
    /**
     * Remover sincronização de um agendamento
     */
    unsyncAgendamento(agendamentoId) {
        if (!confirm('Tem certeza que deseja remover esta sincronização?')) {
            return false;
        }
        
        fetch('/api/v1/google_calendar.php?action=unsync-event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                agendamento_id: agendamentoId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.showSuccess('Sincronização removida!');
                return true;
            } else {
                this.showError(data.error);
                return false;
            }
        })
        .catch(err => {
            this.showError('Erro: ' + err.message);
            return false;
        });
    },
    
    /**
     * Listar eventos do Google Calendar
     */
    listEvents(startDate, endDate) {
        return fetch(
            `/api/v1/google_calendar.php?action=list-events&start_date=${startDate}&end_date=${endDate}`
        )
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return data.events;
            } else {
                throw new Error(data.error);
            }
        });
    },
    
    /**
     * Handle checkbox de sincronização
     */
    handleSyncCheckbox(checked) {
        if (!this.isAuthenticated && checked) {
            alert('Você precisa autenticar com Google Calendar primeiro!');
            document.getElementById('sync-to-google-calendar').checked = false;
            return;
        }
    },
    
    /**
     * Atualizar UI baseado no status de autenticação
     */
    updateUI() {
        const authBtn = document.getElementById('btn-google-calendar-auth');
        const syncSection = document.getElementById('google-calendar-sync-section');
        const authStatus = document.getElementById('google-calendar-auth-status');
        
        if (this.isAuthenticated) {
            if (authBtn) authBtn.style.display = 'none';
            if (syncSection) syncSection.style.display = 'block';
            if (authStatus) {
                authStatus.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i>Autenticado com Google Calendar';
            }
        } else {
            if (authBtn) authBtn.style.display = 'block';
            if (syncSection) syncSection.style.display = 'none';
            if (authStatus) {
                authStatus.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-2"></i>Não autenticado';
            }
        }
    },
    
    /**
     * Mostrar mensagem de sucesso
     */
    showSuccess(message) {
        // Usar notificação do sistema ou alert
        alert('✅ Sucesso:\n' + message);
    },
    
    /**
     * Mostrar mensagem de erro
     */
    showError(message) {
        // Usar notificação do sistema ou alert
        alert('❌ Erro:\n' + message);
    }
};

// Inicializar quando o documento carregar
document.addEventListener('DOMContentLoaded', () => {
    GoogleCalendarSync.init();
});
