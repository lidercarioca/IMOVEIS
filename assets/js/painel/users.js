// users.js: Gerenciamento de usuários (apenas para admin)
// users.js: Gerenciamento de usuários (CRUD) para administradores.

/**
 * Inicializa eventos e renderização dos usuários no painel administrativo.
 * Busca usuários, renderiza tabela e configura eventos de edição/exclusão.
 */
window.initUsers = function() {
  const usersSection = document.getElementById('users-section');
  if (!usersSection) return;

  const usersList = document.getElementById('users-list');
  const userFormContainer = document.getElementById('user-form-container');
  const userForm = document.getElementById('user-form');
  const btnAddUser = document.getElementById('btn-add-user');
  const btnCancelUser = document.getElementById('btn-cancel-user');
  const userFormTitle = document.getElementById('user-form-title');

  /**
   * Renderiza a tabela de usuários no painel.
   * Busca dados via API e atualiza o DOM.
   */
  function renderUsers() {
    usersList.innerHTML = '<div>Carregando...</div>';
    fetch('api/users.php')
      .then(res => res.json())
      .then(json => {
        if (!json.success) {
          usersList.innerHTML = '<div class="text-danger">Erro ao carregar usuários</div>';
          return;
        }
        if (!json.data.length) {
          usersList.innerHTML = '<div>Nenhum usuário cadastrado.</div>';
          return;
        }
        usersList.innerHTML = `<table class="table table-bordered"><thead><tr><th>ID</th><th>Usuário</th><th>Nome</th><th>E-mail</th><th>Tipo</th><th>Status</th><th>Ações</th><th>Excluir</th></tr></thead><tbody>${json.data.map(u => `
          <tr>
            <td>${u.id}</td>
            <td>${u.username}</td>
            <td>${u.name}</td>
            <td>${u.email}</td>
            <td>${u.role === 'admin' ? 'Administrador' : 'Usuário'}</td>
            <td>${u.active ? 'Ativo' : 'Inativo'}</td>
            <td>
              <button class="btn btn-sm btn-primary" data-edit="${u.id}">Editar</button>
            </td>
            <td>
              ${u.id !== 1 ? `
                <button class="btn btn-sm btn-danger" data-delete="${u.id}">
                  <i class="fas fa-trash"></i>
                </button>
              ` : ''}
            </td>
          </tr>`).join('')}</tbody></table>`;
        usersList.querySelectorAll('button[data-edit]').forEach(btn => {
          btn.addEventListener('click', () => editUser(btn.getAttribute('data-edit')));
        });
      });
  }

  /**
   * Exclui um usuário do sistema após confirmação.
   * @param {number|string} id - ID do usuário a ser excluído
   */
  async function excluirUsuario(id) {
    if (!confirm('Tem certeza que deseja excluir este usuário?')) {
      return;
    }

    try {
      const response = await fetch('api/deleteUser.php', { // Removido a barra inicial
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id })
      });

      let data;
      const contentType = response.headers.get('content-type');
      if (contentType && contentType.includes('application/json')) {
        data = await response.json();
      } else {
        // Se não for JSON, lê como texto para debug
        const text = await response.text();
        console.error('Resposta não-JSON recebida:', text);
        throw new Error('Resposta inválida do servidor');
      }

      if (data.success) {
        alert('Usuário excluído com sucesso!');
        renderUsers(); // Recarrega a lista
      } else {
        throw new Error(data.message || 'Erro desconhecido ao excluir usuário');
      }
    } catch (error) {
      console.error('Erro ao excluir usuário:', error);
      alert('Erro ao excluir usuário: ' + error.message);
    }
  }

  // Adiciona evento de clique para botões de excluir
  usersList.addEventListener('click', (e) => {
    const deleteBtn = e.target.closest('button[data-delete]');
    if (deleteBtn) {
      const userId = deleteBtn.getAttribute('data-delete');
      excluirUsuario(userId);
    }
  });

  /**
   * Exibe o formulário de usuário para edição ou criação.
   * @param {boolean} edit - Indica se é edição (true) ou criação (false)
   * @param {object} user - Objeto com os dados do usuário (apenas para edição)
   */
  function showForm(edit = false, user = {}) {
    userForm.reset();
    userFormContainer.style.display = 'block';
    userFormTitle.textContent = edit ? 'Editar Usuário' : 'Novo Usuário';
    document.getElementById('user-id').value = user.id || '';
    document.getElementById('user-username').value = user.username || '';
    document.getElementById('user-password').value = '';
    document.getElementById('user-name').value = user.name || '';
    document.getElementById('user-email').value = user.email || '';
    document.getElementById('user-role').value = user.role || 'user';
    if (edit) document.getElementById('user-username').setAttribute('readonly', 'readonly');
    else document.getElementById('user-username').removeAttribute('readonly');
  }

  /**
   * Oculta o formulário de usuário.
   */
  function hideForm() {
    userFormContainer.style.display = 'none';
  }

  btnAddUser.addEventListener('click', () => {
    showForm(false);
  });
  btnCancelUser.addEventListener('click', hideForm);

  userForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const id = document.getElementById('user-id').value;
    const username = document.getElementById('user-username').value;
    const password = document.getElementById('user-password').value;
    const name = document.getElementById('user-name').value;
    const email = document.getElementById('user-email').value;
    const role = document.getElementById('user-role').value;
    const method = id ? 'PUT' : 'POST';
    const body = JSON.stringify({ id, username, password, name, email, role });
    fetch('api/users.php', {
      method,
      headers: { 'Content-Type': 'application/json' },
      body
    })
      .then(res => res.json())
      .then(json => {
        if (json.success) {
          hideForm();
          renderUsers();
        } else {
          window.utils.mostrarErro(json.message || 'Erro ao salvar usuário');
        }
      });
  });

  /**
   * Inicia o processo de edição de um usuário.
   * Busca os dados do usuário e exibe no formulário.
   * @param {number|string} id - ID do usuário a ser editado
   */
  function editUser(id) {
    fetch('api/users.php')
      .then(res => res.json())
      .then(json => {
        const user = json.data.find(u => u.id == id);
        if (user) showForm(true, user);
      });
  }

  renderUsers();
};
