// Script para gerenciar restauração de backups
async function listarBackups() {
    try {
        const response = await fetch('restore.php');
        let data;
        
        // Se for erro de autorização
        if (response.status === 403) {
            const backupList = document.getElementById('backup-list');
            if (backupList) {
                backupList.innerHTML = `
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center">
                            <div class="text-red-500 mb-2">
                                Você não tem permissão para visualizar backups.
                            </div>
                            <div>
                                <a href="painel.php" class="text-blue-500 hover:underline">Voltar ao Painel</a>
                            </div>
                        </td>
                    </tr>`;
            }
            return;
        }

        // Tenta parsear a resposta JSON
        try {
            const text = await response.text();
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Erro ao parsear resposta:', e);
                console.error('Resposta recebida:', text);
                throw new Error('Resposta inválida do servidor');
            }
        } catch (e) {
            console.error('Erro ao ler resposta:', e);
            throw new Error('Erro ao comunicar com o servidor');
        }

        const backupList = document.getElementById('backup-list');
        if (!backupList) return;

        // Verifica se a resposta foi bem sucedida
        if (!data.success) {
            throw new Error(data.message || 'Erro ao listar backups');
        }
        
        if (!data.data || data.data.length === 0) {
            backupList.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">Nenhum backup encontrado</td></tr>';
            return;
        }
        
        backupList.innerHTML = data.data.map(backup => `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${backup.date}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">${formatFileSize(backup.size)}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <button onclick="restaurarBackup('${backup.file}')" 
                            class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i class="fas fa-undo-alt mr-2"></i>
                        Restaurar
                    </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-center">
                    <button onclick="excluirBackup('${backup.file}')" 
                            class="inline-flex items-center px-3 py-1 bg-red-600 text-white text-sm font-medium rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <i class="fas fa-trash-alt mr-2"></i>
                        Excluir
                    </button>
                </td>
            </tr>
        `).join('');
        
    } catch (error) {
        console.error('Erro:', error);
        const backupList = document.getElementById('backup-list');
        if (backupList) {
            backupList.innerHTML = `
                <tr>
                    <td colspan="4" class="px-6 py-4 text-center text-red-500">
                        Erro ao carregar backups: ${error.message}
                    </td>
                </tr>`;
        }
    }
}

async function restaurarBackup(filename) {
    if (!confirm('ATENÇÃO: Esta operação irá substituir todos os dados atuais pelos dados do backup. Deseja continuar?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('backup_file', filename);
        
        const response = await fetch('restore.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Erro ao restaurar backup');
        }
        
        alert('Backup restaurado com sucesso!');
        window.location.reload(); // Recarrega a página para refletir os dados restaurados
        
    } catch (error) {
        console.error('Erro:', error);
        alert('Erro ao restaurar backup: ' + error.message);
    }
}

async function excluirBackup(filename) {
    await BackupModule.excluirBackup(filename);
    listarBackups(); // Atualiza a lista específica desta página
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Inicializa quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', listarBackups);
