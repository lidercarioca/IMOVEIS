// Follow-up Automático - JavaScript
// Gerencia sequências de follow-up, agendamento e execução


let followupStats = {};
let followupModalListenersInitialized = false;

// Carregar dashboard de follow-up
async function loadFollowupDashboard() {
    try {
        await loadFollowupStats();
        await loadFollowupSequences();
        setupFollowupEventListeners();
    } catch (error) {
        console.error('Erro ao carregar dashboard de follow-up:', error);
        showFollowupError('Erro ao carregar dados de follow-up');
    }
}

// Carregar estatísticas de follow-up
async function loadFollowupStats() {
    try {
        const response = await fetch('/api/v1/leads/followup/stats.php');
        const data = await response.json();

        if (data.success) {
            followupStats = data.data;
            updateFollowupStatsDisplay();
        } else {
            throw new Error(data.error || 'Erro ao carregar estatísticas');
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
        throw error;
    }
}

// Atualizar display das estatísticas
function updateFollowupStatsDisplay() {
    document.getElementById('active-sequences-count').textContent = followupStats.active_sequences || 0;
    document.getElementById('pending-followups-count').textContent = followupStats.pending_followups || 0;
    document.getElementById('executed-today-count').textContent = followupStats.executed_today || 0;
    document.getElementById('total-followups-count').textContent =
        (followupStats.pending_followups || 0) + (followupStats.executed_today || 0);
}

// Carregar sequências de follow-up
async function loadFollowupSequences() {
    const container = document.getElementById('sequences-container');

    try {
        container.innerHTML = '<div class="text-center py-8 text-gray-500"><i class="fas fa-spinner fa-spin text-2xl mb-4"></i><p>Carregando sequências...</p></div>';

        const response = await fetch('/api/v1/leads/followup/sequences.php');
        const data = await response.json();

        if (data.success) {
            renderSequences(data.data);
        } else {
            throw new Error(data.error || 'Erro ao carregar sequências');
        }
    } catch (error) {
        console.error('Erro ao carregar sequências:', error);
        container.innerHTML = '<div class="text-center py-8 text-red-500"><i class="fas fa-exclamation-circle text-2xl mb-4"></i><p>Erro ao carregar sequências</p><p class="text-sm">' + error.message + '</p></div>';
    }
}

// Renderizar sequências
function renderSequences(sequences) {
    const container = document.getElementById('sequences-container');

    if (!sequences || sequences.length === 0) {
        container.innerHTML = `
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-envelope-open-text text-4xl mb-4"></i>
                <h3 class="text-lg font-medium mb-2">Nenhuma sequência criada</h3>
                <p class="mb-4">Crie sua primeira sequência de follow-up automático para começar a nutrir seus leads.</p>
                <button id="create-first-sequence-btn" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    <i class="fas fa-plus mr-2"></i> Criar Primeira Sequência
                </button>
            </div>
        `;
        return;
    }

    let html = '<div class="space-y-4">';

    sequences.forEach(sequence => {
        const statusClass = sequence.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
        const statusText = sequence.is_active ? 'Ativa' : 'Inativa';

        html += `
            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1">
                        <h4 class="text-lg font-semibold text-gray-900">${sequence.name}</h4>
                        <p class="text-sm text-gray-600 mt-1">${sequence.description || 'Sem descrição'}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 text-xs rounded-full ${statusClass}">${statusText}</span>
                        <div class="dropdown relative">
                            <button type="button" class="text-gray-400 hover:text-gray-600 p-1 sequence-dropdown-toggle" data-sequence-id="${sequence.id}">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div id="menu-${sequence.id}" class="sequence-dropdown-menu hidden absolute right-0 mt-1 w-48 bg-white rounded-md shadow-lg z-10 border">
                                <div class="py-1">
                                    <button type="button" class="sequence-action block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-action="edit" data-sequence-id="${sequence.id}">
                                        <i class="fas fa-edit mr-2"></i> Editar
                                    </button>
                                    <button type="button" class="sequence-action block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-action="toggle-status" data-sequence-id="${sequence.id}" data-current-status="${sequence.is_active}">
                                        <i class="fas fa-${sequence.is_active ? 'pause' : 'play'} mr-2"></i> ${sequence.is_active ? 'Pausar' : 'Ativar'}
                                    </button>
                                    <button type="button" class="sequence-action block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100" data-action="delete" data-sequence-id="${sequence.id}">
                                        <i class="fas fa-trash mr-2"></i> Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Trigger:</span>
                        <span class="ml-1 font-medium">${getTriggerLabel(sequence.trigger_condition, sequence.trigger_value)}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Passos:</span>
                        <span class="ml-1 font-medium">${sequence.steps_count || 0}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Agendados:</span>
                        <span class="ml-1 font-medium">${sequence.scheduled_count || 0}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Criado em:</span>
                        <span class="ml-1 font-medium">${formatDate(sequence.created_at)}</span>
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
    setupSequenceContainerDelegation();
}

function setupSequenceContainerDelegation() {
    const container = document.getElementById('sequences-container');
    if (!container || container.dataset.followupDelegated === 'true') {
        return;
    }

    container.addEventListener('click', function (event) {
        const toggleButton = event.target.closest('.sequence-dropdown-toggle');
        if (toggleButton) {
            handleSequenceToggleClick(event, toggleButton);
            return;
        }

        const actionButton = event.target.closest('.sequence-action');
        if (actionButton) {
            handleSequenceActionClick(event, actionButton);
            return;
        }
    });

    container.dataset.followupDelegated = 'true';
}

function handleSequenceToggleClick(event, button) {
    event.stopPropagation();
    event.preventDefault();
    const dropdown = button.closest('.dropdown');
    if (!dropdown) {
        return;
    }

    const menu = dropdown.querySelector('.sequence-dropdown-menu');
    if (!menu) {
        return;
    }

    menu.classList.toggle('hidden');
}

function handleSequenceActionClick(event, button) {
    event.preventDefault();
    event.stopPropagation();

    const action = button.dataset.action;
    const sequenceId = button.dataset.sequenceId;


    if (!action || !sequenceId) {
        return;
    }

    if (action === 'edit') {
        editSequence(sequenceId);
    } else if (action === 'toggle-status') {
        const currentStatus = button.dataset.currentStatus === 'true' || button.dataset.currentStatus === '1';
        toggleSequenceStatus(sequenceId, currentStatus);
    } else if (action === 'delete') {
        deleteSequence(sequenceId);
    }
}

// Configurar event listeners
function setupFollowupEventListeners() {
    // Botão criar sequência
    document.getElementById('create-sequence-btn')?.addEventListener('click', () => openSequenceModal());
    document.getElementById('create-first-sequence-btn')?.addEventListener('click', () => openSequenceModal());

    // Botão executar follow-ups pendentes
    document.getElementById('execute-followups-btn')?.addEventListener('click', executePendingFollowups);

    // Botão atualizar
    document.getElementById('refresh-sequences-btn')?.addEventListener('click', () => {
        loadFollowupStats();
        loadFollowupSequences();
    });
}

// Abrir modal para criar/editar sequência
function openSequenceModal(sequenceId = null) {
    const modal = document.getElementById('sequence-modal');
    const title = document.getElementById('sequence-modal-title');
    const form = document.getElementById('sequence-form');


    // Reset form
    form.reset();
    delete form.dataset.sequenceId;
    document.getElementById('steps-container').innerHTML = '';

    if (sequenceId) {
        title.textContent = 'Editar Sequência';
        form.dataset.sequenceId = sequenceId;
        loadSequenceForEdit(sequenceId);
    } else {
        title.textContent = 'Criar Nova Sequência';
        addStepToForm(); // Adicionar primeiro passo vazio
    }

    modal.classList.remove('hidden');
    setupModalEventListeners();
}

// Carregar sequência para edição
async function loadSequenceForEdit(sequenceId) {
    try {
        const url = `/api/v1/leads/followup/sequences.php?id=${sequenceId}`;
        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.data) {
            const sequence = data.data;
            document.getElementById('sequence-name').value = sequence.name || '';
            document.getElementById('sequence-trigger').value = sequence.trigger_condition || '';
            document.getElementById('sequence-trigger-value').value = sequence.trigger_value || '';
            document.getElementById('sequence-description').value = sequence.description || '';

            // Carregar passos
            if (sequence.steps && sequence.steps.length > 0) {
                sequence.steps.forEach(step => addStepToForm(step));
            } else {
                addStepToForm(); // Pelo menos um passo vazio
            }
        } else {
            throw new Error(data.error || 'Erro ao carregar sequência');
        }
    } catch (error) {
        console.error('Erro ao carregar sequência:', error);
        showFollowupError('Erro ao carregar sequência: ' + error.message);
    }
}

// Adicionar passo ao formulário
function addStepToForm(stepData = null) {
    const container = document.getElementById('steps-container');
    const stepIndex = container.children.length;

    const stepHtml = `
        <div class="step-item border border-gray-200 rounded-lg p-4 bg-gray-50">
            <div class="flex justify-between items-center mb-4">
                <h5 class="font-medium text-gray-900">Passo ${stepIndex + 1}</h5>
                <button type="button" onclick="removeStep(this)" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ação</label>
                    <select name="steps[${stepIndex}][action_type]" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecione</option>
                        <option value="email" ${stepData?.action_type === 'email' ? 'selected' : ''}>E-mail</option>
                        <option value="sms" ${stepData?.action_type === 'sms' ? 'selected' : ''}>SMS</option>
                        <option value="call_reminder" ${stepData?.action_type === 'call_reminder' ? 'selected' : ''}>Lembrete de Ligação</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Delay (dias)</label>
                    <input type="number" name="steps[${stepIndex}][delay_days]" min="0" value="${stepData?.delay_days || 0}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Delay (horas)</label>
                    <input type="number" name="steps[${stepIndex}][delay_hours]" min="0" value="${stepData?.delay_hours || 0}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="email-fields" style="display: ${stepData?.action_type === 'email' ? 'block' : 'none'};">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assunto do E-mail</label>
                        <input type="text" name="steps[${stepIndex}][template_subject]" value="${stepData?.template_subject || ''}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Assunto do e-mail">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Corpo do E-mail</label>
                        <textarea name="steps[${stepIndex}][template_body]" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Corpo do e-mail (pode usar {{nome}}, {{email}}, etc.)">${stepData?.template_body || ''}</textarea>
                    </div>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', stepHtml);

    // Adicionar event listener para mudança de tipo de ação
    const stepElement = container.lastElementChild;
    const actionSelect = stepElement.querySelector('select[name*="[action_type]"]');
    actionSelect.addEventListener('change', function () {
        const emailFields = stepElement.querySelector('.email-fields');
        emailFields.style.display = this.value === 'email' ? 'block' : 'none';
    });
}

// Remover passo
function removeStep(button) {
    const stepItem = button.closest('.step-item');
    stepItem.remove();
    updateStepNumbers();
}

// Atualizar numeração dos passos
function updateStepNumbers() {
    const steps = document.querySelectorAll('.step-item');
    steps.forEach((step, index) => {
        const title = step.querySelector('h5');
        title.textContent = `Passo ${index + 1}`;
    });
}

// Configurar event listeners do modal
function setupModalEventListeners() {
    if (followupModalListenersInitialized) {
        return;
    }

    // Fechar modal
    document.getElementById('close-sequence-modal').addEventListener('click', closeSequenceModal);
    document.getElementById('cancel-sequence-btn').addEventListener('click', closeSequenceModal);

    // Adicionar passo
    document.getElementById('add-step-btn').addEventListener('click', () => addStepToForm());

    // Submit form
    document.getElementById('sequence-form').addEventListener('submit', handleSequenceSubmit);

    // Fechar ao clicar fora
    document.getElementById('sequence-modal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeSequenceModal();
        }
    });

    followupModalListenersInitialized = true;
}

// Fechar modal
function closeSequenceModal() {
    const form = document.getElementById('sequence-form');
    delete form.dataset.sequenceId;
    document.getElementById('sequence-modal').classList.add('hidden');
}

// Enviar formulário
async function handleSequenceSubmit(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const sequenceData = {
        name: formData.get('name'),
        trigger_condition: formData.get('trigger_condition'),
        trigger_value: formData.get('trigger_value'),
        description: formData.get('description'),
        steps: []
    };

    // Coletar passos
    const steps = document.querySelectorAll('.step-item');
    steps.forEach((step, index) => {
        const stepData = {
            step_order: index + 1,
            action_type: formData.get(`steps[${index}][action_type]`),
            delay_days: parseInt(formData.get(`steps[${index}][delay_days]`) || 0),
            delay_hours: parseInt(formData.get(`steps[${index}][delay_hours]`) || 0),
            template_subject: formData.get(`steps[${index}][template_subject]`) || '',
            template_body: formData.get(`steps[${index}][template_body]`) || ''
        };
        sequenceData.steps.push(stepData);
    });

    // Validação básica
    if (!sequenceData.name || !sequenceData.trigger_condition) {
        showFollowupError('Nome e condição de disparo são obrigatórios');
        return;
    }

    if (sequenceData.steps.length === 0) {
        showFollowupError('Adicione pelo menos um passo à sequência');
        return;
    }

    try {
        const sequenceId = e.target.dataset.sequenceId;
        const method = sequenceId ? 'PUT' : 'POST';
        const url = '/api/v1/leads/followup/sequences.php';
        const body = method === 'PUT'
            ? JSON.stringify({ id: sequenceId, ...sequenceData })
            : JSON.stringify(sequenceData);

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: body
        });

        const data = await response.json();

        if (data.success) {
            showFollowupSuccess('Sequência salva com sucesso!');
            closeSequenceModal();
            loadFollowupSequences(); // Recarregar lista
        } else {
            throw new Error(data.error || 'Erro ao salvar sequência');
        }
    } catch (error) {
        console.error('Erro ao salvar sequência:', error);
        showFollowupError('Erro ao salvar sequência: ' + error.message);
    }
}

