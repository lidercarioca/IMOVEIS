/**
 * Converte uma cor HEX para seus valores RGB
 * @param {string} hex - Cor em formato hexadecimal (ex: #FF0000)
 * @returns {string} Valores RGB separados por vírgula (ex: "255, 0, 0")
 */
function hexToRGB(hex) {
    // Valida se hex não é nulo ou vazio
    if (!hex || typeof hex !== 'string' || hex.trim() === '') {
        // Silenciosamente retorna padrão durante digitação
        return '0, 0, 0';
    }
    
    // Remove o # se existir
    hex = hex.replace('#', '').trim();
    
    // Valida formato hexadecimal (6 caracteres)
    if (!/^[0-9A-Fa-f]{6}$/.test(hex)) {
        // Silenciosamente retorna padrão durante digitação
        return '0, 0, 0';
    }
    
    // Converte para RGB
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);
    
    return `${r}, ${g}, ${b}`;
}

/**
 * Atualiza as variáveis CSS de cor com suporte a RGB
 * @param {Object} data - Objeto com as cores da empresa
 */
function atualizarCoresComRGB(data) {
    if (data.company_color1) {
        document.documentElement.style.setProperty('--cor-primaria', data.company_color1);
        document.documentElement.style.setProperty('--cor-primaria-rgb', hexToRGB(data.company_color1));
    }
    
    if (data.company_color2) {
        document.documentElement.style.setProperty('--cor-secundaria', data.company_color2);
        document.documentElement.style.setProperty('--cor-secundaria-rgb', hexToRGB(data.company_color2));
    }
    
    if (data.company_color3) {
        document.documentElement.style.setProperty('--cor-destaque', data.company_color3);
        document.documentElement.style.setProperty('--cor-destaque-rgb', hexToRGB(data.company_color3));
    }
}
