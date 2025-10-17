// Gerenciador de Favoritos
class FavoritesManager {
    constructor() {
        this.storageKey = 'propertyFavorites';
        this.favorites = this.loadFavorites();
        this.initializeFavorites();
    }

    // Carrega favoritos do localStorage
    loadFavorites() {
        const stored = localStorage.getItem(this.storageKey);
        return stored ? JSON.parse(stored) : [];
    }

    // Salva favoritos no localStorage
    saveFavorites() {
        localStorage.setItem(this.storageKey, JSON.stringify(this.favorites));
    }

    // Adiciona ou remove um imóvel dos favoritos
    toggleFavorite(propertyId) {
        const index = this.favorites.indexOf(propertyId);
        if (index === -1) {
            this.favorites.push(propertyId);
        } else {
            this.favorites.splice(index, 1);
        }
        this.saveFavorites();
        this.updateFavoriteButton(propertyId);
    }

    // Verifica se um imóvel é favorito
    isFavorite(propertyId) {
        return this.favorites.includes(propertyId);
    }

    // Atualiza o ícone do botão de favorito
    updateFavoriteButton(propertyId) {
        const buttons = document.querySelectorAll(`button[data-property-id="${propertyId}"]`);
        const isFavorite = this.isFavorite(propertyId);
        
        buttons.forEach(button => {
            const icon = button.querySelector('i');
            if (icon) {
                if (isFavorite) {
                    icon.classList.remove('fa-regular');
                    icon.classList.add('fa-solid');
                } else {
                    icon.classList.remove('fa-solid');
                    icon.classList.add('fa-regular');
                }
            }
        });
    }

    // Inicializa os botões de favorito
    initializeFavorites() {
        // Adiciona listener para clicks nos botões de favorito
        document.addEventListener('click', (e) => {
            const favoriteBtn = e.target.closest('.favorite-btn');
            if (favoriteBtn) {
                e.preventDefault();
                const propertyId = favoriteBtn.dataset.propertyId;
                if (propertyId) {
                    this.toggleFavorite(propertyId);
                }
            }
        });

        // Observer para monitorar mudanças na lista de imóveis
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.addedNodes.length) {
                    this.updateAllFavoriteButtons();
                }
            });
        });

        // Observa mudanças na lista de imóveis
        const propertyList = document.getElementById('property-list');
        if (propertyList) {
            observer.observe(propertyList, { childList: true, subtree: true });
        }

        // Atualiza o estado inicial dos botões
        this.updateAllFavoriteButtons();
    }

    // Atualiza todos os botões de favorito na página
    updateAllFavoriteButtons() {
        const buttons = document.querySelectorAll('.favorite-btn');
        buttons.forEach(button => {
            const propertyId = button.dataset.propertyId;
            if (propertyId) {
                const icon = button.querySelector('i');
                if (icon) {
                    if (this.isFavorite(propertyId)) {
                        icon.classList.remove('fa-regular');
                        icon.classList.add('fa-solid');
                    } else {
                        icon.classList.remove('fa-solid');
                        icon.classList.add('fa-regular');
                    }
                }
            }
        });
    }
}

// Inicializa o gerenciador de favoritos quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', () => {
    window.favoritesManager = new FavoritesManager();
});