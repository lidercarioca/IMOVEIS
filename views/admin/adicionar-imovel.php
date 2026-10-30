  <link rel="stylesheet" href="../../assets/css/ribbons.css">
  <?php
  $rootPath = $_SERVER['DOCUMENT_ROOT'];
  require_once $rootPath . '/auth.php';
  require_once $rootPath . '/config/session_manager.php';
  require_once $rootPath . '/app/security/Security.php';
  // Garantir autoload PSR-4 disponível quando necessário
  if (file_exists($rootPath . '/vendor/autoload.php')) {
    require_once $rootPath . '/vendor/autoload.php';
  }
  Security::init();
  checkAuth();

  // Verifica se o usuário é admin
  $isAdmin = isAdmin();

  // Garantir que $pdo esteja disponível neste escopo.
  // Preferir conexão global quando disponível, senão usar getConnection()
  if (!isset($pdo) || !($pdo instanceof \PDO)) {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
      $pdo = $GLOBALS['pdo'];
    } elseif (function_exists('getConnection')) {
      // getConnection() é definido em config/database.php como compatibilidade
      $pdo = getConnection();
    } else {
      // Tentativa final: incluir config/database.php se existir (não cria nova lógica de conexão aqui)
      $dbFile = $rootPath . '/config/database.php';
      if (file_exists($dbFile)) {
        require_once $dbFile;
        if (!isset($pdo) && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof \PDO) {
          $pdo = $GLOBALS['pdo'];
        }
      }
    }
  }

  // Se for uma chamada AJAX/fetch, retorna apenas o conteúdo do formulário
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $tenantId = function_exists('getRequestTenantId') ? (int)getRequestTenantId() : null;
    if (!$tenantId) {
      $tenantId = isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
    }
    $planLimitMessage = '';

    if ($tenantId) {
      try {
        // Usar serviços PSR-4 read-only equivalentes ao helper legacy
        $propertyService = new \App\Service\PropertyService($pdo);
        $totalImoveis = (int)$propertyService->getPropertyCountForTenant((int)$tenantId);

        $tenantRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);
        $tenant = $tenantRepo->getTenantData($tenantId);
        if ($tenant && !empty($tenant['plano'])) {
          $plano = $tenantRepo->getPlanByCode((string)$tenant['plano']);
          $limiteImoveis = $plano ? ($plano['limite_imoveis'] ?? null) : null;
        } else {
          $limiteImoveis = null;
        }

        if ($limiteImoveis !== null) {
          if ($limiteImoveis === 0) {
            $planLimitMessage = '<div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">Seu plano atual não permite cadastro de imóveis. Atualize seu plano em <a href="/planos.php" class="font-semibold text-yellow-900 underline">Planos</a> para liberar novas vagas.</div>';
          } elseif ($totalImoveis >= $limiteImoveis) {
            $planLimitMessage = '<div class="mb-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">Você já cadastrou todos os imóveis permitidos pelo seu plano atual (<strong>' . $totalImoveis . '/' . $limiteImoveis . '</strong>). Para cadastrar mais imóveis, atualize seu plano em <a href="/planos.php" class="font-semibold text-yellow-900 underline">Planos</a>.</div>';
          } else {
            $remaining = max(0, $limiteImoveis - $totalImoveis);
            $planLimitMessage = '<div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">Seu plano atual permite até <strong>' . $limiteImoveis . '</strong> imóveis. Atualmente você possui <strong>' . $totalImoveis . '</strong>, restando <strong>' . $remaining . '</strong> vagas para novos imóveis.</div>';
          }
        } else {
          $planLimitMessage = '<div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">Não foi possível obter o limite de imóveis do plano atual. Se necessário, acesse <a href="/planos.php" class="font-semibold text-blue-900 underline">Planos</a>.</div>';
        }
      } catch (Exception $e) {
        error_log('Erro ao obter limite de imóveis do tenant: ' . $e->getMessage());
        $planLimitMessage = '<div class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">Não foi possível obter o limite de imóveis do seu plano no momento. Tente novamente em breve.</div>';
      }
    }

    ob_start(); // Inicia o buffer de saída
  ?>
    <style>
      .erro-validacao {
        animation: fadeIn 0.3s ease-in-out;
      }

      @keyframes fadeIn {
        from {
          opacity: 0;
          transform: translateY(-5px);
        }

        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      input.border-red-500,
      textarea.border-red-500,
      select.border-red-500 {
        border-color: rgb(239, 68, 68);
        background-color: rgb(254, 242, 242);
      }

      input.border-red-500:focus,
      textarea.border-red-500:focus,
      select.border-red-500:focus {
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
      }
    </style>


    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-800 mb-2">Adicionar Novo Imóvel</h2>
      <p class="text-gray-600">Preencha os campos abaixo com as informações do imóvel.</p>
      <?php echo $planLimitMessage; ?>
    </div>


    <div class="bg-white rounded-lg shadow-sm p-6">
      <form id="property-form">
        <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
        <input type="hidden" name="edit_id" id="edit_id" value="" />
        <!-- CAMPOS DO FORMULÁRIO (mesmos do seu HTML original) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-gray-700 mb-2" for="title">Título do Imóvel *</label>
            <input id="title" name="title" type="text" required
              placeholder="Ex: Apartamento Moderno em Pinheiros"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="type">Tipo de Imóvel *</label>
            <select id="type" name="type" required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione o tipo</option>
              <option value="apartment">Apartamento</option>
              <option value="house">Casa</option>
              <option value="commercial">Comercial</option>
              <option value="land">Terreno</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
          <div>
            <label class="block text-gray-700 mb-2" for="transactionType">Tipo de Transação *</label>
            <select id="transactionType" name="transactionType" required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione</option>
              <option value="venda">Venda</option>
              <option value="aluguel">Aluguel</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="price">Preço *</label>
            <input id="price" name="price" type="text" required autocomplete="off"
              placeholder="Ex: 450.000,00"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="area">Área (m&sup2;) *</label>
            <input id="area" name="area" type="text" required autocomplete="off"
              placeholder="Ex: 75"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div>
          <label class="block text-gray-700 mb-2" for="yearBuilt">Ano de Construção</label>
          <input id="yearBuilt" name="yearBuilt" type="number" min="1800" max="2100"
            placeholder="Ex: 2018"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 mt-2">
          <div>
            <label class="block text-gray-700 mb-2" for="condominium">Condomínio (R$)</label>
            <input id="condominium" name="condominium" type="text" inputmode="decimal"
              placeholder="Ex: 450,00"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="iptu">IPTU (R$)</label>
            <input id="iptu" name="iptu" type="text" inputmode="decimal"
              placeholder="Ex: 1200,00"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="commission_percentage">Percentual de Comissão (%)</label>
            <input id="commission_percentage" name="commission_percentage" type="text" inputmode="decimal"
              placeholder="Ex: 5,00"
              <?php echo !$isAdmin ? 'readonly' : ''; ?>
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 <?php echo !$isAdmin ? 'bg-gray-100 text-gray-600 cursor-not-allowed' : ''; ?>" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-gray-700 mb-2" for="suites">Suítes</label>
            <input id="suites" name="suites" type="number" min="0"
              placeholder="Ex: 1"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 mt-6">
          <div>
            <label class="block text-gray-700 mb-2" for="bedrooms">Quartos</label>
            <input id="bedrooms" name="bedrooms" type="number" required
              placeholder="Ex: 2"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="bathrooms">Banheiros</label>
            <input id="bathrooms" name="bathrooms" type="number" required
              placeholder="Ex: 1"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="garage">Vagas de Garagem</label>
            <input id="garage" name="garage" type="number"
              placeholder="Ex: 1"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-gray-700 mb-2" for="address">Endereço *</label>
            <input id="address" name="address" type="text" required
              placeholder="Ex: Rua dos Pinheiros, 123"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="neighborhood">Bairro *</label>
            <input id="neighborhood" name="neighborhood" type="text" required
              placeholder="Ex: Pinheiros"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-gray-700 mb-2" for="assigned_user_id">Atribuir a Usuário (opcional)</label>
            <?php if ($isAdmin): ?>
              <select id="assigned_user_id" name="assigned_user_id"
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Nenhum</option>
              </select>
            <?php else: ?>
              <?php $currentUserId = $_SESSION['user_id'] ?? 0; ?>
              <select id="assigned_user_id" name="assigned_user_id" disabled
                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-700 cursor-not-allowed">
                <option value="<?php echo intval($currentUserId); ?>">Você</option>
              </select>
              <input type="hidden" id="assigned_user_id_hidden" name="assigned_user_id" value="<?php echo intval($currentUserId); ?>" />
            <?php endif; ?>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
          <div>
            <label class="block text-gray-700 mb-2" for="city">Cidade *</label>
            <input id="city" name="city" type="text" required
              placeholder="Ex: São Paulo"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="state">Estado *</label>
            <select id="state" name="state" required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione</option>
              <option value="AC">Acre</option>
              <option value="AL">Alagoas</option>
              <option value="AP">Amapá</option>
              <option value="AM">Amazonas</option>
              <option value="BA">Bahia</option>
              <option value="CE">Ceará</option>
              <option value="DF">Distrito Federal</option>
              <option value="ES">Espírito Santo</option>
              <option value="GO">Goiás</option>
              <option value="MA">Maranhão</option>
              <option value="MT">Mato Grosso</option>
              <option value="MS">Mato Grosso do Sul</option>
              <option value="MG">Minas Gerais</option>
              <option value="PA">Pará</option>
              <option value="PB">Paraíba</option>
              <option value="PR">Paraná</option>
              <option value="PE">Pernambuco</option>
              <option value="PI">Piauí</option>
              <option value="RJ">Rio de Janeiro</option>
              <option value="RN">Rio Grande do Norte</option>
              <option value="RS">Rio Grande do Sul</option>
              <option value="RO">Rondônia</option>
              <option value="RR">Roraima</option>
              <option value="SC">Santa Catarina</option>
              <option value="SP">São Paulo</option>
              <option value="SE">Sergipe</option>
              <option value="TO">Tocantins</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700 mb-2" for="zip">CEP *</label>
            <input id="zip" name="zip" type="text" required
              placeholder="Ex: 01234-567"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
        </div>

        <div class="mb-6">
          <label class="block text-gray-700 mb-2" for="description">Descrição *</label>
          <textarea id="description" name="description" rows="5" required
            placeholder="Descreva o imóvel detalhadamente..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
        </div>

        <div class="mb-6">
          <label class="block text-gray-700 mb-2">Características</label>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <label><input type="checkbox" name="features[]" value="Piscina" /> Piscina</label>
            <label><input type="checkbox" name="features[]" value="Jardim" /> Jardim</label>
            <label><input type="checkbox" name="features[]" value="Academia" /> Academia</label>
            <label><input type="checkbox" name="features[]" value="Segurança" /> Segurança 24h</label>
            <label><input type="checkbox" name="features[]" value="Elevador" /> Elevador</label>
            <label><input type="checkbox" name="features[]" value="Churrasqueira" /> Churrasqueira</label>
            <label><input type="checkbox" name="features[]" value="Mobiliado" /> Mobiliado</label>
            <label><input type="checkbox" name="features[]" value="Aceita Pet" /> Aceita Pet</label>
          </div>
        </div>

        <div class="mb-6">
          <label class="block text-gray-700 mb-2">Imagens do Imóvel</label>
          <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
            <p class="text-gray-500 mb-2">Arraste e solte as imagens aqui ou</p>
            <input id="property-images" name="imagens[]" type="file" accept="image/*" multiple class="hidden" />
            <button type="button" class="btn-sistema-principal" onclick="document.getElementById('property-images').click();"><i class="fa-sharp fa-solid fa-folder-plus"></i>
              Selecionar Arquivos
            </button>
            <p class="text-xs text-gray-500 mt-2">PNG, JPG ou JPEG (máx. 5MB por arquivo. Formato 1280x720)</p>
            <div id="selected-files-list" class="mt-4"></div>
          </div>
        </div>

        <div class="mb-6">
          <label class="block text-gray-700 mb-2">Status do Imóvel</label>
          <div class="flex space-x-4">
            <label><input type="radio" name="status" value="active" checked /> Ativo</label>
            <label><input type="radio" name="status" value="pending" /> Pendente</label>
            <label><input type="radio" name="status" value="vendido" /> Vendido</label>
            <label><input type="radio" name="status" value="alugado" /> Alugado</label>
            <label><input type="radio" name="status" value="inactive" /> Inativo</label>
          </div>
        </div>

        <div class="flex justify-end space-x-4">
          <button id="btn-cancelar-imovel" type="button"
            class="px-6 py-2 border border-gray-300 rounded-lg hover:bg-gray-100 transition"><i class="fa-solid fa-rectangle-xmark"></i>
            Cancelar
          </button>
          <button type="submit"
            class="btn-sistema-principal"><i class="fa-solid fa-floppy-disk"></i>
            Salvar Imóvel
          </button>
        </div>

      </form>
    </div>
  <?php
    $content = ob_get_clean(); // Pega o conteúdo do buffer e limpa
    echo $content;
    exit();
  }
  ?>