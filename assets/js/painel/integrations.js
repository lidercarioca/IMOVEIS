document.addEventListener('DOMContentLoaded', function () {
  const modalEl = document.getElementById('portalIntegrationModal');
  const modalForm = document.getElementById('portalIntegrationForm');
  const saveButton = document.getElementById('integrationSaveButton');
  const cardContainer = document.getElementById('integration-cards');
  let currentIntegration = null;
  let bootstrapModal = null;

  if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrapModal = new bootstrap.Modal(modalEl);
  }

  if (modalForm) {
    modalForm.addEventListener('submit', async function (event) {
      event.preventDefault();
      await saveIntegrationSettings();
    });
  }

  if (saveButton) {
    saveButton.addEventListener('click', async function () {
      await saveIntegrationSettings();
    });
  }

  async function saveIntegrationSettings() {
    if (!currentIntegration) return;
    const portal = currentIntegration.portal;
    const enabled = document.getElementById('integrationEnabledCheckbox').checked;
    const endpoint = document.getElementById('integrationEndpointInput').value.trim();
    const credentialFields = Array.from(document.querySelectorAll('.integration-credential-field')).reduce((acc, field) => {
      if (field.type === 'checkbox') {
        acc[field.name] = field.checked ? 1 : 0;
      } else {
        acc[field.name] = field.value.trim();
      }
      return acc;
    }, {});

    saveButton.disabled = true;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Salvando...';

    try {
      const response = await fetch('/api/v1/auth/portalIntegrations.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
          portal: portal,
          enabled: enabled ? 1 : 0,
          endpoint: endpoint,
          credentials: credentialFields
        })
      });

      const data = await response.json();
      if (!data.success) {
        throw new Error(data.error || 'Falha ao salvar a integração');
      }

      await loadIntegrationsDashboard();
      currentIntegration.enabled = enabled;
      currentIntegration.endpoint = endpoint;
      currentIntegration.credentials = credentialFields;

      if (portal === 'olx' && enabled) {
        showIntegrationLinks(portal);
      } else if (bootstrapModal) {
        bootstrapModal.hide();
      }
    } catch (error) {
      const errorBox = document.getElementById('integrationModalError');
      if (errorBox) {
        errorBox.textContent = error.message;
        errorBox.style.display = 'block';
      } else {
        alert('Erro: ' + error.message);
      }
    } finally {
      saveButton.disabled = false;
      saveButton.innerHTML = 'Salvar configurações';
    }
  }

  let activeIntegrationSubtab = 'todas';

  function setActiveIntegrationSubtab(subtab) {
    activeIntegrationSubtab = subtab;
    document.querySelectorAll('.integrations-subtab-link').forEach(button => {
      button.classList.toggle('active', button.dataset.subtab === subtab);
    });
  }

  function filterIntegrationList(integrations, subtab) {
    switch (subtab) {
      case 'ativas':
        return integrations.filter(i => i.enabled);
      case 'analise-trafego':
        return integrations.filter(i => i.category === 'Análise de Tráfego');
      case 'atendimento':
        return integrations.filter(i => i.category === 'Atendimento');
      case 'automacao-marketing':
        return integrations.filter(i => i.category === 'Automação de Marketing');
      case 'central-conexoes':
        return integrations.filter(i => i.category === 'Central de Conexões');
      case 'marketing-publicidade':
        return integrations.filter(i => i.category === 'Marketing e Publicidade');
      case 'portais-imobiliarios':
        return integrations.filter(i => i.category === 'Portais Imobiliários');
      case 'todas':
      default:
        return integrations;
    }
  }

  window.loadIntegrationsDashboard = async function (subtab = 'portais-imobiliarios') {
    if (!cardContainer) return;
    setActiveIntegrationSubtab(subtab);

    cardContainer.innerHTML = `<div class="col-span-full bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center text-gray-600">
      <i class="fas fa-spinner fa-spin text-3xl mb-4"></i>
      <p class="text-lg font-semibold">Carregando integrações...</p>
      <p class="text-sm mt-2 text-gray-500">Aguarde enquanto recuperamos as configurações disponíveis.</p>
    </div>`;

    try {
      const response = await fetch('/api/v1/auth/portalIntegrations.php', { cache: 'no-store' });
      const json = await response.json();
      if (!json.success) {
        throw new Error(json.error || 'Não foi possível carregar integrações');
      }

      const integrations = json.data.integrations || [];
      const filtered = filterIntegrationList(integrations, subtab);
      if (!filtered.length) {
        cardContainer.innerHTML = `<div class="col-span-full bg-white rounded-2xl border border-gray-200 shadow-sm p-8 text-center text-gray-600">
          <p class="text-lg font-semibold">Nenhuma integração encontrada</p>
          <p class="text-sm mt-2">Nenhuma integração corresponde a esta categoria.</p>
        </div>`;
        return;
      }

      cardContainer.innerHTML = '';
      filtered.forEach(integration => {
        const card = renderIntegrationCard(integration);
        cardContainer.appendChild(card);
      });
    } catch (error) {
      cardContainer.innerHTML = `<div class="col-span-full bg-white rounded-2xl border border-red-200 shadow-sm p-8 text-center text-red-600">
        <p class="text-lg font-semibold">Erro ao carregar integrações</p>
        <p class="text-sm mt-2">${error.message}</p>
      </div>`;
    }
  };

  document.querySelectorAll('.integrations-subtab-link').forEach(button => {
    button.addEventListener('click', async function (event) {
      event.preventDefault();
      const subtab = this.dataset.subtab;
      if (subtab) {
        await loadIntegrationsDashboard(subtab);
      }
    });
  });

  function getIntegrationSignupUrl(integration) {
    const knownSites = {
      google_analytics: 'https://analytics.google.com',
      hotjar: 'https://www.hotjar.com',
      jivochat: 'https://www.jivochat.com',
      leadster: 'https://leadster.com.br',
      tawk_to: 'https://www.tawk.to',
      whatsapp_chat: 'https://www.whatsapp.com/business',
      spimovel: 'https://www.spimovel.com.br',
      zap: 'https://www.zapimoveis.com.br',
      zap_plus: 'https://canalpro.grupozap.com/login',
      grupo_zap: 'https://www.zapimoveis.com.br',
      olx: 'https://www.olx.com.br',
      imovelweb: 'https://www.imovelweb.com.br',
      chavesnamao: 'https://www.chavesnamao.com.br',
      chaves_na_mao: 'https://www.chavesnamao.com.br',
      dfimoveis: 'https://www.dfimoveis.com.br',
      viva_real: 'https://www.vivareal.com.br',
      wimoveis: 'https://www.wimoveis.com.br',
      meta_anuncios: 'https://www.facebook.com/business/ads',
      lais_ai: 'https://lais.ai',
      helpdesk: 'https://www.helpdesk.com.br',
      lead_sync: 'https://leadsync.com.br',
      active_campaign: 'https://www.activecampaign.com',
      mailchimp: 'https://mailchimp.com',
      rd_station: 'https://rdstation.com',
      adopt: 'https://dash.goadopt.io/register',
      facebook_leads: 'https://www.facebook.com/business/ads/ad-objectives/lead-generation/',
      facebook_pixel: 'https://www.facebook.com/business/tools/meta-pixel',
      google_ads: 'https://ads.google.com',
      google_search_console: 'https://search.google.com/search-console',
      google_tag_manager: 'https://tagmanager.google.com',
      meta_anuncios: 'https://www.facebook.com/business/ads',
      photo_blocker: 'https://photoblocker.com',
      facebook_domain_verification: 'https://business.facebook.com/settings/digital_assets/domains',
    };

    if (knownSites[integration.portal]) {
      return knownSites[integration.portal];
    }

    const endpoint = String(integration.endpoint || '').trim();
    if (endpoint) {
      try {
        const parsed = new URL(endpoint);
        const hostname = parsed.hostname.replace(/^api\./i, '');
        return `${parsed.protocol}//${hostname}`;
      } catch (error) {
        return endpoint;
      }
    }

    return '#';
  }

  function renderIntegrationCard(integration) {
    const card = document.createElement('div');
    card.className = 'bg-white rounded-3xl border border-gray-200 shadow-sm p-6 flex flex-col justify-between w-full h-full';

    const isComingSoon = integration.status === 'coming_soon' || integration.comingSoon;
    const statusColor = isComingSoon ? 'bg-yellow-100 text-yellow-800' : integration.enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700';
    const actionLabel = isComingSoon ? 'Em breve' : integration.enabled ? 'Configurar' : 'Ativar';
    const statusLabel = isComingSoon ? 'Em breve' : (integration.enabled ? 'Ativa' : 'Inativa');
    const secondaryLabel = integration.enabled ? 'Desativar' : 'Mais detalhes';
    const signupUrl = getIntegrationSignupUrl(integration);

    card.innerHTML = `
      <div>
        <div class="flex items-center justify-between mb-4">
          <div>
            <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">Integração</p>
            <h3 class="text-2xl font-semibold text-slate-900 mt-2">${escapeHtml(integration.name)}</h3>
          </div>
          <div class="rounded-full px-3 py-2 text-xs font-semibold ${statusColor}">${statusLabel}</div>
        </div>
        <p class="text-sm text-slate-600 mb-5 min-h-[60px]">${escapeHtml(integration.description || 'Configure a conta do portal, endpoint e credenciais para conectar seu feed.')}</p>
        <div class="mb-4 text-center">
          <p class="text-sm text-slate-500">Não tem uma conta?<br><a href="${escapeHtml(signupUrl)}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:text-blue-800">Clique e cadastre-se</a></p>
        </div>
        <div class="flex items-center gap-3 text-slate-500 mb-4">
          <i class="${escapeHtml(integration.icon)} text-xl ${escapeHtml(integration.iconColor)}"></i>
          <span class="text-sm">${escapeHtml(integration.portal.toUpperCase())}</span>
        </div>
      </div>
      <div class="mt-4 flex flex-col gap-3">
        <button type="button" class="integration-action-btn w-full inline-flex items-center justify-center rounded-xl bg-blue-600 text-white py-3 px-4 text-sm font-semibold transition ${isComingSoon ? 'opacity-60 cursor-not-allowed pointer-events-none' : 'hover:bg-blue-700'}" ${isComingSoon ? 'disabled' : ''}>
          ${actionLabel}
        </button>
        ${integration.enabled ? `<button type="button" class="integration-disable-btn w-full inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 py-3 px-4 text-sm font-semibold hover:bg-slate-50 transition">${secondaryLabel}</button>` : ''}
      </div>
    `;

    const actionBtn = card.querySelector('.integration-action-btn');
    if (!isComingSoon) {
      actionBtn.addEventListener('click', () => showIntegrationModal(integration));
    }

    const disableBtn = card.querySelector('.integration-disable-btn');
    if (disableBtn) {
      disableBtn.addEventListener('click', async () => {
        await deactivateIntegration(integration.portal);
      });
    }

    return card;
  }

  function showIntegrationModal(integration) {
    currentIntegration = integration;
    const title = document.getElementById('integrationModalTitle');
    const description = document.getElementById('integrationModalDescription');
    const endpointInput = document.getElementById('integrationEndpointInput');
    const enabledCheckbox = document.getElementById('integrationEnabledCheckbox');
    const credentialContainer = document.getElementById('integrationCredentialFields');
    const portalName = document.getElementById('integrationPortalName');
    const iconEl = document.getElementById('integrationPortalIcon');
    const errorBox = document.getElementById('integrationModalError');

    if (!title || !endpointInput || !enabledCheckbox || !credentialContainer || !portalName || !iconEl) {
      return;
    }

    hideIntegrationLinks();
    title.textContent = integration.name;
    description.textContent = integration.description || 'Configure as credenciais para esta integração.';
    portalName.textContent = integration.name;
    iconEl.className = `${integration.icon} text-3xl ${integration.iconColor}`;
    enabledCheckbox.checked = integration.enabled;
    endpointInput.value = integration.endpoint || '';
    credentialContainer.innerHTML = '';
    errorBox.style.display = 'none';
    errorBox.textContent = '';

    const endpointContainer = document.getElementById('integrationEndpointContainer');
    if (endpointContainer) {
      const scriptOnlyPortals = ['google_analytics', 'hotjar', 'jivochat', 'leadster', 'tawk_to', 'whatsapp_chat'];
      const hasWidgetScriptField = Array.isArray(integration.credential_fields_meta) && integration.credential_fields_meta.some(field => field.name === 'widget_script');
      const hideEndpoint = scriptOnlyPortals.includes(integration.portal) || hasWidgetScriptField || integration.portal === 'olx' || integration.hide_endpoint === true;
      endpointContainer.style.display = hideEndpoint ? 'none' : 'block';
    }

    const credentialFields = getCredentialFieldsForIntegration(integration);
    if (credentialFields.length) {
      credentialFields.forEach(field => {
        const savedValue = integration.credentials?.[field.name];
        const isSensitive = /(password|token|secret|api_key)/i.test(field.name);
        const placeholderText = field.placeholder || (savedValue ? `${field.label} já configurado` : `Digite ${field.label}`);
        const value = (savedValue !== undefined && savedValue !== null && !isSensitive) ? savedValue : '';
        let fieldHtml = '';

        if (field.type === 'select') {
          const optionsHtml = (field.options || []).map(option => {
            const selected = savedValue && option === savedValue ? ' selected' : '';
            return `<option value="${escapeHtml(option)}"${selected}>${escapeHtml(option)}</option>`;
          }).join('');
          fieldHtml = `
            <div class="mb-4">
              <label class="block text-sm font-medium text-slate-700 mb-2" for="integrationField-${field.name}">${escapeHtml(field.label)}</label>
              <select id="integrationField-${field.name}" name="${escapeHtml(field.name)}" class="integration-credential-field block w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                ${optionsHtml}
              </select>
              ${field.description ? `<p class="mt-2 text-xs text-slate-500">${escapeHtml(field.description)}</p>` : ''}
            </div>
          `;
        } else if (field.type === 'textarea') {
          fieldHtml = `
            <div class="mb-4">
              <label class="block text-sm font-medium text-slate-700 mb-2" for="integrationField-${field.name}">${escapeHtml(field.label)}</label>
              <textarea id="integrationField-${field.name}" name="${escapeHtml(field.name)}" rows="4" class="integration-credential-field block w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="${escapeHtml(placeholderText)}">${escapeHtml(value)}</textarea>
              ${field.description ? `<p class="mt-2 text-xs text-slate-500">${escapeHtml(field.description)}</p>` : ''}
            </div>
          `;
        } else if (field.type === 'toggle') {
          const checked = !!parseInt(savedValue, 10) ? ' checked' : '';
          fieldHtml = `
            <div class="mb-4">
              <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div>
                  <p class="text-sm font-medium text-slate-700">${escapeHtml(field.label)}</p>
                  ${field.description ? `<p class="text-xs text-slate-500 mt-1">${escapeHtml(field.description)}</p>` : ''}
                </div>
                <label class="inline-flex items-center cursor-pointer">
                  <input id="integrationField-${field.name}" name="${escapeHtml(field.name)}" type="checkbox" class="integration-credential-field h-5 w-10 rounded-full border border-slate-300 bg-white checked:bg-blue-600"${checked} />
                  <span class="ml-3 text-sm text-slate-700">Sim</span>
                </label>
              </div>
            </div>
          `;
        } else {
          const inputType = field.type || 'text';
          fieldHtml = `
            <div class="mb-4">
              <label class="block text-sm font-medium text-slate-700 mb-2" for="integrationField-${field.name}">${escapeHtml(field.label)}</label>
              <input id="integrationField-${field.name}" name="${escapeHtml(field.name)}" type="${escapeHtml(inputType)}" value="${escapeHtml(value)}" class="integration-credential-field block w-full rounded-xl border border-slate-200 bg-white py-3 px-4 text-sm text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200" placeholder="${escapeHtml(placeholderText)}">
              ${field.description ? `<p class="mt-2 text-xs text-slate-500">${escapeHtml(field.description)}</p>` : ''}
            </div>
          `;
        }

        credentialContainer.insertAdjacentHTML('beforeend', fieldHtml);
      });
    }

    if (integration.enabled && ['olx', 'facebook_leads'].includes(integration.portal)) {
      showIntegrationLinks(integration.portal);
    } else {
      hideIntegrationLinks();
    }

    if (bootstrapModal) {
      bootstrapModal.show();
    } else {
      modalEl.style.display = 'flex';
    }
  }

  function getTenantId() {
    return window.TENANT_ID_SERVER || window.SUBDOMAIN_TENANT_ID || null;
  }

  function buildIntegrationLinks(portal) {
    const tenantId = getTenantId();
    const origin = window.location.origin;
    if (!tenantId) {
      return { feedUrl: '', leadUrl: '', webhookUrl: '' };
    }
    // Não incluir tenant_id na URL; a autenticação deve ser feita por X-API-Key
    const feedUrl = `${origin}/api/v1/public/portalFeed.php?portal=${encodeURIComponent(portal)}&format=xml`;
    const leadUrl = `${origin}/api/v1/public/olxLeadReceiver.php`;
    const webhookUrl = `${origin}/api/v1/public/facebookLeadReceiver.php`;
    return { feedUrl, leadUrl, webhookUrl };
  }

  function showIntegrationLinks(portal) {
    const container = document.getElementById('integrationLinkResults');
    const feedContainer = document.getElementById('integrationFeedLinkContainer');
    const leadContainer = document.getElementById('integrationLeadLinkContainer');
    const webhookContainer = document.getElementById('integrationWebhookLinkContainer');
    const feedInput = document.getElementById('integrationFeedLink');
    const leadInput = document.getElementById('integrationLeadLink');
    const webhookInput = document.getElementById('integrationWebhookLink');
    const copyFeedBtn = document.getElementById('integrationCopyFeedLink');
    const copyLeadBtn = document.getElementById('integrationCopyLeadLink');
    const copyWebhookBtn = document.getElementById('integrationCopyWebhookLink');
    const linkTitle = document.getElementById('integrationLinkTitle');
    const linkSubtitle = document.getElementById('integrationLinkSubtitle');
    if (!container || !feedContainer || !leadContainer || !webhookContainer || !linkTitle || !linkSubtitle) return;

    const { feedUrl, leadUrl, webhookUrl } = buildIntegrationLinks(portal);
    if (portal === 'facebook_leads') {
      feedContainer.classList.add('hidden');
      leadContainer.classList.add('hidden');
      webhookContainer.classList.remove('hidden');
      linkTitle.textContent = 'Webhook do Facebook Leads';
      linkSubtitle.textContent = 'Use esta URL como callback do webhook no painel do Facebook.';
      if (webhookInput) {
        webhookInput.value = webhookUrl;
      }
    } else {
      feedContainer.classList.remove('hidden');
      leadContainer.classList.remove('hidden');
      webhookContainer.classList.add('hidden');
      linkTitle.textContent = 'Links gerados para OLX';
      linkSubtitle.textContent = 'Copie estes links para colar no painel do OLX.';
      if (feedInput) {
        feedInput.value = feedUrl;
      }
      if (leadInput) {
        leadInput.value = leadUrl;
      }
    }

    container.classList.remove('hidden');

    if (copyFeedBtn) {
      copyFeedBtn.onclick = () => {
        if (feedInput) {
          feedInput.select();
          document.execCommand('copy');
          copyFeedBtn.textContent = 'Copiado!';
          setTimeout(() => { copyFeedBtn.textContent = 'Copiar'; }, 1600);
        }
      };
    }

    if (copyLeadBtn) {
      copyLeadBtn.onclick = () => {
        if (leadInput) {
          leadInput.select();
          document.execCommand('copy');
          copyLeadBtn.textContent = 'Copiado!';
          setTimeout(() => { copyLeadBtn.textContent = 'Copiar'; }, 1600);
        }
      };
    }

    if (copyWebhookBtn) {
      copyWebhookBtn.onclick = () => {
        if (webhookInput) {
          webhookInput.select();
          document.execCommand('copy');
          copyWebhookBtn.textContent = 'Copiado!';
          setTimeout(() => { copyWebhookBtn.textContent = 'Copiar'; }, 1600);
        }
      };
    }
  }

  function hideIntegrationLinks() {
    const container = document.getElementById('integrationLinkResults');
    if (container) {
      container.classList.add('hidden');
    }
  }

  async function deactivateIntegration(portal) {
    try {
      const response = await fetch('/api/v1/auth/portalIntegrations.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ portal: portal, enabled: 0, endpoint: '', credentials: {} })
      });
      const json = await response.json();
      if (!json.success) {
        throw new Error(json.error || 'Falha ao desativar integração');
      }
      await loadIntegrationsDashboard();
    } catch (error) {
      alert('Erro: ' + error.message);
    }
  }

  function getCredentialFieldsForIntegration(integration) {
    if (integration.credential_fields_meta && Array.isArray(integration.credential_fields_meta) && integration.credential_fields_meta.length) {
      return integration.credential_fields_meta.map(field => ({
        name: field.name,
        label: field.label || humanizeFieldName(field.name),
        type: field.type || 'text',
        placeholder: field.placeholder || '',
        description: field.description || '',
        options: field.options || [],
      }));
    }

    return (integration.credential_fields || []).map(fieldName => {
      const normalized = fieldName.toLowerCase();
      let type = 'text';
      if (normalized.includes('password') || normalized.includes('token') || normalized.includes('secret') || normalized.includes('api_key')) {
        type = 'password';
      } else if (normalized.includes('phone')) {
        type = 'tel';
      }
      return {
        name: fieldName,
        label: humanizeFieldName(fieldName),
        type,
        placeholder: `Digite ${humanizeFieldName(fieldName)}`,
        description: '',
        options: [],
      };
    });
  }

  function humanizeFieldName(name) {
    return name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }

  function escapeHtml(value) {
    const stringValue = value === undefined || value === null ? '' : String(value);
    return stringValue.replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      }[char];
    });
  }

  const closeButtons = document.querySelectorAll('[data-close-integration-modal]');
  closeButtons.forEach(button => {
    button.addEventListener('click', function () {
      if (bootstrapModal) {
        bootstrapModal.hide();
      } else if (modalEl) {
        modalEl.style.display = 'none';
      }
    });
  });
});
