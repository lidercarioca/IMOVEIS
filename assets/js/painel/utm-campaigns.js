/**
 * UTM Campaigns Manager
 * Gerencia criação, edição e visualização de campanhas UTM
 */

async function carregarCampanhasUTM() {
  try {
    const response = await fetch("/api/v1/utm/getCampaigns.php");
    const data = await response.json();

    if (!data.success) {
      alert("Erro ao carregar campanhas: " + data.error);
      return;
    }

    renderizarCampanhasUTM(data.data);
  } catch (err) {
    console.error("Erro ao carregar campanhas:", err);
    alert("Erro ao carregar campanhas");
  }
}

function renderizarCampanhasUTM(campanhas) {
  const container = document.getElementById("utm-campaigns-list");
  if (!container) return;

  if (!campanhas || campanhas.length === 0) {
    container.innerHTML = `
      <div class="text-center py-12">
        <i class="fas fa-link text-4xl text-gray-300 mb-3 block"></i>
        <p class="text-gray-500">Nenhuma campanha UTM criada ainda</p>
        <p class="text-gray-400 text-sm mt-1">Crie a primeira campanha para começar a rastrear leads</p>
      </div>
    `;
    return;
  }

  container.innerHTML = campanhas
    .map(
      (camp) => `
    <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
      <div class="flex items-start justify-between mb-3">
        <div>
          <h4 class="font-bold text-gray-800">${camp.campaign_name}</h4>
          <p class="text-sm text-gray-500">${camp.source} • ${camp.medium}</p>
        </div>
        <div class="flex gap-2">
          <button onclick="editarCampanhaUTM(${camp.id})" class="px-3 py-1 bg-blue-100 text-blue-600 rounded hover:bg-blue-200 transition text-sm">
            <i class="fas fa-edit mr-1"></i>Editar
          </button>
          <button onclick="deletarCampanhaUTM(${camp.id})" class="px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 transition text-sm">
            <i class="fas fa-trash mr-1"></i>Deletar
          </button>
        </div>
      </div>

      <div class="mb-3 p-3 bg-gray-50 rounded text-sm font-mono text-gray-700 break-all">
        ${camp.utm_link}
      </div>

      <div class="flex gap-2">
        <button onclick="copiarLinkUTM('${camp.utm_link}')" class="flex-1 px-3 py-2 bg-green-50 text-green-600 hover:bg-green-100 rounded text-sm font-medium transition">
          <i class="fas fa-copy mr-1"></i>Copiar Link
        </button>
        <button onclick="abrirLinkUTM('${camp.utm_link}')" class="flex-1 px-3 py-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded text-sm font-medium transition">
          <i class="fas fa-external-link-alt mr-1"></i>Abrir
        </button>
      </div>

      ${camp.description ? `<p class="text-xs text-gray-600 mt-3 italic">${camp.description}</p>` : ""}
    </div>
  `,
    )
    .join("");
}

function abrirModalNovaCampanhaUTM() {
  const html = `
    <div class="fixed bg-black bg-opacity-50 flex items-center justify-center" id="modal-nova-campanha" style="z-index: 1040; left: 280px; right: 0; top: 0; bottom: 0;">
      <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-gray-800 mb-4">Nova Campanha UTM</h3>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nome da Campanha *</label>
            <input type="text" id="utm-campaign-name" placeholder="Ex: Vendas_Google_Ads_Janeiro" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Fonte *</label>
              <select id="utm-source" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Selecione</option>
                <option value="google">Google</option>
                <option value="facebook">Facebook</option>
                <option value="instagram">Instagram</option>
                <option value="linkedin">LinkedIn</option>
                <option value="email">Email</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="organic">Organic</option>
                <option value="direct">Direct</option>
                <option value="outro">Outro</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Meio *</label>
              <select id="utm-medium" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Selecione</option>
                <option value="cpc">CPC</option>
                <option value="cpm">CPM</option>
                <option value="email">Email</option>
                <option value="social">Social</option>
                <option value="organic">Organic</option>
                <option value="referral">Referral</option>
                <option value="direct">Direct</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Conteúdo (Opcional)</label>
            <input type="text" id="utm-content" placeholder="Ex: Ad_Copy_A" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição (Opcional)</label>
            <textarea id="utm-description" placeholder="Notas sobre essa campanha" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3"></textarea>
          </div>
        </div>

        <div class="flex gap-2 mt-6">
          <button onclick="document.getElementById('modal-nova-campanha').remove()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
            Cancelar
          </button>
          <button onclick="criarCampanhaUTM()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
            Criar Campanha
          </button>
        </div>
      </div>
    </div>
  `;
  document.body.insertAdjacentHTML("beforeend", html);
}

async function criarCampanhaUTM() {
  const campaign_name = document
    .getElementById("utm-campaign-name")
    .value.trim();
  const source = document.getElementById("utm-source").value.trim();
  const medium = document.getElementById("utm-medium").value.trim();
  const content = document.getElementById("utm-content").value.trim();
  const description = document.getElementById("utm-description").value.trim();

  if (!campaign_name || !source || !medium) {
    alert("Preencha os campos obrigatórios (*)");
    return;
  }

  try {
    const response = await fetch("/api/v1/utm/getCampaigns.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        campaign_name,
        source,
        medium,
        content: content || null,
        description: description || null,
      }),
    });

    const data = await response.json();

    if (!data.success) {
      alert("Erro: " + data.error);
      return;
    }

    alert("✅ Campanha criada com sucesso!");
    document.getElementById("modal-nova-campanha").remove();
    carregarCampanhasUTM();
  } catch (err) {
    console.error("Erro ao criar campanha:", err);
    alert("Erro ao criar campanha");
  }
}

function editarCampanhaUTM(id) {
  alert("Funcionalidade de edição em breve");
}

async function deletarCampanhaUTM(id) {
  if (!confirm("Tem certeza que deseja deletar essa campanha?")) return;

  try {
    const response = await fetch("/api/v1/utm/getCampaigns.php", {
      method: "DELETE",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id }),
    });

    const data = await response.json();

    if (!data.success) {
      alert("Erro: " + data.error);
      return;
    }

    alert("✅ Campanha deletada com sucesso!");
    carregarCampanhasUTM();
  } catch (err) {
    console.error("Erro ao deletar campanha:", err);
    alert("Erro ao deletar campanha");
  }
}

function copiarLinkUTM(link) {
  navigator.clipboard.writeText(link).then(() => {
    alert("✅ Link copiado para a área de transferência!");
  });
}

function abrirLinkUTM(link) {
  window.open(link, "_blank");
}

// Inicializar quando a aba de Settings é aberta
window.addEventListener("load", () => {
  const settingsButton = document.querySelector('[href="#settings"]');
  if (settingsButton) {
    settingsButton.addEventListener("click", () => {
      setTimeout(() => carregarCampanhasUTM(), 100);
    });
  }
});
