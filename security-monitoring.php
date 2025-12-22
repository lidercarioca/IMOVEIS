<?php
/**
 * Painel de Monitoramento de Segurança
 * Mostra anomalias, IPs suspeitas, alertas de ataque
 */

require_once 'auth.php';
require_once 'app/security/SecurityHeaders.php';

// Necessita autenticação
checkAuth();

// Somente admin principal (username == 'admin') tem acesso
if (!isAdmin() || !isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    // Para UX, redireciona para unauthorized
    header('Location: unauthorized.php');
    exit;
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
            </div>
        </div>

        <!-- Cards de Resumo -->
        <div class="row mb-4">
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
    <script>
        // Carrega dados de monitoramento via API
        async function loadSecurityDashboard() {
            try {
                const response = await fetch('/api/getSecurityMonitoring.php');
                const data = await response.json();
                
                if (data.success) {
                    updateDashboard(data.data);
                }
            } catch (error) {
                console.error('Erro ao carregar dashboard:', error);
            }
        }
        
        function updateDashboard(data) {
            // Atualiza cards de resumo
            document.getElementById('anomalies-total').textContent = data.anomalies_count || 0;
            document.getElementById('suspicious-ips').textContent = data.suspicious_ips_count || 0;
            document.getElementById('blocked-ips').textContent = data.blocked_ips_count || 0;
            document.getElementById('critical-alerts').textContent = data.critical_alerts_count || 0;
            
            // Anomalias
            const anomaliesHtml = (data.anomalies || []).map(a => `
                <div class="anomaly-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-1">
                                <span class="badge bg-${a.severity === 'CRITICAL' ? 'danger' : a.severity === 'HIGH' ? 'warning' : 'info'}">
                                    ${a.anomaly_type}
                                </span>
                            </h6>
                            <p class="mb-1"><strong>${a.username}</strong></p>
                            <small class="text-muted">IP: ${a.ip_address}</small>
                            <br>
                            <small class="text-muted">${new Date(a.detected_at).toLocaleString('pt-BR')}</small>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary" onclick="resolveAnomaly(${a.id})">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                </div>
            `).join('');
            document.getElementById('anomalies-container').innerHTML = anomaliesHtml || '<p class="text-muted">Nenhuma anomalia detectada</p>';
            
            // IPs suspeitas
            const suspiciousHtml = (data.suspicious_ips || []).map(ip => `
                <div class="ip-suspicious">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${ip.ip_address}</strong>
                            <br>
                            <small class="text-muted">
                                ${ip.attempt_count} tentativas de ${ip.unique_users} usuário(s)
                            </small>
                            <br>
                            <small class="text-muted">${ip.attempted_users}</small>
                        </div>
                        <button class="btn btn-sm btn-danger" onclick="blockIP('${ip.ip_address}')">
                            <i class="fas fa-ban"></i> Bloquear
                        </button>
                    </div>
                </div>
            `).join('');
            document.getElementById('suspicious-ips-container').innerHTML = suspiciousHtml || '<p class="text-muted">Nenhuma IP suspeita</p>';
            
            // IPs bloqueadas
            const blockedHtml = (data.blocked_ips || []).map(ip => `
                <div class="alert alert-danger mb-2">
                    <strong>${ip.ip_address}</strong> - ${ip.reason}
                    <br>
                    <small>Bloqueada há ${new Date(ip.blocked_at).toLocaleString('pt-BR')}</small>
                </div>
            `).join('');
            document.getElementById('blocked-ips-container').innerHTML = blockedHtml || '<p class="text-muted">Nenhuma IP bloqueada</p>';
            
            // Alertas
            const alertsHtml = (data.alerts || []).map(a => `
                <div class="alert alert-${a.severity === 'CRITICAL' ? 'danger' : a.severity === 'HIGH' ? 'warning' : 'info'} mb-2">
                    <strong>${a.alert_type}</strong> - ${a.message}
                    <br>
                    <small>${new Date(a.alert_time).toLocaleString('pt-BR')}</small>
                </div>
            `).join('');
            document.getElementById('security-alerts-container').innerHTML = alertsHtml || '<p class="text-muted">Nenhum alerta</p>';
        }
        
        async function resolveAnomaly(anomalyId) {
            // TODO: Implementar API para marcar anomalia como resolvida
            alert('Funcionalidade em desenvolvimento');
        }
        
        async function blockIP(ip) {
            // TODO: Implementar API para bloquear IP
            alert('Funcionalidade em desenvolvimento');
        }
        
        // Carrega dashboard ao abrir
        loadSecurityDashboard();
        
        // Atualiza a cada 30 segundos
        setInterval(loadSecurityDashboard, 30000);
    </script>
</body>
</html>
