/**
 * Módulo de Comissões
 * Gerencia visualização, aprovação e pagamento de comissões
 */

let commissionsState = {
    currentFilter: 'todos',
    currentPage: 1,
    selectedCommissions: new Set(),
    commissions: [],
};

document.addEventListener('DOMContentLoaded', function () {
    inicializarComissoes();

    // Fechar modal ao clicar fora dele
    const modal = document.getElementById('modal-detalhes-comissao');
    if (modal) {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                fecharModalDetalhesComissao();
            }
        });
    }
});

/**
 * Inicializa eventos da aba Comissões
 */
function inicializarComissoes() {
    // Carregar comissões imediatamente e também ao entrar na aba
    carregarVendedores();
    carregarComissoes(); // Carregamento inicial

    const abaComissoes = document.querySelector('[href="#comissoes"]');
    if (abaComissoes) {
        abaComissoes.addEventListener('click', () => {
            carregarVendedores();
            carregarComissoes();
        });
    }

    // Event listener para "selecionar todos"
    const selectAllCheckbox = document.getElementById('comissoes-select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.comissao-checkbox');
            checkboxes.forEach((checkbox) => {
                checkbox.checked = this.checked;
                const commissionId = parseInt(checkbox.value, 10);
                if (this.checked) {
                    commissionsState.selectedCommissions.add(commissionId);
                } else {
                    commissionsState.selectedCommissions.delete(commissionId);
                }
            });
            atualizarBotoesAcao();
        });
    }

    // Carregar vendedores na inicialização
    carregarVendedores();

    // Filtro de status
    const statusFilter = document.getElementById('comissoes-status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            commissionsState.currentPage = 1;
            commissionsState.selectedCommissions.clear();
            atualizarBotoesAcao();
            carregarComissoes();
        });
    }

    // Filtro de período
    const periodoFilter = document.getElementById('comissoes-periodo');
    if (periodoFilter) {
        periodoFilter.addEventListener('change', () => {
            commissionsState.currentPage = 1;
            commissionsState.selectedCommissions.clear();
            atualizarBotoesAcao();
            carregarComissoes();
        });
    }

    // Filtro de vendedor
    const vendedorFilter = document.getElementById('comissoes-vendedor');
    if (vendedorFilter) {
        vendedorFilter.addEventListener('change', () => {
            commissionsState.currentPage = 1;
            commissionsState.selectedCommissions.clear();
            atualizarBotoesAcao();
            carregarComissoes();
        });
    }

    // Botão aprovar selecionadas
    const aprovarBtn = document.getElementById('btn-aprovar-comissoes');
    if (aprovarBtn) {
        aprovarBtn.addEventListener('click', aprovarComissoesSelecionadas);
    }

    // Botão pagar selecionadas
    const pagarBtn = document.getElementById('btn-pagar-comissoes');
    if (pagarBtn) {
        pagarBtn.addEventListener('click', pagarComissoesSelecionadas);
    }

    // Botão exportar
    const exportarBtn = document.getElementById('btn-exportar-comissoes');
    if (exportarBtn) {
        exportarBtn.addEventListener('click', exportarComissoes);
    }
}

/**
 * Carrega lista de vendedores/corretores para o dropdown
 */
async function carregarVendedores() {
    try {
        const response = await fetch('/api/v1/users/index.php?role=user&limit=1000');

        // Verificar se a resposta é OK
        if (!response.ok) {
            return;
        }

        // Tentar fazer parse do JSON
        const result = await response.json();

        const select = document.getElementById('comissoes-vendedor');
        if (!select) {
            return;
        }

        if (!result.success || !result.data) {
            return;
        }

        // Manter a opção padrão
        const defaultOption = select.querySelector('option[value=""]');
        select.innerHTML = '';
        if (defaultOption) {
            select.appendChild(defaultOption);
        } else {
            select.innerHTML = '<option value="">Todos os Corretores</option>';
        }

        // Adicionar vendedores
        result.data.forEach((user) => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = user.name || user.username;
            select.appendChild(option);
        });
    } catch (e) {}
}

/**
 * Carrega lista de comissões com filtros
 */
