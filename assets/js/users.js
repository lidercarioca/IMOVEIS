// users.js: Gerenciamento de usuários (CRUD) para administradores
window.initUsers = function() {
  const usersSection = document.getElementById('users-section');
  if (!usersSection) return;

  // Elementos do DOM
  const usersList = document.getElementById('users-list');
  const userFormContainer = document.getElementById('user-form-container');
  const userForm = document.getElementById('user-form');
  const btnAddUser = document.getElementById('btn-add-user');
  const btnCancelUser = document.getElementById('btn-cancel-user');
  const userFormTitle = document.getElementById('user-form-title');
  
  // Inicializa o modal do Bootstrap
  const userModal = new bootstrap.Modal(userFormContainer);

  /**
   * Renderiza a tabela de usuários
   */
  function renderUsers() {
    const tableBody = usersList.querySelector('tbody');
    if (!tableBody) return;
    
    tableBody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></td></tr>';
    
    fetch('api/users.php')
      .then(res => res.json())
      .then(json => {
        if (!json.success) {
          tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erro ao carregar usuários</td></tr>';
          return;
        }
        if (!json.data.length) {
          tableBody.innerHTML = '<tr><td colspan="7" class="text-center">Nenhum usuário cadastrado.</td></tr>';
          return;
        }
        
        tableBody.innerHTML = json.data.map(u => `
          <tr>
            <td>${u.id}</td>
            <td>${u.username}</td>
            <td>${u.name}</td>
            <td>${u.email}</td>
            <td>
              <span class="badge ${u.role === 'admin' ? 'bg-primary' : 'bg-secondary'}">
                ${u.role === 'admin' ? 'Administrador' : 'Usuário'}
              </span>
            </td>
            <td>
              <span class="badge ${u.active ? 'bg-success' : 'bg-danger'}">
                ${u.active ? 'Ativo' : 'Inativo'}
              </span>
            </td>
            <td class="text-center">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" data-edit="${u.id}" title="Editar">
                  <i class="fas fa-edit"></i>
                </button>
                ${u.id !== 1 ? `
                  <button class="btn btn-outline-danger" data-delete="${u.id}" title="Excluir">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                ` : ''}
              </div>
            </td>
          </tr>
        `).join('');
      })
      .catch(err => {
        tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erro ao carregar usuários</td></tr>';
        console.error('Erro ao carregar usuários:', err);
      });
  }

  /**
   * Exclui um usuário após confirmação
   */
  async function excluirUsuario(id) {
    const result = await Swal.fire({
      title: 'Confirmar exclusão?',
      text: 'Esta ação não pode ser desfeita!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Sim, excluir!',
      cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;
    
    try {
      const res = await fetch('api/deleteUser.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
      });
      
      const contentType = res.headers.get('content-type');
      let data;
      
      if (contentType && contentType.includes('application/json')) {
        data = await res.json();
      } else {
        const text = await res.text();
        console.error('Resposta não-JSON recebida:', text);
        throw new Error('Resposta inválida do servidor');
      }
      
      if (data.success) {
        renderUsers();
        Swal.fire('Sucesso!', 'Usuário excluído com sucesso.', 'success');
      } else {
        throw new Error(data.message || 'Erro ao excluir usuário');
      }
    } catch (err) {
      Swal.fire('Erro!', err.message || 'Erro ao excluir usuário', 'error');
      console.error('Erro ao excluir usuário:', err);
    }
  }

  /**
   * Exibe o formulário de usuário em modal
   */
  function showForm(edit = false, user = {}) {
    userForm.reset();
    userForm.classList.remove('was-validated');
    userFormTitle.textContent = edit ? 'Editar Usuário' : 'Novo Usuário';
    
    // Preenche os campos
    document.getElementById('user-id').value = user.id || '';
    document.getElementById('user-username').value = user.username || '';
    document.getElementById('user-password').value = '';
    document.getElementById('user-name').value = user.name || '';
    document.getElementById('user-email').value = user.email || '';
    document.getElementById('user-role').value = user.role || 'user';
    
    // Configura o campo username
    const usernameInput = document.getElementById('user-username');
    if (edit) {
      usernameInput.setAttribute('readonly', 'readonly');
      usernameInput.classList.add('bg-light');
    } else {
      usernameInput.removeAttribute('readonly');
      usernameInput.classList.remove('bg-light');
    }

    // Mostra o modal
    userModal.show();
  }

  /**
   * Fecha o formulário de usuário
   */
  function hideForm() {
    userModal.hide();
  }

  // Event Listeners
  btnAddUser.addEventListener('click', () => showForm(false));
  btnCancelUser.addEventListener('click', hideForm);

  // Handler para botões de ação na tabela
  usersList.addEventListener('click', (e) => {
    const target = e.target.closest('button');
    if (!target) return;

    if (target.hasAttribute('data-delete')) {
      excluirUsuario(target.getAttribute('data-delete'));
    } else if (target.hasAttribute('data-edit')) {
      editUser(target.getAttribute('data-edit'));
    }
  });

  // Handler para envio do formulário
  userForm.addEventListener('submit', async function (e) {
    e.preventDefault();
    
    // Validação do formulário
    if (!this.checkValidity()) {
      e.stopPropagation();
      this.classList.add('was-validated');
      return;
    }

    const formData = {
      id: document.getElementById('user-id').value,
      username: document.getElementById('user-username').value,
      password: document.getElementById('user-password').value,
      name: document.getElementById('user-name').value,
      email: document.getElementById('user-email').value,
      role: document.getElementById('user-role').value
    };

    const method = formData.id ? 'PUT' : 'POST';
    
    try {
      const res = await fetch('api/users.php', {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
      });
      const json = await res.json();
      
      if (json.success) {
        hideForm();
        renderUsers();
        Swal.fire('Sucesso!', 'Usuário salvo com sucesso!', 'success');
      } else {
        throw new Error(json.message || 'Erro ao salvar usuário');
      }
    } catch (err) {
      Swal.fire('Erro!', err.message || 'Erro ao salvar usuário', 'error');
      console.error('Erro ao salvar usuário:', err);
    }
  });

  /**
   * Carrega e exibe dados do usuário para edição
   */
  async function editUser(id) {
    try {
      const res = await fetch('api/users.php');
      const json = await res.json();
      
      if (!json.success) {
        throw new Error(json.message || 'Erro ao carregar dados do usuário');
      }
      
      const user = json.data.find(u => u.id == id);
      if (user) {
        showForm(true, user);
      } else {
        throw new Error('Usuário não encontrado');
      }
    } catch (err) {
      Swal.fire('Erro!', err.message || 'Erro ao carregar dados do usuário', 'error');
      console.error('Erro ao carregar dados do usuário:', err);
    }
  }

  // Inicialização
  renderUsers();
};
