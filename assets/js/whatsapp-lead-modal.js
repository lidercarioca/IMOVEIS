/**
 * Modal de Contato via WhatsApp
 * Captura nome e telefone, salva como lead e redireciona para WhatsApp
 */

window.whatsappChatIntegration = {
    enabled: false,
    credentials: {},
};

// Carrega o número de WhatsApp da empresa no início e dados de integração
(async function loadCompanyWhatsApp() {
    try {
        const data = await window.getCompanySettings();
        if (data.success && data.data) {
            const company = data.data;
            let whatsapp = company.company_whatsapp
                ? String(company.company_whatsapp).replace(/\D/g, '')
                : '';
            const integration = company.whatsapp_chat_integration || {
                enabled: false,
                credentials: {},
            };
            window.whatsappChatIntegration = {
                enabled: !!integration.enabled,
                credentials: integration.credentials || {},
            };

            if (window.whatsappChatIntegration.enabled) {
                const integrationPhone = String(
                    window.whatsappChatIntegration.credentials.phone_number || ''
                ).replace(/\D/g, '');
                if (integrationPhone) {
                    whatsapp = integrationPhone;
                }
            }

            if (!whatsapp) {
                whatsapp = '5511999999999';
            }

            if (!whatsapp.startsWith('55')) {
                whatsapp = '55' + whatsapp;
            }
            window.company_whatsapp = whatsapp;
            renderFloatingWhatsAppButton();
        } else {
            window.company_whatsapp = '5511999999999';
            renderFloatingWhatsAppButton();
        }
    } catch (e) {
        console.error('Erro ao carregar WhatsApp:', e);
        window.company_whatsapp = '5511999999999';
        renderFloatingWhatsAppButton();
    }
})();

function getContactStageStatus(stageLabel) {
    const stageMap = {
        Novo: 'new',
        'Em contato': 'contacted',
        Convertido: 'closed',
        Perdido: 'cancelled',
        Negociando: 'negotiating',
    };
    return stageMap[stageLabel] || 'new';
}

function getWhatsAppButtonPositionStyles(position) {
    switch (position) {
        case 'Canto inferior esquerdo':
            return 'left: 20px; bottom: 20px;';
        case 'Centro inferior':
            return 'left: 50%; transform: translateX(-50%); bottom: 20px;';
        case 'Topo direito':
            return 'right: 20px; top: 20px;';
        case 'Canto inferior direito':
        default:
            return 'right: 20px; bottom: 20px;';
    }
}

function renderFloatingWhatsAppButton() {
    if (!window.whatsappChatIntegration || !window.whatsappChatIntegration.enabled) {
        return;
    }

    const existingButton = document.getElementById('whatsapp-chat-floating-button');
    if (existingButton) {
        return;
    }

    const position =
        window.whatsappChatIntegration.credentials.button_position || 'Canto inferior direito';
    const style = getWhatsAppButtonPositionStyles(position);
    const buttonText = window.whatsappChatIntegration.credentials.initial_message
        ? 'WhatsApp'
        : 'Atendimento';

    const button = document.createElement('button');
    button.id = 'whatsapp-chat-floating-button';
    button.type = 'button';
    button.innerHTML = `<i class="fab fa-whatsapp"></i> ${buttonText}`;
    button.style.cssText = `
    position: fixed;
    z-index: 99990;
    ${style}
    padding: 12px 18px;
    border: none;
    border-radius: 999px;
    background: #25D366;
    color: white;
    font-size: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: inherit;
  `;
    button.addEventListener('click', function () {
        abrirModalWhatsAppLead({});
    });

    document.body.appendChild(button);
}

