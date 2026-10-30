window.paginaAtual = 1;
const itensPorPagina = 12;
window.todasAsProperties = [];
let categoryUpdateInterval;
let latestPropertyEditRequestId = 0;

// Iniciar atualização automática quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    // Atualizar a cada 30 segundos
    categoryUpdateInterval = setInterval(() => {
        loadPropertyCategories();
    }, 30000);

    // Limpar intervalo quando a página for fechada
    window.addEventListener('beforeunload', () => {
        if (categoryUpdateInterval) {
            clearInterval(categoryUpdateInterval);
        }
    });
});

//AQUI INICIA
/**
 * Normaliza as categorias de imóveis do formato bruto da API
 * @param {Object} raw - Dados brutos das categorias
 * @returns {Object} Categorias normalizadas com contagens
 */
function normalizeCategories(raw) {
    const mapping = {
        Apartamentos: 'apartment',
        Casas: 'house',
        Comercial: 'commercial',
        Terrenos: 'land',
    };

    const normalized = [];
    for (const [key, value] of Object.entries(raw)) {
        const mappedKey = mapping[key] || key.toLowerCase();
        normalized.push({ type: mappedKey, total: value });
    }
    return normalized;
}
//AQUI TERMINA

/**
 * Formata o valor da área para exibição (ex: 120 -> '120,00').
 */
/**
 * Formata a área do imóvel para exibição
 * @param {number|string} area - Área a ser formatada
 * @returns {string} Área formatada com unidade de medida
 */
function formatarArea(area) {
    if (!area) return '0,00';
    try {
        let valor = String(area);
        if (valor.includes('.')) {
            const num = parseFloat(valor);
            return num.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }
        if (valor.includes(',')) {
            const partes = valor.split(',');
            const inteiros = partes[0].replace(/\./g, '');
            const decimais = (partes[1] || '00').padEnd(2, '0').slice(0, 2);
            return `${inteiros},${decimais}`;
        }
        const num = parseFloat(valor);
        return num.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    } catch (e) {
        console.error('Erro ao formatar área:', e);
        return '0,00';
    }
}

/**
 * Retorna a quantidade de quartos a partir do array de features.
 */
/**
 * Obtém a quantidade de quartos de um imóvel a partir do array de características
 * @param {Array} features - Array com as características do imóvel
 * @returns {string} Quantidade de quartos ou "N/D" se não encontrado
 */
function obterQtdQuartos(features) {
    if (!Array.isArray(features)) return 'N/D';
    const quartos = features.find((f) => f.toLowerCase().includes('quarto'));
    return quartos || 'N/D';
}

/**
 * Retorna a quantidade de banheiros a partir do array de features.
 */
/**
 * Obtém a quantidade de banheiros de um imóvel a partir do array de características
 * @param {Array} features - Array com as características do imóvel
 * @returns {string} Quantidade de banheiros ou "N/D" se não encontrado
 */
function obterQtdBanheiros(features) {
    if (!Array.isArray(features)) return 'N/D';
    const banheiros = features.find((f) => f.toLowerCase().includes('banheiro'));
    return banheiros || 'N/D';
}

/**
 * Carrega e exibe o gráfico de categorias de imóveis no dashboard
 * @param {boolean} isRealtime - Indica se é uma atualização em tempo real
 */
/**
 * Carrega as categorias de imóveis do servidor
 * @param {boolean} isRealtime - Indica se é uma atualização em tempo real
 * @returns {Promise<void>}
 */
function loadPropertyCategories(isRealtime = false) {
    const container = document.getElementById('property-categories');
    if (!container) return;

    // Se for atualização em tempo real, busca os dados mais recentes
    if (isRealtime) {
        // Adiciona classe de loading
        container.classList.add('updating');

        // Usa uma API separada para as categorias com credenciais
        apiCall('/api/v1/properties/getPropertiesCategories.php')
            .then((result) => {
                if (!result.success) {
                    throw new Error(result.error || 'Erro ao carregar categorias');
                }

                // result.data é a resposta do backend {categories: {...}}
                const data = result.data;
                window.categoryData = data;

                if (data.categories) {
                    window.categoryData = normalizeCategories(data.categories);
                }

                updateCategoryDisplay();
            })
            .catch((error) => {
                console.error('Erro ao atualizar categorias:', error);
                // Mostra mensagem de erro sutil
                const errorToast = document.createElement('div');
                errorToast.className = 'text-sm text-red-500 mt-2 fade-out';
                errorToast.textContent = 'Erro ao atualizar categorias';
                container.appendChild(errorToast);
                setTimeout(() => errorToast.remove(), 3000);
            })
            .finally(() => {
                // Remove classe de loading
                container.classList.remove('updating');
            });
        return;
    }

    updateCategoryDisplay();
}

/**
 * Atualiza a exibição das categorias no dashboard
 */
/**
 * Atualiza a exibição das categorias de imóveis na interface
 * Renderiza os cards com as contagens e ícones de cada categoria
 */
