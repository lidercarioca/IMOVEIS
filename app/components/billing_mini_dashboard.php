<?php

/**
 * Componente: Mini Dashboard de Cobrança para o Painel
 * Mostra informações importantes de cobrança dentro do painel
 */

// BillingHistoryModel agora carregado via Composer PSR-4 em src/Models
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

function renderBillingMiniDashboard($pdo, $tenantId, $diasRestantesCobranca)
{
    try {
        // Obter informações do tenant (migrado para TenantRepository::getTenantData)
        $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
        $tenantData = $tenantRepo->getTenantData($tenantId);

        // Reusar a instância existente de BillingHistoryModel para obter o overview PSR-4.
        $model = new \App\Models\BillingHistoryModel($pdo);
        $paymentRepo = new \App\Infrastructure\Billing\PaymentRepository($model);
        $cobranca = $paymentRepo->getBillingOverview($tenantId);

        if (!$tenantData || !$cobranca) {
            return '';
        }

        // Calcular status visual usando lógica centralizada
        $diasRestantesCobranca = $tenantRepo->getDaysRemaining($tenantId);
        if ($diasRestantesCobranca === null) {
            $diasRestantesCobranca = 0;
        }

        $statusClass = 'success';
        $statusIcon = 'check-circle';
        $statusText = 'Ativo';

        if ($tenantData['status'] === 'inactive') {
            $statusClass = 'danger';
            $statusIcon = 'times-circle';
            $statusText = 'Inativo';
        } elseif ($tenantRepo->isExpired($tenantId)) {
            $statusClass = 'danger';
            $statusIcon = 'times-circle';
            $statusText = 'Expirado';
        } elseif ($tenantRepo->shouldShowExpiryWarning($tenantId)) {
            $statusClass = 'warning';
            $statusIcon = 'exclamation-circle';
            $statusText = 'Vencendo';
        } else {
            $statusClass = 'success';
            $statusIcon = 'check-circle';
            $statusText = 'Ativo';
        }

        // Buscar últimos pagamentos
        $model = new \App\Models\BillingHistoryModel($pdo);
        $paymentResult = $model->listPayments($tenantId, [], 1, 3);
        $recentPayments = $paymentResult['success'] && !empty($paymentResult['data'])
            ? array_slice($paymentResult['data'], 0, 3)
            : [];

        // Obter resumo do mês
        $reportResult = $model->getRevenueReport($tenantId, 'mes');
        $monthlySummary = $reportResult['success'] ? $reportResult['summary'] : null;

        $html = '
        <div class="billing-mini-dashboard">
            <style>
                .billing-mini-dashboard {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 12px;
                    padding: 1.5rem;
                    color: white;
                    margin-bottom: 2rem;
                    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
                }

                .billing-mini-dashboard h4 {
                    font-weight: 700;
                    margin-bottom: 1rem;
                    font-size: 1.25rem;
                }

                .billing-status {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 1.5rem;
                    padding-bottom: 1.5rem;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
                }

                .billing-status-info {
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                }

                .billing-status-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.5rem 1rem;
                    background: rgba(255, 255, 255, 0.2);
                    border-radius: 6px;
                    font-size: 0.875rem;
                    font-weight: 600;
                }

                .billing-stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                    gap: 1rem;
                    margin-bottom: 1.5rem;
                }

                .billing-stat {
                    background: rgba(255, 255, 255, 0.1);
                    padding: 1rem;
                    border-radius: 8px;
                    text-align: center;
                    border: 1px solid rgba(255, 255, 255, 0.2);
                }

                .billing-stat-label {
                    font-size: 0.75rem;
                    opacity: 0.9;
                    text-transform: uppercase;
                    margin-bottom: 0.5rem;
                }

                .billing-stat-value {
                    font-size: 1.5rem;
                    font-weight: 700;
                    margin-bottom: 0.25rem;
                }

                .billing-stat-subtext {
                    font-size: 0.75rem;
                    opacity: 0.85;
                }

                .recent-payments {
                    margin-top: 1.5rem;
                    padding-top: 1.5rem;
                    border-top: 1px solid rgba(255, 255, 255, 0.2);
                }

                .recent-payments h6 {
                    font-size: 0.875rem;
                    font-weight: 600;
                    margin-bottom: 1rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }

                .payment-item {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 0.75rem;
                    background: rgba(255, 255, 255, 0.05);
                    border-radius: 6px;
                    margin-bottom: 0.5rem;
                    border-left: 3px solid rgba(255, 255, 255, 0.4);
                }

                .payment-item:last-child {
                    margin-bottom: 0;
                }

                .payment-info {
                    font-size: 0.875rem;
                }

                .payment-date {
                    opacity: 0.9;
                    display: block;
                    margin-bottom: 0.25rem;
                }

                .payment-plan {
                    font-size: 0.75rem;
                    opacity: 0.8;
                }

                .payment-amount {
                    font-weight: 700;
                    font-size: 1rem;
                }

                .actions-row {
                    display: flex;
                    gap: 0.75rem;
                    margin-top: 1.5rem;
                }

                .actions-row a {
                    flex: 1;
                    padding: 0.75rem 1rem;
                    background: rgba(255, 255, 255, 0.2);
                    border: 1px solid rgba(255, 255, 255, 0.3);
                    color: white;
                    text-align: center;
                    text-decoration: none;
                    border-radius: 6px;
                    font-size: 0.875rem;
                    font-weight: 500;
                    transition: all 0.3s ease;
                }

                .actions-row a:hover {
                    background: rgba(255, 255, 255, 0.3);
                    border-color: rgba(255, 255, 255, 0.5);
                }

                .actions-row a.btn-primary {
                    background: white;
                    color: #667eea;
                    border-color: white;
                }

                .actions-row a.btn-primary:hover {
                    background: #f0f0f0;
                    border-color: #f0f0f0;
                }
            </style>

            <h4>
                <i class="fas fa-credit-card me-2"></i>
                Informações de Cobrança
            </h4>

            <!-- Status da Assinatura -->
            <div class="billing-status">
                <div class="billing-status-info">
                    <div>
                        <small>Status da Assinatura</small>
                        <p class="mb-0">
                            <strong>' . htmlspecialchars($tenantData['name']) . '</strong>
                        </p>
                    </div>
                </div>
                <span class="billing-status-badge">
                    <i class="fas fa-' . $statusIcon . '"></i>
                    ' . htmlspecialchars($statusText) . '
                </span>
            </div>

            <!-- Estatísticas -->
            <div class="billing-stats">
                <div class="billing-stat">
                    <div class="billing-stat-label">Plano Atual</div>
                    <div class="billing-stat-value">' . ucfirst(htmlspecialchars($cobranca['plano'])) . '</div>
                    <div class="billing-stat-subtext">
                        ' . htmlspecialchars($cobranca['limite_usuarios']) . ' usuários
                    </div>
                </div>

                <div class="billing-stat">
                    <div class="billing-stat-label">Dias Restantes</div>
                    <div class="billing-stat-value">
                        ' . ($diasRestantesCobranca > 0 ? $diasRestantesCobranca : '0') . '
                    </div>
                    <div class="billing-stat-subtext">
                        ' . ($diasRestantesCobranca > 0 ? 'até expiração' : 'expirado') . '
                    </div>
                </div>

                <div class="billing-stat">
                    <div class="billing-stat-label">Total Pago</div>
                    <div class="billing-stat-value">R$ ' . number_format($cobranca['total_pago'] ?? 0, 0, ',', '.') . '</div>
                    <div class="billing-stat-subtext">
                        ' . ($cobranca['total_pagamentos'] ?? 0) . ' pagamento(s)
                    </div>
                </div>';

        if ($monthlySummary) {
            $html .= '
                <div class="billing-stat">
                    <div class="billing-stat-label">Faturamento Mês</div>
                    <div class="billing-stat-value">R$ ' . number_format($monthlySummary['valor_pago'], 0, ',', '.') . '</div>
                    <div class="billing-stat-subtext">
                        ' . $monthlySummary['total_pagamentos'] . ' transação(ões)
                    </div>
                </div>';
        }

        $html .= '
            </div>';

        // Últimos Pagamentos
        if (!empty($recentPayments)) {
            $html .= '
            <div class="recent-payments">
                <h6>Últimos Pagamentos</h6>';

            foreach (array_slice($recentPayments, 0, 3) as $payment) {
                $statusIcon = $payment['status'] === 'pago'
                    ? '<i class="fas fa-check-circle me-1"></i>'
                    : '<i class="fas fa-hourglass-end me-1"></i>';

                $html .= '
                <div class="payment-item">
                    <div class="payment-info">
                        <span class="payment-date">' . htmlspecialchars($payment['data_pagamento']) . '</span>
                        <span class="payment-plan">' . ucfirst(htmlspecialchars($payment['plano'])) . '</span>
                    </div>
                    <div class="payment-amount">R$ ' . number_format($payment['valor'], 2, ',', '.') . '</div>
                </div>';
            }

            $html .= '
            </div>';
        }

        // Botões de Ação
        $html .= '
            <div class="actions-row">
                <a href="/payment-history.php" class="btn-primary">
                    <i class="fas fa-history me-1"></i> Ver Histórico
                </a>
                <a href="/planos.php">
                    <i class="fas fa-refresh me-1"></i> Renovar Plano
                </a>
            </div>
        </div>';

        return $html;
    } catch (Exception $e) {
        error_log("Erro ao renderizar billing mini dashboard: " . $e->getMessage());
        return '';
    }
}
