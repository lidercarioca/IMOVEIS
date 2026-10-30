/**
 * Módulo de Relatórios - Painel Administrativo
 * Gerencia carregamento e exibição de relatórios
 */

// Armazena relatórios carregados atualmente
let relatoriosAtivos = new Set();

document.addEventListener('DOMContentLoaded', function () {
    // Verifica se a seção de relatórios existe (usuário tem acesso)
    const periodoSelect = document.getElementById('relatorio-periodo');
    if (!periodoSelect) {
        return; // Usuário comum não tem acesso aos relatórios
    }

    // Inicializa eventos da aba Relatórios
    inicializarRelatorios();
    carregarFiltros();
});

/**
 * Inicializa eventos da aba Relatórios
 */
function inicializarRelatorios() {
    // Event listener para mudança de período
    const periodoSelect = document.getElementById('relatorio-periodo');
    if (periodoSelect) {
        periodoSelect.addEventListener('change', function () {
            mostrarOcultarCamposData(this.value);
            atualizarTodosRelatorios();
        });
    }

    // Event listener para campos de data
    const dataInicio = document.getElementById('relatorio-data-inicio');
    const dataFim = document.getElementById('relatorio-data-fim');
    if (dataInicio) {
        dataInicio.addEventListener('change', function () {
            atualizarTodosRelatorios();
        });
    }
    if (dataFim) {
        dataFim.addEventListener('change', function () {
            atualizarTodosRelatorios();
        });
    }

    // Event listener para filtro de corretor
    const corretorSelect = document.getElementById('relatorio-corretor');
    if (corretorSelect) {
        corretorSelect.addEventListener('change', function () {
            atualizarTodosRelatorios();
        });
    }

    // Event listener para filtro de status
    const statusSelect = document.getElementById('relatorio-status');
    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            atualizarTodosRelatorios();
        });
    }

    // Event listener para filtro de tipo
    const tipoSelect = document.getElementById('relatorio-tipo');
    if (tipoSelect) {
        tipoSelect.addEventListener('change', function () {
            atualizarTodosRelatorios();
        });
    }

    // Event delegation para botões "Visualizar"
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-relatorio-id]');
        if (btn) {
            const relatorioId = btn.getAttribute('data-relatorio-id');
            abrirDetalhesRelatorio(relatorioId);
        }
    });

    // Event delegation para botões de exportação
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-export-csv]')) {
            const relatorioId = e.target
                .closest('[data-export-csv]')
                .getAttribute('data-export-csv');
            exportarRelatorioCSV(relatorioId);
        }
        if (e.target.closest('[data-export-pdf]')) {
            const relatorioId = e.target
                .closest('[data-export-pdf]')
                .getAttribute('data-export-pdf');
            exportarRelatorioPDF(relatorioId);
        }
    });
}

/**
 * Atualiza todos os relatórios com os filtros atuais
 */
function atualizarTodosRelatorios() {
    // Lista de todos os relatórios disponíveis
    const todosRelatorios = [
        'imoveis-status',
        'imoveis-tipo',
        'imoveis-bairro',
        'imoveis-preco',
        'imoveis-parados',
        'imoveis-vendidos',
        'leads-origem',
        'leads-status',
        'leads-conversao',
        'leads-perdidos',
        'leads-tempo',
        'corretor-leads',
        'corretor-vendas',
        'corretor-conversao',
        'corretor-ranking',
        'vendas-periodo',
        'vendas-tipo',
        'vendas-ticket',
        'vendas-receita',
        'vendas-ciclo',
        'vendas-pipeline',
    ];

    todosRelatorios.forEach((relatorio) => {
        carregarRelatorio(relatorio);
    });
}

/**
 * Mostra/esconde campos de data customizada baseado no período selecionado
 */
