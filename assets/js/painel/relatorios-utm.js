/**
 * UTM Analytics Report
 * Gerencia visualização de análises de campanhas UTM
 */

// Dados em cache para não fazer múltiplas requisições
let utmAnalyticsCache = null;

async function carregarAnaliticsUTM() {
  const periodo = document.getElementById('utm-periodo')?.value || '30';
  const campaignId = document.getElementById('utm-campaign-filter')?.value || '';

  try {
    const url = new URL('/api/v1/utm/getAnalytics.php', window.location.origin);
    url.searchParams.append('days', periodo);
    if (campaignId) url.searchParams.append('campaign_id', campaignId);

    const response = await fetch(url.toString());
    const data = await response.json();

    if (!data.success) {
      console.error('Erro ao carregar analytics:', data.error);
      alert('Erro ao carregar dados: ' + data.error);
      return;
    }

    utmAnalyticsCache = data.data;
    renderizarAnaliticsUTM(data.data);
  } catch (err) {
    console.error('Erro ao carregar analytics UTM:', err);
    alert('Erro ao carregar relatório UTM');
  }
}

function renderizarAnaliticsUTM(analytics) {
  // KPIs
  document.getElementById('utm-total-clicks').textContent = 
    (analytics.summary?.total_clicks || 0).toLocaleString('pt-BR');
  
  document.getElementById('utm-unique-visitors').textContent = 
    (analytics.summary?.unique_visitors || 0).toLocaleString('pt-BR');
  
  document.getElementById('utm-conversions').textContent = 
    (analytics.summary?.total_conversions || 0).toLocaleString('pt-BR');
  
  const convRate = ((analytics.summary?.conversion_rate || 0) * 100).toFixed(2);
  document.getElementById('utm-conversion-rate').textContent = convRate + '%';

  // Gráficos
  renderizarGraficoSource(analytics.by_source);
  renderizarGraficoMedium(analytics.by_medium);
  renderizarGraficoTimeline(analytics.timeline);

  // Tabela de campanhas
  renderizarTabelaCampanhas(analytics.by_campaign);

  // Carregar campanhas no filtro
  carregarCampanhasNoFiltro(analytics.by_campaign);
}

function renderizarGraficoSource(bySource) {
  const container = document.getElementById('utm-chart-source');
  if (!container || !bySource) return;

  const labels = Object.keys(bySource);
  const data = Object.values(bySource);
  const cores = ['#111827', '#EF4444', '#10B981', '#D97706', '#8B5CF6', '#EC4899'];

  container.innerHTML = '';
  
  // Criar gráfico simples com canvas
  const canvas = document.createElement('canvas');
  container.appendChild(canvas);
  const ctx = canvas.getContext('2d');

  // Simular gráfico com SVG ou canvas
  const maxValue = Math.max(...data);
  const height = 300;
  const barWidth = container.offsetWidth / (labels.length + 1);

  const html = `
    <div class="flex h-80 items-end justify-around gap-2">
      ${labels.map((label, idx) => {
        const barHeight = (data[idx] / maxValue) * 250;
        return `
          <div class="flex flex-col items-center">
            <div class="text-sm font-semibold text-gray-800 mb-2">${data[idx]}</div>
            <div class="bg-blue-500 rounded-t" style="width: 40px; height: ${barHeight}px; background-color: ${cores[idx % cores.length]};" title="${label}"></div>
            <div class="text-xs text-gray-600 mt-2 text-center">${label}</div>
          </div>
        `;
      }).join('')}
    </div>
  `;

  container.innerHTML = html;
}

function renderizarGraficoMedium(byMedium) {
  const container = document.getElementById('utm-chart-medium');
  if (!container || !byMedium) return;

  const labels = Object.keys(byMedium);
  const data = Object.values(byMedium);
  const cores = ['#111827', '#EF4444', '#10B981', '#D97706', '#8B5CF6'];

  container.innerHTML = '';

  const html = `
    <div class="flex h-80 items-end justify-around gap-2">
      ${labels.map((label, idx) => {
        const maxValue = Math.max(...data);
        const barHeight = (data[idx] / maxValue) * 250;
        return `
          <div class="flex flex-col items-center">
            <div class="text-sm font-semibold text-gray-800 mb-2">${data[idx]}</div>
            <div class="rounded-t" style="width: 40px; height: ${barHeight}px; background-color: ${cores[idx % cores.length]};" title="${label}"></div>
            <div class="text-xs text-gray-600 mt-2 text-center">${label}</div>
          </div>
        `;
      }).join('')}
    </div>
  `;

  container.innerHTML = html;
}

