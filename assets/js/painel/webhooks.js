/**
 * webhooks.js
 * Gerenciador de webhooks no painel administrativo
 * Data: 30/01/2026
 */

let webhookData = {
  subscriptions: [],
  filter: 'all', // all, active, inactive
  initialized: false,
  updateInterval: null,
  carregando: false
};

/**
 * Inicializa o módulo de webhooks
 */
async function initWebhooks() {
  // Evitar inicializar múltiplas vezes
  if (webhookData.initialized) {
    await carregarWebhooks();
    return;
  }
  
  webhookData.initialized = true;
  
  // Carregar webhooks ao abrir a aba
  await carregarWebhooks();
  
  // Configurar listeners
  const form = document.getElementById('webhook-form');
  if (form) {
    form.removeEventListener('submit', criarWebhook);
    form.addEventListener('submit', criarWebhook);
  }
  
  // Parar o intervalo anterior (se existir)
  if (webhookData.updateInterval) {
    clearInterval(webhookData.updateInterval);
  }
  
  // Atualizar a cada 30 segundos apenas quando a aba estiver ativa
  webhookData.updateInterval = setInterval(() => {
    // Verificar se a aba de webhooks está visível
    const webhooksTab = document.getElementById('webhooks');
    if (webhooksTab && !webhooksTab.classList.contains('hidden')) {
      carregarWebhooks();
    }
  }, 30000);
}

/**
 * Carregar lista de webhooks do servidor
 */
async function carregarWebhooks() {
  // Evitar múltiplas requisições simultâneas
  if (webhookData.carregando) {
    return;
  }
  
  webhookData.carregando = true;
  
  try {
    const response = await fetch('/api/v1/webhooks/getSubscriptions.php', {
      headers: {
        'Authorization': `Bearer ${window.sessionStorage.getItem('token') || ''}`
      }
    });
    
    if (!response.ok) {
      throw new Error(`HTTP Error ${response.status}`);
    }
    
    const result = await response.json();
    
    if (result.success && Array.isArray(result.data)) {
      webhookData.subscriptions = result.data;
      renderizarWebhooks();
      atualizarEstatisticas();
    } else {
      console.warn('[Webhooks] Resposta inválida:', result);
      mostrarMensagemErro('Erro ao carregar webhooks');
    }
  } catch (error) {
    console.error('[Webhooks] Erro ao carregar:', error);
    mostrarMensagemErro('Erro ao conectar com o servidor');
  } finally {
    webhookData.carregando = false;
  }
}

/**
 * Criar novo webhook
 */
async function criarWebhook(event) {
  event.preventDefault();
  
  const formData = new FormData(event.target);
  const dados = {
    event_type: formData.get('event_type'),
    url: formData.get('url'),
    secret: formData.get('secret') || null,
    active: 1
  };
  
  // Validações básicas
  if (!dados.event_type) {
    mostrarMensagemErro('Selecione um tipo de evento');
    return;
  }
  
  if (!dados.url) {
    mostrarMensagemErro('Digite a URL do webhook');
    return;
  }
  
  // Validar URL
  try {
    new URL(dados.url);
  } catch (e) {
    mostrarMensagemErro('URL inválida');
    return;
  }
  
  try {
    const botao = event.target.querySelector('button[type="submit"]');
    botao.disabled = true;
    botao.innerHTML = '<i class="fas fa-spinner-third fa-spin mr-2"></i>Criando...';
    
    const response = await fetch('/api/v1/webhooks/subscribe.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${window.sessionStorage.getItem('token') || ''}`
      },
      body: JSON.stringify(dados)
    });
    
    const result = await response.json();
    
    if (result.success) {
      mostrarMensagemSucesso('Webhook criado com sucesso!');
      event.target.reset();
      await carregarWebhooks();
    } else {
      mostrarMensagemErro(result.message || 'Erro ao criar webhook');
    }
  } catch (error) {
    console.error('[Webhooks] Erro ao criar:', error);
    mostrarMensagemErro('Erro ao criar webhook');
  } finally {
    const botao = event.target.querySelector('button[type="submit"]');
    botao.disabled = false;
    botao.innerHTML = '<i class="fas fa-plus mr-2"></i>Criar Webhook';
  }
}

/**
 * Renderizar lista de webhooks
 */
