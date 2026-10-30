/**
 * Pipeline Kanban Board
 * Gerencia o drag-and-drop de items entre estágios
 */

let stages = [];
let items = [];
let sortables = [];

// Função para inicializar o pipeline quando a aba é aberta
async function initializePipeline() {
  try {
    // Detectar qual container usar (relatórios ou principal)
    const loadingMessage =
      document.getElementById("loadingMessage-relatorios") ||
      document.getElementById("loadingMessage");
    const container =
      document.getElementById("pipelineContainer-relatorios") ||
      document.getElementById("pipelineContainer");

    if (loadingMessage) loadingMessage.style.display = "block";
    if (container) container.style.display = "none";

    await carregarEstagios();
    await carregarItems();
    renderizarEstagios(loadingMessage, container);
    inicializarDragDrop();

    if (loadingMessage) loadingMessage.style.display = "none";
    if (container) {
      container.style.display = "grid";
    }
  } catch (error) {
    console.error("Erro ao inicializar pipeline:", error);
    const loadingMessage =
      document.getElementById("loadingMessage-relatorios") ||
      document.getElementById("loadingMessage");
    const container =
      document.getElementById("pipelineContainer-relatorios") ||
      document.getElementById("pipelineContainer");
    if (loadingMessage) loadingMessage.style.display = "none";
    if (container) container.style.display = "block";
    alert("Erro ao carregar pipeline: " + error.message);
  }
}

document.addEventListener("DOMContentLoaded", async () => {
  try {
    // Apenas carrega se a aba pipeline estiver visível
    const pipelineTab = document.getElementById("pipeline");
    if (pipelineTab && pipelineTab.style.display !== "none") {
      await initializePipeline();
    }
  } catch (error) {
    console.error("Erro ao inicializar pipeline:", error);
    alert("Erro ao carregar pipeline: " + error.message);
  }
});

/**
 * Carrega estágios do pipeline
 */
