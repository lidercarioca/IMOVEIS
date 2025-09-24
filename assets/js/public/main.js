document.addEventListener("DOMContentLoaded", async () => {
  await carregarImoveis();
  carregarHeroBanners();
// Carrega banners do backend e exibe na section hero
async function carregarHeroBanners() {
  const res = await fetch('api/getBanners.php');
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
banners.length > 1 ? `
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
    const processedStatus = window.utils.processarStatus(property.status);
    let ribbon = '';
    let showBadge = true;
    
    // Verifica status para ribbon
    if (processedStatus === 'vendido' || processedStatus === 'alugado') {
      ribbon = `<div class="property-ribbon" style="${colors.badge}">${processedStatus === 'vendido' ? 'VENDIDO' : 'ALUGADO'}</div>`;
      showBadge = false;
    }
    card.innerHTML = `
      <div class="position-relative">
        <img src="${imageSrc}" class="card-img-top object-fit-cover" style="height: 224px;" alt="Imagem do imóvel">
        ${ribbon ? ribbon : ''}
        ${showBadge ? `<span class="position-absolute top-0 start-0 m-3 badge rounded-pill text-white" style="${colors.badge}">${tipoLabel}</span>` : ''}
        <button type="button" class="btn btn-light position-absolute top-0 end-0 m-3 rounded-circle p-2" aria-label="Favoritar">
          <i class="fa-regular fa-heart text-danger"></i>
        </button>
      </div>
      <div class="card-body d-flex flex-column h-100">
        <h4 class="card-title h5 fw-bold mb-2">${property.title}</h4>
        <p class="card-text text-secondary small mb-3">
          <i class="fas fa-map-marker-alt me-2" style="${colors.icon}"></i>
          ${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}
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
            <i class="fas fa-car me-2" style="${colors.icon}"></i>
            <span>${vagas} Vagas</span>
          </div>
          <div class="col d-flex align-items-center">
            <i class="fas fa-ruler-combined me-2" style="${colors.icon}"></i>
            <span>${property.area}m²</span>
          </div>
        </div>
        <div class="mt-auto">
          <p class="h5 fw-bold mb-3 text-primary" style="color: var(--bs-primary) !important;">
            ${window.utils.formatarPreco(property.price, tipoLabel === 'Para Alugar', property.type)}
          </p>
          <button class="btn w-100 ver-detalhes fw-semibold py-3 rounded-3" style="${colors.button}" data-id="${property.id}">
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
async function carregarImoveis() {
  try {
    const res = await fetch("api/getProperties.php");
    const properties = await res.json();
  dadosImoveis = properties;
  window.dadosImoveis = dadosImoveis;
  window.dadosImoveisOriginal = [...properties];

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
            <span class="text-blue-600 font-bold text-lg block text-left">${window.utils.formatarPreco(property.price, tipoLabel === 'Para Alugar')}</span>
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
  
  // Aplicar cores do cabeçalho e rodapé
  modalHeader.style = colors.badge;
  modalFooter.style = colors.badge;
  
  // Aplicar cor aos botões
  const modalButtons = modalEl.querySelectorAll('.btn-primary');
  modalButtons.forEach(btn => btn.style = colors.button);
  
  // Cor para ícones
  let iconColor = 'modal-icon';
  
  // Aplicar estilo para os ícones do modal
  const style = document.createElement('style');
  style.textContent = `
    .modal-icon {
      ${colors.icon}
      transition: color 0.3s ease;
    }
  `;
  modalEl.appendChild(style);

  // Garantir que features seja array válido
  let features = window.utils.processarFeatures(property.features);
  // Filtrar valores vazios ou nulos
  features = features.filter(f => !!f && f.trim() !== '');

  // Montar lista de características em <li> para o <ul id="modal-features">
  let featuresHtml = '';
  if (features.length > 0) {
    features.forEach(f => {
      featuresHtml += `<li class='d-flex align-items-center mb-1 col'><i class='fas fa-check modal-icon me-2'></i>${f}</li>`;
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
  document.getElementById('modal-price').textContent = window.utils.formatarPreco(property.price, false, property.type);
  document.getElementById('modal-location').innerHTML = `<i class="fas fa-map-marker-alt me-2 modal-icon"></i> ${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}`;
  document.getElementById('modal-description').textContent = property.description || "Sem descrição disponível";
  document.getElementById('modal-area').innerHTML = `<i class="fas fa-ruler-combined modal-icon me-2"></i> <span class="fw-semibold text-dark">${property.area}m²</span>`;
  const yearBuiltValue = property.yearBuilt && property.yearBuilt.toString().trim() !== '' ? property.yearBuilt : 'N/A';
  document.getElementById('modal-yearBuilt').innerHTML = `<i class="fas fa-calendar-alt modal-icon me-2"></i> <span class="fw-semibold text-dark">${yearBuiltValue}</span>`;

  // Exibir quartos, banheiros e vagas com ícones coloridos
  document.getElementById('modal-bedrooms').parentElement.innerHTML = `<i class="fas fa-bed modal-icon"></i> <span id="modal-bedrooms" class="fw-semibold text-dark">${property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A'} Quartos</span>`;
  document.getElementById('modal-bathrooms').parentElement.innerHTML = `<i class="fas fa-bath modal-icon"></i> <span id="modal-bathrooms" class="fw-semibold text-dark">${property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A'} Banheiros</span>`;
  document.getElementById('modal-garage').parentElement.innerHTML = `<i class="fas fa-car modal-icon"></i> <span id="modal-garage" class="fw-semibold text-dark">${property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A'} Vagas</span>`;

  // Estilizar área, ano, quartos e banheiros
  document.getElementById('modal-area').parentElement.parentElement.className = 'd-flex justify-content-between bg-blue-50 p-3 rounded mb-4';
  document.getElementById('modal-area').parentElement.className = 'd-flex flex-column';
  document.getElementById('modal-yearBuilt').parentElement.className = 'd-flex flex-column text-end';
  document.getElementById('modal-bedrooms').parentElement.className = 'd-flex align-items-center gap-2';
  document.getElementById('modal-bathrooms').parentElement.className = 'd-flex align-items-center gap-2';

  // Características
  const featuresList = document.getElementById('modal-features');
  featuresList.className = '';
  featuresList.innerHTML = featuresHtml;

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
    e.preventDefault();

    const targetId = this.getAttribute('href');
    if (targetId === '#') return;

    const targetElement = document.querySelector(targetId);
    if (targetElement) {
      window.scrollTo({
        top: targetElement.offsetTop - 80,
        behavior: 'smooth'
      });

      const mobileMenu = document.getElementById('mobile-menu');
      if (!mobileMenu.classList.contains('hidden')) {
        mobileMenu.classList.add('hidden');
      }
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

  // Envia para a API de leads
  try {
    const res = await fetch('api/addLead.php', {
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