async function carregarComissoes() {
    try {
        const status = document.getElementById('comissoes-status-filter')?.value || 'todos';
        const periodo = document.getElementById('comissoes-periodo')?.value || 'mes';
        const vendedorId = document.getElementById('comissoes-vendedor')?.value || '';

        let url = `/api/v1/financeiro/getCommissions.php?page=${commissionsState.currentPage}&limit=20`;

        if (status && status !== 'todos') {
            url += `&status=${status}`;
        }

        if (vendedorId) {
            url += `&user_id=${vendedorId}`;
        }

        // Calcular datas baseado no período
        const hoje = new Date();
        let startDate = new Date();

        if (periodo === 'mes') {
            startDate.setDate(1);
        } else if (periodo === '30dias') {
            startDate.setDate(hoje.getDate() - 30);
        } else if (periodo === '90dias') {
            startDate.setDate(hoje.getDate() - 90);
        } else if (periodo === 'ano') {
            startDate.setFullYear(hoje.getFullYear(), 0, 1);
        }

        if (periodo !== 'todos') {
            url += `&start_date=${startDate.toISOString().split('T')[0]}`;
            url += `&end_date=${hoje.toISOString().split('T')[0]}`;
        }

        const res = await fetch(url);

        // Tratamento de erros HTTP
        if (res.status === 401) {
            mostrarAvisoErro('Sessão expirada. Faça login novamente.', 'error');
            window.location.href = '/login.php';
            return;
        }
        if (res.status === 403) {
            mostrarAvisoErro('Você não tem permissão para acessar este recurso.', 'error');
            return;
        }
        if (!res.ok) {
            mostrarAvisoErro(`Erro na requisição: ${res.status} ${res.statusText}`, 'error');
            return;
        }

        const result = await res.json();

        if (result.success) {
            // A API retorna result.result.data, não result.data
            const data = result.data || (result.result && result.result.data) || [];
            const pagination =
                result.pagination || (result.result && result.result.pagination) || {};

            commissionsState.commissions = data;
            renderizarComissoes(data);
            renderizarPaginacao(pagination);
            carregarResumoComissoes();
        } else {
            mostrarAvisoErro(result.error || 'Erro ao carregar comissões', 'warning');
        }
    } catch (e) {
        mostrarAvisoErro('Erro ao carregar comissões: ' + e.message, 'error');
    }
}

/**
 * Renderiza tabela de comissões
 */
function renderizarComissoes(comissoes) {
    const tbody = document.getElementById('comissoes-tbody');
    if (!tbody) {
        return;
    }

    if (!comissoes || comissoes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-gray-500">Nenhuma comissão encontrada</td></tr>`;
        return;
    }

    tbody.innerHTML = comissoes
        .map(
            (c) => `
        <tr class="border-b hover:bg-gray-50">
            <td class="px-6 py-4">
                ${c.status === 'paga' || c.status === 'aprovada' ? '' : `<input type="checkbox" class="comissao-checkbox" value="${c.id}" onchange="toggleComissaoSelection(${c.id})">`}
            </td>
            <td class="px-6 py-4">
                <div class="font-medium text-sm">${c.seller_name || 'N/A'}</div>
                <div class="text-xs text-gray-500">${c.seller_email || ''}</div>
            </td>
            <td class="px-6 py-4 text-sm">${c.property_title || 'N/A'}</td>
            <td class="px-6 py-4 text-right">
                <div class="font-medium">R$ ${parseFloat(c.sale_value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
            </td>
            <td class="px-6 py-4 text-center">${c.commission_percentage}%</td>
            <td class="px-6 py-4 text-right font-bold text-blue-600">
                R$ ${parseFloat(c.commission_value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
            </td>
            <td class="px-6 py-4">
                <span class="px-2 py-1 rounded-full text-xs font-semibold ${getStatusBadgeClass(c.status)}">
                    ${translateStatus(c.status)}
                </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">
                ${new Date(c.created_at).toLocaleDateString('pt-BR')}
            </td>
            <td class="px-6 py-4 text-right">
                <div class="flex gap-2 justify-end">
                    ${c.status === 'pendente' ? `<button onclick="abrirAprovaComissao(${c.id})" class="px-2 py-1 bg-yellow-500 text-white rounded text-xs hover:bg-yellow-600">Aprovar</button>` : ''}
                    ${c.status === 'aprovada' ? `<button onclick="pagarComissaoIndividual(${c.id})" class="px-2 py-1 bg-green-500 text-white rounded text-xs hover:bg-green-600">Pagar</button>` : ''}
                    <button onclick="abrirDetalhesComissao(${c.id})" class="px-2 py-1 bg-blue-500 text-white rounded text-xs hover:bg-blue-600">Ver</button>
                    <button onclick="deletarComissao(${c.id}, '${c.property_title}')" class="px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600" title="Deletar comissão">🗑️</button>
                </div>
            </td>
        </tr>
    `
        )
        .join('');
}

