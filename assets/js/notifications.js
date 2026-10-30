// Configurações
const POLLING_INTERVAL = 30000; // 30 segundos (era 5, muito agressivo)
let pollingTimeout = null;
let lastNotificationCount = 0;

// Elementos DOM
let notificationsBtn, notificationsDropdown, notificationsBadge, notificationsList;

// Função para verificar novas notificações
async function checkNotifications() {
    try {
        const res = await fetch('/api/v1/notifications/getNotifications.php', {
            credentials: 'include'
        });
        if (!res.ok) throw new Error('Falha na requisição: ' + res.status);
        
        const data = await res.json();
        
        
        if (!data.success) {
            throw new Error(data.error || data.message || 'Erro ao verificar notificações');
        }

        const unreadCount = data.unreadCount || 0;
        

        // Se houver novas notificações
        if (unreadCount > lastNotificationCount) {
            
            
            // Atualiza o badge
            updateNotificationBadge(unreadCount);
            
            // Detecta se há notificação de lead/contato e recarrega leads se na aba correta
            if (data.notifications && Array.isArray(data.notifications)) {
                const hasLeadNotification = data.notifications.some(n => 
                    n.type === 'contact_form' || 
                    n.type === 'new_lead' || 
                    n.message.toLowerCase().includes('lead')
                );
                
                if (hasLeadNotification) {
                    reloadLeadsTableIfVisible();
                }
            }
            
            // Mostra notificação do sistema se o dropdown estiver fechado
            if (notificationsDropdown.classList.contains('hidden')) {
                showSystemNotification("Nova notificação", "Você tem novas notificações não lidas");
            } else {
                // Se o dropdown estiver aberto, atualiza a lista
                await loadNotifications();
            }
        }

        lastNotificationCount = unreadCount;
    } catch (err) {
        console.error('Erro ao verificar notificações:', err);
    }
}

// Função para recarregar leads se a aba estiver visível
function reloadLeadsTableIfVisible() {
    try {
        // Verifica se a aba de leads está visível
        const leadsTab = document.getElementById('leads');
        if (leadsTab && !leadsTab.classList.contains('hidden') && typeof loadLeadsPage === 'function') {
            // Recarrega a primeira página de leads
            loadLeadsPage(1);
        }
    } catch (err) {
        console.error('Erro ao recarregar tabela de leads:', err);
    }
}

// Função para iniciar o polling
function startPolling() {
    // Primeira verificação imediata
    checkNotifications().catch(err => console.error('Erro na verificação inicial:', err));
    
    // Configura o polling
    if (!pollingTimeout) {
        pollingTimeout = setInterval(() => {
            checkNotifications().catch(err => console.error('Erro no polling:', err));
        }, POLLING_INTERVAL);
    }
}

// Função para parar o polling
function stopPolling() {
    if (pollingTimeout) {
        clearInterval(pollingTimeout);
        pollingTimeout = null;
    }
}

// Função para atualizar o badge de notificações
function updateNotificationBadge(count) {
    if (!notificationsBadge) return;
    
    const notificationIcon = notificationsBtn.querySelector('i.fa-bell, i.fa-bell-exclamation');
    
    if (count > 0) {
        notificationsBadge.classList.remove('hidden');
        notificationsBadge.classList.add('animate-pulse');
        notificationsBadge.textContent = count;
        notificationsBadge.style.display = 'block';
        
        if (notificationIcon) {
            notificationIcon.classList.remove('fa-bell');
            notificationIcon.classList.add('fa-bell-exclamation');
        }
    } else {
        notificationsBadge.classList.add('hidden');
        notificationsBadge.classList.remove('animate-pulse');
        
        if (notificationIcon) {
            notificationIcon.classList.remove('fa-bell-exclamation');
            notificationIcon.classList.add('fa-bell');
        }
    }
}

