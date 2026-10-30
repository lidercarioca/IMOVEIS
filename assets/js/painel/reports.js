/**
 * Reports Module
 * Gerencia a geração e exportação de relatórios no painel administrativo
 */

class ReportsManager {
  constructor() {
    this.currentReport = null;
    this.currentData = null;
    this.init();
  }

  init() {
    this.setupEventListeners();
  }

  setupEventListeners() {
    const generateBtn = document.getElementById('generate-report-btn');
    const exportBtn = document.getElementById('export-report-btn');
    const typeSelect = document.getElementById('report-type-select');

    if (generateBtn) {
      generateBtn.addEventListener('click', () => this.generateReport());
    }

    if (exportBtn) {
      exportBtn.addEventListener('click', () => this.exportReport());
    }

    if (typeSelect) {
      typeSelect.addEventListener('change', () => {
        // Limpar relatório anterior quando mudar tipo
        if (this.currentReport) {
          document.getElementById('export-report-btn').disabled = true;
        }
      });
    }
  }

  async generateReport() {
    const reportType = document.getElementById('report-type-select').value;
    const period = document.getElementById('report-period-select').value;

    if (!reportType) {
      alert('Por favor, selecione um tipo de relatório');
      return;
    }

    const container = document.getElementById('report-container');
    const generateBtn = document.getElementById('generate-report-btn');

    // Loading state
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando...';
    container.innerHTML = '<div class="text-center py-12"><i class="fas fa-spinner fa-spin text-3xl text-blue-600"></i></div>';

    try {
      const response = await fetch('/api/v1/reports/getReports.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          type: reportType,
          period: period
        })
      });

      const data = await response.json();

      if (data.success) {
        this.currentReport = reportType;
        this.currentData = data.data;
        this.displayReport(reportType, data.data);
        document.getElementById('export-report-btn').disabled = false;
      } else {
        container.innerHTML = `
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
            <i class="fas fa-exclamation-circle mr-2"></i>
            ${data.message || 'Erro ao gerar relatório'}
          </div>
        `;
      }
    } catch (error) {
      container.innerHTML = `
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
          <i class="fas fa-exclamation-circle mr-2"></i>
          Erro ao processar relatório: ${error.message}
        </div>
      `;
    } finally {
      generateBtn.disabled = false;
      generateBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Gerar Relatório';
    }
  }

  displayReport(type, data) {
    const container = document.getElementById('report-container');

    switch (type) {
      case 'inventory':
        this.displayInventoryReport(container, data);
        break;
      case 'leads':
        this.displayLeadsReport(container, data);
        break;
      case 'sales':
        this.displaySalesReport(container, data);
        break;
      case 'financial':
        this.displayFinancialReport(container, data);
        break;
      case 'users':
        this.displayUsersReport(container, data);
        break;
      case 'properties-status':
        this.displayPropertiesStatusReport(container, data);
        break;
      default:
        container.innerHTML = '<p class="text-gray-500">Tipo de relatório não reconhecido</p>';
    }
  }

  displayInventoryReport(container, data) {
    if (!data.properties || !Array.isArray(data.properties)) {
      container.innerHTML = '<p class="text-gray-500">Sem dados disponíveis</p>';
      return;
    }

    const stats = data.stats || {};
    const properties = data.properties;

    let html = `
      <div class="mb-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Resumo do Inventário</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
            <p class="text-gray-600 text-sm font-medium">Total de Imóveis</p>
            <p class="text-3xl font-bold text-blue-600">${stats.total || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
            <p class="text-gray-600 text-sm font-medium">Imóveis Ativos</p>
            <p class="text-3xl font-bold text-green-600">${stats.active || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
            <p class="text-gray-600 text-sm font-medium">Imóveis Vendidos</p>
            <p class="text-3xl font-bold text-yellow-600">${stats.sold || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-red-50 to-red-100 p-4 rounded-lg border border-red-200">
            <p class="text-gray-600 text-sm font-medium">Imóveis Indisponíveis</p>
            <p class="text-3xl font-bold text-red-600">${stats.inactive || 0}</p>
          </div>
        </div>
      </div>

      <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Detalhes dos Imóveis</h3>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-gray-100 border-b-2 border-gray-300">
                <th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Endereço</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Tipo</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Preço</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Corretor</th>
              </tr>
            </thead>
            <tbody>
              ${properties.slice(0, 50).map(prop => `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                  <td class="px-4 py-3 text-gray-600">${prop.id || 'N/A'}</td>
                  <td class="px-4 py-3 text-gray-600">${prop.address || 'N/A'}</td>
                  <td class="px-4 py-3 text-gray-600">${prop.property_type || 'N/A'}</td>
                  <td class="px-4 py-3 text-gray-600 font-medium">R$ ${(prop.price || 0).toLocaleString('pt-BR')}</td>
                  <td class="px-4 py-3">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${this.getStatusClass(prop.status)}">
                      ${this.getStatusLabel(prop.status)}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-gray-600">${prop.assigned_user || 'N/A'}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
        ${properties.length > 50 ? `<p class="mt-4 text-sm text-gray-500">Exibindo 50 de ${properties.length} imóveis</p>` : ''}
      </div>
    `;

    container.innerHTML = html;
  }

  displayLeadsReport(container, data) {
    if (!data.leads || !Array.isArray(data.leads)) {
      container.innerHTML = '<p class="text-gray-500">Sem dados disponíveis</p>';
      return;
    }

    const stats = data.stats || {};
    const leads = data.leads;

    let html = `
      <div class="mb-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Resumo de Leads</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
            <p class="text-gray-600 text-sm font-medium">Total de Leads</p>
            <p class="text-3xl font-bold text-blue-600">${stats.total || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
            <p class="text-gray-600 text-sm font-medium">Novos (7 dias)</p>
            <p class="text-3xl font-bold text-purple-600">${stats.new || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
            <p class="text-gray-600 text-sm font-medium">Convertidos</p>
            <p class="text-3xl font-bold text-green-600">${stats.converted || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg border border-orange-200">
            <p class="text-gray-600 text-sm font-medium">Taxa Conversão</p>
            <p class="text-3xl font-bold text-orange-600">${stats.conversion_rate || '0'}%</p>
          </div>
        </div>
      </div>

      <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Detalhes dos Leads</h3>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-gray-100 border-b-2 border-gray-300">
                <th class="px-4 py-3 text-left font-semibold text-gray-700">ID</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Nome</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Email</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Telefone</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Data</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Status</th>
              </tr>
            </thead>
            <tbody>
              ${leads.slice(0, 50).map(lead => `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                  <td class="px-4 py-3 text-gray-600">${lead.id || 'N/A'}</td>
                  <td class="px-4 py-3 text-gray-600">${lead.name || 'N/A'}</td>
                  <td class="px-4 py-3 text-gray-600">${lead.email || 'N/A'}</td>
                  <td class="px-4 py-3 text-gray-600">${lead.phone || 'N/A'}</td>
                  <td class="px-4 py-3 text-gray-600">${lead.date ? new Date(lead.date).toLocaleDateString('pt-BR') : 'N/A'}</td>
                  <td class="px-4 py-3">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold ${this.getLeadStatusClass(lead.status)}">
                      ${this.getLeadStatusLabel(lead.status)}
                    </span>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
        ${leads.length > 50 ? `<p class="mt-4 text-sm text-gray-500">Exibindo 50 de ${leads.length} leads</p>` : ''}
      </div>
    `;

    container.innerHTML = html;
  }

  displaySalesReport(container, data) {
    const stats = data.stats || {};

    let html = `
      <div class="mb-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Pipeline de Vendas</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
            <p class="text-gray-600 text-sm font-medium">Negociações Ativas</p>
            <p class="text-3xl font-bold text-blue-600">${stats.active_negotiations || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
            <p class="text-gray-600 text-sm font-medium">Vendas Fechadas</p>
            <p class="text-3xl font-bold text-green-600">${stats.closed_sales || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
            <p class="text-gray-600 text-sm font-medium">Valor Total</p>
            <p class="text-2xl font-bold text-yellow-600">R$ ${(stats.total_value || 0).toLocaleString('pt-BR')}</p>
          </div>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Status do Pipeline</h3>
        <p class="text-gray-500 text-center py-8">
          <i class="fas fa-info-circle mr-2"></i>
          Dados de pipeline indisponíveis no momento
        </p>
      </div>
    `;

    container.innerHTML = html;
  }

  displayFinancialReport(container, data) {
    const stats = data.stats || {};
    const transactions = data.transactions || [];
    const charts = data.charts || {};
    // Derivar total de comissões a partir das transações (garante consistência)
    const derivedCommissions = transactions.reduce((sum, t) => {
      return this.isComissao(t.tipo) ? sum + (parseFloat(t.valor) || 0) : sum;
    }, 0);
    const netProfit = Number(stats.net_profit || 0);
    const netIsNegative = netProfit < 0;
    const rowsHtml = this.renderTransactionRows(transactions);

    let html = `
      <div class="mb-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Resumo Financeiro</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
            <p class="text-gray-600 text-sm font-medium">Receita Total</p>
            <p class="text-2xl font-bold text-green-600">R$ ${(stats.total_revenue || 0).toLocaleString('pt-BR')}</p>
          </div>
          <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg border border-orange-200">
            <p class="text-gray-600 text-sm font-medium">Comissões</p>
            <p class="text-2xl font-bold text-orange-600">R$ ${derivedCommissions.toLocaleString('pt-BR')}</p>
          </div>
          <div class="bg-gradient-to-br from-red-50 to-red-100 p-4 rounded-lg border border-red-200">
            <p class="text-gray-600 text-sm font-medium">Despesas</p>
            <p class="text-2xl font-bold text-red-600">R$ ${(stats.total_expenses || 0).toLocaleString('pt-BR')}</p>
          </div>
          <div class="bg-gradient-to-br ${netIsNegative ? 'from-red-50 to-red-100' : 'from-blue-50 to-blue-100'} p-4 rounded-lg border ${netIsNegative ? 'border-red-200' : 'border-blue-200'}">
            <p class="text-gray-600 text-sm font-medium">Lucro Líquido</p>
            <p class="text-2xl font-bold ${netIsNegative ? 'text-red-600' : 'text-blue-600'}">R$ ${netProfit.toLocaleString('pt-BR')}</p>
          </div>
        </div>
      </div>

      <div class="mb-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white border border-gray-200 rounded-lg p-6">
          <div class="flex items-start justify-between mb-4">
            <h4 class="text-lg font-semibold">Transações</h4>
            <div class="flex items-center space-x-3">
              <label class="flex items-center text-sm text-gray-700">
                <select id="filter-report-kind" class="border border-gray-200 rounded px-2 py-1 text-sm bg-white">
                  <option value="all">Todos</option>
                  <option value="despesa">Apenas Despesas</option>
                  <option value="comissao">Apenas Comissões</option>
                  <option value="receita">Apenas Receitas</option>
                </select>
              </label>
              <div class="flex items-center space-x-2 text-sm">
                <span class="px-2 py-1 rounded text-xs bg-red-100 text-red-800">Despesa</span>
                <span class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-800">Comissão</span>
                <span class="px-2 py-1 rounded text-xs bg-green-100 text-green-800">Receita</span>
              </div>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
              <thead>
                <tr class="bg-gray-100 border-b-2 border-gray-300">
                  <th class="px-3 py-2 text-left font-semibold text-gray-700">ID</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-700">Corretor</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-700">Imóvel</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-700">Tipo</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-700">Categoria</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-700">Data</th>
                  <th class="px-3 py-2 text-right font-semibold text-gray-700">Valor</th>
                  <th class="px-3 py-2 text-left font-semibold text-gray-700">Status</th>
                </tr>
              </thead>
              <tbody id="transactions-tbody">
                  ${rowsHtml}
              </tbody>
            </table>
          </div>
          ${transactions.length > 200 ? `<p class="mt-3 text-sm text-gray-500">Exibindo 200 de ${transactions.length} transações</p>` : ''}
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-6">
          <h4 class="text-lg font-semibold mb-4">Análises</h4>
          <div class="mb-6">
            <canvas id="chart-by-category" height="180"></canvas>
          </div>
          <div>
            <canvas id="chart-time-series" height="140"></canvas>
          </div>
        </div>
      </div>
    `;

    container.innerHTML = html;

    // Wire filter select to re-render transaction rows and update charts
      const filterSelect = container.querySelector('#filter-report-kind');
      const tbody = container.querySelector('#transactions-tbody');
      const allTransactions = transactions;
      if (filterSelect && tbody) {
        filterSelect.addEventListener('change', (e) => {
          const val = e.target.value;
          let filtered = allTransactions;
          if (val === 'despesa') filtered = allTransactions.filter(t => this.isDespesa(t.tipo));
          else if (val === 'comissao') filtered = allTransactions.filter(t => this.isComissao(t.tipo));
          else if (val === 'receita') filtered = allTransactions.filter(t => this.isReceita(t.tipo));
          tbody.innerHTML = this.renderTransactionRows(filtered);
          // Update charts with filtered data
          this.updateChartsWithFiltered(filtered);
        });
      }

    // Renderizar gráficos se Chart estiver disponível
    try {
      this.updateChartsWithFiltered(transactions);
    } catch (e) {
      console.error('Erro ao renderizar gráficos:', e);
    }

    }

  displayUsersReport(container, data) {
    if (!data.users || !Array.isArray(data.users)) {
      container.innerHTML = '<p class="text-gray-500">Sem dados disponíveis</p>';
      return;
    }

    const users = data.users;

    let html = `
      <div>
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Desempenho de Usuários</h3>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-sm">
            <thead>
              <tr class="bg-gray-100 border-b-2 border-gray-300">
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Usuário</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Email</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-700">Imóveis</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-700">Leads</th>
                <th class="px-4 py-3 text-center font-semibold text-gray-700">Vendas</th>
                <th class="px-4 py-3 text-left font-semibold text-gray-700">Último Acesso</th>
              </tr>
            </thead>
            <tbody>
              ${users.map(user => `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                  <td class="px-4 py-3 text-gray-600 font-medium">${user.name || 'N/A'}</td>
                  <td class="px-4 py-3 text-gray-600">${user.email || 'N/A'}</td>
                  <td class="px-4 py-3 text-center text-gray-600">${user.properties_count || 0}</td>
                  <td class="px-4 py-3 text-center text-gray-600">${user.leads_count || 0}</td>
                  <td class="px-4 py-3 text-center text-gray-600 font-medium">${user.sales_count || 0}</td>
                  <td class="px-4 py-3 text-gray-600">${user.last_login ? new Date(user.last_login).toLocaleDateString('pt-BR') : 'Nunca'}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>
    `;

    container.innerHTML = html;
  }

  displayPropertiesStatusReport(container, data) {
    const stats = data.stats || {};

    let html = `
      <div class="mb-6">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Status dos Imóveis</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
            <p class="text-gray-600 text-sm font-medium">Ativos</p>
            <p class="text-3xl font-bold text-green-600">${stats.active || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
            <p class="text-gray-600 text-sm font-medium">Inativos</p>
            <p class="text-3xl font-bold text-blue-600">${stats.inactive || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
            <p class="text-gray-600 text-sm font-medium">Vendidos</p>
            <p class="text-3xl font-bold text-yellow-600">${stats.sold || 0}</p>
          </div>
          <div class="bg-gradient-to-br from-red-50 to-red-100 p-4 rounded-lg border border-red-200">
            <p class="text-gray-600 text-sm font-medium">Bloqueados</p>
            <p class="text-3xl font-bold text-red-600">${stats.blocked || 0}</p>
          </div>
        </div>
      </div>

      <div class="bg-white border border-gray-200 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Distribuição por Status</h3>
        <p class="text-gray-500 text-center py-8">
          <i class="fas fa-chart-pie mr-2"></i>
          Gráfico de distribuição em desenvolvimento
        </p>
      </div>
    `;

    container.innerHTML = html;
  }

  exportReport() {
    if (!this.currentReport || !this.currentData) {
      alert('Nenhum relatório gerado para exportar');
      return;
    }

    const data = {
      report_type: this.currentReport,
      generated_at: new Date().toLocaleString('pt-BR'),
      data: this.currentData
    };

    const jsonString = JSON.stringify(data, null, 2);
    const blob = new Blob([jsonString], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `relatorio-${this.currentReport}-${Date.now()}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  getStatusClass(status) {
    switch (status?.toLowerCase()) {
      // Propriedades
      case 'ativo':
      case 'active':
        return 'bg-green-100 text-green-800';
      case 'inativo':
      case 'inactive':
        return 'bg-gray-100 text-gray-800';
      case 'vendido':
      case 'sold':
        return 'bg-blue-100 text-blue-800';

      // Status financeiros / transações
      case 'concluido':
      case 'concluído':
      case 'concluído':
      case 'completed':
        return 'bg-green-100 text-green-800';
      case 'pendente':
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'cancelado':
      case 'cancelled':
        return 'bg-red-100 text-red-800';
      case 'reembolsado':
      case 'reembolsar':
        return 'bg-purple-100 text-purple-800';

      default:
        return 'bg-gray-100 text-gray-800';
    }
  }

  getStatusLabel(status) {
    switch (status?.toLowerCase()) {
      case 'ativo':
      case 'active':
        return 'Ativo';
      case 'inativo':
      case 'inactive':
        return 'Inativo';
      case 'vendido':
      case 'sold':
        return 'Vendido';
      default:
        return status || 'Desconhecido';
    }
  }

  getLeadStatusClass(status) {
    switch (status?.toLowerCase()) {
      case 'novo':
      case 'new':
        return 'bg-blue-100 text-blue-800';
      case 'convertido':
      case 'converted':
        return 'bg-green-100 text-green-800';
      case 'perdido':
      case 'lost':
        return 'bg-red-100 text-red-800';
      case 'cancelado':
      case 'cancelled':
        return 'bg-red-100 text-red-800';
      case 'fechado':
      case 'closed':
        return 'bg-gray-100 text-gray-800';
      case 'contatado':
      case 'contacted':
        return 'bg-yellow-100 text-yellow-800';
      case 'pendente':
      case 'pending':
        return 'bg-orange-100 text-orange-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  }

  getLeadStatusLabel(status) {
    switch (status?.toLowerCase()) {
      case 'novo':
      case 'new':
        return 'Novo';
      case 'convertido':
      case 'converted':
        return 'Convertido';
      case 'perdido':
      case 'lost':
        return 'Perdido';
      case 'cancelado':
      case 'cancelled':
        return 'Cancelado';
      case 'fechado':
      case 'closed':
        return 'Fechado';
      case 'contatado':
      case 'contacted':
        return 'Contatado';
      case 'pendente':
      case 'pending':
        return 'Pendente';
      default:
        return status || 'Desconhecido';
    }
  }

    getTipoClass(tipo) {
      switch ((tipo||'').toLowerCase()) {
        case 'despesa':
        case 'expense':
          return 'bg-red-100 text-red-800';
        case 'comissao':
        case 'comissão':
        case 'commission':
          return 'bg-orange-100 text-orange-800';
        case 'receita':
        case 'revenue':
          return 'bg-green-100 text-green-800';
        default:
          return 'bg-gray-100 text-gray-800';
      }
    }

    isDespesa(tipo) {
      if (!tipo) return false;
      return /despesa|despeza|expense/i.test(tipo);
    }

    isComissao(tipo) {
      if (!tipo) return false;
      return /comiss|comissão|commission/i.test(tipo);
    }

    isReceita(tipo) {
      if (!tipo) return false;
      return /receita|revenue/i.test(tipo);
    }

    updateChartsWithFiltered(transactions) {
      // Recalculate chart data from filtered transactions
      const byCategory = this.aggregateByCategory(transactions);
      const timeSeries = this.aggregateTimeSeries(transactions);
      this.renderChartsWithData(byCategory, timeSeries);
    }

    aggregateByCategory(transactions) {
      const map = {};
      transactions.forEach(t => {
        const isDespesa = this.isDespesa(t.tipo);
        // Se for despesa, agrupar por tipo (categoria será "Despesa")
        // Se não for despesa, agrupar por categoria
        const groupKey = isDespesa ? `Despesa - ${t.tipo || 'Desconhecida'}` : (t.categoria || 'Sem categoria');
        if (!map[groupKey]) map[groupKey] = { total: 0, isDespesa: false, tipo: t.tipo };
        const valor = parseFloat(t.valor) || 0;
        // Despesas são negativas (saída de dinheiro)
        map[groupKey].total += isDespesa ? -valor : valor;
        if (isDespesa) map[groupKey].isDespesa = true;
      });
      return Object.entries(map).map(([label, data]) => ({ categoria: label, total: data.total, isDespesa: data.isDespesa }));
    }

    aggregateTimeSeries(transactions) {
      const map = {};
      transactions.forEach(t => {
        const date = t.data_transacao ? new Date(t.data_transacao).toLocaleDateString('pt-BR') : 'S/Data';
        if (!map[date]) map[date] = 0;
        const valor = parseFloat(t.valor) || 0;
        const isDespesa = this.isDespesa(t.tipo);
        // Despesas são negativas (saída de dinheiro)
        map[date] += isDespesa ? -valor : valor;
      });
      return Object.entries(map).map(([date, total]) => ({ date, total })).sort((a, b) => new Date(a.date) - new Date(b.date));
    }

    renderChartsWithData(byCategory, timeSeries) {
      try {
        if (window.Chart) {
          // Destroy existing chart instances to avoid conflicts
          if (window.chartByCategory) window.chartByCategory.destroy();
          if (window.chartTimeSeries) window.chartTimeSeries.destroy();

          // By category chart - bar chart with positive/negative values colored
          const ctxCat = document.getElementById('chart-by-category')?.getContext('2d');
          if (ctxCat) {
            const colors = byCategory.map(c => c.isDespesa ? '#EF4444' : ['#60A5FA','#34D399','#FBBF24','#F472B6','#A78BFA'][Math.random() * 5 | 0]);
            window.chartByCategory = new Chart(ctxCat, {
              type: 'bar',
              data: {
                labels: byCategory.map(c => c.categoria),
                datasets: [{
                  label: 'Valor (Receita + / Despesa -)',
                  data: byCategory.map(c => c.total),
                  backgroundColor: colors,
                  borderColor: colors,
                  borderWidth: 1
                }]
              },
              options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                  x: {
                    ticks: { callback: function(value) { return 'R$ ' + value.toLocaleString('pt-BR'); } }
                  }
                }
              }
            });
          }

          // Time series chart - line chart showing daily net with expenses reducing total
          const ctxTs = document.getElementById('chart-time-series')?.getContext('2d');
          if (ctxTs) {
            window.chartTimeSeries = new Chart(ctxTs, {
              type: 'line',
              data: {
                labels: timeSeries.map(s => s.date),
                datasets: [{
                  label: 'Fluxo por dia (Receita + / Despesa -)',
                  data: timeSeries.map(s => s.total),
                  borderColor: '#3B82F6',
                  backgroundColor: 'rgba(59,130,246,0.08)',
                  fill: true,
                  pointBackgroundColor: timeSeries.map(s => s.total < 0 ? '#EF4444' : '#3B82F6'),
                  pointBorderColor: timeSeries.map(s => s.total < 0 ? '#EF4444' : '#3B82F6')
                }]
              },
              options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                  x: { display: true },
                  y: { display: true, ticks: { callback: function(value) { return 'R$ ' + value.toLocaleString('pt-BR'); } } }
                }
              }
            });
          }
        }
      } catch (e) {
        console.error('Erro ao atualizar gráficos:', e);
      }
    }

    renderTransactionRows(transactions) {
      return transactions.slice(0,200).map(t => `
                    <tr class="border-b border-gray-200 ${this.isDespesa(t.tipo) ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-gray-50'}">
                      <td class="px-3 py-2 text-gray-600">${t.id}</td>
                      <td class="px-3 py-2 text-gray-600">${t.user_name || 'N/A'}</td>
                      <td class="px-3 py-2 text-gray-600">${t.property_title || '—'}</td>
                      <td class="px-3 py-2 text-gray-600"><span class="px-2 py-1 rounded text-sm font-medium ${this.getTipoClass(t.tipo)}">${t.tipo || '—'}</span></td>
                      <td class="px-3 py-2 text-gray-600">${t.categoria || '—'}</td>
                      <td class="px-3 py-2 text-gray-600">${t.data_transacao ? new Date(t.data_transacao).toLocaleDateString('pt-BR') : '—'}</td>
                      <td class="${this.isDespesa(t.tipo) ? 'px-3 py-2 text-right font-bold' : 'px-3 py-2 text-right text-gray-700 font-medium'}">
                        <span ${this.isDespesa(t.tipo) ? 'style="color:#b91c1c"' : ''}>R$ ${(t.valor || 0).toLocaleString('pt-BR')}</span>
                      </td>
                      <td class="px-3 py-2"><span class="px-2 py-1 rounded-full text-xs font-semibold ${this.isDespesa(t.tipo) ? 'bg-red-100 text-red-800' : this.getStatusClass(t.status)}">${t.status || '—'}</span></td>
                    </tr>
                  `).join('');
    }
}

// Inicializar quando o documento estiver carregado
document.addEventListener('DOMContentLoaded', () => {
  new ReportsManager();
});
