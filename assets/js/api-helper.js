/**
 * API Helper - Wrapper centralizado para fetch com credenciais
 * Garante cookies de sessão e JWT automático quando disponível
 */

const originalFetch = window.fetch.bind(window);

// evita múltiplos overrides acidentais
if (window.__API_FETCH_WRAPPED__) {
    console.warn('[API] fetch já foi sobrescrito anteriormente.');
} else {
    window.__API_FETCH_WRAPPED__ = true;
}

const REFRESH_ENDPOINT = '/api/v1/auth/refreshToken.php';
let authRefreshPromise = null;

function normalizeHeaders(headers = {}) {
    if (headers instanceof Headers) {
        const normalized = {};
        headers.forEach((value, key) => {
            normalized[key] = value;
        });
        return normalized;
    }
    return { ...headers };
}

/**
 * Verifica se é request da mesma origem
 */
function isSameOriginRequest(url) {
    if (!url || typeof url !== 'string') return false;

    try {
        if (url.startsWith('http')) {
            return new URL(url).origin === window.location.origin;
        }
        return true; // URLs relativas são same-origin
    } catch {
        return false;
    }
}

function getRequestPath(url) {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return null;
    }
}

function isRefreshEndpoint(url) {
    const path = getRequestPath(url);
    return path === REFRESH_ENDPOINT;
}

function hasRefreshToken() {
    return (
        window.AuthManager &&
        typeof window.AuthManager.getRefreshToken === 'function' &&
        Boolean(window.AuthManager.getRefreshToken())
    );
}

function canRefreshRequest(url, options = {}) {
    if (!isSameOriginRequest(url)) {
        return false;
    }

    if (isRefreshEndpoint(url)) {
        return false;
    }

    if (options.__retryAfterRefresh) {
        return false;
    }

    return hasRefreshToken();
}

async function refreshAccessToken() {
    if (authRefreshPromise) {
        return authRefreshPromise;
    }

    if (!hasRefreshToken()) {
        return Promise.reject(new Error('Refresh token não disponível'));
    }

    authRefreshPromise = (async () => {
        const refreshToken = window.AuthManager.getRefreshToken();
        const payload = JSON.stringify({ refresh_token: refreshToken });

        try {
            const response = await originalFetch(REFRESH_ENDPOINT, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: payload,
            });

            const contentType = response.headers.get('content-type') || '';
            let data = null;

            if (contentType.includes('application/json')) {
                try {
                    data = await response.json();
                } catch {
                    data = null;
                }
            }

            if (response.ok && data && data.success) {
                if (window.AuthManager && typeof window.AuthManager.setTokens === 'function') {
                    window.AuthManager.setTokens(data);
                }
                dispatchApiEvent('auth:refreshed', { status: response.status });
                return data;
            }

            throw new Error(data?.error || `Falha ao renovar token (${response.status})`);
        } catch (error) {
            if (window.AuthManager && typeof window.AuthManager.clearTokens === 'function') {
                window.AuthManager.clearTokens();
            }
            dispatchApiEvent('auth:expired', { error: error?.message || 'refresh_failed' });
            throw error;
        } finally {
            authRefreshPromise = null;
        }
    })();

    return authRefreshPromise;
}

/**
 * Retorna header de autenticação JWT (se existir)
 */
function getAuthHeader() {
    if (window.AuthManager && typeof window.AuthManager.getAccessToken === 'function') {
        const token = window.AuthManager.getAccessToken();
        if (token) {
            return `Bearer ${token}`;
        }
    }
    return null;
}

/**
 * Override global do fetch
 */
window.fetch = async function (url, options = {}) {
    options = options || {};

    // garante cookies de sessão
    options.credentials ??= 'include';

    // aplica auth apenas para mesma origem
    if (isSameOriginRequest(url)) {
        const authHeader = getAuthHeader();

        if (authHeader) {
            const headers = normalizeHeaders(options.headers);

            // evita conflito de headers
            delete headers.authorization;
            delete headers.Authorization;

            headers.Authorization = authHeader;

            options.headers = headers;
        }
    }

    return originalFetch(url, options);
};

