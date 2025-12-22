<?php
session_start();
// Verificar se usuário está logado
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1.0" name="viewport" />
  <title>Configurações da Empresa - RR Imóveis</title>
  
  <!-- Frameworks CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
  
  <!-- CSS Custom -->
  <link rel="stylesheet" href="assets/css/ribbons.css">
  
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
    
    :root {
      --cor-primaria: #2563eb;
      --cor-primaria-hover: #1d4ed8;
      --cor-secundaria: #10b981;
      --cor-destaque: #f59e0b;
      --fonte-principal: 'Poppins', sans-serif;
    }
    
    body { 
      font-family: var(--fonte-principal); 
    }
    </style>
    
    <script>
    // Garante que as variáveis CSS sejam definidas imediatamente
    (async function() {
      try {
        const res = await fetch('api/getCompanySettings.php');
        const json = await res.json();
        if (json.success && json.data) {
          const data = json.data;
          // Define as cores
          if (data.company_color1) {
            document.documentElement.style.setProperty('--cor-primaria', data.company_color1);
            document.documentElement.style.setProperty('--cor-primaria-hover', data.company_color1);
          }
          if (data.company_color2) {
            document.documentElement.style.setProperty('--cor-secundaria', data.company_color2);
          }
          if (data.company_color3) {
            document.documentElement.style.setProperty('--cor-destaque', data.company_color3);
          }
        }
      } catch (e) {
        console.error('Erro ao carregar cores:', e);
      }
    })();
    </script>
    <style>
    
    .color-preview { 
      width: 30px; 
      height: 30px; 
      border-radius: 50%; 
      display: inline-block; 
      margin-right: 10px; 
      vertical-align: middle; 
    }


  </style>
