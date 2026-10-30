<?php

/**
 * Componente: Payment History Summary
 * Mostra um resumo dos pagamentos e link para histórico completo
 * Reutilizável em diferentes páginas
 */

// BillingHistoryModel agora carregado via Composer PSR-4 em src/Models
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

function renderPaymentHistoryWidget($pdo, $tenantId, $options = [])
{
    $maxRows = $options['maxRows'] ?? 5;
    $showFullLink = $options['showFullLink'] ?? true;
    $showChart = $options['showChart'] ?? false;

    try {
        $model = new \App\Models\BillingHistoryModel($pdo);

        // Buscar últimos pagamentos
        $result = $model->listPayments($tenantId, [], 1, $maxRows);

        if (!$result['success']) {
            return '<div class="alert alert-warning">Erro ao carregar histórico de pagamentos.</div>';
        }

        $payments = $result['data'];

        // Buscar resumo do mês
        $report = $model->getRevenueReport($tenantId, 'mes');
        $summary = $report['success'] ? $report['summary'] : null;

        $html = '<div class="payment-history-widget">';

        // Cabeçalho
        $html .= '
        <div class="widget-header mb-3">
            <h6 class="mb-0">
                <i class="fas fa-history me-2"></i>
                Últimos Pagamentos
            </h6>
        </div>';

        // Status rápido
        if ($summary) {
            $html .= '
            <div class="quick-stats mb-3">
                <div class="stat-item">
                    <small class="text-muted">Pago este mês</small>
                    <strong class="text-success">R$ ' . number_format($summary['valor_pago'], 2, ',', '.') . '</strong>
                </div>
                <div class="stat-item">
                    <small class="text-muted">Total de pagamentos</small>
                    <strong>' . $summary['total_pagamentos'] . '</strong>
                </div>
            </div>';
        }

        // Tabela de pagamentos
        if (!empty($payments)) {
            $html .= '
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="text-muted">Data</th>
                            <th class="text-muted">Plano</th>
                            <th class="text-muted">Valor</th>
                            <th class="text-muted">Status</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($payments as $payment) {
                switch ($payment['status']) {
                    case 'pago':
                        $statusClass = 'success';
                        break;
                    case 'pendente':
                        $statusClass = 'warning';
                        break;
                    case 'cancelado':
                        $statusClass = 'danger';
                        break;
                    default:
                        $statusClass = 'secondary';
                        break;
                }

                switch ($payment['status']) {
                    case 'pago':
                        $statusIcon = 'check-circle';
                        break;
                    case 'pendente':
                        $statusIcon = 'hourglass-end';
                        break;
                    case 'cancelado':
                        $statusIcon = 'times-circle';
                        break;
                    default:
                        $statusIcon = 'circle';
                        break;
                }

                $html .= '
                    <tr>
                        <td><small>' . $payment['data_pagamento'] . '</small></td>
                        <td>
                            <small class="badge bg-info">' . ucfirst($payment['plano']) . '</small>
                        </td>
                        <td>
                            <strong>R$ ' . number_format($payment['valor'], 2, ',', '.') . '</strong>
                        </td>
                        <td>
                            <span class="badge bg-' . $statusClass . '">
                                <i class="fas fa-' . $statusIcon . ' me-1"></i>' . ucfirst($payment['status']) . '
                            </span>
                        </td>
                    </tr>';
            }

            $html .= '
                    </tbody>
                </table>
            </div>';
        } else {
            $html .= '
            <div class="alert alert-info mb-0">
                <small>
                    <i class="fas fa-info-circle me-1"></i>
                    Nenhum pagamento encontrado
                </small>
            </div>';
        }

        // Link para histórico completo
        if ($showFullLink) {
            $html .= '
            <div class="mt-3 pt-3 border-top">
                <a href="/payment-history.php" class="btn btn-primary btn-sm w-100">
                    <i class="fas fa-arrow-right me-1"></i>
                    Ver Histórico Completo
                </a>
            </div>';
        }

        $html .= '</div>';

        return $html;
    } catch (Exception $e) {
        error_log("Erro ao renderizar payment history widget: " . $e->getMessage());
        return '<div class="alert alert-danger">Erro ao carregar dados de pagamentos.</div>';
    }
}

/**
 * Renderizar card de faturamento mensal
 */
function renderMonthlyBillingCard($pdo, $tenantId)
{
    try {
        $model = new \App\Models\BillingHistoryModel($pdo);
        $report = $model->getRevenueReport($tenantId, 'mes');

        if (!$report['success']) {
            return '';
        }

        $summary = $report['summary'];

        $html = '
        <div class="billing-card">
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <small class="text-muted d-block mb-2">
                        <i class="fas fa-bar-chart me-1"></i>
                        Faturamento do Mês
                    </small>
                    <h5 class="mb-2">
                        R$ ' . number_format($summary['valor_total'], 2, ',', '.') . '
                    </h5>
                    <small class="text-muted">
                        ' . $summary['total_pagamentos'] . ' pagamentos
                        <br>
                        Taxa: ' . number_format($summary['taxa_pagamento'], 1, ',', '.') . '%
                    </small>
                </div>
            </div>
        </div>';

        return $html;
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Renderizar mini-tabela com últimos pagamentos para dashboard
 */
function renderLatestPaymentsPreview($pdo, $tenantId, $limit = 3)
{
    try {
        $model = new \App\Models\BillingHistoryModel($pdo);
        $result = $model->listPayments($tenantId, [], 1, $limit);

        if (!$result['success'] || empty($result['data'])) {
            return '';
        }

        $payments = $result['data'];

        $html = '
        <div class="latest-payments-preview">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Últimos Pagamentos</h6>
                <a href="/payment-history.php" class="text-muted small">Ver tudo</a>
            </div>
            
            <div class="list-group list-group-flush">';

        foreach ($payments as $payment) {
            switch ($payment['status']) {
                case 'pago':
                    $statusIcon = '<i class="fas fa-check-circle text-success me-2"></i>';
                    break;
                case 'pendente':
                    $statusIcon = '<i class="fas fa-hourglass-end text-warning me-2"></i>';
                    break;
                default:
                    $statusIcon = '<i class="fas fa-times-circle text-danger me-2"></i>';
                    break;
            }

            $html .= '
                <div class="list-group-item px-0 py-2 border-0">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <small class="text-muted d-block">' . $payment['data_pagamento'] . '</small>
                            <small class="fw-500">' . ucfirst($payment['plano']) . '</small>
                        </div>
                        <div class="text-end">
                            <small class="d-block fw-bold">R$ ' . number_format($payment['valor'], 2, ',', '.') . '</small>
                            <small class="text-muted">' . $statusIcon . ucfirst($payment['status']) . '</small>
                        </div>
                    </div>
                </div>';
        }

        $html .= '
            </div>
        </div>';

        return $html;
    } catch (Exception $e) {
        return '';
    }
}
