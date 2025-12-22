document.addEventListener("DOMContentLoaded", async () => {
  await carregarImoveis();
  carregarHeroBanners();
// Carrega banners do backend e exibe na section hero
/**
 * Carrega os banners do carrossel principal
 * @returns {Promise<void>}
 */
async function carregarHeroBanners() {
  const res = await fetch('/api/getBanners.php');
  const banners = await res.json();
  const hero = document.getElementById('carousel-container');
  if (!hero || !Array.isArray(banners) || banners.length === 0) return;

  let indicators = banners.length > 1 ? `
    <div class="carousel-indicators">
      ${banners.map((_, idx) => `
        <button type="button" data-bs-target="#heroBannerCarousel" data-bs-slide-to="${idx}"${idx === 0 ? ' class="active" aria-current="true"' : ''} aria-label="Slide ${idx + 1}"></button>
      `).join('')}
    </div>
  ` : '';

  let controls = banners.length > 1 ? `
    <button class="carousel-control-prev" type="button" data-bs-target="#heroBannerCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Anterior</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroBannerCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Próximo</span>
    </button>
  ` : '';

  let carouselHtml = `
    <div id="heroBannerCarousel" class="carousel slide position-relative overflow-hidden" data-bs-ride="carousel" style="height: 500px; z-index: 1;">
      ${indicators}
      <div class="carousel-inner h-100">
        ${banners.map((banner, idx) => `
          <div class="carousel-item${idx === 0 ? ' active' : ''} h-100">
            <img src="${banner.image_url}" 
                 class="d-block w-100 h-100" 
                 alt="${banner.title || 'Banner'}" 
                 style="object-fit: cover;"
                 loading="${idx === 0 ? 'eager' : 'lazy'}">
            <div class="carousel-caption d-block position-absolute start-50 translate-middle-x bottom-0 mb-4 bg-dark bg-opacity-50 rounded-3 p-4" style="max-width: min(800px, 90%);">
              ${banner.title ? `<h2 class="fs-3 fw-bold mb-2">${banner.title}</h2>` : ''}
              ${banner.description ? `<p class="mb-3 d-none d-md-block">${banner.description}</p>` : ''}
              ${banner.button_text && banner.link ? `<a href="${banner.link}" class="btn btn-dynamic-primary fw-semibold px-4 py-2">${banner.button_text}</a>` : ''}
            </div>
          </div>
        `).join('')}
      </div>
      ${controls}
    </div>
  `;
  hero.innerHTML = carouselHtml;
}
  // Expor funções e dados globais para integração com filtros
  window.dadosImoveis = dadosImoveis;
  window.carregarImoveis = carregarImoveis;

  // Filtros: Tipo, Bairro, Preço
  const selectTipo = document.getElementById('select-tipo');
  const selectBairro = document.getElementById('select-localizacao');
  const selectPreco = document.getElementById('select-preco');
  const btnBuscar = document.querySelector('button.bg-blue-600, button:has(.fa-search)');

  function filtrarImoveis() {
    let filtrados = window.dadosImoveisOriginal ? [...window.dadosImoveisOriginal] : [...window.dadosImoveis];
    // Filtro por tipo
    if (selectTipo && selectTipo.selectedIndex > 0) {
      const tipo = selectTipo.value.trim().toLowerCase();
      filtrados = filtrados.filter(imv => (imv.type || '').toLowerCase() === tipo);
    }
    // Filtro por bairro/localização
    if (selectBairro && selectBairro.selectedIndex > 0) {
      const bairro = selectBairro.value.trim().toLowerCase();
      filtrados = filtrados.filter(imv => (imv.neighborhood || '').toLowerCase() === bairro || (imv.location || '').toLowerCase() === bairro);
    }
    // Filtro por preço
    if (selectPreco && selectPreco.selectedIndex > 0) {
      const faixa = selectPreco.value;
      filtrados = filtrados.filter(imv => {
        const preco = parseFloat(imv.price || 0);
        if (faixa.includes('Até')) return preco <= 200000;
        if (faixa.includes('200.000 - 500.000')) return preco > 200000 && preco <= 500000;
        if (faixa.includes('500.000 - 1.000.000')) return preco > 500000 && preco <= 1000000;
        if (faixa.includes('Acima')) return preco > 1000000;
        return true;
      });
    }
    window.renderizarImoveis(filtrados);
  }

  if (selectTipo) selectTipo.addEventListener('change', filtrarImoveis);
  if (selectBairro) selectBairro.addEventListener('change', filtrarImoveis);
  if (selectPreco) selectPreco.addEventListener('change', filtrarImoveis);
  if (btnBuscar) btnBuscar.addEventListener('click', function(e) { e.preventDefault(); filtrarImoveis(); });

  // Botão "Ver Todos os Imóveis" abre nova página
  let btnVerTodos = null;
  document.querySelectorAll('button').forEach(b => {
    if (b.textContent && b.textContent.trim().toLowerCase() === 'ver todos os imóveis') btnVerTodos = b;
  });
  if (btnVerTodos) {
    btnVerTodos.addEventListener('click', function(e) {
      e.preventDefault();
      window.open('/views/public/todos-imoveis.html', '_blank');
    });
  }
});