async function carregarEstagios() {
  try {
    const response = await fetch("/api/v1/pipeline/getStages.php", {
      credentials: "include",
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (!data.success) {
      console.error("Erro ao carregar estágios:", data.error);
      throw new Error(data.error || "Erro desconhecido");
    }

    stages = data.data;
  } catch (error) {
    console.error("Erro em carregarEstagios:", error);
    throw error;
  }
}

/**
 * Carrega items do pipeline
 */
async function carregarItems() {
  try {
    const response = await fetch("/api/v1/pipeline/getItems.php", {
      credentials: "include",
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (!data.success) {
      console.error("Erro ao carregar items:", data.error);
      // Continuar mesmo se não tiver items
      items = [];
      return;
    }

    items = data.data;
  } catch (error) {
    console.error("Erro em carregarItems:", error);
    items = [];
  }
}

/**
 * Renderizar estágios no DOM
 */
function renderizarEstagios(loadingMessageParam, containerParam) {
  let container =
    containerParam || document.getElementById("pipelineContainer");
  let loadingMessage =
    loadingMessageParam || document.getElementById("loadingMessage");

  // Se não encontrou, tentar os IDs de relatórios
  if (!container) {
    container = document.getElementById("pipelineContainer-relatorios");
  }
  if (!loadingMessage) {
    loadingMessage = document.getElementById("loadingMessage-relatorios");
  }

  if (!container) {
    console.error("Container não encontrado!");
    return;
  }

  container.innerHTML = "";

  if (stages.length === 0) {
    container.innerHTML =
      '<div class="alert alert-warning">Nenhum estágio encontrado</div>';
    if (loadingMessage) loadingMessage.style.display = "none";
    container.style.display = "block";
    return;
  }

  stages.forEach((stage) => {
    const stageDiv = document.createElement("div");
    stageDiv.className = "pipeline-stage";
    stageDiv.style.setProperty("--stage-color", stage.color);
    stageDiv.dataset.stageId = stage.id;

    // Contar items neste estágio
    const itemsCount = items.filter((i) => i.stage_id === stage.id).length;

    stageDiv.innerHTML = `
            <div class="pipeline-stage-header">
                <div>
                    <div class="pipeline-stage-title">${stage.name}</div>
                    <small class="text-muted">${stage.description || ""}</small>
                </div>
                <div class="pipeline-stage-count">${itemsCount}</div>
            </div>
            <div class="pipeline-items" id="stage-${stage.id}" data-stage-id="${stage.id}">
                ${renderizarItems(stage.id)}
            </div>
            <div class="add-item-btn">
                <button class="btn btn-sm btn-outline-primary" onclick="abrirFormNovoItem(${stage.id})">
                    <i class="fas fa-plus"></i> Adicionar
                </button>
            </div>
        `;

    container.appendChild(stageDiv);
  });

  // Mostrar container e esconder mensagem de carregamento
  if (loadingMessage) loadingMessage.style.display = "none";
  container.style.display = "grid";
}

/**
 * Renderizar items de um estágio
 */
function renderizarItems(stageId) {
  const stageItems = items.filter((i) => i.stage_id === stageId);

  if (stageItems.length === 0) {
    return '<div class="pipeline-empty"><i class="fas fa-inbox"></i><br>Nenhum item</div>';
  }

  return stageItems
    .map(
      (item) => `
        <div class="pipeline-item" data-id="${item.id}" onclick="abrirDetalhesItem(event, ${item.id})">
            <div class="item-title">${item.title}</div>
            <div class="item-subtitle">${item.subtitle || ""}</div>
            ${item.value > 0 ? `<div class="item-value">R$ ${formatarPreco(item.value)}</div>` : ""}
            ${item.expected_close_date ? `<div class="item-date"><i class="fas fa-calendar"></i> ${formatarData(item.expected_close_date)}</div>` : ""}
        </div>
    `,
    )
    .join("");
}

/**
 * Inicializar Sortable (drag-and-drop)
 */
function inicializarDragDrop() {
  // Limpar sortables antigos
  sortables.forEach((s) => s.destroy());
  sortables = [];

  stages.forEach((stage) => {
    const container = document.getElementById(`stage-${stage.id}`);
    if (!container) return;

    const sortable = Sortable.create(container, {
      group: "pipeline-items",
      animation: 150,
      ghostClass: "dragging",
      onEnd: async (evt) => {
        await moverItem(
          parseInt(evt.item.dataset.id),
          parseInt(evt.to.dataset.stageId),
        );
      },
    });

    sortables.push(sortable);
  });
}

/**
 * Mover item para novo estágio
 */
async function moverItem(pipelineId, toStageId) {
  try {
    const response = await fetch("/api/v1/pipeline/moveItem.php", {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        pipeline_id: pipelineId,
        to_stage_id: toStageId,
      }),
    });

    const data = await response.json();

    if (data.success) {
      // Atualizar items locais
      const item = items.find((i) => i.id === pipelineId);
      if (item) {
        item.stage_id = toStageId;
        const stage = stages.find((s) => s.id === toStageId);
        if (stage) {
          item.stage_name = stage.name;
          item.stage_color = stage.color;
        }
      }

      // Recarregar estágios
      renderizarEstagios();
      inicializarDragDrop();

      mostrarNotificacao("Item movido com sucesso", "success");
    } else {
      console.error("Erro:", data.error);
      mostrarNotificacao("Erro ao mover item", "danger");
    }
  } catch (error) {
    console.error("Erro:", error);
    mostrarNotificacao("Erro ao mover item", "danger");
  }
}

/**
 * Abrir modal com detalhes do item
 */
function abrirDetalhesItem(event, itemId) {
  event.preventDefault();
  const item = items.find((i) => i.id === itemId);
  if (!item) return;

  const modal = document.getElementById("itemModal");
  document.getElementById("itemModalTitle").textContent = item.title;

  // Verificar se o lead pertence ao usuário logado
  const pertenceAoUsuario = item.assigned_user_id == window.currentUserId;

  // Botão de excluir apenas para admins
  const deleteButtonHTML = isAdmin
    ? `
        <div class="mt-4">
            <button class="btn btn-sm btn-danger" onclick="deletarItem(${item.id})">
                <i class="fas fa-trash"></i> Excluir
            </button>
        </div>
    `
    : "";

  // Mostrar notas com opção de editar se pertence ao usuário
  const valorFormatado = new Intl.NumberFormat("pt-BR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(item.value || 0);

  const valorHTML = pertenceAoUsuario
    ? `
        <div class="mb-3">
            <label class="form-label"><strong>Valor:</strong></label>
            <input type="text" id="itemValue" class="form-control" value="${valorFormatado}" placeholder="1.989.000,00">
        </div>
    `
    : `<p><strong>Valor:</strong> R$ ${valorFormatado}</p>`;

  const notasHTML = pertenceAoUsuario
    ? `
        <div class="mb-3">
            <label class="form-label"><strong>Notas:</strong></label>
            <textarea id="itemNotes" class="form-control" rows="3">${item.notes || ""}</textarea>
        </div>
        <button class="btn btn-primary btn-sm" onclick="salvarNotasItem(${item.id})">
            <i class="fas fa-save"></i> Salvar Alterações
        </button>
    `
    : item.notes
      ? `<p><strong>Notas:</strong><br>${item.notes}</p>`
      : "";

  const contatoTexto = [item.lead_email, item.lead_phone]
    .filter(Boolean)
    .join(" / ");
  const contatoHTML = contatoTexto
    ? `<span class="item-detail-pill"><strong>Contato:</strong> ${contatoTexto}</span>`
    : "";
  const tipoHTML = `<span class="item-detail-pill"><strong>Tipo:</strong> ${item.type === "lead" ? "Lead" : "Imóvel"}</span>`;
  const estagioHTML = `<span class="item-detail-pill"><strong>Estágio:</strong> ${item.stage_name}</span>`;
  const valorDetailHTML = `<span class="item-detail-pill"><strong>Valor:</strong> ${item.value > 0 ? `R$ ${formatarPreco(item.value)}` : "R$ 0,00"}</span>`;
  const dataHTML = item.expected_close_date
    ? `<span class="item-detail-pill"><strong>Data prevista:</strong> ${formatarData(item.expected_close_date)}</span>`
    : "";

  document.getElementById("itemModalBody").innerHTML = `
        <div class="item-details-row">
            ${tipoHTML}
            ${estagioHTML}
            ${valorDetailHTML}
            ${dataHTML}
            ${contatoHTML}
        </div>
        <div style="margin-top: 16px;">
            ${notasHTML}
        </div>
        ${deleteButtonHTML}
    `;

  new bootstrap.Modal(modal).show();
}

/**
 * Deletar item do pipeline
 */
async function deletarItem(itemId) {
  if (!confirm("Tem certeza que deseja deletar este item?")) {
    return;
  }

  try {
    const response = await fetch("/api/v1/pipeline/deleteItem.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        pipeline_id: itemId,
      }),
    });

    const data = await response.json();

    if (data.success) {
      // Remover do array local
      items = items.filter((i) => i.id !== itemId);

      // Fechar modal
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("itemModal"),
      );
      if (modal) modal.hide();

      // Recarregar renderização
      renderizarEstagios();
      inicializarDragDrop();

      mostrarNotificacao("Item deletado com sucesso", "success");
    } else {
      alert("Erro: " + (data.error || "Erro ao deletar item"));
    }
  } catch (error) {
    console.error("Erro:", error);
    alert("Erro ao deletar item: " + error.message);
  }
}

