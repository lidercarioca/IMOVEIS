// Função para formatar o preço
function formatPrice(price) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
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
    // Regex simples e prática: alfanuméricos, pontos, sublinhados e sinais permitidos,
    // domínio com letras/números e TLD com pelo menos 2 caracteres.
    const re = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
    return re.test(String(email).trim());
}

// Mapeamento de tipos de imóveis para português
const propertyTypes = {
    house: 'Casa',
    apartment: 'Apartamento',
    commercial: 'Comercial',
    land: 'Terreno',
};

// Mapeamento de status do imóvel para português
const propertyStatus = {
    for_sale: 'À Venda',
    for_rent: 'Para Alugar',
    vendido: 'Vendido',
    alugado: 'Alugado',
    on_hold: 'Em Negociação',
    featured: 'Destaque',
};

const propertyTransactionType = {
    venda: 'À Venda',
    aluguel: 'Para Alugar',
};

// Função para gerar a mensagem com os dados do imóvel
function generatePropertyMessage(property) {
    if (!property) return '';

    // Traduz o tipo do imóvel ou usa o valor original se não encontrar tradução
    const propertyType =
        propertyTypes[property.type?.toLowerCase()] || property.type || 'Não especificado';

    // Traduz o status do imóvel ou usa o valor original se não encontrar tradução
    const status =
        propertyStatus[property.status?.toLowerCase()] || property.status || 'Não especificado';

    const para =
        propertyTransactionType[property.transactionType?.toLowerCase()] ||
        property.transactionType ||
        'Não especificado';

    return `Olá, tenho interesse no imóvel:

${property.title ? `Título: ${property.title}` : ''}
Tipo: ${propertyType}
Situação: ${para}
Status: ${status}
Localização: ${property.location || 'Não especificada'}
Preço: ${property.price ? formatPrice(property.price) : 'Não especificado'}
${property.bedrooms ? `Quartos: ${property.bedrooms}` : ''}
${property.bathrooms ? `Banheiros: ${property.bathrooms}` : ''}
${property.area ? `Área: ${property.area}m&sup2;` : ''}

Por favor, gostaria de mais informações sobre este imóvel.`;
}

// Função para abrir o modal de contato com os dados do imóvel
function openContactForm(property) {
    try {
        // debug: abertura do formulário de contato

        // Verifica se a propriedade foi passada ou usa currentProperty como fallback
        const propertyData = property || window.currentProperty;

        if (!propertyData) {
            console.error('Nenhuma propriedade disponível para o formulário de contato');
            return;
        }

        // Gera a mensagem com os dados do imóvel
        const message = generatePropertyMessage(propertyData);
        // debug: mensagem gerada para o formulário de contato

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
    // Remove todos os backdrops
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());

    // Remove classes de modal aberto
    document.body.classList.remove('modal-open');
    document.documentElement.classList.remove('modal-open');

    // Remove estilos inline que bloqueiam scroll
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
    document.body.style.overflow = '';

    document.documentElement.style.removeProperty('padding-right');
    document.documentElement.style.removeProperty('overflow');
    document.documentElement.style.overflow = '';

    // Remove outras propriedades que podem bloquear
    document.body.style.removeProperty('overscroll-behavior');
    document.documentElement.style.removeProperty('overscroll-behavior');
}

