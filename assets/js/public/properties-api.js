/**
 * API Centralizada para Operações com Imóveis
 * Biblioteca compartilhada para evitar duplicação de código
 * Usada por main.js e todos-imoveis.js
 */

window.PropertiesAPI = (function () {
    'use strict';

    function normalizePropertiesResponse(payload) {
        if (Array.isArray(payload)) {
            return payload;
        }

        if (payload && payload.success && Array.isArray(payload.data)) {
            return payload.data;
        }

        if (payload && Array.isArray(payload.data)) {
            return payload.data;
        }

        console.warn(
            '[PropertiesAPI] Payload de propriedades inválido, retornando array vazio',
            payload
        );
        return [];
    }

    /**
     * Busca imóveis da API com suporte a tenant_id
     * @param {Object} options - Opções de busca
     * @returns {Promise<Array>} Array de imóveis
     */
    async function fetchProperties(options = {}) {
        try {
            // Obtém tenant_id com prioridade: URL > window
            const urlParams = new URLSearchParams(window.location.search);
            const tenantIdFromUrl = urlParams.get('tenant_id');
            const tenantId = tenantIdFromUrl
                ? parseInt(tenantIdFromUrl)
                : window.SUBDOMAIN_TENANT_ID || 1;

            // Constrói URL da API
            const url = new URL('/api/v1/properties/getProperties.php', window.location.origin);
            url.searchParams.append('tenant_id', tenantId);

            const res = await fetch(url.toString());
            if (!res.ok) throw new Error(`API error: ${res.status}`);

            let properties = normalizePropertiesResponse(await res.json());

            // Filtra imóveis inativos/pendentes por padrão
            if (options.filterInactive !== false) {
                properties = properties.filter((property) => {
                    if (!property.status) return true;
                    const st = property.status.toLowerCase();
                    return !['inativo', 'inactive', 'pendente', 'pending'].includes(st);
                });
            }

            return properties;
        } catch (error) {
            console.error('[PropertiesAPI] Erro ao buscar propriedades:', error);
            throw error;
        }
    }

    /**
     * Carrega imóveis e armazena globalmente
     * @param {Function} onSuccess - Callback ao sucesso
     * @param {Function} onError - Callback ao erro
     */
    async function loadAndCache(onSuccess, onError) {
        try {
            const properties = await fetchProperties();

            // Armazena globalmente
            window.dadosImoveis = properties;
            window.dadosImoveisOriginal = [...properties];

            // Dispara evento
            try {
                document.dispatchEvent(
                    new CustomEvent('dadosImoveisLoaded', {
                        detail: { count: properties.length },
                    })
                );
            } catch (e) {
                // Silencioso
            }

            // Callback
            if (onSuccess && typeof onSuccess === 'function') {
                onSuccess(properties);
            }

            return properties;
        } catch (error) {
            if (onError && typeof onError === 'function') {
                onError(error);
            }
            throw error;
        }
    }

    /**
     * Expõe a API pública
     */
    return {
        fetchProperties,
        loadAndCache,
    };
})();
