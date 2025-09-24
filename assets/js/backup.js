// Arquivo mantido para compatibilidade, toda a lógica foi movida para backupModule.js

// Re-exporta as funções do BackupModule para manter compatibilidade com código existente
window.excluirBackup = function(filename) {
    return BackupModule.excluirBackup(filename);
};

window.restaurarBackup = function(filename) {
    return BackupModule.restaurarBackup(filename);
};

window.realizarBackup = function() {
    return BackupModule.realizarBackup();
};

window.atualizarListaBackups = function() {
    return BackupModule.atualizarTodasListas();
};

// Funções utilitárias re-exportadas
window.formatFileSize = function(bytes) {
    return BackupModule.utils.formatFileSize(bytes);
};

window.formatDate = function(filename) {
    return BackupModule.utils.formatDate(filename);
};

window.showError = function(message) {
    return BackupModule.utils.showError(message);
};

window.showSuccess = function(message) {
    return BackupModule.utils.showSuccess(message);
};

window.toggleLoading = function(show) {
    return BackupModule.utils.toggleLoading(show);
};

// Inicialização
if (document.readyState === 'complete') {
    BackupModule.init();
} else {
    window.addEventListener('load', () => BackupModule.init());
}