function updateCategoryDisplay() {
    const container = document.getElementById('property-categories');
    if (!container) return;

    // Usa window.categoryData em vez de window.propertyData para separar as fontes de dados
    const properties = window.categoryData || window.propertyData || [];
    container.innerHTML = '';

    if (properties.length === 0) {
        container.innerHTML = '<p class="text-gray-500">Nenhum imóvel cadastrado.</p>';
        return;
    }

    // Contagem por categoria
    const categoryCounts = {
        apartment: 0,
        house: 0,
        commercial: 0,
        land: 0,
    };

    // Conta imóveis por categoria
    properties.forEach((property) => {
        const type = property.type;
        if (type && type in categoryCounts) {
            categoryCounts[type]++;
        }
    });

    const total = properties.length;

    const categories = [
        {
            name: 'Apartamentos',
            count: categoryCounts['apartment'],
            color: 'rgb(var(--cor-primaria-rgb))',
            icon: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-building"></i></div>',
        },
        {
            name: 'Casas',
            count: categoryCounts['house'],
            color: 'rgb(var(--cor-secundaria-rgb))',
            icon: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-green-100 text-green-600"><i class="fas fa-home"></i></div>',
        },
        {
            name: 'Comercial',
            count: categoryCounts['commercial'],
            color: 'rgb(var(--cor-destaque-rgb))',
            icon: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-orange-100 text-orange-600"><i class="fas fa-store"></i></div>',
        },
        {
            name: 'Terrenos',
            count: categoryCounts['land'],
            color: 'rgb(156, 163, 175)',
            icon: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-600"><i class="fas fa-map"></i></div>',
        },
    ];

    categories.forEach((category) => {
        const percentage = total > 0 ? Math.round((category.count / total) * 100) : 0;
        const div = document.createElement('div');
        div.className = 'flex items-start gap-3 mb-4';
        div.innerHTML = `
      ${category.icon}
      <div class="flex-grow">
        <div class="flex justify-between mb-2">
          <span class="text-gray-700 font-medium">${category.name}</span>
          <span class="text-gray-900 font-semibold">${category.count}</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full overflow-hidden">
          <div class="h-2 rounded-full transition-all duration-500 ease-in-out" 
               style="width: ${percentage}%; background-color: ${category.color}; min-width: ${category.count > 0 ? '0.25rem' : '0'}">
          </div>
        </div>
      </div>
    `;
        container.appendChild(div);
        // ...
    });
}
/**
 * Abre o modal de detalhes do imóvel pelo id.
 */
/**
 * Abre o modal com os detalhes de um imóvel específico
 * @param {number} id - ID do imóvel
 */
