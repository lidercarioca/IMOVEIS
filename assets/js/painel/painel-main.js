window.paginaAtual = 1;
const itensPorPagina = 12;
window.todasAsProperties = [];
let categoryUpdateInterval;

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
function normalizeCategories(raw) {
  const mapping = {
    'Apartamentos': 'apartment',
    'Casas': 'house',
    'Comercial': 'commercial',
    'Terrenos': 'land'
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
function formatarArea(area) {
  if (!area) return '0,00';
  try {
    let valor = String(area);
    if (valor.includes('.')) {
      const num = parseFloat(valor);
      return num.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
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
      maximumFractionDigits: 2
    });
  } catch (e) {
    console.error('Erro ao formatar área:', e);
    return '0,00';
  }
}

/**
 * Retorna a quantidade de quartos a partir do array de features.
 */
function obterQtdQuartos(features) {
  if (!Array.isArray(features)) return "N/D";
  const quartos = features.find(f => f.toLowerCase().includes("quarto"));
  return quartos || "N/D";
}

/**
 * Retorna a quantidade de banheiros a partir do array de features.
 */
function obterQtdBanheiros(features) {
  if (!Array.isArray(features)) return "N/D";
  const banheiros = features.find(f => f.toLowerCase().includes("banheiro"));
  return banheiros || "N/D";
}

/**
 * Carrega e exibe o gráfico de categorias de imóveis no dashboard
 * @param {boolean} isRealtime - Indica se é uma atualização em tempo real
 */
function loadPropertyCategories(isRealtime = false) {
  const container = document.getElementById('property-categories');
  if (!container) return;

  // Se for atualização em tempo real, busca os dados mais recentes
  if (isRealtime) {
    // Adiciona classe de loading
    container.classList.add('updating');
    
    // Usa uma API separada para as categorias
    fetch('/api/getPropertiesCategories.php')
      .then(response => response.json())
      .then(data => {
        // Armazena apenas os dados das categorias, separado dos dados de desempenho
        window.categoryData = data;
        window.categoryData = normalizeCategories(data.categories);
        updateCategoryDisplay();
      })
      .catch(error => {
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
    'apartment': 0,
    'house': 0,
    'commercial': 0,
    'land': 0
  };

  // Conta imóveis por categoria
  properties.forEach(property => {
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
      icon: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-building"></i></div>'
    },
    { 
      name: 'Casas', 
      count: categoryCounts['house'], 
      color: 'rgb(var(--cor-secundaria-rgb))',
      icon: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-green-100 text-green-600"><i class="fas fa-home"></i></div>'
    },
    { 
      name: 'Comercial', 
      count: categoryCounts['commercial'], 
      color: 'rgb(var(--cor-destaque-rgb))',
      icon: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-orange-100 text-orange-600"><i class="fas fa-store"></i></div>'
    },
    { 
      name: 'Terrenos', 
      count: categoryCounts['land'], 
      color: 'rgb(156, 163, 175)',
      icon: '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-600"><i class="fas fa-map"></i></div>'
    }
  ];

  categories.forEach(category => {
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
function abrirModalDetalhes(id) {
  fetch(`api/getPropertyById.php?id=${id}`)
    .then((res) => {
      if (!res.ok) {
        throw new Error('Erro na resposta da API');
      }
      return res.json();
    })
    .then((data) => {
      // Com o PDO, os dados vêm dentro de data.data
      const property = data.data;
      const titleEl = document.getElementById("modal-title");
      const priceEl = document.getElementById("modal-price");
      const descEl = document.getElementById("modal-description");
      const locEl = document.getElementById("modal-location");
      const areaEl = document.getElementById("modal-area");
      const yearEl = document.getElementById("modal-yearBuilt");
      const featuresList = document.getElementById("modal-features");
      const imageContainer = document.getElementById("modal-carousel-images");
      
      if (!titleEl || !priceEl || !descEl || !locEl || !areaEl || !yearEl || !featuresList || !imageContainer) {
        console.error("Alguns elementos do modal não foram encontrados.");
        window.utils.mostrarErro("Erro ao abrir detalhes do imóvel: elementos do modal não encontrados");
        return;
      }
      
      // Inicializa o modal Bootstrap
      const modalEl = document.getElementById("propertyModal");
      if (!modalEl) {
        console.error("Modal não encontrado no DOM");
        window.utils.mostrarErro("Erro ao abrir detalhes do imóvel: modal não encontrado");
        return;
      }

      titleEl.textContent = property.title || '';
      
      // O PDO já retorna o price como número
      const price = typeof property.price === 'number' ? property.price : 0;
      priceEl.textContent = `R$ ${price.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
      
      // O PDO já retorna a área como número
      const area = typeof property.area === 'number' ? property.area : 0;
      areaEl.textContent = `${area}m²`;
      descEl.textContent = property.description;
      // Definir cor do ícone do modal igual ao badge do card
      let iconColor = 'text-blue-600';
      const tipoTransacao = (property.transactionType || '').toLowerCase();
      const tipoImovel = (property.type || '').toLowerCase();
      if (tipoTransacao.includes('comercial') || tipoTransacao.includes('commercial') || tipoImovel === 'comercial' || tipoImovel === 'commercial') {
        iconColor = 'text-orange-500';
      } else if (tipoTransacao === 'aluguel' || tipoTransacao === 'alugar' || tipoTransacao === 'rent' || tipoImovel === 'aluguel') {
        iconColor = 'text-green-600';
      } else if (tipoTransacao === 'venda' || tipoTransacao === 'sale' || tipoImovel === 'venda') {
        iconColor = 'text-blue-600';
      }
      locEl.innerHTML = `<i class="fas fa-map-marker-alt me-2 ${iconColor}"></i> ${property.location}${property.neighborhood ? ' - ' + property.neighborhood : ''}`;
      // Atualiza os valores de área e ano
  areaEl.innerHTML = `<i class="fas fa-ruler-combined ${iconColor} me-2"></i> <span class="fw-semibold text-dark">${formatarArea(property.area)}m²</span>`;
  yearEl.innerHTML = `<i class="fas fa-calendar-alt ${iconColor} me-2"></i> <span class="fw-semibold text-dark">${property.yearBuilt || 'N/A'}</span>`;

      // Exibe quartos, banheiros e vagas no modal (com ícones) logo abaixo do quadrado azul e acima da descrição
      const bedrooms = property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A';
      const bathrooms = property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A';
      const garage = property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A';
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
          <i class="fas fa-car ${iconColor}"></i> <span class="fw-semibold text-dark">${garage} Vagas</span>
        </div>
      `;

      // Atualiza as classes e estrutura do container
      areaEl.parentElement.parentElement.className = 'd-flex justify-content-between bg-blue-50 p-3 rounded mb-4';
      areaEl.parentElement.className = 'd-flex flex-column';
      yearEl.parentElement.className = 'd-flex flex-column text-end';

      featuresList.innerHTML = "";
      if (Array.isArray(property.features)) {
        property.features.forEach((f) => {
          const li = document.createElement("li");
          let icon = '';
          if (f.toLowerCase().includes('quarto')) {
            icon = `<i class=\"fas fa-bed ${iconColor} me-2\"></i>`;
          } else if (f.toLowerCase().includes('banheiro')) {
            icon = `<i class=\"fas fa-bath ${iconColor} me-2\"></i>`;
          } else {
            icon = `<i class=\"fas fa-check ${iconColor} me-2\"></i>`;
          }
          li.innerHTML = `${icon}${f}`;
          featuresList.appendChild(li);
        });
      }

      const imagens = typeof property.images === "string" ? JSON.parse(property.images) : property.images;
      imageContainer.innerHTML = "";
      if (Array.isArray(imagens) && imagens.length > 0) {
        imagens.forEach((img, index) => {
          const item = document.createElement("div");
          item.className = `carousel-item ${index === 0 ? "active" : ""}`;
          item.innerHTML = `
            <img src="assets/imagens/${property.id}/${img}" class="d-block w-100 h-64 object-cover rounded" alt="Imagem">
          `;
          imageContainer.appendChild(item);
        });
      }

      const whatsappBtn = document.getElementById("modal-whatsapp");
      if (whatsappBtn) {
        whatsappBtn.href = `https://wa.me/5551999999999?text=Olá! Tenho interesse no imóvel: ${property.title}`;
      }

      const modal = new bootstrap.Modal(document.getElementById("propertyModal"));
      modal.show();
    })
    .catch((err) => {
      console.error("Erro ao carregar detalhes do imóvel:", err);
      window.utils.mostrarErro("Erro ao abrir detalhes do imóvel.");
    });
}
/**
 * Busca e renderiza os imóveis no painel administrativo, com paginação e ações de editar/excluir.
 */
async function carregarImoveisPainel() {
  try {
    const res = await fetch("api/getProperties.php");
    const properties = await res.json();
    
    // Salva os dados globalmente
    window.todasAsProperties = properties;
    window.propertyData = properties;
    
    // Renderiza os imóveis
    window.renderizarPagina(window.paginaAtual);
    
    // Atualiza o dashboard se estivermos na aba dashboard
    const dashboardPane = document.getElementById('dashboard');
    if (dashboardPane && dashboardPane.classList.contains('active')) {
      loadPropertyCategories();
      loadRecentProperties();
    }
  } catch (err) {
    console.error("Erro ao carregar imóveis:", err);
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

  // Pega os 5 imóveis mais recentes
  const recentProperties = [...properties]
    .sort((a, b) => Number(b.id) - Number(a.id))
    .slice(0, 5);

  container.innerHTML = '';
  
  recentProperties.forEach(property => {
    let statusClass = 'badge-status status-inactive';
    let statusText = property.status || 'Inativo';
    let priceFormatted = '';
    let location = property.location || '';

    // Se o utils já estiver carregado, usa suas funções
    if (window.utils) {
      statusClass = window.utils.getStatusClass(property.status);
      statusText = window.utils.processarStatus(property.status);
      priceFormatted = window.utils.formatarPreco(property.price);
      location = property.neighborhood ? 
        `${property.location} - ${property.neighborhood}` : 
        property.location;
    } else {
      // Fallback simples se utils não estiver disponível
      priceFormatted = property.price ? `R$ ${Number(property.price).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : 'Sob consulta';
      location = property.neighborhood ? 
        `${property.location} - ${property.neighborhood}` : 
        property.location;
    }

    const typeIcons = {
      'apartment': '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600"><i class="fas fa-building"></i></div>',
      'house': '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-green-100 text-green-600"><i class="fas fa-home"></i></div>',
      'commercial': '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-orange-100 text-orange-600"><i class="fas fa-store"></i></div>',
      'land': '<div class="w-8 h-8 rounded-lg flex items-center justify-center bg-gray-100 text-gray-600"><i class="fas fa-map"></i></div>'
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

document.addEventListener("DOMContentLoaded", () => {
  carregarImoveisPainel();
  loadRecentProperties();
});


/**
 * Renderiza a página de imóveis atual na paginação do painel.
 */
window.renderizarPagina = function(pagina) {
  const container = document.getElementById("properties-container");
  if (!container) return;
  container.innerHTML = "";

  const inicio = (pagina - 1) * itensPorPagina;
  const fim = inicio + itensPorPagina;
  const propriedadesPagina = window.todasAsProperties.slice(inicio, fim);

  // Sempre rola para o topo do container ao trocar de página
  const containerTop = container.getBoundingClientRect().top + window.scrollY - 100;
  window.scrollTo({ top: containerTop, behavior: 'smooth' });

  propriedadesPagina.forEach((property) => {
    const card = document.createElement("div");
    card.className = "property-card bg-white rounded-xl shadow-lg border border-gray-100 p-0 flex flex-col transition-transform duration-300 hover:scale-105 hover:shadow-2xl";

    const images = typeof property.images === "string" ? JSON.parse(property.images) : property.images;
    const imageSrc = images && images.length > 0 ? images[0] : "assets/imagens/default.jpg";
    const colors = window.utils.getPropertyColors(property);
    let tipoLabel = colors.badgeText;
    let btnDetalhesColor = colors.button;
    let iconColor = colors.icon;
    let btnStyle = '';
    let quartos = property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A';
    let banheiros = property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A';
    let vagas = property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A';
    // Usa a função getStatusClass do utils se disponível, ou fallback para classe padrão
    function getStatusClass(status) {
      if (window.utils) {
        return window.utils.getStatusClass(status);
      }
      return 'badge-status status-inactive';
    }
    card.innerHTML = `
      <div class="relative">
        <img src="${imageSrc}" class="w-full h-56 object-cover rounded-t-xl" alt="Imagem do imóvel">
        <span class="absolute top-3 left-3 px-3 py-1 rounded-full text-white text-xs font-bold shadow" style="${colors.badge} z-index:2;">${tipoLabel}</span>
        <div class="absolute top-3 right-3 flex gap-2 z-10">
          <button class="bg-white rounded-full p-2 shadow hover:bg-yellow-100 transition btn-editar-imovel" style="border:none;" title="Editar Imóvel" data-id="${property.id}">
            <i class="fa-solid fa-pen text-yellow-600 text-lg"></i>
          </button>
          ${window.isAdmin ? `<button class="bg-white rounded-full p-2 shadow hover:bg-red-100 transition btn-excluir-imovel" style="border:none;" title="Excluir Imóvel" data-id="${property.id}"><i class="fa-solid fa-trash text-red-500 text-lg"></i></button>` : ''}
        </div>
      </div>
      <div class="p-6 flex-1 flex flex-col justify-between">
        <div class="flex justify-between items-center mb-2">
          <h4 class="text-lg font-bold text-gray-800">${property.title}</h4>
            <span class="text-blue-600 font-bold text-lg">${window.utils.formatarPreco(property.price, tipoLabel === 'Para Alugar', property.type)}</span>
        </div>
        <div class="flex justify-between items-center mb-2">
          <p class="text-gray-500 text-sm flex items-center mb-0">
            <i class="fas fa-map-marker-alt mr-2" style="${colors.icon}"></i> 
            ${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}
          </p>
          <span class="${getStatusClass(property.status)}">${window.utils.processarStatus(property.status)}</span>
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
            <span>${formatarArea(property.area)}m²</span>
          </div>
        </div>
        <button class="text-base py-2 px-4 rounded-lg transition ver-detalhes font-semibold w-full mt-2 text-white" style="${btnDetalhesColor} ${btnStyle}" data-id="${property.id}"><i class="fas fa-search me-2"></i>
          Ver Detalhes
        </button>
      </div>
    `;
    // Eventos dos botões
    const btnEditar = card.querySelector('.btn-editar-imovel');
    if (btnEditar) {
      btnEditar.addEventListener('click', function (e) {
        e.stopPropagation();
        const tabAdd = document.querySelector('a[href="#add-property"]');
        if (tabAdd) tabAdd.click();
        setTimeout(() => {
          const form = document.getElementById('property-form');
          if (!form) {
            window.utils.mostrarErro('Formulário de imóvel não encontrado!');
            return;
          }
          form.reset();
          // Define o ID do imóvel sendo editado
          const editIdInput = form.querySelector('#edit_id');
          if (editIdInput) editIdInput.value = property.id;

          // Atualiza o título do formulário para modo de edição
          const formTitle = document.querySelector('#add-property h2');
          if (formTitle) formTitle.textContent = 'Editar Imóvel';

          // Preenche os campos do formulário
          for (const [key, value] of Object.entries(property)) {
            // Tratamento especial para features
            if (key === 'features') {
              const features = typeof value === 'string' ? JSON.parse(value) : value;
              if (Array.isArray(features)) {
                features.forEach(feature => {
                  const checkbox = form.querySelector(`input[name="features[]"][value="${feature}"]`);
                  if (checkbox) checkbox.checked = true;
                });
              }
              continue;
            }
            // Tratamento especial para status
            if (key === 'status') {
              const statusRadio = form.querySelector(`input[name="status"][value="${value}"]`);
              if (statusRadio) statusRadio.checked = true;
              continue;
            }
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
              if (input.type === 'radio') {
                const radios = form.querySelectorAll(`input[name="${key}"]`);
                radios.forEach(r => { r.checked = (r.value === String(value)); });
              } else if (input.type === 'checkbox') {
                input.checked = !!value;
              } else {
                if (key === 'area') {
                  const areaNum = parseFloat(value);
                  if (!isNaN(areaNum)) {
                    input.value = areaNum.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace('.', ',');
                  }
                } else if (key === 'price') {
                  const priceNum = parseFloat(value);
                  if (!isNaN(priceNum)) {
                    input.value = priceNum.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                  }
                } else {
                  input.value = value;
                }
              }
            }
          }
          // Exibir imagens do imóvel para edição
          const imagensEditContainerId = 'property-edit-images';
          let imagensEditContainer = document.getElementById(imagensEditContainerId);
          if (!imagensEditContainer) {
            imagensEditContainer = document.createElement('div');
            imagensEditContainer.id = imagensEditContainerId;
            imagensEditContainer.className = 'mb-3 d-flex flex-wrap gap-3';
            // Insere antes do botão de upload, se existir
            const uploadBtn = document.getElementById('property-upload-btn');
            if (uploadBtn && uploadBtn.parentElement) {
              uploadBtn.parentElement.insertBefore(imagensEditContainer, uploadBtn);
            } else {
              form.appendChild(imagensEditContainer);
            }
          }
          imagensEditContainer.innerHTML = '<span class="fw-semibold mb-2 d-block">Imagens do imóvel:</span>';
          fetch(`api/getPropertyImages.php?id=${property.id}`)
            .then(res => res.json())
            .then(imgs => {
              if (!Array.isArray(imgs) || imgs.length === 0) {
                imagensEditContainer.innerHTML += '<span class="text-muted">Nenhuma imagem encontrada.</span>';
                return;
              }
              imgs.forEach(img => {
                const imgBox = document.createElement('div');
                imgBox.className = 'position-relative';
                imgBox.style = 'display:inline-block;';
                imgBox.innerHTML = `
                  <img src="${img}" style="max-width:120px;max-height:120px;border-radius:8px;box-shadow:0 2px 8px #0002;" alt="Imagem">
                  <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" title="Excluir imagem" style="border-radius:50%;padding:2px 6px;z-index:2;">&times;</button>
                `;
                const btnExcluir = imgBox.querySelector('button');
                btnExcluir.onclick = function() {
                  if (confirm('Deseja realmente excluir esta imagem?')) {
                    const fd = new FormData();
                    fd.append('id', property.id);
                    fd.append('file', img.split('/').pop());
                    fetch('api/deletePropertyImage.php', {
                      method: 'POST',
                      body: fd
                    })
                      .then(res => res.json())
                      .then(json => {
                        if (json.success) {
                          imgBox.remove();
                          if (typeof window.updateDashboardCounts === 'function') {
                            window.updateDashboardCounts();
                          }
                        } else {
                          window.utils.mostrarErro(json.message || 'Erro ao excluir imagem.');
                        }
                      })
                      .catch(() => window.utils.mostrarErro('Erro ao excluir imagem.'));
                  }
                };
                imagensEditContainer.appendChild(imgBox);
              });
            });
          if (property.location) {
            const addressInput = form.querySelector('[name="address"]');
            if (addressInput) addressInput.value = property.location;
          }
          if (property.neighborhood) {
            const bairroInput = form.querySelector('[name="neighborhood"]');
            if (bairroInput) bairroInput.value = property.neighborhood;
          }
          if (property.city) {
            const cidadeInput = form.querySelector('[name="city"]');
            if (cidadeInput) cidadeInput.value = property.city;
          }
          if (property.state) {
            const estadoInput = form.querySelector('[name="state"]');
            if (estadoInput) estadoInput.value = property.state;
          }
          if (property.zip) {
            const cepInput = form.querySelector('[name="zip"]');
            if (cepInput) cepInput.value = property.zip;
          }
          form.querySelectorAll('input[name="features[]"]').forEach(cb => cb.checked = false);
          if (Array.isArray(property.features)) {
            property.features.forEach(f => {
              const cb = form.querySelector(`input[name="features[]"][value="${f}"]`);
              if (cb) cb.checked = true;
            });
          }
          let editId = form.querySelector('input[name="edit_id"]');
          if (!editId) {
            editId = document.createElement('input');
            editId.type = 'hidden';
            editId.name = 'edit_id';
            form.appendChild(editId);
          }
          editId.value = property.id;
        }, 100);
      });
    }
    if (window.isAdmin) {
      const btnExcluir = card.querySelector('.btn-excluir-imovel');
      if (btnExcluir) {
        btnExcluir.addEventListener('click', function (e) {
          e.stopPropagation();
          if (confirm('Tem certeza que deseja excluir este imóvel?')) {
            const formData = new FormData();
            formData.append('id', property.id);
            fetch('api/deleteProperty.php', {
              method: 'POST',
              body: formData
            })
              .then(res => res.json())
              .then(data => {
                if (data.success) {
                  card.remove();
                  if (typeof window.updateDashboardCounts === 'function') {
                    window.updateDashboardCounts();
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
    }
    card.querySelector('.ver-detalhes').addEventListener('click', function () {
      abrirModalDetalhes(property.id);
    });
    container.appendChild(card);
  });

  // Atualiza a paginação dinâmica
  atualizarPaginacao();
}

function atualizarPaginacao() {
  // Seleciona o container correto da paginação do painel
  const paginacaoContainer = document.querySelector('.flex.space-x-1');
  if (!paginacaoContainer) return;
  paginacaoContainer.innerHTML = '';
  const totalPaginas = Math.ceil(window.todasAsProperties.length / itensPorPagina);
  // Classes para o visual desejado
  const baseBtn = 'px-3 py-1 border border-gray-300 rounded-md hover:bg-gray-100 transition text-base font-medium focus:outline-none';
  const activeBtn = 'bg-blue-600 text-white';
  // Botão anterior
  const btnPrev = document.createElement('button');
  btnPrev.className = baseBtn;
  btnPrev.innerHTML = '<i class="fas fa-chevron-left"></i>';
  btnPrev.disabled = paginaAtual === 1;
  btnPrev.addEventListener('click', (e) => {
    e.preventDefault();
    if (paginaAtual > 1) {
      paginaAtual--;
      renderizarPagina(paginaAtual);
    }
  });
  paginacaoContainer.appendChild(btnPrev);
  // Botões de página
  for (let i = 1; i <= totalPaginas; i++) {
    const btn = document.createElement('button');
    btn.className = baseBtn + (i === paginaAtual ? ' ' + activeBtn : '');
    btn.innerText = i;
    btn.disabled = i === paginaAtual;
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      if (paginaAtual !== i) {
        paginaAtual = i;
        renderizarPagina(paginaAtual);
      }
    });
    paginacaoContainer.appendChild(btn);
  }
  // Botão próximo
  const btnNext = document.createElement('button');
  btnNext.className = baseBtn;
  btnNext.innerHTML = '<i class="fas fa-chevron-right"></i>';
  btnNext.disabled = paginaAtual === totalPaginas;
  btnNext.addEventListener('click', (e) => {
    e.preventDefault();
    if (paginaAtual < totalPaginas) {
      paginaAtual++;
      renderizarPagina(paginaAtual);
    }
  });
  paginacaoContainer.appendChild(btnNext);
}


// A paginação agora é gerada dinamicamente por atualizarPaginacao()