// Executar follow-ups pendentes
async function executePendingFollowups() {
    const stats = await fetch('/api/v1/leads/followup/stats.php').then(r => r.json());

    let message = 'Deseja executar todos os follow-ups pendentes agora?';
    if (stats.data && stats.data.pending_followups) {
        message = `Existem ${stats.data.pending_followups} follow-ups pendentes. Deseja executá-los agora?`;
    }

    if (!confirm(message)) {
        return;
    }

    const button = document.getElementById('execute-followups-btn');
    const originalText = button.innerHTML;

    try {
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Executando...';

        const formData = new FormData();
        formData.append('force', 'true'); // Força a execução de todos os pendentes

        const response = await fetch('/api/v1/leads/followup/execute.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();

        if (data.success) {
            const message = `Executados: ${data.data.executed}, Falharam: ${data.data.failed}`;
            showFollowupSuccess(message);

            // Mostrar mais informações se houver
            if (data.data.pending_found > data.data.executed + data.data.failed) {
                showFollowupInfo(`${data.data.pending_found - data.data.executed - data.data.failed} follow-ups não foram processados`);
            }

            loadFollowupStats(); // Recarregar estatísticas
        } else {
            throw new Error(data.error || 'Erro ao executar follow-ups');
        }
    } catch (error) {
        console.error('Erro ao executar follow-ups:', error);
        showFollowupError('Erro ao executar follow-ups: ' + error.message);
    } finally {
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

// Funções auxiliares
function getTriggerLabel(condition, value) {
    const labels = {
        'lead_created': 'Lead Criado',
        'status_changed': `Status: ${value}`,
        'score_changed': `Score ≥ ${value}`,
        'inactivity_days': `Inativo há ${value} dias`
    };
    return labels[condition] || condition;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function toggleSequenceMenu(button, event) {
    const evt = event || window.event;
    if (evt && typeof evt.stopPropagation === 'function') {
        evt.stopPropagation();
    }

    const dropdown = button.closest('.dropdown');
    if (!dropdown) {
        return;
    }

    const menu = dropdown.querySelector('.sequence-dropdown-menu');
    if (!menu) {
        return;
    }

    menu.classList.toggle('hidden');
}

function editSequence(sequenceId) {
    openSequenceModal(sequenceId);
}

async function toggleSequenceStatus(sequenceId, currentStatus) {
    const newStatus = currentStatus ? 0 : 1;
    const action = newStatus ? 'ativar' : 'pausar';

    if (!confirm(`Deseja ${action} esta sequência?`)) {
        return;
    }

    // Adiciona feedback visual de loading no botão
    const btn = document.querySelector(`[data-sequence-id="${sequenceId}"][data-action="toggle-status"]`);
    let originalHtml = null;
    if (btn) {
        originalHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Aguarde...';
    }

    // Executa a requisição de forma assíncrona, sem travar o handler
    setTimeout(async () => {
        try {
            const response = await fetch('/api/v1/leads/followup/sequences.php', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: sequenceId,
                    is_active: newStatus
                })
            });

            const data = await response.json();

            if (data.success) {
                showFollowupSuccess(`Sequência ${newStatus ? 'ativada' : 'pausada'} com sucesso!`);
                loadFollowupSequences(); // Recarregar lista
            } else {
                throw new Error(data.error || 'Erro ao alterar status');
            }
        } catch (error) {
            console.error('Erro ao alterar status:', error);
            showFollowupError('Erro ao alterar status: ' + error.message);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    }, 0);
}

async function deleteSequence(sequenceId) {
    if (!confirm('Tem certeza que deseja excluir esta sequência? Esta ação não pode ser desfeita.')) {
        return;
    }

    try {
        const response = await fetch('/api/v1/leads/followup/sequences.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: sequenceId })
        });

        const data = await response.json();

        if (data.success) {
            showFollowupSuccess('Sequência excluída com sucesso!');
            loadFollowupSequences(); // Recarregar lista
        } else {
            throw new Error(data.error || 'Erro ao excluir sequência');
        }
    } catch (error) {
        console.error('Erro ao excluir sequência:', error);
        showFollowupError('Erro ao excluir sequência: ' + error.message);
    }
}

// Funções de notificação
function showFollowupSuccess(message) {
    // Implementar toast de sucesso
    alert('✅ ' + message);
}

function showFollowupError(message) {
    // Implementar toast de erro
    alert('❌ ' + message);
}

function showFollowupInfo(message) {
    // Implementar toast de informação
    alert('ℹ️ ' + message);
}

// Fechar menus dropdown ao clicar fora
document.addEventListener('click', function (event) {
    const target = event.target;
    const isInsideDropdown = target.closest('.dropdown');
    const isSequenceDropdownToggle = target.classList.contains('sequence-dropdown-toggle') || target.closest('.sequence-dropdown-toggle');
    const isSequenceAction = target.classList.contains('sequence-action') || target.closest('.sequence-action');


    if (!isInsideDropdown) {
        document.querySelectorAll('.sequence-dropdown-menu').forEach(menu => {
            menu.classList.add('hidden');
        });
    } else {
    }
});


// Função de teste para debug
window.testFollowupModal = function () {
    openSequenceModal(1);
};

// Função de teste para verificar elementos
window.checkFollowupElements = function () {
};