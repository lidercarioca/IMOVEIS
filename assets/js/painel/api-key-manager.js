/**
 * Gerenciador de Chaves de API
 * Responsável por gerar, listar e revogar chaves de API
 */

// Armazenar dados localmente para cache
let apiKeysCache = [];

/**
 * Inicializa o gerenciador de chaves de API
 */
function initAPIKeyManager() {
  // Escuta quando a aba de API Pública é selecionada
  const apiPublicaBtn = document.querySelector('[data-subtab="api-publica"]');
  if (apiPublicaBtn) {
    apiPublicaBtn.addEventListener("click", () => {
      carregarChavesAPI();
    });
  }

  // Carrega as chaves na primeira vez
  carregarChavesAPI();

  // Event delegation para botões de revogar
  document.addEventListener("click", (e) => {
    const revogarBtn = e.target.closest("[data-revoke-key-id]");
    if (revogarBtn) {
      const keyId = revogarBtn.getAttribute("data-revoke-key-id");
      revogarChaveAPI(keyId);
    }
  });
}

/**
 * Carrega as chaves de API do servidor
 */
async function carregarChavesAPI() {
  try {
    const response = await fetch("/api/v1/public/getAPIKeys.php", {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success && Array.isArray(data.keys)) {
      apiKeysCache = data.keys;
      renderizarChavesAPI(data.keys);
    } else {
      exibirErroChaves(data.message || "Erro ao carregar chaves de API");
    }
  } catch (error) {
    exibirErroChaves(`Erro ao conectar: ${error.message}`);
  }
}

/**
 * Renderiza a lista de chaves de API
 */
function renderizarChavesAPI(keys) {
  const container = document.getElementById("api-keys-list");

  if (!keys || keys.length === 0) {
    container.innerHTML = `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center">
                <i class="fas fa-info-circle text-yellow-600 text-2xl mb-2"></i>
                <p class="text-gray-700">Você ainda não tem chaves de API geradas.</p>
                <p class="text-sm text-gray-600 mt-2">Crie uma nova chave acima para começar!</p>
            </div>
        `;
    return;
  }

  let html = "";

  keys.forEach((key) => {
    const criadoEm = new Date(key.created_at).toLocaleDateString("pt-BR");
    const expiraEm = key.expires_at
      ? new Date(key.expires_at).toLocaleDateString("pt-BR")
      : "Nunca";
    const status = key.is_active ? "Ativa" : "Inativa";
    const statusClass = key.is_active
      ? "bg-green-100 text-green-800"
      : "bg-red-100 text-red-800";

    // Mascarar a chave, mostrando apenas os últimos 8 caracteres
    const chaveMascarada = key.key.substring(0, 8) + "..." + key.key.slice(-8);

    html += `
            <div class="bg-white border border-gray-300 rounded-lg p-4 flex items-start justify-between hover:shadow-sm transition">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <h5 class="font-bold text-gray-800">${escapeHtml(key.name)}</h5>
                        <span class="px-2 py-1 text-xs font-semibold rounded ${statusClass}">
                            ${status}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                        <div>
                            <p class="text-gray-500">Chave:</p>
                            <code class="bg-gray-100 px-2 py-1 rounded border border-gray-300 select-all">${chaveMascarada}</code>
                        </div>
                        <div>
                            <p class="text-gray-500">Criada em:</p>
                            <p>${criadoEm}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Expira em:</p>
                            <p>${expiraEm}</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-eye-slash mr-1"></i>
                        A chave completa é mostrada apenas uma vez durante a criação
                    </p>
                </div>
                <div class="flex gap-2 ml-4">
                    <button onclick="copiarChaveCompleta('${key.key}')" class="bg-blue-100 hover:bg-blue-200 text-blue-700 font-medium py-2 px-3 rounded transition" title="Copiar chave completa">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button data-revoke-key-id="${key.id}" class="bg-red-100 hover:bg-red-200 text-red-700 font-medium py-2 px-3 rounded transition" title="Revogar chave">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        `;
  });

  container.innerHTML = html;
}

/**
 * Gera uma nova chave de API
 */
async function gerarChaveAPI() {
  const nome = document.getElementById("api-key-name").value.trim();
  const expiracao = document.getElementById("api-key-expiration").value;

  if (!nome) {
    mostrarNotificacao("Por favor, insira um nome para a chave", "danger");
    return;
  }

  // Calcular data de expiração
  let expiresAt = null;
  if (expiracao !== "never") {
    const dias = parseInt(expiracao);
    const data = new Date();
    data.setDate(data.getDate() + dias);
    expiresAt = data.toISOString().split("T")[0];
  }

  try {
    const response = await fetch("/api/v1/public/createAPIKey.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        name: nome,
        expires_at: expiresAt,
      }),
    });

    const data = await response.json();

    if (data.success && data.key) {
      mostrarNotificacao("Chave de API gerada com sucesso!", "success");

      // Mostrar modal com a chave completa
      exibirChaveNova(data.key, nome);

      // Limpar formulário
      document.getElementById("api-key-name").value = "";
      document.getElementById("api-key-expiration").value = "never";

      // Recarregar lista
      await carregarChavesAPI();
    } else {
      mostrarNotificacao(data.message || "Erro ao gerar chave", "danger");
    }
  } catch (error) {
    mostrarNotificacao("Erro ao conectar com o servidor", "danger");
  }
}

