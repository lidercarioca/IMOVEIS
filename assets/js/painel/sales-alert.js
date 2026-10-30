/**
 * Sistema de avisos de vendas pendentes
 * Exibe notificação e alerta ao admin sobre vendas que precisam de transação financeira
 */

document.addEventListener('DOMContentLoaded', async function() {
  // Apenas executa para admins
  if (typeof window.isAdmin === 'undefined' || !window.isAdmin) {
    return;
  }

  // Variável global para controlar o intervalo de polling
  window.pendingSalesPollingInterval = null;
  
  // Carrega as vendas já processadas do localStorage
  window.processedSales = JSON.parse(localStorage.getItem('processedSales') || '[]');

  /**
   * Busca vendas pendentes e exibe aviso
   */
  async function loadPendingSales() {
    try {
      const res = await fetch('/api/v1/reports/getPendingSales.php');
      const data = await res.json();

      if (!data.success) {
        console.error('Erro ao carregar vendas pendentes:', data.error);
        return;
      }

      if (data.count === 0) {
        return; // Nenhuma venda pendente
      }

      // Filtra apenas as vendas que ainda não foram processadas
      const newSales = data.data.filter(sale => 
        !window.processedSales.includes(sale.id)
      );

      if (newSales.length > 0) {
        displaySalesAlert(newSales);
      }
    } catch (err) {
      console.error('Erro ao buscar vendas pendentes:', err);
    }
  }

  /**
   * Exibe alerta visual para vendas pendentes
   */
  function displaySalesAlert(sales) {
    // Cria container para avisos se não existir
    let alertContainer = document.getElementById('sales-alerts-container');
    if (!alertContainer) {
      alertContainer = document.createElement('div');
      alertContainer.id = 'sales-alerts-container';
      alertContainer.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        max-width: 450px;
        max-height: 600px;
        overflow-y: auto;
        z-index: 9998;
        gap: 10px;
        display: flex;
        flex-direction: column;
      `;
      document.body.appendChild(alertContainer);
    }

    // Exibe cada venda como um alerta
    sales.forEach((sale, idx) => {
      setTimeout(() => {
        const alert = createSaleAlert(sale);
        alertContainer.appendChild(alert);
      }, idx * 300); // Escalonado para não aparecer todas ao mesmo tempo
    });
  }

  /**
   * Cria um elemento de alerta para uma venda
   */
  function createSaleAlert(sale) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-warning alert-dismissible fade show';
    alertDiv.setAttribute('data-sale-id', sale.id);
    alertDiv.style.cssText = `
      background: linear-gradient(135deg, #fff3cd 0%, #fffbea 100%);
      border: 2px solid #ffc107;
      border-radius: 8px;
      padding: 16px;
      box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
      min-width: 350px;
      animation: slideInRight 0.3s ease-out;
    `;

    const salePrice = parseFloat(sale.property_price);
    const commission = parseFloat(sale.commission_6percent);
    // Obtém percentual real do banco de dados
    const commissionPercent = sale.hasOwnProperty('sale_commission_percentage') && sale.sale_commission_percentage !== null 
      ? parseFloat(sale.sale_commission_percentage) 
      : null;

    alertDiv.innerHTML = `
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
      <div style="margin-bottom: 10px;">
        <strong style="font-size: 1.1em; color: #856404;">
          <i class="fas fa-exclamation-triangle me-2"></i>Nova Venda Registrada
        </strong>
      </div>
      <div style="background: rgba(255,255,255,0.7); padding: 12px; border-radius: 6px; margin-bottom: 10px;">
        <div style="margin-bottom: 8px;">
          <span style="color: #555; font-weight: 500;">👤 Usuário:</span>
          <span style="color: #333; font-weight: 600;">${escapeHtml(sale.username)}</span>
        </div>
        <div style="margin-bottom: 8px;">
          <span style="color: #555; font-weight: 500;">🏠 Imóvel:</span>
          <span style="color: #333; font-weight: 600;">${escapeHtml(sale.property_title)}</span>
        </div>
        <div style="margin-bottom: 8px;">
          <span style="color: #555; font-weight: 500;">💰 Valor:</span>
          <span style="color: #28a745; font-weight: 600; font-size: 1.05em;">R$ ${formatCurrency(salePrice)}</span>
        </div>
        <div style="border-top: 1px solid #ddd; padding-top: 8px;">
            <span style="color: #555; font-weight: 500;">📊 Comissão (${commissionPercent !== null ? formatPercent(commissionPercent) : '?'}%):</span>
            <span style="color: #d9534f; font-weight: 600; font-size: 1.1em;">R$ ${formatCurrency(commission)}</span>
          </div>
        </div>
    `;

    // Adiciona listener ao botão de fechar para marcar a venda como processada
    const closeBtn = alertDiv.querySelector('.btn-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        try {
          markSaleAsProcessed(sale.id);
        } catch (err) {
          console.error('Erro ao marcar venda como processada:', err);
        }
        alertDiv.remove();
      });
    }

    return alertDiv;
  }

  /**
   * Para o polling de vendas pendentes
   */
  function stopSalesPolling() {
    if (window.pendingSalesPollingInterval) {
      clearInterval(window.pendingSalesPollingInterval);
      window.pendingSalesPollingInterval = null;
    }
  }

  /**
   * Marca uma venda como processada para não ser alertada novamente
   */
  function markSaleAsProcessed(saleId) {
    // Adiciona ao array em memória
    if (!window.processedSales.includes(saleId)) {
      window.processedSales.push(saleId);
    }
    
    // Salva no localStorage para persistir entre sessões
    localStorage.setItem('processedSales', JSON.stringify(window.processedSales));
  }

  /**
   * Formata número como moeda
   */
  function formatCurrency(value) {
    return parseFloat(value).toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  /**
   * Formata percentual removendo zeros desnecessários (ex: 6,00 -> 6%, 6.5 -> 6.5%)
   */
  function formatPercent(value) {
    if (typeof value === 'undefined' || value === null || isNaN(value)) return '';
    const n = parseFloat(value);
    if (Number.isInteger(n)) return n + '%';
    return parseFloat(n.toFixed(2)).toString() + '%';
  }

  /**
   * Escapa caracteres HTML para segurança
   */
  function escapeHtml(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  // Função global para ir para financeiro
  window.goToFinanceiro = function() {
    // Simula um clique na aba Financeiro
    const financeirotab = document.querySelector('a[href="#financeiro"]');
    if (financeirotab) {
      financeirotab.click();
    } else {
      // Fallback: redireciona por URL
      window.location.href = '/painel.php?tab=financeiro';
    }
  };

  // Carrega vendas pendentes ao inicializar
  loadPendingSales();

  // Recarrega a cada 30 segundos para pegar novas vendas
  // Armazena na variável global para poder parar depois
  window.pendingSalesPollingInterval = setInterval(loadPendingSales, 30000);
});

// Adiciona CSS para animação
const style = document.createElement('style');
style.textContent = `
  @keyframes slideInRight {
    from {
      opacity: 0;
      transform: translateX(400px);
    }
    to {
      opacity: 1;
      transform: translateX(0);
    }
  }

  #sales-alerts-container {
    max-height: 80vh;
  }

  #sales-alerts-container .alert {
    transition: all 0.3s ease;
  }

  #sales-alerts-container .alert:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(255, 193, 7, 0.4) !important;
  }
`;
document.head.appendChild(style);
