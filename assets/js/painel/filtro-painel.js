// Variáveis globais para os filtros

let currentFilters = {
    tipo: '',
    bairro: '',
    preco: '',
    busca: ''
};

// Mapeamento de tipos em português (escopo global)
const tiposMap = {
    'house': 'Casa',
    'apartment': 'Apartamento',
    'commercial': 'Comercial',
    'land': 'Terreno'
};

// Função para sanitizar strings
function sanitizarString(str) {
    if (!str) return '';
    return String(str).trim();
}

// Função para sanitizar preços
function sanitizarPreco(preco) {
    try {
        if (!preco) return 0;
        const precoStr = String(preco).replace(/[^\d,.-]/g, '').replace(',', '.');
        const precoNum = parseFloat(precoStr);
        return isNaN(precoNum) ? 0 : precoNum;
    } catch (erro) {
        console.warn('Erro ao sanitizar preço:', erro);
        return 0;
    }
}

// Função para validação de imóvel
function isImovelValido(imovel) {
    return (
        imovel &&
        typeof imovel === 'object' &&
        imovel.id &&
        imovel.type &&
        typeof imovel.type === 'string' &&
        imovel.neighborhood &&
        typeof imovel.neighborhood === 'string'
    );
}

// Função para mostrar feedback visual de erros
function mostrarErroValidacao(mensagem) {
    const container = document.getElementById('properties-container');
    if (!container) return;

    const alertaExistente = document.getElementById('erro-filtro');
    if (alertaExistente) {
        alertaExistente.remove();
    }

    const alerta = document.createElement('div');
    alerta.id = 'erro-filtro';
    alerta.className = 'alert alert-danger mb-4';
    alerta.innerHTML = `
        <i class="fas fa-exclamation-circle me-2"></i>
        ${mensagem}
    `;
    container.parentElement.insertBefore(alerta, container);

    setTimeout(() => {
        if (document.getElementById('erro-filtro')) {
            document.getElementById('erro-filtro').remove();
        }
    }, 5000);
}

// Função para registrar logs
function registrarLog(tipo, mensagem, dados = {}) {
    console.log(`[${tipo}] ${mensagem}`, dados);
}

// Função para renderizar os imóveis
function renderizarImoveis(imoveisFiltrados) {
    const container = document.getElementById('properties-container');
    if (!container) return;

    // Remove alerta anterior se existir
    const alertaExistente = document.getElementById('erro-filtro');
    if (alertaExistente) {
        alertaExistente.remove();
    }

    // Atualiza o array global de imóveis com os resultados filtrados
    imoveisFiltrados = imoveisFiltrados || [];
    window.todasAsProperties = imoveisFiltrados;
    
    // Reset para primeira página
    window.paginaAtual = 1;
    
    // Verifica se há resultados e mostra mensagem se necessário
    if (imoveisFiltrados.length === 0) {
        const alerta = document.createElement('div');
        alerta.id = 'erro-filtro';
        alerta.className = 'alert alert-info mt-3';
        alerta.innerHTML = '<i class="fas fa-info-circle me-2"></i>Nenhum imóvel encontrado com os filtros selecionados.';
        container.parentElement.insertBefore(alerta, container);
    }
    
    // Usa a função de renderização do painel-main.js
    if (typeof window.renderizarPagina === 'function') {
        window.renderizarPagina(1);
    }
}