let dadosImoveis = [];

/**
 * Renderiza os cards de imóveis na listagem pública, a partir de um array filtrado.
 */
window.renderizarImoveis = function(imoveis) {
  const container = document.getElementById("property-list");
  if (!container) return;
  container.innerHTML = "";
  // Se estiver na todos-imoveis.html, sempre mostrar todos os imóveis
  let mostrarTodos = false;
  if (window.location.pathname.includes('todos-imoveis.html')) {
    mostrarTodos = true;
  } else {
    const selectTipo = document.querySelector('select:not([id])');
    const selectBairro = document.getElementById('select-localizacao');
    const selectPreco = document.querySelectorAll('select')[2];
    if (
      (selectTipo && selectTipo.selectedIndex > 0) ||
      (selectBairro && selectBairro.selectedIndex > 0) ||
      (selectPreco && selectPreco.selectedIndex > 0)
    ) {
      mostrarTodos = true;
    }
  }
  const lista = mostrarTodos ? imoveis : imoveis.slice(0, 9);
  lista.forEach((property) => {
    if (property.status && ["inativo","inactive","pendente","pending"].includes(property.status.toLowerCase())) {
      return;
    }
    const colDiv = document.createElement("div");
    colDiv.className = "col";
    
    const card = document.createElement("div");
    card.className = "card h-100 shadow-sm";
    
    const images = typeof property.images === "string" ? JSON.parse(property.images) : property.images;
    let imageSrc = images.length > 0 ? images[0] : "/assets/imagens/default.jpg";
    // Corrige caminho relativo para absoluto
    if (imageSrc && !imageSrc.startsWith('/')) {
      imageSrc = '/' + imageSrc.replace(/^\/+/, '');
    }
    
    // Obter cores e estilos do imóvel
    const colors = window.utils.getPropertyColors(property);
    let tipoLabel = colors.badgeText;
    let btnStyle = colors.button;
    let features = window.utils.processarFeatures(property.features);
    
    // Criar ID único para o card
    const cardId = `property-card-${property.id || Math.random().toString(36).substr(2, 9)}`;
    card.id = cardId;
    
    // Informações do imóvel
    let quartos = property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A';
    let banheiros = property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A';
    let vagas = property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A';
    let suites = property.suites !== undefined && property.suites !== null && property.suites !== '' ? property.suites : 'N/A';

    const processedStatus = window.utils.processarStatus(property.status);

    // Monta endereço completo para link do Google Maps
    const addressParts = [];
    if (property.address) addressParts.push(property.address);
    if (property.location) addressParts.push(property.location);
    if (property.neighborhood) addressParts.push(property.neighborhood);
    if (property.city) addressParts.push(property.city);
    if (property.state) addressParts.push(property.state);
    if (property.zip) addressParts.push(property.zip);
    const fullAddress = addressParts.filter(Boolean).join(', ');
    const mapsUrl = fullAddress ? 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(fullAddress) : '';
    const mapsLinkHtml = fullAddress ? `
      <a href="javascript:void(0)" rel="noopener" class="d-block text-primary mt-1" onclick="(function(){const w=900,h=600;const left=Math.max(0,Math.round((screen.width-w)/2));const top=Math.max(0,Math.round((screen.height-h)/2));const popup = window.open('${mapsUrl}','rr_map_popup','width='+w+',height='+h+',left='+left+',top='+top+',menubar=no,toolbar=no,resizable=yes,scrollbars=yes'); if(!popup){ window.open('${mapsUrl}','_blank'); } return false; })()">
        <i class="fas fa-map-marker-alt me-2"></i>Ver no Google Maps
      </a>` : '';

    let ribbon = '';
    let showBadge = true;

    // Segurança: garante que property.type exista
    const typeClass = property.type ? property.type : 'padrao';

    // Verifica status para ribbon
    if (processedStatus === 'vendido' || processedStatus === 'alugado') {
      if (property.type === 'commercial') {
        // Mantém a classe do tipo, mas texto do status
        ribbon = `<div class="property-ribbon ${typeClass}" style="${colors.badge}">${processedStatus === 'vendido' ? 'VENDIDO' : 'ALUGADO'}</div>`;
      } else {
        // Outros tipos usam classe do status
        const statusClass = processedStatus === 'vendido' ? 'vendido' : 'aluguel';
        ribbon = `<div class="property-ribbon ${statusClass}" style="${colors.badge}">${processedStatus === 'vendido' ? 'VENDIDO' : 'ALUGADO'}</div>`;
      }
      showBadge = false;
    }

    // Monta o conteúdo do card
    card.innerHTML = `
      <div class="position-relative">
        <img src="${imageSrc}" class="card-img-top object-fit-cover" style="height: 224px;" alt="Imagem do imóvel">
        ${ribbon ? ribbon : ''}
        ${showBadge ? `<span class="position-absolute top-4 left-4 start-0 px-3 py-1 rounded-lg font-medium text-white badge-grande" style="${colors.badge}">${tipoLabel}</span>` : ''}
        <button type="button" class="btn btn-light position-absolute top-0 end-0 m-3 rounded-circle p-2 favorite-btn" data-property-id="${property.id}" aria-label="Favoritar">
          <i class="fa-${window.favoritesManager && window.favoritesManager.isFavorite(property.id) ? 'solid' : 'regular'} fa-heart text-danger"></i>
        </button>
      </div>
      <div class="card-body d-flex flex-column h-100">
        <h4 class="card-title h5 fw-bold mb-2">${property.title}</h4>
        <p class="card-text text-secondary small mb-3">
          <i class="fas fa-map-marker-alt me-2" style="${colors.icon}"></i>
          ${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}
          ${mapsLinkHtml}
        </p>
          <div class="row row-cols-2 g-2 text-secondary small mb-3">
          <div class="col d-flex align-items-center">
            <i class="fas fa-bed me-2" style="${colors.icon}"></i>
            <span>${quartos} Quartos</span>
          </div>
          <div class="col d-flex align-items-center">
            <i class="fas fa-bath me-2" style="${colors.icon}"></i>
            <span>${banheiros} Banheiros</span>
          </div>
          <div class="col d-flex align-items-center">
            <i class="fas fa-door-closed me-2" style="${colors.icon}"></i>
            <span>${suites} Suítes</span>
          </div>
          <div class="col d-flex align-items-center">
            <i class="fas fa-car me-2" style="${colors.icon}"></i>
            <span>${vagas} Vagas</span>
          </div>
          <div class="col d-flex align-items-center">
            <i class="fas fa-ruler-combined me-2" style="${colors.icon}"></i>
            <span>${property.area}m²</span>
          </div>
        </div>
        <div class="mt-auto">
          <p class="text-primary fs-4 fw-bold d-block">
            ${window.utils.formatarPreco(property.price, tipoLabel === 'Aluguel', property.type)}
          </p>
          <button class="btn btn-detalhes w-100 ver-detalhes fw-semibold py-3 rounded-3" style="${colors.button}" data-id="${property.id}">
            <i class="fas fa-search me-2"></i>Ver Detalhes
          </button>
        </div>
      </div>
    `;
    
    // Adiciona eventos
    card.querySelector('.ver-detalhes').addEventListener('click', function() {
      if (window.showPropertyDetails) {
        window.showPropertyDetails(property.id);
      } else if (typeof showPropertyDetails === 'function') {
        showPropertyDetails(property.id);
      }
    });

    // Adiciona o card ao wrapper da coluna e depois ao container
    colDiv.appendChild(card);
    container.appendChild(colDiv);
  });
};