function renderizarGraficoTimeline(timeline) {
  const container = document.getElementById('utm-chart-timeline');
  if (!container || !timeline) return;

  const labels = Object.keys(timeline).sort();
  const data = labels.map(d => timeline[d]);
  const maxValue = Math.max(...data);

  container.innerHTML = '';

  const html = `
    <div class="flex h-80 items-end justify-between gap-1 p-4">
      ${labels.map((label, idx) => {
        const barHeight = (data[idx] / maxValue) * 250;
        const date = new Date(label);
        const dayLabel = date.toLocaleDateString('pt-BR', { month: 'short', day: 'numeric' });
        return `
          <div class="flex flex-col items-center flex-1">
            <div class="text-xs font-semibold text-gray-700 mb-2">${data[idx]}</div>
            <div class="bg-green-500 rounded-t w-full" style="height: ${barHeight}px; opacity: 0.8;"></div>
            <div class="text-xs text-gray-600 mt-2 text-center w-full">${dayLabel}</div>
          </div>
        `;
      }).join('')}
    </div>
  `;

  container.innerHTML = html;
}

function renderizarTabelaCampanhas(byCampaign) {
  const tbody = document.getElementById('utm-campaigns-table');
  if (!tbody || !byCampaign) return;

  if (Object.keys(byCampaign).length === 0) {
    tbody.innerHTML = `
      <tr class="text-center py-8">
        <td colspan="5" class="text-gray-500 py-8">Nenhuma campanha com dados</td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = Object.entries(byCampaign).map(([campaignName, stats]) => `
    <tr class="hover:bg-gray-50">
      <td class="px-6 py-4 text-sm font-medium text-gray-800">${campaignName}</td>
      <td class="px-6 py-4 text-sm text-gray-700">${stats.total_clicks || 0}</td>
      <td class="px-6 py-4 text-sm text-gray-700">${stats.unique_visitors || 0}</td>
      <td class="px-6 py-4 text-sm text-gray-700">${stats.conversions || 0}</td>
      <td class="px-6 py-4 text-sm">
        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold">
          ${((stats.conversion_rate || 0) * 100).toFixed(2)}%
        </span>
      </td>
    </tr>
  `).join('');
}

function carregarCampanhasNoFiltro(byCampaign) {
  const select = document.getElementById('utm-campaign-filter');
  if (!select || !byCampaign) return;

  const campanhas = Object.keys(byCampaign);
  const currentValue = select.value;

  // Adicionar opções de campanhas (mantendo a opção "Todas")
  const novasOpcoes = campanhas.map(c => `<option value="">${c}</option>`).join('');
  
  // Preservar a opção "Todas as campanhas"
  select.innerHTML = '<option value="">Todas as campanhas</option>' + novasOpcoes;
  select.value = currentValue;
}

// Carregar analytics quando a aba for aberta
document.addEventListener('DOMContentLoaded', () => {
  const observer = new MutationObserver(() => {
    const tab = document.getElementById('utm-analytics-content');
    if (tab && tab.style.display !== 'none' && !utmAnalyticsCache) {
      carregarAnaliticsUTM();
    }
  });

  observer.observe(document.body, { attributes: true, subtree: true });
});

// Escutar mudanças nos filtros
document.addEventListener('DOMContentLoaded', () => {
  const periodoSelect = document.getElementById('utm-periodo');
  if (periodoSelect) {
    periodoSelect.addEventListener('change', carregarAnaliticsUTM);
  }

  const campaignSelect = document.getElementById('utm-campaign-filter');
  if (campaignSelect) {
    campaignSelect.addEventListener('change', carregarAnaliticsUTM);
  }
});
