/**
 * Sistema de Paginação para Leads
 * Gerencia carregamento e exibição de leads com paginação
 */

window.leadsPaginationState = {
  currentPage: 1,
  itemsPerPage: 10,
  totalPages: 1,
  totalRecords: 0,
  isLoading: false
};

/**
 * Carrega leads de uma página específica
 * @param {number} page - Número da página
 */
async function loadLeadsPage(page = 1) {
  const state = window.leadsPaginationState;
  
  // Evita múltiplos carregamentos simultâneos
  if (state.isLoading) return;
  
  state.isLoading = true;
  const container = document.getElementById('leads-container');
  
  try {
    // Mostra loading
    container.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center"><i class="fas fa-spinner fa-spin"></i> Carregando...</td></tr>';
    
    // Busca os filtros atuais do DOM (se existirem)
    const searchElem = document.getElementById('leads-search');
    const statusElem = document.getElementById('leads-status-filter');
    const periodElem = document.getElementById('leads-period-filter');

    const params = new URLSearchParams();
    params.set('page', page);
    params.set('limit', state.itemsPerPage);
    if (searchElem && searchElem.value.trim() !== '') params.set('q', searchElem.value.trim());
    if (statusElem && statusElem.value && statusElem.value !== 'all') params.set('status', statusElem.value);
    if (periodElem && periodElem.value && periodElem.value !== 'all') params.set('period', periodElem.value);

    // Busca os leads
    const url = `api/getLeads.php?${params.toString()}`;
    const response = await fetch(url);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    const json = await response.json();
    
    if (!json.success) {
      throw new Error(json.message || 'Erro desconhecido');
    }
    
    // Atualiza estado
    state.currentPage = json.pagination.current_page;
    state.totalPages = json.pagination.total_pages;
    state.totalRecords = json.pagination.total_records;
    
    // Armazena dados globalmente
    window.leadsData = json.data;
    
    // Renderiza leads
    renderLeadsTable(json.data);
    
    // Carrega usuários nos dropdowns (apenas para admin)
    if (window.isAdmin) {
      populateUserSelects();
    }
    
    // Atualiza contador
    updateLeadsCounter(json.pagination);
    
    // Atualiza paginação
    updatePaginationButtons();
    
  } catch (error) {
    console.error('Erro ao carregar leads:', error);
    container.innerHTML = `<tr><td colspan="6" class="px-6 py-4 text-center text-red-500">Erro ao carregar leads</td></tr>`;
  } finally {
    state.isLoading = false;
  }
}

/**
 * Renderiza a tabela de leads
 * @param {Array} leads - Array de leads
 */
