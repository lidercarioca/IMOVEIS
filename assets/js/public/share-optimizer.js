/**
 * ShareOptimizer.js
 * 
 * Otimiza compartilhamento em redes sociais com:
 * 1. Meta tags Open Graph dinâmicas
 * 2. Truncamento de texto para Twitter (280 caracteres)
 * 3. Melhor formatação para cada plataforma
 */

window.ShareOptimizer = {
  /**
   * Atualiza meta tags Open Graph dinamicamente
   */
  updateOpenGraphTags(propertyData) {
    const {
      title = 'Imóvel - DJ Imóveis',
      description = 'Confira este imóvel',
      image = '/assets/imagens/og-image.jpg',
      url = window.location.href,
      price = '',
      address = ''
    } = propertyData;

    // Cria descrição enriquecida com preço e endereço
    const enrichedDescription = [description, price, address].filter(Boolean).join(' • ');

    const ogTags = {
      'og:title': title,
      'og:description': enrichedDescription.substring(0, 160), // Max 160 chars para OG
      'og:image': image,
      'og:url': url,
      'og:type': 'product',
      'twitter:card': 'summary_large_image',
      'twitter:title': title,
      'twitter:description': enrichedDescription.substring(0, 160),
      'twitter:image': image
    };

    // Atualiza ou cria meta tags
    Object.entries(ogTags).forEach(([property, content]) => {
      let meta = document.querySelector(`meta[property="${property}"], meta[name="${property}"]`);
      if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('property' in ogTags ? 'property' : 'name', property);
        document.head.appendChild(meta);
      }
      meta.content = content;
    });
  },

  /**
   * Trunca texto para Twitter (280 caracteres incluindo URL)
   * Deixa espaço para a URL (aprox. 23-30 caracteres)
   */
  truncateForTwitter(text, maxLength = 280) {
    const urlLength = 30; // Espaço reservado para URL encurtada
    const availableLength = maxLength - urlLength - 1; // -1 para espaço

    if (text.length <= availableLength) return text;

    // Remove quebras de linha, condensa espaços
    const cleaned = text
      .replace(/\n+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();

    // Trunca e adiciona reticências
    return cleaned.substring(0, availableLength - 1) + '…';
  },

  /**
   * Trunca texto para outras redes (mais permissivas)
   */
  truncateForOtherPlatforms(text, maxLength = 1000) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength - 1) + '…';
  },

  /**
   * Formata texto específico para cada plataforma
   */
  formatForPlatform(platform, shareText, url) {
    const urls = {
      whatsapp: `https://api.whatsapp.com/send?text=${encodeURIComponent(shareText + '\n' + url)}`,
      facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
      linkedin: `https://www.linkedin.com/shareArticle?mini=true&url=${encodeURIComponent(url)}&title=${encodeURIComponent(shareText.split('\n')[0])}&summary=${encodeURIComponent(shareText)}`,
      twitter: `https://twitter.com/intent/tweet?text=${encodeURIComponent(this.truncateForTwitter(shareText))}&url=${encodeURIComponent(url)}`,
      telegram: `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(shareText)}`,
      pinterest: `https://pinterest.com/pin/create/button/?url=${encodeURIComponent(url)}&description=${encodeURIComponent(shareText.split('\n')[0])}`,
      email: `mailto:?subject=${encodeURIComponent(shareText.split('\n')[0])}&body=${encodeURIComponent(shareText + '\n\nAcesse: ' + url)}`
    };

    return urls[platform] || '#';
  }
};