function abrirModalWhatsAppLead(property = {}) {
    // Fecha o modal do imóvel se estiver aberto
    const propertyModalEl = document.getElementById('propertyModal');
    if (propertyModalEl) {
        const propertyModal = bootstrap.Modal.getInstance(propertyModalEl);
        if (propertyModal) {
            propertyModal.hide();
        }
    }

    // Remove modal anterior se existir
    const oldModal = document.getElementById('whatsapp-lead-modal');
    if (oldModal) oldModal.remove();

    // Remove backdrop anterior se existir
    const oldBackdrop = document.getElementById('whatsapp-backdrop');
    if (oldBackdrop) oldBackdrop.remove();

    // Cria backdrop para desabilitar modal anterior
    const backdrop = document.createElement('div');
    backdrop.id = 'whatsapp-backdrop';
    backdrop.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: transparent;
    z-index: 9998;
  `;
    document.body.appendChild(backdrop);

    // Cria o HTML do modal
    const modalHTML = `
    <div id="whatsapp-backdrop" style="
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.45);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 99999;
    ">
      <div id="whatsapp-lead-modal" style="
        width: 100%;
        max-width: 460px;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 22px 56px rgba(0, 0, 0, 0.18);
        background: #ffffff;
      ">
        <div style="
          padding: 24px 24px 20px;
          background: linear-gradient(135deg, #20c997 0%, #0ea36b 100%);
          color: white;
        ">
          <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
            <div>
              <p style="margin: 0; font-size: 14px; font-weight: 600; opacity: 0.92;">Olá!</p>
              <h3 style="margin: 8px 0 0; font-size: 22px; font-weight: 700; line-height: 1.2;">Preencha os campos abaixo para iniciar a conversa no WhatsApp</h3>
            </div>
            <button onclick="fecharModalWhatsAppLead()" style="
              background: rgba(255,255,255,0.18);
              border: none;
              color: white;
              width: 34px;
              height: 34px;
              border-radius: 50%;
              font-size: 20px;
              line-height: 1;
              cursor: pointer;
            ">&times;</button>
          </div>
        </div>
        <form id="whatsapp-lead-form" onsubmit="enviarFormularioWhatsAppLead(event, ${property.id || 0})" style="padding: 24px;">
          ${property.title ? `<p style="margin: 0 0 18px; color: #374151; font-size: 14px;">Imóvel: <strong>${property.title}</strong></p>` : ''}
          <div style="margin-bottom: 16px;">
            <label for="whatsapp-lead-name" style="display: block; margin-bottom: 8px; font-size: 14px; color: #111827; font-weight: 600;">Nome *</label>
            <input id="whatsapp-lead-name" name="name" type="text" required placeholder="Digite seu nome" style="width: 100%; padding: 14px 16px; border: 1px solid #d1d5db; border-radius: 14px; font-size: 14px; color: #111827; background: #f8fafc; outline: none;" />
          </div>
          <div style="margin-bottom: 16px;">
            <label for="whatsapp-lead-email" style="display: block; margin-bottom: 8px; font-size: 14px; color: #111827; font-weight: 600;">E-mail *</label>
            <input id="whatsapp-lead-email" name="email" type="email" required placeholder="Digite seu e-mail" style="width: 100%; padding: 14px 16px; border: 1px solid #d1d5db; border-radius: 14px; font-size: 14px; color: #111827; background: #f8fafc; outline: none;" />
          </div>
          <div style="margin-bottom: 24px;">
            <label for="whatsapp-lead-phone" style="display: block; margin-bottom: 8px; font-size: 14px; color: #111827; font-weight: 600;">Telefone *</label>
            <div style="display: flex; gap: 10px; align-items: center;">
              <div style="display: inline-flex; align-items: center; justify-content: center; min-width: 68px; padding: 14px 12px; border: 1px solid #d1d5db; border-radius: 14px; background: #f8fafc; color: #111827; font-size: 14px;">+55</div>
              <input id="whatsapp-lead-phone" name="phone" type="tel" required placeholder="(00) 00000-0000" style="flex: 1; padding: 14px 16px; border: 1px solid #d1d5db; border-radius: 14px; font-size: 14px; color: #111827; background: #f8fafc; outline: none;" />
            </div>
          </div>
          <button type="submit" style="width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 10px; padding: 14px 16px; background: #16a34a; color: white; border: none; border-radius: 14px; font-size: 15px; font-weight: 700; cursor: pointer; transition: background .2s ease;"> <i class="fab fa-whatsapp"></i> Iniciar a conversa</button>
        </form>
      </div>
    </div>
  `;

    // Insere o modal no DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    // Formata input de telefone
    const phoneInput = document.getElementById('whatsapp-lead-phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 2) {
                    value = `(${value}`;
                } else if (value.length <= 6) {
                    value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
                } else {
                    value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7, 11)}`;
                }
            }
            e.target.value = value;
        });
    }

    // Armazena dados do imóvel no window para usar no envio
    window.propertyDataForWhatsApp = property;

    // Focus no primeiro input
    setTimeout(() => {
        const nameInput = document.getElementById('whatsapp-lead-name');
        if (nameInput) nameInput.focus();
    }, 100);
}

/**
 * Fecha o modal de WhatsApp
 */
function fecharModalWhatsAppLead() {
    const modal = document.getElementById('whatsapp-lead-modal');
    const backdrop = document.getElementById('whatsapp-backdrop');
    if (modal) modal.remove();
    if (backdrop) backdrop.remove();
}

/**
 * Envia o formulário e redireciona para WhatsApp
 */
