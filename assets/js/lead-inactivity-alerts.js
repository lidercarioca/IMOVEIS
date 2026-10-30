/**
 * Lead Inactivity Alerts
 * Gerencia exibição e ações de alertas de leads inativos
 */

let inactivityAlerts = [];
let alertsLoaded = false;

async function loadInactivityAlerts(forceReload = false) {
    // Se já está carregado e não é forceReload, usar cache
    if (alertsLoaded && !forceReload && inactivityAlerts.length > 0) {
        return inactivityAlerts;
    }

    try {
        const response = await fetch('/api/v1/alertas/getInactivityAlerts.php?limit=50');
        const json = await response.json();

        if (json.success) {
            inactivityAlerts = json.data || [];
            alertsLoaded = true;
            return json;
        } else {
            console.error('Erro ao carregar alertas:', json.error);
            return { success: false, data: [], stats: {} };
        }
    } catch (e) {
        console.error('Erro na requisição de alertas:', e);
        return { success: false, data: [], stats: {} };
    }
}

/**
 * Renderizar widget de alertas de inatividade
 */
async function renderInactivityAlertsWidget(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Mostrar loading
    container.innerHTML = `
        <div class="alerts-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Carregando alertas...</span>
        </div>
    `;

    // Carregar dados
    const result = await loadInactivityAlerts();

    if (!result.success || !result.data || result.data.length === 0) {
        container.innerHTML = `
            <div class="alerts-empty">
                <i class="fas fa-check-circle"></i>
                <p>Nenhum lead inativo no momento</p>
            </div>
        `;
        return;
    }

    // Renderizar alertas
    let html = `
        <div class="inactivity-alerts-container">
            <div class="alerts-header">
                <h3><i class="fas fa-bell"></i> Alertas de Leads Inativos</h3>
                <div class="alerts-stats">
                    <span class="stat critical" title="Crítico">
                        <i class="fas fa-circle"></i> ${result.stats.critical || 0}
                    </span>
                    <span class="stat high" title="Alto">
                        <i class="fas fa-circle"></i> ${result.stats.high || 0}
                    </span>
                    <span class="stat medium" title="Médio">
                        <i class="fas fa-circle"></i> ${result.stats.medium || 0}
                    </span>
                    <span class="stat low" title="Baixo">
                        <i class="fas fa-circle"></i> ${result.stats.low || 0}
                    </span>
                </div>
            </div>

            <div class="alerts-list">
    `;

    // Ordenar por severidade
    const severityOrder = { critical: 0, high: 1, medium: 2, low: 3 };
    result.data.sort((a, b) => severityOrder[a.severity] - severityOrder[b.severity]);

    // Renderizar cada alerta
    result.data.slice(0, 10).forEach(alert => {
        const severityLabel = getSeverityLabel(alert.severity);
        const ageLabel = `${alert.alert_age_days || 0} dias atrás`;

        html += `
            <div class="alert-item severity-${alert.severity}" data-alert-id="${alert.id}">
                <div class="alert-left">
                    <div class="alert-icon severity-${alert.severity}">
                        <i class="fas fa-exclamation"></i>
                    </div>
                    <div class="alert-content">
                        <div class="alert-title">
                            <strong>${alert.lead_name || 'Lead'}</strong>
                        </div>
                        <div class="alert-message">
                            ${alert.message}
                        </div>
                        <div class="alert-meta">
                            <span class="severity-badge severity-${alert.severity}">
                                ${severityLabel}
                            </span>
                            <span class="age-badge">
                                <i class="fas fa-clock"></i> ${ageLabel}
                            </span>
                            <span class="status-badge status-${alert.status}">
                                ${getStatusLabel(alert.status)}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="alert-actions">
                    <button class="btn-action btn-contact" onclick="handleAlertAction('resolve', ${alert.id}, ${alert.related_entity_id})" title="Marcar como contatado">
                        <i class="fas fa-phone"></i> Contatei
                    </button>
                    <button class="btn-action btn-ack" onclick="handleAlertAction('acknowledge', ${alert.id})" title="Reconhecer">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn-action btn-close" onclick="handleAlertAction('delete', ${alert.id})" title="Fechar">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    });

    if (result.data.length > 10) {
        html += `
            <div class="alerts-footer">
                <a href="/painel.php?tab=alertas-inatividade" class="view-all-link">
                    Ver todos os ${result.data.length} alertas →
                </a>
            </div>
        `;
    }

    html += `
            </div>
        </div>
    `;

    container.innerHTML = html;
}

/**
 * Processar ação em alerta
 */
async function handleAlertAction(action, alertId, leadId = null) {
    try {
        const response = await fetch('/api/v1/alertas/handleInactivityAlert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: action,
                alert_id: alertId,
                lead_id: leadId
            })
        });

        const json = await response.json();

        if (json.success) {
            // Remover alerta da lista
            const alertElement = document.querySelector(`[data-alert-id="${alertId}"]`);
            if (alertElement) {
                alertElement.classList.add('removing');
                setTimeout(() => alertElement.remove(), 300);
            }

            // Mostrar notificação
            showNotification(`Alerta ${action}d com sucesso`, 'success');

            // Recarregar widget
            setTimeout(() => {
                loadInactivityAlerts(true).then(() => {
                    const container = document.getElementById('inactivity-alerts-widget');
                    if (container) renderInactivityAlertsWidget('inactivity-alerts-widget');
                });
            }, 500);
        } else {
            showNotification(`Erro: ${json.error}`, 'error');
        }
    } catch (e) {
        console.error('Erro ao processar ação:', e);
        showNotification('Erro ao processar ação', 'error');
    }
}

/**
 * Obter label de severidade
 */
function getSeverityLabel(severity) {
    const labels = {
        'critical': '🔴 Crítico',
        'high': '🟠 Alto',
        'medium': '🟡 Médio',
        'low': '🟢 Baixo'
    };
    return labels[severity] || severity;
}

/**
 * Obter label de status
 */
function getStatusLabel(status) {
    const labels = {
        'novo': 'Novo',
        'acknowledged': 'Reconhecido',
        'resolved': 'Resolvido'
    };
    return labels[status] || status;
}

/**
 * Renderizar lista completa de alertas (página)
 */
async function renderInactivityAlertsPage() {
    const result = await loadInactivityAlerts(true);

    if (!result.success) {
        document.body.innerHTML = `<div class="error-page">Erro ao carregar alertas</div>`;
        return;
    }

    let html = `
        <div class="inactivity-alerts-page">
            <header class="page-header">
                <h1><i class="fas fa-bell"></i> Alertas de Leads Inativos</h1>
                <div class="page-stats">
                    <div class="stat-card">
                        <span class="stat-value">${result.stats.total}</span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-card critical">
                        <span class="stat-value">${result.stats.critical}</span>
                        <span class="stat-label">Crítico</span>
                    </div>
                    <div class="stat-card high">
                        <span class="stat-value">${result.stats.high}</span>
                        <span class="stat-label">Alto</span>
                    </div>
                    <div class="stat-card medium">
                        <span class="stat-value">${result.stats.medium}</span>
                        <span class="stat-label">Médio</span>
                    </div>
                    <div class="stat-card low">
                        <span class="stat-value">${result.stats.low}</span>
                        <span class="stat-label">Baixo</span>
                    </div>
                </div>
            </header>

            <div class="alerts-grid">
    `;

    result.data.forEach(alert => {
        html += `
            <div class="alert-card severity-${alert.severity}">
                <div class="card-header">
                    <div class="severity-indicator severity-${alert.severity}"></div>
                    <div class="card-title">${alert.lead_name || 'Lead'}</div>
                    <div class="card-status status-${alert.status}">
                        ${getStatusLabel(alert.status)}
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-message">${alert.message}</p>
                    <div class="card-meta">
                        <span><i class="fas fa-envelope"></i> ${alert.lead_email || 'N/A'}</span>
                        <span><i class="fas fa-clock"></i> ${alert.alert_age_days} dias atrás</span>
                    </div>
                </div>
                <div class="card-footer">
                    <button onclick="handleAlertAction('resolve', ${alert.id}, ${alert.related_entity_id})" class="btn btn-primary btn-small">
                        <i class="fas fa-phone"></i> Contatei
                    </button>
                    <button onclick="handleAlertAction('delete', ${alert.id})" class="btn btn-secondary btn-small">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });

    html += `
            </div>
        </div>
    `;

    document.body.innerHTML = html;
}

// Auto-load quando documentado está pronto
document.addEventListener('DOMContentLoaded', () => {
    const widget = document.getElementById('inactivity-alerts-widget');
    if (widget) {
        renderInactivityAlertsWidget('inactivity-alerts-widget');
        // Recarregar a cada 5 minutos
        setInterval(() => loadInactivityAlerts(true), 300000);
    }
});
