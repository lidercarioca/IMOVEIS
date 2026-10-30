// Inicializar variáveis globais de contador
let unreadNotifications = 0;
let unreadMessages = 0;

// Inicializar todos os dropdowns do Bootstrap
var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
  return new bootstrap.Dropdown(dropdownToggleEl)
});

// Toggle do sidebar
const sidebarToggleBtn = document.getElementById('toggle-sidebar');
if (sidebarToggleBtn) {
  sidebarToggleBtn.addEventListener('click', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const companyName = document.getElementById('company-name');
    const sidebarTexts = document.querySelectorAll('.sidebar-text');

    if (!sidebar || !mainContent) return;

    const expanded = sidebar.classList.contains('sidebar-expanded');

    if (expanded) {
      sidebar.classList.remove('sidebar-expanded');
      sidebar.classList.add('sidebar-collapsed');
      mainContent.classList.remove('main-content-expanded');
      mainContent.classList.add('main-content-collapsed');
      document.body.classList.add('sidebar-collapsed');
      document.body.classList.remove('sidebar-expanded');
      if (companyName) companyName.style.display = 'none';
      sidebarTexts.forEach(text => text.style.display = 'none');
    } else {
      sidebar.classList.remove('sidebar-collapsed');
      sidebar.classList.add('sidebar-expanded');
      mainContent.classList.remove('main-content-collapsed');
      mainContent.classList.add('main-content-expanded');
      document.body.classList.remove('sidebar-collapsed');
      document.body.classList.add('sidebar-expanded');
      if (companyName) companyName.style.display = 'block';
      sidebarTexts.forEach(text => text.style.display = 'block');
    }
  });
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