function renderLeadsTable(leads) {
  const container = document.getElementById('leads-container');
  
  if (!leads || leads.length === 0) {
    container.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">Nenhum lead encontrado</td></tr>';
    return;
  }
  
  container.innerHTML = '';
  
  leads.forEach(lead => {
    const row = document.createElement('tr');
    row.dataset.leadId = lead.id;
    
    // Preview da mensagem
    let msgPreview = '';
    if (lead.message) {
      const cleanMsg = lead.message.replace(/\n/g, ' ');
      msgPreview = cleanMsg.length > 50 ? cleanMsg.substring(0, 50) + '…' : cleanMsg;
    }
    
    // Debug: verificar dados do lead
    console.log(`Renderizando lead ${lead.id}:`, { assigned_user_id: lead.assigned_user_id, assigned_user_name: lead.assigned_user_name });
    
    row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="flex items-center">
          <input type="checkbox" class="mr-3 lead-checkbox" data-lead-id="${lead.id}">
          <div><div class="text-sm font-medium text-gray-900">${lead.name || ''}</div></div>
        </div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="text-sm text-gray-900">${lead.email || ''}</div>
        <div class="text-sm text-gray-500">${lead.phone || ''}</div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="text-sm text-gray-900">${msgPreview}</div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="text-sm text-gray-900">${lead.created_at ? new Date(lead.created_at).toLocaleString('pt-BR') : ''}</div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="relative inline-block">
          <div class="lead-status ${getLeadStatusClass(lead.status)}" data-id="${lead.id}" data-value="${lead.status}" onclick="toggleLeadStatus(this)" style="min-width: 120px;">
            <i class="${getLeadStatusIcon(lead.status)}"></i>
            <span>${getLeadStatusText(lead.status)}</span>
          </div>
          <div class="lead-status-menu hidden absolute left-0 bg-white shadow-lg rounded-lg mt-1 z-50" style="min-width: 160px;">
            <div class="py-1">
              <button class="w-full text-left px-4 py-2 hover:bg-gray-100 lead-status-option novo" data-value="new">
                <i class="fas fa-star me-2"></i>Novo
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-gray-100 lead-status-option em-contato" data-value="contacted">
                <i class="fas fa-phone me-2"></i>Em contato
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-gray-100 lead-status-option convertido" data-value="closed">
                <i class="fas fa-check-circle me-2"></i>Convertido
              </button>
              <button class="w-full text-left px-4 py-2 hover:bg-gray-100 lead-status-option perdido" data-value="cancelled">
                <i class="fas fa-times-circle me-2"></i>Perdido
              </button>
            </div>
          </div>
        </div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
        <div class="flex items-center gap-2">
          <span class="lead-assignment-display" data-lead-id="${lead.id}" style="min-width: 120px; color: ${lead.assigned_user_name ? '#000' : '#999'};">
            ${lead.assigned_user_name || 'Nenhum'}
          </span>
          <select class="lead-user-assignment border border-gray-300 rounded px-2 py-1 text-sm" data-lead-id="${lead.id}" style="width: 120px; display: none;" ${!window.isAdmin ? 'disabled' : ''}>
            <option value="">Nenhum</option>
          </select>
        </div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
        <div class="flex space-x-2">
          <button class="text-blue-600 hover:text-blue-900 btn-ver-lead" data-id="${lead.id}" title="Ver detalhes"><i class="fas fa-eye"></i></button>
          <a class="btn-ver-lead" style="color: var(--cor-secundaria);" title="Enviar e-mail" href="mailto:${lead.email}?subject=Contato&body=Olá ${lead.name}"><i class="fas fa-envelope"></i></a>
          <button class="btn-excluir-lead" style="color: #e53e3e;" data-id="${lead.id}" title="Excluir"><i class="fas fa-trash"></i></button>
        </div>
      </td>
    `;
    
    container.appendChild(row);
  });
  
  // Registra eventos dos botões
  attachLeadButtonEvents();
}

/**
 * Atualiza o contador de leads
 * @param {Object} pagination - Dados de paginação
 */
function updateLeadsCounter(pagination) {
  const countElem = document.querySelector('#leads .text-sm.text-gray-600');
  if (!countElem) return;
  
  const { current_page, per_page, total_records } = pagination;
  const start = (current_page - 1) * per_page + 1;
  const end = Math.min(current_page * per_page, total_records);
  
  countElem.textContent = `Mostrando ${start}-${end} de ${total_records} leads`;
}

/**
 * Atualiza os botões de paginação
 */
function updatePaginationButtons() {
  const state = window.leadsPaginationState;
  const container = document.querySelector('#leads .flex.space-x-1');
  
  if (!container) return;
  
  container.innerHTML = '';
  
  // Botão anterior
  const btnPrev = document.createElement('button');
  btnPrev.className = 'px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition';
  btnPrev.innerHTML = '<i class="fas fa-chevron-left text-gray-600"></i>';
  btnPrev.disabled = state.currentPage === 1;
  btnPrev.onclick = () => {
    if (state.currentPage > 1) {
      loadLeadsPage(state.currentPage - 1);
    }
  };
  container.appendChild(btnPrev);
  
  // Botões de página (mostra max 5)
  const startPage = Math.max(1, state.currentPage - 2);
  const endPage = Math.min(state.totalPages, state.currentPage + 2);
  
  for (let i = startPage; i <= endPage; i++) {
    const btn = document.createElement('button');
    btn.className = 'px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition';
    if (i === state.currentPage) {
      btn.className += ' bg-blue-600 text-white';
    }
    btn.textContent = i;
    btn.disabled = i === state.currentPage;
    btn.onclick = () => {
      if (i !== state.currentPage) {
        loadLeadsPage(i);
      }
    };
    container.appendChild(btn);
  }
  
  // Botão próximo
  const btnNext = document.createElement('button');
  btnNext.className = 'px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition';
  btnNext.innerHTML = '<i class="fas fa-chevron-right text-gray-600"></i>';
  btnNext.disabled = state.currentPage === state.totalPages;
  btnNext.onclick = () => {
    if (state.currentPage < state.totalPages) {
      loadLeadsPage(state.currentPage + 1);
    }
  };
  container.appendChild(btnNext);
}

/**
 * Registra eventos dos botões de lead
 */
function attachLeadButtonEvents() {
  const container = document.getElementById('leads-container');
  if (!container) return;
  
  // Botão ver detalhes
  container.querySelectorAll('.btn-ver-lead').forEach(btn => {
    btn.addEventListener('click', function(e) {
      // Se for um link (mailto), deixa o comportamento padrão
      if (this.tagName === 'A') return;
      
      e.preventDefault();
      const id = this.getAttribute('data-id');
      const lead = window.leadsData.find(l => String(l.id) === String(id));
      if (lead && typeof mostrarModalLead === 'function') {
        mostrarModalLead(lead);
      }
    });
  });
  
  // Botão excluir
  container.querySelectorAll('.btn-excluir-lead').forEach(btn => {
    btn.addEventListener('click', async function() {
      if (!confirm('Tem certeza que deseja excluir este lead?')) return;
      
      const id = this.getAttribute('data-id');
      try {
        const res = await fetch(`api/deleteLead.php?id=${id}`);
        const json = await res.json();
        
        if (json.success) {
          // Recarrega a página ou volta para anterior
          const state = window.leadsPaginationState;
          const rowsLeft = document.querySelectorAll('#leads-container tr').length;
          
          if (rowsLeft <= 1 && state.currentPage > 1) {
            loadLeadsPage(state.currentPage - 1);
          } else {
            loadLeadsPage(state.currentPage);
          }
          
          // Atualiza dashboard se existir
          if (typeof atualizarContadorLeadsDashboard === 'function') {
            atualizarContadorLeadsDashboard();
          }
        } else {
          alert('Erro ao excluir lead');
        }
      } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao excluir lead');
      }
    });
  });
  
  // Atribuição de usuários (apenas para admin)
  if (window.isAdmin) {
    // Adicionar evento de clique para editar atribuição
    container.querySelectorAll('.lead-assignment-display').forEach(display => {
      display.addEventListener('click', function() {
        const leadId = this.getAttribute('data-lead-id');
        const select = container.querySelector(`.lead-user-assignment[data-lead-id="${leadId}"]`);
        if (select) {
          this.style.display = 'none';
          select.style.display = 'block';
          select.focus();
        }
      });
    });
    
    // Adicionar evento de change do select
    container.querySelectorAll('.lead-user-assignment').forEach(select => {
      select.addEventListener('change', async function() {
        const leadId = this.getAttribute('data-lead-id');
        const userId = this.value || null;
        
        try {
          const res = await fetch('api/assignLeadToUser.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              lead_id: leadId,
              assigned_user_id: userId
            })
          });
          
          const json = await res.json();
          
          if (json.success) {
            // Mostra mensagem de sucesso
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg';
            toast.textContent = 'Lead atribuído com sucesso';
            document.body.appendChild(toast);
            
            setTimeout(() => toast.remove(), 3000);
            
            // Recarrega os leads
            loadLeadsPage(window.leadsPaginationState.currentPage);
          } else {
            alert('Erro ao atribuir lead: ' + (json.message || 'Desconhecido'));
            // Restaura o valor anterior
            this.value = window.leadsData.find(l => String(l.id) === leadId)?.assigned_user_id || '';
          }
        } catch (error) {
          console.error('Erro:', error);
          alert('Erro ao atribuir lead');
          // Restaura o valor anterior
          const lead = window.leadsData.find(l => String(l.id) === leadId);
          this.value = lead?.assigned_user_id || '';
        }
      });
      
      // Adicionar evento de blur para voltar ao display
      select.addEventListener('blur', function() {
        const leadId = this.getAttribute('data-lead-id');
        const display = container.querySelector(`.lead-assignment-display[data-lead-id="${leadId}"]`);
        if (display) {
          this.style.display = 'none';
          display.style.display = 'inline';
        }
      });
    });
  }
}

/**
 * Popula os selects de usuários com a lista de usuários
 */
async function populateUserSelects() {
  try {
    const res = await fetch('api/users.php');
    const json = await res.json();
    
    if (!json.success || !json.data) {
      console.warn('Não foi possível carregar lista de usuários');
      return;
    }
    
    // Armazena usuários globalmente para referência
    window.availableUsers = json.data;
    
    // Preenche cada select
    const selects = document.querySelectorAll('.lead-user-assignment');
    selects.forEach(select => {
      const leadId = select.getAttribute('data-lead-id');
      const lead = window.leadsData.find(l => String(l.id) === String(leadId));
      const currentUserId = lead?.assigned_user_id;
      const assignedUserName = lead?.assigned_user_name;
      
      console.log(`Lead ${leadId}:`, { lead, currentUserId, assignedUserName });
      
      // Atualiza o display com o nome do usuário
      const display = document.querySelector(`.lead-assignment-display[data-lead-id="${leadId}"]`);
      if (display && assignedUserName) {
        display.textContent = assignedUserName;
        display.style.color = '#000';
      } else if (display) {
        display.textContent = 'Nenhum';
        display.style.color = '#999';
      }
      
      // Limpa opções existentes (mantém a primeira "Nenhum")
      while (select.options.length > 1) {
        select.remove(1);
      }
      
      // Adiciona os usuários
      json.data.forEach(user => {
        const option = document.createElement('option');
        option.value = user.id;
        option.textContent = user.name;
        if (currentUserId && String(user.id) === String(currentUserId)) {
          option.selected = true;
        }
        select.appendChild(option);
      });
      
      // Define o select para mostrar o usuário atual se houver
      if (currentUserId) {
        select.value = currentUserId;
      }
    });
  } catch (error) {
    console.error('Erro ao carregar usuários:', error);
  }
}

/**
 * Inicializa a paginação (chamado quando aba é aberta)
 */
window.initLeadsPagination = function() {
  // Inicializa filtros e eventos
  const searchElem = document.getElementById('leads-search');
  const statusElem = document.getElementById('leads-status-filter');
  const periodElem = document.getElementById('leads-period-filter');

  // Debounce helper
  const debounce = (fn, wait) => {
    let t;
    return function(...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait);
    };
  };

  if (searchElem) {
    searchElem.addEventListener('input', debounce(() => loadLeadsPage(1), 350));
  }
  if (statusElem) {
    statusElem.addEventListener('change', () => loadLeadsPage(1));
  }
  if (periodElem) {
    periodElem.addEventListener('change', () => loadLeadsPage(1));
  }

  // Carrega primeira página
  loadLeadsPage(1);
};
