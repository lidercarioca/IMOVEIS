window.initUsers = function () {
    const el = {
        section: document.getElementById('users-section'),
        tableBody: document.querySelector('.users-table tbody'),
        empty: document.getElementById('users-empty'),

        formContainer: document.getElementById('user-form-container'),
        form: document.getElementById('user-form'),
        btnAdd: document.getElementById('btn-add-user'),
        btnCancel: document.getElementById('btn-cancel-user'),
        title: document.getElementById('user-form-title'),

        id: document.getElementById('user-id'),
        username: document.getElementById('user-username'),
        password: document.getElementById('user-password'),
        name: document.getElementById('user-name'),
        phone: document.getElementById('user-phone'),
        email: document.getElementById('user-email'),
        role: document.getElementById('user-role'),
    };

    if (!el.section) return;

    // =========================
    // API
    // =========================
    const api = {
        async request(url, options = {}) {
            const res = await fetch(url, {
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                ...options,
            });

            const text = await res.text();

            let json;
            try {
                json = JSON.parse(text);
            } catch {
                throw new Error('Resposta inválida da API');
            }

            if (!res.ok) {
                throw new Error(json.message || `Erro HTTP ${res.status}`);
            }

            if (!json.success) {
                throw new Error(json.message || 'Erro na operação');
            }

            return json;
        },

        getUsers() {
            // Use index.php to retrieve full user objects (includes email, active)
            return this.request('/api/v1/users/index.php?limit=1000');
        },

        getUser(id) {
            // new show.php returns single user object; adapt to legacy shape (data: [user])
            return this.request('/api/v1/users/show.php?id=' + id).then((json) => {
                if (json && json.data && !Array.isArray(json.data)) {
                    json.data = [json.data];
                }
                return json;
            });
        },

        saveUser(data) {
            const url = data && data.id ? '/api/v1/users/update.php' : '/api/v1/users/create.php';
            return this.request(url, {
                method: data && data.id ? 'PUT' : 'POST',
                body: JSON.stringify(data),
            });
        },

        deleteUser(id) {
            return this.request('/api/v1/users/destroy.php', {
                method: 'POST',
                body: JSON.stringify({ id }),
            });
        },
    };

    // =========================
    // MODAL
    // =========================
    function openForm(edit = false, user = {}) {
        el.form.reset();
        el.formContainer.style.display = 'block';

        el.title.textContent = edit ? 'Editar Usuário' : 'Novo Usuário';

        el.id.value = user.id || '';
        el.username.value = user.username || '';
        el.password.value = '';
        el.name.value = user.name || '';
        el.phone.value = user.phone || '';
        el.email.value = user.email || '';
        el.role.value = user.role || 'user';

        el.username.disabled = edit;
        el.password.required = !edit;
    }

    function closeForm() {
        el.formContainer.style.display = 'none';

        // 🔥 RESET COMPLETO (ESSENCIAL)
        el.form.reset();
        el.id.value = '';
        el.username.disabled = false;
        el.password.required = true;
        el.password.value = '';
        el.phone.value = '';

        el.title.textContent = 'Novo Usuário';
    }

    // =========================
    // RENDER
    // =========================
    async function renderUsers() {
        el.tableBody.innerHTML = '';

        try {
            const json = await api.getUsers();

            if (!json.data || json.data.length === 0) {
                el.empty.style.display = 'block';
                return;
            }

            el.empty.style.display = 'none';

            el.tableBody.innerHTML = json.data
                .map(
                    (u) => `
          <tr>
            <td>${u.id}</td>
            <td>${u.username}</td>
            <td>${u.name}</td>
            <td>${u.email}</td>
            <td>${u.role}</td>
            <td>${u.active ? 'Ativo' : 'Inativo'}</td>
            <td>
              <button data-edit="${u.id}" class="btn btn-primary btn-sm">
                Editar
              </button>
            </td>
            <td>
              ${
                  u.role !== 'admin'
                      ? `<button data-delete="${u.id}" class="btn btn-danger btn-sm">
                      Excluir
                    </button>`
                      : ''
              }
            </td>
          </tr>
        `
                )
                .join('');
        } catch (err) {
            el.tableBody.innerHTML = `
        <tr><td colspan="7">${err.message}</td></tr>
      `;
        }
    }

    // =========================
    // EVENTS
    // =========================
    el.tableBody.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-edit]');
        const delBtn = e.target.closest('[data-delete]');

        if (editBtn) {
            try {
                const json = await api.getUser(editBtn.dataset.edit);
                openForm(true, json.data[0]);
            } catch (err) {
                alert(err.message);
            }
        }

        if (delBtn) {
            if (!confirm('Excluir usuário?')) return;

            try {
                await api.deleteUser(delBtn.dataset.delete);
                await renderUsers();
            } catch (err) {
                alert(err.message);
            }
        }
    });

    // =========================
    // ACTIONS
    // =========================
    el.btnAdd.addEventListener('click', () => openForm(false));
    el.btnCancel.addEventListener('click', closeForm);

    el.form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const data = {
            username: el.username.value,
            name: el.name.value,
            phone: el.phone.value,
            email: el.email.value,
            role: el.role.value,
        };

        // incluir id somente se existir (evitar enviar id vazio que o backend rejeita)
        if (el.id.value && el.id.value !== '') {
            data.id = el.id.value;
        }

        if (el.password.value) {
            data.password = el.password.value;
        }

        try {
            await api.saveUser(data);

            // 🔥 RESET FORTE (resolve seu bug de "primeiro save trava tudo")
            closeForm();

            el.id.value = '';
            el.username.disabled = false;
            el.password.required = true;

            await renderUsers();

            window.utils?.mostrarSucesso?.('Usuário salvo com sucesso!');
        } catch (err) {
            console.error(err);

            // 🔥 garante que nunca trava UI mesmo com erro
            closeForm();

            window.utils?.mostrarErro?.(err.message);
        }
    });

    // INIT
    renderUsers();
};
