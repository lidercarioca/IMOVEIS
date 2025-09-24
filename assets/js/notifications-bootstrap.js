// Gerenciamento de notificações usando Bootstrap
document.addEventListener('DOMContentLoaded', function() {
    // Elementos do DOM
    const notificationsBtn = document.getElementById('notifications-btn');
    const notificationsList = document.getElementById('notifications-list');
    const notificationBadge = document.getElementById('notifications-badge');
    const markAllReadBtn = document.getElementById('mark-all-notifications-read');

    // Dropdown de notificações usando Bootstrap
    const notificationsDropdown = new bootstrap.Dropdown(notificationsBtn);

    // Função para criar um item de notificação
    function createNotificationItem(notification) {
        const item = document.createElement('div');
        item.className = `notification-item p-3 border-bottom ${notification.unread ? 'unread' : ''}`;
        if (notification.type) {
            item.classList.add(`type-${notification.type}`);
        }

        const content = `
            <div class="d-flex align-items-start">
                <div class="icon-wrapper text-${notification.type || 'info'} me-3">
                    <i class="fas ${getNotificationIcon(notification.type)} fs-5"></i>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1">${notification.title}</h6>
                    <p class="text-muted mb-1 small">${notification.message}</p>
                    <span class="text-muted small">${formatNotificationDate(notification.date)}</span>
                </div>
                ${notification.unread ? '<span class="badge bg-primary rounded-pill">Novo</span>' : ''}
            </div>
        `;

        item.innerHTML = content;
        return item;
    }

    // Função para obter o ícone com base no tipo
    function getNotificationIcon(type) {
        switch (type) {
            case 'success': return 'fa-check-circle';
            case 'warning': return 'fa-exclamation-triangle';
            case 'danger': return 'fa-times-circle';
            case 'info': return 'fa-info-circle';
            default: return 'fa-bell';
        }
    }

    // Formatar data da notificação
    function formatNotificationDate(date) {
        return new Date(date).toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Atualizar contador de notificações
    function updateNotificationCount(count) {
        if (count > 0) {
            notificationBadge.classList.remove('d-none');
            notificationBadge.textContent = count > 99 ? '99+' : count;
        } else {
            notificationBadge.classList.add('d-none');
        }
    }

    // Carregar notificações do servidor
    async function loadNotifications() {
        try {
            const response = await fetch('/api/getNotifications.php');
            const data = await response.json();
            
            notificationsList.innerHTML = '';
            
            if (data.notifications && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    notificationsList.appendChild(createNotificationItem(notification));
                });
                updateNotificationCount(data.unreadCount);
            } else {
                notificationsList.innerHTML = '<div class="p-3 text-center text-muted">Nenhuma notificação</div>';
                updateNotificationCount(0);
            }
        } catch (error) {
            console.error('Erro ao carregar notificações:', error);
            notificationsList.innerHTML = '<div class="p-3 text-center text-danger">Erro ao carregar notificações</div>';
        }
    }

    // Marcar todas as notificações como lidas
    async function markAllAsRead() {
        try {
            await fetch('/api/markNotificationRead.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    markAll: true
                })
            });

            loadNotifications();
            notificationsDropdown.hide();
        } catch (error) {
            console.error('Erro ao marcar notificações como lidas:', error);
        }
    }

    // Event Listeners
    notificationsBtn.addEventListener('show.bs.dropdown', loadNotifications);
    markAllReadBtn.addEventListener('click', markAllAsRead);

    // Carregar notificações iniciais
    loadNotifications();

    // Polling para novas notificações (a cada 30 segundos)
    setInterval(loadNotifications, 30000);
});