/**
 * Renderiza paginação
 */
function renderizarPaginacao(pagination) {
    const container = document.getElementById('comissoes-paginacao');
    if (!container || !pagination) return;

    let html = `<p class="text-sm text-gray-600">Página ${pagination.page} de ${pagination.total_pages} (Total: ${pagination.total_records})</p>`;
    html += '<div class="flex gap-2">';

    if (pagination.page > 1) {
        html += `<button onclick="irParaPaginaComissoes(1)" class="px-3 py-1 border rounded hover:bg-gray-100">Primeira</button>`;
        html += `<button onclick="irParaPaginaComissoes(${pagination.page - 1})" class="px-3 py-1 border rounded hover:bg-gray-100">Anterior</button>`;
    }

    if (pagination.page < pagination.total_pages) {
        html += `<button onclick="irParaPaginaComissoes(${pagination.page + 1})" class="px-3 py-1 border rounded hover:bg-gray-100">Próxima</button>`;
        html += `<button onclick="irParaPaginaComissoes(${pagination.total_pages})" class="px-3 py-1 border rounded hover:bg-gray-100">Última</button>`;
    }

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Toggle seleção de comissão
 */
function toggleComissaoSelection(commissionId) {
    // Converte para número para garantir consistência
    const id = parseInt(commissionId, 10);

    if (commissionsState.selectedCommissions.has(id)) {
        commissionsState.selectedCommissions.delete(id);
    } else {
        commissionsState.selectedCommissions.add(id);
    }

    // Verificar se todos estão selecionados para atualizar o checkbox "select all"
    const checkboxes = document.querySelectorAll('.comissao-checkbox');
    const selectAllCheckbox = document.getElementById('comissoes-select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked =
            checkboxes.length > 0 && Array.from(checkboxes).every((cb) => cb.checked);
    }

    atualizarBotoesAcao();

    // Debug temporário
    //console.log('Selected commissions:', commissionsState.selectedCommissions, 'Size:', commissionsState.selectedCommissions.size);
}

/**
 * Atualizar estado dos botões
 */
function atualizarBotoesAcao() {
    const count = commissionsState.selectedCommissions.size;
    const aprovarBtn = document.getElementById('btn-aprovar-comissoes');
    const pagarBtn = document.getElementById('btn-pagar-comissoes');

    if (aprovarBtn) {
        aprovarBtn.disabled = count === 0;
        aprovarBtn.textContent = `✓ Aprovar (${count})`;
    }
    if (pagarBtn) {
        pagarBtn.disabled = count === 0;
        pagarBtn.textContent = `💳 Pagar (${count})`;
    }
}

/**
 * Aprovar comissões selecionadas
 */
async function aprovarComissoesSelecionadas() {
    if (commissionsState.selectedCommissions.size === 0) {
        alert('Selecione pelo menos uma comissão');
        return;
    }

    const notes = prompt('Observações (opcional):');
    if (notes === null) return; // Cancelado

    let successCount = 0;
    let errorCount = 0;

    for (const commissionId of commissionsState.selectedCommissions) {
        try {
            const res = await fetch('/api/v1/financeiro/approveCommission.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    commission_id: commissionId,
                    notes: notes,
                }),
            });

            const result = await res.json();
            if (result.success) {
                successCount++;
            } else {
                errorCount++;
                alert(`Erro ao aprovar comissão ${commissionId}: ${result.error}`);
            }
        } catch (e) {
            errorCount++;
        }
    }

    commissionsState.selectedCommissions.clear();

    if (successCount > 0) {
        alert(`${successCount} comissão(ões) aprovada(s) com sucesso!`);
        // Aguarda um pouco antes de recarregar para garantir que os dados foram atualizados no banco
        await new Promise((resolve) => setTimeout(resolve, 500));
        carregarComissoes();
    }
}

