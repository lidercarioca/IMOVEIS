// Inicializar todos os dropdowns do Bootstrap
var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
  return new bootstrap.Dropdown(dropdownToggleEl)
});

// Toggle do sidebar
document.getElementById('toggle-sidebar').addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('show-sidebar');
    document.getElementById('main-content').classList.toggle('content-shifted');
});

// Função para lidar com notificações
function updateNotificationBadge() {
    const badge = document.getElementById('notifications-badge');
    if (unreadNotifications > 0) {
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}

// Função para lidar com mensagens
function updateMessageBadge() {
    const badge = document.getElementById('messages-badge');
    if (unreadMessages > 0) {
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}

// Responsividade em dispositivos móveis
function handleMobileView() {
    if (window.innerWidth < 768) {
        document.getElementById('sidebar').classList.remove('show');
        document.getElementById('main-content').classList.add('sidebar-hidden');
    }
}

// Event Listeners
window.addEventListener('resize', handleMobileView);
document.addEventListener('DOMContentLoaded', handleMobileView);