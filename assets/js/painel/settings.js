//assets/js/settings.js

/**
 * Converte cor hexadecimal para RGB
 * @param {string} hex Cor em formato hexadecimal (ex: #ff0000)
 * @returns {string} Valores RGB separados por vírgula (ex: "255, 0, 0")
 */
function hexToRgb(hex) {
    // Valida se hex não é nulo ou vazio
    if (!hex || typeof hex !== 'string' || hex.trim() === '') {
        return '0, 0, 0'; // Retorna preto por padrão
    }

    // Remove o # se existir
    hex = hex.replace('#', '').trim();

    // Valida formato hexadecimal (6 caracteres)
    if (!/^[0-9A-Fa-f]{6}$/.test(hex)) {
        return '0, 0, 0'; // Retorna preto por padrão
    }

    // Converte para RGB
    const r = parseInt(hex.substring(0, 2), 16);
    const g = parseInt(hex.substring(2, 4), 16);
    const b = parseInt(hex.substring(4, 6), 16);

    return `${r}, ${g}, ${b}`;
}

/**
 * Função global para atualizar preview da logo
 * Busca elementos do DOM dinamicamente para funcionar após AJAX loading
 */
function updateLogoPreview() {
    const logoInput = document.getElementById('company_logo');
    const logoPreview = document.getElementById('logo_preview');

    if (!logoInput || !logoPreview) return;

    let path = logoInput.value;

    if (path && path.trim()) {
        // Garante que seja um caminho absoluto (começa com /)
        if (!path.startsWith('/') && !path.startsWith('http')) {
            path = '/' + path;
        }

        // Adiciona cache busting para forçar reload
        const fullPath = `${path}?_t=${Date.now()}`;

        // Cria elemento img dinamicamente para evitar problemas com escape de caracteres
        const img = document.createElement('img');
        img.src = fullPath;
        img.alt = 'Logo da Empresa';
        img.className = 'object-contain w-full h-full';

        // Handler de erro via event listener (mais confiável)
        img.onerror = function () {
            console.error('[updateLogoPreview] ❌ Erro ao carregar imagem:', fullPath);
            logoPreview.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i>';
        };

        // Handler de sucesso para confirmar que carregou
        img.onload = function () {};

        // Limpa e adiciona a imagem
        logoPreview.innerHTML = '';
        logoPreview.appendChild(img);
    } else {
        logoPreview.innerHTML = '<i class="fas fa-building text-2xl"></i>';
    }
}

/**
 * Inicializa os eventos e carregamento das configurações da empresa no painel.
 * Sincroniza color pickers, eventos de submit/cancelar e upload de logo.
 */
/**
 * Centraliza a detecção de tenant_id usada no painel de configurações.
 * Usa query string quando presente, caso contrário usa a variável global ou padrão 1.
 */
function getPanelSettingsTenantId() {
    if (typeof window.getCompanySettingsTenantId === 'function') {
        const tenantId = window.getCompanySettingsTenantId();
        if (tenantId && parseInt(tenantId, 10) > 0) {
            return parseInt(tenantId, 10);
        }
    }

    const urlParams = new URLSearchParams(window.location.search);
    const tenantIdFromUrl = urlParams.get('tenant_id');
    if (tenantIdFromUrl && parseInt(tenantIdFromUrl, 10) > 0) {
        return parseInt(tenantIdFromUrl, 10);
    }

    return window.SUBDOMAIN_TENANT_ID || 1;
}

/**
 * Atualiza as informações de título e descrição do banner
 */

