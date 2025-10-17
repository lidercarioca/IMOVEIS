// Script dedicado para todos-imoveis.html
// Carrega e renderiza todos os imóveis, e ativa o modal de detalhes

// Inicializa o script quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', async function() {
  // Aguarda DOM e estilos estarem prontos
  if (document.readyState === 'loading') {
    await new Promise(resolve => document.addEventListener('DOMContentLoaded', resolve));
  }

  // Função para verificar se as variáveis CSS estão carregadas
  function checkCSSVariables() {
    const style = getComputedStyle(document.documentElement);
    const primary = style.getPropertyValue('--cor-primaria').trim();
    const secondary = style.getPropertyValue('--cor-secundaria').trim();
    const highlight = style.getPropertyValue('--cor-destaque').trim();
    return primary && secondary && highlight;
  }

  // Aguarda até que as variáveis CSS estejam disponíveis
  while (!checkCSSVariables()) {
    await new Promise(resolve => setTimeout(resolve, 50));
  }

  // Função para buscar imóveis
  async function buscarImoveis() {
    const res = await fetch('/api/getProperties.php');
    let properties = await res.json();
    // Filtra imóveis inativos ou pendentes
    properties = properties.filter(property => {
      if (!property.status) return true;
      const st = property.status.toLowerCase();
      return !['inativo','inactive','pendente','pending'].includes(st);
    });
    return properties;
  }

  // Função para determinar a classe do botão baseado no tipo e status do imóvel
  function getButtonClass(property) {
    if (!property) return 'btn-dynamic-primary';
    
    // Usa o utilitário centralizado para determinar as cores
    const colors = window.utils.getPropertyColors(property);
    const badgeText = colors.badgeText.toLowerCase();
    
    console.log('Propriedade:', {
      tipo: property.type,
      badgeText: badgeText,
      colors: colors
    });
    
    // Se for comercial (usa cor destaque)
    if (colors.badgeText === 'Comercial') {
      console.log('Imóvel comercial - usando cor destaque');
      return 'btn-dynamic-destaque';
    }
    
    // Se for aluguel (usa cor secundária)
    const transaction = (property.transactionType || '').toLowerCase();
    const isAluguel = ['aluguel', 'alugar', 'rent', 'locacao', 'locação'].some(keyword => 
      transaction.includes(keyword.toLowerCase())
    );
    
    if (isAluguel) {
      console.log('Imóvel para aluguel - usando cor secundária');
      return 'btn-dynamic-secondary';
    }
    
    // Se não for comercial nem aluguel, é venda (usa cor primária)
    console.log('Imóvel para venda - usando cor primária');
    return 'btn-dynamic-primary';
  }

  // Função para renderizar cards
  // Carrega os imóveis inicialmente
  try {
    const imoveis = await buscarImoveis();
    renderizarImoveis(imoveis);
  } catch (error) {
    console.error('Erro ao carregar imóveis:', error);
  }

  // Função para renderizar os imóveis
  function renderizarImoveis(imoveis) {
    const container = document.getElementById('property-list');
    if (!container) return;
    container.innerHTML = '';
    imoveis.forEach(property => {

      // Cria a coluna
      const colDiv = document.createElement("div");
      colDiv.className = "col";

      // Cria o card
      const card = document.createElement('div');
      card.className = 'card property h-100 shadow-sm';

      // Processa as imagens
      const images = typeof property.images === 'string' ? JSON.parse(property.images) : property.images;
      let imageSrc = images && images.length > 0 ? images[0] : '/assets/imagens/default.jpg';
      if (imageSrc && !imageSrc.startsWith('/')) imageSrc = '/' + imageSrc.replace(/^\/+/, '');
      
      // Usa a função getPropertyColors para definir as cores e textos
      const colors = window.utils.getPropertyColors(property);
      const status = window.utils.processarStatus(property.status);
      let ribbonHTML = '';
      
      // Segurança: garante que property.type e colors.badgeText existam
      const typeClass = property.type ? property.type : 'padrao';
      const badgeText = colors.badgeText ? colors.badgeText : 'Imóvel';

      if (status === 'vendido' || status === 'alugado') {
        if (property.type === 'commercial') {
          ribbonHTML = `<div class="property-ribbon ${typeClass}">${status === 'vendido' ? 'VENDIDO' : 'ALUGADO'}</div>`;
        } else {
          const statusClass = status === 'vendido' ? 'vendido' : 'aluguel';
          ribbonHTML = `<div class="property-ribbon ${statusClass}">${status === 'vendido' ? 'Vendido' : 'ALUGADO'}</div>`;
        }

      } else {
        ribbonHTML = '';
      }

      // Badge só aparece se não tiver ribbon de status especial
      let showBadge = status !== 'vendido' && status !== 'alugado';

      
      let tipoLabel = colors.badgeText;
      let btnDetalhesColor = colors.button;
      let iconColor = colors.icon;
      let quartos = property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A';
      let banheiros = property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A';
      let vagas = property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A';
      
      //let showBadge = !ribbonHTML; // Se tiver ribbon, não mostra o badge
      
      card.innerHTML = `
        <div class="position-relative">
          <img src="${imageSrc}" class="card-img-top" style="height: 224px; object-fit: cover;" alt="Imagem do imóvel">
          ${ribbonHTML}
          ${showBadge ? `<span class="badge-grande" style="${colors.badge}">${tipoLabel}</span>` : ''}
          <button type="button" class="btn btn-light position-absolute top-0 end-0 m-3 rounded-circle p-2 favorite-btn" data-property-id="${property.id}" aria-label="Favoritar">
            <i class="fa-${window.favoritesManager && window.favoritesManager.isFavorite(property.id) ? 'solid' : 'regular'} fa-heart text-danger"></i>
          </button>
        </div>
        <div class="card-body d-flex flex-column justify-content-between">
          <h4 class="card-title fs-5 fw-bold text-dark mb-2">${property.title}</h4>
          <p class="card-text text-secondary small mb-2 d-flex align-items-center">
            <i class="fas fa-map-marker-alt me-2" style="${iconColor}"></i>
            ${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}
          </p>
          <div class="d-flex justify-content-between text-secondary small mb-4">
            <div class="d-flex align-items-center me-2">
              <i class="fas fa-bed me-1" style="${iconColor}"></i>
              <span>${quartos} Quartos</span>
            </div>
            <div class="d-flex align-items-center me-2">
              <i class="fas fa-bath me-1" style="${iconColor}"></i>
              <span>${banheiros} Banheiros</span>
            </div>
            <div class="d-flex align-items-center me-2">
              <i class="fas fa-car me-1" style="${iconColor}"></i>
              <span>${vagas} Vagas</span>
            </div>
            <div class="d-flex align-items-center">
              <i class="fas fa-ruler-combined me-1" style="${iconColor}"></i>
              <span>${property.area}m²</span>
            </div>
          </div>
          <div class="mb-3">
            <p class="text-primary fs-4 fw-bold d-block">
            ${window.utils.formatarPreco(property.price, tipoLabel === 'Aluguel', property.type)}
          </p>
          </div>
          <button class="btn btn-detalhes py-2 px-4 rounded-lg w-100 mt-2 ver-detalhes text-white" style="${colors.button}" data-id="${property.id}">
          <i class="fas fa-search me-2"></i>Ver Detalhes
          </button>
        </div>
      `;
      // Adiciona listener para o botão de detalhes
      card.querySelector('.ver-detalhes').addEventListener('click', function() {
        abrirModalDetalhes(property);
      });
      
      // Adiciona o card ao container
      container.appendChild(card);
    });

    // Atualiza o estado dos favoritos após renderizar todos os cards
    if (window.favoritesManager) {
      window.favoritesManager.updateAllFavoriteButtons();
    }
  }
  // Adicione icones as características do ímovel
