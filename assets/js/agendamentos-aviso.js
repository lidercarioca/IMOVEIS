/**
 * Sistema de Aviso de Agendamentos Próximos
 * Exibe agendamentos do dia e do dia seguinte ao usuário fazer login
 */

async function carregarAgendamentosProximos() {
    try {
        const response = await fetch('api/getAgendamentosProximos.php');
        if (!response.ok) throw new Error('Erro ao carregar agendamentos');
        
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Erro ao carregar agendamentos');
        
        // Exibir avisos se houver agendamentos
        if (data.total > 0) {
            exibirAvisosAgendamentos(data.hoje, data.amanha);
        }
        
    } catch (error) {
        console.error('Erro ao carregar agendamentos próximos:', error);
    }
}

function exibirAvisosAgendamentos(hoje, amanha) {
    const container = document.getElementById('agendamentos-aviso-container');
    
    if (!container) {
        console.warn('Container de avisos não encontrado');
        return;
    }
    
    // Limpar avisos anteriores
    container.innerHTML = '';
    
    // Exibir agendamentos de hoje
    if (hoje && hoje.length > 0) {
        const avisoHoje = criarAvisoAgendamento('Agendamentos de Hoje', hoje, 'danger');
        container.appendChild(avisoHoje);
    }
    
    // Exibir agendamentos de amanhã
    if (amanha && amanha.length > 0) {
        const avisoAmanha = criarAvisoAgendamento('Agendamentos de Amanhã', amanha, 'warning');
        container.appendChild(avisoAmanha);
    }
}

function criarAvisoAgendamento(titulo, agendamentos, tipo) {
    const aviso = document.createElement('div');
    aviso.className = `alert alert-${tipo} alert-dismissible fade show`;
    aviso.setAttribute('role', 'alert');
    
    let html = `
        <div class="d-flex align-items-start">
            <div>
                <h5 class="alert-heading">
                    <i class="fas fa-calendar-check me-2"></i>${titulo}
                </h5>
                <hr>
    `;
    
    agendamentos.forEach((agendamento, index) => {
        const data = new Date(agendamento.data_agendamento);
        const hora = data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        const statusClass = {
            'confirmado': 'success',
            'realizado': 'info',
            'cancelado': 'danger'
        }[agendamento.status] || 'secondary';
        
        const statusTexto = {
            'confirmado': 'Confirmado',
            'realizado': 'Realizado',
            'cancelado': 'Cancelado'
        }[agendamento.status] || agendamento.status;
        
        html += `
            <div class="mb-3 p-2 bg-white rounded">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <p class="mb-1">
                            <strong><i class="fas fa-building me-1"></i>${agendamento.property_title || 'Propriedade'}</strong>
                        </p>
                        <p class="mb-1 small text-muted">
                            <i class="fas fa-clock me-1"></i>${hora}
                            <span class="badge bg-${statusClass} ms-2">${statusTexto}</span>
                        </p>
                        ${agendamento.descricao ? `<p class="mb-0 small"><em>"${agendamento.descricao}"</em></p>` : ''}
                    </div>
                    <button onclick="editarAgendamento(${agendamento.id})" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    html += `
                <small class="text-muted">Clique em "Editar" para gerenciar o agendamento</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    `;
    
    aviso.innerHTML = html;
    return aviso;
}

// Carregar agendamentos quando o painel carregar
document.addEventListener('DOMContentLoaded', function() {
    // Aguardar um pouco para garantir que o DOM está pronto
    setTimeout(() => {
        carregarAgendamentosProximos();
    }, 500);
});

// Exibe um pequeno destaque visual e um toast quando um novo agendamento é criado/alterado
// Parâmetros opcionais: agendamentoId (int|null), title (string), time (string)
function showNewAgendamentoCue(agendamentoId, title, time) {
    const badge = document.getElementById('notifications-badge');
    const tabLink = document.querySelector('a[href="#agendamentos"]');

    // Cria estilos de animação e toast se não existirem
    if (!document.getElementById('agendamento-cue-styles')) {
        const style = document.createElement('style');
        style.id = 'agendamento-cue-styles';
        style.innerHTML = `
            @keyframes pulse-ring { 0% { transform: scale(1); opacity:1 } 50% { transform: scale(1.45); opacity:0.6 } 100% { transform: scale(1); opacity:0 } }
            .pulse-ring { box-shadow: 0 0 0 0 rgba(59,130,246,0.6); animation: pulse-ring 900ms ease-out; border-radius: 9999px; }
            .pulse-highlight { animation: pulse-ring 900ms ease-out; }
            .agendamento-toast { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; border-radius:8px; }
            .agendamento-toast .btn { padding:4px 8px; font-size:13px; }
        `;
        document.head.appendChild(style);
    }

    // Atualiza badge numérico
    if (badge) {
        badge.classList.remove('hidden');
        const cur = parseInt(badge.dataset.count || '0', 10) || 0;
        const next = cur + 1;
        badge.dataset.count = next;
        badge.textContent = next > 99 ? '99+' : String(next);
        badge.classList.add('pulse-ring');
        setTimeout(() => badge.classList.remove('pulse-ring'), 1200);
    }

    if (tabLink) {
        tabLink.classList.add('pulse-highlight');
        setTimeout(() => tabLink.classList.remove('pulse-highlight'), 1200);
    }

    // Criar toast informativo
    const toastId = 'agendamento-toast-' + Date.now();
    const toast = document.createElement('div');
    toast.id = toastId;
    toast.className = 'agendamento-toast shadow-lg p-3 bg-white';
    toast.style = 'position:fixed;right:20px;bottom:20px;z-index:1060;max-width:360px;border:1px solid rgba(0,0,0,0.06);';

    const displayTitle = title || 'Novo agendamento';
    const displayTime = time || '';

    toast.innerHTML = `
        <div class="d-flex align-items-start">
            <div class="flex-grow-1 me-2">
                <strong style="display:block">${displayTitle}</strong>
                <div style="font-size:12px;color:#6b7280">${displayTime}</div>
            </div>
            <div>
                ${agendamentoId ? '<button class="btn btn-sm btn-primary open-ag-btn">Abrir</button>' : '<button class="btn btn-sm btn-secondary close-ag-btn">Fechar</button>'}
            </div>
        </div>
    `;

    document.body.appendChild(toast);

    if (agendamentoId) {
        toast.querySelector('.open-ag-btn').addEventListener('click', () => {
            if (typeof editarAgendamento === 'function') editarAgendamento(agendamentoId);
            toast.remove();
        });
    } else {
        toast.querySelector('.close-ag-btn').addEventListener('click', () => toast.remove());
    }

    // auto-remove
    setTimeout(() => { try { toast.remove(); } catch (e) {} }, 7000);
}
