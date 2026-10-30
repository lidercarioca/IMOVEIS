<?php
/**
 * Painel de Monitoramento de Segurança
 * Mostra anomalias, IPs suspeitas, alertas de ataque
 *
 * Acesso: Apenas admin
 * Mostra: Tentativas de acesso negadas, IPs bloqueadas, alertas
 */

require_once 'auth.php';
require_once 'config/database.php';
require_once 'app/security/Security.php';
require_once 'app/security/TenantContext.php';
require_once 'app/helpers/tenant_isolation_helpers.php';

// Headers de segurança
Security::setSecurityHeaders();

// Necessita autenticação
checkAuth();

// Somente admins têm acesso (qualquer admin, não apenas username='admin')
if (!isAdmin()) {
    http_response_code(403);
    echo '<h1>Acesso Negado</h1>';
    exit;
}

// Initialize tenant context
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

// Registra acesso ao painel de monitoramento
try {
    $stmt = $pdo->prepare("INSERT INTO security_events (tenant_id, user_id, event_type, severity, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        (int)$tenantId,
        $_SESSION['user_id'] ?? null,
        'ADMIN_ACCESS',
        'INFO',
        'Acesso ao painel de monitoramento de segurança',
        $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
    ]);
} catch (Exception $e) {
    error_log("Erro ao registrar acesso ao monitoramento: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento de Segurança - RR Imóveis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --danger: #dc3545;
            --warning: #ffc107;
            --success: #28a745;
            --info: #17a2b8;
        }
        
        .alert-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            padding: 5px 10px;
            border-radius: 50%;
            background: var(--danger);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .anomaly-card {
            border-left: 4px solid var(--danger);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            background: #f8f9fa;
        }
        
        .severity-critical { color: var(--danger); }
        .severity-high { color: #fd7e14; }
        .severity-medium { color: var(--warning); }
        .severity-low { color: var(--success); }
        
        .ip-suspicious {
            background: #ffe6e6;
            border: 1px solid #ffcccc;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h2 d-flex align-items-center gap-2">
                    <i class="fas fa-shield-alt text-danger"></i> 
                    Monitoramento de Segurança
                </h1>
                <p class="text-muted">Detecte anomalias, bloqueie IPs suspeitas e monitore ataques em tempo real</p>
                <small class="text-muted">
                    <i class="fas fa-sync-alt"></i> Atualiza a cada 30 segundos
                    | Última atualização: <span id="last-update">--:--:--</span>
                </small>
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Tentativas Falhadas</h6>
                        <h3 id="failed-attempts" class="text-danger">0</h3>
                        <small class="text-muted">Últimas 24h</small>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Acessos Negados</h6>
                        <h3 id="denied-access" class="text-warning">0</h3>
                        <small class="text-muted">Últimas 24h</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body position-relative">
                        <span class="alert-badge" id="anomalies-count">0</span>
                        <h6 class="text-muted">Anomalias Detectadas</h6>
                        <h3 id="anomalies-total" class="text-danger">0</h3>
                        <small class="text-muted">Últimas 24h</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">IPs Suspeitas</h6>
                        <h3 id="suspicious-ips" class="text-warning">0</h3>
                        <small class="text-muted">Última hora</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">IPs Bloqueadas</h6>
                        <h3 id="blocked-ips" class="text-info">0</h3>
                        <small class="text-muted">Ativas</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Alertas Críticos</h6>
                        <h3 id="critical-alerts" class="text-danger">0</h3>
                        <small class="text-muted">Não resolvidos</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Anomalias Recentes -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle text-danger"></i> Anomalias Detectadas</h5>
                    </div>
                    <div class="card-body" id="anomalies-container">
                        <p class="text-muted text-center py-4">Carregando...</p>
                    </div>
                </div>
            </div>

            <!-- IPs Suspeitas -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="fas fa-network-wired text-warning"></i> IPs Suspeitas (Última Hora)</h5>
                    </div>
                    <div class="card-body" id="suspicious-ips-container">
                        <p class="text-muted text-center py-4">Carregando...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- IPs Bloqueadas -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="fas fa-ban text-danger"></i> IPs Bloqueadas</h5>
                    </div>
                    <div class="card-body" id="blocked-ips-container">
                        <p class="text-muted text-center py-4">Carregando...</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alertas de Segurança -->
        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0"><i class="fas fa-bell text-danger"></i> Alertas de Segurança</h5>
                    </div>
                    <div class="card-body" id="security-alerts-container">
                        <p class="text-muted text-center py-4">Carregando...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Token CSRF para proteção contra ataques
        const csrfToken = '<?php echo Security::generateCSRFToken(); ?>';
        
        // Função para escapar HTML e prevenir XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Função para validar IP (previne injeção)
        function isValidIP(ip) {
            const ipv4Regex = /^(\d{1,3}\.){3}\d{1,3}$/;
            const ipv6Regex = /^([0-9a-f]{0,4}:){2,7}[0-9a-f]{0,4}$/i;
            return ipv4Regex.test(ip) || ipv6Regex.test(ip);
        }

        // Carrega dados de monitoramento via API
        async function loadSecurityDashboard() {
            try {
                const response = await fetch('/api/v1/debug/getSecurityMonitoring.php', {
                    method: 'GET',
                    credentials: 'include',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error('API falhou: ' + (data.message || 'Desconhecido'));
                }
                
                updateDashboard(data.data);
                
                // Atualiza o timestamp
                const now = new Date();
                document.getElementById('last-update').textContent = now.toLocaleTimeString('pt-BR');
                
            } catch (error) {
                // Mostra erro em todos os containers
                const containers = ['anomalies-container', 'suspicious-ips-container', 'blocked-ips-container', 'security-alerts-container'];
                containers.forEach(id => {
                    const elem = document.getElementById(id);
                    if (elem) {
                        elem.innerHTML = '<p class="text-danger text-center py-3"><strong>Erro:</strong> ' + error.message + '</p>';
                    }
                });
            }
        }
        
        function updateDashboard(data) {
            try {
                // Atualiza cards de resumo com validação numérica
                updateCard('anomalies-total', parseInt(data.anomalies_count) || 0);
                updateCard('suspicious-ips', parseInt(data.suspicious_ips_count) || 0);
                updateCard('blocked-ips', parseInt(data.blocked_ips_count) || 0);
                updateCard('critical-alerts', parseInt(data.critical_alerts_count) || 0);
                
                // Atualiza badges
                updateCard('anomalies-badge', parseInt(data.anomalies_count) || 0);
                updateCard('suspicious-ips-badge', parseInt(data.suspicious_ips_count) || 0);
                updateCard('blocked-ips-badge', parseInt(data.blocked_ips_count) || 0);
                updateCard('alerts-badge', parseInt(data.critical_alerts_count) || 0);
                
                renderAnomalies(data.anomalies || []);
                
                // IPs suspeitas com validação
                renderSuspiciousIPs(data.suspicious_ips || []);
                
                // IPs bloqueadas
                renderBlockedIPs(data.blocked_ips || []);
                
                // Alertas críticos
                renderAlerts(data.alerts || []);
                
            } catch (error) {
                throw error;
            }
        }

        function updateCard(elementId, value) {
            const elem = document.getElementById(elementId);
            if (elem) {
                elem.textContent = value;
            }
        }

        function renderAnomalies(anomalies) {
            try {
                const container = document.getElementById('anomalies-container');
                if (!anomalies.length) {
                    container.innerHTML = '<p class="text-muted text-center py-3">Nenhuma anomalia detectada</p>';
                    return;
                }

                container.innerHTML = anomalies.map(a => {
                    const severityClass = a.severity === 'CRITICAL' ? 'danger' : a.severity === 'HIGH' ? 'warning' : 'info';
                    const detectedAt = new Date(a.detected_at).toLocaleString('pt-BR');
                    
                    return `
                        <div class="list-group-item list-group-item-action border-start border-${severityClass}">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-2">
                                        <span class="badge bg-${severityClass}">
                                            ${escapeHtml(a.anomaly_type)}
                                        </span>
                                    </h6>
                                    <p class="mb-1"><strong>${escapeHtml(a.username)}</strong></p>
                                    <small class="text-muted">IP: ${escapeHtml(a.ip_address)}</small>
                                    <br>
                                    <small class="text-muted">${escapeHtml(detectedAt)}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-success" onclick="resolveAnomaly(${parseInt(a.id)}, event)">
                                    <i class="fas fa-check"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (error) {
                document.getElementById('anomalies-container').innerHTML = '<p class="text-danger">Erro ao renderizar anomalias: ' + error.message + '</p>';
            }
        }

        function renderSuspiciousIPs(ips) {
            try {
                const container = document.getElementById('suspicious-ips-container');
                if (!ips.length) {
                    container.innerHTML = '<p class="text-muted text-center py-3">Nenhuma IP suspeita</p>';
                    return;
                }

                container.innerHTML = ips.map((ip, index) => {
                    if (!isValidIP(ip.ip_address)) {
                        return '';
                    }
                    return `
                        <div class="list-group-item list-group-item-action border-start border-warning">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><strong>${escapeHtml(ip.ip_address)}</strong></h6>
                                    <small class="text-muted">
                                        ${parseInt(ip.attempt_count || 0)} tentativas de ${parseInt(ip.unique_users || 0)} usuário(s)
                                    </small>
                                    <br>
                                    <small class="text-muted">${escapeHtml(ip.attempted_users || 'Desconhecido')}</small>
                                </div>
                                <button class="btn btn-sm btn-danger" onclick="blockIP('${escapeHtml(ip.ip_address)}', event)">
                                    <i class="fas fa-ban"></i> Bloquear
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (error) {
                document.getElementById('suspicious-ips-container').innerHTML = '<p class="text-danger">Erro ao renderizar: ' + error.message + '</p>';
            }
        }

        function renderBlockedIPs(ips) {
            try {
                const container = document.getElementById('blocked-ips-container');
                if (!ips.length) {
                    container.innerHTML = '<p class="text-muted text-center py-3">Nenhuma IP bloqueada</p>';
                    return;
                }

                container.innerHTML = ips.map(ip => {
                    const blockedAt = new Date(ip.blocked_at).toLocaleString('pt-BR');
                    return `
                        <div class="alert alert-danger mb-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>${escapeHtml(ip.ip_address)}</strong> - ${escapeHtml(ip.reason)}
                                    <br>
                                    <small>Bloqueada há ${escapeHtml(blockedAt)}</small>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" onclick="unblockIP('${escapeHtml(ip.ip_address)}', event)">
                                    <i class="fas fa-lock-open"></i> Desbloquear
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (error) {
                document.getElementById('blocked-ips-container').innerHTML = '<p class="text-danger">Erro ao renderizar: ' + error.message + '</p>';
            }
        }

        function renderAlerts(alerts) {
            try {
                const container = document.getElementById('security-alerts-container');
                if (!alerts.length) {
                    container.innerHTML = '<p class="text-muted text-center py-3">Nenhum alerta</p>';
                    return;
                }

                container.innerHTML = alerts.map(a => {
                    const alertTime = new Date(a.alert_time).toLocaleString('pt-BR');
                    const severityClass = a.severity === 'CRITICAL' ? 'danger' : a.severity === 'HIGH' ? 'warning' : 'info';
                    return `
                        <div class="alert alert-${severityClass} mb-2">
                            <strong>${escapeHtml(a.alert_type)}</strong> - ${escapeHtml(a.message)}
                            <br>
                            <small>${escapeHtml(alertTime)}</small>
                        </div>
                    `;
                }).join('');
            } catch (error) {
                document.getElementById('security-alerts-container').innerHTML = '<p class="text-danger">Erro ao renderizar: ' + error.message + '</p>';
            }
        }
        
        async function resolveAnomaly(anomalyId, event) {
            if (!Number.isInteger(anomalyId) || anomalyId <= 0) {
                alert('ID de anomalia inválido');
                return;
            }

            event.target.closest('button').disabled = true;
            event.target.closest('button').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const response = await fetch('/api/v1/debug/resolveAnomaly.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ anomaly_id: anomalyId, csrf_token: csrfToken })
                });

                const data = await response.json();
                if (data.success) {
                    // Remove a anomalia após 500ms
                    setTimeout(() => loadSecurityDashboard(), 500);
                } else {
                    alert('Erro: ' + (data.message || 'Falha ao resolver anomalia'));
                    event.target.closest('button').disabled = false;
                    event.target.closest('button').innerHTML = '<i class="fas fa-check"></i>';
                }
            } catch (error) {
                alert('Erro ao processar solicitação');
                event.target.closest('button').disabled = false;
                event.target.closest('button').innerHTML = '<i class="fas fa-check"></i>';
            }
        }
        
        async function blockIP(ip, event) {
            if (!isValidIP(ip)) {
                alert('Formato de IP inválido');
                return;
            }

            if (!confirm(`Confirma o bloqueio de ${ip}?`)) {
                return;
            }

            event.target.closest('button').disabled = true;
            event.target.closest('button').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const response = await fetch('/api/v1/debug/blockIP.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ ip_address: ip, reason: 'Bloqueada manualmente pelo admin', csrf_token: csrfToken })
                });

                const data = await response.json();
                if (data.success) {
                    setTimeout(() => loadSecurityDashboard(), 500);
                } else {
                    alert('Erro: ' + (data.message || 'Falha ao bloquear IP'));
                    event.target.closest('button').disabled = false;
                    event.target.closest('button').innerHTML = '<i class="fas fa-ban"></i> Bloquear';
                }
            } catch (error) {
                alert('Erro ao processar solicitação');
                event.target.closest('button').disabled = false;
                event.target.closest('button').innerHTML = '<i class="fas fa-ban"></i> Bloquear';
            }
        }

        async function unblockIP(ip, event) {
            if (!isValidIP(ip)) {
                alert('Formato de IP inválido');
                return;
            }

            if (!confirm(`Confirma o desbloqueio de ${ip}?`)) {
                return;
            }

            event.target.closest('button').disabled = true;
            event.target.closest('button').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const response = await fetch('/api/v1/debug/unblockIP.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ ip_address: ip, csrf_token: csrfToken })
                });

                const data = await response.json();
                if (data.success) {
                    setTimeout(() => loadSecurityDashboard(), 500);
                } else {
                    alert('Erro: ' + (data.message || 'Falha ao desbloquear IP'));
                    event.target.closest('button').disabled = false;
                    event.target.closest('button').innerHTML = '<i class="fas fa-lock-open"></i> Desbloquear';
                }
            } catch (error) {
                alert('Erro ao processar solicitação');
                event.target.closest('button').disabled = false;
                event.target.closest('button').innerHTML = '<i class="fas fa-lock-open"></i> Desbloquear';
            }
        }
        
        // Carrega dashboard ao abrir
        // Garantir que o DOM está pronto antes de chamar
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                loadSecurityDashboard();
            });
        } else {
            loadSecurityDashboard();
        }
        
        // Atualiza a cada 30 segundos
        setInterval(() => {
            loadSecurityDashboard();
        }, 30000);
    </script>
</body>
</html>
