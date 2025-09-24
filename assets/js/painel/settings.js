//assets/js/settings.js

/**
 * Converte cor hexadecimal para RGB
 * @param {string} hex Cor em formato hexadecimal (ex: #ff0000)
 * @returns {string} Valores RGB separados por vírgula (ex: "255, 0, 0")
 */
function hexToRgb(hex) {
  // Remove o # se existir
  hex = hex.replace('#', '');
  
  // Converte para RGB
  const r = parseInt(hex.substring(0, 2), 16);
  const g = parseInt(hex.substring(2, 4), 16);
  const b = parseInt(hex.substring(4, 6), 16);
  
  return `${r}, ${g}, ${b}`;
}

/**
 * Inicializa os eventos e carregamento das configurações da empresa no painel.
 * Sincroniza color pickers, eventos de submit/cancelar e upload de logo.
 */
/**
 * Atualiza as informações de título e descrição do banner
 */

async function updateBannerInfo(id, data) {
  try {
    console.log('Atualizando banner:', { id, data }); // Debug

    const formData = new FormData();
    formData.append('id', id);
    if (data.title !== undefined) formData.append('title', data.title);
    if (data.description !== undefined) formData.append('description', data.description);

    const res = await fetch('api/updateBanner.php', {
      method: 'POST',
      body: formData
    });

    const text = await res.text(); // Primeiro pegamos o texto da resposta
    console.log('Resposta do servidor:', text); // Debug

    let result;
    try {
      result = JSON.parse(text);
    } catch (e) {
      console.error('Erro ao parsear resposta JSON:', e);
      window.utils.mostrarErro('Erro ao processar resposta do servidor');
      return;
    }

    if (!result.success) {
      window.utils.mostrarErro(result.error || 'Erro ao salvar informações do banner');
    } else {
      // Atualiza a interface se necessário
      console.log('Banner atualizado com sucesso');
    }
  } catch (error) {
    console.error('Erro ao atualizar banner:', error);
    window.utils.mostrarErro('Erro ao salvar informações do banner. Verifique o console para mais detalhes.');
  }
}

async function initializeSettings() {
  // Inicializa as cores através do ColorManager
  await ColorManager.initialize();
  
  // Carrega as configurações e banners
  await carregarCompanySettings();
  await carregarBannerImages();

  // Inicializa o modal e botão de upload de banner
  const btnAdd = document.getElementById('btn_adicionar_banner');
  const modal = document.getElementById('modal_banner_upload');
  const modalFile = document.getElementById('modal_banner_file');
  const modalSend = document.getElementById('modal_banner_send');
  const modalCancel = document.getElementById('modal_banner_cancel');
  const modalFeedback = document.getElementById('modal_banner_feedback');

  if (btnAdd && modal) {
    btnAdd.addEventListener('click', function() {
      modal.style.display = 'flex';
      modalFile.value = '';
      modalFeedback.textContent = '';
    });
    
    modalCancel.addEventListener('click', function() {
      modal.style.display = 'none';
    });
    
    modalSend.addEventListener('click', async function() {
      if (!modalFile.files[0]) {
        modalFeedback.textContent = 'Selecione uma imagem.';
        return;
      }
      modalFeedback.textContent = 'Enviando...';
      const formData = new FormData();
      formData.append('banner', modalFile.files[0]);
      formData.append('title', document.getElementById('modal_banner_title')?.value || '');
      formData.append('description', document.getElementById('modal_banner_description')?.value || '');
      try {
        const res = await fetch('api/uploadBannerImage.php', { method: 'POST', body: formData });
        const result = await res.json();
        if (result.success) {
          modalFeedback.textContent = 'Imagem enviada!';
          modal.style.display = 'none';
          carregarBannerImages();
        } else {
          modalFeedback.textContent = result.error || 'Erro ao enviar.';
        }
      } catch (e) {
        modalFeedback.textContent = 'Erro de comunicação.';
      }
    });
    
    // Fecha modal ao clicar fora
    modal.addEventListener('click', function(e) {
      if (e.target === modal) modal.style.display = 'none';
    });
  }

  // Sincroniza color picker e campo texto para cada cor
  [1,2,3].forEach(function(i){
    var preview = document.getElementById('color_preview_'+i);
    var picker = document.getElementById('color_picker_'+i);
    var input = document.getElementById('company_color'+i);
    const corVar = i === 1 ? '--cor-primaria' : i === 2 ? '--cor-secundaria' : '--cor-destaque';
    const corVarRgb = i === 1 ? '--cor-primaria-rgb' : i === 2 ? '--cor-secundaria-rgb' : '--cor-destaque-rgb';
    if(preview && picker && input){
      // Atualiza preview e campo texto ao mudar picker
      picker.addEventListener('input',function(){
        const color = picker.value;
        input.value = color;
        preview.style.backgroundColor = color;
        // Atualiza as variáveis CSS em tempo real
        document.documentElement.style.setProperty(corVar, color);
        document.documentElement.style.setProperty(corVarRgb, hexToRgb(color));
        var event = new Event('input', { bubbles: true });
        input.dispatchEvent(event);
      });
      // Atualiza picker e preview ao mudar campo texto
      input.addEventListener('input',function(){
        picker.value = input.value;
        preview.style.backgroundColor = input.value;
      });
      // Inicializa preview e picker com valores padrão ou carregados
      if(input.value) {
          picker.value = input.value;
          preview.style.backgroundColor = input.value;
      }
    }
  });

  const form = document.getElementById("company_settings_form");
  if (form) {
    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      await salvarCompanySettings();
    });

    // Botão Cancelar restaura as configurações do backend
    const btnCancelar = document.getElementById('cancelar_config_btn');
    if (btnCancelar) {
      btnCancelar.addEventListener('click', async function() {
        if (confirm('Deseja realmente cancelar as alterações? Todas as mudanças não salvas serão perdidas.')) {
          await carregarCompanySettings();
          // Mensagem de feedback
          const message = document.createElement('div');
          message.className = 'fixed bottom-4 right-4 bg-blue-50 text-blue-700 px-6 py-3 rounded-lg shadow-lg';
          message.textContent = 'As alterações foram canceladas e as configurações foram restauradas.';
          document.body.appendChild(message);
          setTimeout(() => { message.remove(); }, 3000);
        }
      });
    }
  }

  
