// Script para filtro dinâmico de imóveis no painel administrativo
document.addEventListener('DOMContentLoaded', function () {
  // Elementos do DOM
  const tipoSelect = document.getElementById('property-type-filter');
  const statusSelect = document.getElementById('property-status-filter');
  const buscaInput = document.getElementById('property-search');
  const container = document.getElementById('properties-container');
  const counter = document.getElementById('properties-count');

  // Mapeamento de tipos de imóveis
  const tiposMap = {
    'apartment': 'Apartamento',
    'house': 'Casa',
    'commercial': 'Comercial',
    'land': 'Terreno'
  };

  // Função para sanitizar strings
  function sanitizarString(str) {
    if (!str) return '';
    return String(str).trim().toLowerCase();
  }

  // Event listeners para filtros
  if (buscaInput) buscaInput.addEventListener('input', aplicarFiltros);
  if (tipoSelect) tipoSelect.addEventListener('change', aplicarFiltros);
  if (statusSelect) statusSelect.addEventListener('change', aplicarFiltros);

  // Carrega os imóveis inicialmente
  async function carregarImoveis() {
    try {
      const res = await fetch('/api/getProperties.php');
      const imoveis = await res.json();

      renderizarImoveisPainel(imoveis);
    } catch (error) {
      console.error('Erro ao carregar imóveis:', error);
      container.innerHTML = '<p class="text-red-500">Erro ao carregar imóveis.</p>';
    }
  }

  // Função para aplicar os filtros
  function aplicarFiltros() {
    console.log('Iniciando filtro...');
    // Sanitiza os valores dos filtros
    const busca = sanitizarString(buscaInput?.value);
    const tipo = sanitizarString(tipoSelect?.value);
    const status = sanitizarString(statusSelect?.value);
    
    console.log('Valores dos filtros:', { busca, tipo, status });

    // Pega todos os cards de imóveis
    const cards = container.getElementsByClassName('property-card');
    let visibleCount = 0;

    Array.from(cards).forEach(card => {
      // Obtém e sanitiza os valores do card
      const title = sanitizarString(card.querySelector('h4')?.textContent);
      const location = sanitizarString(card.querySelector('.text-gray-500')?.textContent);
      const tipoImovel = sanitizarString(card.getAttribute('data-type')); // Valor real do tipo (em inglês)
      
      // Encontra o elemento de status correto (excluindo os botões)
      const statusElement = Array.from(card.querySelectorAll('[class*="bg-"]'))
        .find(el => !el.classList.contains('bg-yellow-400') && 
                   !el.classList.contains('bg-red-500'));
      
      let statusImovel = sanitizarString(statusElement?.textContent);

      // Normalização do status
      if (statusImovel.includes('inactive') || statusImovel.includes('inativo')) statusImovel = 'inativo';
      if (statusImovel.includes('pending') || statusImovel.includes('pendente')) statusImovel = 'pendente';
      if (statusImovel.includes('active') || statusImovel.includes('ativo')) statusImovel = 'ativo';
      if (statusImovel.includes('sold') || statusImovel.includes('vendido')) statusImovel = 'vendido';
      if (statusImovel.includes('rented') || statusImovel.includes('alugado')) statusImovel = 'alugado';

      console.log('Valores do Filtro:', {
        buscaDigitada: busca,
        tipoSelecionado: tipo,
        statusSelecionado: status,
        tipoDoCard: tipoImovel,
        statusDoCard: statusImovel
      });
      
      // Função auxiliar para normalizar o tipo
      function normalizarTipo(tipo) {
        const tipos = {
          'apartamento': 'apartment',
          'casa': 'house',
          'comercial': 'commercial',
          'terreno': 'land'
        };
        return tipos[tipo.toLowerCase()] || tipo.toLowerCase();
      }

      // Verifica se o card atende aos critérios de filtro
      const matchBusca = !busca || 
        title.toLowerCase().includes(busca) || 
        location.toLowerCase().includes(busca);
      
      // Normaliza o tipo selecionado e o tipo do card para comparação
      const tipoNormalizado = normalizarTipo(tipo);
      const tipoCardNormalizado = tipoImovel.toLowerCase();
      const matchTipo = !tipo || tipoNormalizado === tipoCardNormalizado;
      
      const matchStatus = !status || statusImovel === status;
      
      console.log('Comparação de tipos:', {
        tipoSelecionado: tipo,
        tipoNormalizado: tipoNormalizado,
        tipoCard: tipoImovel,
        tipoCardNormalizado: tipoCardNormalizado,
        match: matchTipo
      });

      console.log('Resultados do Match:', {
        matchBusca,
        matchTipo,
        matchStatus
      });

      // Mostra ou esconde o card baseado nos filtros
      const shouldShow = matchBusca && matchTipo && matchStatus;
      console.log('Card deve ser mostrado?', shouldShow);
      
      if (shouldShow) {
        card.style.display = '';
        visibleCount++;
      } else {
        card.style.display = 'none';
      }
    });

    // Atualiza o contador e mensagem de nenhum imóvel
    if (counter) {
      counter.textContent = `Mostrando ${visibleCount} ${visibleCount === 1 ? 'imóvel' : 'imóveis'}`;
    }
    
    // Mostra mensagem quando não há resultados
    const noResultsMessage = container.querySelector('.no-results-message');
    if (visibleCount === 0) {
      if (!noResultsMessage) {
        const message = document.createElement('p');
        message.className = 'text-gray-500 no-results-message';
        message.textContent = 'Nenhum imóvel encontrado.';
        container.appendChild(message);
      }
    } else if (noResultsMessage) {
      noResultsMessage.remove();
    }
  }

  function renderizarImoveisPainel(imoveis) {
    container.innerHTML = '';
    if (!imoveis.length) {
      container.innerHTML = '<p class="text-gray-500">Nenhum imóvel encontrado.</p>';
      if (counter) counter.textContent = 'Mostrando 0 imóveis';
      return;
    }
    imoveis.forEach(property => {
      let images = property.images;
      if (typeof images === 'string') {
        try { images = JSON.parse(images); } catch { images = []; }
      }
      let imageHtml = '';
      if (Array.isArray(images) && images.length > 0 && images[0]) {
        let imgSrc = images[0];
        if (!/^assets\/imagens\//.test(imgSrc) && !/^https?:\/.*/.test(imgSrc) && !imgSrc.startsWith('/')) {
          imgSrc = `assets/imagens/${property.id}/${imgSrc}`;
        }
        imageHtml = `<img src='${imgSrc}' alt='${property.title}' class='w-full h-48 object-cover rounded-lg mb-2' onerror="this.src='/assets/imagens/default.jpg';" />`;
      } else {
        imageHtml = `<img src='/assets/imagens/default.jpg' alt='Sem imagem' class='w-full h-48 object-cover rounded-lg mb-2' />`;
      }
      let priceNumber = Number(property.price);
      if (isNaN(priceNumber)) {
        priceNumber = parseFloat((property.price || '').replace(/\./g, '').replace(/,/g, '.'));
      }
      const priceFormatted = priceNumber.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
      // Funções utilitárias do painel (devem estar globais)
      const statusClass = window.getStatusClass ? window.getStatusClass(property.status) : '';
      const statusText = window.getStatusText ? window.getStatusText(property.status) : property.status;
      const typeText = window.getPropertyTypeText ? window.getPropertyTypeText(property.type) : property.type;
      // Card padrão do painel
      const card = document.createElement('div');
      card.className = 'bg-white rounded-lg shadow-md p-4 property-card mb-6';
      card.setAttribute('data-type', property.type || '');
      card.innerHTML = `
        <div class="relative mb-0" style="min-height: 192px;">
          ${imageHtml}
          <span class="absolute top-3 left-3 bg-blue-700 text-white text-xs font-semibold px-4 py-1 rounded-full shadow" style="z-index:2;">${typeText}</span>
          <div class="absolute top-3 right-3 flex gap-2 z-10">
            <button title="Editar" class="btn-editar bg-yellow-400 hover:bg-yellow-500 text-white rounded-full p-2 transition" data-id="${property.id}"><i class="fas fa-pen"></i></button>
            <button title="Excluir" class="btn-excluir bg-red-500 hover:bg-red-600 text-white rounded-full p-2 transition" data-id="${property.id}"><i class="fas fa-trash"></i></button>
          </div>
        </div>
        <div class="p-0 pt-4 pb-2 px-6">
          <div class="flex justify-between items-center mb-2">
            <h4 class="text-lg font-semibold text-gray-800 m-0">${property.title}</h4>
            <span class="text-xl font-bold text-blue-700">R$ ${priceFormatted}</span>
          </div>
          <p class="text-gray-500 text-sm mb-2 flex items-center">
            <i class="fas fa-map-marker-alt mr-2 text-blue-700"></i>
            ${property.neighborhood || ''}${property.neighborhood && property.city ? ' - ' : ''}${property.city || ''}
          </p>
          <div class="flex items-center gap-2 mb-2">
            <span class="${statusClass}">${statusText}</span>
          </div>
          <div class="flex w-100 text-sm text-gray-600 mb-4 gap-6 justify-between">
            <div class="flex items-center gap-1 min-w-0">
              <i class="fas fa-bed text-blue-700"></i>
              <span>${property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A'} Quartos</span>
            </div>
            <div class="flex items-center gap-1 min-w-0">
              <i class="fas fa-bath text-blue-700"></i>
              <span>${property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A'} Banheiros</span>
            </div>
            <div class="flex items-center gap-1 min-w-0">
              <i class="fas fa-car text-blue-700"></i>
              <span>${property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A'} Vagas</span>
            </div>
            <div class="flex items-center gap-1 min-w-0">
              <i class="fas fa-ruler-combined text-blue-700"></i>
              <span>${property.area ? property.area : 'N/A'}m²</span>
            </div>
          </div>
        </div>
        <div class="px-6 pb-4">
          <button class="btn-ver-detalhes w-full mt-1 bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2 rounded-lg transition text-base"><i class="fas fa-eye mr-2"></i>Ver Detalhes</button>
        </div>
      `;
      card.querySelector('.btn-ver-detalhes').addEventListener('click', function() {
        abrirModalDetalhesPainel(property);
      });
      card.querySelector('.btn-excluir').addEventListener('click', function() {
        if (window.deleteProperty) {
          window.deleteProperty(property.id);
        } else {
          alert('Função de exclusão não encontrada.');
        }
      });
      card.querySelector('.btn-editar').addEventListener('click', function() {
        if (window.editProperty) {
          window.editProperty(property.id);
        } else {
          alert('Função de edição não encontrada.');
        }
      });
      container.appendChild(card);
  // Função para abrir e preencher o modal de detalhes do imóvel no painel
  function abrirModalDetalhesPainel(property) {
    // Preenche campos do modal
    const el = id => document.getElementById(id);
    if (el('modal-title')) el('modal-title').textContent = property.title;
    if (el('modal-price')) el('modal-price').textContent = Number(property.price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    if (el('modal-location')) el('modal-location').innerHTML = `<i class="fas fa-map-marker-alt me-2 text-primary"></i> ${property.location || ''}${property.neighborhood ? ' - ' + property.neighborhood : ''}`;
    if (el('modal-description')) el('modal-description').textContent = property.description || 'Sem descrição disponível';
    if (el('modal-area')) el('modal-area').textContent = property.area ? property.area + 'm²' : 'N/A';
    if (el('modal-yearBuilt')) el('modal-yearBuilt').textContent = property.yearBuilt || 'N/A';
    if (el('modal-bedrooms')) el('modal-bedrooms').innerHTML = `<i class="fas fa-bed text-primary"></i> ${property.bedrooms !== undefined && property.bedrooms !== '' ? property.bedrooms : 'N/A'} Quartos`;
    if (el('modal-bathrooms')) el('modal-bathrooms').innerHTML = `<i class="fas fa-bath text-primary"></i> ${property.bathrooms !== undefined && property.bathrooms !== '' ? property.bathrooms : 'N/A'} Banheiros`;
    if (el('modal-garage')) el('modal-garage').innerHTML = `<i class="fas fa-car text-primary"></i> ${property.garage !== undefined && property.garage !== '' ? property.garage : 'N/A'} Vagas`;
    // Características
    let features = property.features;
    if (typeof features === 'string') {
      try { features = JSON.parse(features); } catch { features = []; }
    }
    if (!Array.isArray(features)) features = [];
    let featuresHtml = '';
    if (features.length > 0) {
      features.forEach(f => {
        if (f && f.trim() !== '') featuresHtml += `<li class='d-flex align-items-center mb-1 col'><i class='fas fa-check text-success me-2'></i>${f}</li>`;
      });
    } else {
      featuresHtml = '<li class="text-muted">Sem características cadastradas</li>';
    }
    if (el('modal-features')) {
      el('modal-features').innerHTML = featuresHtml;
    }
    // Imagens no carrossel
    let images = property.images;
    if (typeof images === 'string') {
      try { images = JSON.parse(images); } catch { images = []; }
    }
    if (!Array.isArray(images)) images = [];
    const carouselInner = document.getElementById('modal-carousel-images');
    if (carouselInner) {
      let html = '';
      if (images.length > 0) {
        images.forEach((img, idx) => {
          let imgSrc = img;
          if (!/^assets\/imagens\//.test(imgSrc) && !/^https?:\/.*/.test(imgSrc) && !imgSrc.startsWith('/')) {
            imgSrc = `assets/imagens/${property.id}/${imgSrc}`;
          }
          html += `<div class="carousel-item${idx === 0 ? ' active' : ''}"><img src="${imgSrc}" class="d-block w-100 rounded shadow modal-img-clickable" alt="Imagem do imóvel" style="max-height:650px;object-fit:cover;" data-img="${imgSrc}"></div>`;
        });
      } else {
        html = `<div class="carousel-item active"><img src="/assets/imagens/default.jpg" class="d-block w-100 rounded shadow modal-img-clickable" alt="Imagem do imóvel" style="max-height:650px;object-fit:cover;" data-img="/assets/imagens/default.jpg"></div>`;
      }
      carouselInner.innerHTML = html;
    }
    // Abre o modal (Bootstrap 5)
    const modal = new bootstrap.Modal(document.getElementById('propertyModal'));
    modal.show();
  }
    });
    if (counter) counter.textContent = `Mostrando ${imoveis.length} imóveis`;
  }

  // Inicia o carregamento dos imóveis
  carregarImoveis();
});
