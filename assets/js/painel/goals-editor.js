// Modal de Edição de Metas
document.addEventListener("DOMContentLoaded", function () {
  // Verificar se é admin e mostrar botão
  checkIfAdmin();

  // Botão Editar Metas
  document
    .getElementById("edit-goals-btn")
    ?.addEventListener("click", abrirModalEditarMetas);
});

function checkIfAdmin() {
  // Buscar informações do usuário
  fetch("/api/v1/check_login.php", { credentials: "same-origin" })
    .then((r) => r.json())
    .then((res) => {
      if (res.isAdmin) {
        document.getElementById("edit-goals-btn").classList.remove("hidden");
      }
    })
    .catch(() => {
      // Se erro, não mostra botão
    });
}

function abrirModalEditarMetas() {
  const html = `
        <div class="fixed bg-black bg-opacity-50 flex items-center justify-center" id="modal-editar-metas" style="z-index: 1040; left: 280px; right: 0; top: 0; bottom: 0;">
            <div class="bg-white rounded-lg p-6 max-w-lg w-full mx-4 overflow-y-auto" style="max-height:80vh;">
                <h2 class="text-2xl font-bold mb-6">Editar Metas e Objetivos</h2>
                
                <div id="goals-list" class="space-y-6 mb-6">
                    <!-- Será preenchido dinamicamente -->
                </div>
                
                <div class="flex gap-3 justify-end">
                    <button onclick="document.getElementById('modal-editar-metas').remove()" 
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button onclick="salvarTodasMetas()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    `;

  document.body.insertAdjacentHTML("beforeend", html);

  // Fechar modal ao clicar fora (no fundo)
  const modal = document.getElementById("modal-editar-metas");
  modal.addEventListener("click", function (e) {
    if (e.target === modal) {
      modal.remove();
    }
  });

  carregarMetasParaEdicao();
}

async function carregarMetasParaEdicao() {
  try {
    const res = await fetch("/api/v1/goals/getGoalsConfig.php", {
      credentials: "same-origin",
    });
    const result = await res.json();

    if (!result.success) {
      alert("Erro ao carregar metas");
      return;
    }

    // Ordenar conforme a sequência desejada
    const ordem = ["leads", "sales", "revenue", "conversion", "properties"];
    const goalsOrdenados = ordem
      .map((goalId) => result.data.find((goal) => goal.goal_id === goalId))
      .filter((goal) => goal !== undefined);

    const goalsList = document.getElementById("goals-list");
    goalsList.innerHTML = goalsOrdenados
      .map(
        (goal) => `
            <div class="border border-gray-300 rounded-lg p-4 mb-4" data-goal-id="${goal.goal_id}">
                <h3 class="text-base font-semibold mb-4 text-gray-800">${goal.goal_name}</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ${goal.goal_id === "revenue" ? "Valor Alvo (R$)" : "Quantidade Alvo"}
                        </label>
                        <input type="number" 
                            id="input-target-${goal.goal_id}"
                            step="${goal.goal_id === "revenue" ? "0.01" : "1"}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="${goal.target_value}"
                            data-goal-id="${goal.goal_id}">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Período (dias)</label>
                        <input type="number" 
                            id="input-period-${goal.goal_id}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="${goal.period_days}"
                            data-goal-id="${goal.goal_id}"
                            min="1">
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" 
                            id="input-active-${goal.goal_id}"
                            class="w-4 h-4" 
                            ${goal.is_active ? "checked" : ""}
                            data-goal-id="${goal.goal_id}">
                        <span class="text-sm text-gray-600">Meta ativa</span>
                    </label>
                </div>
            </div>
        `,
      )
      .join("");
  } catch (e) {
    console.error("Erro ao carregar metas:", e);
    alert("Erro ao carregar metas");
  }
}

async function salvarTodasMetas() {
  const modal = document.getElementById("modal-editar-metas");
  const goals = ["leads", "revenue", "sales", "conversion", "properties"];
  let sucessos = 0;
  let skipped = 0;

  for (const goalId of goals) {
    const targetInput = document.getElementById(`input-target-${goalId}`);
    const periodInput = document.getElementById(`input-period-${goalId}`);
    const activeInput = document.getElementById(`input-active-${goalId}`);

    if (!targetInput || !periodInput || !activeInput) {
      // Apenas warning, não é erro crítico
      console.warn(`Inputs não encontrados para goal: ${goalId} (será pulado)`);
      skipped++;
      continue;
    }

    try {
      const res = await fetch("/api/v1/goals/getGoalsConfig.php", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          goal_id: goalId,
          target_value: parseFloat(targetInput.value),
          period_days: parseInt(periodInput.value),
          is_active: activeInput.checked ? 1 : 0,
        }),
      });

      const result = await res.json();
      if (result.success) {
        sucessos++;
      } else {
        console.error("Erro na resposta para goal:", goalId, result);
      }
    } catch (e) {
      console.error("Erro ao salvar meta:", e);
    }
  }

  if (sucessos > 0) {
    alert(`✅ ${sucessos} meta(s) salva(s) com sucesso!`);
    modal.remove();

    // Recarregar metas no painel
    if (typeof GoalsAndTargets !== "undefined") {
      GoalsAndTargets.init();
    }
  } else {
    alert("⚠ Algumas metas foram salvas, mas houve erros");
  }
}
