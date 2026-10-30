/**
 * Session Keep-Alive (Production Grade / SaaS Level)
 */

(function () {
    'use strict';

    const HEARTBEAT_INTERVAL = 5 * 60 * 1000; // 5 min
    const INACTIVITY_TIMEOUT = 20 * 60 * 1000; // 20 min

    let heartbeatInterval = null;
    let inactivityTimeout = null;
    let activityThrottle = null;

    let lastActivityTime = Date.now();

    // =========================
    // ANTI DUPLICAÇÃO GLOBAL
    // =========================
    if (window.__KEEPALIVE_STARTED__) return;

    window.__KEEPALIVE_STARTED__ = true;
    window.__KEEPALIVE_RUNNING__ = false;
    window.__KEEPALIVE_LISTENERS__ = false;

    // =========================
    // HEARTBEAT
    // =========================
    async function sendHeartbeat() {
        try {
            const response = await fetch('/api/v1/check_login.php', {
                method: 'GET',
                credentials: 'include',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.status === 401) {
                handleSessionExpired();
                return;
            }

            // retry leve em caso de instabilidade
            if (!response.ok) {
                setTimeout(sendHeartbeat, 3000);
            }
        } catch (err) {
            console.warn('[KeepAlive] heartbeat falhou, tentando novamente...');
            setTimeout(sendHeartbeat, 5000);
        }
    }

    // =========================
    // ATIVIDADE USUÁRIO
    // =========================
    function recordActivity() {
        const now = Date.now();
        lastActivityTime = now;

        if (inactivityTimeout) clearTimeout(inactivityTimeout);

        inactivityTimeout = setTimeout(() => {
            console.warn('[KeepAlive] usuário inativo por muito tempo');
        }, INACTIVITY_TIMEOUT);
    }

    function onActivity() {
        if (activityThrottle) return;

        activityThrottle = setTimeout(() => {
            recordActivity();
            activityThrottle = null;
        }, 1200);
    }

    // =========================
    // START / STOP
    // =========================
    function startKeepAlive() {
        if (heartbeatInterval || window.__KEEPALIVE_RUNNING__) return;

        window.__KEEPALIVE_RUNNING__ = true;

        heartbeatInterval = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL);

        sendHeartbeat(); // ping inicial

        console.log('[KeepAlive] iniciado');
    }

    function stopKeepAlive() {
        window.__KEEPALIVE_RUNNING__ = false;

        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }

        if (inactivityTimeout) {
            clearTimeout(inactivityTimeout);
            inactivityTimeout = null;
        }

        console.log('[KeepAlive] parado');
    }

    // =========================
    // SESSÃO EXPIRADA
    // =========================
    function handleSessionExpired() {
        stopKeepAlive();

        if (window.__SESSION_EXPIRED_SHOWN__) return;
        window.__SESSION_EXPIRED_SHOWN__ = true;

        const el = document.createElement('div');
        el.style.cssText = `
            position:fixed;
            top:20px;
            right:20px;
            background:#ef4444;
            color:#fff;
            padding:14px 18px;
            border-radius:10px;
            z-index:99999;
            font-family:Arial;
            box-shadow:0 10px 30px rgba(0,0,0,0.2);
        `;

        el.innerHTML = `
            <b>Sessão expirada</b><br>
            Redirecionando...
        `;

        document.body.appendChild(el);

        setTimeout(() => {
            window.location.href = '/login.php?error=session_expired';
        }, 1500);
    }

    // =========================
    // MONITORAMENTO
    // =========================
    function setupActivityMonitoring() {
        if (window.__KEEPALIVE_LISTENERS__) return;
        window.__KEEPALIVE_LISTENERS__ = true;

        const events = ['mousedown', 'keydown', 'scroll', 'touchstart'];

        events.forEach((event) => {
            document.addEventListener(event, onActivity, { passive: true });
        });
    }

    function setupVisibilityMonitoring() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopKeepAlive();
            } else {
                recordActivity();
                startKeepAlive();
            }
        });
    }

    // =========================
    // INIT
    // =========================
    function init() {
        if (
            !window.location.pathname.includes('painel') &&
            !window.location.pathname.includes('dashboard')
        ) {
            return;
        }

        setupActivityMonitoring();
        setupVisibilityMonitoring();

        recordActivity();
        startKeepAlive();
    }

    // =========================
    // BOOT
    // =========================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
