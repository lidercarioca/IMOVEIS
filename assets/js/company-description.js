// Script para gerenciar a descrição da empresa
document.addEventListener('DOMContentLoaded', async function() {
  const descriptionElement = document.getElementById('company-description');
  if (!descriptionElement) return;

  try {
    const response = await fetch('/api/getCompanySettings.php');
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    const data = await response.json();
    
    if (!data.success || !data.data) {
      throw new Error('Dados não encontrados');
    }

    const description = data.data.company_description;
    if (!description) {
      throw new Error('Descrição não encontrada');
    }

    // Divide o texto em parágrafos e remove linhas vazias
    const paragraphs = description.split('\n')
      .map(p => p.trim())
      .filter(p => p);

    if (paragraphs.length > 0) {
      descriptionElement.innerHTML = paragraphs
        .map(p => `<p class="text-secondary mb-4">${p}</p>`)
        .join('');
    }
  } catch (error) {
    console.error('Erro ao carregar descrição:', error);
    descriptionElement.innerHTML = `
      <p class="text-secondary mb-4">
        Há mais de 15 anos no mercado, a RR Imóveis se destaca pela excelência e compromisso com nossos clientes.
        Nossa missão é encontrar o imóvel perfeito para você e sua família, com atendimento personalizado e as
        melhores condições do mercado.
      </p>
      <p class="text-secondary mb-4">
        Contamos com uma equipe de corretores especializados, prontos para oferecer todo o suporte necessário em
        cada etapa da negociação, desde a busca pelo imóvel ideal até a finalização do contrato.
      </p>
    `;
  }
});
