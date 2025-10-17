// Função para formatar o preço
function formatPrice(price) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(price);
}

// Função para formatar telefone
function formatPhone(phone) {
    phone = phone.replace(/\D/g, '');
    phone = phone.replace(/^(\d{2})(\d)/g, '($1) $2');
    phone = phone.replace(/(\d)(\d{4})$/, '$1-$2');
    return phone;
}

// Função para validar e-mail
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Mapeamento de tipos de imóveis para português
const propertyTypes = {
    'house': 'Casa',
    'apartment': 'Apartamento',
    'commercial': 'Comercial',
    'land': 'Terreno'
    
};

// Mapeamento de status do imóvel para português
const propertyStatus = {
    'for_sale': 'À Venda',
    'for_rent': 'Para Alugar',
    'vendido': 'Vendido',
    'alugado': 'Alugado',
    'on_hold': 'Em Negociação',
    'featured': 'Destaque'
};

const propertyTransactionType = {
    'venda': 'À Venda',
    'aluguel': 'Para Alugar',
   
};



// Função para gerar a mensagem com os dados do imóvel
function generatePropertyMessage(property) {
    if (!property) return '';
    
    // Traduz o tipo do imóvel ou usa o valor original se não encontrar tradução
    const propertyType = propertyTypes[property.type?.toLowerCase()] || property.type || 'Não especificado';
    
    // Traduz o status do imóvel ou usa o valor original se não encontrar tradução
    const status = propertyStatus[property.status?.toLowerCase()] || property.status || 'Não especificado';

    const para = propertyTransactionType[property.transactionType?.toLowerCase()] || property.transactionType || 'Não especificado';

    return `Olá, tenho interesse no imóvel:

${property.title ? `Título: ${property.title}` : ''}
Tipo: ${propertyType}
Situação: ${para}
Status: ${status}
Localização: ${property.location || 'Não especificada'}
Preço: ${property.price ? formatPrice(property.price) : 'Não especificado'}
${property.bedrooms ? `Quartos: ${property.bedrooms}` : ''}
${property.bathrooms ? `Banheiros: ${property.bathrooms}` : ''}
${property.area ? `Área: ${property.area}m²` : ''}

Por favor, gostaria de mais informações sobre este imóvel.`;
}

// Função para abrir o modal de contato com os dados do imóvel
function openContactForm(property) {
    try {
        console.log('Abrindo formulário de contato com propriedade:', property);

         // Verifica se a propriedade foi passada ou usa currentProperty como fallback
            const propertyData = property || window.currentProperty;

            if (!propertyData) {
            console.error('Nenhuma propriedade disponível para o formulário de contato');
            return;
        }

        // Gera a mensagem com os dados do imóvel
        const message = generatePropertyMessage(propertyData);
        console.log('Mensagem gerada:', message);

        // Limpa e reseta o formulário
        const form = document.getElementById('contact-form-modal');
        if (form) {
            form.reset();
            form.classList.remove('was-validated');
        }

        // Preenche a mensagem
        const messageField = document.getElementById('contact-message');
        if (messageField) {
            messageField.value = message;
        }

        // Fecha o modal do imóvel primeiro
        const propertyModal = document.getElementById('propertyModal');
        if (propertyModal) {
            const propertyModalInstance = bootstrap.Modal.getInstance(propertyModal);
            if (propertyModalInstance) {
                propertyModalInstance.hide();
            }
        }

        // Abre o modal do formulário de contato
        const contactFormModal = document.getElementById('contactFormModal');
        if (contactFormModal) {
            const modal = new bootstrap.Modal(contactFormModal);
            modal.show();
        } else {
            console.error('Modal de contato não encontrado no DOM');
        }
    } catch (error) {
        console.error('Erro ao abrir formulário de contato:', error);
    }
}

// Função para limpar o backdrop e classes modais
function cleanupModalEffects() {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('padding-right');
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
    document.documentElement.style.paddingRight = '';
}

// Inicializa os handlers quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    
    
    // Adiciona listener para limpar efeitos modais quando qualquer modal for fechado
    const contactFormModal = document.getElementById('contactFormModal');
    if (contactFormModal) {
        contactFormModal.addEventListener('hidden.bs.modal', cleanupModalEffects);
    }
    
    // Adiciona o evento de clique ao botão de contato
    const btnOpenContact = document.getElementById('btnOpenContact');
    if (btnOpenContact && !btnOpenContact.hasAttribute('data-handler-attached')) {
        
        btnOpenContact.setAttribute('data-handler-attached', 'true');
        btnOpenContact.addEventListener('click', () => openContactForm());
    } else {
        console.error('Botão de contato não encontrado');
    }

    const form = document.getElementById('contact-form-modal');
    const phoneInput = document.getElementById('contact-phone');
    
    // Formata o telefone enquanto digita
    if (phoneInput && !phoneInput.hasAttribute('data-handler-attached')) {
        phoneInput.setAttribute('data-handler-attached', 'true');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value;
            value = formatPhone(value);
            e.target.value = value;
        });
    }
    
    // Handler de submit do formulário
    if (form && !form.hasAttribute('data-handler-attached')) {
        form.setAttribute('data-handler-attached', 'true');
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validação customizada
            this.classList.add('was-validated');
            if (!this.checkValidity()) {
                return;
            }
            
            // Validação adicional de e-mail
            const emailInput = document.getElementById('contact-email');
            if (!validateEmail(emailInput.value)) {
                emailInput.setCustomValidity('E-mail inválido');
                return;
            }
            
            // Prepara os dados
            const formData = {
                name: document.getElementById('contact-name').value.trim(),
                email: emailInput.value.trim(),
                phone: phoneInput.value.replace(/\D/g, ''),
                message: document.getElementById('contact-message').value.trim()
            };
            
            // Mostra loading
            const submitBtn = this.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            
            try {
                const response = await fetch('/api/addLead.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    // Feedback visual de sucesso
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success mt-3';
                    successAlert.textContent = 'Mensagem enviada com sucesso! Em breve entraremos em contato.';
                    form.appendChild(successAlert);
                    
                    // Fecha o modal após 2 segundos
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('contactFormModal'));
                        if (modal) {
                            // Reseta o formulário antes de fechar o modal
                            form.reset();
                            form.classList.remove('was-validated');
                            successAlert.remove();
                            
                            // Fecha o modal usando o método do Bootstrap
                            modal.hide();
                            
                            // Espera a animação terminar antes de limpar os efeitos
                            setTimeout(() => {
                                cleanupModalEffects();
                            }, 300); // 300ms é a duração padrão da animação do Bootstrap
                        }
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Erro ao enviar mensagem');
                }
            } catch (error) {
                // Feedback visual de erro
                const errorAlert = document.createElement('div');
                errorAlert.className = 'alert alert-danger mt-3';
                errorAlert.textContent = 'Erro ao enviar mensagem: ' + error.message;
                form.appendChild(errorAlert);
                
                // Remove a mensagem de erro após 5 segundos
                setTimeout(() => errorAlert.remove(), 5000);
            } finally {
                // Restaura o botão
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
                
                // Garante que o backdrop seja removido em caso de erro também
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
            }
        });
    }
});
