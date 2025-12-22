/**
 * Gerenciador de notificações de EMAILS reais do Gmail/servidor
 * ATENÇÃO: Este arquivo monitora EMAILS reais, não mensagens de formulário
 * Para mensagens de contato, ver: email-notifications.js
 */
const GMAIL_POLLING_INTERVAL = 60000; // 1 minuto
let gmailPollingTimeout = null;
let trackedGmailEmails = JSON.parse(localStorage.getItem('trackedGmailEmails') || '[]');

/**
 * Carrega e compara EMAILS reais não lidos do Gmail
 * Cria notificações para novos emails
 */
async function checkNewGmailEmails() {
    try {
        const res = await fetch('api/getUnreadGmailEmails.php');
        if (!res.ok) {
            console.debug('Gmail API não disponível ou sem permissão');
            return;
        }
        
        const data = await res.json();
        if (!data.success) {
            console.debug('Erro ao buscar emails do Gmail:', data.error);
            return;
        }
        
        // Para cada EMAIL não lido do Gmail
        if (data.emails && data.emails.length > 0) {
            for (const email of data.emails) {
                // Verifica se já foi processado
                if (!trackedGmailEmails.includes(email.id)) {
                    // Cria notificação
                    await createGmailNotification(email);
                    
                    // Marca como processado
                    trackedGmailEmails.push(email.id);
                }
            }
            
            // Salva emails rastreados no localStorage
            localStorage.setItem('trackedGmailEmails', JSON.stringify(trackedGmailEmails));
        }
    } catch (err) {
        console.debug('Erro ao verificar novos emails do Gmail:', err);
    }
}

/**
 * Cria uma notificação no painel para um novo EMAIL do Gmail
 */
async function createGmailNotification(email) {
    try {
        const res = await fetch('api/createGmailNotification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                gmail_id: email.id,
                from: email.from,
                subject: email.subject,
                snippet: email.snippet
            })
        });
        
        if (!res.ok) return;
        
        const data = await res.json();
        if (data.success) {
            console.log('Notificação de email Gmail criada:', email.from);
            
            // Atualiza as notificações na UI
            if (typeof checkNotifications === 'function') {
                checkNotifications();
            }
        }
    } catch (err) {
        console.error('Erro ao criar notificação de email do Gmail:', err);
    }
}

/**
 * Inicia o polling de EMAILS do Gmail
 */
function startGmailPolling() {
    // Primeira verificação imediata
    checkNewGmailEmails();
    
    // Configura polling periódico
    if (!gmailPollingTimeout) {
        gmailPollingTimeout = setInterval(() => {
            checkNewGmailEmails();
        }, GMAIL_POLLING_INTERVAL);
        console.log('Polling de emails do Gmail iniciado (a cada ' + (GMAIL_POLLING_INTERVAL / 1000) + 's)');
    }
}

/**
 * Para o polling de EMAILS do Gmail
 */
function stopGmailPolling() {
    if (gmailPollingTimeout) {
        clearInterval(gmailPollingTimeout);
        gmailPollingTimeout = null;
        console.log('Polling de emails do Gmail parado');
    }
}

/**
 * Inicializa quando o documento está pronto
 */
document.addEventListener('DOMContentLoaded', function() {
    // Inicia monitoramento de emails reais do Gmail (apenas se admin)
    if (window.isAdmin) {
        startGmailPolling();
        
        // Para o polling quando a página fica invisível
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopGmailPolling();
            } else {
                startGmailPolling();
            }
        });
        
        console.log('Sistema de notificações de EMAILS (Gmail) ativado');
    } else {
        console.log('Sistema de notificações de EMAILS (Gmail) desativado - acesso apenas para admins');
    }
});
