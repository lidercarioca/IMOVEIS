/**
 * AuthManager - Gerenciamento JWT no frontend
 *
 * Este módulo centraliza o armazenamento de tokens JWT,
 * evita espalhar lógica de autenticação e prepara o frontend
 * para uma migração gradual do painel legado.
 */
(function (window) {
    const ACCESS_TOKEN_KEY = 'jwt_access_token';
    const REFRESH_TOKEN_KEY = 'jwt_refresh_token';
    const LEGACY_TOKEN_KEY = 'token';
    const storage = window.sessionStorage;

    function safeLog(message, details = null) {
        // Produção: não loga nada
    }

    function extractTokenPayload(tokens) {
        if (!tokens || typeof tokens !== 'object') {
            return {};
        }

        if (
            'access_token' in tokens ||
            'accessToken' in tokens ||
            'access' in tokens ||
            'refresh_token' in tokens ||
            'refreshToken' in tokens ||
            'refresh' in tokens
        ) {
            return tokens;
        }

        if (tokens.data && typeof tokens.data === 'object') {
            return extractTokenPayload(tokens.data);
        }

        if (tokens.tokens && typeof tokens.tokens === 'object') {
            return extractTokenPayload(tokens.tokens);
        }

        return tokens;
    }

    const AuthManager = {
        getAccessToken() {
            return storage.getItem(ACCESS_TOKEN_KEY);
        },

        getRefreshToken() {
            return storage.getItem(REFRESH_TOKEN_KEY);
        },

        hasAuth() {
            return Boolean(this.getAccessToken());
        },

        setTokens(tokens = {}) {
            const payload = extractTokenPayload(tokens);
            const accessToken = payload.access_token || payload.accessToken || payload.access || '';
            const refreshToken =
                payload.refresh_token || payload.refreshToken || payload.refresh || '';
            const stored = Boolean(accessToken || refreshToken);

            if (accessToken) {
                storage.setItem(ACCESS_TOKEN_KEY, accessToken);
                storage.setItem(LEGACY_TOKEN_KEY, accessToken);
            }

            if (refreshToken) {
                storage.setItem(REFRESH_TOKEN_KEY, refreshToken);
            }

            // Produção: não loga nada

            return stored;
        },

        clearTokens() {
            storage.removeItem(ACCESS_TOKEN_KEY);
            storage.removeItem(REFRESH_TOKEN_KEY);
            storage.removeItem(LEGACY_TOKEN_KEY);
            safeLog('Tokens JWT removidos');
        },

        logout() {
            this.clearTokens();
            window.dispatchEvent(new CustomEvent('auth:logout'));
        },

        getTokens() {
            return {
                access_token: this.getAccessToken(),
                refresh_token: this.getRefreshToken(),
            };
        },
    };

    function syncLegacyLogout() {
        document.addEventListener('click', function (event) {
            const anchor = event.target.closest('a[href*="logout.php"]');
            if (!anchor) return;
            if (window.AuthManager && typeof window.AuthManager.logout === 'function') {
                window.AuthManager.logout();
            }
        });

        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!form || !form.action || !form.action.includes('logout.php')) {
                return;
            }

            if (window.AuthManager && typeof window.AuthManager.logout === 'function') {
                window.AuthManager.logout();
            }
        });
    }

    window.AuthManager = AuthManager;
    syncLegacyLogout();
})(window);
