/**
 * Export Manager
 * Gerencia exportação de dados em PDF, Excel, CSV
 */

window.ExportManager = {
    isExporting: false,
    
    /**
     * Inicializa o Export Manager
     */
    init() {
        const exportBtn = document.getElementById('dashboard-export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.showExportModal());
        }
        
        // Event listeners para formulário de export
        const exportForm = document.getElementById('export-dashboard-form');
        if (exportForm) {
            const csvBtn = document.getElementById('export-csv-btn');
            const excelBtn = document.getElementById('export-excel-btn');
            
            if (csvBtn) csvBtn.addEventListener('click', () => this.export('csv'));
            if (excelBtn) excelBtn.addEventListener('click', () => this.export('excel'));
        }
    },
    
    /**
     * Mostra modal de export
     */
    showExportModal() {
        const modal = document.getElementById('export-dashboard-modal');
        if (modal) {
            modal.style.display = 'block';
        }
    },
    
    /**
     * Fecha modal de export
     */
    closeExportModal() {
        const modal = document.getElementById('export-dashboard-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    },
    
    /**
     * Exporta dados
     */
    export(format) {
        if (this.isExporting) return;
        
        this.isExporting = true;
        
        const reportType = document.getElementById('export-report-type')?.value || 'summary';
        const period = document.getElementById('export-period')?.value || 30;
        
        const formData = new FormData();
        formData.append('format', format);
        formData.append('report_type', reportType);
        formData.append('period', period);
        
        const btn = format === 'csv' 
            ? document.getElementById('export-csv-btn')
            : document.getElementById('export-excel-btn');
        
        const originalText = btn?.innerHTML;
        if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exportando...';
        
        fetch('/api/relatorios/exportDashboard.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            // Obter nome do arquivo
            const contentDisposition = response.headers.get('Content-Disposition');
            let filename = `export_${Date.now()}.${format === 'csv' ? 'csv' : 'xls'}`;
            if (contentDisposition) {
                const filenameMatch = contentDisposition.match(/filename="?(.+?)"?$/);
                if (filenameMatch) filename = filenameMatch[1];
            }
            
            return response.blob().then(blob => ({
                blob,
                filename
            }));
        })
        .then(({blob, filename}) => {
            // Criar download
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            // Mostrar sucesso
            this.showSuccess(`Relatório exportado com sucesso: ${filename}`);
            
            this.isExporting = false;
            if (btn) btn.innerHTML = originalText;
        })
        .catch(error => {
            console.error('Erro ao exportar:', error);
            this.showError('Erro ao exportar relatório');
            
            this.isExporting = false;
            if (btn) btn.innerHTML = originalText;
        });
    },
    
    /**
     * Mostra mensagem de sucesso
     */
    showSuccess(message) {
        const alert = document.createElement('div');
        alert.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 z-50';
        alert.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
        document.body.appendChild(alert);
        
        setTimeout(() => alert.remove(), 3000);
    },
    
    /**
     * Mostra mensagem de erro
     */
    showError(message) {
        const alert = document.createElement('div');
        alert.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 z-50';
        alert.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        document.body.appendChild(alert);
        
        setTimeout(() => alert.remove(), 3000);
    }
};

// Inicializa quando o DOM está pronto
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        ExportManager.init();
    }, 500);
});