function abrirModalDetalhes(id) {
    fetch(`/api/v1/properties/getPropertyById.php?id=${id}`, {
        credentials: 'include',
    })
        .then((res) => {
            if (!res.ok) {
                throw new Error('Erro na resposta da API');
            }
            return res.json();
        })
        .then((data) => {
            // Com o PDO, os dados vêm dentro de data.data
            const property = data.data;
            const titleEl = document.getElementById('modal-title');
            const priceEl = document.getElementById('modal-price');
            const descEl = document.getElementById('modal-description');
            const locEl = document.getElementById('modal-location');
            const areaEl = document.getElementById('modal-area');
            const yearEl = document.getElementById('modal-yearBuilt');
            const featuresList = document.getElementById('modal-features');
            const imageContainer = document.getElementById('modal-carousel-images');

            if (
                !titleEl ||
                !priceEl ||
                !descEl ||
                !locEl ||
                !areaEl ||
                !yearEl ||
                !featuresList ||
                !imageContainer
            ) {
                console.error('Alguns elementos do modal não foram encontrados.');
                window.utils.mostrarErro(
                    'Erro ao abrir detalhes do imóvel: elementos do modal não encontrados'
                );
                return;
            }

            // Inicializa o modal Bootstrap
            const modalEl = document.getElementById('propertyModal');
            if (!modalEl) {
                console.error('Modal não encontrado no DOM');
                window.utils.mostrarErro('Erro ao abrir detalhes do imóvel: modal não encontrado');
                return;
            }

            titleEl.textContent = property.title || '';

            // O PDO já retorna o price como número
            const price = typeof property.price === 'number' ? property.price : 0;
            priceEl.textContent = `R$ ${price.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

            // O PDO já retorna a área como número
            const area = typeof property.area === 'number' ? property.area : 0;
            areaEl.textContent = `${area}m² `;
            descEl.textContent = property.description;
            // Definir cor do ícone do modal igual ao badge do card
            let iconColor = 'text-blue-600';
            const tipoTransacao = (property.transactionType || '').toLowerCase();
            const tipoImovel = (property.type || '').toLowerCase();
            if (
                tipoTransacao.includes('comercial') ||
                tipoTransacao.includes('commercial') ||
                tipoImovel === 'comercial' ||
                tipoImovel === 'commercial'
            ) {
                iconColor = 'text-orange-500';
            } else if (
                tipoTransacao === 'aluguel' ||
                tipoTransacao === 'alugar' ||
                tipoTransacao === 'rent' ||
                tipoImovel === 'aluguel'
            ) {
                iconColor = 'text-green-600';
            } else if (
                tipoTransacao === 'venda' ||
                tipoTransacao === 'sale' ||
                tipoImovel === 'venda'
            ) {
                iconColor = 'text-blue-600';
            }
            locEl.innerHTML = `<i class="fas fa-map-marker-alt me-2 ${iconColor}"></i> ${property.location}${property.neighborhood ? ' - ' + property.neighborhood : ''}`;

            // Exibe o usuário atribuído se existir
            const assignedUserSection = document.getElementById('modal-assigned-user-section');
            const assignedUserName = document.getElementById('modal-assigned-user-name');
            if (assignedUserSection && assignedUserName) {
                if (property.assigned_user_id) {
                    // Busca o nome do usuário pela API
                    fetch(`/api/v1/users/show.php?id=${property.assigned_user_id}`)
                        .then((r) => r.json())
                        .then((j) => {
                            if (j.success && j.data) {
                                const user = j.data;
                                assignedUserName.textContent = `${user.name} (${user.username})`;
                                assignedUserSection.style.display = 'block';
                            } else {
                                assignedUserSection.style.display = 'none';
                            }
                        })
                        .catch((err) => {
                            console.error('Erro ao carregar usuário atribuído:', err);
                            assignedUserSection.style.display = 'none';
                        });
                } else {
                    assignedUserSection.style.display = 'none';
                }
            }

            // Atualiza os valores de área e ano
            areaEl.innerHTML = `<i class="fas fa-ruler-combined ${iconColor} me-2"></i> <span class="fw-semibold text-dark">${formatarArea(property.area)}m&sup2;</span>`;
            yearEl.innerHTML = `<i class="fas fa-calendar-alt ${iconColor} me-2"></i> <span class="fw-semibold text-dark">${property.yearBuilt || 'N/A'}</span>`;

            // Exibe quartos, banheiros e vagas no modal (com ícones) logo abaixo do quadrado azul e acima da descrição
            const bedrooms =
                property.bedrooms !== undefined && property.bedrooms !== ''
                    ? property.bedrooms
                    : 'N/A';
            const bathrooms =
                property.bathrooms !== undefined && property.bathrooms !== ''
                    ? property.bathrooms
                    : 'N/A';
            const garage =
                property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A';
            const suites =
                property.suites !== undefined && property.suites !== null && property.suites !== ''
                    ? property.suites
                    : 'N/A';
            const condominium =
                property.condominium !== undefined &&
                property.condominium !== null &&
                property.condominium !== ''
                    ? parseFloat(property.condominium).toLocaleString('pt-BR', {
                          minimumFractionDigits: 2,
                      })
                    : 'N/A';
            const iptu =
                property.iptu !== undefined && property.iptu !== null && property.iptu !== ''
                    ? parseFloat(property.iptu).toLocaleString('pt-BR', {
                          minimumFractionDigits: 2,
                      })
                    : 'N/A';

            let areaBox = areaEl.parentElement.parentElement;
            let descParent = descEl.parentElement;
            let infoRow = document.getElementById('modal-info-row');
            if (!infoRow) {
                infoRow = document.createElement('div');
                infoRow.id = 'modal-info-row';
                infoRow.className = 'd-flex justify-content-between mb-3';
                // Insere logo após o quadrado azul (área/ano)
                areaBox.parentElement.insertBefore(infoRow, areaBox.nextSibling);
            } else {
                infoRow.innerHTML = '';
            }
            infoRow.innerHTML = `
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-bed ${iconColor}"></i> <span class="fw-semibold text-dark">${bedrooms} Quartos</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-bath ${iconColor}"></i> <span class="fw-semibold text-dark">${bathrooms} Banheiros</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-door-closed ${iconColor}"></i> <span class="fw-semibold text-dark">${suites} Suítes</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-car ${iconColor}"></i> <span class="fw-semibold text-dark">${garage} Vagas</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-building ${iconColor}"></i> <span class="fw-semibold text-dark">${condominium} Condominio</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <i class="fas fa-receipt ${iconColor}"></i> <span class="fw-semibold text-dark">${iptu} IPTU</span>
      `;

            // Atualiza as classes e estrutura do container
            areaEl.parentElement.parentElement.className =
                'd-flex justify-content-between bg-blue-50 p-3 rounded mb-4';
            areaEl.parentElement.className = 'd-flex flex-column';
            yearEl.parentElement.className = 'd-flex flex-column text-end';

            featuresList.innerHTML = '';
            featuresList.className = 'row row-cols-1 row-cols-sm-2 g-2 list-unstyled ps-0';
            let features = property.features;

            if (typeof features === 'string') {
                try {
                    features = JSON.parse(features);
                } catch {
                    features = [];
                }
            }

            if (Array.isArray(features)) {
                features.forEach((f) => {
                    const li = document.createElement('li');
                    const featureKey = String(f).toLowerCase().trim();
                    const iconClass = featuresIcons[featureKey] || 'fa-check';

                    li.className = 'd-flex align-items-center mb-1 col-6 text-primary';
                    li.innerHTML = `<i class="fas ${iconClass} ${iconColor} me-2"></i>${f}`;
                    featuresList.appendChild(li);
                });
            }

            const imagens =
                typeof property.images === 'string' ? JSON.parse(property.images) : property.images;
            imageContainer.innerHTML = '';
            if (Array.isArray(imagens) && imagens.length > 0) {
                imagens.forEach((img, index) => {
                    const item = document.createElement('div');
                    item.className = `carousel-item ${index === 0 ? 'active' : ''}`;
                    item.innerHTML = `
            <img src="assets/imagens/${property.id}/${img}" class="d-block w-100 h-64 object-cover rounded" alt="Imagem">
          `;
                    imageContainer.appendChild(item);
                });
            }

            const whatsappBtn = document.getElementById('modal-whatsapp');
            if (whatsappBtn) {
                // Busca o número do WhatsApp da empresa das configurações
                (async () => {
                    try {
                        const json = await window.getCompanySettings();
                        let whatsappNumber = '5511999999999'; // Número padrão

                        if (json.success && json.data && json.data.company_whatsapp) {
                            whatsappNumber = json.data.company_whatsapp.replace(/\D/g, '');
                            // Adiciona código do país se necessário
                            if (!whatsappNumber.startsWith('55')) {
                                whatsappNumber = '55' + whatsappNumber;
                            }
                        }

                        // Garante que o número seja válido
                        if (whatsappNumber.length < 12) whatsappNumber = '5511999999999';

                        whatsappBtn.href = `https://wa.me/${whatsappNumber}?text=Olá! Tenho interesse no imóvel: ${property.title}`;
                    } catch (err) {
                        console.error('Erro ao buscar número do WhatsApp:', err);
                        whatsappBtn.href = `https://wa.me/5511999999999?text=Olá! Tenho interesse no imóvel: ${property.title}`;
                    }
                })();
            }

            const modal = new bootstrap.Modal(document.getElementById('propertyModal'));
            modal.show();
        })
        .catch((err) => {
            console.error('Erro ao carregar detalhes do imóvel:', err);
            window.utils.mostrarErro('Erro ao abrir detalhes do imóvel.');
        });
}

