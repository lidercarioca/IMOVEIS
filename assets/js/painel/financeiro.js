// ===== FINANCEIRO =====

async function carregarResumoFinanceiro() {
    try {
        const mes = document.getElementById('financeiro-mes')?.value || new Date().toISOString().slice(0, 7);
        
        console.log('Carregando resumo financeiro para:', mes);
        const res = await fetch(`api/getFinanceiro.php?tipo=resumo&mes=${mes}`);
        const text = await res.text();
        console.log('Resposta resumo:', text);
        
        const result = JSON.parse(text);
        
        if (result.success) {
            const data = result.data;
            document.getElementById('total-receitas').textContent = formatarMoeda(data.total_receitas || 0);
            document.getElementById('total-despesas').textContent = formatarMoeda(data.total_despesas || 0);
            document.getElementById('total-comissoes').textContent = formatarMoeda(data.total_comissoes || 0);
            document.getElementById('lucro-liquido').textContent = formatarMoeda(data.lucro_liquido || 0);
            console.log('Resumo carregado:', data);
        } else {
            console.error('Erro ao carregar resumo:', result.error);
        }
    } catch (e) {
        console.error('Erro ao carregar resumo financeiro:', e);
    }
}

async function carregarTransacoesFinanceiras() {
    try {
        const mesElement = document.getElementById('financeiro-mes');
        const mes = mesElement?.value || new Date().toISOString().slice(0, 7);
        const tipoFiltro = document.getElementById('financeiro-tipo')?.value || 'todos';
        const categoria = document.getElementById('financeiro-categoria')?.value || 'todos';
        const status = document.getElementById('financeiro-status')?.value || 'todos';
        
        console.log('Carregando transações para - Mês:', mes, 'Tipo:', tipoFiltro, 'Categoria:', categoria, 'Status:', status);
        
        // Construir URL com parâmetros corretos
        let url = `api/getFinanceiro.php?modo=detalhado&mes=${mes}`;
        if (tipoFiltro && tipoFiltro !== 'todos') {
            url += `&tipoFiltro=${tipoFiltro}`;
        }
        if (categoria && categoria !== 'todos') {
            url += `&categoria=${categoria}`;
        }
        if (status && status !== 'todos') {
            url += `&status=${status}`;
        }
        
        console.log('URL requisição:', url);
        const res = await fetch(url);
        const text = await res.text();
        console.log('Resposta transações:', text.substring(0, 500));
        
        const result = JSON.parse(text);
        
        const container = document.getElementById('financeiro-container');
        if (!container) {
            console.error('Container não encontrado');
            return;
        }
        
        if (!result.success) {
            console.error('Erro na resposta:', result.error);
            container.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-red-500">Erro ao carregar transações: ' + result.error + '</td></tr>';
            return;
        }
        
        if (!result.data || result.data.length === 0) {
            console.log('Nenhuma transação encontrada');
            container.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">Nenhuma transação encontrada</td></tr>';
            return;
        }
        
        console.log('Transações carregadas:', result.data);
        container.innerHTML = result.data.map(transacao => {
            const data = new Date(transacao.data_transacao);
            const dataFormatada = data.toLocaleDateString('pt-BR');
            
            const tipoClass = {
                'receita': 'text-green-600',
                'despesa': 'text-red-600',
                'comissao': 'text-orange-600'
            }[transacao.tipo] || 'text-gray-600';
            
            const statusClass = {
                'pendente': 'bg-yellow-100 text-yellow-800',
                'concluído': 'bg-green-100 text-green-800',
                'cancelado': 'bg-red-100 text-red-800'
            }[transacao.status] || 'bg-gray-100 text-gray-800';
            
            const valor = parseFloat(transacao.valor);
            const valorFormatado = formatarMoeda(valor);
            
            return `
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${dataFormatada}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="font-medium ${tipoClass}">
                            ${transacao.tipo.charAt(0).toUpperCase() + transacao.tipo.slice(1)}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">${transacao.descricao || '-'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${transacao.categoria || 'Outra'}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right ${tipoClass}">${valorFormatado}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="px-3 py-1 rounded-full text-xs font-medium ${statusClass}">
                            ${transacao.status.charAt(0).toUpperCase() + transacao.status.slice(1)}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                        <button onclick="editarTransacao(${transacao.id})" class="text-blue-600 hover:text-blue-800 mr-3">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deletarTransacao(${transacao.id})" class="text-red-600 hover:text-red-800">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
    } catch (e) {
        console.error('Erro ao carregar transações:', e);
        const container = document.getElementById('financeiro-container');
        if (container) {
            container.innerHTML = '<tr><td colspan="7" class="px-6 py-4 text-center text-red-500">Erro ao carregar transações</td></tr>';
        }
    }
}

function formatarMoeda(valor) {
    return parseFloat(valor).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });
}

function abrirModalFinanceiro() {
    const html = `
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" id="modal-financeiro">
            <div class="bg-white rounded-lg p-6 max-w-lg w-full mx-4 max-h-[500px] overflow-y-auto">
                <h2 class="text-xl font-bold mb-4">Nova Transação</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                        <select id="financeiro-tipo-input" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            <option value="">Selecione</option>
                            <option value="receita">Receita</option>
                            <option value="despesa">Despesa</option>
                            <option value="comissao">Comissão</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <input type="text" id="financeiro-descricao" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Descrição da transação" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor</label>
                        <input type="number" id="financeiro-valor" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="0,00" step="0.01" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                        <input type="date" id="financeiro-data" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                        <select id="financeiro-categoria-input" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="Outra">Outra</option>
                            <option value="Venda">Venda</option>
                            <option value="Aluguel">Aluguel</option>
                            <option value="Comissão">Comissão</option>
                            <option value="Serviço">Serviço</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button onclick="document.getElementById('modal-financeiro').remove()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button onclick="salvarTransacao()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', html);
    
    // Definir data de hoje por padrão
    const hoje = new Date().toISOString().slice(0, 10);
    document.getElementById('financeiro-data').value = hoje;
}

async function salvarTransacao() {
    const tipo = document.getElementById('financeiro-tipo-input')?.value;
    const descricao = document.getElementById('financeiro-descricao')?.value;
    const valor = document.getElementById('financeiro-valor')?.value;
    const data = document.getElementById('financeiro-data')?.value;
    const categoria = document.getElementById('financeiro-categoria-input')?.value;
    
    if (!tipo || !descricao || !valor || !data) {
        alert('Preenchimento obrigatório: Tipo, Descrição, Valor e Data');
        return;
    }
    
    try {
        const res = await fetch('api/addFinanceiro.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo: tipo,
                descricao: descricao,
                valor: valor,
                data_transacao: data,
                categoria: categoria
            })
        });
        
        const text = await res.text();
        console.log('Resposta do servidor:', text);
        
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('Erro ao fazer parse JSON:', e);
            alert('Erro: Resposta inválida do servidor\n' + text.substring(0, 200));
            return;
        }
        
        if (result.success) {
            alert('Transação registrada com sucesso!');
            document.getElementById('modal-financeiro')?.remove();
            carregarResumoFinanceiro();
            carregarTransacoesFinanceiras();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (e) {
        alert('Erro ao salvar transação: ' + e.message);
        console.error('Erro completo:', e);
    }
}

async function editarTransacao(id) {
    try {
        // Buscar dados da transação
        const mes = document.getElementById('financeiro-mes')?.value || new Date().toISOString().slice(0, 7);
        const res = await fetch(`api/getFinanceiro.php?tipo=detalhado&mes=${mes}`);
        const text = await res.text();
        const result = JSON.parse(text);
        
        if (!result.success || !result.data) {
            alert('Erro ao carregar transação');
            return;
        }
        
        // Procurar a transação pelo ID
        const transacao = result.data.find(t => t.id == id);
        if (!transacao) {
            alert('Transação não encontrada');
            return;
        }
        
        // Converter data para formato datetime-local
        const dataObj = new Date(transacao.data_transacao);
        const dataFormatada = dataObj.toISOString().split('T')[0];
        
        // Criar modal de edição
        const html = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" id="modal-editar-transacao">
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h2 class="text-xl font-bold mb-4">Editar Transação</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                            <select id="editar-tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                <option value="receita" ${transacao.tipo === 'receita' ? 'selected' : ''}>Receita</option>
                                <option value="despesa" ${transacao.tipo === 'despesa' ? 'selected' : ''}>Despesa</option>
                                <option value="comissao" ${transacao.tipo === 'comissao' ? 'selected' : ''}>Comissão</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <input type="text" id="editar-descricao" value="${transacao.descricao || ''}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valor</label>
                            <input type="number" id="editar-valor" value="${transacao.valor}" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                            <input type="date" id="editar-data" value="${dataFormatada}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                            <select id="editar-categoria" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="Venda" ${transacao.categoria === 'Venda' ? 'selected' : ''}>Venda</option>
                                <option value="Aluguel" ${transacao.categoria === 'Aluguel' ? 'selected' : ''}>Aluguel</option>
                                <option value="Comissão" ${transacao.categoria === 'Comissão' ? 'selected' : ''}>Comissão</option>
                                <option value="Serviço" ${transacao.categoria === 'Serviço' ? 'selected' : ''}>Serviço</option>
                                <option value="Outra" ${transacao.categoria === 'Outra' ? 'selected' : ''}>Outra</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="editar-status" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="pendente" ${transacao.status === 'pendente' ? 'selected' : ''}>Pendente</option>
                                <option value="concluído" ${transacao.status === 'concluído' ? 'selected' : ''}>Concluído</option>
                                <option value="cancelado" ${transacao.status === 'cancelado' ? 'selected' : ''}>Cancelado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button onclick="document.getElementById('modal-editar-transacao').remove()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button onclick="salvarEdicaoTransacao(${id})" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Salvar
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', html);
    } catch (e) {
        alert('Erro ao abrir transação para edição: ' + e.message);
        console.error(e);
    }
}

async function salvarEdicaoTransacao(id) {
    const tipo = document.getElementById('editar-tipo')?.value;
    const descricao = document.getElementById('editar-descricao')?.value;
    const valor = document.getElementById('editar-valor')?.value;
    const data = document.getElementById('editar-data')?.value;
    const categoria = document.getElementById('editar-categoria')?.value;
    const status = document.getElementById('editar-status')?.value;
    
    if (!tipo || !descricao || !valor || !data) {
        alert('Preenchimento obrigatório: Tipo, Descrição, Valor e Data');
        return;
    }
    
    try {
        // Fazer update da transação
        const res = await fetch('api/updateFinanceiro.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                tipo: tipo,
                descricao: descricao,
                valor: parseFloat(valor),
                data_transacao: data,
                categoria: categoria,
                status: status
            })
        });
        
        const text = await res.text();
        const result = JSON.parse(text);
        
        if (result.success) {
            alert('Transação atualizada com sucesso!');
            document.getElementById('modal-editar-transacao')?.remove();
            carregarResumoFinanceiro();
            carregarTransacoesFinanceiras();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (e) {
        alert('Erro ao atualizar transação: ' + e.message);
        console.error(e);
    }
}

