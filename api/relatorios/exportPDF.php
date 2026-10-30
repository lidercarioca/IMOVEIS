<?php
require_once dirname(dirname(__DIR__)) . '/api/check_auth.php';
checkApiAuth();

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/app/helpers/tenant_isolation_helpers.php';
require_once dirname(dirname(__DIR__)) . '/app/middleware/api_auth.php';
require_once dirname(dirname(__DIR__)) . '/app/utils/InputValidator.php';

checkApiAuth();

// Initialize tenant isolation
global $pdo;
initializeTenantMiddleware($pdo);
$tenantId = getCurrentTenantId();

// Verificar dependência
if (!file_exists(dirname(__DIR__) . '/../../vendor/autoload.php')) {
    die(json_encode([
        'success' => false,
        'error' => 'dompdf not installed. Run: composer require dompdf/dompdf'
    ]));
}

require_once dirname(__DIR__) . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json');

try {
    $authContext = \App\Auth\AuthContext::getInstance();
    $actorId = $authContext->getUserId();
    $isAdmin = $authContext->isAdmin() || $authContext->isSuperAdmin();

    // Validar parâmetros GET
    $period = InputValidator::getInt($_GET['period'] ?? 7, 'Período', 1, 365);
    $user_id = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? InputValidator::getInt($_GET['user_id'], 'ID do usuário', 1) : null;
    $category = isset($_GET['category']) ? InputValidator::getString($_GET['category'], 'Categoria', false, 100) : null;
    $region = isset($_GET['region']) ? InputValidator::getString($_GET['region'], 'Região', false, 100) : null;

    $reportService = new \App\Service\ReportService($pdo);
    $snapshot = $reportService->getExportSummarySnapshot($tenantId, $period, $user_id, $category, $region, $isAdmin);
    $kpiData = $snapshot['kpiData'] ?? [];
    $brokers = $snapshot['brokers'] ?? [];

    $dataFim = date('Y-m-d');
    $dataInicio = date('Y-m-d', strtotime("-{$period} days"));

    // Gerar HTML para PDF
    $html = generatePDFHTML($kpiData, $brokers, $dataInicio, $dataFim, $period);

    // Configurar Dompdf
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Retornar PDF ou download
    if ($_GET['download'] ?? false) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="dashboard_' . date('Y-m-d') . '.pdf"');
        echo $dompdf->output();
    } else {
        // Retornar base64 para exibição
        echo json_encode([
            'success' => true,
            'pdf' => base64_encode($dompdf->output()),
            'filename' => 'dashboard_' . date('Y-m-d') . '.pdf',
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s')
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function generatePDFHTML($kpiData, $brokers, $dataInicio, $dataFim, $period)
{
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
                color: #333;
            }
            .header {
                text-align: center;
                border-bottom: 2px solid #007bff;
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
            .header h1 {
                margin: 0;
                color: #007bff;
            }
            .period {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }
            .section {
                margin-bottom: 30px;
                page-break-inside: avoid;
            }
            .section h2 {
                background-color: #f0f0f0;
                padding: 10px;
                border-left: 4px solid #007bff;
                margin: 0 0 15px 0;
            }
            .kpi-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 20px;
            }
            .kpi-card {
                border: 1px solid #ddd;
                padding: 15px;
                border-radius: 5px;
                background-color: #fafafa;
            }
            .kpi-label {
                font-size: 12px;
                color: #666;
                margin-bottom: 5px;
            }
            .kpi-value {
                font-size: 24px;
                font-weight: bold;
                color: #007bff;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            table th {
                background-color: #007bff;
                color: white;
                padding: 10px;
                text-align: left;
                font-size: 12px;
            }
            table td {
                padding: 10px;
                border-bottom: 1px solid #ddd;
                font-size: 12px;
            }
            table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .footer {
                margin-top: 40px;
                text-align: center;
                color: #999;
                font-size: 10px;
                border-top: 1px solid #ddd;
                padding-top: 10px;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>📊 Relatório Dashboard Executivo</h1>
            <div class="period">
                Período: ' . $dataInicio . ' a ' . $dataFim . ' (' . $period . ' dias)
            </div>
        </div>
        
        <div class="section">
            <h2>KPIs Principais</h2>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total de Imóveis</div>
                    <div class="kpi-value">' . ($kpiData['total_properties'] ?? 0) . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total de Leads</div>
                    <div class="kpi-value">' . ($kpiData['total_leads'] ?? 0) . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total de Vendas</div>
                    <div class="kpi-value">' . ($kpiData['total_sales'] ?? 0) . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Ciclo Médio</div>
                    <div class="kpi-value">' . ($kpiData['avg_cycle'] ?? 'N/A') . ' dias</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Imóveis Parados</div>
                    <div class="kpi-value">' . ($kpiData['stopped_count'] ?? 0) . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Taxa Conversão</div>
                    <div class="kpi-value">' . (($kpiData['total_leads'] > 0 ? round(($kpiData['total_sales'] / $kpiData['total_leads']) * 100, 2) : 0)) . '%</div>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h2>Top 5 Brokers</h2>
            <table>
                <thead>
                    <tr>
                        <th>Broker</th>
                        <th>Vendas</th>
                        <th>Ticket Médio</th>
                    </tr>
                </thead>
                <tbody>';

    if (!empty($brokers)) {
        foreach ($brokers as $broker) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($broker['name']) . '</td>
                        <td>' . $broker['sales_count'] . '</td>
                        <td>R$ ' . number_format($broker['avg_price'] ?? 0, 2, ',', '.') . '</td>
                    </tr>';
        }
    }

    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="footer">
            Relatório gerado em ' . date('d/m/Y H:i:s') . '
        </div>
    </body>
    </html>';

    return $html;
}