function renderizarWebhooks() {
  const container = document.getElementById('webhooks-list');
  
  if (webhookData.subscriptions.length === 0) {
    container.innerHTML = `
      <div class="text-center py-12 text-gray-500">
        <i class="fas fa-inbox text-4xl mb-4 opacity-40"></i>
        <p class="text-lg">Nenhum webhook configurado</p>
        <p class="text-sm mt-2">Crie um novo webhook no formulário ao lado</p>
      </div>
    `;
    return;
  }
  
  let html = '<div class="space-y-4">';
  
  webhookData.subscriptions.forEach(webhook => {
    const statusClass = webhook.active ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200';
    const statusIcon = webhook.active ? 'fas fa-check-circle text-green-600' : 'fas fa-times-circle text-gray-400';
    const statusText = webhook.active ? 'Ativo' : 'Inativo';
    
    const criadoEm = new Date(webhook.created_at).toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
    
    const eventoIcon = obterIconoEvento(webhook.event_type);
    const eventoIconFA = obterIconeFontAwesome(webhook.event_type);
    
    html += `
      <div class="webhook-item border ${statusClass} rounded-lg p-4">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-gradient-to-br from-blue-100 to-blue-50 border border-blue-200">
                <i class="${eventoIconFA} text-blue-600 text-lg"></i>
              </div>
              <div>
                <h4 class="font-semibold text-gray-800">${obterNomeEvento(webhook.event_type)}</h4>
                <p class="text-sm text-gray-600"><code class="bg-gray-100 px-2 py-1 rounded text-xs">${webhook.url}</code></p>
              </div>
            </div>
            <div class="flex items-center gap-4 mt-3 text-xs text-gray-600">
              <span><i class="fas fa-calendar mr-1"></i>${criadoEm}</span>
              <span><i class="${statusIcon} mr-1"></i>${statusText}</span>
              ${webhook.retry_count > 0 ? `<span class="text-orange-600"><i class="fas fa-redo mr-1"></i>${webhook.retry_count} tentativa(s)</span>` : ''}
            </div>
          </div>
          <div class="flex gap-2">
            <button onclick="testarWebhook(${webhook.id})" class="btn btn-sm btn-info px-3 py-1 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition" title="Testar">
              <i class="fas fa-play mr-1"></i>Testar
            </button>
            <button onclick="deletarWebhook(${webhook.id})" class="btn btn-sm btn-danger px-3 py-1 rounded bg-red-100 text-red-700 hover:bg-red-200 transition" title="Deletar">
              <i class="fas fa-trash mr-1"></i>
            </button>
          </div>
        </div>
      </div>
    `;
  });
  
  html += '</div>';
  container.innerHTML = html;
}

/**
 * Testar webhook
 */
async function testarWebhook(subscriptionId) {
  let botao = null;
  let htmlOriginal = null;
  
  try {
    botao = event.target.closest('button');
    if (!botao) {
      throw new Error('Botão não encontrado');
    }
    
    botao.disabled = true;
    htmlOriginal = botao.innerHTML;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    const response = await fetch('/api/v1/webhooks/testWebhook.php', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        subscription_id: subscriptionId,
        event_type: 'test.webhook'
      })
    });
    
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      throw new Error('Resposta inválida do servidor');
    }
    
    const result = await response.json();
    
    if (result.success) {
      mostrarMensagemSucesso('Webhook de teste enfileirado! Você receberá em breve.');
    } else {
      mostrarMensagemErro(result.message || 'Erro ao testar webhook');
    }
  } catch (error) {
    console.error('[Webhooks] Erro ao testar:', error);
    mostrarMensagemErro('Erro ao testar webhook: ' + error.message);
  } finally {
    if (botao && htmlOriginal) {
      botao.disabled = false;
      botao.innerHTML = htmlOriginal;
    }
  }
}

/**
 * Deletar webhook
 */
