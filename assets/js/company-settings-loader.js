// assets/js/company-settings-loader.js
// Camada única de carregamento para company settings.
// Expõe `window.getCompanySettings()` e garante uma única requisição global
(function () {
    'use strict';

    function normalizeTenantId(value) {
        if (value === null || value === undefined) return null;
        if (typeof value === 'number' && Number.isFinite(value) && value > 0) {
            return value;
        }
        const parsed = parseInt(String(value).trim(), 10);
        return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
    }

    function detectTenantId() {
        const serverTenantId = normalizeTenantId(window.TENANT_ID_SERVER);
        if (serverTenantId) return serverTenantId;

        const subdomainTenantId = normalizeTenantId(window.SUBDOMAIN_TENANT_ID);
        if (subdomainTenantId) return subdomainTenantId;

        try {
            const params = new URLSearchParams(window.location.search || '');
            const queryTenantId = normalizeTenantId(params.get('tenant_id'));
            if (queryTenantId) return queryTenantId;
        } catch (e) {}

        try {
            const metaTenantNode = document.querySelector('meta[name="tenant-id"]');
            const metaTenantId = normalizeTenantId(metaTenantNode?.getAttribute('content'));
            if (metaTenantId) return metaTenantId;
        } catch (e) {}

        // Não assumir tenant_id fixo — retornar null quando não identificado
        return null;
    }

    function buildUrlWithTenant() {
        // Use URL exposta se disponível (preserva tenant usado por páginas que já definiram)
        if (window.COMPANY_SETTINGS_URL) {
            try {
                const u = new URL(window.COMPANY_SETTINGS_URL, window.location.origin);
                const t = normalizeTenantId(u.searchParams.get('tenant_id'));
                if (t) {
                    return window.COMPANY_SETTINGS_URL;
                }
                const tenantId = detectTenantId();
                if (tenantId) {
                    u.searchParams.set('tenant_id', tenantId);
                    return u.toString();
                }
                return window.COMPANY_SETTINGS_URL;
            } catch (e) {
                throw e;
            }
        }
        const base = '/api/v1/settings/getCompanySettings.php';
        const tenantId = detectTenantId();
        if (!tenantId) {
            throw new Error('Tenant ID não identificado para carregar company settings');
        }
        return `${base}?tenant_id=${encodeURIComponent(tenantId)}`;
    }

    // Expor função global que sempre retorna a mesma promise por página
    window.getCompanySettings = function getCompanySettings() {
        // Reutiliza promise existente se já criada
        if (window.COMPANY_SETTINGS_PROMISE) {
            return window.COMPANY_SETTINGS_PROMISE;
        }

        // Determina URL com tenant e expõe para consumidores existentes
        const url = buildUrlWithTenant();
        window.COMPANY_SETTINGS_URL = window.COMPANY_SETTINGS_URL || url;

        // Cria e armazena a promise global imediatamente para evitar múltiplos fetches
        window.COMPANY_SETTINGS_PROMISE = fetch(window.COMPANY_SETTINGS_URL, {
            credentials: 'include',
        }).then((res) => {
            if (!res.ok) throw new Error('getCompanySettings failed: ' + res.status);
            return res.json();
        });

        return window.COMPANY_SETTINGS_PROMISE;
    };

    // Expor a mesma lógica de tenant_id para outros scripts do módulo SETTINGS
    window.getCompanySettingsTenantId = detectTenantId;

    // Se alguém já criou a promise manualmente antes deste loader, não fazemos nada.
})();
