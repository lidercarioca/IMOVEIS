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
            const gridIcon = gridViewBtn.querySelector('i');
            const listIcon = listViewBtn.querySelector('i');

            // Limpa classes de estado em ambos
            gridViewBtn.classList.remove('active');
            listViewBtn.classList.remove('active');
            if (gridIcon) {
                gridIcon.classList.remove('text-white');
                gridIcon.classList.add('text-gray-600');
            }
            if (listIcon) {
                listIcon.classList.remove('text-white');
                listIcon.classList.add('text-gray-600');
            }

            if (mode === 'grid') {
                gridViewBtn.classList.add('active');
                if (gridIcon) {
                    gridIcon.classList.remove('text-gray-600');
                    gridIcon.classList.add('text-white');
                }
            } else {
                listViewBtn.classList.add('active');
                if (listIcon) {
                    listIcon.classList.remove('text-gray-600');
                    listIcon.classList.add('text-white');
                }
            }
        }

        // Salva preferência
        localStorage.setItem('propertyView', mode);
    }
});