/**
 * Abrir formulário para adicionar novo item
 */
function abrirFormNovoItem(stageId) {
  // Remover foco de qualquer elemento antes de abrir o modal
  if (document.activeElement && document.activeElement !== document.body) {
    document.activeElement.blur();
  }

  const modal = document.createElement("div");
  modal.className = "modal fade";
  modal.id = "dynamicAddItemModal";
  modal.tabIndex = "-1";
  modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Adicionar Item ao Pipeline</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addItemForm">
                        <div class="mb-3">
                            <label class="form-label">Tipo</label>
                            <select id="addItemType" class="form-select" onchange="carregarOptions()">
                                <option value="">Selecione...</option>
                                <option value="lead">Lead</option>
                                <option value="property">Imóvel</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Selecionar Item</label>
                            <select id="addItemSelect" class="form-select" required onchange="atualizarValorDoItem()">
                                <option value="">Carregando...</option>
                            </select>
                        </div>

                        <div class="mb-3" id="leadContainer" style="display: none;">
                            <label class="form-label">Lead Associado (Opcional)</label>
                            <select id="associatedLead" class="form-select">
                                <option value="">Nenhum lead selecionado</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Valor da Negociação (R$)</label>
                            <input type="text" id="addItemValue" class="form-control" placeholder="1.989.000,00" value="0,00">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Data Prevista de Fechamento</label>
                            <input type="date" id="addItemDate" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea id="addItemNotes" class="form-control" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="salvarNovoItem(${stageId})">Adicionar</button>
                </div>
            </div>
        </div>
    `;

  document.body.appendChild(modal);

  // Pequeno delay para permitir que o DOM se atualize
  setTimeout(() => {
    try {
      const bs = new bootstrap.Modal(modal, {
        focus: false, // Evita que Bootstrap tente gerenciar foco automaticamente
      });
      bs.show();
    } catch (error) {
      console.error("Erro ao exibir modal:", error);
    }
  }, 10);

  // Remover modal do DOM quando fechar
  modal.addEventListener("hidden.bs.modal", () => {
    if (document.activeElement && modal.contains(document.activeElement)) {
      document.activeElement.blur();
    }
    modal.remove();
  });
}

/**
 * Carregar options (leads ou propriedades)
 */
async function carregarOptions() {
  const type = document.getElementById("addItemType").value;
  const select = document.getElementById("addItemSelect");
  const leadContainer = document.getElementById("leadContainer");

  if (!type) {
    select.innerHTML = '<option value="">Selecione um tipo primeiro</option>';
    leadContainer.style.display = "none";
    return;
  }

  select.innerHTML = '<option value="">Carregando...</option>';

  try {
    const response = await fetch(
      `/api/v1/pipeline/getOptions.php?type=${type}&limit=100`,
      {
        credentials: "include",
      },
    );
    const data = await response.json();

    if (data.success && Array.isArray(data.data)) {
      const options = data.data;

      if (options.length > 0) {
        select.innerHTML = `<option value="">Selecione um ${type === "lead" ? "lead" : "imóvel"}...</option>`;
        options.forEach((option) => {
          const optionEl = document.createElement("option");
          optionEl.value = option.id;
          optionEl.dataset.value = option.value || 0;
          optionEl.textContent = option.text;
          select.appendChild(optionEl);
        });

        // Se houver apenas uma opção, selecionar automaticamente e preencher o valor.
        if (options.length === 1) {
          select.selectedIndex = 1;
        }

        setTimeout(() => {
          atualizarValorDoItem();
          select.dispatchEvent(new Event("change", { bubbles: true }));
        }, 50);
      } else {
        select.innerHTML = `<option value="">Nenhum ${type === "lead" ? "lead" : "imóvel"} disponível</option>`;
      }

      // Se for imóvel, carregar leads também
      if (type === "property") {
        carregarLeadsAssociados();
      } else {
        leadContainer.style.display = "none";
      }
    } else {
      console.error("Erro na resposta:", data.error);
      select.innerHTML = '<option value="">Erro ao carregar</option>';
      leadContainer.style.display = "none";
    }
  } catch (error) {
    console.error("Erro ao carregar opções:", error);
    select.innerHTML = '<option value="">Erro ao carregar</option>';
    leadContainer.style.display = "none";
  }
}

/**
 * Carregar leads associados quando tipo for "property"
 */
async function carregarLeadsAssociados() {
  const leadContainer = document.getElementById("leadContainer");
  const leadSelect = document.getElementById("associatedLead");

  leadContainer.style.display = "block";
  leadSelect.innerHTML = '<option value="">Carregando leads...</option>';

  try {
    const response = await fetch(
      `/api/v1/pipeline/getOptions.php?type=lead&limit=100`,
      {
        credentials: "include",
      },
    );
    const data = await response.json();

    if (data.success && Array.isArray(data.data)) {
      const leads = data.data;

      leadSelect.innerHTML =
        '<option value="">Nenhum lead selecionado</option>';
      leads.forEach((lead) => {
        const option = document.createElement("option");
        option.value = lead.id;
        option.textContent = lead.text;
        leadSelect.appendChild(option);
      });
    } else {
      leadSelect.innerHTML = '<option value="">Erro ao carregar leads</option>';
    }
  } catch (error) {
    console.error("Erro ao carregar leads:", error);
    leadSelect.innerHTML = '<option value="">Erro ao carregar leads</option>';
  }
}

/**
 * Normaliza valores numéricos em formato BR para JS
 */
function normalizarValorDecimal(valor) {
  if (valor === null || valor === undefined) {
    return 0;
  }

  if (typeof valor !== "string") {
    return Number(valor) || 0;
  }

  const texto = valor.toString().trim();
  if (texto === "") {
    return 0;
  }

  const normalizado = texto.replace(/\./g, "").replace(/,/g, ".");
  return parseFloat(normalizado) || 0;
}

/**
 * Atualizar valor quando seleciona um item
 */
function atualizarValorDoItem() {
  const addModal = document.getElementById("dynamicAddItemModal");
  const select = addModal
    ? addModal.querySelector("#addItemSelect")
    : document.getElementById("itemSelect");
  const valueInput = addModal
    ? addModal.querySelector("#addItemValue")
    : document.getElementById("itemValue");

  if (!select || !valueInput) {
    return;
  }

  const selectedOption = select.options[select.selectedIndex];

  if (selectedOption && selectedOption.dataset.value) {
    const numericValue = normalizarValorDecimal(selectedOption.dataset.value);
    valueInput.value = formatarPreco(numericValue);
  } else {
    valueInput.value = formatarPreco(0);
  }
}

/**
 * Salvar novo item no pipeline
 */
async function salvarNovoItem(stageId) {
  const type = document.getElementById("addItemType").value;
  const itemId = document.getElementById("addItemSelect").value;
  const value = normalizarValorDecimal(
    document.getElementById("addItemValue").value,
  );
  const date = document.getElementById("addItemDate").value;
  const notes = document.getElementById("addItemNotes").value;
  const associatedLead =
    document.getElementById("associatedLead")?.value || null;

  if (!type || !itemId) {
    alert("Selecione um lead ou imóvel");
    return;
  }

  try {
    const payload = {
      [type === "lead" ? "lead_id" : "property_id"]: parseInt(itemId),
      pipeline_stage_id: stageId,
      value: value,
      expected_close_date: date || null,
      notes: notes || null,
    };

    // Se for imóvel e houver lead associado, adicionar ao payload
    if (type === "property" && associatedLead) {
      payload.lead_id = parseInt(associatedLead);
    }

    const response = await fetch("/api/v1/pipeline/createItem.php", {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (data.success) {
      // Fechar modal
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("dynamicAddItemModal"),
      );
      if (modal) modal.hide();

      // Recarregar pipeline
      await carregarItems();
      renderizarEstagios();
      inicializarDragDrop();

      mostrarNotificacao("Item adicionado ao pipeline!", "success");
    } else {
      alert("Erro: " + (data.error || "Erro desconhecido"));
    }
  } catch (error) {
    console.error("Erro:", error);
    alert("Erro ao adicionar item: " + error.message);
  }
}

/**
 * Utilitários
 */
function formatarPreco(valor) {
  return new Intl.NumberFormat("pt-BR", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(valor);
}

function formatarData(data) {
  return new Intl.DateTimeFormat("pt-BR").format(new Date(data));
}

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

/**
 * Salvar notas do item do pipeline
 */
async function salvarNotasItem(itemId) {
  const novasNotas = document.getElementById("itemNotes").value;
  const novoValor = document.getElementById("itemValue")
    ? normalizarValorDecimal(document.getElementById("itemValue").value)
    : null;

  if (!window.currentUserId) {
    mostrarNotificacao("Você precisa estar logado para salvar notas", "danger");
    return;
  }

  const payload = {
    pipeline_id: itemId,
    notes: novasNotas,
  };

  if (novoValor !== null) {
    payload.value = novoValor;
  }

  try {
    const response = await fetch("/api/v1/pipeline/updateItem.php", {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (data.success) {
      // Atualizar o item no array local
      const item = items.find((i) => i.id === itemId);
      if (item) {
        item.notes = novasNotas;
        if (novoValor !== null) {
          item.value = novoValor;
        }
      }

      // Re-renderizar para refletir alteração de valor
      renderizarEstagios();
      inicializarDragDrop();

      // Fechar modal
      const modal = bootstrap.Modal.getInstance(
        document.getElementById("itemModal"),
      );
      if (modal) modal.hide();

      mostrarNotificacao("Notas atualizadas com sucesso", "success");
    } else {
      mostrarNotificacao(
        "Erro: " + (data.error || data.message || "Erro ao atualizar notas"),
        "danger",
      );
    }
  } catch (error) {
    console.error("Erro:", error);
    mostrarNotificacao("Erro ao salvar notas: " + error.message, "danger");
  }
}

/**
 * Abre modal para adicionar novo item ao pipeline
 */
function abrirModalAdicionarAoPipeline() {
  // Aqui você pode criar uma modal para adicionar um novo item
  // Por enquanto, redireciona para o primeiro estágio
  if (stages.length > 0) {
    abrirFormNovoItem(stages[0].id);
  } else {
    alert("Nenhum estágio disponível");
  }
}
