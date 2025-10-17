// Configurações
const POLLING_INTERVAL = 5000; // 5 segundos
let pollingTimeout = null;
let lastNotificationCount = 0;

// Elementos DOM
let notificationsBtn, notificationsDropdown, notificationsBadge, notificationsList;

// Função para verificar novas notificações
async function checkNotifications() {
    try {
        console.log('Verificando notificações...');
        const res = await fetch('api/getNotifications.php');
        if (!res.ok) throw new Error('Falha na requisição');
        
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Erro ao verificar notificações');
        }

        const unreadCount = data.unreadCount || 0;
        console.log('Notificações não lidas:', unreadCount, 'Anterior:', lastNotificationCount);

        // Se houver novas notificações
        if (unreadCount > lastNotificationCount) {
            console.log('Novas notificações detectadas!');
            
            // Atualiza o badge
            updateNotificationBadge(unreadCount);
            
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

// Função para iniciar o polling
function startPolling() {
    // Primeira verificação imediata
    checkNotifications().catch(err => console.error('Erro na verificação inicial:', err));
    
    // Configura o polling
    if (!pollingTimeout) {
        pollingTimeout = setInterval(() => {
            checkNotifications().catch(err => console.error('Erro no polling:', err));
        }, POLLING_INTERVAL);
        console.log('Polling de notificações iniciado');
    }
}

// Função para parar o polling
function stopPolling() {
    if (pollingTimeout) {
        clearInterval(pollingTimeout);
        pollingTimeout = null;
        console.log('Polling de notificações parado');
    }
}

// Função para atualizar o badge de notificações
function updateNotificationBadge(count) {
    if (!notificationsBadge) return;
    
    // Pega o elemento do ícone
    const notificationIcon = notificationsBtn.querySelector('i.fa-bell, i.fa-bell-exclamation');
    
    if (count > 0) {
        notificationsBadge.textContent = count;
        notificationsBadge.classList.remove('hidden');
        notificationsBadge.classList.add('animate-pulse');
        
        // Troca o ícone quando há notificações
        if (notificationIcon) {
            notificationIcon.classList.remove('fa-bell');
            notificationIcon.classList.add('fa-bell-exclamation');
        }
    } else {
        notificationsBadge.classList.add('hidden');
        notificationsBadge.classList.remove('animate-pulse');
        
        // Volta para o ícone padrão quando não há notificações
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
            icon: "/assets/imagens/logo/logo.png"
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
        console.log('Carregando lista de notificações...');
        const res = await fetch('api/getNotifications.php');
        if (!res.ok) throw new Error('Falha na requisição');
        
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar notificações');
        }

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

        // Renderiza cada notificação
        data.notifications.forEach(notification => {
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
    // Listener para botões "Marcar como lida"
    document.querySelectorAll('.mark-read').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const notificationId = this.dataset.id;
            await markNotificationAsRead(notificationId);
        });
    });

    // Listener para botões de exclusão
    document.querySelectorAll('.delete-notification').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            const notificationId = this.dataset.id;
            await deleteNotification(notificationId);
        });
    });

    // Listener para o botão "Marcar todas como lidas"
    const markAllBtn = document.getElementById('mark-all-notifications-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllNotificationsAsRead();
        });
    }
}

// Função para marcar notificação como lida
async function markNotificationAsRead(notificationId) {
    try {
        const res = await fetch('api/markNotificationRead.php', {
            method: 'POST',
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
        const res = await fetch('api/deleteNotification.php', {
            method: 'POST',
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
        const res = await fetch('api/markNotificationRead.php', {
            method: 'POST',
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

    // Solicita permissão para notificações do sistema
    if ("Notification" in window) {
        Notification.requestPermission();
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
    const markAllReadBtn = document.getElementById('mark-all-notifications-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllNotificationsAsRead();
        });
    }

    // Inicia o polling
    startPolling();
});