// Função para mostrar notificação do sistema
function showSystemNotification(title, body) {
    if (Notification.permission === "granted") {
        new Notification(title, {
            body: body,
            icon: "/assets/imagens/logo/logo_padrao.png"
        });
    } else if (Notification.permission !== "denied") {
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                showSystemNotification(title, body);
            }
        });
    }
}

// Função para carregar e exibir notificações
async function loadNotifications() {
    try {
        const res = await fetch('/api/v1/notifications/getNotifications.php', {
            credentials: 'include'
        });
        if (!res.ok) throw new Error('Falha na requisição');
        
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.error || data.message || 'Erro ao carregar notificações');
        }

        // response debug logs removed

        // Atualiza badge
        updateNotificationBadge(data.unreadCount || 0);
        
        // Atualiza o contador
        lastNotificationCount = data.unreadCount || 0;

        // Limpa a lista atual
        notificationsList.innerHTML = '';

        // Se não houver notificações
        if (!data.notifications || data.notifications.length === 0) {
            notificationsList.innerHTML = `
                <div class="p-4 text-center text-gray-500">
                    Nenhuma notificação
                </div>
            `;
            return;
        }

        // Adiciona botão "Marcar todas como lidas" se houver notificações não lidas
        if (data.unreadCount > 0) {
            const markAllDiv = document.createElement('div');
            markAllDiv.className = 'p-2 bg-gray-50 border-b';
            markAllDiv.innerHTML = `
                <button id="mark-all-notifications-read" class="w-full text-center text-blue-600 hover:text-blue-800 text-sm py-1">
                    Marcar todas como lidas
                </button>
            `;
            notificationsList.appendChild(markAllDiv);
        }

        // Renderiza cada notificação - converte para array se necessário
        const notificationsArray = Array.isArray(data.notifications) ? data.notifications : Object.values(data.notifications);
        notificationsArray.forEach(notification => {
            const notificationEl = document.createElement('div');
            notificationEl.className = `p-4 border-b hover:bg-gray-50 transition ${notification.is_read ? '' : 'bg-blue-50'}`;
            notificationEl.innerHTML = `
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="text-sm ${notification.is_read ? 'text-gray-600' : 'text-gray-800 font-semibold'}">${notification.message}</p>
                        <p class="text-xs text-gray-500 mt-1">${new Date(notification.created_at).toLocaleString('pt-BR')}</p>
                    </div>
                    ${notification.is_read ? 
                        `<button class="delete-notification text-red-600 hover:text-red-800" data-id="${notification.id}">
                            <i class="fas fa-trash-alt"></i>
                        </button>` : 
                        `<button class="mark-read text-blue-600 hover:text-blue-800 text-sm" data-id="${notification.id}">
                            Marcar como lida
                        </button>`
                    }
                </div>
            `;
            notificationsList.appendChild(notificationEl);
        });

        // Adiciona event listeners
        addNotificationListeners();
    } catch (err) {
        console.error('Erro ao carregar notificações:', err);
        notificationsList.innerHTML = `
            <div class="p-4 text-center text-red-500">
                Erro ao carregar notificações
            </div>
        `;
    }
}

// Função para adicionar listeners aos botões
function addNotificationListeners() {
    // listeners are handled via delegation attached once on `notificationsList`
}

// Função para marcar notificação como lida
async function markNotificationAsRead(notificationId) {
    try {
        const res = await fetch('/api/v1/notifications/markNotificationRead.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: notificationId })
        });
        
        if (!res.ok) throw new Error('Erro ao marcar notificação como lida');
        
        // Recarrega as notificações
        await loadNotifications();
    } catch (err) {
        console.error('Erro:', err);
    }
}

// Função para excluir notificação
async function deleteNotification(notificationId) {
    try {
        const res = await fetch('/api/v1/notifications/deleteNotification.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: parseInt(notificationId, 10) })
        });

        if (!res.ok) throw new Error('Erro ao excluir notificação');
        
        const data = await res.json();
        if (data.success) {
            await loadNotifications(); // Recarrega a lista
        } else {
            throw new Error(data.message || 'Erro ao excluir notificação');
        }
    } catch (error) {
        console.error('Erro ao excluir notificação:', error);
    }
}

