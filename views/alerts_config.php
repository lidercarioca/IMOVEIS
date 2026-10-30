<?php
require_once __DIR__ . '/../config/session_manager.php';
if (!isLoggedIn()) header('Location: login.php');
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">Configurar Alertas</h1>

        <!-- Alert Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Imóveis Parados -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-red-500">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="text-2xl mr-2">⚠️</span>
                    Imóveis Parados
                </h3>
                <p class="text-gray-600 mb-4">Alerta quando imóvel ficar dias sem movimento</p>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Dias sem movimento:</label>
                        <input type="number" class="alert-threshold" data-alert-type="stopped_properties" 
                               min="1" max="90" value="30" class="w-full px-3 py-2 border rounded">
                    </div>
                    <label class="flex items-center">
                        <input type="checkbox" class="alert-toggle" data-alert-type="stopped_properties" checked>
                        <span class="ml-2">Ativar alerta</span>
                    </label>
                </div>
            </div>

            <!-- Leads Convertidos -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="text-2xl mr-2">🎉</span>
                    Leads em Alta
                </h3>
                <p class="text-gray-600 mb-4">Alerta quando atingir meta de conversões diárias</p>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Meta de conversões/dia:</label>
                        <input type="number" class="alert-threshold" data-alert-type="high_conversion_leads"
                               min="1" max="50" value="5" class="w-full px-3 py-2 border rounded">
                    </div>
                    <label class="flex items-center">
                        <input type="checkbox" class="alert-toggle" data-alert-type="high_conversion_leads" checked>
                        <span class="ml-2">Ativar alerta</span>
                    </label>
                </div>
            </div>

            <!-- Meta de Vendas -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="text-2xl mr-2">🏆</span>
                    Meta de Vendas
                </h3>
                <p class="text-gray-600 mb-4">Alerta ao atingir meta mensal de vendas</p>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Meta mensal:</label>
                        <input type="number" class="alert-threshold" data-alert-type="sales_milestone"
                               min="1" max="100" value="10" class="w-full px-3 py-2 border rounded">
                    </div>
                    <label class="flex items-center">
                        <input type="checkbox" class="alert-toggle" data-alert-type="sales_milestone">
                        <span class="ml-2">Ativar alerta</span>
                    </label>
                </div>
            </div>

            <!-- Taxa de Conversão -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <h3 class="text-lg font-semibold mb-4 flex items-center">
                    <span class="text-2xl mr-2">📊</span>
                    Taxa Conversão Baixa
                </h3>
                <p class="text-gray-600 mb-4">Alerta quando taxa ficar abaixo da meta</p>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium mb-1">Meta taxa (%):</label>
                        <input type="number" class="alert-threshold" data-alert-type="low_conversion_rate"
                               min="1" max="100" value="25" class="w-full px-3 py-2 border rounded">
                    </div>
                    <label class="flex items-center">
                        <input type="checkbox" class="alert-toggle" data-alert-type="low_conversion_rate">
                        <span class="ml-2">Ativar alerta</span>
                    </label>
                </div>
            </div>
        </div>

        <button id="save-alerts" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
            Salvar Configurações
        </button>

        <!-- Histórico de Alertas -->
        <div class="mt-8">
            <h2 class="text-2xl font-bold mb-4">Histórico de Alertas</h2>
            <div id="alerts-history" class="bg-white rounded-lg shadow-md p-6">
                <p class="text-gray-500">Carregando...</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadAlertConfig();
    loadAlertsHistory();

    // Salvar configurações
    document.getElementById('save-alerts').addEventListener('click', async function() {
        const alerts = [];
        document.querySelectorAll('.alert-toggle').forEach(checkbox => {
            const type = checkbox.dataset.alertType;
            const threshold = document.querySelector(`input[data-alert-type="${type}"].alert-threshold`).value;
            alerts.push({
                alert_type: type,
                threshold: threshold,
                enabled: checkbox.checked ? 1 : 0
            });
        });

        for (const alert of alerts) {
            await fetch('/api/relatorios/configureAlerts.php?action=configure', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `alert_type=${alert.alert_type}&threshold=${alert.threshold}&enabled=${alert.enabled}`
            });
        }

        alert('Configurações salvas com sucesso!');
    });
});

async function loadAlertConfig() {
    try {
        const response = await fetch('/api/relatorios/configureAlerts.php?action=get_config');
        const result = await response.json();

        if (result.success) {
            // Suporta tanto 'data' quanto 'alerts'
            const alerts = result.data || result.alerts || [];
            alerts.forEach(alert => {
                const checkbox = document.querySelector(`input[data-alert-type="${alert.alert_type}"].alert-toggle`);
                const input = document.querySelector(`input[data-alert-type="${alert.alert_type}"].alert-threshold`);
                if (checkbox) checkbox.checked = alert.enabled === 1;
                if (input) input.value = alert.threshold;
            });
        }
    } catch (error) {
        console.error('Error loading config:', error);
    }
}

async function loadAlertsHistory() {
    try {
        const response = await fetch('/api/relatorios/configureAlerts.php?action=get_history');
        const result = await response.json();

        if (result.success) {
            // Suporta tanto 'history' quanto 'data'
            const history = result.history || result.data || [];
            let html = '';
            history.forEach(alert => {
                html += `
                    <div class="border-b pb-3 mb-3">
                        <div class="flex justify-between">
                            <div>
                                <p class="font-semibold">${alert.title}</p>
                                <p class="text-gray-600 text-sm">${alert.message}</p>
                            </div>
                            <span class="text-xs text-gray-500">${new Date(alert.sent_at).toLocaleString('pt-BR')}</span>
                        </div>
                    </div>
                `;
            });
            document.getElementById('alerts-history').innerHTML = html || '<p class="text-gray-500">Nenhum alerta</p>';
        }
    } catch (error) {
        console.error('Error loading history:', error);
    }
}
</script>
