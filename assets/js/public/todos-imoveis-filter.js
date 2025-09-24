// Variáveis globais para os filtros

let currentFilters = {
    tipo: '',
    bairro: '',
    preco: ''
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
    const container = document.getElementById('property-list');
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
// Novo renderizarImoveis: apenas mostra/oculta cards existentes
function renderizarImoveis(imoveisFiltrados) {
    const container = document.getElementById('property-list');
    if (!container) return;

    // Não mostrar mensagem de erro se não houver cards ainda
    const cards = container.querySelectorAll('.property-card');
    if (cards.length === 0) return;

    // Mapeia os IDs dos imóveis filtrados
    const idsFiltrados = new Set((imoveisFiltrados || []).map(i => String(i.id)));

    let algumVisivel = false;
    cards.forEach(card => {
        // O id do imóvel deve estar em data-id do botão ou atributo do card
        let id = card.querySelector('[data-id]')?.getAttribute('data-id') || card.getAttribute('data-id');
        if (idsFiltrados.has(String(id))) {
            card.style.display = '';
            algumVisivel = true;
        } else {
            card.style.display = 'none';
        }
    });

    // Mensagem se nenhum card visível
    let alerta = document.getElementById('erro-filtro');
    if (!algumVisivel) {
        if (!alerta) {
            alerta = document.createElement('div');
            alerta.id = 'erro-filtro';
            alerta.className = 'alert alert-info mt-3';
            alerta.innerHTML = '<i class="fas fa-info-circle me-2"></i>Nenhum imóvel encontrado com os filtros selecionados.';
            container.parentElement.insertBefore(alerta, container);
        }
    } else if (alerta) {
        alerta.remove();
    }
}

// Inicialização
document.addEventListener("DOMContentLoaded", async () => {
    async function carregarDadosImoveis() {
        try {
            const response = await fetch('/api/getProperties.php');
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
        if (!window.dadosImoveis || !Array.isArray(window.dadosImoveis)) {
            mostrarErroValidacao('Dados dos imóveis não disponíveis');
            return;
        }

        const selectTipo = document.getElementById('select-tipo');
        const selectBairro = document.getElementById('select-localizacao');
        const selectPreco = document.getElementById('select-preco');
        
        if (!selectTipo || !selectBairro || !selectPreco) {
            console.warn('Elementos de filtro não encontrados');
            return;
        }

        const tipo = sanitizarString(selectTipo.value);
        const bairro = sanitizarString(selectBairro.value);
        const faixa = sanitizarString(selectPreco.value);

        console.log('Aplicando filtros:', { tipo, bairro, faixa });

        // Filtra os imóveis
        let filtrados = window.dadosImoveis;

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

        console.log('Resultados:', {
            total: window.dadosImoveis.length,
            filtrados: filtrados.length,
            filtros: { tipo, bairro, faixa }
        });

        renderizarImoveis(filtrados);
    }

    function inicializarFiltros() {
        const selectTipo = document.getElementById('select-tipo');
        const selectBairro = document.getElementById('select-localizacao');
        const selectPreco = document.getElementById('select-preco');
        
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

        // Event listeners
        selectTipo.addEventListener('change', aplicarFiltros);
        selectBairro.addEventListener('change', aplicarFiltros);
        selectPreco.addEventListener('change', aplicarFiltros);

        // Botão limpar filtros
        const btnLimparFiltros = document.getElementById('btn-limpar-filtros');
        if (btnLimparFiltros) {
            btnLimparFiltros.addEventListener('click', () => {
                selectTipo.value = '';
                selectBairro.value = 'Todas as regiões';
                selectPreco.value = 'Qualquer preço';
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