/**
 * Busca os imóveis do backend e popula a listagem pública, além de expor os dados globalmente.
 */
/**
 * Carrega a lista de imóveis do servidor
 * @returns {Promise<void>}
 */
async function carregarImoveis() {
  try {
    const res = await fetch("/api/getProperties.php");
    const properties = await res.json();
  dadosImoveis = properties;
  window.dadosImoveis = dadosImoveis;
  window.dadosImoveisOriginal = [...properties];
  // Dispara evento para informar que os dados dos imóveis foram carregados
  try { document.dispatchEvent(new CustomEvent('dadosImoveisLoaded', { detail: { count: dadosImoveis.length } })); } catch (e) { console.warn('Não foi possível dispatchar evento dadosImoveisLoaded', e); }

    const container = document.getElementById("property-list");
    if (!container) return;
    container.innerHTML = "";

    properties.forEach((property) => {
      // Filtrar imóveis inativos ou pendentes no site público
      if (property.status && ["inativo","inactive","pendente","pending"].includes(property.status.toLowerCase())) {
        return; // Não exibe este imóvel no index.html
      }
      const card = document.createElement("div");
      card.className = "property-card bg-white rounded-xl shadow-lg border border-gray-100 p-0 flex flex-col transition-transform duration-300 hover:scale-105 hover:shadow-2xl";

      const images = typeof property.images === "string" ? JSON.parse(property.images) : property.images;
      const imageSrc = images.length > 0
        ? images[0]
        : "/assets/imagens/default.jpg";

      // Utiliza utilitário centralizado
      const colors = window.utils.getPropertyColors(property);
      let tipoLabel = colors.badgeText;
      let btnDetalhesColor = colors.button;
      let iconColor = colors.icon;
      let btnStyle = '';

      // Garantir que features seja array
      let features = window.utils.processarFeatures(property.features);

      // Exibir quartos e banheiros diretamente dos campos
      let quartos = property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A';
      let banheiros = property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A';

      // Lógica do ribbon (faixa de status)
      const processedStatus = window.utils.processarStatus(property.status);
      let ribbon = '';
      let showBadge = true;
      
      // Verifica status para ribbon
      if (processedStatus === 'vendido' || processedStatus === 'alugado') {
        ribbon = `<div class="property-ribbon" style="${colors.badge}">${processedStatus === 'vendido' ? 'VENDIDO' : 'ALUGADO'}</div>`;
        showBadge = false;
      }

      // Adiciona '/mês' após o preço se for aluguel
      card.innerHTML = `
        <div class="relative">
          <img src="${imageSrc}" class="w-full h-56 object-cover rounded-t-xl" alt="Imagem do imóvel">
          ${ribbon ? ribbon : ''}
          ${showBadge ? `<span class="absolute top-3 left-3 px-3 py-1 rounded-full text-white text-xs font-bold shadow ${btnDetalhesColor}" style="z-index:2;">${tipoLabel}</span>` : ''}
          <button class="absolute top-3 right-3 bg-white rounded-full p-2 shadow hover:bg-gray-100 transition" style="z-index:2; border:none;">
            <i class="fa-regular fa-heart text-pink-500 text-lg"></i>
          </button>
        </div>
        <div class="p-6 flex-1 flex flex-col justify-between">
          <h4 class="text-lg font-bold text-gray-800 mb-2">${property.title}</h4>
          <p class="text-gray-500 text-sm mb-2 flex items-center"><i class="fas fa-map-marker-alt mr-2 ${iconColor}"></i> ${property.location}</p>
          <div class="flex w-100 text-sm text-gray-600 mb-4">
            <div class="flex-grow d-flex align-items-center gap-2 min-w-0">
              <i class="fas fa-bed ${iconColor}"></i>
              <span class="truncate">${property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A'} Quartos</span>
            </div>
            <div class="flex-grow d-flex align-items-center gap-2 min-w-0 justify-content-center">
              <i class="fas fa-bath ${iconColor}"></i>
              <span class="truncate">${property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A'} Banheiros</span>
            </div>
            <div class="flex-grow d-flex align-items-center gap-2 min-w-0 justify-content-end">
              <i class="fas fa-ruler-combined ${iconColor}"></i>
              <span class="truncate">${property.area}m²</span>
            </div>
          </div>
          <div class="mb-2">
            <span class="text-blue-600 font-bold text-lg block text-left">${window.utils.formatarPreco(property.price, tipoLabel === 'Aluguel')}</span>
          </div>
          <button class="${btnDetalhesColor} text-base py-2 px-4 rounded-lg transition ver-detalhes font-semibold w-full mt-2 text-white" style="${btnStyle}" data-id="${property.id}">
            Ver Detalhes
          </button>
        </div>
      `;

      // Adicionar evento para abrir o modal de detalhes
      card.querySelector('.ver-detalhes').addEventListener('click', function() {
        showPropertyDetails(property.id);
      });

      container.appendChild(card);
    });
    // Atualiza referência global após renderização
    window.dadosImoveis = dadosImoveis;
  } catch (err) {
    console.error("Erro ao carregar imóveis:", err);
  }
}

  // Mapeamento de ícones para características