async function updateBannerInfo(id, data) {
    try {
        const formData = new FormData();
        formData.append('id', id);
        formData.append('tenant_id', getPanelSettingsTenantId());
        if (data.title !== undefined) formData.append('title', data.title);
        if (data.description !== undefined) formData.append('description', data.description);

        const result = await apiCall('/api/v1/banners/updateBanner.php', {
            method: 'POST',
            body: formData,
        });

        if (!result.success) {
            window.utils.mostrarErro(result.error || 'Erro ao salvar informações do banner');
            return;
        }

        // Atualiza a interface se necessário
        // Notifica invalidação de cache para o site público
        if (typeof CacheInvalidator !== 'undefined') {
            CacheInvalidator.notify('banners', { banner_id: id, action: 'updated' });
        }
    } catch (error) {
        console.error('Erro ao atualizar banner:', error);
        window.utils.mostrarErro(
            'Erro ao salvar informações do banner. Verifique o console para mais detalhes.'
        );
    }
}

async function initializeSettings() {
    // Inicializa sem flag de proteção, pois painel.php já protege contra múltiplos carregamentos
    // com a flag configsLoaded. Permitir re-inicialização é essencial para AJAX dynamic loading.

    // Inicializa o modal e botão de upload de banner
    const btnAdd = document.getElementById('btn_adicionar_banner');
    const modal = document.getElementById('modal_banner_upload');
    const modalFile = document.getElementById('modal_banner_file');
    const modalSend = document.getElementById('modal_banner_send');
    const modalCancel = document.getElementById('modal_banner_cancel');
    const modalFeedback = document.getElementById('modal_banner_feedback');

    if (btnAdd && modal) {
        btnAdd.addEventListener('click', function () {
            modal.style.display = 'flex';
            modalFile.value = '';
            modalFeedback.textContent = '';
        });

        modalCancel.addEventListener('click', function () {
            modal.style.display = 'none';
        });

        modalSend.addEventListener('click', async function () {
            if (!modalFile.files[0]) {
                modalFeedback.textContent = 'Selecione uma imagem.';
                return;
            }
            modalFeedback.textContent = 'Enviando...';
            const formData = new FormData();
            formData.append('banner', modalFile.files[0]);
            formData.append('title', document.getElementById('modal_banner_title')?.value || '');
            formData.append(
                'description',
                document.getElementById('modal_banner_description')?.value || ''
            );
            try {
                const result = await apiCall('/api/v1/banners/uploadBannerImage.php', {
                    method: 'POST',
                    body: formData,
                });
                if (result.success) {
                    modalFeedback.textContent = 'Imagem enviada!';
                    modal.style.display = 'none';
                    // Notifica invalidação de cache para o site público
                    if (typeof CacheInvalidator !== 'undefined') {
                        CacheInvalidator.notify('banners', { action: 'created' });
                    }
                    carregarBannerImages();
                } else {
                    modalFeedback.textContent = result.error || 'Erro ao enviar.';
                }
            } catch (e) {
                modalFeedback.textContent = 'Erro de comunicação.';
            }
        });

        // Fecha modal ao clicar fora
        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    }

    // Sincroniza color picker e campo texto para cada cor
    [1, 2, 3].forEach(function (i) {
        var preview = document.getElementById('color_preview_' + i);
        var picker = document.getElementById('color_picker_' + i);
        var input = document.getElementById('company_color' + i);
        const corVar = i === 1 ? '--cor-primaria' : i === 2 ? '--cor-secundaria' : '--cor-destaque';
        const corVarRgb =
            i === 1
                ? '--cor-primaria-rgb'
                : i === 2
                  ? '--cor-secundaria-rgb'
                  : '--cor-destaque-rgb';
        if (preview && picker && input) {
            // Atualiza preview e campo texto ao mudar picker
            picker.addEventListener('input', function () {
                const color = picker.value;
                input.value = color;
                preview.style.backgroundColor = color;
                // Atualiza as variáveis CSS em tempo real
                document.documentElement.style.setProperty(corVar, color);
                document.documentElement.style.setProperty(corVarRgb, hexToRgb(color));
                var event = new Event('input', { bubbles: true });
                input.dispatchEvent(event);
            });
            // Atualiza picker e preview ao mudar campo texto (sem validação estrita durante digitação)
            input.addEventListener('input', function () {
                const color = (input.value || '').trim();
                if (color.length > 0) {
                    // Tenta atribuir ao picker sem validação (HTML5 rejeita automaticamente se inválido)
                    try {
                        picker.value = color;
                        preview.style.backgroundColor = color;
                    } catch (e) {
                        // Ignora erros silenciosamente durante digitação
                    }
                }
            });
            // Valida e ajusta quando termina de digitar
            input.addEventListener('change', function () {
                const color = (input.value || '').trim();
                let normalized = color;
                if (normalized && !normalized.startsWith('#')) {
                    normalized = '#' + normalized;
                }
                // Valida formato: #rrggbb
                if (normalized && /^#[0-9A-Fa-f]{6}$/.test(normalized)) {
                    input.value = normalized;
                    picker.value = normalized;
                    preview.style.backgroundColor = normalized;
                } else if (normalized && normalized !== '#') {
                    console.warn('Cor inválida, ajustando para padrão');
                    input.value = '';
                    picker.value = '#000000';
                    preview.style.backgroundColor = '#000000';
                }
            });
            // Inicializa preview e picker com valores padrão ou carregados
            if (input.value) {
                const color = (input.value || '').trim();
                let normalized = color;
                if (normalized && !normalized.startsWith('#')) {
                    normalized = '#' + normalized;
                }
                // Valida formato: #rrggbb
                if (normalized && /^#[0-9A-Fa-f]{6}$/.test(normalized)) {
                    picker.value = normalized;
                    preview.style.backgroundColor = normalized;
                }
            }
        }
    });

    // Cor da fonte principal removida — nada a sincronizar aqui.

    const form = document.getElementById('company_settings_form');
    if (form) {
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            await salvarCompanySettings();
        });

        // Botão Cancelar restaura as configurações do backend
        const btnCancelar = document.getElementById('cancelar_config_btn');
        if (btnCancelar) {
            btnCancelar.addEventListener('click', async function () {
                if (
                    confirm(
                        'Deseja realmente cancelar as alterações? Todas as mudanças não salvas serão perdidas.'
                    )
                ) {
                    await carregarCompanySettings();
                    // Mensagem de feedback
                    const message = document.createElement('div');
                    message.className =
                        'fixed bottom-4 right-4 bg-blue-50 text-blue-700 px-6 py-3 rounded-lg shadow-lg';
                    message.textContent =
                        'As alterações foram canceladas e as configurações foram restauradas.';
                    document.body.appendChild(message);
                    setTimeout(() => {
                        message.remove();
                    }, 3000);
                }
            });
        }
    }

    // Inicializa o upload de logo
    const logoInput = document.getElementById('company_logo');
    const logoPreview = document.getElementById('logo_preview');
    const changeLogoBtn = document.getElementById('change_logo_btn');
    const logoUploadInput = document.getElementById('logo_upload_input');

    if (logoInput && logoPreview && changeLogoBtn && logoUploadInput) {
        // Adiciona listeners para o preview da logo
        logoInput.addEventListener('input', updateLogoPreview);

        // NÃO CHAMA updateLogoPreview aqui - será chamado APÓS carregarCompanySettings()

        changeLogoBtn.addEventListener('click', () => {
            logoUploadInput.click();
        });

        // Evento de upload de logo (envia para o backend e atualiza preview)
        logoUploadInput.addEventListener('change', async (event) => {
            const file = event.target.files[0];
            const infoEl = document.getElementById('logo_upload_info');
            if (!file) return;

            if (infoEl) infoEl.textContent = 'Envie apenas imagens PNG de até 5MB. Aguarde...';

            if (file.type !== 'image/png' || !file.name.toLowerCase().endsWith('.png')) {
                if (infoEl)
                    infoEl.textContent = 'Erro: Apenas arquivos .png são aceitos para a logo.';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                if (infoEl)
                    infoEl.textContent = 'Erro: o arquivo ultrapassa 5MB. Escolha um PNG menor.';
                return;
            }

            // Mostra preview local imediato enquanto o arquivo é enviado
            const reader = new FileReader();
            reader.onload = function (e) {
                logoPreview.innerHTML = '';
                const img = document.createElement('img');
                img.src = e.target.result;
                img.alt = 'Preview temporário da logo';
                img.className = 'object-contain w-full h-full';
                logoPreview.appendChild(img);
            };
            reader.readAsDataURL(file);

            const formData = new FormData();
            formData.append('logo', file);

            // Usa detecção centralizada de tenant_id
            formData.append('tenant_id', getPanelSettingsTenantId());

            try {
                const result = await apiCall('/api/v1/utils/uploadLogo.php', {
                    method: 'POST',
                    body: formData,
                });

                if (result.success) {
                    const responseData =
                        result.data && result.data.data ? result.data.data : result.data || {};
                    const filePath = responseData.filePath || '';
                    logoInput.value = filePath;
                    updateLogoPreview();
                    if (infoEl) infoEl.textContent = 'Logo enviada com sucesso!';
                    // Notifica outras abas/páginas para atualizar o logo
                    if ('BroadcastChannel' in window) {
                        const canal = new BroadcastChannel('configuracoes_empresa');
                        canal.postMessage({
                            tipo: 'atualizar_logo',
                            company_logo: filePath,
                        });
                        canal.close();
                    }
                } else {
                    if (infoEl)
                        infoEl.textContent =
                            'Erro ao enviar a logo: ' +
                            (result.error || 'Falha na resposta do servidor');
                }
            } catch (error) {
                console.error('Erro no upload:', error);
                if (infoEl) infoEl.textContent = 'Ocorreu um erro de comunicação ao enviar a logo.';
            }
        });
    }

    // Carrega sempre os dados atuais das configurações e banners (uma única vez)
    await carregarCompanySettings();
    await carregarBannerImages();

    // APÓS carregar os dados, atualiza o preview da logo
    setTimeout(() => {
        if (typeof updateLogoPreview === 'function') {
            updateLogoPreview();
        }
    }, 100);
}