/**
 * Dispara eventos globais de API
 */
function dispatchApiEvent(eventName, detail) {
    window.dispatchEvent(new CustomEvent(eventName, { detail }));
}

/**
 * Trata eventos HTTP globais
 */
function handleResponseEvents(url, response, data) {
    const detail = { url, status: response.status, data };

    if (response.status === 401) {
        dispatchApiEvent('auth:unauthorized', detail);
    } else if (response.status === 403) {
        dispatchApiEvent('auth:forbidden', detail);
    } else if (response.status === 429) {
        dispatchApiEvent('api:rate-limit', detail);
    } else if (response.status >= 500) {
        dispatchApiEvent('api:server-error', detail);
    }
}

/**
 * Função principal de API
 */
async function apiCall(url, options = {}) {
    try {
        options.credentials ??= 'include';

        let response = await fetch(url, options);

        if (response.status === 401 && canRefreshRequest(url, options)) {
            try {
                await refreshAccessToken();
                options.__retryAfterRefresh = true;
                response = await fetch(url, options);
            } catch (refreshError) {
                return {
                    success: false,
                    status: 401,
                    error: 'Sessão expirada, faça login novamente',
                    data: null,
                    response: null,
                };
            }
        }

        const contentType = response.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');

        // Parsing resiliente: lê o body apenas uma vez
        const rawText = await response.text();
        let data = null;
        if (isJson) {
            try {
                data = JSON.parse(rawText);
            } catch (jsonError) {
                console.warn('Failed to parse JSON, falling back to text:', jsonError);
                data = rawText;
            }
        } else {
            data = rawText;
        }

        // eventos globais para erros críticos
        if (
            response.status === 401 ||
            response.status === 403 ||
            response.status === 429 ||
            response.status >= 500
        ) {
            handleResponseEvents(url, response, data);
        }

        // erro HTTP padronizado
        if (!response.ok) {
            const errorMsg = isJson
                ? data?.error || data?.message || `HTTP ${response.status}: ${response.statusText}`
                : `HTTP ${response.status}: ${response.statusText}`;

            // Normaliza dados de erro também
            const normalizedData =
                isJson && data ? normalizeApiResponse(data, response.status) : null;

            return {
                success: false,
                status: response.status,
                error: errorMsg,
                data: normalizedData || data,
                response,
            };
        }

        // Sucesso: normaliza dados antes de retornar
        const normalizedData = isJson && data ? normalizeApiResponse(data, response.status) : data;

        return {
            success: true,
            status: response.status,
            data: normalizedData,
            response,
        };
    } catch (error) {
        console.error('API Call Error:', error);

        return {
            success: false,
            status: null,
            error: 'Erro de comunicação com o servidor',
            data: null,
            response: null,
        };
    }
}

// expõe globalmente
window.apiCall = apiCall;

/**
 * ==========================================
 * FASE 16.5 - Normalização Frontend/API
 * ==========================================
 *
 * Camada de normalização para suportar
 * respostas legadas e novas de forma uniforme.
 * Permite consumo gradual do novo padrão
 * sem quebrar compatibilidade.
 */

/**
 * Detecta se a resposta é do formato legado
 * (array puro, success+data, ou error)
 */
function isLegacyResponse(data) {
    // Formato 1: array puro []
    if (Array.isArray(data)) {
        return true;
    }

    // Formato 2: objeto legacy {success, data, error}
    // sem os campos novos (message, errors, meta)
    if (typeof data === 'object' && data !== null) {
        const hasLegacyFields =
            data.success !== undefined || data.data !== undefined || data.error !== undefined;
        const missingNewFields =
            data.message === undefined && data.errors === undefined && data.meta === undefined;

        return hasLegacyFields && missingNewFields;
    }

    return false;
}

/**
 * Detecta se a resposta é do formato novo
 * (ApiResponse padronizado)
 */
function isStandardApiResponse(data) {
    if (typeof data !== 'object' || data === null) {
        return false;
    }

    // Verifica presença dos campos novo padrão
    return (
        data.success !== undefined &&
        data.message !== undefined &&
        data.data !== undefined &&
        data.errors !== undefined &&
        data.meta !== undefined
    );
}