async function deletarWebhook(subscriptionId) {
  if (!confirm('Tem certeza que deseja deletar este webhook? Esta ação não pode ser desfeita.')) {
    return;
  }
  
  try {
    const response = await fetch(`/api/v1/webhooks/deleteSubscription.php`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ id: subscriptionId })
    });
    
    // Verifica se a resposta é válida
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text();
      console.error('[Webhooks] Resposta inválida:', text);
      throw new Error('Resposta inválida do servidor (esperado JSON)');
    }
    
    const result = await response.json();
    
    if (result.success) {
      mostrarMensagemSucesso('Webhook deletado com sucesso');
      await carregarWebhooks();
    } else {
      mostrarMensagemErro(result.message || 'Erro ao deletar webhook');
    }
  } catch (error) {
    console.error('[Webhooks] Erro ao deletar:', error);
    mostrarMensagemErro('Erro ao deletar webhook: ' + error.message);
  }
}

/**
 * Atualizar estatísticas
 */
function atualizarEstatisticas() {
  const total = webhookData.subscriptions.length;
  const ativos = webhookData.subscriptions.filter(w => w.active).length;
  const inativos = total - ativos;
  
  document.getElementById('webhooks-total').textContent = total;
  document.getElementById('webhooks-ativos').textContent = ativos;
  document.getElementById('webhooks-inativos').textContent = inativos;
}

/**
 * Obter ícone do tipo de evento
 */
function obterIconoEvento(eventType) {
  const icones = {
    'lead.created': '👤',
    'lead.updated': '✏️',
    'lead.converted': '🎯',
    'property.created': '🏠',
    'property.sold': '💰',
    'commission.created': '💵',
    'commission.paid': '✅',
    'alert.triggered': '🔔',
    'test.webhook': '🧪'
  };
  return icones[eventType] || '📌';
}

/**
 * Obter ícone Font Awesome do evento
 */
function obterIconeFontAwesome(eventType) {
  const icones = {
    'lead.created': 'fas fa-user-plus',
    'lead.updated': 'fas fa-edit',
    'lead.converted': 'fas fa-bullseye',
    'property.created': 'fas fa-home',
    'property.sold': 'fas fa-dollar-sign',
    'commission.created': 'fas fa-money-bill',
    'commission.paid': 'fas fa-check-circle',
    'alert.triggered': 'fas fa-bell',
    'test.webhook': 'fas fa-flask'
  };
  return icones[eventType] || 'fas fa-circle';
}

/**
 * Obter nome do tipo de evento
 */
function obterNomeEvento(eventType) {
  const nomes = {
    'lead.created': 'Lead Criado',
    'lead.updated': 'Lead Atualizado',
    'lead.converted': 'Lead Convertido',
    'property.created': 'Propriedade Criada',
    'property.sold': 'Propriedade Vendida',
    'commission.created': 'Comissão Criada',
    'commission.paid': 'Comissão Paga',
    'alert.triggered': 'Alerta Disparado',
    'test.webhook': 'Webhook de Teste'
  };
  return nomes[eventType] || eventType;
}

/**
 * Mostrar mensagem de sucesso
 */
function mostrarMensagemSucesso(mensagem) {
  const toast = document.createElement('div');
  toast.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 z-50';
  toast.innerHTML = `<i class="fas fa-check-circle"></i> ${mensagem}`;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.remove();
  }, 3000);
}

/**
 * Mostrar mensagem de erro
 */
function mostrarMensagemErro(mensagem) {
  const toast = document.createElement('div');
  toast.className = 'fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-2 z-50';
  toast.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${mensagem}`;
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.remove();
  }, 3000);
}

/**
 * Inicializar quando a aba de webhooks for ativada
 */
document.addEventListener('DOMContentLoaded', function() {
  // Verificar se estamos na aba de webhooks
  const webhooksTab = document.getElementById('webhooks');
  
  if (webhooksTab) {
    // Usar MutationObserver para detectar quando a aba fica visível
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.attributeName === 'class') {
          if (!webhooksTab.classList.contains('hidden')) {
            initWebhooks();
          }
        }
      });
    });
    
    observer.observe(webhooksTab, { attributes: true });
    
    // Sempre inicializar o módulo ao carregar a página
    // A flag 'initialized' garante que não será inicializado múltiplas vezes
    initWebhooks();
  }
});

/**
 * Interceptar cliques na aba de webhooks
 */
document.addEventListener('click', function(e) {
  const tabLink = e.target.closest('.tab-link[href="#webhooks"]');
  if (tabLink) {
    setTimeout(() => {
      initWebhooks();
    }, 100);
  }
});