/**
 * Busca e exibe as imagens de banner cadastradas, permitindo remoção.
 */
async function carregarBannerImages() {
    const list = document.getElementById('banner_thumbnails');
    if (!list) return;
    list.innerHTML = '<div class="text-gray-400">Carregando...</div>';
    try {
        // Usa detecção corrigida de tenant_id (URL param > window vars > default)
        const urlParams = new URLSearchParams(window.location.search);
        const url = new URL('/api/v1/banners/getBanners.php', window.location.origin);
        url.searchParams.append('tenant_id', getPanelSettingsTenantId());
        const result = await apiCall(url.toString());
        const banners = result.success
            ? Array.isArray(result.data)
                ? result.data
                : Array.isArray(result.data?.data)
                  ? result.data.data
                  : []
            : [];
        if (!Array.isArray(banners) || banners.length === 0) {
            list.innerHTML = '<div class="text-gray-400">Nenhuma imagem cadastrada.</div>';
            return;
        }
        list.innerHTML = '';
        banners.slice(0, 5).forEach((banner) => {
            const imageUrl = banner.image_url || banner.image_path;
            // Garante que a URL comece com /
            const fullImageUrl = imageUrl && !imageUrl.startsWith('/') ? '/' + imageUrl : imageUrl;

            const card = document.createElement('div');
            card.className = 'flex flex-col gap-2 w-full border-b pb-3 mb-3';
            card.innerHTML = `
        <div class="relative" style="width: 100px;">
          <div class="rounded-lg border border-gray-200 flex items-center justify-center overflow-hidden bg-gray-100" style="width: 100px; height: 60px;">
            <!-- Imagem será adicionada via DOM para evitar problemas de escape -->
          </div>
          <button type="button" class="absolute top-0 right-0 bg-white rounded-full p-0.5 shadow banner-remove" style="border:none; width: 20px; height: 20px;" title="Remover" data-id="${banner.id}">
            <i class="fa-solid fa-times text-red-500 text-xs"></i>
          </button>
        </div>
        <div class="flex-grow" style="max-width: 300px;">
          <input type="text" class="border rounded text-xs banner-title" 
            placeholder="Título" 
            value="${banner.title || ''}"
            data-id="${banner.id}"
            style="font-size: 11px; padding: 2px 4px; width: 100%;">
          <textarea class="border rounded text-xs banner-description" 
            placeholder="Descrição" 
            rows="1"
            data-id="${banner.id}"
            style="font-size: 11px; resize: none; padding: 2px 4px; width: 100%; margin-top: 2px;">${banner.description || ''}</textarea>
        </div>
      `;

            // Criar e adicionar imagem dinamicamente (mais confiável)
            const imgContainer = card.querySelector('[style*="width: 100px"]').querySelector('div');
            const img = document.createElement('img');
            img.src = fullImageUrl + '?_t=' + Date.now(); // Cache busting
            img.alt = banner.title || 'Banner';
            img.className = 'w-full h-full object-cover';
            img.style.display = 'block';

            img.onerror = function () {
                console.error('[carregarBannerImages] ❌ Erro ao carregar imagem:', fullImageUrl);
                imgContainer.innerHTML = '<i class="fas fa-image text-gray-300 text-sm"></i>';
            };

            imgContainer.appendChild(img);

            // Adiciona evento para salvar título
            const titleInput = card.querySelector('.banner-title');
            titleInput.addEventListener('blur', async (e) => {
                const id = e.target.dataset.id;
                const title = e.target.value;
                await updateBannerInfo(id, { title });
            });

            // Adiciona evento para salvar descrição
            const descInput = card.querySelector('.banner-description');
            descInput.addEventListener('blur', async (e) => {
                const id = e.target.dataset.id;
                const description = e.target.value;
                await updateBannerInfo(id, { description });
            });

            card.querySelector('button[data-id]').addEventListener('click', async (e) => {
                if (!confirm('Remover esta imagem do banner?')) return;
                const btn = e.currentTarget;
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                btn.style.pointerEvents = 'none';

                try {
                    const formData = new FormData();
                    formData.append('id', banner.id);
                    formData.append('tenant_id', getPanelSettingsTenantId());
                    const result = await apiCall('/api/v1/banners/deleteBanner.php', {
                        method: 'POST',
                        body: formData,
                    });

                    if (result.success) {
                        const thumbs = document.getElementById('banner_thumbnails');
                        if (thumbs) {
                            card.style.opacity = '0';
                            setTimeout(() => {
                                card.remove();
                                if (thumbs.children.length === 0) {
                                    thumbs.innerHTML =
                                        '<div class="text-gray-400">Nenhuma imagem cadastrada.</div>';
                                }
                            }, 300);
                            // Notifica invalidação de cache para o site público
                            if (typeof CacheInvalidator !== 'undefined') {
                                CacheInvalidator.notify('banners', {
                                    banner_id: banner.id,
                                    action: 'deleted',
                                });
                            }
                        }
                    } else {
                        btn.innerHTML = originalHtml;
                        btn.style.pointerEvents = '';
                        window.utils.mostrarErro(
                            'Erro ao remover imagem: ' +
                                (result.error || 'Falha ao remover. Tente novamente.')
                        );
                    }
                } catch (error) {
                    btn.innerHTML = originalHtml;
                    btn.style.pointerEvents = '';
                    window.utils.mostrarErro(
                        'Erro ao remover imagem: Falha na comunicação com o servidor.'
                    );
                }
            });
            list.appendChild(card);
        });
    } catch (err) {
        list.innerHTML = '<div class="text-red-500">Erro ao carregar imagens.</div>';
    }
}

