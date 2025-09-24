// Módulo unificado para gerenciamento de backups
const BackupModule = {
    config: {
        selectors: {
            backupsList: '[id^="backups-list"]',
            backupItem: '[data-backup]'
        },
        endpoints: {
            delete: '/api/deleteBackup.php',
            list: '/api/listBackups.php',
            create: '/backup.php',
            restore: '/restore.php'
        }
    },

    // Helpers
    utils: {
        // Determina o caminho base para as requisições
        getBasePath() {
            return window.location.pathname.includes('/views/admin/') ? '../..' : '';
        },

        // Validação do nome do arquivo de backup
        isValidBackupFilename(filename) {
            return filename.startsWith('db_backup_') && filename.endsWith('.sql');
        },

        // Formata o tamanho do arquivo
        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        // Formata a data do arquivo de backup
        formatDate(filename) {
            const match = filename.match(/\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}/);
            if (!match) return filename;
            const [date, time] = match[0].split('_');
            return `${date.split('-').reverse().join('/')} ${time.replace(/-/g, ':')}`;
        },

        // Mostra mensagem de sucesso
        showSuccess(message) {
            alert(message); // Pode ser substituído por uma implementação mais elegante
        },

        // Mostra mensagem de erro
        showError(message) {
            alert(message); // Pode ser substituído por uma implementação mais elegante
        },

        // Toggle loading state
        toggleLoading(show) {
            document.body.style.cursor = show ? 'wait' : 'default';
            // Implementar lógica adicional de loading se necessário
        }
    },

    // Métodos principais
    async excluirBackup(filename) {
        if (!confirm('Tem certeza que deseja excluir este backup? Esta ação não pode ser desfeita.')) {
            return;
        }

        try {
            this.utils.toggleLoading(true);

            // Valida o nome do arquivo
            if (!this.utils.isValidBackupFilename(filename)) {
                throw new Error('Nome do arquivo de backup inválido');
            }

            // Feedback visual imediato
            const tr = document.querySelector(`${this.config.selectors.backupItem}[data-backup="${filename}"]`);
            if (tr) {
                tr.style.opacity = '0.5';
            }

            // Prepara e envia a requisição
            const formData = new FormData();
            formData.append('filename', filename);

            const response = await fetch(`${this.utils.getBasePath()}${this.config.endpoints.delete}`, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }

            // Processa a resposta
            const responseText = await response.text();
            console.log('Resposta do servidor:', responseText);

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Erro ao processar resposta:', e);
                throw new Error('Erro ao processar resposta do servidor');
            }

            if (!data.success) {
                throw new Error(data.message || 'Erro ao excluir backup');
            }

            // Atualiza todas as listas de backup
            await this.atualizarTodasListas();
            this.utils.showSuccess('Backup excluído com sucesso!');

        } catch (error) {
            this.utils.showError('Erro ao excluir backup: ' + error.message);
            // Restaura a opacidade se houver erro
            if (tr) {
                tr.style.opacity = '1';
            }
        } finally {
            this.utils.toggleLoading(false);
        }
    },

    async atualizarLista(tbody) {
        try {
            const response = await fetch(`${this.utils.getBasePath()}${this.config.endpoints.list}?nocache=${Date.now()}`, {
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate',
                    'Pragma': 'no-cache',
                    'Expires': '0'
                }
            });

            if (!response.ok) {
                throw new Error(`Erro ao buscar lista de backups: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Erro ao carregar backups');
            }

            tbody.innerHTML = '';
            const backups = Array.isArray(data.data) ? data.data : [];

            if (backups.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted">Nenhum backup encontrado.</td>
                    </tr>`;
                return;
            }

            backups.forEach(backup => {
                const tr = document.createElement('tr');
                tr.setAttribute('data-backup', backup.file);
                tr.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${this.utils.formatDate(backup.file)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${backup.file}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        ${this.utils.formatFileSize(backup.size)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <button onclick="BackupModule.restaurarBackup('${backup.file}')" 
                                class="inline-flex items-center px-3 py-1.5 bg-yellow-500 text-white rounded-md hover:bg-yellow-600 mr-2">
                            <i class="fas fa-undo-alt mr-1"></i> Restaurar
                        </button>
                        <button onclick="BackupModule.excluirBackup('${backup.file}')" 
                                class="inline-flex items-center px-3 py-1.5 bg-red-500 text-white rounded-md hover:bg-red-600">
                            <i class="fas fa-trash-alt mr-1"></i> Excluir
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } catch (error) {
            console.error('Erro ao atualizar lista:', error);
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-red-500">
                        Erro ao carregar backups: ${error.message}
                    </td>
                </tr>`;
        }
    },

    async atualizarTodasListas() {
        const lists = document.querySelectorAll(this.config.selectors.backupsList);
        for (const tbody of lists) {
            await this.atualizarLista(tbody);
        }
    },

    // Restaura um backup existente
    async restaurarBackup(filename) {
        if (!confirm('ATENÇÃO: Restaurar um backup irá substituir todos os dados atuais. Esta ação não pode ser desfeita. Deseja continuar?')) {
            return;
        }

        const restoreButton = document.querySelector(`button[onclick="BackupModule.restaurarBackup('${filename}')"]`);
        const originalText = restoreButton ? restoreButton.innerHTML : '';

        try {
            // Validação do nome do arquivo
            if (!this.utils.isValidBackupFilename(filename)) {
                throw new Error('Nome do arquivo de backup inválido');
            }

            // Desabilita o botão e mostra loading
            if (restoreButton) {
                restoreButton.disabled = true;
                restoreButton.innerHTML = `
                    <i class="fas fa-spinner fa-spin mr-1"></i>
                    Restaurando...
                `;
            }
            this.utils.toggleLoading(true);

            // Prepara e envia a requisição
            const formData = new FormData();
            formData.append('backup_file', filename);

            const response = await fetch(`${this.utils.getBasePath()}${this.config.endpoints.restore}`, {
                method: 'POST',
                body: formData
            });

            let data;
            try {
                const responseText = await response.text();
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('Resposta do servidor:', responseText);
                throw new Error('Erro ao processar resposta do servidor');
            }

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Erro ao restaurar backup');
            }

            this.utils.showSuccess('Backup restaurado com sucesso! A página será recarregada.');
            
            // Recarrega a página após 2 segundos
            setTimeout(() => {
                window.location.reload();
            }, 2000);

        } catch (error) {
            this.utils.showError('Erro ao restaurar backup: ' + error.message);
        } finally {
            this.utils.toggleLoading(false);
            if (restoreButton) {
                restoreButton.disabled = false;
                restoreButton.innerHTML = originalText;
            }
        }
    },

    // Realiza um novo backup
    async realizarBackup() {
        const backupButton = document.querySelector('button[onclick="realizarBackup()"]');
        const originalText = backupButton ? backupButton.innerHTML : '';
        
        try {
            if (backupButton) {
                backupButton.disabled = true;
                backupButton.innerHTML = `
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    Realizando Backup...
                `;
            }
            this.utils.toggleLoading(true);

            const response = await fetch(`${this.utils.getBasePath()}${this.config.endpoints.create}`);
            
            let data;
            try {
                const responseText = await response.text();
                try {
                    data = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('Resposta bruta do servidor:', responseText);
                    if (responseText.includes('<?php')) {
                        throw new Error('O arquivo PHP não está sendo processado corretamente');
                    } else if (responseText.includes('Warning:') || responseText.includes('Notice:')) {
                        throw new Error('O PHP está gerando avisos que estão corrompendo a saída JSON');
                    } else {
                        throw new Error('Resposta inválida do servidor. Verifique o console para mais detalhes.');
                    }
                }
            } catch (parseError) {
                console.error('Erro ao processar resposta:', parseError);
                throw new Error(`Erro ao processar resposta do servidor: ${parseError.message}`);
            }

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status} - ${data?.message || response.statusText}`);
            }

            if (data.success) {
                // Mostra mensagem de sucesso com detalhes dos arquivos
                let successMsg = 'Backup realizado com sucesso!\n\nArquivos gerados:\n';
                successMsg += `- Banco de dados: ${data.files.database}\n`;
                successMsg += `- Configurações: ${data.files.env}\n`;
                if (data.files.media) {
                    successMsg += `- Arquivos de mídia: ${data.files.media}`;
                }
                
                // Atualiza todas as listas
                await this.atualizarTodasListas();
                this.utils.showSuccess(successMsg);
            } else {
                throw new Error(data.message || 'Erro ao realizar backup');
            }
        } catch (error) {
            this.utils.showError('Erro ao realizar backup: ' + error.message);
        } finally {
            this.utils.toggleLoading(false);
            if (backupButton) {
                backupButton.disabled = false;
                backupButton.innerHTML = originalText;
            }
        }
    },

    // Inicialização do módulo
    init() {
        try {
            console.log('Inicializando módulo de backup...');
            const backupsLists = document.querySelectorAll(this.config.selectors.backupsList);
            
            if (backupsLists && backupsLists.length > 0) {
                backupsLists.forEach(tbody => {
                    if (tbody) {
                        this.atualizarLista(tbody);
                    }
                });
                console.log('Listas de backup inicializadas');
            } else {
                console.warn('Nenhuma tabela de backups encontrada');
            }
        } catch (error) {
            console.error('Erro na inicialização:', error);
            this.utils.showError('Erro ao inicializar o módulo de backup');
        }
    }
};

// Garante que o módulo está disponível globalmente antes de qualquer uso
window.BackupModule = BackupModule;

// Aguarda o DOM estar completamente carregado
document.addEventListener('DOMContentLoaded', () => {
    try {
        BackupModule.init();
    } catch (error) {
        console.error('Erro ao inicializar BackupModule:', error);
    }
});