window.featuresIcons = {
  'piscina': 'fa-water',
  'churrasqueira': 'fa-fire',
  'academia': 'fa-dumbbell',
  'playground': 'fa-child',
  'salão de festas': 'fa-glass-cheers',
  'segurança': 'fa-shield-alt',
  'elevador': 'fa-arrow-up',
  'portaria': 'fa-user-shield',
  'área de lazer': 'fa-umbrella-beach',
  'quadra': 'fa-basketball-ball',
  'varanda': 'fa-door-open',
  'mobiliado': 'fa-couch',
  'ar condicionado': 'fa-snowflake',
  'interfone': 'fa-phone',
  'jardim': 'fa-leaf',
  'área gourmet': 'fa-utensils',
  'aceita pet': 'fa-paw'
};

/**
 * Exibe os detalhes do imóvel selecionado em um modal.
 */
window.showPropertyDetails = function(id) {
  // Sempre buscar do window.dadosImoveis para funcionar em todos-imoveis.html
  const lista = window.dadosImoveis || (typeof dadosImoveis !== 'undefined' ? dadosImoveis : []);
  const property = lista.find(p => p.id == id);
  if (!property) return;

  // Define a propriedade atual globalmente
  window.currentProperty = property;

  // Definir cores conforme tipo de transação e tipo de imóvel
  const colors = window.utils.getPropertyColors(property);
  
  // Aplicar cores ao modal
  const modalEl = document.getElementById('propertyModal');
  const modalHeader = modalEl.querySelector('.modal-header');
  const modalFooter = modalEl.querySelector('.modal-footer');
  
  // Aplicar cores do cabeçalho e rodapé usando cssText para preservar múltiplos estilos
  if (modalHeader) modalHeader.style.cssText = colors.badge;
  if (modalFooter) modalFooter.style.cssText = colors.badge;

  // Aplicar cor aos botões do modal
  const modalButtons = modalEl.querySelectorAll('.btn-primary, .btn-detalhes');
  modalButtons.forEach(btn => btn.style.cssText = colors.button);

  // Garantir um único bloco de estilo para ícones do modal (evita duplicações)
  let styleEl = modalEl.querySelector('#propertyModalColors');
  if (!styleEl) {
    styleEl = document.createElement('style');
    styleEl.id = 'propertyModalColors';
    modalEl.appendChild(styleEl);
  }
  styleEl.textContent = `.modal-icon { ${colors.icon} transition: color 0.3s ease; }`;

  // Extrai o valor da cor de colors.icon (ex: "color: #10b981;") e aplica ao texto dos elementos do modal
  try {
    const m = /color\s*:\s*([^;]+);?/.exec(colors.icon || '');
    const colorValue = m && m[1] ? m[1].trim() : '';
    if (colorValue) {
      ['modal-bedrooms','modal-bathrooms','modal-garage','modal-condominium','modal-iptu','modal-suites','modal-area','modal-location'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.color = colorValue;
      });
    }
  } catch (e) {
    console.warn('Não foi possível aplicar cor de destaque ao texto do modal', e);
  }

  // Garantir que features seja array válido
  let features = window.utils.processarFeatures(property.features);
  // Filtrar valores vazios ou nulos
  features = features.filter(f => !!f && f.trim() !== '');
  
  let featuresHtml = '';
  // aplica os icones a caracteristica do imovel
  if (features.length > 0) {
    features.forEach(f => {
      const featureKey = f.toLowerCase().trim();
      const iconClass = window.featuresIcons[featureKey] || 'fa-check';
      featuresHtml += `
        <li class='d-flex align-items-center mb-1 col'>
          <i class='fas ${iconClass} modal-icon me-2'></i>${f}
        </li>`;
    });
  } else {
    featuresHtml = '<li class="text-muted">Sem características cadastradas</li>';
  }

  // Exibir quartos, banheiros e vagas no modal
  document.getElementById('modal-bedrooms').textContent = property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A';
  document.getElementById('modal-bathrooms').textContent = property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A';
  document.getElementById('modal-garage').textContent = property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A';
  // IMAGENS DO IMÓVEL NO CARROSSEL DO MODAL
  let images = typeof property.images === 'string' ? JSON.parse(property.images) : property.images;
  if (!Array.isArray(images)) images = [];
  let carouselId = `propertyCarousel_${property.id}`;
  let carouselHtml = '';
  if (images.length > 0) {
          carouselHtml = `
            <div id="${carouselId}" class="carousel slide" data-bs-ride="carousel">
              <div class="carousel-inner rounded shadow-sm" style="width:100%;height:400px;max-width:100vw;max-height:80vh;">
                ${images.map((img, idx) => `
                  <div class="carousel-item${idx === 0 ? ' active' : ''}">
                    <img src="${img}" class="d-block w-100 h-100 rounded shadow" alt="Imagem do imóvel" style="width:100%;height:400px;object-fit:cover;display:block;cursor:pointer;" onclick="window.showImageLightbox('${img}')">
                  </div>
                `).join('')}
              </div>
              ${images.length > 1 ? `
                <button class="carousel-control-prev" type="button" data-bs-target="#${carouselId}" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#${carouselId}" data-bs-slide="next">
                  <span class="carousel-control-next-icon"></span>
                </button>
              ` : ''}
            </div>
          `;
  } else {
    carouselHtml = `
      <div class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner rounded shadow-sm">
          <div class="carousel-item active">
            <img src="/assets/imagens/default.jpg" class="d-block w-100 h-100 rounded shadow" alt="Imagem do imóvel" style="width:100%;height:100%;object-fit:cover;display:block;">
          </div>
        </div>
      </div>
    `;
  }
