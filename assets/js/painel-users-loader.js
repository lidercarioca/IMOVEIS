// Script para carregar users.html na aba Usuários
// Garante que o carregamento ocorra após o DOM e após o main-content

document.addEventListener('DOMContentLoaded', function () {
  const usersTabBtn = document.querySelector('.tab-link[href="#users"]');
  if (usersTabBtn) {
    usersTabBtn.addEventListener('click', async function () {
      const container = document.getElementById('users-dynamic-content');
      if (!container) return;
      container.innerHTML = '<div class="flex flex-col items-center justify-center min-h-[200px] text-gray-500"><i class="fas fa-users-cog text-4xl mb-4 animate-spin"></i><p>Carregando usuários...</p></div>';
      try {
        const res = await fetch('views/admin/users.php');
        if (!res.ok) throw new Error('Erro ao carregar users.php: ' + res.status);
        const html = await res.text();
        container.innerHTML = html;
        // Sempre recarrega o JS de users para garantir a inicialização dos eventos
        const oldScript = document.getElementById('users-js');
        if (oldScript) {
          oldScript.remove();
        }
        const script = document.createElement('script');
        script.src = 'assets/js/users.js';
        script.id = 'users-js';
        script.onload = function() {
          if (window.initUsers) window.initUsers();
        };
        document.body.appendChild(script);
      } catch (err) {
        container.innerHTML = `<div class=\"text-red-500\">Erro ao carregar gerenciamento de usuários: ${err.message}</div>`;
      }
    });
  }
});