/**
 * NORMALIZAÇÃO CENTRAL
 *
 * Converte qualquer formato de resposta para o novo padrão:
 * {
 *   success: boolean,
 *   message: string|null,
 *   data: any,
 *   errors: array|object,
 *   meta: object
 * }
 *
 * @param {any} rawData - Resposta bruta da API
 * @param {number} httpStatus - Status HTTP da resposta (para contexto)
 * @returns {object} Resposta normalizada
 */
const isDevelopmentEnvironment =
    typeof window !== 'undefined' &&
    (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');

function normalizeApiResponse(rawData, httpStatus = 200) {
    // Se já está em formato novo, retorna como está
    if (isStandardApiResponse(rawData)) {
        if (isDevelopmentEnvironment) {
            console.debug('[API NORMALIZER] Resposta já é formato novo', rawData);
        }
        return rawData;
    }

    // Caso 1: Array puro []
    if (Array.isArray(rawData)) {
        if (isDevelopmentEnvironment) {
            console.debug('[API NORMALIZER] Detectado array puro, normalizando');
        }
        return {
            success: true,
            message: null,
            data: rawData,
            errors: [],
            meta: { legacy: true, originalFormat: 'array' },
        };
    }

    // Caso 2: Objeto legacy
    if (typeof rawData === 'object' && rawData !== null) {
        // Caso 2a: {error: "mensagem"}
        if (rawData.error && !rawData.success) {
            if (isDevelopmentEnvironment) {
                console.debug('[API NORMALIZER] Detectado erro legado', rawData.error);
            }
            return {
                success: false,
                message: rawData.error,
                data: null,
                errors: [],
                meta: { legacy: true, originalFormat: 'error' },
            };
        }

        // Caso 2b: {success: true, data: [...]} ou {success: false, data: ...}
        if (rawData.success !== undefined) {
            if (isDevelopmentEnvironment) {
                console.debug('[API NORMALIZER] Detectado formato {success, data}', {
                    success: rawData.success,
                });
            }

            // Se tem .data, usa .data
            // Se não tem .data mas tem outras propriedades (como kpis, top_brokers), usa o objeto inteiro
            let dataPayload = rawData.data;
            if (dataPayload === undefined) {
                // Cria um novo objeto com as propriedades, excluindo success/error/message/errors
                dataPayload = {};
                for (const key in rawData) {
                    if (
                        key !== 'success' &&
                        key !== 'error' &&
                        key !== 'message' &&
                        key !== 'errors' &&
                        key !== 'meta'
                    ) {
                        dataPayload[key] = rawData[key];
                    }
                }
                // Se ficou vazio, usa null
                if (Object.keys(dataPayload).length === 0) {
                    dataPayload = null;
                }
            }

            return {
                success: rawData.success,
                message: rawData.error || rawData.message || null,
                data: dataPayload,
                errors: rawData.errors || [],
                meta: { legacy: true, originalFormat: 'success-data' },
            };
        }

        // Caso 2c: Objeto plano (não é sucesso/erro estruturado)
        if (isDevelopmentEnvironment) {
            console.debug('[API NORMALIZER] Objeto plano detectado, envolvendo como data');
        }
        return {
            success: httpStatus >= 200 && httpStatus < 300,
            message: null,
            data: rawData,
            errors: [],
            meta: { legacy: true, originalFormat: 'plain-object' },
        };
    }

    // Caso 3: Tipo primitivo (string, number, boolean, etc)
    if (isDevelopmentEnvironment) {
        console.debug('[API NORMALIZER] Tipo primitivo detectado', typeof rawData);
    }
    return {
        success: httpStatus >= 200 && httpStatus < 300,
        message: null,
        data: rawData,
        errors: [],
        meta: { legacy: true, originalFormat: 'primitive' },
    };
}

// expõe globalmente para testes e extensões
window.normalizeApiResponse = normalizeApiResponse;
window.isLegacyResponse = isLegacyResponse;
window.isStandardApiResponse = isStandardApiResponse;