// Lightbox para expandir imagem ao clicar
if (!window.showImageLightbox) {
  window.showImageLightbox = function(imgUrl) {
    let lightbox = document.createElement('div');
    lightbox.id = 'image-lightbox-modal';
    lightbox.style.position = 'fixed';
    lightbox.style.top = '0';
    lightbox.style.left = '0';
    lightbox.style.width = '100vw';
    lightbox.style.height = '100vh';
    lightbox.style.background = 'rgba(0,0,0,0.85)';
    lightbox.style.display = 'flex';
    lightbox.style.alignItems = 'center';
    lightbox.style.justifyContent = 'center';
    lightbox.style.zIndex = '9999';
    lightbox.innerHTML = `<img src="${imgUrl}" style="max-width:90vw;max-height:90vh;border-radius:8px;box-shadow:0 0 32px #000;">`;
    lightbox.onclick = function() {
      document.body.removeChild(lightbox);
    };
    document.body.appendChild(lightbox);
  }
}

  // Atualizar modal
  document.getElementById('modal-title').textContent = property.title;
  // Formata o preço no modal
  document.getElementById('modal-price').className = 'fs-5 fw-bold text-primary';
  const isRental = (property.transactionType || '').toLowerCase().includes('aluguel');
  document.getElementById('modal-price').textContent = window.utils.formatarPreco(property.price, isRental, property.type);
  document.getElementById('modal-location').innerHTML = `<i class="fas fa-map-marker-alt me-2 modal-icon"></i> ${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}`;
  document.getElementById('modal-description').textContent = property.description || "Sem descrição disponível";
  document.getElementById('modal-area').innerHTML = `<i class="fas fa-ruler-combined modal-icon me-2"></i> <span class="fw-semibold text-dark">${property.area}m²</span>`;
  const yearBuiltValue = property.yearBuilt && property.yearBuilt.toString().trim() !== '' ? property.yearBuilt : 'N/A';
  document.getElementById('modal-yearBuilt').innerHTML = `<span class="fw-semibold">${yearBuiltValue}</span>`;

  // Preencher Condomínio / IPTU / Suítes
  const condoEl = document.getElementById('modal-condominium');
  const iptuEl = document.getElementById('modal-iptu');
  const suitesEl = document.getElementById('modal-suites');
  if (condoEl) {
    const val = (property.condominium !== undefined && property.condominium !== null && property.condominium !== '') ? window.utils.formatarPreco(property.condominium, false) : 'N/A';
    condoEl.textContent = `${val} Condomínio`;
    if (condoEl.parentElement) condoEl.parentElement.classList.add('d-flex','align-items-center','gap-2','small');
  }
  if (iptuEl) {
    const val = (property.iptu !== undefined && property.iptu !== null && property.iptu !== '') ? window.utils.formatarPreco(property.iptu, false) : 'N/A';
    iptuEl.textContent = `${val} IPTU`;
    if (iptuEl.parentElement) iptuEl.parentElement.classList.add('d-flex','align-items-center','gap-2','small');
  }
  if (suitesEl) {
    const val = (property.suites !== undefined && property.suites !== null && property.suites !== '') ? property.suites : 'N/A';
    suitesEl.textContent = `${val} Suítes`;
    if (suitesEl.parentElement) suitesEl.parentElement.classList.add('d-flex','align-items-center','gap-2','small');
  }

  // Exibir quartos, banheiros e vagas com ícones coloridos
  const bedEl = document.getElementById('modal-bedrooms');
  if (bedEl) {
    bedEl.textContent = `${property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A'} Quartos`;
    if (bedEl.parentElement) bedEl.parentElement.classList.add('d-flex','align-items-center','gap-2','small');
  }
  const bathEl = document.getElementById('modal-bathrooms');
  if (bathEl) {
    bathEl.textContent = `${property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A'} Banheiros`;
    if (bathEl.parentElement) bathEl.parentElement.classList.add('d-flex','align-items-center','gap-2','small');
  }
  const garageEl = document.getElementById('modal-garage');
  if (garageEl) {
    garageEl.textContent = `${property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A'} Vagas`;
    if (garageEl.parentElement) garageEl.parentElement.classList.add('d-flex','align-items-center','gap-2','small');
  }

  // Estilizar área, ano, quartos e banheiros
  document.getElementById('modal-area').parentElement.parentElement.className = 'd-flex justify-content-between bg-blue-50 p-3 rounded mb-4';
  document.getElementById('modal-area').parentElement.className = 'd-flex flex-column';
  document.getElementById('modal-bedrooms').parentElement.className = 'd-flex align-items-center gap-2';
  document.getElementById('modal-bathrooms').parentElement.className = 'd-flex align-items-center gap-2';

  // Características
  const featuresList = document.getElementById('modal-features');
  // Mantém a classe para o grid de 2 colunas
  featuresList.className = 'row row-cols-2 g-2';
  featuresList.innerHTML = features.length > 0 
    ? features.map(feature => {
        const featureKey = feature.toLowerCase().trim();
        const iconClass = window.featuresIcons[featureKey] || 'fa-check';
        return `
          <li class="col">
            <div class="d-flex align-items-center">
              <i class="fas ${iconClass} modal-icon me-2"></i>
              <span>${feature}</span>
            </div>
          </li>`;
      }).join('')
    : '<li class="col-12 text-muted">Sem características cadastradas</li>';

  // Botão WhatsApp
