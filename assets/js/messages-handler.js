/**
 * Gerenciador de Mensagens do Formulário de Contato (Sistema Antigo)
 * Integrado ao novo sistema Phase 9.3 de Notificações
 * 
 * Responsabilidades:
 * - Buscar mensagens não lidas via polling
 * - Atualizar badge com contagem
 * - Renderizar dropdown de mensagens
 * - Marcar mensagens como lidas
 */

// Configurações
const MESSAGES_POLLING_INTERVAL = 30000; // 30 segundos
let messagesPollingTimeout = null;
let lastMessagesCount = 0;

// Elementos DOM
let messagesBtn, messagesDropdown, messagesBadge, messagesList;

// Função para verificar novas mensagens
async function checkMessages() {
    try {
        const res = await fetch('/api/v1/auth/getUnreadEmails.php', {
            credentials: 'include'
        });
        if (!res.ok) throw new Error('Falha na requisição: ' + res.status);
        
        const data = await res.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Erro ao verificar mensagens');
        }

        const unreadCount = data.unread_count || 0;

        // Se houver novas mensagens
        if (unreadCount > lastMessagesCount) {
            updateMessagesBadge(unreadCount);
        } else if (unreadCount < lastMessagesCount) {
            // Se diminuiu (mensagens foram marcadas como lidas)
            updateMessagesBadge(unreadCount);
        }

        lastMessagesCount = unreadCount;
    } catch (err) {
        console.error('[Messages.js] Erro ao verificar mensagens:', err);
    }
}

// Função para iniciar o polling
function startMessagesPolling() {
    // Primeira verificação imediata
    checkMessages().catch(err => console.error('Erro na verificação inicial:', err));
    
    // Configura o polling
    if (!messagesPollingTimeout) {
        messagesPollingTimeout = setInterval(() => {
            checkMessages().catch(err => console.error('Erro no polling:', err));
        }, MESSAGES_POLLING_INTERVAL);
    }
}

// Função para parar o polling
function stopMessagesPolling() {
    if (messagesPollingTimeout) {
        clearInterval(messagesPollingTimeout);
        messagesPollingTimeout = null;
    }
}

// Função para atualizar o badge de mensagens
function updateMessagesBadge(count) {
    if (!messagesBadge) {
        return;
    }
    
    if (count > 0) {
        messagesBadge.classList.remove('hidden');
        messagesBadge.textContent = count > 9 ? '9+' : count;
    } else {
        messagesBadge.classList.add('hidden');
    }
}

// Função para carregar e exibir mensagens
async function loadMessages() {
    try {
        const res = await fetch('/api/v1/auth/getUnreadEmails.php', {
            credentials: 'include'
        });
        if (!res.ok) throw new Error('Falha na requisição');
        
        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar mensagens');
        }

        // Atualiza badge
        updateMessagesBadge(data.unread_count || 0);

        // Limpa a lista atual
        messagesList.innerHTML = '';

        // Se não houver mensagens
        if (!data.messages || data.messages.length === 0) {
            messagesList.innerHTML = `
                <div class="p-4 text-center text-gray-500">
                    Nenhuma mensagem
                </div>
            `;
            return;
        }

        // Adiciona cada mensagem
        for (const message of data.messages) {
            const messageEl = document.createElement('div');
            messageEl.className = 'p-3 border-b hover:bg-gray-50 cursor-pointer transition';
            messageEl.dataset.messageId = message.id;
            messageEl.innerHTML = `
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900 text-sm">${escapeHtml(message.from_name || message.from_email)}</p>
                        <p class="text-gray-600 text-sm">${escapeHtml(message.subject)}</p>
                        <p class="text-gray-400 text-xs mt-1">${formatDate(message.created_at)}</p>
                    </div>
                    <button class="mark-read-btn text-blue-600 hover:text-blue-800 text-sm ml-2" title="Marcar como lida">
                        ✓
                    </button>
                </div>
            `;

            // Evento para abrir detalhes da mensagem
            messageEl.addEventListener('click', (e) => {
                if (!e.target.classList.contains('mark-read-btn')) {
                    openMessageDetail(message.id, message);
                }
            });

            // Evento para marcar como lida
            messageEl.querySelector('.mark-read-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                markMessageAsRead(message.id);
            });

            messagesList.appendChild(messageEl);
        }

    } catch (err) {
        console.error('[Messages.js] Erro ao carregar mensagens:', err);
        messagesList.innerHTML = `
            <div class="p-4 text-center text-red-500">
                Erro ao carregar mensagens
            </div>
        `;
    }
}

// Função para abrir detalhes da mensagem
async function openMessageDetail(messageId, message) {
    // Aqui você pode abrir um modal ou redirecionar para uma página de detalhes
    
    // Opção: Redirecionar para aba de leads ou mostrar modal
    // alert(`Mensagem de ${message.from_name}:\n\n${message.subject}`);
}

// Função para marcar mensagem como lida
async function markMessageAsRead(messageId) {
    try {
        
        const res = await fetch('/api/v1/auth/markMessageAsRead.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ message_id: messageId })
        });

        if (!res.ok) throw new Error('Falha ao marcar como lida');
        
        const data = await res.json();
        if (data.success) {
            // Recarrega a lista
            await loadMessages();
        }
    } catch (err) {
        console.error('[Messages.js] Erro ao marcar como lida:', err);
    }
}

// Função para marcar todas as mensagens como lidas
async function markAllMessagesAsRead() {
    try {
        
        const res = await fetch('/api/v1/auth/markAllMessagesAsRead.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({})
        });

        if (!res.ok) throw new Error('Falha ao marcar todas como lidas');
        
        const data = await res.json();
        if (data.success) {
            // Recarrega a lista
            await loadMessages();
        }
    } catch (err) {
        console.error('[Messages.js] Erro ao marcar todas como lidas:', err);
    }
}

// Função auxiliar para escapar HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Função auxiliar para formatar data
function formatDate(dateString) {
    const date = new Date(dateString);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } else if (date.toDateString() === yesterday.toDateString()) {
        return 'Ontem';
    } else {
        return date.toLocaleDateString('pt-BR');
    }
}

// Inicializar quando o DOM está pronto
document.addEventListener('DOMContentLoaded', () => {
    // Pega elementos do DOM
    messagesBtn = document.getElementById('messages-btn');
    messagesDropdown = document.getElementById('messages-dropdown');
    messagesBadge = document.getElementById('messages-badge');
    messagesList = document.getElementById('messages-list');
    
    if (!messagesBtn || !messagesDropdown || !messagesBadge || !messagesList) {
        return;
    }
    
    // Toggle do dropdown
    messagesBtn.addEventListener('click', async (e) => {
        e.stopPropagation();
        messagesDropdown.classList.toggle('hidden');
        
        // Carrega mensagens quando abre o dropdown
        if (!messagesDropdown.classList.contains('hidden')) {
            await loadMessages();
        }
    });
    
    // Fechar dropdown quando clica fora
    document.addEventListener('click', (e) => {
        if (!messagesBtn.contains(e.target) && !messagesDropdown.contains(e.target)) {
            messagesDropdown.classList.add('hidden');
        }
    });
    
    // Botão "Marcar todas como lidas"
    const markAllBtn = document.getElementById('mark-all-messages-read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', markAllMessagesAsRead);
    }
    
    // Inicia o polling
    startMessagesPolling();
});
