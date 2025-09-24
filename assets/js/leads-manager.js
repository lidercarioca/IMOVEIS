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
            const leadIds = Array.from(checkedBoxes).map(checkbox => {
                const row = checkbox.closest('tr');
                return row ? row.dataset.leadId : null;
            }).filter(id => id !== null);

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
});