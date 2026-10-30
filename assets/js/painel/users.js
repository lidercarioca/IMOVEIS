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
        brokerDefaultCommission: document.getElementById('user-broker-default-commission'),
        brokerDefaultCommissionField: document.querySelector('.broker-default-commission-field'),
        role: document.getElementById('user-role'),
    };

    if (!el.section) return;

    let isLoading = false;
    let submitListener = null;

    // =========================
    // API
    // =========================
    const api = {
        async request(url, options = {}) {
            const result = await apiCall(url, {
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers,
                },
                ...options,
            });

            if (!result.success) {
                throw new Error(result.error || 'Erro na operação');
            }

            return result.data;
        },

        getUsers() {
            return this.request('/api/v1/users/index.php');
        },

        getUser(id) {
            return this.request('/api/v1/users/show.php?id=' + id);
        },

        saveUser(data) {
            const method = data.id ? 'PUT' : 'POST';
            const url = data.id ? '/api/v1/users/update.php' : '/api/v1/users/create.php';
            const payload = data.id
                ? {
                      id: data.id,
                      name: data.name,
                      phone: data.phone,
                      email: data.email,
                      role: data.role,
                      active: data.active,
                      force_password_change: data.force_password_change,
                      password: data.password,
                      broker_default_commission_percentage:
                          data.broker_default_commission_percentage ?? null,
                  }
                : {
                      username: data.username,
                      name: data.name,
                      phone: data.phone,
                      email: data.email,
                      role: data.role,
                      active: data.active,
                      force_password_change: data.force_password_change,
                      password: data.password,
                      broker_default_commission_percentage:
                          data.broker_default_commission_percentage ?? null,
                  };

            // Remove campos indefinidos antes de enviar
            Object.keys(payload).forEach((key) => {
                if (payload[key] === undefined) {
                    delete payload[key];
                }
            });

            return this.request(url, {
                method,
                body: JSON.stringify(payload),
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

        const currentUser = window.currentUser || null;
        const currentUserRole = String(
            currentUser?.role || window.currentUserRole || ''
        ).toLowerCase();
        const currentUserIsSuperAdmin =
            Number(currentUser?.is_superadmin ?? window.currentUserIsSuperAdmin ?? 0) === 1;
        const canManageBrokerDefault = currentUserIsSuperAdmin || currentUserRole === 'admin';

        if (el.brokerDefaultCommissionField) {
            el.brokerDefaultCommissionField.style.display = canManageBrokerDefault ? '' : 'none';
        }

        if (el.brokerDefaultCommission) {
            el.brokerDefaultCommission.disabled = !canManageBrokerDefault;
            const rawValue = user.broker_default_commission_percentage ?? '';
            el.brokerDefaultCommission.value =
                rawValue === null || rawValue === undefined || rawValue === ''
                    ? ''
                    : String(rawValue);
        }

        el.id.value = user.id || '';
        el.username.value = user.username || '';
        el.password.value = '';
        el.name.value = user.name || '';
        if (el.phone) el.phone.value = user.phone || '';
        el.email.value = user.email || '';
        el.role.value = user.role || 'user';

        el.username.disabled = edit;
        el.password.required = !edit;

        el.title.textContent = edit ? 'Editar Usuário' : 'Novo Usuário';
        el.formContainer.style.display = 'flex';
        el.formContainer.classList.add('show');
    }

    function closeForm() {
        el.formContainer.style.display = 'none';
        el.form.reset();

        el.id.value = '';
        el.username.value = '';
        el.password.value = '';
        el.name.value = '';
        if (el.phone) el.phone.value = '';
        el.email.value = '';
        el.role.value = 'user';

        el.username.disabled = false;
        el.password.required = true;

        el.title.textContent = 'Novo Usuário';
        isLoading = false;
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

            const currentUser = json.data.find(
                (item) => String(item.id) === String(window.currentUserId)
            );

            // Fallback para variáveis globais de sessão se currentUser não for encontrado
            const currentUserIsSuperAdmin =
                Number(currentUser?.is_superadmin) === 1 ||
                currentUser?.role?.toLowerCase() === 'superadmin' ||
                window.currentUserIsSuperAdmin === true ||
                Number(window.currentUserIsSuperAdmin) === 1;

            const currentUserRole =
                currentUser?.role?.toLowerCase() || window.currentUserRole?.toLowerCase() || null;

            const currentUserTenantId = currentUser?.tenant_id ?? window.currentTenantId ?? null;

            el.tableBody.innerHTML = json.data
                .map((u) => {
                    const isCurrentUser = String(u.id) === String(window.currentUserId);
                    const targetRole = u.role?.toLowerCase();
                    const targetIsAdmin = targetRole === 'admin';
                    const canDelete =
                        !isCurrentUser &&
                        (currentUserIsSuperAdmin ||
                            (currentUserRole === 'admin' &&
                                u.tenant_id !== null &&
                                u.tenant_id === currentUserTenantId &&
                                !targetIsAdmin));

                    return `
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
                  canDelete
                      ? `<button data-delete="${u.id}" class="btn btn-danger btn-sm">Excluir</button>`
                      : ''
              }
            </td>
          </tr>
        `;
                })
                .join('');
        } catch (err) {
            el.tableBody.innerHTML = `
        <tr><td colspan="8">${err.message}</td></tr>
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
                const response = await api.getUser(editBtn.dataset.edit);
                openForm(true, response.data);
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

    // Remover qualquer listener anterior para evitar duplicatas
    if (submitListener) {
        el.form.removeEventListener('submit', submitListener);
    }

    submitListener = async (e) => {
        e.preventDefault();

        if (isLoading) {
            return;
        }

        isLoading = true;

        try {
            const id = el.id.value ? parseInt(el.id.value) : null;

            const currentUser = window.currentUser || null;
            const currentUserRole = String(
                currentUser?.role || window.currentUserRole || ''
            ).toLowerCase();
            const currentUserIsSuperAdmin =
                Number(currentUser?.is_superadmin ?? window.currentUserIsSuperAdmin ?? 0) === 1;
            const canManageBrokerDefault = currentUserIsSuperAdmin || currentUserRole === 'admin';

            const data = {
                id,
                username: el.username.value,
                name: el.name.value,
                phone: el.phone ? el.phone.value : undefined,
                email: el.email.value,
                role: el.role.value,
                broker_default_commission_percentage:
                    canManageBrokerDefault && el.brokerDefaultCommission
                        ? el.brokerDefaultCommission.value === ''
                            ? null
                            : Number(el.brokerDefaultCommission.value)
                        : undefined,
            };

            if (el.password.value) {
                data.password = el.password.value;
            }

            await api.saveUser(data);
            closeForm();
            await renderUsers();

            window.utils?.mostrarSucesso?.('Usuário salvo com sucesso!');
        } catch (err) {
            window.utils?.mostrarErro?.(err.message);
            closeForm();
        } finally {
            isLoading = false;
        }
    };

    el.form.addEventListener('submit', submitListener);

    // Listener no botão submit como fallback
    const submitBtn = el.form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.addEventListener('click', (e) => {
            e.preventDefault();

            submitListener({
                preventDefault: () => {},
                target: el.form,
            });
        });
    }

    // NÃO remover listeners do formContainer para não quebrar event delegation
    el.formContainer.addEventListener(
        'submit',
        (e) => {
            if (e.target === el.form) {
                // Listener do form vai lidar
            }
        },
        true
    );

    // INIT
    renderUsers();
};