// Mapeamento de ícones para características
window.featuresIcons = {
    piscina: 'fa-water',
    churrasqueira: 'fa-fire',
    academia: 'fa-dumbbell',
    playground: 'fa-child',
    'salão de festas': 'fa-glass-cheers',
    segurança: 'fa-shield-alt',
    elevador: 'fa-arrow-up',
    portaria: 'fa-user-shield',
    'área de lazer': 'fa-umbrella-beach',
    quadra: 'fa-basketball-ball',
    varanda: 'fa-door-open',
    mobiliado: 'fa-couch',
    'ar condicionado': 'fa-snowflake',
    interfone: 'fa-phone',
    jardim: 'fa-leaf',
    'área gourmet': 'fa-utensils',
    'aceita pet': 'fa-paw',
};

/**
 * Busca e renderiza os imóveis no painel administrativo, com paginação e ações de editar/excluir.
 */
/**
 * Carrega os imóveis do servidor para exibição no painel
 * @returns {Promise<void>}
 */
async function carregarImoveisPainel() {
    // Proteção contra múltiplas chamadas simultâneas
    if (window.carregarImoveisPainelLoading) {
        console.debug('carregarImoveisPainel: já está carregando, ignorando chamada');
        return;
    }

    window.carregarImoveisPainelLoading = true;

    try {
        const result = await apiCall('/api/v1/properties/getProperties.php?panel=1');

        if (!result || !result.success) {
            console.warn('Falha ao carregar imóveis do painel:', result?.error || result);
            window.todasAsProperties = [];
            window.propertyData = [];
            window.renderizarPagina(window.paginaAtual);
            return;
        }

        // ✔ CORRETO: API real está dentro de result.data.data
        const properties = result?.data?.data;

        if (!Array.isArray(properties)) {
            console.warn(
                'Payload de imóveis inválido, esperado array em result.data.data:',
                result
            );

            window.todasAsProperties = [];
            window.propertyData = [];
            window.renderizarPagina(window.paginaAtual);
            return;
        }

        // Salva globalmente
        window.todasAsProperties = properties;
        window.propertyData = properties;

        // Renderiza imóveis
        window.renderizarPagina(window.paginaAtual);

        // Atualiza dashboard se estiver ativa
        const dashboardPane = document.getElementById('dashboard');
        if (dashboardPane && dashboardPane.classList.contains('active')) {
            loadPropertyCategories();
            loadRecentProperties();
        }
    } catch (err) {
        console.error('Erro ao carregar imóveis:', err);
        window.todasAsProperties = [];
        window.propertyData = [];
        window.renderizarPagina(window.paginaAtual);
    } finally {
        window.carregarImoveisPainelLoading = false;
    }
}

/**
 * Carrega e exibe os imóveis mais recentes no dashboard
 */