// Garantir que initializeSettings seja chamado quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initializeSettings === 'function') {
        initializeSettings();
    }
});

async function carregarCompanySettings() {
    try {
        // Remove listener temporariamente para evitar múltiplas chamadas a updateLogoPreview
        const logoInput = document.getElementById('company_logo');
        if (logoInput) {
            logoInput.removeEventListener('input', updateLogoPreview);
        }

        // Carrega settings via loader centralizado
        const json = await window.getCompanySettings();

        if (!json || !json.success) {
            console.warn(
                'Falha ao carregar configurações da empresa:',
                json ? json.error : 'getCompanySettings falhou'
            );
            return;
        }

        const result = { success: true, data: json };

        const settingsData = json && json.data ? json.data : json;

        if (settingsData && typeof settingsData === 'object') {
            // Dados carregados

            for (const [key, value] of Object.entries(json.data)) {
                const el = document.getElementById(key);
                if (el) el.value = value;
                // Atualiza logo do painel ao carregar configs
                if (key === 'company_logo') {
                    // BroadcastChannel para sincronizar outras abas
                    if ('BroadcastChannel' in window) {
                        const canal = new BroadcastChannel('configuracoes_empresa');
                        canal.postMessage({ tipo: 'atualizar_logo', company_logo: value });
                        canal.close();
                    }
                }
                // Sincroniza visualmente os color pickers e previews e atualiza variáveis RGB
                if (key.startsWith('company_color')) {
                    const idx = key.replace('company_color', '');
                    const picker = document.getElementById('color_picker_' + idx);
                    const preview = document.getElementById('color_preview_' + idx);
                    if (picker) picker.value = value;
                    if (preview) preview.style.backgroundColor = value;
                    // Atualiza as variáveis RGB
                    const corVarRgb =
                        idx === '1'
                            ? '--cor-primaria-rgb'
                            : idx === '2'
                              ? '--cor-secundaria-rgb'
                              : '--cor-destaque-rgb';
                    document.documentElement.style.setProperty(corVarRgb, hexToRgb(value));
                }
            }

            // Campo company_font_color foi removido da UI — nada a preencher.
            // Adiciona campos de e-mail e notificações (apenas checkboxes pois os inputs já são preenchidos no loop anterior)
            const checkboxes = [
                'notify_new_lead',
                'notify_new_property',
                'notify_property_status',
                'notify_contact_form',
                'notify_agendamento',
            ];

            checkboxes.forEach((checkboxId) => {
                if (checkboxId in json.data) {
                    const el = document.getElementById(checkboxId);
                    if (el) {
                        el.checked = json.data[checkboxId] == 1;
                    }
                }
            });
        }

        // Restaura listener após carregar dados
        if (logoInput) {
            logoInput.addEventListener('input', updateLogoPreview);
        }
    } catch (err) {
        console.error('Erro ao carregar configurações:', err);
    }
}