// Inicialização
document.addEventListener("DOMContentLoaded", async () => {
    async function carregarDadosImoveis() {
        try {
            const response = await fetch('/api/getProperties.php?panel=1');
            const data = await response.json();
            
            // Verifica e sanitiza os dados
            let imoveis = [];
            if (Array.isArray(data)) {
                imoveis = data;
            } else if (data && data.success && Array.isArray(data.data)) {
                imoveis = data.data;
            } else {
                throw new Error('Formato de dados inválido');
            }
            
            // Filtra apenas imóveis válidos
            window.dadosImoveis = imoveis.filter(isImovelValido);
            return true;
        } catch (erro) {
            console.error('Erro ao carregar imóveis:', erro);
            mostrarErroValidacao('Erro ao carregar os dados dos imóveis. Por favor, recarregue a página.');
            return false;
        }
    }

    function aplicarFiltros() {
        // Reset para primeira página sempre que aplicar filtros
        if (typeof window.paginaAtual !== 'undefined') {
            window.paginaAtual = 1;
        }
        
        if (!window.dadosImoveis || !Array.isArray(window.dadosImoveis)) {
            mostrarErroValidacao('Dados dos imóveis não disponíveis');
            return;
        }

        const selectTipo = document.getElementById('select-tipo');
        const selectBairro = document.getElementById('select-localizacao');
        const selectPreco = document.getElementById('select-preco');
        const inputBusca = document.getElementById('property-search');
        
        if (!selectTipo || !selectBairro || !selectPreco) {
            console.warn('Elementos essenciais de filtro não encontrados');
            return;
        }

        const tipo = sanitizarString(selectTipo.value);
        const bairro = sanitizarString(selectBairro.value);
        const faixa = sanitizarString(selectPreco.value);
        const busca = inputBusca ? sanitizarString(inputBusca.value) : '';
        


        // Filtra os imóveis
        let filtrados = window.dadosImoveis;

        if (busca) {
            filtrados = filtrados.filter(i => {
                const titulo = sanitizarString(i.title || '').toLowerCase();
                const descricao = sanitizarString(i.description || '').toLowerCase();
                const endereco = sanitizarString(i.location || '').toLowerCase();
                const bairro = sanitizarString(i.neighborhood || '').toLowerCase();
                const cidade = sanitizarString(i.city || '').toLowerCase();
                const tipo = i.type ? sanitizarString(tiposMap[i.type] || i.type).toLowerCase() : '';
                
                const termoBusca = busca.toLowerCase();
                
                return titulo.includes(termoBusca) ||
                       descricao.includes(termoBusca) ||
                       endereco.includes(termoBusca) ||
                       bairro.includes(termoBusca) ||
                       cidade.includes(termoBusca) ||
                       tipo.includes(termoBusca);
            });
        }

        if (tipo) {
            // Aqui compara o tipo do imóvel com o valor do select (em inglês)
            filtrados = filtrados.filter(i => 
                sanitizarString(i.type).toLowerCase() === tipo
            );
        }

        if (bairro && bairro !== 'Todas as regiões') {
            filtrados = filtrados.filter(i => 
                sanitizarString(i.neighborhood) === bairro
            );
        }

        if (faixa && faixa !== 'Qualquer preço') {
            filtrados = filtrados.filter(i => {
                const preco = sanitizarPreco(i.price);
                if (preco === 0) return false;

                switch (faixa) {
                    case 'Até R$ 200.000':
                        return preco <= 200000;
                    case 'R$ 200.000 - R$ 500.000':
                        return preco > 200000 && preco <= 500000;
                    case 'R$ 500.000 - R$ 1.000.000':
                        return preco > 500000 && preco <= 1000000;
                    case 'Acima de R$ 1.000.000':
                        return preco > 1000000;
                    default:
                        return true;
                }
            });
        }



        renderizarImoveis(filtrados);
        
        // Re-renderiza a página atual após aplicar os filtros
        if (typeof window.renderizarPagina === 'function') {
            window.renderizarPagina(1);
        }
    }

    function inicializarFiltros() {
        const selectTipo = document.getElementById('select-tipo');
        const selectBairro = document.getElementById('select-localizacao');
        const selectPreco = document.getElementById('select-preco');
        const inputBusca = document.getElementById('property-search');
        
        if (!selectTipo || !selectBairro || !selectPreco) {
            console.warn('Elementos de filtro não encontrados');
            return;
        }

        // Preenche select de tipos
        selectTipo.innerHTML = '<option value="">Todos os tipos</option>';
        // Use as chaves do tiposMap para garantir o valor correto
        Object.keys(tiposMap).forEach(tipoKey => {
            // Só adiciona se houver imóveis desse tipo
            if (window.dadosImoveis.some(i => sanitizarString(i.type).toLowerCase() === tipoKey)) {
                const option = document.createElement('option');
                option.value = tipoKey;
                option.textContent = tiposMap[tipoKey];
                selectTipo.appendChild(option);
            }
        });

        // Preenche select de bairros
        selectBairro.innerHTML = '<option value="Todas as regiões">Todas as regiões</option>';
        [...new Set(window.dadosImoveis
            .map(i => sanitizarString(i.neighborhood))
            .filter(Boolean)
        )]
        .sort((a, b) => a.localeCompare(b, 'pt-BR'))
        .forEach(bairro => {
            const option = document.createElement('option');
            option.value = bairro;
            option.textContent = bairro;
            selectBairro.appendChild(option);
        });

        // Event listeners para selects
        selectTipo.addEventListener('change', aplicarFiltros);
        selectBairro.addEventListener('change', aplicarFiltros);
        selectPreco.addEventListener('change', aplicarFiltros);

        // Event listener para busca com debounce
        if (inputBusca) {
            let timeoutId;
            inputBusca.addEventListener('input', () => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(aplicarFiltros, 300);
            });
        }

        // Botão limpar filtros
        const btnLimparFiltros = document.getElementById('btn-limpar-filtros');
        if (btnLimparFiltros) {
            btnLimparFiltros.addEventListener('click', () => {
                selectTipo.value = '';
                selectBairro.value = 'Todas as regiões';
                selectPreco.value = 'Qualquer preço';
                if (inputBusca) inputBusca.value = '';
                aplicarFiltros();
            });
        }

        // Aplica filtros iniciais
        aplicarFiltros();
    }

    // Inicializa a aplicação
    const dadosCarregados = await carregarDadosImoveis();
    if (dadosCarregados) {
        inicializarFiltros();
        // Garante que os cards iniciais sejam renderizados
        aplicarFiltros();
    }
});