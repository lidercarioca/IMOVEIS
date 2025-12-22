// Gerenciador de notificações de MENSAGENS do formulário de contato
// ATENÇÃO: Este arquivo monitora MENSAGENS (formulários do site), não EMAILS reais
// Para emails reais do Gmail, criar arquivo separado: gmail-notifications.js
const MESSAGE_POLLING_INTERVAL = 30000; // 30 segundos
let messagePollingTimeout = null;
let trackedMessages = JSON.parse(localStorage.getItem('trackedMessages') || '[]');

/**
 * Carrega e compara MENSAGENS não lidas do formulário
 * Cria notificações para novas mensagens de contato
 */
async function checkNewMessages() {
    try {
        const res = await fetch('api/getUnreadEmails.php');
        if (!res.ok) return;
        
        const data = await res.json();
        if (!data.success) return;
        
        // Para cada MENSAGEM não lida
        if (data.messages && data.messages.length > 0) {
            for (const message of data.messages) {
                // Verifica se já foi processado
                if (!trackedMessages.includes(message.id)) {
                    // Cria notificação
                    await createMessageNotification(message);
                    
                    // Marca como processado
                    trackedMessages.push(message.id);
                }
            }
            
            // Salva mensagens rastreadas no localStorage
            localStorage.setItem('trackedMessages', JSON.stringify(trackedMessages));
        }
    } catch (err) {
        console.error('Erro ao verificar novas mensagens:', err);
    }
}

/**
 * Cria uma notificação no painel para uma nova MENSAGEM de contato
 */
async function createMessageNotification(message) {
    try {
        const res = await fetch('api/createEmailNotification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email_id: message.id,
                from_name: message.from_name,
                from_email: message.from_email,
                subject: message.subject
            })
        });
        
        if (!res.ok) return;
        
        const data = await res.json();
        if (data.success) {
            console.log('Notificação de mensagem criada:', message.from_name);
            
            // Atualiza as notificações na UI
            if (typeof checkNotifications === 'function') {
                checkNotifications();
            }
        }
    } catch (err) {
        console.error('Erro ao criar notificação de mensagem:', err);
    }
}

/**
 * Inicia o polling de MENSAGENS
 */
function startMessagePolling() {
    // Primeira verificação imediata
    checkNewMessages();
    
    // Configura polling periódico
    if (!messagePollingTimeout) {
        messagePollingTimeout = setInterval(() => {
            checkNewMessages();
        }, MESSAGE_POLLING_INTERVAL);
        console.log('Polling de mensagens de contato iniciado');
    }
}

/**
 * Para o polling de MENSAGENS
 */
function stopMessagePolling() {
    if (messagePollingTimeout) {
        clearInterval(messagePollingTimeout);
        messagePollingTimeout = null;
        console.log('Polling de mensagens parado');
    }
}

/**
 * Inicializa quando o documento está pronto
 */
document.addEventListener('DOMContentLoaded', function() {
    // Inicia monitoramento de mensagens de formulário
    startMessagePolling();
    
    // Para o polling quando a página fica invisível
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            stopMessagePolling();
        } else {
            startMessagePolling();
        }
    });
    
    console.log('Sistema de notificações de MENSAGENS ativado');
});
