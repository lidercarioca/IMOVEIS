// Define o objeto utils globalmente
// Este objeto centraliza funções utilitárias para manipulação de imóveis, tipos, preços e status.
window.utils = {};

// Observa mudanças nas variáveis CSS
if (window.MutationObserver) {
  const observer = new MutationObserver(function() {
    // Limpa o cache quando houver mudanças no CSS
    if (window.utils.colorCache) {
      window.utils.colorCache = null;
    }
  });

  // Observa mudanças nos atributos style do :root
  observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['style']
  });
}

/**finindo o objeto utils globalmente
// Este objeto centraliza funções utilitárias para manipulação de imóveis, tipos, preços e status.
window.utils = {};

/**
 * Normaliza o tipo de imóvel para o padrão do sistema (ex: 'casa' -> 'house').
 * Aceita variações em português e inglês.
 */
/**
 * Normaliza o tipo de imóvel para um formato padrão
 * @param {string} tipo - Tipo do imóvel a ser normalizado
 * @returns {string} Tipo do imóvel normalizado
 */
window.utils.normalizarTipoImovel = function(tipo) {
  if (!tipo) return '';
  tipo = tipo.toLowerCase().trim();
  
  // Mapeamento de tipos em português para o padrão do banco
  const mapeamentoTipos = {
    'casa': 'Casa',
    'house': 'Casa',
    'apartamento': 'Apartamento',
    'apto': 'Apartamento',
    'apartment': 'Apartamento',
    'apt': 'Apartamento',
    'comercial': 'Comercial',
    'commercial': 'Comercial',
    'loja': 'Comercial',
    'sala': 'Comercial',
    'terreno': 'Terreno',
    'land': 'Terreno',
    'lote': 'Terreno'
  };

  return mapeamentoTipos[tipo] || tipo;
};

/**
 * Retorna as cores e textos de destaque (badge/ribbon) para o imóvel, de acordo com o tipo e transação.
 */
/**
 * Verifica se um imóvel é do tipo comercial
 */
/**
 * Verifica se um imóvel é do tipo comercial
 * @param {Object} property - Objeto com os dados do imóvel
 * @returns {boolean} true se for comercial, false caso contrário
 */
window.utils.isComercialProperty = function(property) {
  const type = window.utils.normalizarTipoImovel(property.type || '');
  const transaction = (property.transactionType || '').toLowerCase();
  const comercialKeywords = ['comercial', 'commercial', 'loja', 'sala', 'office', 'escritório'];
  
  // Verifica no tipo normalizado
  if (type.toLowerCase() === 'comercial') return true;
  
  // Verifica keywords no tipo e no tipo de transação
  const hasComercialKeyword = comercialKeywords.some(keyword => {
    const keywordLower = keyword.toLowerCase();
    return (property.type || '').toLowerCase().includes(keywordLower) || 
           transaction.includes(keywordLower);
  });
  
  return hasComercialKeyword;
};

// Cache para cores computadas
window.utils.colorCache = null;

// Função para obter cores computadas do CSS
/**
 * Obtém as cores computadas do tema atual do sistema
 * @returns {Object} Objeto com as cores primária e secundária
 */
window.utils.getComputedColors = function() {
  if (window.utils.colorCache) return window.utils.colorCache;
  
  const style = getComputedStyle(document.documentElement);
  const cores = {
    destaque: style.getPropertyValue('--cor-destaque').trim(),
    secundaria: style.getPropertyValue('--cor-secundaria').trim(),
    primaria: style.getPropertyValue('--cor-primaria').trim()
  };
  
  window.utils.colorCache = cores;
  return cores;
};

/**
 * Obtém as cores associadas ao tipo de imóvel
 * @param {Object} property - Objeto com os dados do imóvel
 * @returns {Object} Objeto com as cores para o imóvel
 */
