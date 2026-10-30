<?php

/**
 * Página: Histórico de Pagamentos
 * Mostra todos os pagamentos do tenant com filtros e relatório
 */

require_once 'config/session_manager.php';
require_once 'config/database.php';
require_once 'app/security/CSRF.php';
require_once 'app/security/Security.php';
require_once 'app/security/TenantContext.php';

// Headers de segurança
Security::setSecurityHeaders();
Security::blockLogsAccess();

// Verificar autenticação
require_once 'auth.php';
checkAuth();

// Validar contexto de tenant
TenantContext::initialize();

// Se for SUPERADMIN sem tenant selecionado, redirecionar
if (isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] && !isset($_SESSION['selected_tenant_id'])) {
    header("Location: select_tenant.php");
    exit;
}

// Obter tenant ID
$tenantId = (int)TenantContext::getTenantId();

// Gera novo token CSRF se não existir
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Carregar branding
$companyName = 'Sua Imobiliária';
$companyLogo = '/assets/imagens/logo/logo_padrao.png';

try {
    $stmt = $pdo->prepare('SELECT company_name, company_logo FROM company_settings WHERE tenant_id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $brand = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($brand) {
        if (!empty($brand['company_name'])) {
            $companyName = $brand['company_name'];
        }
        if (!empty($brand['company_logo'])) {
            $companyLogo = $brand['company_logo'][0] === '/' ? $brand['company_logo'] : '/' . $brand['company_logo'];
        }
    }
} catch (Exception $e) {
    error_log('Erro ao carregar branding: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Pagamentos - <?php echo htmlspecialchars($companyName); ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="/output.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        .navbar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-size: 1.3rem;
            font-weight: 600;
            color: white !important;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
            opacity: 1;
        }

        .payment-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #718096;
            font-size: 1rem;
        }

        .filters-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filters-card h5 {
            margin-bottom: 1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .form-control,
        .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 0.95rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-filter {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            background: linear-gradient(90deg, #5568d3 0%, #6a3f94 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-reset {
            border: 1px solid #e2e8f0;
            background: white;
            color: #667eea;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-reset:hover {
            background: #f7fafc;
            border-color: #667eea;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
        }

        .stat-label {
            color: #718096;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-subtext {
            color: #a0aec0;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .payment-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .table {
            margin-bottom: 0;
        }

        .table thead {
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .table th {
            font-weight: 600;
            color: #4a5568;
            padding: 1rem;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #e2e8f0;
        }

        .table tbody tr:hover {
            background-color: #f7fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            font-size: 0.825rem;
            font-weight: 500;
        }

        .status-pago {
            background-color: #c6f6d5;
            color: #22543d;
        }

        .status-pendente {
            background-color: #feebc8;
            color: #7c2d12;
        }

        .status-cancelado {
            background-color: #fed7d7;
            color: #742a2a;
        }

        .plano-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 4px;
            font-size: 0.825rem;
            font-weight: 500;
            background-color: #bee3f8;
            color: #2c5282;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #718096;
        }

        .empty-state i {
            font-size: 3rem;
            color: #cbd5e0;
            margin-bottom: 1rem;
        }

        .pagination {
            margin-top: 1rem;
            justify-content: center;
        }

        .pagination .page-link {
            border-radius: 6px;
            margin: 0 2px;
            border: 1px solid #e2e8f0;
            color: #667eea;
        }

        .pagination .page-link:hover {
            background-color: #f7fafc;
            border-color: #667eea;
        }

        .pagination .page-link.active {
            background-color: #667eea;
            border-color: #667eea;
        }

        .report-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .report-section h5 {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #718096;
        }

        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 1.5rem;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .filters-card {
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/painel.php">
                <img src="<?php echo htmlspecialchars($companyLogo); ?>" alt="Logo" height="40" class="me-2">
                <span><?php echo htmlspecialchars($companyName); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/painel.php">
                            <i class="fas fa-home me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/payment-history.php">
                            <i class="fas fa-history me-1"></i> Histórico
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/planos.php">
                            <i class="fas fa-credit-card me-1"></i> Planos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Sair
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="payment-container">
        <div class="page-header">
            <h1>
                <i class="fas fa-file-invoice-dollar me-2"></i>
                Histórico de Pagamentos
            </h1>
            <p>Visualize todos os seus pagamentos, planos e relatórios de receita</p>
        </div>

        <!-- Relatório Rápido (KPIs) -->
        <div id="quick-report" class="stats-row loading">
            <div class="loading-spinner"></div>
        </div>

        <!-- Filtros -->
        <div class="filters-card">
            <h5>
                <i class="fas fa-filter me-2"></i>
                Filtros
            </h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Período</label>
                    <select class="form-select" id="filterMes">
                        <option value="">Todos os meses</option>
                        <?php
                        // Gerar lista dos últimos 12 meses
                        for ($i = 0; $i < 12; $i++) {
                            $mes = date('Y-m', strtotime("-$i months"));
                            $label = strftime('%B de %Y', strtotime($mes . '-01'));
                            echo '<option value="' . htmlspecialchars($mes) . '">' . htmlspecialchars($label) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">Todos os status</option>
                        <option value="pago">Pago</option>
                        <option value="pendente">Pendente</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Plano</label>
                    <select class="form-select" id="filterPlano">
                        <option value="">Todos os planos</option>
                        <option value="trial">Trial</option>
                        <option value="basico">Básico</option>
                        <option value="profissional">Profissional</option>
                        <option value="premium">Premium</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button class="btn btn-filter w-100" id="applyFilters">
                            <i class="fas fa-search me-1"></i> Buscar
                        </button>
                        <button class="btn btn-reset" id="resetFilters">
                            <i class="fas fa-redo me-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de Pagamentos -->
        <div class="payment-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Plano</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="paymentTableBody">
                    <tr>
                        <td colspan="6" class="loading">
                            <div class="loading-spinner"></div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Paginação -->
            <nav id="pagination" class="d-none">
                <ul class="pagination"></ul>
            </nav>

            <!-- Estado Vazio -->
            <div id="emptyState" class="empty-state d-none">
                <i class="fas fa-inbox"></i>
                <p>Nenhum pagamento encontrado</p>
            </div>
        </div>

        <!-- Relatório de Receita -->
        <div class="report-section">
            <h5>
                <i class="fas fa-chart-pie me-2"></i>
                Relatório de Receita
            </h5>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Período do Relatório</label>
                    <select class="form-select" id="reportPeriodo">
                        <option value="mes">Este Mês</option>
                        <option value="trimestre">Este Trimestre</option>
                        <option value="ano">Este Ano</option>
                        <option value="todos">Todos os Tempos</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-filter w-100" id="generateReport">
                        <i class="fas fa-chart-bar me-1"></i> Gerar Relatório
                    </button>
                </div>
            </div>

            <div id="reportContent" class="d-none">
                <div class="row g-3" id="reportStats">
                    <!-- Preenchido dinamicamente -->
                </div>

                <!-- Gráfico de Receita por Plano -->
                <div class="mt-4">
                    <h6>Receita por Plano</h6>
                    <div id="planChartContainer" class="chart-container"></div>
                </div>

                <!-- Gráfico de Receita por Mês -->
                <div class="mt-4">
                    <h6>Evolução Mensal</h6>
                    <div id="monthChartContainer" class="chart-container"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <script>
        const API_BASE = '/api/v1/billing';
        let currentPage = 1;
        let charts = {};

        // Funções auxiliares
        function formatCurrency(value) {
            return new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL'
            }).format(value);
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return new Intl.DateTimeFormat('pt-BR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit'
            }).format(date);
        }

        function getStatusBadge(status) {
            const badges = {
                pago: '<span class="status-badge status-pago"><i class="fas fa-check-circle me-1"></i> Pago</span>',
                pendente: '<span class="status-badge status-pendente"><i class="fas fa-hourglass-end me-1"></i> Pendente</span>',
                cancelado: '<span class="status-badge status-cancelado"><i class="fas fa-times-circle me-1"></i> Cancelado</span>'
            };
            return badges[status] || status;
        }

        function getPlanoBadge(plano) {
            const planoNomes = {
                trial: 'Trial',
                basico: 'Básico',
                profissional: 'Profissional',
                premium: 'Premium'
            };
            return '<span class="plano-badge">' + (planoNomes[plano] || plano) + '</span>';
        }

        // Carregar histórico de pagamentos
        async function loadPayments(page = 1) {
            try {
                currentPage = page;

                const params = new URLSearchParams({
                    page: page,
                    limit: 20,
                    mes: document.getElementById('filterMes').value || '',
                    status: document.getElementById('filterStatus').value || '',
                    plano: document.getElementById('filterPlano').value || ''
                });

                const response = await fetch(`${API_BASE}/getPaymentHistory.php?${params}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Erro ao carregar dados');
                }

                renderPaymentTable(data.data);
                renderPagination(data.pagination);

                // Atualizar relatório rápido
                await loadQuickReport();

            } catch (error) {
                console.error('Erro:', error);
                document.getElementById('paymentTableBody').innerHTML =
                    '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar dados</td></tr>';
            }
        }

        // Renderizar tabela de pagamentos
        function renderPaymentTable(payments) {
            const tbody = document.getElementById('paymentTableBody');

            if (!payments || payments.length === 0) {
                tbody.innerHTML = '';
                document.getElementById('emptyState').classList.remove('d-none');
                return;
            }

            document.getElementById('emptyState').classList.add('d-none');

            tbody.innerHTML = payments.map(payment => `
                <tr>
                    <td>
                        <small>${payment.data_pagamento}</small>
                    </td>
                    <td>${getPlanoBadge(payment.plano)}</td>
                    <td>
                        <strong>${formatCurrency(payment.valor)}</strong>
                    </td>
                    <td>${getStatusBadge(payment.status)}</td>
                </tr>
            `).join('');
        }

        // Renderizar paginação
        function renderPagination(pagination) {
            const nav = document.getElementById('pagination');
            const ul = nav.querySelector('ul');

            if (pagination.pages <= 1) {
                nav.classList.add('d-none');
                return;
            }

            nav.classList.remove('d-none');

            let html = '';

            // Botão anterior
            if (pagination.page > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadPayments(${pagination.page - 1}); return false;">Anterior</a></li>`;
            }

            // Números de página
            for (let i = 1; i <= pagination.pages; i++) {
                if (i === pagination.page) {
                    html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
                } else if (i <= 3 || i > pagination.pages - 3 || Math.abs(i - pagination.page) <= 1) {
                    html += `<li class="page-item"><a class="page-link" href="#" onclick="loadPayments(${i}); return false;">${i}</a></li>`;
                } else if (i === 4 || i === pagination.pages - 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            // Botão próximo
            if (pagination.page < pagination.pages) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadPayments(${pagination.page + 1}); return false;">Próximo</a></li>`;
            }

            ul.innerHTML = html;
        }

        // Carregar relatório rápido
        async function loadQuickReport() {
            try {
                const response = await fetch(`${API_BASE}/getRevenueReport.php?periodo=mes`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message);
                }

                const summary = data.summary;
                const div = document.getElementById('quick-report');

                div.classList.remove('loading');
                div.innerHTML = `
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-file-invoice me-1"></i> Total de Pagamentos</div>
                        <div class="stat-value">${summary.total_pagamentos}</div>
                        <div class="stat-subtext">neste mês</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-check-circle me-1"></i> Valor Pago</div>
                        <div class="stat-value">${formatCurrency(summary.valor_pago)}</div>
                        <div class="stat-subtext">${((summary.taxa_pagamento || 0).toFixed(1))}% do total</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-hourglass-end me-1"></i> Pendente</div>
                        <div class="stat-value">${formatCurrency(summary.valor_pendente)}</div>
                        <div class="stat-subtext">aguardando processamento</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label"><i class="fas fa-dollar-sign me-1"></i> Ticket Médio</div>
                        <div class="stat-value">${formatCurrency(summary.ticket_medio)}</div>
                        <div class="stat-subtext">por pagamento</div>
                    </div>
                `;
            } catch (error) {
                console.error('Erro ao carregar relatório rápido:', error);
            }
        }

        // Gerar relatório completo
        async function generateReport() {
            try {
                const periodo = document.getElementById('reportPeriodo').value;
                const response = await fetch(`${API_BASE}/getRevenueReport.php?periodo=${periodo}`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message);
                }

                renderReport(data);

            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao gerar relatório: ' + error.message);
            }
        }

        // Renderizar relatório
        function renderReport(data) {
            const summary = data.summary;
            const byPlan = data.by_plan;
            const byMonth = data.by_month;

            // Renderizar stats
            const statsHtml = `
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Total de Pagamentos</div>
                        <div class="stat-value">${summary.total_pagamentos}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Valor Total</div>
                        <div class="stat-value">${formatCurrency(summary.valor_total)}</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Taxa de Pagamento</div>
                        <div class="stat-value">${summary.taxa_pagamento.toFixed(1)}%</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-label">Ticket Médio</div>
                        <div class="stat-value">${formatCurrency(summary.ticket_medio)}</div>
                    </div>
                </div>
            `;
            document.getElementById('reportStats').innerHTML = statsHtml;

            // Renderizar gráfico de planos
            renderPlanChart(byPlan);

            // Renderizar gráfico de meses
            renderMonthChart(byMonth);

            document.getElementById('reportContent').classList.remove('d-none');
        }

        // Gráfico de receita por plano
        function renderPlanChart(data) {
            const ctx = document.getElementById('planChartContainer');
            ctx.innerHTML = '<canvas id="planChart"></canvas>';
            const canvas = document.getElementById('planChart');

            if (charts.plan) {
                charts.plan.destroy();
            }

            charts.plan = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: data.map(item => {
                        const planos = {
                            trial: 'Trial',
                            basico: 'Básico',
                            profissional: 'Profissional',
                            premium: 'Premium'
                        };
                        return planos[item.plano] || item.plano;
                    }),
                    datasets: [{
                        data: data.map(item => item.valor_pago),
                        backgroundColor: [
                            '#667eea',
                            '#764ba2',
                            '#f093fb',
                            '#4facfe'
                        ],
                        borderColor: 'white',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Gráfico de receita por mês
        function renderMonthChart(data) {
            const ctx = document.getElementById('monthChartContainer');
            ctx.innerHTML = '<canvas id="monthChart"></canvas>';
            const canvas = document.getElementById('monthChart');

            if (charts.month) {
                charts.month.destroy();
            }

            charts.month = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: data.map(item => {
                        const [year, month] = item.mes.split('-');
                        return new Date(year, month - 1).toLocaleDateString('pt-BR', {
                            month: 'short',
                            year: '2-digit'
                        });
                    }),
                    datasets: [{
                        label: 'Faturamento (R$)',
                        data: data.map(item => item.valor_pago),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 5,
                        pointBackgroundColor: '#667eea',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    }
                }
            });
        }

        // Event listeners
        document.getElementById('applyFilters').addEventListener('click', () => loadPayments(1));
        document.getElementById('resetFilters').addEventListener('click', () => {
            document.getElementById('filterMes').value = '';
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterPlano').value = '';
            loadPayments(1);
        });

        document.getElementById('generateReport').addEventListener('click', generateReport);

        // Carregar dados ao iniciar
        window.addEventListener('load', () => {
            loadPayments(1);
        });
    </script>
</body>

</html>