/**
 * Lead Interactions Timeline
 * Gerencia a exibição do histórico de interações em timeline
 */

let timelineData = [];
let currentFilter = 'all';

async function loadLeadInteractionsTimeline(leadId) {
  try {
    const response = await fetch(`/api/v1/leads/getInteractions.php?lead_id=${leadId}&limit=100`);
    const json = await response.json();

    if (!json.success || !json.data || json.data.length === 0) {
      return `
        <div class="timeline-empty">
          <div class="timeline-empty-icon">
            <i class="fas fa-history"></i>
          </div>
          <div class="timeline-empty-text">Nenhuma interação registrada ainda</div>
        </div>
      `;
    }

    timelineData = json.data;
    currentFilter = 'all';
    return renderTimelineWithFilters(json.data);
  } catch (e) {
    console.error('Erro ao carregar interações:', e);
    return `<div class="text-red-500 text-center py-4"><i class="fas fa-exclamation-circle"></i> Erro ao carregar histórico</div>`;
  }
}

function renderTimelineWithFilters(interactions) {
  if (!interactions || interactions.length === 0) {
    return `
      <div class="timeline-empty">
        <div class="timeline-empty-icon">
          <i class="fas fa-history"></i>
        </div>
        <div class="timeline-empty-text">Nenhuma interação registrada</div>
      </div>
    `;
  }

  const stats = calculateStats(interactions);
  const types = getUniqueInteractionTypes(interactions);

  let html = '<div class="timeline-container">';

  // Header com estatísticas
  html += `
      <div class="timeline-header">
        <h3><i class="fas fa-history"></i> Histórico de Interações</h3>
        <div class="timeline-stats">
          <div class="timeline-stat">
            <span class="timeline-stat-icon"><i class="fas fa-list"></i></span>
            <span class="timeline-stat-label">Total:</span>
            <span class="timeline-stat-value">${interactions.length}</span>
          </div>
          <div class="timeline-stat">
            <span class="timeline-stat-icon"><i class="fas fa-calendar"></i></span>
            <span class="timeline-stat-label">Última ação:</span>
            <span class="timeline-stat-value">${formatDateRelative(new Date(interactions[0].created_at))}</span>
          </div>
        </div>
      </div>
    `;

  // Filtros
  html += '<div class="timeline-filters">';
  html += `<button class="filter-btn active" onclick="filterTimeline('all')">
      <i class="fas fa-layer-group"></i> Todas (${interactions.length})
    </button>`;

  types.forEach(type => {
    const count = interactions.filter(i => i.interaction_type === type).length;
    const icon = getInteractionIcon(type);
    html += `<button class="filter-btn" onclick="filterTimeline('${type}')">
        <i class="${icon}"></i> ${getInteractionLabel(type)} (${count})
      </button>`;
  });

  html += '</div>';

  // Timeline items
  html += '<div class="timeline-items" id="timeline-items">';
  html += renderTimeline(interactions);
  html += '</div>';

  html += '</div>';
  return html;
}


function renderTimeline(interactions) {
  if (!interactions || interactions.length === 0) {
    return `
      <div class="timeline-empty">
        <div class="timeline-empty-icon">
          <i class="fas fa-history"></i>
        </div>
        <div class="timeline-empty-text">Nenhuma interação registrada</div>
      </div>
    `;
  }

  // Ordenar por data descendente (mais recentes primeiro)
  const sorted = [...interactions].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

  let html = '';

  sorted.forEach((interaction) => {
    const icon = getInteractionIcon(interaction.interaction_type);
    const color = getInteractionColor(interaction.interaction_type);
    const label = getInteractionLabel(interaction.interaction_type);
    const date = new Date(interaction.created_at);
    const timeStr = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    const dateStr = date.toLocaleDateString('pt-BR');

    const colorClass = color.replace('bg-', '').split('-')[0]; // Extrai a cor base

    html += `
      <div class="timeline-item" data-type="${interaction.interaction_type}">
        <div class="timeline-dot ${getColorClass(color)}">
          <i class="${icon}"></i>
        </div>
        <div class="timeline-content">
          <div class="timeline-card">
            <div class="timeline-card-header">
              <h4 class="timeline-card-title">${label}</h4>
              <div class="timeline-card-meta">
                <div class="timeline-card-time">${dateStr}</div>
                <div class="timeline-card-time">${timeStr}</div>
                <div class="timeline-card-relative">${formatDateRelative(date)}</div>
              </div>
            </div>
            
            ${interaction.title ? `<p class="timeline-card-description"><strong>${escapeHtml(interaction.title)}</strong></p>` : ''}
            
            ${interaction.description ? `<p class="timeline-card-description">${escapeHtml(interaction.description)}</p>` : ''}

            ${interaction.old_value || interaction.new_value
        ? `
              <div class="timeline-card-changes">
                ${interaction.old_value ? `<span class="timeline-card-old-value">${escapeHtml(interaction.old_value)}</span>` : ''}
                ${interaction.old_value && interaction.new_value ? '<span class="timeline-card-arrow">→</span>' : ''}
                ${interaction.new_value ? `<span class="timeline-card-new-value">${escapeHtml(interaction.new_value)}</span>` : ''}
              </div>
            `
        : ''
      }
            
            <div class="timeline-card-user">
              <i class="fas fa-user-circle"></i>
              <span>${interaction.user_name || 'Sistema'}</span>
            </div>
          </div>
        </div>
      </div>
    `;
  });

  return html;
}