const featuresIcons = {
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


  // Função para abrir o modal de detalhes
  function abrirModalDetalhes(property) {
    // (Reutiliza a lógica do showPropertyDetails, mas sem depender de variáveis globais)
    const colors = window.utils.getPropertyColors(property);
    let iconColor = colors.icon;
    let features = window.utils.processarFeatures(property.features);
    features = features.filter(f => !!f && f.trim() !== '');
    let featuresHtml = '';
if (features.length > 0) {
  features.forEach(f => {
    const featureKey = f.toLowerCase().trim();
    const iconClass = featuresIcons[featureKey] || 'fa-check';
    featuresHtml += `<li class='d-flex align-items-center mb-1 col'>
      <i class='fas ${iconClass} me-2' style='${iconColor}'></i>${f}
    </li>`;
  });
} else {
  featuresHtml = '<li class="text-muted">Sem características cadastradas</li>';
}
    const el = id => document.getElementById(id);
    if (el('modal-title')) el('modal-title').textContent = property.title;
    if (el('modal-price')) {
      const isRental = (property.transactionType || '').toLowerCase().includes('aluguel');
      el('modal-price').textContent = window.utils.formatarPreco(property.price, isRental, property.type);
    }
    if (el('modal-location')) el('modal-location').innerHTML = `<i class="fas fa-map-marker-alt me-2 ${iconColor}"></i> ${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}`;
  if (el('modal-location')) el('modal-location').innerHTML = `<i class="fas fa-map-marker-alt me-2" style="${iconColor}"></i> ${property.location}${property.neighborhood ? ` - ${property.neighborhood}` : ''}`;
    if (el('modal-description')) el('modal-description').textContent = property.description || "Sem descrição disponível";
    if (el('modal-area')) el('modal-area').innerHTML = `<i class="fas fa-ruler-combined ${iconColor} me-1"></i> ${property.area}m²`;
  if (el('modal-area')) el('modal-area').innerHTML = `<i class="fas fa-ruler-combined me-1" style="${iconColor}"></i> ${property.area}m²`;
    const yearBuiltValue = property.yearBuilt && property.yearBuilt.toString().trim() !== '' ? property.yearBuilt : 'N/A';
    if (el('modal-yearBuilt')) el('modal-yearBuilt').innerHTML = `<i class="fas fa-calendar-alt ${iconColor} me-1"></i> ${yearBuiltValue}`;
  if (el('modal-yearBuilt')) el('modal-yearBuilt').innerHTML = `<i class="fas fa-calendar-alt me-1" style="${iconColor}"></i> ${yearBuiltValue}`;
    if (el('modal-bedrooms'))
  el('modal-bedrooms').innerHTML = `<i class="fas fa-bed" style="${iconColor}"></i> ${property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A'} Quartos`;
    if (el('modal-bathrooms'))
  el('modal-bathrooms').innerHTML = `<i class="fas fa-bath" style="${iconColor}"></i> ${property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A'} Banheiros`;
    if (el('modal-garage'))
  el('modal-garage').innerHTML = `<i class="fas fa-car" style="${iconColor}"></i> ${property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A'} Vagas`;
    if (el('modal-area') && el('modal-area').parentElement && el('modal-area').parentElement.parentElement)
      el('modal-area').parentElement.parentElement.className = 'd-flex justify-content-between bg-blue-50 p-3 rounded mb-4';
    if (el('modal-area') && el('modal-area').parentElement)
      el('modal-area').parentElement.className = 'd-flex flex-column';
    if (el('modal-yearBuilt') && el('modal-yearBuilt').parentElement)
      el('modal-yearBuilt').parentElement.className = 'd-flex flex-column text-end';
    if (el('modal-bedrooms') && el('modal-bedrooms').parentElement)
      el('modal-bedrooms').parentElement.className = 'd-flex align-items-center gap-2';
    if (el('modal-bathrooms') && el('modal-bathrooms').parentElement)
      el('modal-bathrooms').parentElement.className = 'd-flex align-items-center gap-2';
    const featuresList = el('modal-features');
    if (featuresList) {
      // Mantém a classe para o grid de 2 colunas
      featuresList.className = 'row row-cols-2 g-2';
      featuresList.innerHTML = features.length > 0 
        ? features.map(feature => {
            const featureKey = feature.toLowerCase().trim();
            const iconClass = featuresIcons[featureKey] || 'fa-check';
            return `
              <li class="col">
                <div class="d-flex align-items-center">
                  <i class="fas ${iconClass} me-2" style="${iconColor}"></i>
                  <span>${feature}</span>
                </div>
              </li>`;
          }).join('')
        : '<li class="col-12 text-muted">Sem características cadastradas</li>';
    }
    // Imagens do imóvel no carrossel
    let images = typeof property.images === 'string' ? JSON.parse(property.images) : property.images;
    if (!Array.isArray(images)) images = [];
    // Corrige todos os caminhos de imagem para serem absolutos
    images = images.map(img => {
      if (!img) return '/assets/imagens/default.jpg';
      if (img.startsWith('/')) return img;
      return '/' + img.replace(/^\/+/,'');
    });
    let carouselId = `propertyCarousel_${property.id}`;
    let modalImagesContainer = document.getElementById('modal-images');
    if (modalImagesContainer) {
      if (images.length > 1) {
        // Só carrossel, imagens maiores
        modalImagesContainer.innerHTML = `
          <div id="${carouselId}" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner rounded shadow-sm">
              ${images.map((img, idx) => `
                <div class="carousel-item${idx === 0 ? ' active' : ''}">
                  <img src="${img}" class="d-block w-100 rounded shadow modal-img-clickable" alt="Imagem do imóvel" style="max-height:650px;object-fit:cover;" data-img="${img}">
                </div>
              `).join('')}
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#${carouselId}" data-bs-slide="prev" title="Imagem anterior" aria-label="Anterior">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#${carouselId}" data-bs-slide="next" title="Próxima imagem" aria-label="Próximo">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Próximo</span>
            </button>
          </div>
        `;
      } else {
        // Só imagem única, maior
        let mainImage = images.length > 0 ? images[0] : '/assets/imagens/default.jpg';
        modalImagesContainer.innerHTML = `<img src="${mainImage}" class="d-block w-100 rounded shadow mb-3 modal-img-clickable" alt="Imagem principal do imóvel" style="max-height:650px;object-fit:cover;" data-img="${mainImage}">`;
      }
      // Lightbox simples para imagem grande
      if (!document.getElementById('modal-img-lightbox')) {
        const lightbox = document.createElement('div');
        lightbox.id = 'modal-img-lightbox';
        lightbox.style = 'display:none;position:fixed;z-index:2000;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.85);align-items:center;justify-content:center;';
        lightbox.innerHTML = '<img id="modal-img-lightbox-img" style="max-width:90vw;max-height:90vh;border-radius:12px;box-shadow:0 0 32px #000;">';
        lightbox.onclick = () => { lightbox.style.display = 'none'; };
        document.body.appendChild(lightbox);
      }
      // Adiciona evento para todas imagens clicáveis
      setTimeout(() => {
        document.querySelectorAll('.modal-img-clickable').forEach(imgEl => {
          imgEl.style.cursor = 'zoom-in';
          imgEl.onclick = e => {
            e.stopPropagation();
            const src = imgEl.getAttribute('data-img');
            const lightbox = document.getElementById('modal-img-lightbox');
            const lightboxImg = document.getElementById('modal-img-lightbox-img');
            if (lightbox && lightboxImg) {
              lightboxImg.src = src;
              lightbox.style.display = 'flex';
            }
          };
        });
      }, 100);
    }
    
    // Botão WhatsApp
    const whatsappBtn = document.getElementById('modal-whatsapp');
    const msg = `Olá! Gostaria de saber mais sobre o imóvel:\n${property.title},\n${property.location}${property.neighborhood ? ' - ' + property.neighborhood : ''},\n${property.bedrooms} Quartos,\n${property.bathrooms} Banheiros,\n${property.garage} Vagas na garagem,\n${property.area}m² de área,\nPreço: ${window.utils.formatarPreco(property.price, false, property.type)}`;
    
    // Busca o WhatsApp das configurações da empresa
    let companyWhatsapp = '5511999999999'; // Valor padrão
    fetch('/api/getCompanySettings.php')
      .then(res => res.json())
      .then(json => {
        if (json.success && json.data && json.data.company_whatsapp) {
          companyWhatsapp = json.data.company_whatsapp.replace(/\D/g, '');
          // Adiciona código do país se necessário
          if (!companyWhatsapp.startsWith('55')) {
            companyWhatsapp = '55' + companyWhatsapp;
          }
          // Fallback para número padrão se o número for inválido
          if (companyWhatsapp.length < 12) companyWhatsapp = '5511999999999';
          setupWhatsAppButton();
        }
      })
      .catch(e => {
        console.error('Erro ao buscar WhatsApp da empresa:', e);
        setupWhatsAppButton();
      });

    function setupWhatsAppButton() {
      whatsappBtn.href = `https://wa.me/${companyWhatsapp}?text=${encodeURIComponent(msg)}`;
      whatsappBtn.target = '_blank';
      whatsappBtn.rel = 'noopener noreferrer';
      whatsappBtn.onclick = function(e) {
        e.preventDefault(); // Previne a abertura duplicada
        window.open(this.href, '_blank');
      };
    }
    
    // Configura o botão inicialmente com o número padrão
    setupWhatsAppButton();
    // Armazena a propriedade atual globalmente para o formulário de contato
    window.currentProperty = property;
    
    // Configura o botão de contato
    const btnContact = document.getElementById('btnOpenContact');
    if (btnContact) {
      btnContact.onclick = () => {
        const propertyModal = bootstrap.Modal.getInstance(document.getElementById('propertyModal'));
        if (propertyModal) {
          propertyModal.hide();
        }
        setTimeout(() => {
          const contactModal = new bootstrap.Modal(document.getElementById('contactFormModal'));
          contactModal.show();
          // Preenche a mensagem com os dados do imóvel
          const messageField = document.getElementById('contact-message');
          if (messageField && window.currentProperty) {
            messageField.value = window.generatePropertyMessage(window.currentProperty);
          }
        }, 500);
      };
    }
    
    // Abrir modal
    const modal = new bootstrap.Modal(document.getElementById('propertyModal'));
    modal.show();
  }

  // Execução principal
  try {
    const imoveis = await buscarImoveis();
    renderizarImoveis(imoveis);
  } catch (error) {
    console.error('Erro ao carregar imóveis:', error);
  }
});