// Inicializa os handlers quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function () {
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
        phoneInput.addEventListener('input', function (e) {
            let value = e.target.value;
            value = formatPhone(value);
            e.target.value = value;
        });
    }

    // Handler de submit do formulário
    if (form && !form.hasAttribute('data-handler-attached')) {
        form.setAttribute('data-handler-attached', 'true');
        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
            const spinner = submitBtn ? submitBtn.querySelector('.spinner-border') : null;

            try {
                // Validação customizada
                this.classList.add('was-validated');
                if (!this.checkValidity()) {
                    return;
                }

                // Validação adicional de e-mail
                const emailInput = document.getElementById('contact-email');
                if (!emailInput) {
                    console.error('Campo contact-email não encontrado');
                    return;
                }
                // limpar estado anterior e trim
                emailInput.value = String(emailInput.value || '').trim();
                emailInput.setCustomValidity('');
                if (!validateEmail(emailInput.value)) {
                    emailInput.setCustomValidity('E-mail inválido');
                    emailInput.reportValidity();
                    return;
                }

                // Prepara os dados
                const formData = {
                    name: document.getElementById('contact-name').value.trim(),
                    email: emailInput.value.trim(),
                    phone: phoneInput.value.trim(), // Envia o telefone com formatação
                    message: document.getElementById('contact-message').value.trim(),
                };

                // Adiciona property_id se estiver disponível (vem do window.currentProperty)
                const propertyData = window.currentProperty;
                if (propertyData && propertyData.id) {
                    formData.property_id = propertyData.id;
                }

                // Adiciona tenant_id se estiver disponível (para isolar leads corretamente)
                // Verifica em várias fontes: URL, variable global, localStorage
                let tenantId = null;

                // 1. Tenta obter de parâmetro URL (?tenant_id=2)
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('tenant_id')) {
                    tenantId = parseInt(urlParams.get('tenant_id'));
                }

                // 2. Tenta obter de variável global (window.CURRENT_TENANT_ID)
                if (!tenantId && window.CURRENT_TENANT_ID) {
                    tenantId = parseInt(window.CURRENT_TENANT_ID);
                }

                // 3. Tenta obter de localStorage
                if (!tenantId && localStorage.getItem('current_tenant_id')) {
                    tenantId = parseInt(localStorage.getItem('current_tenant_id'));
                }

                // 4. Tenta obter de sessionStorage
                if (!tenantId && sessionStorage.getItem('current_tenant_id')) {
                    tenantId = parseInt(sessionStorage.getItem('current_tenant_id'));
                }

                // Se encontrou tenant_id válido, adiciona ao formulário
                if (tenantId && tenantId > 0) {
                    formData.tenant_id = tenantId;
                }

                // Adiciona utm_tracking_id se estiver na sessão (para rastreamento de leads via UTM)
                const utmTrackingId = sessionStorage.getItem('utm_tracking_id');
                if (utmTrackingId) {
                    formData.utm_tracking_id = utmTrackingId;
                }

                // Mostra loading
                if (submitBtn && spinner) {
                    spinner.classList.remove('d-none');
                }

                // Tenta enviar normalmente primeiro
                console.log('Tentando enviar formulário...');
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000);

                const response = await fetch('/api/v1/leads/addLead.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData),
                    signal: controller.signal,
                });

                clearTimeout(timeoutId);
                const data = await response.json();

                if (data.success) {
                    console.log('✓ Formulário enviado com sucesso');
                    showSuccessMessage(form, 'contactFormModal');
                } else {
                    throw new Error(data.message || 'Erro ao enviar mensagem');
                }
            } catch (error) {
                console.error('❌ Erro ao enviar:', {
                    name: error.name,
                    message: error.message,
                    type: typeof error,
                });

                // **SEMPRE** tenta salvar offline quando há erro (seja de rede, timeout ou outro)
                console.log('🔴 Tentando salvar offline...');

                try {
                    const emailInput = document.getElementById('contact-email');
                    const formData = {
                        name: document.getElementById('contact-name').value.trim(),
                        email: emailInput ? emailInput.value.trim() : '',
                        phone: phoneInput.value.trim(),
                        message: document.getElementById('contact-message').value.trim(),
                    };
                    const propertyData = window.currentProperty;
                    if (propertyData && propertyData.id) {
                        formData.property_id = propertyData.id;
                    }
                    let tenantId = null;
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('tenant_id')) {
                        tenantId = parseInt(urlParams.get('tenant_id'));
                    }
                    if (!tenantId && window.CURRENT_TENANT_ID) {
                        tenantId = parseInt(window.CURRENT_TENANT_ID);
                    }
                    if (!tenantId && localStorage.getItem('current_tenant_id')) {
                        tenantId = parseInt(localStorage.getItem('current_tenant_id'));
                    }
                    if (!tenantId && sessionStorage.getItem('current_tenant_id')) {
                        tenantId = parseInt(sessionStorage.getItem('current_tenant_id'));
                    }
                    if (tenantId && tenantId > 0) {
                        formData.tenant_id = tenantId;
                    }
                    const utmTrackingId = sessionStorage.getItem('utm_tracking_id');
                    if (utmTrackingId) {
                        formData.utm_tracking_id = utmTrackingId;
                    }

                    if (typeof window.offlineSync === 'undefined') {
                        console.error('✗ OfflineSync não foi carregado');
                        const errorAlert = document.createElement('div');
                        errorAlert.className = 'alert alert-danger mt-3';
                        errorAlert.textContent = 'Erro: Sistema offline não disponível.';
                        form.appendChild(errorAlert);
                        setTimeout(() => errorAlert.remove(), 5000);
                    } else {
                        console.log('⏳ Aguardando OfflineSync inicializar...');
                        const initialized = await window.offlineSync.waitForInit();
                        console.log('✓ OfflineSync pronto, isInitialized:', initialized);

                        const id = await window.offlineSync.addToQueue(
                            '/api/v1/leads/addLead.php',
                            formData,
                            'POST'
                        );

                        console.log(`✓✓✓ Formulário salvo offline com ID: ${id}`);
                        showSuccessMessage(form, 'contactFormModal', true);
                    }
                } catch (offlineError) {
                    console.error('✗ Erro ao salvar offline:', offlineError);
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger mt-3';
                    errorAlert.textContent = 'Erro: ' + offlineError.message;
                    form.appendChild(errorAlert);

                    setTimeout(() => errorAlert.remove(), 5000);
                }
            } finally {
                if (submitBtn && typeof window.utils?.restoreSubmitButtonState === 'function') {
                    window.utils.restoreSubmitButtonState(submitBtn);
                } else if (submitBtn) {
                    submitBtn.disabled = false;
                    if (spinner) {
                        spinner.classList.add('d-none');
                    }
                }

                // Garante limpeza completa de estilos de modal
                setTimeout(() => {
                    cleanupModalEffects();
                }, 100);
            }

            /**
             * Mostra mensagem de sucesso e fecha o modal
             */
            function showSuccessMessage(form, modalId, isOffline = false) {
                // Feedback visual de sucesso
                const successAlert = document.createElement('div');
                successAlert.className = 'alert alert-success mt-3';
                successAlert.textContent = isOffline
                    ? 'Lead salvo offline! Será sincronizado quando você voltar online.'
                    : 'Mensagem enviada com sucesso! Em breve entraremos em contato.';
                form.appendChild(successAlert);

                // Fecha o modal após 2 segundos
                setTimeout(() => {
                    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
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
            }
        });
    }
});
