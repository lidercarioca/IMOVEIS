// Função para verificar se um recurso CSS foi carregado
function isCSSLoaded(href) {
    return Array.from(document.styleSheets).some(styleSheet => {
        try {
            return styleSheet.href === href;
        } catch (e) {
            return false;
        }
    });
}

// Função para carregar recursos com fallback
function loadWithFallback(cdnPath, localPath, type = 'css') {
    return new Promise((resolve, reject) => {
        if (type === 'css') {
            if (!isCSSLoaded(cdnPath)) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = localPath;
                link.onload = resolve;
                link.onerror = reject;
                document.head.appendChild(link);
            } else {
                resolve();
            }
        } else if (type === 'js') {
            const script = document.createElement('script');
            script.src = localPath;
            script.onload = resolve;
            script.onerror = reject;
            document.body.appendChild(script);
        }
    });
}

// Verificar e carregar recursos com fallback
document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Bootstrap CSS
        await loadWithFallback(
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
            '/assets/vendor/bootstrap/bootstrap.min.css'
        );

        // Bootstrap JS
        await loadWithFallback(
            'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
            '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
            'js'
        );

        // Font Awesome
        await loadWithFallback(
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
            '/assets/vendor/fontawesome/all.min.css'
        );
    } catch (error) {
        console.error('Erro ao carregar recursos de fallback:', error);
    }
});