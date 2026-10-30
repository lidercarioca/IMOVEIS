/**
 * painel-configs.js
 * Gerencia o carregamento dinâmico do formulário de configurações no painel
 * Versão simplificada: aproveita o initializeSettings() que já existe
 */

/**
 * Carrega o formulário de configurações dinamicamente
 * Simplesmente inicializa o settings que já está no painel
 */
async function carregarConfigsDinamicamente() {
    // Aguarda um pouco para garantir que o DOM está pronto
    setTimeout(function () {
        // Se a função initializeSettings existe, chama agora
        if (typeof initializeSettings === 'function') {
            // Inicializando settings
            initializeSettings();
        } else {
            console.warn('[carregarConfigsDinamicamente] initializeSettings não definida ainda');
        }
    }, 100);
}

/**
 * Carrega o formulário de adicionar imóvel dinamicamente
 */
async function carregarFormularioImovel() {
    const container = document.getElementById('add-property');

    if (!container) {
        console.warn('Container #add-property não encontrado');
        return Promise.resolve(null);
    }

    if (window.__propertyFormLoadPromise) {
        return window.__propertyFormLoadPromise;
    }

    const activeForm = document.getElementById('property-form');
    const activeEditId = activeForm
        ? (activeForm.querySelector('[name="edit_id"]')?.value ?? '').trim()
        : '';
    const activeEditingPropertyId = activeForm
        ? (activeForm.dataset.editingPropertyId || '').trim()
        : '';

    if (
        activeForm &&
        activeForm.isConnected &&
        (activeEditId !== '' || activeEditingPropertyId !== '')
    ) {
        return Promise.resolve(activeForm);
    }

    // Se já foi carregado, não carrega novamente
    if (container.innerHTML && container.innerHTML.trim().length > 200) {
        // Formulário de imóvel já carregado
        return Promise.resolve(container);
    }

    window.__propertyFormLoadPromise = (async () => {
        // Mostra loading
        container.innerHTML =
            '<div class="flex flex-col items-center justify-center min-h-[200px] text-gray-500"><i class="fas fa-cog text-4xl mb-4 animate-spin"></i><p>Carregando formulário...</p></div>';

        try {
            // Tenta carregar a view existing property form
            const response = await fetch('adicionar-imovel.php?ajax=1', {
                method: 'GET',
            });

            if (!response.ok) {
                console.warn('Formulário não disponível via AJAX, usando página dedicada');
                container.innerHTML = `
            <div class="alert alert-info">
              <i class="fas fa-info-circle mr-2"></i>
              Use a página <a href="adicionar-imovel.php" class="alert-link">Adicionar Imóvel</a> para criar novos imóveis.
            </div>
          `;
                return container;
            }

            // Insere o HTML carregado
            container.innerHTML = await response.text();
            return container;
        } catch (error) {
            console.error('Erro ao carregar formulário de imóvel:', error);
            container.innerHTML = `
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            Formulário temporariamente indisponível. Use a página <a href="adicionar-imovel.php" class="alert-link">Adicionar Imóvel</a>.
          </div>
        `;
            return container;
        } finally {
            window.__propertyFormLoadPromise = null;
        }
    })();

    return window.__propertyFormLoadPromise;
}
