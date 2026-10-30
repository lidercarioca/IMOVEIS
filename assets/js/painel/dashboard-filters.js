/**
 * Dashboard Filters Manager
 * Gerencia filtros avançados para dashboard
 * Date: 2026-01-16
 */

window.DashboardFilters = {
    filters: {
        period: 7,
        user_id: null,
        category: null,
        region: null,
    },

    /**
     * Inicializa o sistema de filtros
     */
    init() {
        // Event listeners para período
        document.querySelectorAll('[data-period]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.filters.period = parseInt(btn.dataset.period);
                this.applyFilters();
            });
        });

        // Event listeners para outros filtros
        const userFilter = document.getElementById('filter-user');
        const categoryFilter = document.getElementById('filter-category');
        const regionFilter = document.getElementById('filter-region');

        if (userFilter) {
            userFilter.addEventListener('change', (e) => {
                this.filters.user_id = e.target.value ? parseInt(e.target.value) : null;
                this.applyFilters();
            });
        }

        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.filters.category = e.target.value || null;
                this.applyFilters();
            });
        }

        if (regionFilter) {
            regionFilter.addEventListener('change', (e) => {
                this.filters.region = e.target.value || null;
                this.applyFilters();
            });
        }

        // Carregar lista de vendedores
        this.loadUsersList();
        // Carregar lista de regiões
        this.loadRegionsList();
    },

    /**
     * Aplica os filtros atuais
     */
    applyFilters() {
        const params = new URLSearchParams();

        params.append('period', this.filters.period);
        if (this.filters.user_id) params.append('user_id', this.filters.user_id);
        if (this.filters.category) params.append('category', this.filters.category);
        if (this.filters.region) params.append('region', this.filters.region);

        // Mostrar loading
        DashboardKPIs.setLoadingState(true);

        // Usar endpoint filtrado se houver filtros
        const hasFilters = this.filters.user_id || this.filters.category || this.filters.region;
        const endpoint = hasFilters
            ? `/api/relatorios/getFilteredDashboardKPIs.php?${params}`
            : `/api/relatorios/getDashboardKPIs.php?period=${this.filters.period}`;

        fetch(endpoint, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then((data) => {
                if (!data.success) throw new Error(data.error || 'Erro ao carregar KPIs');

                DashboardKPIs.renderKPIs(data.kpis);

                if (data.top_brokers) {
                    DashboardKPIs.renderTopBrokers(data.top_brokers);
                }

                DashboardKPIs.setLoadingState(false);
            })
            .catch((error) => {
                console.error('Erro ao aplicar filtros:', error);
                DashboardKPIs.showError('Erro ao carregar dados com filtros');
                DashboardKPIs.setLoadingState(false);
            });
    },

    /**
     * Carrega lista de usuários (vendedores)
     */
    loadUsersList() {
        fetch('/api/v1/users/index.php?role=broker', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((data) => {
                const select = document.getElementById('filter-user');
                if (!select || !data.data) return;

                data.data.forEach((user) => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.name;
                    select.appendChild(option);
                });
            })
            .catch((e) => console.error('Erro ao carregar vendedores:', e));
    },

    /**
     * Carrega lista de regiões
     */
    loadRegionsList() {
        fetch('/api/v1/properties/getRegions.php', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((data) => {
                const select = document.getElementById('filter-region');
                if (!select || !data.data) return;

                data.data.forEach((region) => {
                    const option = document.createElement('option');
                    option.value = region.neighborhood;
                    option.textContent = region.neighborhood;
                    select.appendChild(option);
                });
            })
            .catch((e) => console.error('Erro ao carregar regiões:', e));
    },

    /**
     * Limpa todos os filtros
     */
    clearFilters() {
        this.filters = {
            period: 7,
            user_id: null,
            category: null,
            region: null,
        };

        document.getElementById('filter-user').value = '';
        document.getElementById('filter-category').value = '';
        document.getElementById('filter-region').value = '';

        this.applyFilters();
    },
};

// Inicializar quando documento carregar
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        DashboardFilters.init();
    }, 500);
});