/**
 * Controla o estado visual do botão de salvar
 */
function setSaveButtonState(isSaving) {
    const btn = document.getElementById('save_settings_button');
    if (!btn) return;

    if (isSaving) {
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'not-allowed';
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Salvando...';
    } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.innerHTML = '<i class="fa-regular fa-floppy-disk"></i> Salvar Alterações';
    }
}

/**
 * Coleta os dados do formulário e envia para o backend para salvar as configurações da empresa.
 */
async function salvarCompanySettings() {
    setSaveButtonState(true);

    const fields = [
        'company_name',
        'company_email',
        'company_email2',
        'company_phone',
        'company_whatsapp',
        'company_address',
        'company_weekday_hours',
        'company_saturday_hours',
        'company_description',
        'company_facebook',
        'company_instagram',
        'company_linkedin',
        'company_youtube',
        'company_logo',
        'company_color1',
        'company_color2',
        'company_color3',
        'company_font',
        'email_notifications',
        'email_leads',
    ];
    const required = [
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'company_description',
    ];
    const data = {};
    let missing = [];
    fields.forEach((id) => {
        // Para campos com hífen, procura com o hífen mas salva com underscore
        const searchId = id;
        const el = document.getElementById(searchId);
        if (el) {
            // Converte todos os hífens para underscore ao salvar
            const dataId = id.replace(/-/g, '_');
            data[dataId] = el.value;
        }
        if (required.includes(id) && (!el || !el.value.trim())) {
            missing.push(id);
        }
    });
    // Adiciona checkboxes de notificação
    data['notify_new_lead'] = document.getElementById('notify_new_lead')?.checked ? 1 : 0;
    data['notify_new_property'] = document.getElementById('notify_new_property')?.checked ? 1 : 0;
    data['notify_property_status'] = document.getElementById('notify_property_status')?.checked
        ? 1
        : 0;
    data['notify_contact_form'] = document.getElementById('notify_contact_form')?.checked ? 1 : 0;
    data['notify_agendamento'] = document.getElementById('notify_agendamento')?.checked ? 1 : 0;
    if (missing.length > 0) {
        const labels = {
            company_name: 'Nome da Empresa',
            company_email: 'E-mail Principal',
            company_phone: 'Telefone',
            company_address: 'Endereço',
            company_description: 'Descrição da Empresa',
        };
        window.utils.mostrarErro(
            'Preencha os campos obrigatórios:\n' + missing.map((id) => '- ' + labels[id]).join('\n')
        );
        setSaveButtonState(false);
        return;
    }
    // Campo company_font_color removido da UI — não adicionamos mais esse dado.
    try {
        const result = await apiCall('/api/v1/settings/saveCompanySettings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...data, tenant_id: getPanelSettingsTenantId() }),
        });
        if (result.success) {
            const json = result.data;
            const d = json && json.data ? json.data : json || data;

            // Mostra mensagem de sucesso com estilo Bootstrap
            const alert = document.createElement('div');
            alert.className =
                'alert alert-success alert-dismissible fade show position-fixed bottom-0 end-0 m-3';
            alert.style.zIndex = '9999';
            alert.innerHTML = `
          <strong>Sucesso!</strong> Configurações salvas com sucesso.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      `;
            document.body.appendChild(alert);

            // Remove a mensagem automaticamente após 3 segundos
            setTimeout(() => {
                alert.remove();
            }, 3000);

            // Atualiza as cores e fonte
            if (d.company_font) {
                document.documentElement.style.setProperty(
                    '--fonte-principal',
                    `'${d.company_font}', sans-serif`
                );
                if (!['Open Sans', 'Arial', 'sans-serif'].includes(d.company_font)) {
                    document.querySelectorAll('link[data-dynamic-font]').forEach((l) => l.remove());
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.setAttribute('data-dynamic-font', '1');
                    link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(d.company_font)}:wght@300;400;500;600;700&display=swap`;
                    document.head.appendChild(link);
                }
            }

            // Atualiza as cores (hexadecimal e RGB) no documento atual
            if (d.company_color1) {
                document.documentElement.style.setProperty('--cor-primaria', d.company_color1);
                document.documentElement.style.setProperty(
                    '--cor-primaria-rgb',
                    hexToRgb(d.company_color1)
                );
            }
            if (d.company_color2) {
                document.documentElement.style.setProperty('--cor-secundaria', d.company_color2);
                document.documentElement.style.setProperty(
                    '--cor-secundaria-rgb',
                    hexToRgb(d.company_color2)
                );
            }
            if (d.company_color3) {
                document.documentElement.style.setProperty('--cor-destaque', d.company_color3);
                document.documentElement.style.setProperty(
                    '--cor-destaque-rgb',
                    hexToRgb(d.company_color3)
                );
            }

            // Campo company_font_color removido da UI — nada a aplicar.

            // Notifica outras abas sobre mudanças de cores e fonte
            if ('BroadcastChannel' in window) {
                const canal = new BroadcastChannel('configuracoes_empresa');
                canal.postMessage({
                    tipo: 'atualizar_cores',
                    company_color1: d.company_color1,
                    company_color2: d.company_color2,
                    company_color3: d.company_color3,
                    company_font: d.company_font,
                });
                canal.close();
            }

            // Recarrega os valores do formulário após salvar, imediatamente
            await carregarCompanySettings();
        } else {
            window.utils.mostrarErro(json.message || 'Erro ao salvar configurações.');
        }
    } catch (err) {
        window.utils.mostrarErro('Erro ao salvar configurações.');
    } finally {
        setSaveButtonState(false);
    }
}
