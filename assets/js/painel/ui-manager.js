// Sistema de Notificações
const NotificationSystem = {
    container: document.getElementById('notification-system'),
    show(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `
            alert shadow rounded w-100 p-3 d-flex align-items-center justify-content-between gap-3 position-relative
            border-0 opacity-0 translate-middle-x
        `;

        const iconClass = {
            success: 'text-success fas fa-check-circle',
            error: 'text-danger fas fa-exclamation-circle',
            warning: 'text-warning fas fa-exclamation-triangle',
            info: 'text-primary fas fa-info-circle'
        }[type] || 'text-blue-500 fas fa-info-circle';

        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="${iconClass} fs-5 me-3"></i>
                <p class="mb-0 small">${message}</p>
            </div>
            <button type="button" class="btn-close" aria-label="Close"></button>
        `;

        this.container.appendChild(notification);
        
        // Anima entrada
        requestAnimationFrame(() => {
            notification.classList.remove('translate-middle-x');
        });

        // Fecha ao clicar
        notification.querySelector('button').addEventListener('click', () => {
            this.dismiss(notification);
        });

        // Auto fecha
        if (duration > 0) {
            setTimeout(() => {
                this.dismiss(notification);
            }, duration);
        }
    },

    dismiss(notification) {
        notification.classList.add('translate-middle-x');
        setTimeout(() => {
            notification.remove();
        }, 300);
    },

    success(message, duration) {
        this.show(message, 'success', duration);
    },

    error(message, duration) {
        this.show(message, 'error', duration);
    },

    warning(message, duration) {
        this.show(message, 'warning', duration);
    },

    info(message, duration) {
        this.show(message, 'info', duration);
    }
};

// Gerenciador de Tema
const ThemeManager = {
    init() {
        const toggle = document.getElementById('theme-toggle');
        if (toggle) {
            toggle.addEventListener('click', () => this.toggleTheme());
        }

        // Observa mudanças de preferência do sistema
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.theme) {
                this.setTheme(e.matches ? 'dark' : 'light');
            }
        });
    },

    toggleTheme() {
        if (document.documentElement.classList.contains('dark')) {
            this.setTheme('light');
        } else {
            this.setTheme('dark');
        }
    },

    setTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.classList.add('dark');
            localStorage.theme = 'dark';
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.theme = 'light';
        }
        
        // Notifica a mudança
        NotificationSystem.info(`Tema ${theme === 'dark' ? 'escuro' : 'claro'} ativado`);
    }
};

// Barra de Progresso
const ProgressBar = {
    element: document.getElementById('progress-bar'),
    
    start() {
        this.element.style.transform = 'scaleX(0.3)';
    },
    
    progress(value) {
        this.element.style.transform = `scaleX(${value})`;
    },
    
    finish() {
        this.element.style.transform = 'scaleX(1)';
        setTimeout(() => {
            this.element.style.transform = 'scaleX(0)';
        }, 200);
    }
};

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    ThemeManager.init();
});