/**
 * Busca e exibe as imagens de banner cadastradas, permitindo remoção.
 */
async function carregarBannerImages() {
  const list = document.getElementById('banner_thumbnails');
  if (!list) return;
  list.innerHTML = '<div class="text-gray-400">Carregando...</div>';
  try {
    const res = await fetch('api/getBanners.php');
    const banners = await res.json();
    if (!Array.isArray(banners) || banners.length === 0) {
      list.innerHTML = '<div class="text-gray-400">Nenhuma imagem cadastrada.</div>';
      return;
    }
    list.innerHTML = '';
    banners.slice(0,5).forEach(banner => {
      const imageUrl = banner.image_url || banner.image_path;
      const card = document.createElement('div');
      card.className = 'flex gap-4 items-start w-full border-b pb-4 mb-4';
      card.innerHTML = `
        <div class="relative">
          <div class="w-40 h-24 bg-cover bg-center rounded-lg border border-gray-200 flex items-center justify-center" style="background-image:url('${imageUrl}')"></div>
          <button type="button" class="absolute top-2 right-2 bg-white rounded-full p-1 shadow banner-remove" style="border:none;" title="Remover" data-id="${banner.id}">
            <i class="fa-solid fa-times text-red-500"></i>
          </button>
        </div>
        <div class="flex-grow">
          <input type="text" class="w-full px-3 py-2 border rounded-lg mb-2 banner-title" 
            placeholder="Título do banner" 
            value="${banner.title || ''}"
            data-id="${banner.id}">
          <textarea class="w-full px-3 py-2 border rounded-lg banner-description" 
            placeholder="Descrição do banner" 
            rows="2"
            data-id="${banner.id}">${banner.description || ''}</textarea>
        </div>
      `;
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
          const res = await fetch('api/deleteBanner.php', { method: 'POST', body: formData });
          const result = await res.json();
          
          if (result.success) {
            const thumbs = document.getElementById('banner_thumbnails');
            if (thumbs) {
              card.style.opacity = '0';
              setTimeout(() => {
                card.remove();
                if (thumbs.children.length === 0) {
                  thumbs.innerHTML = '<div class="text-gray-400">Nenhuma imagem cadastrada.</div>';
                }
              }, 300);
            }
          } else {
            btn.innerHTML = originalHtml;
            btn.style.pointerEvents = '';
            window.utils.mostrarErro('Erro ao remover imagem: ' + (result.error || 'Falha ao remover. Tente novamente.'));
          }
        } catch (error) {
          btn.innerHTML = originalHtml;
          btn.style.pointerEvents = '';
          window.utils.mostrarErro('Erro ao remover imagem: Falha na comunicação com o servidor.');
        }
      });
      list.appendChild(card);
    });
  } catch (err) {
    list.innerHTML = '<div class="text-red-500">Erro ao carregar imagens.</div>';
  }
}
// Garantir que initializeSettings seja chamado quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
  if (typeof initializeSettings === 'function') {
    initializeSettings();
  }
});
  const logoInput = document.getElementById('company_logo');
  const logoPreview = document.getElementById('logo_preview');
  const changeLogoBtn = document.getElementById('change_logo_btn');
  const logoUploadInput = document.getElementById('logo_upload_input');

  if (logoInput && logoPreview && changeLogoBtn && logoUploadInput) {
  /**
   * Atualiza o preview da logo conforme o valor do input (URL ou caminho).
   */
  function updateLogoPreview() {
      const path = logoInput.value;
      // Adiciona a base do caminho se for um caminho relativo de assets
      const fullPath = (path && path.startsWith('assets/')) ? `../${path}` : path;
      
      if (path && (path.startsWith('http') || path.startsWith('assets/') || path.match(/\.(png|jpe?g|svg|webp)$/i))) {
        logoPreview.innerHTML = `<img src="${fullPath}" alt="Logo" class="object-contain w-full h-full">`;
      } else {
        logoPreview.innerHTML = '<i class="fas fa-building text-2xl"></i>';
      }
    }

    logoInput.addEventListener('input', updateLogoPreview);
    
    // Garante que o preview seja atualizado depois que os dados são carregados
    setTimeout(updateLogoPreview, 300); 

    changeLogoBtn.addEventListener('click', () => {
      logoUploadInput.click();
    });

  // Evento de upload de logo (envia para o backend e atualiza preview)
  logoUploadInput.addEventListener('change', async (event) => {
      const file = event.target.files[0];
      const infoEl = document.getElementById('logo_upload_info');
      if (!file) return;

      // Limpa mensagem
      if (infoEl) infoEl.textContent = 'Apenas arquivos .png são aceitos para a logo.';

      if (file.type !== 'image/png' || !file.name.toLowerCase().endsWith('.png')) {
        if (infoEl) infoEl.textContent = 'Erro: Apenas arquivos .png são aceitos para a logo.';
        return;
      }

      const formData = new FormData();
      formData.append('logo', file);

      try {
        const response = await fetch('api/uploadLogo.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();

        if (result.success) {
          logoInput.value = result.filePath;
          updateLogoPreview();
          if (infoEl) infoEl.textContent = 'Logo enviada com sucesso!';
          // Notifica outras abas/páginas para atualizar o logo
          if ('BroadcastChannel' in window) {
            const canal = new BroadcastChannel('configuracoes_empresa');
            canal.postMessage({ tipo: 'atualizar_logo', company_logo: result.filePath });
            canal.close();
          }
        } else {
          if (infoEl) infoEl.textContent = 'Erro ao enviar a logo: ' + result.message;
        }
      } catch (error) {
        console.error('Erro no upload:', error);
        if (infoEl) infoEl.textContent = 'Ocorreu um erro de comunicação ao enviar a logo.';
      }
    });
  }
}

