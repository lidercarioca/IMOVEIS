document.addEventListener('DOMContentLoaded', function() {
    // Botões de visualização
    const gridViewBtn = document.getElementById('grid-view-btn');
    const listViewBtn = document.getElementById('list-view-btn');
    const container = document.getElementById('properties-container');

    // Carrega a preferência salva
    const savedView = localStorage.getItem('propertyView') || 'grid';
    setViewMode(savedView);

    // Event listeners para os botões
    if (gridViewBtn) {
        gridViewBtn.addEventListener('click', () => setViewMode('grid'));
    }
    if (listViewBtn) {
        listViewBtn.addEventListener('click', () => setViewMode('list'));
    }

    function setViewMode(mode) {
        if (!container) return;

        // Remove classes existentes
        container.classList.remove('grid-view', 'list-view');
        
        // Adiciona nova classe
        container.classList.add(`${mode}-view`);
        
        // Atualiza visual dos botões
        if (gridViewBtn && listViewBtn) {
            if (mode === 'grid') {
                gridViewBtn.classList.add('bg-blue-600', 'text-white');
                listViewBtn.classList.remove('bg-blue-600', 'text-white');
            } else {
                listViewBtn.classList.add('bg-blue-600', 'text-white');
                gridViewBtn.classList.remove('bg-blue-600', 'text-white');
            }
        }

        // Salva preferência
        localStorage.setItem('propertyView', mode);
    }
});