const whatsappBtn = document.getElementById('modal-whatsapp');
const msg = `Olá! Gostaria de saber mais sobre o imóvel:
${property.title},
${property.location}${property.neighborhood ? ' - ' + property.neighborhood : ''},
${property.bedrooms} Quartos,
${property.bathrooms} Banheiros,
${property.garage} Vagas na garagem,
${property.area}m² de área,
Preço: ${window.utils.formatarPreco(property.price, false, property.type)}`;

// Busca o número do WhatsApp da empresa (company_whatsapp) do escopo global ou de onde estiver disponível
let companyWhatsapp = window.company_whatsapp || '5511999999999';
companyWhatsapp = companyWhatsapp.replace(/\D/g, '');
if (companyWhatsapp.length < 10) companyWhatsapp = '5511999999999';

whatsappBtn.href = `https://wa.me/${companyWhatsapp}?text=${encodeURIComponent(msg)}`;
whatsappBtn.target = '_blank';
whatsappBtn.rel = 'noopener noreferrer';
whatsappBtn.onclick = function(e) {
  // Garante que sempre abra em nova aba
  window.open(this.href, '_blank');
};

  // --- Links de compartilhamento social (usados no bloco de botões abaixo do preço) ---
  try {
    const shareUrl = (property.url && property.url.length > 0)
      ? property.url
      : `${window.location.origin}${window.location.pathname}?property=${property.id}`;
    const shareTitle = property.title || '';
    const firstImage = (images && images.length>0) ? images[0] : '';
    // Monta texto detalhado para compartilhar: título, endereço e características
    const addressParts = [];
    if (property.address) addressParts.push(property.address);
    if (property.location) addressParts.push(property.location);
    if (property.neighborhood) addressParts.push(property.neighborhood);
    if (property.city) addressParts.push(property.city);
    if (property.state) addressParts.push(property.state);
    const fullAddress = addressParts.filter(Boolean).join(' - ');
    const characteristics = [];
    characteristics.push((property.bedrooms !== undefined && property.bedrooms !== '') ? `${property.bedrooms} Quartos` : null);
    characteristics.push((property.bathrooms !== undefined && property.bathrooms !== '') ? `${property.bathrooms} Banheiros` : null);
    characteristics.push((property.garage !== undefined && property.garage !== '') ? `${property.garage} Vagas` : null);
    if (property.area) characteristics.push(`${property.area}m²`);
    const charText = characteristics.filter(Boolean).join(', ');
    const shareText = `${shareTitle}${fullAddress ? '\n' + fullAddress : ''}${charText ? '\n' + charText : ''}`;

    const elShare = id => document.getElementById(id);

    const whatsappShare = elShare('share-whatsapp');
    const fbShare = elShare('share-facebook');
    const linkedinShare = elShare('share-linkedin');
    const telegramShare = elShare('share-telegram');
    const xShare = elShare('share-x');
    const emailShare = elShare('share-email');
    const pinterestShare = elShare('share-pinterest');
    const copyBtn = elShare('share-copy');

    if (whatsappShare) whatsappShare.href = `https://api.whatsapp.com/send?text=${encodeURIComponent(shareText + '\n' + shareUrl)}`;
    if (fbShare) fbShare.href = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`;
    if (linkedinShare) linkedinShare.href = `https://www.linkedin.com/shareArticle?mini=true&url=${encodeURIComponent(shareUrl)}&title=${encodeURIComponent(shareTitle)}&summary=${encodeURIComponent(charText)}`;
    if (telegramShare) telegramShare.href = `https://t.me/share/url?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(shareText)}`;
    if (xShare) xShare.href = `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(shareUrl)}`;
    if (emailShare) emailShare.href = `mailto:?subject=${encodeURIComponent(shareTitle)}&body=${encodeURIComponent(shareText + '\n' + shareUrl)}`;
    if (pinterestShare) pinterestShare.href = `https://pinterest.com/pin/create/button/?url=${encodeURIComponent(shareUrl)}&media=${encodeURIComponent(firstImage)}&description=${encodeURIComponent(shareTitle)}`;

    // Definir target/rel e comportamento de abertura para todas as âncoras de compartilhamento (exceto mailto)
    [whatsappShare, fbShare, linkedinShare, telegramShare, xShare, pinterestShare].forEach(a => {
      if (!a) return;
      a.setAttribute('target', '_blank');
      a.setAttribute('rel', 'noopener noreferrer');
      a.onclick = function(e) {
        e.preventDefault();
        if (this.href && this.href !== '#') {
          window.open(this.href, '_blank');
        }
      };
    });
    // emailShare deve abrir mailto no mesmo contexto; se existir, garantir href
    if (emailShare && emailShare.href && emailShare.href.startsWith('mailto:')) {
      // emailShare já tem o href configurado, apenas garantir que seja válido
      emailShare.onclick = function(e) {
        // Permitir comportamento padrão do mailto
        return true;
      };
    }

    if (copyBtn) {
      copyBtn.onclick = function() {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(shareUrl).then(() => {
            if (window.utils && typeof window.utils.mostrarSucesso === 'function') {
              window.utils.mostrarSucesso('Link copiado para a área de transferência');
            } else {
              alert('Link copiado para a área de transferência');
            }
          }).catch(() => {
            window.utils && window.utils.mostrarErro ? window.utils.mostrarErro('Não foi possível copiar o link') : alert('Não foi possível copiar o link');
          });
        } else {
          // Fallback
          const tmp = document.createElement('input');
          document.body.appendChild(tmp);
          tmp.value = shareUrl;
          tmp.select();
          try { document.execCommand('copy');
            window.utils && window.utils.mostrarSucesso ? window.utils.mostrarSucesso('Link copiado para a área de transferência') : alert('Link copiado');
          } catch (e) {
            window.utils && window.utils.mostrarErro ? window.utils.mostrarErro('Não foi possível copiar o link') : alert('Não foi possível copiar o link');
          }
          document.body.removeChild(tmp);
        }
      };
    }
  } catch (e) {
    console.warn('Erro ao configurar botões de compartilhamento', e);
  }

  // Preencher imagens do imóvel no modal
  const modalImagesContainer = document.getElementById('modal-images');
  if (modalImagesContainer) {
    modalImagesContainer.innerHTML = carouselHtml;
  }

  // Abrir modal
  const modal = new bootstrap.Modal(document.getElementById('propertyModal'));
  modal.show();
}

