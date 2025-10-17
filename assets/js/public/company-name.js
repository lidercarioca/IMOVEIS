document.addEventListener('DOMContentLoaded', async function() {
    const companyNameElement = document.getElementById('company-name');
    const companyNameFooter = document.getElementById('company-name-footer');

    // Se nenhum dos elementos existir, não continua
    if (!companyNameElement && !companyNameFooter) {
        console.error('Nenhum elemento de nome da empresa encontrado');
        return;
    }

    try {
        const response = await fetch('/api/getCompanySettings.php');
        const data = await response.json();

        if (data.success && data.data && data.data.company_name) {
            // Atualiza os elementos se eles existirem
            if (companyNameElement) {
                companyNameElement.textContent = data.data.company_name;
            }
            if (companyNameFooter) {
                companyNameFooter.textContent = data.data.company_name;
            }
            // Atualiza também o título da página
            document.title = `${data.data.company_name} - Seu Imóvel dos Sonhos`;
        } else {
            throw new Error('Nome da empresa não encontrado nas configurações');
        }
    } catch (error) {
        console.error('Erro ao carregar nome da empresa:', error);
        const defaultName = 'RR Imóveis';
        if (companyNameElement) {
            companyNameElement.textContent = defaultName;
        }
        if (companyNameFooter) {
            companyNameFooter.textContent = defaultName;
        }
    }
});
// Sistema para atualizar em tempo real
if ('BroadcastChannel' in window) {
    const channel = new BroadcastChannel('configuracoes_empresa');
    channel.onmessage = (event) => {
        if (event.data && event.data.tipo === 'atualizar_nome') {
            const companyNameElement = document.getElementById('company-name');
            if (companyNameElement && event.data.nome) {
                companyNameElement.textContent = event.data.nome;
                document.title = `${event.data.nome} - Seu Imóvel dos Sonhos`;
            }
        }
    };
}