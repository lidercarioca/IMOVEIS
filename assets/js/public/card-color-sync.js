// Sistema de sincronização de cores dos cartões
(() => {
  const CardColorSync = {
    observer: null,
    canal: null,
    dados: null,

    init() {
      this.carregarCoresIniciais().then(() => {
        this.iniciarObservador();
        this.iniciarCanal();
      });
    },

    async carregarCoresIniciais() {
      try {
        const response = await fetch('/api/getCompanySettings.php');
        const dados = await response.json();
        
        if (dados.success) {
          this.dados = dados.settings;
          this.aplicarCores();
        }
      } catch (error) {
        console.error('Erro ao carregar cores:', error);
      }
    },

    iniciarObservador() {
      // Configura o observador para monitorar mudanças no DOM
      this.observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          if (mutation.type === 'childList') {
            mutation.addedNodes.forEach((node) => {
              if (node.nodeType === 1) { // Elemento
                this.aplicarCoresNoElemento(node);
              }
            });
          }
        });
      });

      // Inicia a observação do DOM
      this.observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    },

    iniciarCanal() {
      // Cria um canal de broadcast para sincronizar cores entre abas
      this.canal = new BroadcastChannel('syncCores');
      this.canal.onmessage = (event) => {
        if (event.data.type === 'atualizarCores') {
          this.dados = event.data.cores;
          this.aplicarCores();
        }
      };
    },

    aplicarCores() {
      if (!this.dados) return;

      // Aplica as classes dinâmicas aos botões
      document.querySelectorAll('.btn-primary').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-dynamic-primary');
      });

      document.querySelectorAll('.btn-secondary').forEach(btn => {
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-dynamic-secondary');
      });

      document.querySelectorAll('[data-property-type="commercial"] .btn, .btn-accent').forEach(btn => {
        btn.classList.remove('btn-primary', 'btn-secondary', 'btn-accent');
        btn.classList.add('btn-dynamic-highlight');
      });

      // Atualiza variáveis CSS personalizadas
      document.documentElement.style.setProperty('--cor-primaria', this.dados.cor_primaria);
      document.documentElement.style.setProperty('--cor-secundaria', this.dados.cor_secundaria);
      document.documentElement.style.setProperty('--cor-destaque', this.dados.cor_destaque);
    },

    aplicarCoresNoElemento(elemento) {
      if (!this.dados) return;

      // Aplica cores em novos botões
      elemento.querySelectorAll('.btn-primary').forEach(btn => {
        this.aplicarEstilosNoBotao(btn, this.dados.cor_primaria);
      });

      elemento.querySelectorAll('.btn-secondary').forEach(btn => {
        this.aplicarEstilosNoBotao(btn, this.dados.cor_secundaria);
      });

      elemento.querySelectorAll('.btn-accent').forEach(btn => {
        this.aplicarEstilosNoBotao(btn, this.dados.cor_destaque);
      });
    },

    aplicarEstilosNoBotao(botao, cor) {
      if (!cor) return;

      // Remove classes antigas
      botao.className = botao.className.replace(/bg-[^\s]*/g, '')
                                     .replace(/hover:bg-[^\s]*/g, '')
                                     .replace(/border-[^\s]*/g, '')
                                     .replace(/text-[^\s]*/g, '');

      // Aplica a cor como background
      botao.style.backgroundColor = cor;
      botao.style.borderColor = cor;

      // Configura hover
      const corHover = this.ajustarTom(cor, -10);
      botao.addEventListener('mouseenter', () => {
        botao.style.backgroundColor = corHover;
        botao.style.borderColor = corHover;
      });

      botao.addEventListener('mouseleave', () => {
        botao.style.backgroundColor = cor;
        botao.style.borderColor = cor;
      });

      // Ajusta a cor do texto baseado no contraste
      const corTexto = this.getCorTextoContrastante(cor);
      botao.style.color = corTexto;
    },

    ajustarTom(cor, percent) {
      const num = parseInt(cor.replace('#', ''), 16);
      const amt = Math.round(2.55 * percent);
      const R = (num >> 16) + amt;
      const G = (num >> 8 & 0x00FF) + amt;
      const B = (num & 0x0000FF) + amt;

      return '#' + (0x1000000 +
        (R < 255 ? (R < 1 ? 0 : R) : 255) * 0x10000 +
        (G < 255 ? (G < 1 ? 0 : G) : 255) * 0x100 +
        (B < 255 ? (B < 1 ? 0 : B) : 255)
      ).toString(16).slice(1);
    },

    getCorTextoContrastante(cor) {
      const hex = cor.replace('#', '');
      const r = parseInt(hex.slice(0, 2), 16);
      const g = parseInt(hex.slice(2, 4), 16);
      const b = parseInt(hex.slice(4, 6), 16);
      const luminosidade = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
      return luminosidade > 0.5 ? '#000000' : '#FFFFFF';
    }
  };

  // Inicializa o sistema de cores quando o DOM estiver pronto
  document.addEventListener('DOMContentLoaded', () => CardColorSync.init());
})();
