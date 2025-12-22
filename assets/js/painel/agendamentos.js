// ===== AGENDAMENTOS =====

async function carregarAgendamentos() {
    try {
        let filtro = document.getElementById('agendamentos-filtro')?.value || 'proximos';
        const status = document.getElementById('agendamentos-status')?.value || 'todos';
        const mesSelecionado = document.getElementById('agendamentos-mes')?.value;
        const mes = mesSelecionado || new Date().toISOString().slice(0, 7);
        
        // Se um mês específico foi selecionado, muda o filtro para 'mes'
        if (mesSelecionado && filtro !== 'mes') {
            filtro = 'mes';
        }
        
        const url = `api/getAgendamentos.php?filtro=${filtro}&status=${status}&mes=${mes}`;
        const res = await fetch(url);
        const result = await res.json();
        
        const container = document.getElementById('agendamentos-container');
        if (!container) return;
        
        if (!result.success || result.data.length === 0) {
            container.innerHTML = '<div class="text-center py-8 text-gray-500">Nenhum agendamento encontrado</div>';
            return;
        }
        
        container.innerHTML = result.data.map(agendamento => {
            const data = new Date(agendamento.data_agendamento);
            const dataFormatada = data.toLocaleDateString('pt-BR');
            const horaFormatada = data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            
            const statusClass = {
                'confirmado': 'bg-green-100 text-green-800',
                'cancelado': 'bg-red-100 text-red-800',
                'realizado': 'bg-blue-100 text-blue-800'
            }[agendamento.status] || 'bg-gray-100 text-gray-800';
            
            return `
                <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-blue-600">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="font-semibold text-gray-800">${agendamento.property_title || 'Propriedade não especificada'}</h3>
                                <span class="px-3 py-1 rounded-full text-sm font-medium ${statusClass}">
                                    ${agendamento.status.charAt(0).toUpperCase() + agendamento.status.slice(1)}
                                </span>
                            </div>
                            <div class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-calendar-alt mr-2"></i>${dataFormatada} às ${horaFormatada}
                            </div>
                            <div class="text-sm text-gray-600 mb-2">
                                <i class="fas fa-user mr-2"></i>Agente: ${agendamento.user_name || 'Não especificado'}
                            </div>
                            ${agendamento.descricao ? `<p class="text-sm text-gray-700 mt-2">📝 ${agendamento.descricao}</p>` : ''}
                        </div>
                        <div class="flex gap-2 ml-4">
                            <button onclick="editarAgendamento(${agendamento.id})" class="px-3 py-1 bg-blue-100 text-blue-600 rounded hover:bg-blue-200 transition text-sm">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deletarAgendamento(${agendamento.id})" class="px-3 py-1 bg-red-100 text-red-600 rounded hover:bg-red-200 transition text-sm">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
    } catch (e) {
        console.error('Erro ao carregar agendamentos:', e);
        const container = document.getElementById('agendamentos-container');
        if (container) {
            container.innerHTML = '<div class="text-center py-8 text-red-500">Erro ao carregar agendamentos</div>';
        }
    }
}

function abrirModalAgendamento() {
    const html = `
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" id="modal-agendamento">
            <div class="bg-white rounded-lg p-6 max-w-lg w-full mx-4">
                <h2 class="text-xl font-bold mb-4">Novo Agendamento</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Imóvel</label>
                        <select id="agendamento-property" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            <option value="">Selecione um imóvel</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Data e Hora</label>
                        <input type="datetime-local" id="agendamento-data" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea id="agendamento-descricao" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" placeholder="Notas adicionais..."></textarea>
                    </div>
                </div>
                
                <div class="flex gap-3 mt-6">
                    <button onclick="document.getElementById('modal-agendamento').remove()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button onclick="salvarAgendamento()" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Salvar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', html);
    
    // Carregar propriedades
    fetch('api/getPropertiesSimple.php')
        .then(r => {
            console.log('Status da resposta:', r.status);
            return r.json();
        })
        .then(res => {
            console.log('Resposta de propriedades:', res);
            const select = document.getElementById('agendamento-property');
            if (res.success && res.data && res.data.length > 0) {
                res.data.forEach(prop => {
                    const option = document.createElement('option');
                    option.value = prop.id;
                    option.textContent = prop.title || `Imóvel #${prop.id}`;
                    select.appendChild(option);
                });
                console.log('Carregados', res.data.length, 'imóveis');
            } else {
                const option = document.createElement('option');
                option.textContent = 'Nenhum imóvel disponível';
                option.disabled = true;
                select.appendChild(option);
                console.warn('Nenhum imóvel encontrado. Resposta:', res);
            }
        })
        .catch(e => {
            console.error('Erro ao carregar propriedades:', e);
            const select = document.getElementById('agendamento-property');
            const option = document.createElement('option');
            option.textContent = 'Erro ao carregar imóveis';
            option.disabled = true;
            select.appendChild(option);
        });
}

async function salvarAgendamento() {
    const propertyId = document.getElementById('agendamento-property')?.value;
    const data = document.getElementById('agendamento-data')?.value;
    const descricao = document.getElementById('agendamento-descricao')?.value;
    
    if (!propertyId || !data) {
        alert('Preenchimento obrigatório: Imóvel e Data');
        return;
    }
    
    try {
        function formatLocalToMySQL(val) {
            // val expected like 'YYYY-MM-DDTHH:MM' or 'YYYY-MM-DDTHH:MM:SS'
            if (!val) return val;
            if (val.indexOf('T') !== -1) {
                let s = val.replace('T', ' ');
                if (s.length === 16) s = s + ':00';
                return s;
            }
            return val;
        }

        const res = await fetch('api/addAgendamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                property_id: propertyId,
                data_agendamento: formatLocalToMySQL(data),
                descricao: descricao
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
            alert('Agendamento criado com sucesso!');
            document.getElementById('modal-agendamento')?.remove();
            carregarAgendamentos();
            // Atualiza avisos de agendamentos (hoje/amanhã) sem recarregar a página
            if (typeof carregarAgendamentosProximos === 'function') carregarAgendamentosProximos();
            // Atualiza lista de notificações se existir
            if (typeof loadNotifications === 'function') loadNotifications();
            // Destaque visual + toast para novo agendamento
            if (typeof showNewAgendamentoCue === 'function' && result && result.data) {
                const ag = result.data;
                const d = new Date(ag.data_agendamento);
                const hora = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                showNewAgendamentoCue(ag.id, ag.property_title || 'Agendamento', `${d.toLocaleDateString('pt-BR')} ${hora}`);
            }
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (e) {
        alert('Erro ao salvar agendamento: ' + e.message);
        console.error('Erro completo:', e);
    }
}

async function editarAgendamento(id) {
    // Buscar agendamento
    try {
        const res = await fetch('api/getAgendamentos.php?id=' + id);
        const result = await res.json();
        
        if (!result.success || !result.data || result.data.length === 0) {
            alert('Agendamento não encontrado');
            return;
        }
        
        const agendamento = result.data[0];
        
        // Converter data do servidor para formato datetime-local (preservando horário local)
        let ds = agendamento.data_agendamento || '';
        // Normaliza para formato YYYY-MM-DDTHH:MM:SS para garantir parsing consistente
        if (ds.indexOf(' ') !== -1 && ds.indexOf('T') === -1) ds = ds.replace(' ', 'T');
        const dataObj = new Date(ds);
        const pad = n => (n < 10 ? '0' + n : n);
        const dataFormatada = dataObj.getFullYear() + '-' + pad(dataObj.getMonth() + 1) + '-' + pad(dataObj.getDate()) + 'T' + pad(dataObj.getHours()) + ':' + pad(dataObj.getMinutes());
        
        const html = `
            <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" id="modal-editar-agendamento">
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h2 class="text-xl font-bold mb-4">Editar Agendamento</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Imóvel</label>
                            <input type="text" value="${agendamento.property_title}" disabled class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Data e Hora</label>
                            <input type="datetime-local" id="editar-agendamento-data" value="${dataFormatada}" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="editar-agendamento-status" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                <option value="confirmado" ${agendamento.status === 'confirmado' ? 'selected' : ''}>Confirmado</option>
                                <option value="cancelado" ${agendamento.status === 'cancelado' ? 'selected' : ''}>Cancelado</option>
                                <option value="realizado" ${agendamento.status === 'realizado' ? 'selected' : ''}>Realizado</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                            <textarea id="editar-agendamento-descricao" class="w-full px-3 py-2 border border-gray-300 rounded-lg" rows="3" placeholder="Notas adicionais...">${agendamento.descricao || ''}</textarea>
                        </div>
                    </div>
                    
                    <div class="flex gap-3 mt-6">
                        <button onclick="document.getElementById('modal-editar-agendamento').remove()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button onclick="salvarEdicaoAgendamento(${id})" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Salvar
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', html);
    } catch (e) {
        alert('Erro ao abrir agendamento: ' + e.message);
        console.error(e);
    }
}

async function salvarEdicaoAgendamento(id) {
    const data = document.getElementById('editar-agendamento-data')?.value;
    const status = document.getElementById('editar-agendamento-status')?.value;
    const descricao = document.getElementById('editar-agendamento-descricao')?.value;
    
    if (!data || !status) {
        alert('Preenchimento obrigatório: Data e Status');
        return;
    }
    
    try {
        // Primeiro salvar a data
        function formatLocalToMySQL(val) {
            if (!val) return val;
            if (val.indexOf('T') !== -1) {
                let s = val.replace('T', ' ');
                if (s.length === 16) s = s + ':00';
                return s;
            }
            return val;
        }

        let res = await fetch('api/updateAgendamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                data_agendamento: formatLocalToMySQL(data)
            })
        });
        
        let result = await res.json();
        if (!result.success) throw new Error(result.error);
        
        // Depois salvar o status
        res = await fetch('api/updateAgendamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                status: status
            })
        });
        
        result = await res.json();
        if (!result.success) throw new Error(result.error);
        
        // Por último salvar a descrição
        res = await fetch('api/updateAgendamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: id,
                descricao: descricao
            })
        });
        
        result = await res.json();
        if (!result.success) throw new Error(result.error);
        
        // Após salvar todos os campos, buscar o agendamento atualizado e usar seus dados para atualizar a UI
        try {
            const resAg = await fetch('api/getAgendamentos.php?id=' + id);
            const jsonAg = await resAg.json();
            if (jsonAg.success && jsonAg.data && jsonAg.data.length > 0) {
                const ag = jsonAg.data[0];
                const d = new Date((ag.data_agendamento || '').replace(' ', 'T'));
                const hora = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

                alert('Agendamento atualizado com sucesso!');
                document.getElementById('modal-editar-agendamento')?.remove();
                carregarAgendamentos();
                if (typeof carregarAgendamentosProximos === 'function') carregarAgendamentosProximos();
                if (typeof loadNotifications === 'function') loadNotifications();
                if (typeof showNewAgendamentoCue === 'function') showNewAgendamentoCue(ag.id, ag.property_title || 'Agendamento', `${d.toLocaleDateString('pt-BR')} ${hora}`);
            } else {
                // fallback
                alert('Agendamento atualizado com sucesso!');
                document.getElementById('modal-editar-agendamento')?.remove();
                carregarAgendamentos();
                if (typeof carregarAgendamentosProximos === 'function') carregarAgendamentosProximos();
                if (typeof loadNotifications === 'function') loadNotifications();
            }
        } catch (e) {
            console.error('Erro ao buscar agendamento atualizado:', e);
            alert('Agendamento atualizado com sucesso!');
            document.getElementById('modal-editar-agendamento')?.remove();
            carregarAgendamentos();
            if (typeof carregarAgendamentosProximos === 'function') carregarAgendamentosProximos();
            if (typeof loadNotifications === 'function') loadNotifications();
        }
    } catch (e) {
        alert('Erro ao atualizar agendamento: ' + e.message);
        console.error(e);
    }
}

async function deletarAgendamento(id) {
    if (!confirm('Tem certeza que deseja deletar este agendamento?')) return;
    
    try {
        const res = await fetch('api/deleteAgendamento.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id
        });
        
        const result = await res.json();
        
        if (result.success) {
            alert('Agendamento deletado com sucesso!');
            carregarAgendamentos();
            // Atualiza avisos e notificações após exclusão
            if (typeof carregarAgendamentosProximos === 'function') carregarAgendamentosProximos();
            if (typeof loadNotifications === 'function') loadNotifications();
            if (typeof showNewAgendamentoCue === 'function') showNewAgendamentoCue(null, 'Agendamento removido', '');
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (e) {
        alert('Erro ao deletar agendamento: ' + e.message);
    }
}

// Event listeners para agendamentos
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('agendamentos-filtro')?.addEventListener('change', carregarAgendamentos);
    document.getElementById('agendamentos-status')?.addEventListener('change', carregarAgendamentos);
    document.getElementById('agendamentos-mes')?.addEventListener('change', carregarAgendamentos);
    
    // Carregar agendamentos quando clicar na aba
    document.querySelectorAll('a[href="#agendamentos"]').forEach(link => {
        link.addEventListener('click', () => {
            setTimeout(carregarAgendamentos, 100);
        });
    });
});