// Função para marcar todas as notificações como lidas
async function markAllNotificationsAsRead() {
    try {
        const res = await fetch('/api/v1/notifications/markNotificationRead.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ all: true })
        });

        if (!res.ok) throw new Error('Erro ao marcar notificações como lidas');
        
        // Recarrega as notificações
        await loadNotifications();
    } catch (err) {
        console.error('Erro ao marcar todas as notificações como lidas:', err);
    }
}

// Inicialização quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    
    
    // Inicializa elementos DOM
    notificationsBtn = document.getElementById('notifications-btn');
    notificationsDropdown = document.getElementById('notifications-dropdown');
    notificationsBadge = document.getElementById('notifications-badge');
    notificationsList = document.getElementById('notifications-list');

    

    // Verifica se os elementos existem
    if (!notificationsBtn || !notificationsDropdown || !notificationsBadge || !notificationsList) {
        console.error('Elementos de notificação não encontrados');
        return;
    }

    // Configura visibilidade da página
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopPolling();
        } else {
            startPolling();
        }
    });

    // Fechar dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!notificationsBtn.contains(e.target) && !notificationsDropdown.contains(e.target)) {
            notificationsDropdown.classList.add('hidden');
        }
    });

    // Toggle dropdown
    notificationsBtn.addEventListener('click', function() {
        notificationsDropdown.classList.toggle('hidden');
        if (!notificationsDropdown.classList.contains('hidden')) {
            loadNotifications();
        }
    });

    // Configurar botão 'Marcar todas como lidas'
    // Delegate clicks inside notificationsList to handle mark/read/delete/all
    if (notificationsList) {
        notificationsList.addEventListener('click', async function(e) {
            const btn = e.target.closest('button');
            if (!btn) return;

            // Marcar única notificação como lida
            if (btn.classList.contains('mark-read')) {
                e.preventDefault();
                e.stopPropagation();
                const notificationId = btn.dataset.id;
                await markNotificationAsRead(notificationId);
                return;
            }

            // Excluir notificação
            if (btn.classList.contains('delete-notification')) {
                e.preventDefault();
                e.stopPropagation();
                const notificationId = btn.dataset.id;
                await deleteNotification(notificationId);
                return;
            }

            // Marcar todas como lidas
            if (btn.id === 'mark-all-notifications-read') {
                e.preventDefault();
                try {
                    btn.disabled = true;
                    await markAllNotificationsAsRead();
                } catch (err) {
                    console.error('[notifications] mark-all error', err);
                } finally {
                    btn.disabled = false;
                }
                return;
            }
        });
    }
    
    // Também delegar no container do dropdown para capturar o botão superior
    if (notificationsDropdown) {
        notificationsDropdown.addEventListener('click', async function(e) {
            const btn = e.target.closest('button');
            if (!btn) return;

            // Reaproveita as mesmas ações
            if (btn.classList.contains('mark-read')) {
                e.preventDefault();
                e.stopPropagation();
                const notificationId = btn.dataset.id;
                await markNotificationAsRead(notificationId);
                return;
            }

            if (btn.classList.contains('delete-notification')) {
                e.preventDefault();
                e.stopPropagation();
                const notificationId = btn.dataset.id;
                await deleteNotification(notificationId);
                return;
            }

            if (btn.id === 'mark-all-notifications-read') {
                e.preventDefault();
                try {
                    btn.disabled = true;
                    await markAllNotificationsAsRead();
                } catch (err) {
                    console.error('[notifications] mark-all error', err);
                } finally {
                    btn.disabled = false;
                }
                return;
            }
        });
    }

    // Inicia o polling
    startPolling();
});