async function deletarTransacao(id) {
    if (!confirm('Tem certeza que deseja deletar esta transação?')) return;
    
    try {
        const res = await fetch('api/deleteFinanceiro.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        });
        
        const result = await res.json();
        
        if (result.success) {
            alert('Transação deletada com sucesso!');
            carregarResumoFinanceiro();
            carregarTransacoesFinanceiras();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (e) {
        alert('Erro ao deletar transação: ' + e.message);
    }
}

// Event listeners para financeiro
document.addEventListener('DOMContentLoaded', function() {
    // Carregar quando clicar na aba
    document.querySelectorAll('a[href="#financeiro"]').forEach(link => {
        link.addEventListener('click', () => {
            setTimeout(() => {
                console.log('Aba financeiro clicada');
                carregarResumoFinanceiro();
                carregarTransacoesFinanceiras();
            }, 100);
        });
    });
    
    document.getElementById('financeiro-mes')?.addEventListener('change', () => {
        carregarResumoFinanceiro();
        carregarTransacoesFinanceiras();
    });
    
    document.getElementById('financeiro-tipo')?.addEventListener('change', carregarTransacoesFinanceiras);
    document.getElementById('financeiro-categoria')?.addEventListener('change', carregarTransacoesFinanceiras);
    document.getElementById('financeiro-status')?.addEventListener('change', carregarTransacoesFinanceiras);
    
    // Carregar imediatamente se a aba financeiro estiver visível
    if (window.location.hash === '#financeiro' || document.querySelector('#financeiro:not(.hidden)')) {
        console.log('Aba financeiro já visível, carregando dados');
        carregarResumoFinanceiro();
        carregarTransacoesFinanceiras();
    }
});