/**
 * Pagar comissões selecionadas
 */
async function pagarComissoesSelecionadas() {
    if (commissionsState.selectedCommissions.size === 0) {
        alert('Selecione pelo menos uma comissão');
        return;
    }

    const method = prompt('Método de pagamento (transferência/cheque/dinheiro):', 'transferência');
    if (method === null) return;

    let successCount = 0;
    let errorCount = 0;

    for (const commissionId of commissionsState.selectedCommissions) {
        try {
            const res = await fetch('/api/v1/financeiro/payCommission.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    commission_id: commissionId,
                    method: method,
                }),
            });

            const result = await res.json();
            if (result.success) {
                successCount++;
            } else {
                errorCount++;
                alert(`Erro ao pagar comissão ${commissionId}: ${result.error}`);
            }
        } catch (e) {
            errorCount++;
        }
    }

    commissionsState.selectedCommissions.clear();

    if (successCount > 0) {
        alert(`${successCount} comissão(ões) paga(s) com sucesso!`);
        // Aguarda um pouco antes de recarregar para garantir que os dados foram atualizados no banco
        await new Promise((resolve) => setTimeout(resolve, 500));
        carregarComissoes();
    }
}

/**
 * Pagar comissão individual
 */
async function pagarComissaoIndividual(commissionId) {
    const method = prompt('Método de pagamento:', 'transferência');
    if (method === null) return;

    try {
        const res = await fetch('/api/v1/financeiro/payCommission.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ commission_id: commissionId, method }),
        });

        const result = await res.json();
        if (result.success) {
            alert('Comissão paga com sucesso!');
            carregarComissoes();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (e) {}
}

/**
 * Abrir modal de aprovação
 */
function abrirAprovaComissao(commissionId) {
    const notes = prompt('Observações na aprovação:');
    if (notes === null) return;

    fetch('/api/v1/financeiro/approveCommission.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ commission_id: commissionId, notes }),
    })
        .then((r) => r.json())
        .then((result) => {
            if (result.success) {
                alert('Comissão aprovada!');
                carregarComissoes();
            }
        })
        .catch((e) => console.error('Erro:', e));
}

/**
 * Abrir modal de detalhes
 */