</head>
<body class="bg-gray-100">
  <div class="container py-5">
    <div class="card shadow mx-auto" style="max-width: 48rem;">
      <div class="card-body">
        <h2 class="h3 fw-bold mb-2">Configurações</h2>
        <p class="text-secondary mb-4">Gerencie as configurações do seu site e painel administrativo.</p>
        <form id="company_settings_form">
               
          <div class="row g-4 mb-4">
          <div class="col-md-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="company_name" placeholder="Nome da Empresa">
              <label for="company_name">Nome da Empresa</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="email" class="form-control" id="company_email" placeholder="E-mail Principal">
              <label for="company_email">E-mail Principal</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="email" class="form-control" id="company_email2" placeholder="E-mail Secundário">
              <label for="company_email2">E-mail Secundário</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="company_phone" placeholder="Telefone">
              <label for="company_phone">Telefone</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="company_whatsapp" placeholder="WhatsApp">
              <label for="company_whatsapp">WhatsApp</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-floating">
              <input type="text" class="form-control" id="company_address" placeholder="Endereço">
              <label for="company_address">Endereço</label>
            </div>
          </div>
          <div>
            <label class="form-label text-dark mb-2" for="company_business_hours">Horário de Atendimento</label>
            <div class="row g-4">
              <div class="col-6">
                <label class="form-label text-secondary mb-1">Segunda a Sexta</label>
                <input class="form-control" id="company_weekday_hours" type="text" />
              </div>
              <div class="col-6">
                <label class="form-label text-secondary mb-1">Sábado</label>
                <input class="form-control" id="company_saturday_hours" type="text" />
              </div>
            </div>
          </div>
        </div>
        <div class="mb-6">
          <label class="form-label text-dark mb-2" for="company_description">Descrição da Empresa</label>
          <textarea class="form-control" id="company_description" rows="4"></textarea>
          
        </div>
        <div class="mb-6">
                <label class="block text-gray-700 mb-2">Redes Sociais</label>
                <div class="space-y-3">
                  <div class="flex items-center">
                    <span class="w-9 h-9 flex items-center justify-center rounded-full bg-blue-100 mr-3"><i class="fab fa-facebook-f text-blue-600 text-xl"></i></span>
                    <input class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="company_facebook" type="text" placeholder="https://facebook.com/rrimoveis" />
                  </div>
                  <div class="flex items-center">
                    <span class="w-9 h-9 flex items-center justify-center rounded-full bg-pink-100 mr-3"><i class="fab fa-instagram text-pink-500 text-xl"></i></span>
                    <input class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-pink-500" id="company_instagram" type="text" placeholder="https://instagram.com/rrimoveis" />
                  </div>
                  <div class="flex items-center">
                    <span class="w-9 h-9 flex items-center justify-center rounded-full bg-blue-100 mr-3"><i class="fab fa-linkedin-in text-blue-700 text-xl"></i></span>
                    <input class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-700" id="company_linkedin" type="text" placeholder="https://linkedin.com/company/rrimoveis" />
                  </div>
                  <div class="flex items-center">
                    <span class="w-9 h-9 flex items-center justify-center rounded-full bg-red-100 mr-3"><i class="fab fa-youtube text-red-600 text-xl"></i></span>
                    <input class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600" id="company_youtube" type="text" placeholder="https://youtube.com/rrimoveis" />
                  </div>
                </div>
              </div>
        <hr class="my-8">
  <!-- Bloco duplicado de imagens estáticas removido -->
        <h3 class="fs-5 fw-semibold text-dark mb-4">Personalização do Site</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-gray-700 mb-2 font-bold">Cores Principais  Originais >>>  Primária #111827 / Secundária #10b981 / Destaque #d97706</label>
            <div class="flex items-center space-x-4">
              <div>
                <span class="block text-sm text-gray-500 mb-1">Primária</span>
                <div class="flex items-center">
                  <span class="color-preview" id="color_preview_1" style="background-color: #1e40af; cursor:pointer;"></span>
                  <input id="color_picker_1" type="color" value="#1e40af" style="margin-right:8px;">
                  <input id="company_color1" type="text" value="#1e40af" class="w-24 px-2 py-1 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
              </div>
              <div>
                <span class="block text-sm text-gray-500 mb-1">Secundária</span>
                <div class="flex items-center">
                  <span class="color-preview" id="color_preview_2" style="background-color: #10b981; cursor:pointer;"></span>
                  <input id="color_picker_2" type="color" value="#10b981" style="margin-right:8px;">
                  <input id="company_color2" type="text" value="#10b981" class="w-24 px-2 py-1 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
              </div>
              <div>
                <span class="block text-sm text-gray-500 mb-1">Destaque</span>
                <div class="flex items-center">
                  <span class="color-preview" id="color_preview_3" style="background-color: #f59e0b; cursor:pointer;"></span>
                  <input id="color_picker_3" type="color" value="#f59e0b" style="margin-right:8px;">
                  <input id="company_color3" type="text" value="#f59e0b" class="w-24 px-2 py-1 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                </div>
              </div>
            </div>
          </div>
                  <div class="col-md-3">
                    <div class="form-floating">
                      <select class="form-select" id="company_font" style="max-width:220px;">
                        <option>Poppins</option>
                        <option>Roboto</option>
                        <option>Open Sans</option>
                        <option>Montserrat</option>
                        <option>Lato</option>
                      </select>
                      <label for="company_font">Fonte Principal</label>
                    </div>
                  </div>
                  <!-- Cor da Fonte Principal removida (campo retirado conforme solicitado) -->
          <!-- (duplicado removido: campo Fonte Principal mantido ao lado das cores) -->
        </div>
        <div class="mb-4">
          <h5 class="mb-3">Logo da Empresa</h5>
          <div class="d-flex flex-column flex-sm-row align-items-center gap-3">
            <div id="logo_preview" class="square bg-light border rounded-3 d-flex align-items-center justify-content-center text-muted overflow-hidden" style="width: 56px; height: 56px">
              <i class="fas fa-building fs-5"></i>
            </div>
            <div class="form-floating flex-grow-1">
              <input type="text" class="form-control" id="company_logo" placeholder="URL da logo ou nome do arquivo">
              <label for="company_logo">URL da logo ou nome do arquivo</label>
            </div>
            <button type="button" id="change_logo_btn" class="btn btn-primary">
              Alterar Logo
            </button>
            <input type="file" id="logo_upload_input" accept="image/*" class="d-none" />
          </div>
        </div>
        <div class="mb-6">
          <label class="block text-gray-700 font-semibold mb-2">Imagens do Banner Principal</label>
        </div>
        <div id="banner_thumbnails" class="flex gap-4 mb-4 flex-wrap"></div>
        <div class="mt-2 mb-8">
          <button id="btn_adicionar_banner" type="button" class="btn-sistema-principal"><i class="fa-solid fa-plus me-2"></i>Adicionar Imagem</button>
          <!-- Modal de upload de banner -->
          <div id="modal_banner_upload" class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg shadow-lg border border-gray-200" style="display:none; width:400px; z-index:50;">
            <div class="p-6 flex flex-col gap-4">
              <h4 class="text-lg font-semibold mb-2">Adicionar Imagem ao Banner</h4>
              <input type="file" id="modal_banner_file" accept="image/*" class="mb-2" />
              <div id="modal_banner_feedback" class="text-sm text-red-500 mb-2"></div>
              <div class="flex gap-3 justify-end">
                <button type="button" id="modal_banner_cancel" class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300"><i class="fa-solid fa-xmark"></i>Cancelar</button>
                <button type="button" id="modal_banner_send" class="btn-sistema-principal"><i class="fa-solid fa-paper-plane"></i>Enviar</button>
              </div>
            </div>
          </div>
        </div>
        <hr class="my-8">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Configurações de E-mail</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-gray-700 mb-2" for="email_notifications">E-mail para Notificações</label>
            <input type="email" id="email_notifications" placeholder="email@exemplo.com.br" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="email_leads">E-mail para Leads</label>
            <input type="email" id="email_leads" placeholder="leads@exemplo.com.br" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
        </div>
        <div class="mb-4">
          <h5 class="mb-3">Notificações por E-mail</h5>
          <div class="vstack gap-3">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="notify_new_lead" checked>
              <label class="form-check-label" for="notify_new_lead">Novo lead recebido</label>
            </div>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="notify_new_property" checked>
              <label class="form-check-label" for="notify_new_property">Novo imóvel cadastrado</label>
            </div>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="notify_property_status" checked>
              <label class="form-check-label" for="notify_property_status">Alteração de status de imóvel</label>
            </div>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="notify_contact_form" checked>
              <label class="form-check-label" for="notify_contact_form">Mensagem do formulário de contato</label>
            </div>
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="notify_agendamento" checked>
              <label class="form-check-label" for="notify_agendamento">Novo agendamento e alteração de status</label>
            </div>
          </div>
        </div>
        <div class="text-end mt-4">
          <button type="button" id="cancelar_config_btn" class="btn btn-outline-secondary me-2"><i class="fa-solid fa-xmark"></i>
            Cancelar
          </button>
          <button type="submit" class="btn btn-primary"><i class="fa-regular fa-floppy-disk"></i>
            Salvar Alterações
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
  <script src="assets/js/painel/settings.js"></script>
  <script>
    if (typeof initializeSettings === 'function') {
      initializeSettings();
    } else {
      window.addEventListener('DOMContentLoaded', function() {
        if (typeof initializeSettings === 'function') initializeSettings();
      });
    }
  </script>
  <script>
  // Aplica a fonte principal do backend na tela de configurações
  window.addEventListener('DOMContentLoaded', async function() {
    try {
      const res = await fetch('api/getCompanySettings.php');
      const json = await res.json();
      if (!json.success || !json.data) return;
      const data = json.data;
      if (data.company_font) {
        document.documentElement.style.setProperty('--fonte-principal', `'${data.company_font}', sans-serif`);
        if (!['Poppins','Arial','sans-serif'].includes(data.company_font)) {
          const link = document.createElement('link');
          link.rel = 'stylesheet';
          link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(data.company_font)}:wght@300;400;500;600;700&display=swap`;
          document.head.appendChild(link);
        }
      }
    } catch (e) {}
  });
  </script>
</body>
</html>
