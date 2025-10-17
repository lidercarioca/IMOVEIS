// Funções para filtrar e mostrar imóveis
function showFavorites() {
    const propertyList = document.getElementById('property-list');
    if (!propertyList) return;

    // Atualiza o título da página
    const pageTitle = document.querySelector('h1.display-4');
    if (pageTitle) {
        pageTitle.textContent = 'Imóveis Favoritos';
    }

    // Filtra os cards para mostrar apenas os favoritos
    const allCards = propertyList.querySelectorAll('.col');
    allCards.forEach(card => {
        const propertyId = card.querySelector('.favorite-btn')?.dataset.propertyId;
        if (propertyId && window.favoritesManager) {
            if (window.favoritesManager.isFavorite(propertyId)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        }
    });

    // Verifica se há favoritos
    const visibleCards = Array.from(allCards).filter(card => card.style.display !== 'none');
    if (visibleCards.length === 0) {
        const noFavoritesMessage = document.createElement('div');
        noFavoritesMessage.className = 'col-12 text-center py-5';
        noFavoritesMessage.innerHTML = `
            <div class="text-muted">
                <i class="fas fa-heart-broken fa-3x mb-3"></i>
                <h3>Nenhum imóvel favorito</h3>
                <p>Você ainda não adicionou nenhum imóvel aos favoritos.</p>
            </div>
        `;
        propertyList.appendChild(noFavoritesMessage);
    }

    // Rola até a seção de propriedades
    document.getElementById('properties').scrollIntoView({ behavior: 'smooth' });
}

function showAllProperties() {
    const propertyList = document.getElementById('property-list');
    if (!propertyList) return;

    // Restaura o título original
    const sectionTitle = document.querySelector('#properties h2');
    if (sectionTitle) {
        sectionTitle.textContent = 'Imóveis em Destaque';
    }

    // Restaura a descrição original
    const sectionDescription = document.querySelector('#properties p');
    if (sectionDescription) {
        sectionDescription.textContent = 'Confira nossa seleção de propriedades exclusivas com as melhores condições do mercado.';
    }

    // Remove mensagem de "nenhum favorito" se existir
    const noFavoritesMessage = propertyList.querySelector('.text-muted')?.closest('.col-12');
    if (noFavoritesMessage) {
        noFavoritesMessage.remove();
    }

    // Mostra todos os cards
    const allCards = propertyList.querySelectorAll('.col');
    allCards.forEach(card => {
        card.style.display = '';
    });

    // Rola até a seção de propriedades
    document.getElementById('properties').scrollIntoView({ behavior: 'smooth' });
}