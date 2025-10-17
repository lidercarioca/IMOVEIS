// Gerenciador de cores do sistema
class ColorManager {
    static async initialize() {
        // Previne transi\u00e7\u00f5es durante a inicializa\u00e7\u00e3o
        document.documentElement.classList.add('preload');
        
        try {
            // Carrega cores do localStorage primeiro (se existirem)
            const cachedColors = this.loadCachedColors();
            if (cachedColors) {
                await this.applyColors(cachedColors);
            }
            
            // Busca cores atualizadas da API
            const res = await fetch('api/getCompanySettings.php');
            const json = await res.json();
            
            if (json.success && json.data) {
                const data = json.data;
                await this.applyColors(data);
                this.initializeColorControls();
                
                // Cache das cores para pr\u00f3ximo carregamento
                this.cacheColors(data);
            }
        } catch (error) {
            console.error('Erro ao inicializar cores:', error);
        } finally {
            // Remove a classe preload ap\u00f3s um pequeno delay
            setTimeout(() => {
                document.documentElement.classList.remove('preload');
            }, 100);
        }
    }
    
    static loadCachedColors() {
        try {
            const cached = localStorage.getItem('companyColors');
            return cached ? JSON.parse(cached) : null;
        } catch {
            return null;
        }
    }
    
    static cacheColors(colors) {
        try {
            localStorage.setItem('companyColors', JSON.stringify(colors));
        } catch {
            console.warn('N\u00e3o foi poss\u00edvel cachear as cores');
        }
    }

    static async applyColors(data) {
        if (data.company_color1) {
            await this.setPrimaryColor(data.company_color1);
        }
        if (data.company_color2) {
            await this.setSecondaryColor(data.company_color2);
        }
        if (data.company_color3) {
            await this.setAccentColor(data.company_color3);
        }
        this.broadcastColorUpdate(data);
    }

    static hexToRgb(hex) {
        // Garante que o hex seja válido
        hex = hex.replace(/^#/, '');
        if (!/^[0-9A-Fa-f]{6}$/.test(hex)) {
            console.warn('Cor hexadecimal inválida:', hex);
            return '0, 0, 0';
        }
        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);
        return `${r}, ${g}, ${b}`;
    }

    static calculateHoverColor(hex) {
        // Garante que o hex seja válido
        hex = hex.replace(/^#/, '');
        if (!/^[0-9A-Fa-f]{6}$/.test(hex)) {
            console.warn('Cor hexadecimal inválida para hover:', hex);
            return '#000000';
        }
        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);

        // Escurece a cor em 15% (multiplica por 0.85)
        return `#${Math.floor(r * 0.85).toString(16).padStart(2, '0')}${
            Math.floor(g * 0.85).toString(16).padStart(2, '0')}${
            Math.floor(b * 0.85).toString(16).padStart(2, '0')}`;
    }

    static async setPrimaryColor(color) {
        if (!this.isValidColor(color)) return;

        const root = document.documentElement;
        const rgb = this.hexToRgb(color);
        const hover = this.calculateHoverColor(color);
        const hoverRgb = this.hexToRgb(hover);

        // Define as variáveis CSS
        root.style.setProperty('--cor-primaria', color);
        root.style.setProperty('--cor-primaria-rgb', rgb);
        root.style.setProperty('--cor-primaria-hover', hover);
        root.style.setProperty('--cor-primaria-hover-rgb', hoverRgb);

        // Atualiza elementos com classes Tailwind
        document.querySelectorAll('.bg-blue-600').forEach(el => {
            el.style.backgroundColor = color;
        });
        document.querySelectorAll('.text-blue-600').forEach(el => {
            el.style.color = color;
        });
    }

    static async setSecondaryColor(color) {
        if (!this.isValidColor(color)) return;

        const root = document.documentElement;
        const rgb = this.hexToRgb(color);
        
        root.style.setProperty('--cor-secundaria', color);
        root.style.setProperty('--cor-secundaria-rgb', rgb);

        // Atualiza elementos com classes Tailwind
        document.querySelectorAll('.bg-green-600').forEach(el => {
            el.style.backgroundColor = color;
        });
        document.querySelectorAll('.text-green-600').forEach(el => {
            el.style.color = color;
        });
    }

    static async setAccentColor(color) {
        if (!this.isValidColor(color)) return;

        const root = document.documentElement;
        const rgb = this.hexToRgb(color);
        
        root.style.setProperty('--cor-destaque', color);
        root.style.setProperty('--cor-destaque-rgb', rgb);

        // Atualiza elementos com classes Tailwind
        document.querySelectorAll('.bg-orange-500').forEach(el => {
            el.style.backgroundColor = color;
        });
        document.querySelectorAll('.text-orange-500').forEach(el => {
            el.style.color = color;
        });
    }

    static isValidColor(color) {
        if (!color) {
            console.warn('Cor indefinida');
            return false;
        }
        
        // Aceita cores no formato #RRGGBB
        const isValidHex = /^#[0-9A-Fa-f]{6}$/.test(color);
        if (!isValidHex) {
            console.warn('Formato de cor inválido:', color);
            return false;
        }
        
        return true;
    }

    static initializeColorControls() {
        // Inicializa os controles de cor (color pickers e inputs)
        [1, 2, 3].forEach(i => {
            const preview = document.getElementById(`color_preview_${i}`);
            const picker = document.getElementById(`color_picker_${i}`);
            const input = document.getElementById(`company_color${i}`);

            if (preview && picker && input) {
                // Atualiza preview e campo texto ao mudar picker
                picker.addEventListener('input', () => {
                    const color = picker.value;
                    input.value = color;
                    preview.style.backgroundColor = color;
                    this.updateColorVariable(i, color);
                });

                // Atualiza picker e preview ao mudar campo texto
                input.addEventListener('change', () => {
                    let color = input.value;
                    if (this.isValidColor(color)) {
                        picker.value = color;
                        preview.style.backgroundColor = color;
                        this.updateColorVariable(i, color);
                    }
                });

                // Click no preview abre o color picker
                preview.addEventListener('click', () => {
                    picker.click();
                });
            }
        });
    }

    static updateColorVariable(index, color) {
        if (!this.isValidColor(color)) return;

        switch (index) {
            case 1:
                this.setPrimaryColor(color);
                break;
            case 2:
                this.setSecondaryColor(color);
                break;
            case 3:
                this.setAccentColor(color);
                break;
        }

        // Broadcast da atualização para outras abas/janelas
        this.broadcastColorUpdate({
            [`company_color${index}`]: color
        });
    }

    static broadcastColorUpdate(colors) {
        if ('BroadcastChannel' in window) {
            const canal = new BroadcastChannel('configuracoes_empresa');
            canal.postMessage({
                tipo: 'atualizar_cores',
                ...colors
            });
        }
    }
}

// Inicializa as cores imediatamente
ColorManager.initialize();

// Re-inicializa quando o DOM estiver pronto (caso necessário)
document.addEventListener('DOMContentLoaded', () => ColorManager.initialize());