function abrirDetalhesComissao(commissionId) {
    const comissao = commissionsState.commissions.find((c) => c.id === commissionId);
    if (!comissao) return;

    const modal = document.getElementById('modal-detalhes-comissao');
    if (!modal) {
        return;
    }

    // Preencher dados do modal
    document.getElementById('detalhe-vendedor').textContent = comissao.seller_name || 'N/A';
    document.getElementById('detalhe-imovel').textContent = comissao.property_title || 'N/A';
    document.getElementById('detalhe-valor-venda').textContent =
        `R$ ${parseFloat(comissao.sale_value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
    document.getElementById('detalhe-percentual').textContent =
        `${comissao.commission_percentage}%`;
    document.getElementById('detalhe-comissao').textContent =
        `R$ ${parseFloat(comissao.commission_value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

    const statusElement = document.getElementById('detalhe-status');
    statusElement.textContent = translateStatus(comissao.status);
    statusElement.className = `text-lg font-semibold px-3 py-1 rounded-full ${getStatusBadgeClass(comissao.status)}`;

    document.getElementById('detalhe-data').textContent = new Date(
        comissao.created_at
    ).toLocaleDateString('pt-BR');

    // Preencher observações se houver
    const observacoesContainer = document.getElementById('detalhe-observacoes-container');
    if (comissao.approval_notes) {
        observacoesContainer.style.display = 'block';
        document.getElementById('detalhe-observacoes').textContent = comissao.approval_notes;
    } else {
        observacoesContainer.style.display = 'none';
    }

    // Mostrar modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

/**
 * Fechar modal de detalhes
 */
function fecharModalDetalhesComissao() {
    const modal = document.getElementById('modal-detalhes-comissao');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

/**
 * Carregar resumo de comissões
 */
async function carregarResumoComissoes() {
    try {
        const vendedorId = document.getElementById('comissoes-vendedor')?.value;
        const mes = new Date().toISOString().slice(0, 7);

        let url = `/api/v1/financeiro/getCommissionStats.php?start_date=${mes}-01`;

        // Se um vendedor específico foi selecionado, passar o user_id
        if (vendedorId && vendedorId !== '') {
            url += `&user_id=${vendedorId}`;
        }

        const res = await fetch(url);
        if (!res.ok) {
            return;
        }

        const result = await res.json();

        if (result.success && result.stats) {
            const stats = result.stats.by_status || {};

            document.getElementById('comissoes-pendentes-count').textContent =
                stats.pendente?.count || 0;
            document.getElementById('comissoes-pendentes-valor').textContent =
                `R$ ${(stats.pendente?.value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

            document.getElementById('comissoes-aprovadas-count').textContent =
                stats.aprovada?.count || 0;
            document.getElementById('comissoes-aprovadas-valor').textContent =
                `R$ ${(stats.aprovada?.value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;

            document.getElementById('comissoes-pagas-count').textContent = stats.paga?.count || 0;
            document.getElementById('comissoes-pagas-valor').textContent =
                `R$ ${(stats.paga?.value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
        }
    } catch (e) {}
}

/**
 * Exportar comissões para CSV
 */
function exportarComissoes() {
    if (commissionsState.commissions.length === 0) {
        alert('Nenhuma comissão para exportar');
        return;
    }

    let csv = 'Vendedor,Email,Imóvel,Valor Venda,Percentual,Comissão,Status,Data\n';
    commissionsState.commissions.forEach((c) => {
        csv += `"${c.seller_name}","${c.seller_email}","${c.property_title}",${c.sale_value},${c.commission_percentage}%,${c.commission_value},"${translateStatus(c.status)}","${new Date(c.created_at).toLocaleDateString('pt-BR')}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `comissoes_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
}

/**
 * Ir para página específica
 */
function irParaPaginaComissoes(page) {
    commissionsState.currentPage = page;
    commissionsState.selectedCommissions.clear();
    atualizarBotoesAcao();
    carregarComissoes();
}

/**
 * Traduzir status
 */
function translateStatus(status) {
    const map = {
        pendente: '⏳ Pendente',
        aprovada: '✅ Aprovada',
        paga: '💰 Paga',
        cancelada: '❌ Cancelada',
    };
    return map[status] || status;
}

/**
 * Classe CSS do badge de status
 */
function getStatusBadgeClass(status) {
    const classes = {
        pendente: 'bg-yellow-100 text-yellow-800',
        aprovada: 'bg-blue-100 text-blue-800',
        paga: 'bg-green-100 text-green-800',
        cancelada: 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

/**
 * Mostrar aviso/erro visual para o usuário
 */
function mostrarAvisoErro(mensagem, tipo = 'warning') {
    const alertClass =
        tipo === 'error' ? 'alert-danger' : tipo === 'success' ? 'alert-success' : 'alert-warning';
    const iconClass =
        tipo === 'error'
            ? 'fa-exclamation-circle'
            : tipo === 'success'
              ? 'fa-check-circle'
              : 'fa-exclamation-triangle';
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        <i class="fas ${iconClass} me-2"></i>
        ${mensagem}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);

    // Auto-remover após 5 segundos
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

/**
 * Deletar comissão (Admin only)
 */
async function deletarComissao(commissionId, propertyTitle) {
    // Confirmação
    if (
        !confirm(
            `Tem certeza que deseja deletar a comissão referente ao imóvel "${propertyTitle}"? Esta ação não pode ser desfeita.`
        )
    ) {
        return;
    }

    try {
        const res = await fetch('/api/v1/financeiro/deleteCommission.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                commission_id: commissionId,
            }),
        });

        const contentType = res.headers.get('content-type');

        // Verificar se a resposta é JSON
        if (!contentType || !contentType.includes('application/json')) {
            const text = await res.text();
            mostrarAvisoErro('Erro: Resposta inválida do servidor.', 'error');
            return;
        }

        const result = await res.json();

        if (result.success) {
            mostrarAvisoErro('✅ Comissão deletada com sucesso!', 'success');
            setTimeout(() => carregarComissoes(), 1000); // Recarregar tabela após 1s
        } else {
            mostrarAvisoErro('❌ Erro ao deletar comissão: ' + result.error, 'error');
        }
    } catch (e) {
        mostrarAvisoErro('Erro ao deletar comissão: ' + e.message, 'error');
    }
}
