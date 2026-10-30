// Módulo unificado para gerenciamento de backups
const BackupModule = {
    config: {
        selectors: {
            backupsList: '[id^="backups-list"]',
            backupItem: '[data-backup]'
        },
        endpoints: {
            delete: '/api/v1/auth/deleteBackup.php',
            list: '/api/v1/auth/listBackups.php',
            create: '/tools/backup.php',
            restore: '/tools/restore.php'
        }
    },

    // Helpers
    utils: {
        // Determina o caminho base para as requisições
        getBasePath() {
            return window.location.pathname.includes('/views/admin/') ? '../..' : '';
        },

        // Validação do nome do arquivo de backup
        // Aceita dois formatos:
        // - Novo: db_backup_tenant_X_YYYY-MM-DD_HH-mm-ss.sql
        // - Legado: db_backup_YYYY-MM-DD_HH-mm-ss.sql
        isValidBackupFilename(filename) {
            return (filename.startsWith('db_backup_tenant_') && filename.endsWith('.sql')) ||
                   (filename.startsWith('db_backup_') && filename.endsWith('.sql'));
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
        // Suporta dois formatos:
        // - Novo: db_backup_tenant_X_YYYY-MM-DD_HH-mm-ss.sql
        // - Legado: db_backup_YYYY-MM-DD_HH-mm-ss.sql
        formatDate(filename) {
            // Tenta match do novo formato: db_backup_tenant_X_YYYY-MM-DD_HH-mm-ss.sql
            let match = filename.match(/db_backup_tenant_\d+_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/);
            
            // Se não encontrar, tenta formato legado
            if (!match) {
                match = filename.match(/db_backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})/);
            }
            
            if (!match) return filename;
            
            const [date, time] = match[1].split('_');
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

        let tr = null;  // Declarar fora do try para usar em catch/finally

        try {
            this.utils.toggleLoading(true);

            // Valida o nome do arquivo
            if (!this.utils.isValidBackupFilename(filename)) {
                throw new Error('Nome do arquivo de backup inválido');
            }

            // Feedback visual imediato
            tr = document.querySelector(`${this.config.selectors.backupItem}[data-backup="${filename}"]`);
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

            // Processa a resposta
            const responseText = await response.text();
            // Resposta recebida

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                throw new Error('Erro ao processar resposta do servidor: ' + responseText);
            }

            if (!response.ok || !data.success) {
                let errorMsg = data.message || 'Erro ao excluir backup';
                if (data.debug_trace) {
                    errorMsg += '\n\nDetalhes técnicos: ' + data.debug_trace;
                }
                throw new Error(errorMsg);
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

            const response = await fetch(`${this.utils.getBasePath()}${this.config.endpoints.create}?ajax=1`);
            
            let data;
            try {
                const responseText = await response.text();
                const cleanedResponse = responseText.trim(); // Remove espaços em branco
                try {
                    data = JSON.parse(cleanedResponse);
                } catch (jsonError) {
                    throw new Error('Resposta inválida do servidor: não é JSON válido');
                }
            } catch (parseError) {
                throw new Error(`Erro ao processar resposta: ${parseError.message}`);
            }

            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status} - ${data?.message || response.statusText}`);
            }

            if (data.success) {
                // Monta mensagem com os arquivos gerados
                let successMsg = 'Backup realizado com sucesso!\n\nArquivos gerados:\n';
                
                if (data.files) {
                    if (data.files.database) {
                        successMsg += `- Banco de dados: ${data.files.database}\n`;
                    }
                    if (data.files.env) {
                        successMsg += `- Configurações: ${data.files.env}\n`;
                    }
                    if (data.files.database_config) {
                        successMsg += `- Config DB: ${data.files.database_config}\n`;
                    }
                    if (data.files.media) {
                        successMsg += `- Mídia: ${data.files.media}`;
                    }
                } else {
                    // Fallback se não houver dados de files
                    successMsg += '- Backup criado com sucesso';
                }
                
                this.utils.showSuccess(successMsg);
                
                // Atualiza a lista de backups
                await this.atualizarTodasListas();
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
            const backupsLists = document.querySelectorAll(this.config.selectors.backupsList);

            if (backupsLists && backupsLists.length > 0) {
                backupsLists.forEach(tbody => {
                    if (tbody) {
                        this.atualizarLista(tbody);
                    }
                });
            } else {
                // Sem backups disponíveis
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
