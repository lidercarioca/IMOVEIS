document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all-leads');
    const leadsContainer = document.getElementById('leads-container');
    const deleteSelectedBtn = document.getElementById('delete-selected-leads');

    // Função para atualizar o estado do botão de exclusão
    function updateDeleteButton() {
        const checkedBoxes = leadsContainer.querySelectorAll('input[type="checkbox"]:checked');
        if (deleteSelectedBtn) {
            deleteSelectedBtn.disabled = checkedBoxes.length === 0;
        }
    }

    // Handler para o checkbox "Selecionar Todos"
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = leadsContainer.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateDeleteButton();
        });
    }

    // Handler para checkboxes individuais
    leadsContainer.addEventListener('change', function(e) {
        if (e.target.type === 'checkbox') {
            const checkboxes = leadsContainer.querySelectorAll('input[type="checkbox"]');
            const checkedBoxes = leadsContainer.querySelectorAll('input[type="checkbox"]:checked');
            
            // Atualiza o estado do checkbox principal
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = checkboxes.length === checkedBoxes.length;
                selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkboxes.length !== checkedBoxes.length;
            }
            
            updateDeleteButton();
        }
    });

    // Handler para excluir leads selecionados
    if (deleteSelectedBtn) {
        deleteSelectedBtn.addEventListener('click', async function() {
            const checkedBoxes = leadsContainer.querySelectorAll('input[type="checkbox"]:checked');
            // Usamos o data-lead-id do checkbox para coletar os IDs
            const leadIds = Array.from(checkedBoxes)
                .map(checkbox => checkbox.getAttribute('data-lead-id'))
                .filter(id => id !== null);

            if (leadIds.length === 0) return;

            if (confirm(`Tem certeza que deseja excluir ${leadIds.length} lead(s)?`)) {
                try {
                    const response = await fetch('/api/deleteLead.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ leadIds })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Remove as linhas da tabela
                        checkedBoxes.forEach(checkbox => {
                            const row = checkbox.closest('tr');
                            if (row) row.remove();
                        });

                        // Reseta o checkbox principal
                        if (selectAllCheckbox) {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = false;
                        }

                        // Atualiza o botão de exclusão
                        updateDeleteButton();

                        // Atualiza o contador de leads
                        const remainingLeads = leadsContainer.querySelectorAll('tr').length;
                        const countElement = document.querySelector('.text-sm.text-gray-600');
                        if (countElement) {
                            countElement.textContent = `Mostrando ${remainingLeads} leads`;
                        }

                        alert('Leads excluídos com sucesso!');
                        
                        // Atualiza contadores do dashboard se a função existir
                        if (typeof window.updateDashboardCounts === 'function') {
                            window.updateDashboardCounts();
                        }
                    } else {
                        throw new Error(data.message || 'Erro ao excluir leads');
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    alert('Erro ao excluir leads. Por favor, tente novamente.');
                }
            }
        });
    }

    // Handler para exportar leads como CSV (todos ou selecionados)
    const exportBtn = document.getElementById('export-leads-csv');
    if (exportBtn) {
        exportBtn.addEventListener('click', async function() {
            try {
                const checkedBoxes = leadsContainer.querySelectorAll('input[type="checkbox"]:checked');
                const selectedIds = Array.from(checkedBoxes)
                    .map(cb => cb.getAttribute('data-lead-id'))
                    .filter(id => id !== null);

                const res = await fetch('/api/getLeads.php');
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Erro ao obter leads');
                let leads = Array.isArray(json.data) ? json.data : [];

                if (selectedIds.length > 0) {
                    leads = leads.filter(l => {
                        const idVal = l.id ?? l.lead_id ?? l.ID ?? l.leads_id ?? null;
                        return idVal !== null && selectedIds.includes(String(idVal));
                    });
                }

                if (leads.length === 0) {
                    alert('Nenhum lead disponível para exportar.');
                    return;
                }

                const keys = Object.keys(leads[0]);
                const csvRows = [];
                csvRows.push(keys.join(','));

                leads.forEach(obj => {
                    const row = keys.map(k => {
                        let v = obj[k] === null || obj[k] === undefined ? '' : String(obj[k]);
                        v = v.replace(/"/g, '""');
                        if (v.search(/[,"\n]/) >= 0) v = `"${v}"`;
                        return v;
                    }).join(',');
                    csvRows.push(row);
                });

                const csvString = csvRows.join('\n');
                const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                const now = new Date();
                const fname = `leads_${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}.csv`;
                a.setAttribute('download', fname);
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            } catch (err) {
                console.error('Erro ao exportar CSV:', err);
                alert('Erro ao exportar CSV. Veja o console para detalhes.');
            }
        });
    }
});