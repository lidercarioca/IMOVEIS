/**
 * Módulo de Sub-abas de Relatórios
 * Gerencia as sub-abas (Relatórios e Pipeline) dentro da aba Relatórios
 */

document.addEventListener('DOMContentLoaded', function() {
    // Verifica se a seção de relatórios existe (usuário tem acesso)
    const relatoriosSection = document.getElementById('relatorios-listar-content');
    if (!relatoriosSection) {
        return; // Usuário comum não tem acesso, não inicializa
    }
    
    inicializarSubtabsRelatorios();
    
    // Pequeno delay para garantir que o DOM está completamente pronto
    setTimeout(() => {
        carregarConteudoSubaba('relatorios-listar');
    }, 100);
});

/**
 * Inicializa eventos das sub-abas de relatórios
 */
function inicializarSubtabsRelatorios() {
    const links = document.querySelectorAll('.relatorios-subtab-link');
    
    links.forEach(link => {
        link.addEventListener('click', async (e) => {
            e.preventDefault();
            const subtab = link.dataset.subtab;
            
            // Remove classe active de todos os links
            links.forEach(l => l.classList.remove('active'));
            links.forEach(l => l.classList.remove('border-blue-600'));
            links.forEach(l => l.classList.add('border-transparent'));
            
            // Adiciona classe active ao link clicado
            link.classList.add('active');
            link.classList.add('border-blue-600');
            link.classList.remove('border-transparent');
            
            // Oculta todos os conteúdos das sub-abas
            document.querySelectorAll('.relatorios-subtab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Mostra o conteúdo da sub-aba selecionada
            const contentId = subtab + '-content';
            const content = document.getElementById(contentId);
            if (content) {
                content.classList.remove('hidden');
                
                // Carrega conteúdo específico se necessário
                if (subtab === 'pipeline-view') {
                    await carregarConteudoSubaba('pipeline-view');
                } else if (subtab === 'utm-analytics') {
                    if (typeof carregarAnaliticsUTM === 'function') {
                        carregarAnaliticsUTM();
                    }
                } else if (subtab === 'relatorios-listar') {
                    await carregarConteudoSubaba('relatorios-listar');
                }
            }
        });
    });
}

/**
 * Carrega o conteúdo da sub-aba selecionada
 * @param {string} subtab - Nome da sub-aba (relatorios-listar ou pipeline-view)
 */
function carregarConteudoSubaba(subtab) {
    const contentId = subtab + '-content';
    const container = document.getElementById(contentId);
    
    if (!container) {
        // Usuário não tem acesso a esta seção, retorna silenciosamente
        return;
    }
    
    // Se já tem conteúdo, não recarrega
    if (container.innerHTML.trim() !== '' && subtab === 'relatorios-listar') {
        return;
    }
    
    if (subtab === 'relatorios-listar') {
        // O conteúdo já está presente no HTML
        // Apenas certificar que está visível
        container.classList.remove('hidden');
    } else if (subtab === 'pipeline-view') {
        // Carregar o pipeline se necessário
        if (typeof carregarPipeline === 'function') {
            carregarPipeline();
        }
    }
}

/**
 * Função para carregar o pipeline (se existir)
 */
function carregarPipeline() {
    // Implementação do carregamento do pipeline
    const loadingMessage = document.getElementById('loadingMessage-relatorios');
    const pipelineContainer = document.getElementById('pipelineContainer-relatorios');
    
    if (loadingMessage) {
        loadingMessage.style.display = 'block';
    }
    if (pipelineContainer) {
        pipelineContainer.style.display = 'none';
    }
    
    // Chama a função initializePipeline que está definida em pipeline.js
    if (typeof initializePipeline === 'function') {
        initializePipeline().catch(error => {
            console.error('Erro ao carregar pipeline:', error);
        });
    } else {
        console.error('initializePipeline não está disponível');
    }
}
