// Script para controlar as abas principais do painel
// Evita que múltiplas abas apareçam simultaneamente

document.addEventListener('DOMContentLoaded', function () {
    // Função para ocultar TODAS as abas
    function hideAllTabs() {
        document.querySelectorAll('.tab-content').forEach((el) => {
            el.classList.remove('active');
            el.style.display = 'none';
        });

        // TAMBÉM oculta TODAS as sub-abas de Configurações
        document.querySelectorAll('.settings-subtab-content').forEach((el) => {
            el.classList.add('hidden');
            el.style.display = 'none';
        });
    }

    // Função para mostrar aba específica
    async function showTab(tabName) {
        hideAllTabs();

        const element = document.getElementById(tabName);
        if (element && element.classList.contains('tab-content')) {
            element.style.display = 'block';
            element.classList.add('active');

            // Carrega dados específicos para cada aba
            if (tabName === 'settings') {
                // Carrega as configurações dinamicamente
                if (typeof carregarConfigsDinamicamente === 'function') {
                    await carregarConfigsDinamicamente();
                }
                showSettingsSubtab('banner');
            } else if (tabName === 'add-property') {
                // Carrega formulário de adicionar imóvel
                if (typeof carregarFormularioImovel === 'function') {
                    await carregarFormularioImovel();
                }
            } else if (tabName === 'properties' || tabName === 'imoveis') {
                // Carrega lista de imóveis
                if (typeof carregarImoveisPainel === 'function') {
                    await carregarImoveisPainel();
                }
            } else if (tabName === 'leads') {
                // Carrega lista de leads
                if (typeof initLeadsPagination === 'function') {
                    await initLeadsPagination();
                }
                // Inicia polling automático de leads
                if (typeof startLeadsPolling === 'function') {
                    startLeadsPolling();
                }
            } else if (tabName === 'followup') {
                // Carrega follow-up automático
                if (typeof loadFollowupDashboard === 'function') {
                    await loadFollowupDashboard();
                }
            } else if (tabName === 'alertas') {
                // Carrega alertas
                // Carregando aba alertas
                if (typeof carregarAlertas === 'function') {
                    await carregarAlertas();
                }
                if (typeof carregarStatsAlertas === 'function') {
                    await carregarStatsAlertas();
                }
            } else if (tabName === 'integrations') {
                if (typeof loadIntegrationsDashboard === 'function') {
                    await loadIntegrationsDashboard();
                }
            } else if (tabName === 'agendamentos') {
                // Carrega status do Google Calendar
                if (typeof carregarGoogleCalendar === 'function') {
                    carregarGoogleCalendar();
                }
            } else if (tabName === 'pipeline') {
                // Carrega pipeline
                if (typeof initializePipeline === 'function') {
                    await initializePipeline();
                }
            } else if (tabName === 'cobranca') {
                // Aba de Cobrança - conteúdo já está no HTML
                // Nenhum carregamento dinâmico necessário
            } else if (tabName === 'webhooks') {
                // Aba de Webhooks - conteúdo já está no HTML
                // Nenhum carregamento dinâmico necessário
            } else if (tabName === 'financeiro') {
                // Aba de Financeiro - conteúdo já está no HTML
                // Nenhum carregamento dinâmico necessário
            } else if (tabName === 'comissoes') {
                // Aba de Comissões - conteúdo já está no HTML
                // Nenhum carregamento dinâmico necessário
            }
        }

        // Atualiza o link ativo na sidebar
        document.querySelectorAll('.tab-link').forEach((link) => {
            link.classList.remove('active');
            if (link.getAttribute('href') === '#' + tabName) {
                link.classList.add('active');
            }
        });
    }

    // Função para mostrar sub-aba de Configurações
    function showSettingsSubtab(subtabName) {
        // Oculta TODAS as sub-abas
        document.querySelectorAll('.settings-subtab-content').forEach((el) => {
            el.classList.add('hidden');
            el.style.display = 'none';
        });

        // Remove active de todos os botões
        document.querySelectorAll('.settings-subtab-link').forEach((el) => {
            el.classList.remove('active');
        });

        // Mostra a sub-aba selecionada
        const subtabContent = document.getElementById(`settings-${subtabName}-content`);
        if (subtabContent) {
            subtabContent.classList.remove('hidden');
            subtabContent.style.display = 'block';
        }

        // Marca o botão como ativo
        const subtabButton = document.querySelector(
            `.settings-subtab-link[data-subtab="${subtabName}"]`
        );
        if (subtabButton) {
            subtabButton.classList.add('active');
        }

        // Carrega conteúdo específico das sub-abas
        if (subtabName === 'seguranca') {
            if (typeof carregarSeguranca === 'function') {
                carregarSeguranca();
            }
        } else if (subtabName === 'utm-campaigns') {
            if (typeof carregarCampanhasUTM === 'function') {
                carregarCampanhasUTM();
            }
        } else if (subtabName === 'google-calendar') {
            if (typeof carregarStatusGoogleCalendarSettings === 'function') {
                carregarStatusGoogleCalendarSettings();
            }
        } else if (subtabName === 'users') {
            // Carrega lista de usuários na sub-aba
            loadUsersInSubtab();
        }
    }

    // Event listeners para os links das abas principais
    document.querySelectorAll('.tab-link').forEach((link) => {
        link.addEventListener('click', async function (e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            const tabName = href.substring(1); // Remove o '#'

            // Atualiza o hash da URL
            window.location.hash = '#' + tabName;

            // Mostra a aba
            await showTab(tabName);
        });
    });

    // Event listeners para as sub-abas de Configurações
    document.querySelectorAll('.settings-subtab-link').forEach((button) => {
        button.addEventListener('click', function (e) {
            e.preventDefault();
            const subtabName = this.getAttribute('data-subtab');
            if (subtabName) {
                showSettingsSubtab(subtabName);
            }
        });
    });

    // Monitora mudanças no hash
    window.addEventListener('hashchange', async function () {
        const hash = window.location.hash.substring(1) || 'dashboard';
        await showTab(hash);
    });

    // Mostrar aba inicial
    const initialHash = window.location.hash.substring(1) || 'dashboard';
    showTab(initialHash);
});

/**
 * Função para carregar usuários na sub-aba de Configurações
 */
async function loadUsersInSubtab() {
    const container = document.getElementById('users-dynamic-content');
    if (!container) return;

    // Verifica se já está carregado
    if (container.innerHTML.trim() !== '' && container.querySelector('table')) {
        return;
    }

    container.innerHTML =
        '<div class="flex flex-col items-center justify-center min-h-[200px] text-gray-500"><i class="fas fa-users-cog text-4xl mb-4 animate-spin"></i><p>Carregando usuários...</p></div>';

    try {
        const res = await fetch('views/admin/users.php');
        if (!res.ok) throw new Error('Erro ao carregar users.php: ' + res.status);

        const html = await res.text();
        container.innerHTML = html;

        // Recarrega o JS de users para garantir inicialização dos eventos
        if (typeof initUsers === 'function') {
            initUsers();
        } else {
            // Carrega o script se não estiver presente
            const script = document.createElement('script');
            script.src = 'assets/js/painel/users.js';
            script.onload = function () {
                if (typeof initUsers === 'function') {
                    initUsers();
                }
            };
            document.head.appendChild(script);
        }
    } catch (e) {
        container.innerHTML =
            '<div class="text-red-500 text-center py-8"><i class="fas fa-exclamation-circle text-2xl mb-4"></i><p>Erro ao carregar usuários</p><p class="text-sm mt-2">' +
            e.message +
            '</p></div>';
    }
}