/**
 * Exibe modal com a chave recém gerada
 */
function exibirChaveNova(chave, nome) {
  const modal = document.createElement("div");
  modal.className =
    "fixed bg-black bg-opacity-50 flex items-center justify-center";
  modal.style.cssText =
    "z-index: 1040; left: 280px; right: 0; top: 0; bottom: 0;";
  modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full mx-4">
            <div class="text-center mb-6">
                <div class="inline-block bg-green-100 rounded-full p-3 mb-4">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">Chave Gerada!</h3>
                <p class="text-gray-600 text-sm mt-2">Copie sua chave abaixo e guarde em local seguro</p>
            </div>
            
            <div class="bg-gray-50 border border-gray-300 rounded-lg p-4 mb-6">
                <p class="text-xs text-gray-500 mb-2">Chave de API (${nome}):</p>
                <code class="block bg-white px-3 py-2 rounded border border-gray-300 text-sm break-all select-all">${chave}</code>
            </div>
            
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-red-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Importante:</strong> Esta é a única vez que a chave completa será exibida. 
                    Salve-a agora em um local seguro.
                </p>
            </div>
            
            <div class="flex gap-3">
                <button onclick="copiarChaveCompleta('${chave}')" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded transition" style="background-color: var(--cor-primaria);">
                    <i class="fas fa-copy mr-2"></i>Copiar Chave
                </button>
                <button onclick="this.closest('.fixed').remove()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-800 font-medium py-2 px-4 rounded transition">
                    Fechar
                </button>
            </div>
        </div>
    `;

  document.body.appendChild(modal);
  modal.addEventListener("click", (e) => {
    if (e.target === modal) {
      modal.remove();
    }
  });
}

/**
 * Copia a chave para clipboard
 */
function copiarChaveCompleta(chave) {
  navigator.clipboard
    .writeText(chave)
    .then(() => {
      mostrarNotificacao("Chave copiada para clipboard!", "success");
    })
    .catch(() => {
      mostrarNotificacao("Erro ao copiar chave", "danger");
    });
}

/**
 * Revoga uma chave de API
 */
async function revogarChaveAPI(keyId) {
  if (
    !confirm(
      "Tem certeza que deseja revogar esta chave? Todas as integrações usando ela vão parar de funcionar.",
    )
  ) {
    return;
  }

  try {
    const response = await fetch("/api/v1/public/revokeAPIKey.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        key_id: parseInt(keyId, 10),
      }),
    });

    const data = await response.json();

    if (data.success) {
      mostrarNotificacao("Chave revogada com sucesso", "success");
      await carregarChavesAPI();
    } else {
      mostrarNotificacao(data.message || "Erro ao revogar chave", "danger");
    }
  } catch (error) {
    console.error("Erro ao revogar chave:", error);
    mostrarNotificacao("Erro ao conectar com o servidor", "danger");
  }
}

/**
 * Exibe erro no container de chaves
 */
function exibirErroChaves(mensagem) {
  const container = document.getElementById("api-keys-list");
  if (container) {
    container.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 text-center">
                <i class="fas fa-exclamation-circle text-red-600 text-2xl mb-2"></i>
                <p class="text-gray-700">${escapeHtml(mensagem)}</p>
            </div>
        `;
  }
}

/**
 * Escapa caracteres HTML para segurança
 */
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

/**
 * Mostra notificação no topo da página
 */
function mostrarNotificacao(mensagem, tipo = "info") {
  const alertDiv = document.createElement("div");
  alertDiv.className = `alert alert-${tipo} alert-dismissible fade show`;
  alertDiv.innerHTML = `
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

  // Renderizar dentro do container #alerts
  const alertsContainer = document.getElementById("alerts");
  if (alertsContainer) {
    alertsContainer.appendChild(alertDiv);
  } else {
    // Fallback: se o container não existir, renderizar no topo
    document.body.insertAdjacentElement("afterbegin", alertDiv);
  }

  setTimeout(() => alertDiv.remove(), 5000);
}

// Inicializar quando o DOM estiver pronto
document.addEventListener("DOMContentLoaded", initAPIKeyManager);
