<?php

/**
 * Componente: Dashboard de Cobrança
 * Reutilizável em painel.php e select_tenant.php
 * Mostra informações de billing e status de assinatura
 */

require_once __DIR__ . '/../../config/session_manager.php';

function renderBillingDashboard($pdo, $tenantId = null)
{
    global $pdo;
    $db = $pdo;

    if (!$tenantId) {
        $tenantId = function_exists('getRequestTenantId') ? getRequestTenantId() : null;
    }

    if (!$tenantId) {
        return '<div class="alert alert-danger">Tenant não identificado para exibir cobrança.</div>';
    }

    try {
        // Usar TenantRepository PSR-4 para funções read-only equivalentes
        $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);

        // Buscar informações de billing via repository PSR-4, preservando a lógica atual do dashboard.
        $model = new \App\Models\BillingHistoryModel($pdo);
        $paymentRepo = new \App\Infrastructure\Billing\PaymentRepository($model);
        $cobranca = $paymentRepo->getBillingOverview($tenantId);
        $showExpiryWarning = $tenantRepo->shouldShowExpiryWarning($tenantId);

        if (!$cobranca) {
            return '<div class="alert alert-warning">Erro ao carregar informações de cobrança.</div>';
        }

        // Calcular dias restantes
        $diasRestantes = $tenantRepo->getDaysRemaining($tenantId);
        if ($diasRestantes === null) {
            $diasRestantes = 0;
        }
        $statusCor = 'secondary';
        $statusIcon = 'circle';

        if ($cobranca['data_expiracao']) {
            // Determinar cor baseado em dias restantes
            if ($cobranca['status'] === 'inactive') {
                $statusCor = 'danger';
                $statusIcon = 'times-circle';
            } elseif ($diasRestantes < 0) {
                $statusCor = 'danger';
                $statusIcon = 'times-circle';
            } elseif ($diasRestantes <= 3) {
                $statusCor = 'warning';
                $statusIcon = 'exclamation-circle';
            } elseif ($diasRestantes <= 7) {
                $statusCor = 'info';
                $statusIcon = 'info-circle';
            } else {
                $statusCor = 'success';
                $statusIcon = 'check-circle';
            }
        }

        $html = '<div class="cobranca-dashboard">';

        // Seção 1: Status Atual
        $html .= '
        <div class="row mb-4">
            <div class="col-md-12">
                <h5 class="mb-3">
                    <i class="fas fa-credit-card"></i> Status da Assinatura
                </h5>
            </div>
        </div>
        
        <div class="row mb-4">
            <!-- Card: Status -->
            <div class="col-md-3 mb-3">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Status</small>
                        <p class="mb-0">
                            <span class="badge bg-' . $statusCor . '">
                                <i class="fas fa-' . $statusIcon . ' me-1"></i>
                                ' . ucfirst($cobranca['status']) . '
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Card: Plano -->
            <div class="col-md-3 mb-3">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Plano</small>
                        <p class="mb-0 fw-bold">' . ucfirst($cobranca['plano']) . '</p>
                        <small class="text-muted">
                            ' . $cobranca['limite_usuarios'] . ' usuários
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Card: Data de Expiração -->
            <div class="col-md-3 mb-3">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Expiração</small>
                        <p class="mb-0 fw-bold">
                            ' . ($cobranca['data_expiracao'] ? date('d/m/Y', strtotime($cobranca['data_expiracao'])) : 'Sem data') . '
                        </p>
                        <small class="text-muted">
                            ' . ($diasRestantes > 0 ? "$diasRestantes dias restantes" : ($diasRestantes === 0 ? 'Expira hoje' : 'Expirado')) . '
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Card: Pagamentos -->
            <div class="col-md-3 mb-3">
                <div class="card border-0 bg-light">
                    <div class="card-body">
                        <small class="text-muted d-block mb-2">Receita Paga</small>
                        <p class="mb-0 fw-bold">
                            R$ ' . number_format($cobranca['total_pago'] ?? 0, 2, ',', '.') . '
                        </p>
                        <small class="text-muted">
                            ' . ($cobranca['total_pagamentos'] ?? 0) . ' pagamento(s)
                        </small>
                    </div>
                </div>
            </div>
        </div>';

        // Seção 2: Último Pagamento
        if ($cobranca['ultimo_pagamento']) {
            $html .= '
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert alert-info mb-0">
                        <small>
                            <i class="fas fa-check-circle me-2"></i>
                            Último pagamento: <strong>' . date('d/m/Y H:i', strtotime($cobranca['ultimo_pagamento'])) . '</strong>
                        </small>
                    </div>
                </div>
            </div>';
        }

        // Seção 3: Avisos
        if ($cobranca['status'] === 'inactive' || $diasRestantes < 0) {
            $html .= '
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert alert-danger" role="alert">
                        <h6 class="alert-heading mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>Ação Necessária
                        </h6>
                        <p>Sua assinatura expirou. Acesse <a href="/planos.php" class="alert-link">Planos</a> para renovar.</p>
                    </div>
                </div>
            </div>';
        } elseif ($showExpiryWarning) {
            $html .= '
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert alert-warning" role="alert">
                        <h6 class="alert-heading mb-2">
                            <i class="fas fa-exclamation-circle me-2"></i>Aviso
                        </h6>
                        <p>Sua assinatura vence em <strong>' . $diasRestantes . ' dias</strong>. Renove em <a href="/planos.php" class="alert-link">Planos</a>.</p>
                    </div>
                </div>
            </div>';
        }

        // Seção 4: Botões de Ação
        $html .= '
        <div class="row">
            <div class="col-md-12">
                <div class="btn-group" role="group">
                    <a href="/planos.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-refresh me-1"></i> Renovar/Alterar Plano
                    </a>
                    <a href="/payment-history.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-history me-1"></i> Histórico de Pagamentos
                    </a>
                </div>
            </div>
        </div>
        
        </div>';

        return $html;
    } catch (Exception $e) {
        error_log("Erro ao renderizar billing dashboard: " . $e->getMessage());
        return '<div class="alert alert-danger">Erro ao carregar informações de cobrança.</div>';
    }
}

/**
 * Retorna informações de billing de um tenant (para listagens)
 */
function getBillingCardInfo($pdo, $tenantId)
{
    try {
        $model = new \App\Models\BillingHistoryModel($pdo);
        $paymentRepo = new \App\Infrastructure\Billing\PaymentRepository($model);
        $cobranca = $paymentRepo->getBillingOverview($tenantId);

        if (!$cobranca) {
            return null;
        }

        $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
        $diasRestantes = $tenantRepo->getDaysRemaining($tenantId);
        if ($diasRestantes === null) {
            $diasRestantes = 0;
        }

        // Determinar badge de status
        $statusBadge = 'secondary';
        if ($cobranca['status'] === 'active' && $diasRestantes > 7) {
            $statusBadge = 'success';
        } elseif ($cobranca['status'] === 'active' && $diasRestantes <= 7) {
            $statusBadge = 'warning';
        } else {
            $statusBadge = 'danger';
        }

        return [
            'status' => $cobranca['status'],
            'plano' => $cobranca['plano'],
            'limite_usuarios' => $cobranca['limite_usuarios'],
            'data_expiracao' => $cobranca['data_expiracao'],
            'dias_restantes' => $diasRestantes,
            'total_pago' => $cobranca['total_pago'],
            'status_badge' => $statusBadge
        ];
    } catch (Exception $e) {
        error_log("Erro ao buscar info de cobrança para card: " . $e->getMessage());
        return null;
    }
}