function filterTimeline(type) {
  currentFilter = type;

  // Atualizar botões de filtro
  document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.classList.remove('active');
  });
  event.target.closest('.filter-btn').classList.add('active');

  // Filtrar items
  const items = document.querySelectorAll('.timeline-item');
  items.forEach(item => {
    if (type === 'all' || item.getAttribute('data-type') === type) {
      item.style.display = 'flex';
    } else {
      item.style.display = 'none';
    }
  });
}

function calculateStats(interactions) {
  const stats = {
    total: interactions.length,
    types: {},
    lastInteraction: interactions.length > 0 ? new Date(interactions[0].created_at) : null,
  };

  interactions.forEach(i => {
    if (!stats.types[i.interaction_type]) {
      stats.types[i.interaction_type] = 0;
    }
    stats.types[i.interaction_type]++;
  });

  return stats;
}

function getUniqueInteractionTypes(interactions) {
  const types = new Set();
  interactions.forEach(i => types.add(i.interaction_type));
  return Array.from(types);
}

function getInteractionIcon(type) {
  const icons = {
    lead_created: 'fas fa-plus',
    viewed: 'fas fa-eye',
    called: 'fas fa-phone',
    email: 'fas fa-envelope',
    email_sent: 'fas fa-envelope',
    sms: 'fas fa-comment-dots',
    whatsapp: 'fab fa-whatsapp',
    status_change: 'fas fa-exchange-alt',
    notes_added: 'fas fa-sticky-note',
    proposal_sent: 'fas fa-file-pdf',
    assigned: 'fas fa-user-check',
    follow_up_scheduled: 'fas fa-calendar-check',
    meeting_scheduled: 'fas fa-calendar',
    comment_added: 'fas fa-comment',
    updated: 'fas fa-edit',
    deleted: 'fas fa-trash',
  };
  return icons[type] || 'fas fa-circle';
}

function getInteractionColor(type) {
  const colors = {
    lead_created: 'bg-blue-500',
    viewed: 'bg-cyan-500',
    called: 'bg-green-500',
    email: 'bg-purple-500',
    email_sent: 'bg-purple-500',
    sms: 'bg-indigo-500',
    whatsapp: 'bg-green-600',
    status_change: 'bg-orange-500',
    notes_added: 'bg-yellow-500',
    proposal_sent: 'bg-red-500',
    assigned: 'bg-indigo-500',
    follow_up_scheduled: 'bg-pink-500',
    meeting_scheduled: 'bg-pink-500',
    comment_added: 'bg-blue-400',
    updated: 'bg-gray-500',
    deleted: 'bg-red-600',
  };
  return colors[type] || 'bg-gray-400';
}

function getColorClass(bgColor) {
  const mapping = {
    'bg-blue-500': 'blue',
    'bg-cyan-500': 'cyan',
    'bg-green-500': 'green',
    'bg-green-600': 'green',
    'bg-purple-500': 'purple',
    'bg-orange-500': 'orange',
    'bg-yellow-500': 'yellow',
    'bg-red-500': 'red',
    'bg-red-600': 'red',
    'bg-indigo-500': 'indigo',
    'bg-pink-500': 'pink',
    'bg-blue-400': 'blue',
    'bg-gray-500': 'gray',
    'bg-gray-400': 'gray',
  };
  return mapping[bgColor] || 'gray';
}

function getInteractionLabel(type) {
  const labels = {
    lead_created: 'Lead criado',
    viewed: 'Lead visualizado',
    called: 'Chamada telefônica',
    email: 'Email enviado',
    email_sent: 'Email enviado',
    sms: 'SMS enviado',
    whatsapp: 'WhatsApp enviado',
    status_change: 'Status alterado',
    notes_added: 'Anotação adicionada',
    proposal_sent: 'Proposta enviada',
    assigned: 'Lead atribuído',
    follow_up_scheduled: 'Follow-up agendado',
    meeting_scheduled: 'Reunião agendada',
    comment_added: 'Comentário adicionado',
    updated: 'Lead atualizado',
    deleted: 'Lead deletado',
  };
  return labels[type] || 'Interação registrada';
}

function formatDateRelative(date) {
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) return 'Agora';
  if (diffMins < 60) return `${diffMins}m atrás`;
  if (diffHours < 24) return `${diffHours}h atrás`;
  if (diffDays < 7) return `${diffDays}d atrás`;

  return date.toLocaleDateString('pt-BR');
}

function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