window.utils.getPropertyColors = function(property) {
  // Normalização de dados de entrada
  const type = window.utils.normalizarTipoImovel(property.type || '');
  const transaction = (property.transactionType || '').toLowerCase();
  const status = window.utils.processarStatus(property.status || '');

  // Obtém as cores computadas do CSS
  const cores = window.utils.getComputedColors();
  
  // Função auxiliar para criar o objeto de retorno
  const criarRetorno = (cor, texto) => ({
    badge: `background-color: ${cor};`,
    button: `background-color: ${cor};`,
    icon: `color: ${cor};`,
    badgeText: texto
  });

  // 1. COMERCIAL - sempre usa cor destaque (laranja)
  if (window.utils.isComercialProperty(property)) {
    const texto = status === 'alugado' ? 'Alugado' :
                 status === 'vendido' ? 'Vendido' : 'Comercial';
    return criarRetorno(cores.destaque, texto);
  }

  // 2. ALUGUEL - sempre usa cor secundária (verde)
  const isAluguel = ['aluguel', 'alugar', 'rent', 'locacao', 'locação'].some(keyword => 
    transaction.toLowerCase().includes(keyword.toLowerCase())
  );

  if (isAluguel) {
    const texto = status === 'alugado' ? 'Alugado' : 'Aluguel';
    return criarRetorno(cores.secundaria, texto);
  }
  
  // 3. VENDA - sempre usa cor primária (azul)
  const texto = status === 'vendido' ? 'Vendido' : 'Venda';
  return criarRetorno(cores.primaria, texto);
};

/**
 * Formata o preço do imóvel para o padrão brasileiro, adicionando '/mês' se for aluguel ou comercial.
 */
/**
 * Formata o preço do imóvel para exibição
 * @param {number} price - Preço a ser formatado
 * @param {boolean} isAluguel - Indica se é um valor de aluguel
 * @param {string} type - Tipo do imóvel
 * @returns {string} Preço formatado
 */
window.utils.formatarPreco = function(price, isAluguel = false, type = '') {
  let priceNumber = Number(price);
  if (isNaN(priceNumber)) {
    priceNumber = parseFloat((price || '').replace(/\./g, '').replace(/,/g, '.'));
  }
  const isComercial = type.toLowerCase() === 'commercial';
  return `R$ ${priceNumber.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}${isAluguel || isComercial ? ' /mês' : ''}`;
};

/**
 * Garante que o campo features seja sempre um array, convertendo de string JSON se necessário.
 */
window.utils.processarFeatures = function(features) {
  if (typeof features === 'string') {
    try {
      features = features.trim() === '' ? [] : JSON.parse(features);
    } catch {
      features = [];
    }
  }
  return Array.isArray(features) ? features : [];
};

/**
 * Remove acentos de uma string (útil para comparações e buscas).
 */
/**
 * Remove acentos de uma string para facilitar comparações e buscas
 * @param {string} str - String com acentos a ser processada
 * @returns {string} String sem acentos
 */
window.utils.removeAccents = function(str) {
  return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
};

/**
 * Normaliza e traduz o status do imóvel para minúsculo, sem acento, para uso em filtros e exibição.
 */
/**
 * Normaliza e padroniza o status do imóvel
 * @param {string} status - Status original do imóvel
 * @returns {string} Status processado: minúsculo, sem acentos e sem espaços extras
 */
window.utils.processarStatus = function(status) {
  status = (status || '').toString();
  const statusProcessado = this.removeAccents(status).trim().toLowerCase();
  return statusProcessado; // Retorna sempre em minúsculo para facilitar comparações
};

/**
 * Exibe uma mensagem de erro padronizada para o usuário.
 * Pode ser customizada para usar modal, toast, etc.
 */
/**
 * Exibe uma mensagem de erro padronizada para o usuário
 * @param {string} mensagem - Mensagem de erro a ser exibida
 */
window.utils.mostrarErro = function(mensagem) {
  alert(mensagem); // Troque por modal/toast se desejar
};


// Adicione esta função ao objeto utils
window.utils.processarStatus = function(status) {
  // Mapeamento de status em inglês para português
  const statusMap = {
    'pending': 'Pendente',
    'active': 'Ativo',
    'sold': 'Vendido',
    'rented': 'Alugado',
    'inactive': 'Inativo'
  };

  // Retorna o status em português ou o original se não encontrar
  return statusMap[status] || status;
};
/**
 * Retorna a classe CSS apropriada para o badge de status do imóvel.
 */

window.utils.getStatusClass = function(status) {
  if (!status) return 'badge-status status-inactive';
  
  const map = {
    'ativo': 'ativo',
    'active': 'ativo',
    'pendente': 'pendente',
    'pending': 'pendente',
    'vendido': 'vendido',
    'sold': 'vendido',
    'alugado': 'alugado',
    'rented': 'alugado',
    'inativo': 'inativo',
    'inactive': 'inativo'
  };
  
  const key = map[String(status).toLowerCase()] || 'inativo';
  
  const classes = {
    ativo: 'badge-status status-ativo',
    pendente: 'badge-status status-pendente',
    vendido: 'badge-status status-vendido',
    alugado: 'badge-status status-alugado',
    inativo: 'badge-status status-inativo'
  };
  
  return classes[key] || 'badge-status status-inactive';
};