function loadRecentProperties() {
    const container = document.getElementById('recent-properties');
    if (!container) return;

    const properties = window.propertyData || [];

    if (properties.length === 0) {
        container.innerHTML = `
      <tr>
        <td colspan="5" class="px-6 py-4 text-center text-gray-500">
          Nenhum imóvel cadastrado ainda.
        </td>
      </tr>
    `;
        return;
    }

    // Pega os 7 imóveis mais recentes
    const recentProperties = [...properties]
        .sort((a, b) => Number(b.id) - Number(a.id))
        .slice(0, 7);

    container.innerHTML = '';

    recentProperties.forEach((property) => {
        let statusClass = 'badge-status status-inactive';
        let statusText = property.status || 'Inativo';
        let priceFormatted = '';
        let location = property.location || '';

        // Se o utils já estiver carregado, usa suas funções
        if (window.utils) {
            statusClass = window.utils.getStatusClass(property.status);
            statusText = window.utils.processarStatus(property.status);
            priceFormatted = window.utils.formatarPreco(property.price);
            location = property.neighborhood
                ? `${property.location} - ${property.neighborhood}`
                : property.location;
        } else {
            // Fallback simples se utils não estiver disponível
            priceFormatted = property.price
                ? `R$ ${Number(property.price).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`
                : 'Sob consulta';
            location = property.neighborhood
                ? `${property.location} - ${property.neighborhood}`
                : property.location;
        }

        const typeIcons = {
            apartment:
                '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-building"></i></div>',
            house: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-green-100 text-green-600"><i class="fas fa-home"></i></div>',
            commercial:
                '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-orange-100 text-orange-600"><i class="fas fa-store"></i></div>',
            land: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-600"><i class="fas fa-map"></i></div>',
        };

        const row = document.createElement('tr');
        row.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="flex items-center gap-3">
          ${typeIcons[property.type] || ''}
          <div class="text-sm font-medium text-gray-900">
            ${property.title}
          </div>
        </div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="text-sm text-gray-500">${window.utils.normalizarTipoImovel(property.type) || '-'}</div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="text-sm text-gray-500">${location}</div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <div class="text-sm text-gray-900">${priceFormatted}</div>
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <span class="${statusClass}">
          ${statusText}
        </span>
      </td>
    `;
        container.appendChild(row);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    carregarImoveisPainel();
    loadRecentProperties();
});

/**
 * Renderiza a página de imóveis atual na paginação do painel.
 */
window.renderizarPagina = function (pagina) {
    const container = document.getElementById('properties-container');
    if (!container) return;
    container.innerHTML = '';

    const inicio = (pagina - 1) * itensPorPagina;
    const fim = inicio + itensPorPagina;
    const todasAsProperties = Array.isArray(window.todasAsProperties)
        ? window.todasAsProperties
        : [];
    if (!Array.isArray(window.todasAsProperties)) {
        console.warn(
            'Renderização de página recebeu payload de imóveis inválido:',
            window.todasAsProperties
        );
    }
    const propriedadesPagina = todasAsProperties.slice(inicio, fim);

    // Sempre rola para o topo do container ao trocar de página
    const containerTop = container.getBoundingClientRect().top + window.scrollY - 100;
    window.scrollTo({ top: containerTop, behavior: 'smooth' });

    propriedadesPagina.forEach((property) => {
        const card = document.createElement('div');
        card.className =
            'property-card bg-white rounded-xl shadow-lg border border-gray-100 p-0 flex flex-col transition-transform duration-300 hover:scale-105 hover:shadow-2xl';

        const images =
            typeof property.images === 'string' ? JSON.parse(property.images) : property.images;
        const imageSrc = images && images.length > 0 ? images[0] : '/assets/imagens/default.jpg';
        const colors = window.utils.getPropertyColors(property);
        let tipoLabel = colors.badgeText;
        let btnDetalhesColor = colors.button;
        let iconColor = colors.icon;
        let btnStyle = '';
        let quartos =
            property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A';
        let banheiros =
            property.bathrooms !== undefined && property.bathrooms !== ''
                ? property.bathrooms
                : 'N/A';
        let vagas =
            property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A';
        // Usa a função getStatusClass do utils se disponível, ou fallback para classe padrão
        function getStatusClass(status) {
            if (window.utils) {
                return window.utils.getStatusClass(status);
            }
            return 'badge-status status-inactive';
        }
        card.innerHTML = `
      <div class="relative h-48 overflow-visible">
        <img src="${imageSrc}" class="w-full h-full object-cover rounded-t-xl" alt="Imagem do imóvel">
        <span class="badge-grande" style="${colors.badge} color: white; z-index:2;">${tipoLabel}</span>
        <div class="absolute top-2 right-2 flex gap-2 z-50">
          ${
              window.isAdmin
                  ? `<button class="bg-white rounded-full p-2 shadow hover:bg-yellow-100 transition btn-editar-imovel" style="border:none;" title="Editar Imóvel" data-id="${property.id}">
            <i class="fa-solid fa-pen text-yellow-600 text-lg"></i>
          </button>
          <button class="bg-white rounded-full p-2 shadow hover:bg-red-100 transition btn-excluir-imovel" style="border:none;" title="Excluir Imóvel" data-id="${property.id}"><i class="fa-solid fa-trash text-red-500 text-lg"></i></button>`
                  : ''
          }
        </div>
      </div>
      <div class="p-6 flex-1 flex flex-col justify-between">
        <div class="mb-3">
          <h4 class="text-lg font-bold text-gray-800 line-clamp-2 mb-2">${property.title}</h4>
          <span class="text-blue-600 font-bold text-lg">${window.utils.formatarPreco(property.price, tipoLabel === 'Para Alugar', property.type)}</span>
        </div>
        <div class="mb-3">
          <p class="text-gray-500 text-sm flex items-center mb-0 line-clamp-1">
            <i class="fas fa-map-marker-alt mr-2 flex-shrink-0" style="${colors.icon}"></i> 
            <span class="truncate">${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}</span>
          </p>
          <span class="${getStatusClass(property.status)} inline-block mt-2">${window.utils.processarStatus(property.status)}</span>
        </div>
        <div class="flex w-100 text-sm text-gray-600 mb-4 gap-3 justify-between">
          <div class="flex items-center gap-1 min-w-0">
            <i class="fas fa-bed" style="${colors.icon}"></i>
            <span>${quartos} Quartos</span>
          </div>
          <div class="flex items-center gap-1 min-w-0">
            <i class="fas fa-bath" style="${colors.icon}"></i>
            <span>${banheiros} Banheiros</span>
          </div>
          <div class="flex items-center gap-1 min-w-0">
            <i class="fas fa-car" style="${colors.icon}"></i>
            <span>${vagas} Vagas</span>
          </div>
          <div class="flex items-center gap-1 min-w-0">
            <i class="fas fa-ruler-combined" style="${colors.icon}"></i>
            <span>${formatarArea(property.area)}m&sup2;</span>
          </div>
        </div>
        <button class="btn btn-detalhes text-base py-2 px-4 rounded-lg transition ver-detalhes font-semibold w-full mt-2 text-white" style="${btnDetalhesColor} ${btnStyle}" data-id="${property.id}"><i class="fas fa-search me-2"></i>
          Ver Detalhes
        </button>
      </div>
    `;
        // Eventos dos botões
        async function waitForPropertyFormReady() {
            const maxAttempts = 120;
            for (let attempt = 0; attempt < maxAttempts; attempt += 1) {
                const form = document.getElementById('property-form');
                if (form) {
                    return form;
                }
                await new Promise((resolve) => requestAnimationFrame(resolve));
            }
            return null;
        }

        const btnEditar = card.querySelector('.btn-editar-imovel');
        if (btnEditar) {
            btnEditar.addEventListener('click', async function (e) {
                e.stopPropagation();
                const tabAdd = document.querySelector('a[href="#add-property"]');
                if (tabAdd) tabAdd.click();

                const requestedPropertyId = Number(property.id);
                if (!Number.isInteger(requestedPropertyId) || requestedPropertyId <= 0) {
                    if (window.utils && typeof window.utils.mostrarErro === 'function') {
                        window.utils.mostrarErro('Imóvel inválido para edição.');
                    }
                    return;
                }

                const form = await waitForPropertyFormReady();
                if (!form) {
                    if (window.utils && typeof window.utils.mostrarErro === 'function') {
                        window.utils.mostrarErro(
                            'Formulário de imóvel não ficou disponível para edição.'
                        );
                    }
                    return;
                }

                latestPropertyEditRequestId += 1;
                const currentRequestId = latestPropertyEditRequestId;

                fetch(`/api/v1/properties/getPropertyById.php?id=${requestedPropertyId}`, {
                    credentials: 'include',
                })
                    .then(async (response) => {
                        if (currentRequestId !== latestPropertyEditRequestId) {
                            return;
                        }

                        if (!response.ok) {
                            let errorMessage = 'Erro ao carregar dados do imóvel para edição.';
                            try {
                                const errorJson = await response.json();
                                if (errorJson && errorJson.message) {
                                    errorMessage = errorJson.message;
                                }
                            } catch (error) {
                                // Ignora resposta sem JSON válido.
                            }
                            console.warn(
                                'Resposta HTTP inválida ao buscar imóvel para edição:',
                                errorMessage
                            );
                            if (window.utils && typeof window.utils.mostrarErro === 'function') {
                                window.utils.mostrarErro(errorMessage);
                            }
                            return;
                        }

                        const json = await response.json();
                        if (currentRequestId !== latestPropertyEditRequestId) {
                            return;
                        }

                        if (!json || json.success !== true || !json.data || !json.data.id) {
                            console.warn('Resposta de imóvel inválida para edição:', json);
                            if (window.utils && typeof window.utils.mostrarErro === 'function') {
                                window.utils.mostrarErro(
                                    'Não foi possível carregar os dados do imóvel.'
                                );
                            }
                            return;
                        }

                        const prop = json.data;
                        const readyForm = document.getElementById('property-form');
                        if (!readyForm) {
                            if (window.utils && typeof window.utils.mostrarErro === 'function') {
                                window.utils.mostrarErro('Formulário de imóvel não encontrado!');
                            }
                            return;
                        }

                        readyForm.reset();

                        let editIdInput = readyForm.querySelector('#edit_id');
                        if (!editIdInput) {
                            editIdInput = document.createElement('input');
                            editIdInput.type = 'hidden';
                            editIdInput.id = 'edit_id';
                            editIdInput.name = 'edit_id';
                            readyForm.appendChild(editIdInput);
                        }
                        editIdInput.value = prop.id;
                        readyForm.dataset.editingPropertyId = String(prop.id);

                        const formTitle = document.querySelector('#add-property h2');
                        if (formTitle) formTitle.textContent = 'Editar Imóvel';

                        const fieldNameMappings = {
                            sale_commission_percentage: 'commission_percentage',
                            location: 'address',
                        };

                        const typeValueMap = {
                            apartment: 'apartment',
                            house: 'house',
                            commercial: 'commercial',
                            land: 'land',
                        };

                        for (const [key, value] of Object.entries(prop)) {
                            if (
                                key === 'assigned_user_id' ||
                                key === 'features' ||
                                key === 'status' ||
                                key === 'id' ||
                                key === 'tenant_id' ||
                                key === 'created_at' ||
                                key === 'updated_at' ||
                                key === 'images'
                            ) {
                                continue;
                            }

                            let fieldName = fieldNameMappings[key] || key;
                            const input = readyForm.querySelector(`[name="${fieldName}"]`);

                            if (!input) continue;

                            if (value === null || value === undefined) {
                                if (input.tagName === 'SELECT') {
                                    input.value = '';
                                } else if (input.type === 'radio') {
                                    input.checked = false;
                                } else {
                                    input.value = '';
                                }
                                continue;
                            }

                            if (input.type === 'radio') {
                                const radios = readyForm.querySelectorAll(
                                    `input[name="${fieldName}"]`
                                );
                                radios.forEach((r) => {
                                    r.checked = r.value === String(value);
                                });
                            } else if (input.type === 'checkbox') {
                                input.checked = !!value;
                            } else if (input.tagName === 'SELECT') {
                                let selectValue = String(value);
                                if (fieldName === 'type' && typeValueMap[selectValue]) {
                                    selectValue = typeValueMap[selectValue];
                                }
                                input.value = selectValue;
                            } else if (input.tagName === 'TEXTAREA') {
                                input.value = String(value);
                            } else {
                                if (fieldName === 'area') {
                                    const areaNum = parseFloat(value);
                                    if (!isNaN(areaNum)) {
                                        input.value = areaNum
                                            .toLocaleString('pt-BR', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            })
                                            .replace('.', ',');
                                    }
                                } else if (fieldName === 'price') {
                                    const priceNum = parseFloat(value);
                                    if (!isNaN(priceNum)) {
                                        input.value = priceNum.toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        });
                                    }
                                } else if (fieldName === 'condominium' || fieldName === 'iptu') {
                                    const numValue = parseFloat(value);
                                    if (!isNaN(numValue) && numValue !== 0) {
                                        input.value = numValue.toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        });
                                    }
                                } else if (fieldName === 'commission_percentage') {
                                    const commissionNum = parseFloat(value);
                                    if (!isNaN(commissionNum) && commissionNum !== 0) {
                                        input.value = commissionNum.toLocaleString('pt-BR', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        });
                                    }
                                } else if (fieldName === 'yearBuilt' || input.type === 'number') {
                                    const numValue = parseInt(value);
                                    if (!isNaN(numValue)) {
                                        input.value = numValue;
                                    }
                                } else {
                                    input.value = String(value || '');
                                }
                            }
                        }

                        if (prop.status) {
                            const statusRadio = readyForm.querySelector(
                                `input[name="status"][value="${prop.status}"]`
                            );
                            if (!statusRadio) {
                                const statusSelect =
                                    readyForm.querySelector(`select[name="status"]`);
                                if (statusSelect) {
                                    statusSelect.value = prop.status;
                                }
                            } else {
                                statusRadio.checked = true;
                            }
                        }

                        if (prop.features) {
                            let features = prop.features;
                            if (typeof features === 'string') {
                                try {
                                    features = JSON.parse(features);
                                } catch {
                                    features = [];
                                }
                            }
                            readyForm
                                .querySelectorAll('input[name="features[]"]')
                                .forEach((cb) => (cb.checked = false));
                            if (Array.isArray(features)) {
                                features.forEach((feature) => {
                                    const checkbox = readyForm.querySelector(
                                        `input[name="features[]"][value="${CSS.escape(String(feature))}"]`
                                    );
                                    if (checkbox) checkbox.checked = true;
                                });
                            }
                        }

                        const imagensEditContainerId = 'property-edit-images';
                        let imagensEditContainer = document.getElementById(imagensEditContainerId);
                        if (!imagensEditContainer) {
                            imagensEditContainer = document.createElement('div');
                            imagensEditContainer.id = imagensEditContainerId;
                            imagensEditContainer.className = 'mb-3 d-flex flex-wrap gap-3';
                            const uploadBtn = document.getElementById('property-upload-btn');
                            if (uploadBtn && uploadBtn.parentElement) {
                                uploadBtn.parentElement.insertBefore(
                                    imagensEditContainer,
                                    uploadBtn
                                );
                            } else {
                                readyForm.appendChild(imagensEditContainer);
                            }
                        }
                        imagensEditContainer.innerHTML =
                            '<span class="fw-semibold mb-2 d-block">Imagens do imóvel:</span>';
                        fetch(`/api/v1/properties/getPropertyImages.php?id=${prop.id}`, {
                            credentials: 'include',
                        })
                            .then(async (res) => {
                                if (currentRequestId !== latestPropertyEditRequestId) {
                                    return;
                                }
                                const imgs = await res.json();
                                if (!Array.isArray(imgs) || imgs.length === 0) {
                                    imagensEditContainer.innerHTML +=
                                        '<span class="text-muted">Nenhuma imagem encontrada.</span>';
                                    return;
                                }
                                imgs.forEach((img) => {
                                    const imgBox = document.createElement('div');
                                    imgBox.className = 'position-relative';
                                    imgBox.style = 'display:inline-block;';
                                    imgBox.innerHTML = `
                      <img src="${img}" style="max-width:120px;max-height:120px;border-radius:8px;box-shadow:0 2px 8px #0002;" alt="Imagem">
                      <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" title="Excluir imagem" style="border-radius:50%;padding:2px 6px;z-index:2;">&times;</button>
                    `;
                                    const btnExcluir = imgBox.querySelector('button');
                                    btnExcluir.onclick = function () {
                                        if (confirm('Deseja realmente excluir esta imagem?')) {
                                            const fd = new FormData();
                                            fd.append('id', prop.id);
                                            fd.append('file', img.split('/').pop());
                                            fetch('/api/v1/properties/deletePropertyImage.php', {
                                                method: 'POST',
                                                body: fd,
                                                credentials: 'include',
                                            })
                                                .then((res) => res.json())
                                                .then((json) => {
                                                    if (json.success) {
                                                        imgBox.remove();
                                                        if (
                                                            typeof window.updateDashboardCounts ===
                                                            'function'
                                                        ) {
                                                            window.updateDashboardCounts();
                                                        }
                                                    } else {
                                                        window.utils.mostrarErro(
                                                            json.message ||
                                                                'Erro ao excluir imagem.'
                                                        );
                                                    }
                                                })
                                                .catch(() =>
                                                    window.utils.mostrarErro(
                                                        'Erro ao excluir imagem.'
                                                    )
                                                );
                                        }
                                    };
                                    imagensEditContainer.appendChild(imgBox);
                                });
                            })
                            .catch((err) => {
                                if (currentRequestId !== latestPropertyEditRequestId) {
                                    return;
                                }
                                console.error(
                                    'Erro ao carregar imagens do imóvel para edição:',
                                    err
                                );
                            });

                        (function populateAssignedUser() {
                            const assignedSelect = readyForm.querySelector(
                                '[name="assigned_user_id"]'
                            );
                            if (!assignedSelect) return;
                            const savedUserId = prop.assigned_user_id;

                            fetch('/api/v1/users/index.php')
                                .then((r) => r.json())
                                .then((j) => {
                                    if (currentRequestId !== latestPropertyEditRequestId) {
                                        return;
                                    }
                                    if (j.success && Array.isArray(j.data)) {
                                        assignedSelect.innerHTML =
                                            '<option value="">Nenhum</option>';
                                        j.data.forEach((u) => {
                                            const o = document.createElement('option');
                                            o.value = String(u.id);
                                            o.textContent = u.name + ' (' + u.username + ')';
                                            assignedSelect.appendChild(o);
                                        });
                                        if (savedUserId) {
                                            assignedSelect.value = String(savedUserId);
                                        }
                                    }
                                })
                                .catch(() => {
                                    /* não bloquear */
                                });
                        })();
                    })
                    .catch((err) => {
                        if (currentRequestId !== latestPropertyEditRequestId) {
                            return;
                        }
                        console.error('Erro ao obter imóvel completo:', err);
                        if (window.utils && typeof window.utils.mostrarErro === 'function') {
                            window.utils.mostrarErro(
                                'Erro ao carregar dados do imóvel para edição.'
                            );
                        }
                    });
            });
        }
        // Permite que qualquer usuário do tenant delete imóveis
        const btnExcluir = card.querySelector('.btn-excluir-imovel');
        if (btnExcluir) {
            btnExcluir.addEventListener('click', async function (e) {
                e.stopPropagation();
                if (confirm('Tem certeza que deseja excluir este imóvel?')) {
                    const formData = new FormData();
                    formData.append('id', property.id);
                    fetch('/api/v1/properties/deleteProperty.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'include',
                    })
                        .then((res) => res.json())
                        .then(async (data) => {
                            if (data.success) {
                                card.remove();
                                if (typeof window.updateDashboardCounts === 'function') {
                                    window.updateDashboardCounts();
                                }
                                if (typeof window.carregarImoveisPainel === 'function') {
                                    await window.carregarImoveisPainel();
                                }
                                // Notifica invalidação de cache para o site público
                                if (typeof CacheInvalidator !== 'undefined') {
                                    CacheInvalidator.notify('properties', {
                                        property_id: property.id,
                                        action: 'deleted',
                                    });
                                }
                            } else {
                                window.utils.mostrarErro(data.message || 'Erro ao excluir imóvel.');
                            }
                        })
                        .catch((err) => {
                            window.utils.mostrarErro('Erro ao excluir imóvel.');
                        });
                }
            });
        }
        card.querySelector('.ver-detalhes').addEventListener('click', function () {
            abrirModalDetalhes(property.id);
        });
        container.appendChild(card);
    });

    // Atualiza a paginação dinâmica
    atualizarPaginacao();

    // Atualiza o contador de imóveis
    const counter = document.getElementById('properties-count');
    if (counter) {
        const inicio = (pagina - 1) * itensPorPagina + 1;
        const fim = Math.min(pagina * itensPorPagina, window.todasAsProperties.length);
        const total = window.todasAsProperties.length;
        counter.textContent = `Mostrando ${inicio}-${fim} de ${total} imóveis`;
    }
};

function atualizarPaginacao() {
    const paginacaoContainer = document.getElementById('pagination-buttons');
    const btnPrevEl = document.getElementById('prev-page');
    const btnNextEl = document.getElementById('next-page');

    if (!paginacaoContainer) return;

    paginacaoContainer.innerHTML = '';
    const totalPaginas = Math.ceil(window.todasAsProperties.length / itensPorPagina);

    // Classes para o visual desejado
    const baseBtn =
        'px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition text-sm font-medium focus:outline-none';
    const activeBtn = 'bg-blue-600 text-white border-blue-600';

    // Mostrar no máximo 7 páginas
    let startPage = Math.max(1, window.paginaAtual - 3);
    let endPage = Math.min(totalPaginas, startPage + 6);
    if (endPage - startPage < 6) {
        startPage = Math.max(1, endPage - 6);
    }

    // Botão "1" se não for a primeira página
    if (startPage > 1) {
        const btn = document.createElement('button');
        btn.className = baseBtn;
        btn.innerText = '1';
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            window.paginaAtual = 1;
            renderizarPagina(window.paginaAtual);
        });
        paginacaoContainer.appendChild(btn);

        // Ellipsis se houver gap
        if (startPage > 2) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-2 py-1 text-gray-500 pagination-ellipsis';
            ellipsis.innerText = '...';
            paginacaoContainer.appendChild(ellipsis);
        }
    }

    // Botões de página
    for (let i = startPage; i <= endPage; i++) {
        const btn = document.createElement('button');
        const isActive = i === window.paginaAtual;
        btn.className = baseBtn + (isActive ? ' ' + activeBtn : '');
        btn.innerText = i;
        btn.disabled = isActive;

        if (isActive) {
            btn.classList.add('active');
        }

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if (window.paginaAtual !== i) {
                window.paginaAtual = i;
                renderizarPagina(window.paginaAtual);
            }
        });
        paginacaoContainer.appendChild(btn);
    }

    // Botão última página se não for a última
    if (endPage < totalPaginas) {
        // Ellipsis se houver gap
        if (endPage < totalPaginas - 1) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'px-2 py-1 text-gray-500 pagination-ellipsis';
            ellipsis.innerText = '...';
            paginacaoContainer.appendChild(ellipsis);
        }

        const btn = document.createElement('button');
        btn.className = baseBtn;
        btn.innerText = totalPaginas;
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            window.paginaAtual = totalPaginas;
            renderizarPagina(window.paginaAtual);
        });
        paginacaoContainer.appendChild(btn);
    }

    // Atualizar botões de navegação
    if (btnPrevEl) {
        btnPrevEl.disabled = window.paginaAtual === 1;
        btnPrevEl.onclick = (e) => {
            e.preventDefault();
            if (window.paginaAtual > 1) {
                window.paginaAtual--;
                renderizarPagina(window.paginaAtual);
            }
        };
    }

    if (btnNextEl) {
        btnNextEl.disabled = window.paginaAtual === totalPaginas;
        btnNextEl.onclick = (e) => {
            e.preventDefault();
            if (window.paginaAtual < totalPaginas) {
                window.paginaAtual++;
                renderizarPagina(window.paginaAtual);
            }
        };
    }
}

// A paginação agora é gerada dinamicamente por atualizarPaginacao()