/**
 * Carrega as configurações da empresa do backend e preenche o formulário.
 */
async function carregarCompanySettings() {
  try {
    const res = await fetch("api/getCompanySettings.php");
    const json = await res.json();
    console.log('[DEBUG] Dados recebidos do backend (painel):', json);
    if (json.success && json.data) {
      for (const [key, value] of Object.entries(json.data)) {
        const el = document.getElementById(key);
        if (el) el.value = value;
        // Atualiza logo do painel ao carregar configs
        if (key === 'company_logo' && 'BroadcastChannel' in window) {
          const canal = new BroadcastChannel('configuracoes_empresa');
          canal.postMessage({ tipo: 'atualizar_logo', company_logo: value });
          canal.close();
        }
        // Sincroniza visualmente os color pickers e previews e atualiza variáveis RGB
        if (key.startsWith('company_color')) {
          const idx = key.replace('company_color','');
          const picker = document.getElementById('color_picker_' + idx);
          const preview = document.getElementById('color_preview_' + idx);
          if (picker) picker.value = value;
          if (preview) preview.style.backgroundColor = value;
          // Atualiza as variáveis RGB
          const corVarRgb = idx === '1' ? '--cor-primaria-rgb' : idx === '2' ? '--cor-secundaria-rgb' : '--cor-destaque-rgb';
          document.documentElement.style.setProperty(corVarRgb, hexToRgb(value));
        }
      }
      
      // Adiciona campos de e-mail e notificações (apenas checkboxes pois os inputs já são preenchidos no loop anterior)
      if ('notify_new_lead' in json.data) document.getElementById('notify_new_lead').checked = json.data.notify_new_lead == 1;
      if ('notify_new_property' in json.data) document.getElementById('notify_new_property').checked = json.data.notify_new_property == 1;
      if ('notify_property_status' in json.data) document.getElementById('notify_property_status').checked = json.data.notify_property_status == 1;
      if ('notify_contact_form' in json.data) document.getElementById('notify_contact_form').checked = json.data.notify_contact_form == 1;

      // Debug para verificar os valores
      console.log('Valores de email:', {
        notifications: json.data.email_notifications,
        leads: json.data.email_leads
      });
    }
  } catch (err) {
    console.error("Erro ao carregar configurações:", err);
  }
}

