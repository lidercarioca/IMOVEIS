/**
 * Integração do cache-invalidator com o site público
 * Re-carrega dados quando há mudanças no painel
 */

document.addEventListener('DOMContentLoaded', function () {
    // Aguarda o carregamento do CacheInvalidator
    if (typeof CacheInvalidator === 'undefined') {
        return;
    }

    /**
     * Callback para recarregar propriedades
     */
    CacheInvalidator.on('properties', function (data) {
        // Recarrega as propriedades via API
        if (typeof window.carregarPropriedades === 'function') {
            window.carregarPropriedades();
        } else if (typeof window.loadProperties === 'function') {
            window.loadProperties();
        } else {
            // Fallback: re-busca os dados e renderiza
            // Obtém tenant_id da URL (máxima prioridade) ou window
            const urlParams = new URLSearchParams(window.location.search);
            const tenantIdFromUrl = urlParams.get('tenant_id');
            const tenantId = tenantIdFromUrl
                ? parseInt(tenantIdFromUrl)
                : window.SUBDOMAIN_TENANT_ID || 1;

            // Passa o tenant_id detectado
            const url = new URL('/api/v1/properties/getProperties.php', window.location.origin);
            url.searchParams.append('tenant_id', tenantId);

            fetch(url.toString())
                .then((res) => res.json())
                .then((data) => {
                    const props = Array.isArray(data)
                        ? data
                        : data && data.success && Array.isArray(data.data)
                          ? data.data
                          : data && Array.isArray(data.data)
                            ? data.data
                            : [];

                    if (typeof window.renderizarImoveis === 'function') {
                        window.dadosImoveis = props;
                        window.renderizarImoveis(props);
                    }
                })
                .catch((err) => {
                    /* Silencioso */
                });
        }
    });

    /**
     * Callback para recarregar banners
     */
    CacheInvalidator.on('banners', function (data) {
        if (typeof window.carregarBanners === 'function') {
            window.carregarBanners();
        } else {
            // Tenta recarregar os carousels
            location.reload();
        }
    });

    /**
     * Callback para recarregar configurações da empresa
     */
    CacheInvalidator.on('company-settings', function (data) {
        // Remove cache local COM ISOLAMENTO POR TENANT
        const urlParams = new URLSearchParams(window.location.search);
        const tenantIdFromUrl = urlParams.get('tenant_id');
        const tenantId = tenantIdFromUrl
            ? parseInt(tenantIdFromUrl)
            : window.SUBDOMAIN_TENANT_ID || 1;

        localStorage.removeItem('logoPath_' + tenantId);
        localStorage.removeItem('companySettings_' + tenantId);

        // Remove cache genérico antigo também
        localStorage.removeItem('logoPath');
        localStorage.removeItem('companySettings');

        // Recarrega a página para aplicar novas configurações
        location.reload();
    });

    /**
     * Callback para recarregar leads/mensagens
     */
    CacheInvalidator.on('leads', function (data) {
        // Não é necessário recarregar a página publica para leads
    });

    /**
     * Callback para recarregar agendamentos
     */
    CacheInvalidator.on('agendamentos', function (data) {
        // Não é necessário recarregar a página publica para agendamentos
    });
});