async function enviarFormularioWhatsAppLead(event, propertyId) {
    event.preventDefault();

    const name = document.getElementById('whatsapp-lead-name').value.trim();
    const email = document.getElementById('whatsapp-lead-email').value.trim();
    const phone = document.getElementById('whatsapp-lead-phone').value.trim();

    if (!name || !email || !phone) {
        alert('Por favor, preencha todos os campos');
        return;
    }

    try {
        // Constrói informações do imóvel para a mensagem
        const property = window.propertyDataForWhatsApp || {};
        let propertyInfo = '';

        if (property.id) {
            propertyInfo += `\n\n*Imóvel de Interesse:*\n`;
            if (property.title) propertyInfo += `📍 ${property.title}\n`;
            if (property.location) propertyInfo += `Localização: ${property.location}\n`;
            if (property.neighborhood) propertyInfo += `Bairro: ${property.neighborhood}\n`;
            if (property.price) {
                const isRental = property.type && property.type.toLowerCase().includes('aluga');
                const priceFormatted =
                    typeof window.utils !== 'undefined' && window.utils.formatarPreco
                        ? window.utils.formatarPreco(property.price, isRental, property.type)
                        : `R$ ${property.price}`;
                propertyInfo += `Valor: ${priceFormatted}\n`;
            }
            if (property.area) propertyInfo += `Área: ${property.area}m&sup2; \n`;
            if (property.bedrooms) propertyInfo += `Quartos: ${property.bedrooms}\n`;
            if (property.bathrooms) propertyInfo += `Banheiros: ${property.bathrooms}\n`;
            if (property.garage) propertyInfo += `Vagas: ${property.garage}\n`;
        }

        // Cria mensagem para WhatsApp e para o banco
        const initialMessage = String(
            window.whatsappChatIntegration.credentials?.initial_message ||
                'Olá, gostaria de mais informações sobre um imóvel. Meu nome é'
        ).trim();
        const message = `${initialMessage} ${name}. Tenho interesse em mais informações sobre o imóvel.${propertyInfo}`;
        const createTicket = !!(
            window.whatsappChatIntegration &&
            window.whatsappChatIntegration.credentials &&
            parseInt(window.whatsappChatIntegration.credentials.create_ticket, 10)
        );
        const contactStage = window.whatsappChatIntegration.credentials?.contact_stage || 'Novo';
        const status = getContactStageStatus(contactStage);
        const companyWhatsapp = window.company_whatsapp || '5511999999999';
        const whatsappUrl = `https://wa.me/${companyWhatsapp}?text=${encodeURIComponent(message)}`;

        // Detecta tenant_id de múltiplas fontes (mesmo padrão do contact-form-handler.js)
        let tenantId = null;

        // 1. Verifica URL parameter (?tenant_id=2)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('tenant_id')) {
            tenantId = parseInt(urlParams.get('tenant_id'));
        }
        // 2. Verifica window.CURRENT_TENANT_ID (definido por getCompanySettings)
        else if (window.CURRENT_TENANT_ID) {
            tenantId = parseInt(window.CURRENT_TENANT_ID);
        }
        // 3. Verifica localStorage
        else if (localStorage.getItem('current_tenant_id')) {
            tenantId = parseInt(localStorage.getItem('current_tenant_id'));
        }
        // 4. Verifica sessionStorage
        else if (sessionStorage.getItem('current_tenant_id')) {
            tenantId = parseInt(sessionStorage.getItem('current_tenant_id'));
        }

        if (tenantId) {
            console.log('Tenant ID detectado para WhatsApp lead:', tenantId);
        }

        const email = document.getElementById('whatsapp-lead-email')
            ? document.getElementById('whatsapp-lead-email').value.trim()
            : 'whatsapp@lead.local';
        const leadData = {
            name: name,
            email: email || 'whatsapp@lead.local',
            phone: phone,
            property_id: propertyId > 0 ? propertyId : null,
            message: message,
            source: 'whatsapp',
            utm_tracking_id: sessionStorage.getItem('utm_tracking_id'),
            status: status,
        };

        // Adiciona tenant_id se foi detectado
        if (tenantId) {
            leadData.tenant_id = tenantId;
        }

        if (!createTicket) {
            fecharModalWhatsAppLead();
            setTimeout(() => window.open(whatsappUrl, '_blank'), 300);
            return;
        }

        const response = await fetch('/api/v1/leads/addLead.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(leadData),
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
            fecharModalWhatsAppLead();
            setTimeout(() => {
                window.open(whatsappUrl, '_blank');
            }, 300);
        } else {
            console.error('Erro na resposta:', data);
            alert('Erro ao processar o contato: ' + (data.error || 'Tente novamente'));
        }
    } catch (error) {
        console.error('Erro ao enviar formulário:', error);
        alert('Erro ao processar o contato. Tente novamente.');
    }
}

// Fecha o modal ao clicar fora dele (no backdrop)
document.addEventListener('click', function (event) {
    const backdrop = document.getElementById('whatsapp-backdrop');
    if (backdrop && event.target === backdrop) {
        fecharModalWhatsAppLead();
    }
});

// Fecha o modal ao pressionar ESC
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        fecharModalWhatsAppLead();
    }
});
