// Variável global para armazenar a propriedade atual
window.currentProperty = null;

// Modifica a função showPropertyDetails para armazenar a propriedade atual
const originalShowPropertyDetails = window.showPropertyDetails;
window.showPropertyDetails = function(id) {
    const lista = window.dadosImoveis || (typeof dadosImoveis !== 'undefined' ? dadosImoveis : []);
    const property = lista.find(p => p.id == id);
    if (!property) return;
    
    // Armazena a propriedade atual globalmente
    window.currentProperty = property;
    
    // Chama a função original
    if (typeof originalShowPropertyDetails === 'function') {
        originalShowPropertyDetails(id);
    }
};
