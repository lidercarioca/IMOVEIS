// Gerenciamento de notificações e mensagens
document.addEventListener('DOMContentLoaded', function() {
    // Obter elementos do DOM
    const notificationsBtn = document.getElementById('notifications-btn');
    const notificationsDropdown = document.getElementById('notifications-dropdown');
    const notificationsBadge = document.getElementById('notifications-badge');
    const notificationsList = document.getElementById('notifications-list');
    
    const messagesBtn = document.getElementById('messages-btn');
    const messagesDropdown = document.getElementById('messages-dropdown');
    const messagesBadge = document.getElementById('messages-badge');
    const messagesList = document.getElementById('messages-list');

    // Verificar se todos os elementos necessários existem
    if (!notificationsBtn || !notificationsDropdown || !notificationsBadge || !notificationsList ||
        !messagesBtn || !messagesDropdown || !messagesBadge || !messagesList) {
        console.error('Elementos necessários não encontrados no DOM');
        return;
    }

    // Fechar dropdowns ao clicar fora
    document.addEventListener('click', function(e) {
        if (!notificationsBtn.contains(e.target) && !notificationsDropdown.contains(e.target)) {
            notificationsDropdown.classList.add('hidden');
        }
        if (!messagesBtn.contains(e.target) && !messagesDropdown.contains(e.target)) {
            messagesDropdown.classList.add('hidden');
        }
    });

    // Toggle dropdowns
    notificationsBtn.addEventListener('click', function() {
        messagesDropdown.classList.add('hidden');
        notificationsDropdown.classList.toggle('hidden');
        if (!notificationsDropdown.classList.contains('hidden')) {
            loadNotifications();
        }
    });

    // Função para adicionar listeners aos botões de notificação
    function addNotificationListeners() {
        // Listener para botões "Marcar como lida"
        document.querySelectorAll('.mark-read').forEach(btn => {
            btn.addEventListener('click', async function() {
                const notificationId = this.dataset.id;
                await markNotificationAsRead(notificationId);
            });
        });

        // Listener para botões de exclusão
        document.querySelectorAll('.delete-notification').forEach(btn => {
            btn.addEventListener('click', async function() {
                const notificationId = this.dataset.id;
                if (confirm('Tem certeza que deseja excluir esta notificação?')) {
                    await deleteNotification(notificationId);
                }
            });
        });
    }

    // Função para excluir notificação
    async function deleteNotification(notificationId) {
        try {
            console.log('Tentando excluir notificação:', notificationId);
            
            // Verifica se o ID é válido
            if (!notificationId) {
                throw new Error('ID da notificação não fornecido');
            }

            // Constrói a URL usando o caminho relativo
            const url = window.location.pathname.replace(/\/[^\/]*$/, '') + '/api/deleteNotification.php';
            console.log('URL da requisição:', url);

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                body: JSON.stringify({ 
                    id: parseInt(notificationId, 10) 
                })
            });

            console.log('Status da resposta:', response.status);
            
            // Lê o texto da resposta
            const text = await response.text();
            console.log('Resposta do servidor:', text);

            // Tenta fazer o parse do JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Erro ao fazer parse da resposta:', e);
                throw new Error('Resposta inválida do servidor: ' + text);
            }

            if (data.success) {
                console.log('Notificação excluída com sucesso');
                loadNotifications(); // Recarrega a lista
            } else {
                throw new Error(data.message || 'Erro ao excluir notificação');
            }
        } catch (error) {
            console.error('Erro ao excluir notificação:', error);
        }
    }

    messagesBtn.addEventListener('click', function() {
        notificationsDropdown.classList.add('hidden');
        messagesDropdown.classList.toggle('hidden');
        if (!messagesDropdown.classList.contains('hidden')) {
            loadMessages();
        }
    });

    // Carregar notificações
    async function loadNotifications() {
        try {
            const res = await fetch('api/getNotifications.php');
            const data = await res.json();
            
            // Atualiza badge
            if (data.unread_count > 0) {
                notificationsBadge.classList.remove('hidden');
                notificationsBadge.style.display = 'block';
            } else {
                notificationsBadge.classList.add('hidden');
            }

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

            // Adiciona event listeners para os botões
            addNotificationListeners();
        } catch (err) {
            console.error('Erro ao carregar notificações:', err);
        }
    }

    // Carregar mensagens
    async function loadMessages() {
        try {
            // Adiciona um timestamp para evitar cache
            const res = await fetch('api/getMessages.php?_=' + Date.now());
            
            if (!res.ok) {
                const errorData = await res.json().catch(() => ({}));
                throw new Error(errorData.error || 'Erro ao buscar mensagens');
            }
            
            const data = await res.json().catch(() => {
                throw new Error('Erro ao processar resposta do servidor');
            });
            
            // Atualiza badge apenas se os dados forem válidos
            if (data && typeof data.unread_count !== 'undefined') {
                messagesBadge.classList.toggle('hidden', data.unread_count === 0);
                if (data.unread_count > 0) {
                    messagesBadge.textContent = data.unread_count.toString();
                    messagesBadge.style.display = 'flex';
                }
            }
            
            // Limpa e preenche lista
            messagesList.innerHTML = '';
            if (!data || !data.messages || data.messages.length === 0) {
                messagesList.innerHTML = '<div class="p-4 text-gray-500 text-center">Nenhuma mensagem</div>';
                return;
            }
            
            data.messages.forEach(message => {
                const div = document.createElement('div');
                div.className = `p-4 border-b hover:bg-gray-50 transition ${message.is_read ? 'bg-white' : 'bg-blue-50'}`;
                
                // Prepara o texto da mensagem (limitado a 100 caracteres)
                const messagePreview = message.message.length > 100 ? 
                    message.message.substring(0, 100) + '...' : 
                    message.message;
                
                div.innerHTML = `
                    <div class="d-flex">
                        <div class="flex-grow">
                            <div class="d-flex justify-content-between">
                                <h4 class="small fw-bold text-dark">${message.from_name}</h4>
                                <span class="smaller text-muted">
                                    ${new Date(message.created_at).toLocaleString('pt-BR')}
                                </span>
                            </div>
                            <p class="small text-secondary mb-2">
                                <strong class="text-dark">${message.subject}</strong><br>
                                ${messagePreview}
                            </p>
                            ${message.property_title ? `
                                <p class="smaller text-primary mb-1">
                                    Imóvel: ${message.property_title}
                                </p>
                            ` : ''}
                        </div>
                        ${!message.is_read ? `
                            <button class="text-blue-600 hover:text-blue-800 text-sm mark-read" data-id="${message.id}">
                                Marcar como lida
                            </button>
                        ` : ''}
                    </div>
                `;
                messagesList.appendChild(div);
            });
        } catch (err) {
            console.error('Erro ao carregar mensagens:', err);
            messagesList.innerHTML = `
                <div class="p-4 text-red-500 text-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    ${err.message}
                </div>
            `;
            // Oculta o badge em caso de erro
            messagesBadge.classList.add('hidden');
        }
    }

    // Função para marcar uma notificação como lida
    async function markNotificationAsRead(id) {
        try {
            const res = await fetch('api/markNotificationRead.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id })
            });
            
            if (!res.ok) throw new Error('Erro ao marcar notificação como lida');
            
            // Recarrega as notificações
            loadNotifications();
        } catch (err) {
            console.error('Erro:', err);
            alert('Erro ao marcar notificação como lida');
        }
    }

    // Função para marcar todas as notificações como lidas
    async function markAllNotificationsAsRead() {
        try {
            const res = await fetch('api/markNotificationRead.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({})  // Sem ID marca todas como lidas
            });
            
            if (!res.ok) throw new Error('Erro ao marcar notificações como lidas');
            
            // Recarrega as notificações
            loadNotifications();
        } catch (err) {
            console.error('Erro:', err);
            alert('Erro ao marcar notificações como lidas');
        }
    }

    // Event listener para marcar notificação individual como lida
    notificationsList.addEventListener('click', function(e) {
        if (e.target.classList.contains('mark-read')) {
            const id = e.target.dataset.id;
            markNotificationAsRead(id);
        }
    });

    // Event listener para marcar todas as notificações como lidas
    document.getElementById('mark-all-notifications-read').addEventListener('click', markAllNotificationsAsRead);

    // Verificar notificações a cada minuto
    setInterval(() => {
        // Sempre verifica se há novas notificações, mesmo com o dropdown fechado
        checkNewNotifications();
        
        // Atualiza as listas se estiverem abertas
        if (!notificationsDropdown.classList.contains('hidden')) {
            loadNotifications();
        }
        if (!messagesDropdown.classList.contains('hidden')) {
            loadMessages();
        }
    }, 60000);

    // Função para verificar novas notificações em segundo plano
    async function checkNewNotifications() {
        try {
            const res = await fetch('api/getNotifications.php');
            if (!res.ok) {
                throw new Error('Erro ao buscar notificações');
            }
            const data = await res.json();
            
            // Atualiza badge se houver notificações não lidas
            if (data && data.unread_count > 0) {
                notificationsBadge.classList.remove('hidden');
                notificationsBadge.style.display = 'block';
                
                // Se houver notificações novas e o dropdown estiver fechado, mostra um aviso
                if (notificationsDropdown.classList.contains('hidden')) {
                    showNotificationToast('Nova notificação recebida!');
                }
            } else {
                notificationsBadge.classList.add('hidden');
            }
        } catch (err) {
            console.error('Erro ao verificar notificações:', err);
        }
    }

    // Função para mostrar um toast de notificação
    function showNotificationToast(message) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 animate-fade-in';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        // Remove o toast após 3 segundos
        setTimeout(() => {
            toast.classList.add('animate-fade-out');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Verificar notificações imediatamente ao carregar a página
    checkNewNotifications();

    // Carregar inicialmente para mostrar os badges
    loadNotifications();
    loadMessages();
});
