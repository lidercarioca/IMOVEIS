// Definindo o objeto utils globalmente
// Este objeto centraliza funções utilitárias para manipulação de imóveis, tipos, preços e status.
window.utils = {};

/**
 * Normaliza o tipo de imóvel para o padrão do sistema (ex: 'casa' -> 'house').
 * Aceita variações em português e inglês.
 */
window.utils.normalizarTipoImovel = function(tipo) {
  if (!tipo) return '';
  tipo = tipo.toLowerCase().trim();
  
  // Mapeamento de tipos do banco para português
  const mapeamentoTipos = {
    'house': 'Casa',
    'apartment': 'Apartamento',
    'commercial': 'Comercial',
    'land': 'Terreno'
  };

  return mapeamentoTipos[tipo] || tipo;
};

/**
 * Retorna as cores e textos de destaque (badge/ribbon) para o imóvel, de acordo com o tipo e transação.
 */
window.utils.getPropertyColors = function(property) {
  let type = window.utils.normalizarTipoImovel(property.type);
  let transaction = (property.transactionType || '').toLowerCase();
  
  // Comercial sempre usa cor destaque
  if (type === 'commercial' || transaction === 'commercial') {
    return {
      badge: `background-color: var(--cor-destaque);`,
      button: `background-color: var(--cor-destaque);`,
      icon: `color: var(--cor-destaque);`,
      ribbonColor: 'var(--cor-destaque)',
      badgeText: 'Comercial'
    };
  }
  
  // Aluguel sempre usa cor secundária
  if (transaction === 'aluguel' || transaction === 'alugar' || transaction === 'rent') {
    return {
      badge: `background-color: var(--cor-secundaria);`,
      button: `background-color: var(--cor-secundaria);`,
      icon: `color: var(--cor-secundaria);`,
      ribbonColor: 'var(--cor-secundaria)',
      badgeText: 'Para Alugar'
    };
  }
  
  // Venda padrão usa cor primária
  return {
    badge: `background-color: var(--cor-primaria);`,
    button: `background-color: var(--cor-primaria);`,
    icon: `color: var(--cor-primaria);`,
    ribbonColor: 'var(--cor-primaria)',
    badgeText: 'Venda'
  };
};

/**
 * Formata o preço do imóvel para o padrão brasileiro, adicionando '/mês' se for aluguel ou comercial.
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
window.utils.removeAccents = function(str) {
  return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
};

/**
 * Normaliza e traduz o status do imóvel para minúsculo, sem acento, para uso em filtros e exibição.
 */
window.utils.processarStatus = function(status) {
  status = (status || '').toString();
  return this.removeAccents(status).trim().toLowerCase();
};

/**
 * Exibe uma mensagem de erro padronizada para o usuário.
 * Pode ser customizada para usar modal, toast, etc.
 */
window.utils.mostrarErro = function(mensagem) {
  if (typeof Swal !== 'undefined') {
    Swal.fire('Erro!', mensagem, 'error');
  } else {
    alert(mensagem);
  }
};

/**
 * Exibe uma mensagem de sucesso padronizada para o usuário.
 */
window.utils.mostrarSucesso = function(mensagem) {
  if (typeof Swal !== 'undefined') {
    Swal.fire('Sucesso!', mensagem, 'success');
  } else {
    alert(mensagem);
  }