function mostrarOcultarCamposData(periodo) {
    const dataIniciDiv = document.getElementById('relatorio-data-inicio-div');
    const dataFimDiv = document.getElementById('relatorio-data-fim-div');

    if (periodo === 'custom') {
        if (dataIniciDiv) dataIniciDiv.style.display = 'block';
        if (dataFimDiv) dataFimDiv.style.display = 'block';
    } else {
        if (dataIniciDiv) dataIniciDiv.style.display = 'none';
        if (dataFimDiv) dataFimDiv.style.display = 'none';
    }
}

/**
 * Carrega os filtros (corretores, tipos, etc)
 */
async function carregarFiltros() {
    try {
        // Carrega corretores para o filtro
        const responseCorretores = await apiCall('/api/v1/users/index.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = responseCorretores.success ? responseCorretores.data : null;
        // Suporta ambos os formatos: array direto ou { success: true, data: [...] }
        const usuarios = Array.isArray(payload)
            ? payload
            : payload && Array.isArray(payload.data)
              ? payload.data
              : [];

        const selectCorretor = document.getElementById('relatorio-corretor');
        if (selectCorretor && usuarios && Array.isArray(usuarios)) {
            const corretores = usuarios.filter((user) => {
                return user.is_superadmin !== 1 && user.is_superadmin !== '1';
            });

            corretores.forEach((user) => {
                const option = document.createElement('option');
                option.value = user.id || user.user_id;
                option.textContent = user.name || user.username;
                selectCorretor.appendChild(option);
            });
        }

        // Carrega tipos de imóvel
        const responseTipos = await apiCall('/api/v1/properties/getPropertiesCategories.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payloadTipos = responseTipos.success ? responseTipos.data : null;
        const tipos = Array.isArray(payloadTipos)
            ? payloadTipos
            : payloadTipos && Array.isArray(payloadTipos.data)
              ? payloadTipos.data
              : [];

        const selectTipo = document.getElementById('relatorio-tipo');
        if (selectTipo && tipos && Array.isArray(tipos)) {
            tipos.forEach((tipo) => {
                const option = document.createElement('option');
                option.value = tipo.value || tipo;
                option.textContent = tipo.name || tipo;
                selectTipo.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erro ao carregar filtros:', error);
    }
}

/**
 * Carrega um relatório específico
 * @param {string} relatorioId - ID do relatório a carregar
 */
async function carregarRelatorio(relatorioId) {
    // Mapeia o ID do relatório para o endpoint e elementos (DEVE estar no início)
    const mapeamento = {
        'imoveis-status': {
            endpoint: '/api/relatorios/getPropertiesByStatus.php',
            preview: 'imoveis-status-preview',
        },
        'imoveis-tipo': {
            endpoint: '/api/relatorios/getPropertiesByType.php',
            preview: 'imoveis-tipo-preview',
        },
        'imoveis-bairro': {
            endpoint: '/api/relatorios/getPropertiesByNeighborhood.php',
            preview: 'imoveis-bairro-preview',
        },
        'imoveis-preco': {
            endpoint: '/api/relatorios/getPropertiesByPrice.php',
            preview: 'imoveis-preco-preview',
        },
        'imoveis-parados': {
            endpoint: '/api/relatorios/getPropertiesParados.php',
            preview: 'imoveis-parados-preview',
        },
        'imoveis-vendidos': {
            endpoint: '/api/relatorios/getPropertiesSoldRented.php',
            preview: 'imoveis-vendidos-preview',
        },
        'leads-origem': {
            endpoint: '/api/relatorios/getLeadsByOrigin.php',
            preview: 'leads-origem-preview',
        },
        'leads-status': {
            endpoint: '/api/relatorios/getLeadsByStatus.php',
            preview: 'leads-status-preview',
        },
        'leads-conversao': {
            endpoint: '/api/relatorios/getLeadsConversionRate.php',
            preview: 'leads-conversao-preview',
        },
        'leads-perdidos': {
            endpoint: '/api/relatorios/getLeadsLost.php',
            preview: 'leads-perdidos-preview',
        },
        'leads-tempo': {
            endpoint: '/api/relatorios/getLeadsAverageTime.php',
            preview: 'leads-tempo-preview',
        },
        'corretor-leads': {
            endpoint: '/api/relatorios/getLeadsByBroker.php',
            preview: 'corretor-leads-preview',
        },
        'corretor-vendas': {
            endpoint: '/api/relatorios/getSalesByBroker.php',
            preview: 'corretor-vendas-preview',
        },
        'corretor-conversao': {
            endpoint: '/api/relatorios/getBrokerConversionRate.php',
            preview: 'corretor-conversao-preview',
        },
        'corretor-ranking': {
            endpoint: '/api/relatorios/getBrokerRanking.php',
            preview: 'corretor-ranking-preview',
        },
        'vendas-periodo': {
            endpoint: '/api/relatorios/getSalesByPeriod.php',
            preview: 'vendas-periodo-preview',
        },
        'vendas-tipo': {
            endpoint: '/api/relatorios/getSalesByType.php',
            preview: 'vendas-tipo-preview',
        },
        'vendas-ticket': {
            endpoint: '/api/relatorios/getAverageTicket.php',
            preview: 'vendas-ticket-preview',
        },
        'vendas-receita': {
            endpoint: '/api/relatorios/getRevenueAndCommissions.php',
            preview: 'vendas-receita-preview',
        },
        'vendas-ciclo': {
            endpoint: '/api/relatorios/getSalesCycle.php',
            preview: 'vendas-ciclo-preview',
        },
        'vendas-pipeline': {
            endpoint: '/api/relatorios/getSalesPipeline.php',
            preview: 'vendas-pipeline-preview',
        },
    };

    const periodo = document.getElementById('relatorio-periodo')?.value || 'mes';
    const corretor = document.getElementById('relatorio-corretor')?.value || '';
    const status = document.getElementById('relatorio-status')?.value || '';
    const tipo = document.getElementById('relatorio-tipo')?.value || '';

    // Captura datas customizadas se período = 'custom'
    let dataInicio = '';
    let dataFim = '';
    if (periodo === 'custom') {
        dataInicio = document.getElementById('relatorio-data-inicio')?.value || '';
        dataFim = document.getElementById('relatorio-data-fim')?.value || '';

        // Se não tiver datas definidas, não carrega o relatório
        if (!dataInicio || !dataFim) {
            const previewId = Object.values(mapeamento).find((m) =>
                m.endpoint.includes(relatorioId)
            )?.preview;
            if (previewId) {
                document.getElementById(previewId).innerHTML =
                    '<p class="text-gray-500 text-sm">Selecione data início e fim</p>';
            }
            return;
        }
    }

    const config = mapeamento[relatorioId];
    if (!config) {
        console.error('Relatório não encontrado:', relatorioId);
        return;
    }

    const previewElement = document.getElementById(config.preview);
    if (previewElement) {
        previewElement.innerHTML =
            '<span class="text-gray-500 flex items-center"><i class="fas fa-spinner fa-spin mr-2"></i>Carregando...</span>';
    }

    try {
        const params = new URLSearchParams({
            periodo: periodo,
            corretor: corretor,
            status: status,
            tipo: tipo,
        });

        // Adiciona datas customizadas se aplicável
        if (periodo === 'custom') {
            params.append('data_inicio', dataInicio);
            params.append('data_fim', dataFim);
        }

        const response = await apiCall(`${config.endpoint}?${params}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.success) {
            throw new Error(response.error || `Erro HTTP ${response.status}`);
        }

        const dados = response.data;

        // Para pipeline, use o array de items dentro de dados.data
        let previewData = dados.data;
        if (relatorioId === 'vendas-pipeline' && dados.data && Array.isArray(dados.data.items)) {
            previewData = dados.data.items;
        }

        // Atualiza preview com dados do relatório
        if (previewElement && previewData) {
            const preview = formatarPreview(relatorioId, previewData);
            previewElement.innerHTML = preview;
            relatoriosAtivos.add(relatorioId);
        }
    } catch (error) {
        console.error('Erro ao carregar relatório:', error);
        if (previewElement) {
            previewElement.innerHTML = '<span class="text-red-500 text-sm">Erro ao carregar</span>';
        }
    }
}

/**
 * Formata a preview do relatório
 * @param {string} relatorioId - ID do relatório
 * @param {Array} dados - Dados do relatório
 * @returns {string} HTML da preview
 */
function formatarPreview(relatorioId, dados) {
    if (!dados || dados.length === 0) {
        return '<span class="text-gray-500">Sem dados</span>';
    }

    let preview = '';

    // Adapta formato baseado no tipo de relatório
    if (
        relatorioId.includes('status') ||
        relatorioId.includes('tipo') ||
        relatorioId.includes('origem') ||
        relatorioId.includes('bairro')
    ) {
        // Dados com contagem
        preview = dados
            .slice(0, 3)
            .map((item) => {
                const label =
                    item.label ||
                    item.status ||
                    item.type ||
                    item.origin ||
                    item.source ||
                    item.neighborhood ||
                    '';
                const valor = item.count || item.quantidade || 0;
                return `<div class="flex justify-between text-sm"><span class="truncate">${label}</span><strong class="ml-2">${valor}</strong></div>`;
            })
            .join('');
    } else if (relatorioId.includes('corretor-leads') || relatorioId.includes('corretor-vendas')) {
        // Leads/Vendas por corretor
        preview = dados
            .slice(0, 3)
            .map((item) => {
                const label = item.label || item.name || '';
                const count = item.count || item.vendas || item.quantidade || 0;
                return `<div class="flex justify-between text-sm"><span class="truncate">${label}</span><strong class="ml-2">${count}</strong></div>`;
            })
            .join('');
    } else if (relatorioId.includes('preco') || relatorioId.includes('ticket')) {
        // Dados monetários
        preview = dados
            .slice(0, 3)
            .map((item) => {
                const label = item.label || item.faixa || '';
                const valor = item.valor || item.amount || 0;
                return `<div class="flex justify-between text-sm"><span class="truncate">${label}</span><strong class="ml-2">R$ ${parseFloat(valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</strong></div>`;
            })
            .join('');
    } else if (relatorioId.includes('conversao')) {
        // Taxa de conversão
        preview = dados
            .slice(0, 3)
            .map((item) => {
                const label = item.label || item.name || '';
                const taxa = (item.taxa || item.rate || 0).toFixed(2);
                return `<div class="flex justify-between text-sm"><span class="truncate">${label}</span><strong class="ml-2">${taxa}%</strong></div>`;
            })
            .join('');
    } else if (relatorioId.includes('tempo') || relatorioId.includes('ciclo')) {
        // Tempo em dias
        preview = dados
            .slice(0, 3)
            .map((item) => {
                const label = item.label || item.name || '';
                const tempo = item.dias || item.days || 0;
                return `<div class="flex justify-between text-sm"><span class="truncate">${label}</span><strong class="ml-2">${tempo} dias</strong></div>`;
            })
            .join('');
    } else if (relatorioId.includes('ranking')) {
        // Ranking de corretores
        preview = dados
            .slice(0, 3)
            .map((item) => {
                const label = item.label || item.name || '';
                const valor = item.valor
                    ? `R$ ${parseFloat(item.valor).toLocaleString('pt-BR', { maximumFractionDigits: 0 })}`
                    : item.vendas || 0;
                return `<div class="flex justify-between text-sm"><span class="truncate">${label}</span><strong class="ml-2">${valor}</strong></div>`;
            })
            .join('');
    } else if (relatorioId.includes('periodo')) {
        // Por período
        preview = dados
            .slice(0, 3)
            .map((item) => {
                const label = item.label || item.mes || '';
                const count = item.count || item.quantidade || 0;
                return `<div class="flex justify-between text-sm"><span class="truncate">${label}</span><strong class="ml-2">${count}</strong></div>`;
            })
            .join('');
    } else {
        // Fallback genérico
        preview = dados
            .slice(0, 3)
            .map((item) => {
                const keys = Object.keys(item);
                const valor = item[keys[keys.length - 1]] || item[keys[0]] || '';
                const label = item.label || item[keys[0]] || '';
                return `<div class="flex justify-between text-sm"><span class="truncate">${label}</span><strong class="ml-2">${valor}</strong></div>`;
            })
            .join('');
    }

    return preview || '<span class="text-gray-500">Sem dados</span>';
}

/**
 * Abre a modal/detalhes completo de um relatório
 * @param {string} relatorioId - ID do relatório
 */
async function abrirDetalhesRelatorio(relatorioId) {
    const periodo = document.getElementById('relatorio-periodo')?.value || 'mes';
    const corretor = document.getElementById('relatorio-corretor')?.value || '';
    const status = document.getElementById('relatorio-status')?.value || '';
    const tipo = document.getElementById('relatorio-tipo')?.value || '';

    const mapeamento = {
        'imoveis-status': 'api/relatorios/getPropertiesByStatus.php',
        'imoveis-tipo': 'api/relatorios/getPropertiesByType.php',
        'imoveis-bairro': 'api/relatorios/getPropertiesByNeighborhood.php',
        'imoveis-preco': 'api/relatorios/getPropertiesByPrice.php',
        'imoveis-parados': 'api/relatorios/getPropertiesParados.php',
        'imoveis-vendidos': 'api/relatorios/getPropertiesSoldRented.php',
        'leads-origem': 'api/relatorios/getLeadsByOrigin.php',
        'leads-status': 'api/relatorios/getLeadsByStatus.php',
        'leads-conversao': 'api/relatorios/getLeadsConversionRate.php',
        'leads-perdidos': 'api/relatorios/getLeadsLost.php',
        'leads-tempo': 'api/relatorios/getLeadsAverageTime.php',
        'corretor-leads': 'api/relatorios/getLeadsByBroker.php',
        'corretor-vendas': 'api/relatorios/getSalesByBroker.php',
        'corretor-conversao': 'api/relatorios/getBrokerConversionRate.php',
        'corretor-ranking': 'api/relatorios/getBrokerRanking.php',
        'vendas-periodo': 'api/relatorios/getSalesByPeriod.php',
        'vendas-tipo': 'api/relatorios/getSalesByType.php',
        'vendas-ticket': 'api/relatorios/getAverageTicket.php',
        'vendas-receita': 'api/relatorios/getRevenueAndCommissions.php',
        'vendas-ciclo': 'api/relatorios/getSalesCycle.php',
        'vendas-pipeline': 'api/relatorios/getSalesPipeline.php',
    };

    const endpoint = mapeamento[relatorioId];
    if (!endpoint) {
        alert('Relatório não encontrado');
        return;
    }

    try {
        const params = new URLSearchParams({
            periodo: periodo,
            corretor: corretor,
            status: status,
            tipo: tipo,
        });

        const response = await apiCall(`${endpoint}?${params}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.success) {
            throw new Error(response.error || `Erro ao carregar dados: ${response.status}`);
        }

        const dados = response.data;

        // Para o pipeline, use .items
        let dataToDisplay = dados.data || [];
        if (relatorioId === 'vendas-pipeline' && dados.data && dados.data.items) {
            dataToDisplay = dados.data.items;
        }

        // Cria modal com os dados
        const modalHtml = `
      <div class="fixed bg-black bg-opacity-50 flex items-center justify-center" id="relatorio-modal" onclick="fecharDetalhesRelatorio()" style="z-index: 1040; left: 280px; right: 0; top: 0; bottom: 0;">
        <div class="bg-white rounded-lg shadow-lg w-11/12 max-h-[80vh] overflow-y-auto p-8" onclick="event.stopPropagation()">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-800">Detalhes do Relatório</h2>
            <button onclick="fecharDetalhesRelatorio()" class="text-gray-500 hover:text-gray-700">
              <i class="fas fa-times text-2xl"></i>
            </button>
          </div>
          <div id="relatorio-details-content">
            ${gerarTabelaRelatorio(relatorioId, dataToDisplay)}
          </div>
          <div class="mt-6 flex gap-4">
            <button onclick="imprimirRelatorio('${relatorioId}')" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
              <i class="fas fa-print mr-2"></i>Imprimir
            </button>
            <button onclick="exportarRelatorioCSV('${relatorioId}')" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
              <i class="fas fa-download mr-2"></i>Exportar CSV
            </button>
            <button onclick="exportarRelatorioPDF('${relatorioId}')" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
              <i class="fas fa-file-pdf mr-2"></i>Exportar PDF
            </button>
          </div>
        </div>
      </div>
    `;

        let modal = document.getElementById('relatorio-modal');
        if (modal) {
            modal.remove();
        }

        document.body.insertAdjacentHTML('beforeend', modalHtml);
    } catch (error) {
        alert('Erro ao carregar relatório: ' + error.message);
    }
}

/**
 * Gera tabela HTML para exibir dados do relatório
 * @param {string} relatorioId - ID do relatório
 * @param {Array} dados - Dados para a tabela
 * @returns {string} HTML da tabela
 */
function gerarTabelaRelatorio(relatorioId, dados) {
    if (!dados || dados.length === 0) {
        return '<p class="text-gray-500 text-center py-8">Sem dados para este relatório</p>';
    }

    const primeiroItem = dados[0];
    const colunas = Object.keys(primeiroItem).filter((coluna) => {
        const key = coluna.toString().toLowerCase();
        return !['id', 'lead_id', 'user_id', 'item_contact'].includes(key);
    });

    // Mapeamento de nomes de colunas para Português
    function traduzirColuna(coluna) {
        const key = coluna.toString().toLowerCase();
        const mapa = {
            id: 'ID',
            title: 'Título',
            label: 'Descrição',
            name: 'Nome',
            username: 'Usuário',
            email: 'E-mail',
            role: 'Função',
            count: 'Quantidade',
            quantidade: 'Quantidade',
            vendas: 'Vendas',
            valor: 'Valor',
            amount: 'Valor',
            price: 'Preço',
            preco: 'Preço',
            taxa: 'Taxa (%)',
            rate: 'Taxa (%)',
            days: 'Dias',
            dias: 'Dias',
            month: 'Mês',
            mes: 'Período',
            origin: 'Origem',
            tipo: 'Tipo',
            type: 'Tipo',
            neighborhood: 'Bairro',
            bairro: 'Bairro',
            created_at: 'Criado em',
            created: 'Criado em',
            updated_at: 'Atualizado em',
            date: 'Data',
            status: 'Status',
            broker: 'Corretor',
            user: 'Usuário',
            lead_id: 'ID Lead',
            property_id: 'ID Imóvel',
            imovel: 'Imóvel',
            user_id: 'ID Usuário',
            pipeline_stage_id: 'Estágio',
            value: 'Valor',
            expected_close_date: 'Data Prevista',
            notes: 'Notas',
            stage_name: 'Estágio',
            stage_color: 'Cor',
            item_title: 'Nome',
            item_contact: 'Contato',
            user_name: 'Responsável',
        };

        if (mapa[key]) return mapa[key];

        // Heurística: converte snake_case / camelCase para palavras e traduz termos comuns
        let human = coluna.replace(/_/g, ' ').replace(/([a-z])([A-Z])/g, '$1 $2');
        human = human.replace(
            /\b(id|name|email|status|type|price|amount|count|rate|days|month|origin|broker)\b/gi,
            function (m) {
                const low = m.toLowerCase();
                return mapa[low] || m;
            }
        );
        // Capitaliza primeira letra
        return human.charAt(0).toUpperCase() + human.slice(1);
    }

    let html = '<div class="overflow-x-auto"><table class="min-w-full border-collapse"><thead><tr>';
    colunas.forEach((coluna) => {
        html += `<th class="border border-gray-300 bg-gray-100 px-4 py-2 text-left text-sm font-semibold whitespace-nowrap">${traduzirColuna(coluna)}</th>`;
    });
    html += '</tr></thead><tbody>';

    dados.forEach((linha) => {
        html += '<tr>';
        colunas.forEach((coluna) => {
            let valor = linha[coluna];

            // Formatação de células: moeda, percentual, dias
            const chave = coluna.toString().toLowerCase();
            if (valor === null || valor === undefined || valor === '') {
                valor = '-';
            } else if (
                chave.includes('valor') ||
                chave.includes('amount') ||
                chave.includes('price') ||
                chave.includes('preco')
            ) {
                const num = parseFloat(valor) || 0;
                valor = `R$ ${num.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
            } else if (chave.includes('taxa') || chave.includes('rate')) {
                const num = parseFloat(valor) || 0;
                valor = `${num.toFixed(2)}%`;
            } else if (chave.includes('dias') || chave.includes('days')) {
                valor = `${valor} dias`;
            }

            html += `<td class="border border-gray-300 px-4 py-2 text-sm whitespace-nowrap">${valor}</td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table></div>';
    return html;
}

/**
 * Imprime o relatório
 * @param {string} relatorioId - ID do relatório
 */
function imprimirRelatorio(relatorioId) {
    const conteudo = document.getElementById('relatorio-details-content');
    if (!conteudo) {
        alert('Erro ao imprimir relatório');
        return;
    }

    // Cria janela de impressão
    const janelaImpressao = window.open('', '', 'width=900,height=600');
    janelaImpressao.document.write(`
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Relatório</title>
      <style>
        body {
          font-family: Arial, sans-serif;
          margin: 20px;
          background-color: #fff;
        }
        h1 {
          text-align: center;
          color: #333;
          margin-bottom: 30px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          margin-top: 20px;
        }
        th {
          background-color: #f0f0f0;
          border: 1px solid #ddd;
          padding: 12px;
          text-align: left;
          font-weight: bold;
          color: #333;
        }
        td {
          border: 1px solid #ddd;
          padding: 10px;
          color: #555;
        }
        tr:nth-child(even) {
          background-color: #f9f9f9;
        }
        .rodape {
          margin-top: 40px;
          text-align: center;
          font-size: 12px;
          color: #999;
        }
        @media print {
          body { margin: 0; }
          .rodape { page-break-before: avoid; }
        }
      </style>
    </head>
    <body>
      <h1>Relatório - ${relatorioId.replace(/-/g, ' ').toUpperCase()}</h1>
      <div>
        ${conteudo.innerHTML}
      </div>
      <div class="rodape">
        Impresso em ${new Date().toLocaleString('pt-BR')}
      </div>
    </body>
    </html>
  `);
    janelaImpressao.document.close();

    // Aguarda um pouco para garantir que o conteúdo foi carregado
    setTimeout(() => {
        janelaImpressao.print();
    }, 250);
}

/**
 * Fecha a modal de detalhes do relatório
 */
function fecharDetalhesRelatorio() {
    const modal = document.getElementById('relatorio-modal');
    if (modal) {
        modal.remove();
    }
}

/**
 * Exporta relatório para CSV
 * @param {string} relatorioId - ID do relatório
 */
function exportarRelatorioCSV(relatorioId) {
    alert(`Exportar ${relatorioId} para CSV - Em desenvolvimento`);
    // TODO: Implementar exportação CSV
}

/**
 * Exporta relatório para PDF
 * @param {string} relatorioId - ID do relatório
 */
function exportarRelatorioPDF(relatorioId) {
    alert(`Exportar ${relatorioId} para PDF - Em desenvolvimento`);
    // TODO: Implementar exportação PDF
}
