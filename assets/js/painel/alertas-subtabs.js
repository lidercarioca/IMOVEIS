/**
 * Módulo de Sub-abas de Alertas
 * Gerencia as sub-abas (Listar e Configurar) dentro da aba Alertas
 */

document.addEventListener('DOMContentLoaded', function() {
    inicializarSubtabsAlertas();
    
    // Pequeno delay para garantir que o DOM está completamente pronto
    setTimeout(() => {
        carregarConfiguracoesAlertas();
    }, 100);
});

// Também tentar quando a aba de alertas fica visível
document.addEventListener('click', function(e) {
    if (e.target?.classList?.contains('alertas-subtab-link')) {
        if (e.target.dataset.subtab === 'alertas-configurar') {
            setTimeout(() => {
                const container = document.getElementById('alertas-config-container');
                if (container && container.innerHTML.trim() === '') {
                    carregarConfiguracoesAlertas();
                }
            }, 100);
        } else if (e.target.dataset.subtab === 'alertas-listar') {
            // Carregar alertas quando clicar na sub-aba de listar
            setTimeout(() => {
                if (typeof carregarAlertas === 'function') {
                    carregarAlertas();
                    carregarStatsAlertas();
                }
            }, 100);
        }
    }
}, true);

/**
 * Inicializa eventos das sub-abas de alertas
 */
function inicializarSubtabsAlertas() {
    const links = document.querySelectorAll('.alertas-subtab-link');
    
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const subtab = link.dataset.subtab;
            
            // Remove classe active de todos os links
            links.forEach(l => l.classList.remove('active'));
            
            // Adiciona classe active ao link clicado
            link.classList.add('active');
            
            // Oculta todos os conteúdos
            const contents = document.querySelectorAll('.alertas-subtab-content');
            contents.forEach(content => content.classList.add('hidden'));
            
            // Mostra o conteúdo da subtab selecionada
            const activeContent = document.getElementById(`${subtab}-content`);
            if (activeContent) {
                activeContent.classList.remove('hidden');
            }
        });
    });
}

/**
 * Carrega as configurações de alertas do usuário
 */
async function carregarConfiguracoesAlertas() {
    try {
        // Usar endpoint v1 correto que retorna dados enriquecidos
        const res = await fetch('/api/v1/alertas/getAlertConfigs.php', {
            credentials: 'include'
        });

        // Tratamento de erros HTTP
        if (res.status === 401) {
            return;
        }
        if (!res.ok) {
            return;
        }

        const result = await res.json();

        // Obter dados do endpoint v1
        const configs = result.data || [];
        
        renderizarConfiguracoesAlertas(configs);
    } catch (e) {
        console.error('Erro ao carregar configurações:', e);
        // Carrega com valores padrão mesmo se houver erro
        renderizarConfiguracoesAlertas([]);
    }
}

/**
 * Renderiza o formulário de configurações de alertas
 */
function renderizarConfiguracoesAlertas(configs) {
    const container = document.getElementById('alertas-config-container');
    
    if (!container) {
        return;
    }

    // Se não houver configurações, renderizar com valores padrão vazio
    if (!configs || configs.length === 0) {
        container.innerHTML = '<p class="text-gray-500">Nenhuma configuração encontrada.</p>';
        return;
    }

    // Renderizar cada alerta com os dados do endpoint v1 (já enriquecidos)
    const html = configs.map(alerta => {
        const nome = alerta.nome || alerta.alert_type;
        const descricao = alerta.descricao || '';
        const unidade = alerta.unidade || '';
        const threshold = alerta.threshold ?? (alerta.default_threshold ?? '');
        const enabled = alerta.enabled ? 1 : 0;
        
        return `
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h4 class="text-lg font-semibold text-gray-800">${nome}</h4>
                        <p class="text-sm text-gray-600 mt-1">${descricao}</p>
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" 
                               id="alerta-${alerta.alert_type}-enabled" 
                               data-alerta="${alerta.alert_type}"
                               ${enabled ? 'checked' : ''}
                               class="w-5 h-5 text-blue-600 rounded focus:ring-blue-500 cursor-pointer">
                        <label for="alerta-${alerta.alert_type}-enabled" class="ml-2 text-sm font-medium text-gray-700 cursor-pointer">Ativo</label>
                    </div>
                </div>
                
                <div class="flex items-end gap-4">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Limite</label>
                        <div class="flex items-center gap-2">
                            <input type="number" 
                                   id="alerta-${alerta.alert_type}-threshold"
                                   data-alerta="${alerta.alert_type}"
                                   value="${threshold}"
                                   placeholder="Digite o valor"
                                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <span class="text-gray-600 font-medium">${unidade}</span>
                        </div>
                    </div>
                    <div class="text-xs text-gray-500">
                        Padrão: ${alerta.default_threshold || '0'} ${unidade}
                    </div>
                </div>
            </div>
        `;
    }).join('');

    container.innerHTML = html;

    // Adicionar evento ao botão de salvar
    const btnSalvar = document.getElementById('btn-salvar-config-alertas');
    if (btnSalvar) {
        btnSalvar.removeEventListener('click', salvarConfiguracoesAlertas);
        btnSalvar.addEventListener('click', salvarConfiguracoesAlertas);
    }
}

/**
 * Salva as configurações de alertas
 */
async function salvarConfiguracoesAlertas() {
    const checkboxes = document.querySelectorAll('input[id*="-enabled"][data-alerta]');
    const configs = [];

    checkboxes.forEach(checkbox => {
        const tipoAlerta = checkbox.dataset.alerta;
        const thresholdInput = document.getElementById(`alerta-${tipoAlerta}-threshold`);
        
        if (thresholdInput) {
            configs.push({
                alert_type: tipoAlerta,
                enabled: checkbox.checked ? 1 : 0,
                threshold: parseInt(thresholdInput.value) || 0
            });
        }
    });

    if (configs.length === 0) {
        mostrarAvisoErroAlerta('Nenhuma configuração encontrada.', 'warning');
        return;
    }

    try {
        const btnSalvar = document.getElementById('btn-salvar-config-alertas');
        if (btnSalvar) btnSalvar.disabled = true;

        let sucessos = 0;
        for (const config of configs) {
            const res = await fetch('/api/v1/alertas/configureAlert.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(config),
                credentials: 'include'
            });

            // Tratamento de erros
            if (res.status === 401) {
                mostrarAvisoErroAlerta('Sessão expirada. Faça login novamente.', 'error');
                window.location.href = '/login.php';
                return;
            }
            if (res.ok) {
                sucessos++;
            } else {
                console.error('Erro ao salvar alerta:', config.alert_type);
            }
        }

        // Mostrar sucesso
        if (sucessos > 0) {
            const mensagem = sucessos === configs.length ? 'Todas as configurações salvas com sucesso!' : `${sucessos} de ${configs.length} configurações salvas`;
            mostrarAvisoErroAlerta(mensagem, 'success');
            
            // Recarregar as configurações para garantir que estão atualizadas
            setTimeout(() => {
                carregarConfiguracoesAlertas();
            }, 500);
        } else {
            mostrarAvisoErroAlerta('Erro ao salvar configurações.', 'error');
        }
    } catch (e) {
        console.error('Erro:', e);
        mostrarAvisoErroAlerta('Erro ao salvar configurações.', 'error');
    } finally {
        const btnSalvar = document.getElementById('btn-salvar-config-alertas');
        if (btnSalvar) btnSalvar.disabled = false;
    }
}

