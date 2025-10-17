// Função para mostrar apenas os imóveis favoritos
function showFavorites() {
    const propertyList = document.getElementById('property-list');
    if (!propertyList) return;

    // Atualiza o título da página
    const pageTitle = document.querySelector('h1.display-4');
    if (pageTitle) {
        pageTitle.textContent = 'Imóveis Favoritos';
    }

    // Filtra os cards para mostrar apenas os favoritos
    const propertyCards = propertyList.children;
    let hasVisibleCards = false;

    Array.from(propertyCards).forEach(card => {
        const favoriteBtn = card.querySelector('.favorite-btn');
        const propertyId = favoriteBtn?.dataset.propertyId;
        
        if (propertyId && window.favoritesManager) {
            if (window.favoritesManager.isFavorite(propertyId)) {
                card.style.display = '';
                hasVisibleCards = true;
            } else {
                card.style.display = 'none';
            }
        }
    });

    // Remove mensagem existente se houver
    const existingMessage = propertyList.querySelector('.no-favorites-message');
    if (existingMessage) {
        existingMessage.remove();
    }

    // Mostra mensagem se não houver favoritos
    if (!hasVisibleCards) {
        const noFavoritesMessage = document.createElement('div');
        noFavoritesMessage.className = 'no-favorites-message col-12 text-center py-5';
        noFavoritesMessage.innerHTML = `
            <div class="text-muted">
                <i class="fas fa-heart-broken fa-3x mb-3"></i>
                <h3>Nenhum imóvel favorito</h3>
                <p>Você ainda não adicionou nenhum imóvel aos favoritos.</p>
            </div>
        `;
        propertyList.appendChild(noFavoritesMessage);
    }

    // Rola suavemente até a seção de propriedades
    document.getElementById('properties').scrollIntoView({ behavior: 'smooth' });
}

// Função para mostrar todos os imóveis
function showAllProperties() {
    const propertyList = document.getElementById('property-list');
    if (!propertyList) return;

    // Restaura o título original
    const pageTitle = document.querySelector('h1.display-4');
    if (pageTitle) {
        pageTitle.textContent = 'Todos os Imóveis';
    }

    // Remove mensagem de "nenhum favorito" se existir
    const noFavoritesMessage = propertyList.querySelector('.no-favorites-message');
    if (noFavoritesMessage) {
        noFavoritesMessage.remove();
    }

    // Mostra todos os cards
    const propertyCards = propertyList.children;
    Array.from(propertyCards).forEach(card => {
        if (!card.classList.contains('no-favorites-message')) {
            card.style.display = '';
        }
    });

    // Rola até a seção de propriedades
    document.getElementById('properties').scrollIntoView({ behavior: 'smooth' });
}