// Mobile menu toggle

document.getElementById('mobile-menu-button').addEventListener('click', function() {
  const mobileMenu = document.getElementById('mobile-menu');
  mobileMenu.classList.toggle('hidden');
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function (e) {
    const targetId = this.getAttribute('href');
    if (targetId === '#' || !targetId.startsWith('#')) return;

    // Validar que o targetId é um seletor CSS válido
    try {
      const targetElement = document.querySelector(targetId);
      if (targetElement) {
        e.preventDefault();
        window.scrollTo({
          top: targetElement.offsetTop - 80,
          behavior: 'smooth'
        });

        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
          mobileMenu.classList.add('hidden');
        }
      }
    } catch (err) {
      // Se querySelector falhar (seletor inválido), permitir comportamento padrão
      console.warn('Seletor inválido:', targetId);
    }
  });
});

// Contact form submission

document.getElementById('contact-form').addEventListener('submit', async function(e) {
  e.preventDefault();

  const name = document.getElementById('name').value;
  const email = document.getElementById('email').value;
  const phone = document.getElementById('phone').value;
  const subject = document.getElementById('subject').value;
  const message = document.getElementById('message').value;
  const terms = document.getElementById('terms').checked;

  if (!name || !email || !phone || !message || !terms) {
    window.utils.mostrarErro('Por favor, preencha todos os campos obrigatórios e aceite os termos.');
    return;
  }

  // Validação de e-mail: tenta usar função compartilhada, senão usa regex local
  let emailValid = false;
  if (typeof validateEmail === 'function') {
    try { emailValid = validateEmail(email); } catch (e) { emailValid = false; }
  } else {
    const re = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
    emailValid = re.test(String(email || '').trim());
  }
  if (!emailValid) {
    window.utils.mostrarErro('E-mail inválido. Verifique o endereço e tente novamente.');
    return;
  }

  // Envia para a API de leads
  /**
   * Envia o formulário de contato para a API
   * 
   * @async
   * @param {string} name - Nome do contato
   * @param {string} email - Email do contato
   * @param {string} phone - Telefone do contato
   * @param {string} subject - Assunto da mensagem
   * @param {string} message - Mensagem completa
   */
  try {
    const res = await fetch('/api/addLead.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name,
        email,
        phone,
        message: `[${subject}] ${message}`,
        property_id: null
      })
    });
    const json = await res.json();
    if (json.success) {
      alert('Mensagem enviada com sucesso! Em breve entraremos em contato.');
      this.reset();
    } else {
      let msg = json.message || 'Erro ao enviar mensagem.';
      if (json.error) msg += '\n' + json.error;
      window.utils.mostrarErro(msg);
    }
  } catch (err) {
    window.utils.mostrarErro('Erro ao enviar mensagem.');
  }
});

// HERO CARROSSEL DO BACKEND (TESTE FINAL APLICADO)