/**
 * Coleta os dados do formulário e envia para o backend para salvar as configurações da empresa.
 */
async function salvarCompanySettings() {
  const fields = [
    'company_name', 'company_email', 'company_email2', 'company_phone', 'company_whatsapp', 'company_address',
    'company_weekday_hours', 'company_saturday_hours',
    'company_description', 'company_facebook', 'company_instagram', 'company_linkedin', 'company_youtube',
    'company_logo', 'company_color1', 'company_color2', 'company_color3', 'company_font',
    'email_notifications', 'email_leads'
  ];
  const required = [
    'company_name', 'company_email', 'company_phone', 'company_address', 'company_description'
  ];
  const data = {};
  let missing = [];
  fields.forEach(id => {
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
  data['notify_property_status'] = document.getElementById('notify_property_status')?.checked ? 1 : 0;
  data['notify_contact_form'] = document.getElementById('notify_contact_form')?.checked ? 1 : 0;
  if (missing.length > 0) {
    const labels = {
      'company_name': 'Nome da Empresa',
      'company_email': 'E-mail Principal',
      'company_phone': 'Telefone',
      'company_address': 'Endereço',
      'company_description': 'Descrição da Empresa'
    };
    window.utils.mostrarErro('Preencha os campos obrigatórios:\n' + missing.map(id => '- ' + labels[id]).join('\n'));
    return;
  }
  try {
    const res = await fetch("api/saveCompanySettings.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    });
    const json = await res.json();
    if (json.success) {
      // Usa os dados retornados do backend para garantir atualização instantânea
      const d = json.data || data;
      
      // Mostra mensagem de sucesso com estilo Bootstrap
      const alert = document.createElement('div');
      alert.className = 'alert alert-success alert-dismissible fade show position-fixed bottom-0 end-0 m-3';
      alert.style.zIndex = '9999';
      alert.innerHTML = `
          <strong>Sucesso!</strong> Configurações salvas com sucesso.
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      `;
      document.body.appendChild(alert);

      // Atualiza as cores e fonte
      if (d.company_font) {
        document.documentElement.style.setProperty('--fonte-principal', `'${d.company_font}', sans-serif`);
        if (!['Poppins','Arial','sans-serif'].includes(d.company_font)) {
          document.querySelectorAll('link[data-dynamic-font]').forEach(l => l.remove());
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
        document.documentElement.style.setProperty('--cor-primaria-rgb', hexToRgb(d.company_color1));
      }
      if (d.company_color2) {
        document.documentElement.style.setProperty('--cor-secundaria', d.company_color2);
        document.documentElement.style.setProperty('--cor-secundaria-rgb', hexToRgb(d.company_color2));
      }
      if (d.company_color3) {
        document.documentElement.style.setProperty('--cor-destaque', d.company_color3);
        document.documentElement.style.setProperty('--cor-destaque-rgb', hexToRgb(d.company_color3));
      }

      // Notifica outras abas sobre mudanças de cores e fonte
      if ('BroadcastChannel' in window) {
        const canal = new BroadcastChannel('configuracoes_empresa');
        canal.postMessage({ 
          tipo: 'atualizar_cores',
          company_color1: d.company_color1,
          company_color2: d.company_color2,
          company_color3: d.company_color3,
          company_font: d.company_font 
        });
        canal.close();
      }

      // Recarrega a página após 1 segundo
      setTimeout(() => {
        window.location.reload();
      }, 1000);
    } else {
      window.utils.mostrarErro(json.message || "Erro ao salvar configurações.");
    }
  } catch (err) {
    window.utils.mostrarErro("Erro ao salvar configurações.");
  }
}

