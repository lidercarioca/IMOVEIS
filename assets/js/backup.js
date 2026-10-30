// Arquivo mantido para compatibilidade, toda a lógica foi movida para backupModule.js

// Re-exporta as funções do BackupModule para manter compatibilidade com código existente
/**
 * Exclui um arquivo de backup do sistema
 * @param {string} filename - Nome do arquivo de backup a ser excluído
 * @returns {Promise} Promise resolvida após a exclusão do backup
 */
window.excluirBackup = function(filename) {
    return BackupModule.excluirBackup(filename);
};

/**
 * Restaura o sistema a partir de um arquivo de backup
 * @param {string} filename - Nome do arquivo de backup a ser restaurado
 * @returns {Promise} Promise resolvida após a restauração do backup
 */
window.restaurarBackup = function(filename) {
    return BackupModule.restaurarBackup(filename);
};

/**
 * Realiza um novo backup do sistema
 * @returns {Promise} Promise resolvida após a criação do backup
 */
window.realizarBackup = function() {
    return BackupModule.realizarBackup();
};

/**
 * Atualiza a lista de backups disponíveis na interface
 * @returns {Promise} Promise resolvida após a atualização da lista
 */
window.atualizarListaBackups = function() {
    return BackupModule.atualizarTodasListas();
};

// Funções utilitárias re-exportadas
/**
 * Formata um tamanho em bytes para uma string legível
 * @param {number} bytes - Tamanho em bytes
 * @returns {string} Tamanho formatado (ex: "1.5 MB")
 */
window.formatFileSize = function(bytes) {
    return BackupModule.utils.formatFileSize(bytes);
};

/**
 * Formata a data contida no nome do arquivo de backup
 * @param {string} filename - Nome do arquivo de backup
 * @returns {string} Data formatada
 */
window.formatDate = function(filename) {
    return BackupModule.utils.formatDate(filename);
};

/**
 * Exibe uma mensagem de erro na interface
 * @param {string} message - Mensagem de erro
 */
window.showError = function(message) {
    return BackupModule.utils.showError(message);
};

/**
 * Exibe uma mensagem de sucesso na interface
 * @param {string} message - Mensagem de sucesso
 */
window.showSuccess = function(message) {
    return BackupModule.utils.showSuccess(message);
};

/**
 * Alterna a exibição do indicador de carregamento
 * @param {boolean} show - true para mostrar, false para esconder
 */
window.toggleLoading = function(show) {
    return BackupModule.utils.toggleLoading(show);
};

// Inicialização
if (document.readyState === 'complete') {
    BackupModule.init();
} else {
    window.addEventListener('load', () => BackupModule.init());
}
