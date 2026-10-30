/**
 * Sales Commission Percentage Field Controller
 * Handles visibility and edit permissions for the sale_commission_percentage field
 * Only admins can edit this field, but all users can view it
 */

class SalesCommissionController {
  constructor() {
    this.fieldId = 'sale_commission_percentage';
    this.isAdmin = this.checkIsAdmin();
    this.init();
  }

  /**
   * Check if current user is admin
   * This check is done by verifying the current user role from the session/localStorage
   */
  checkIsAdmin() {
    // Get from localStorage or window variable set by server
    if (window.userRole === 'admin' || window.isAdmin === true) {
      return true;
    }
    if (localStorage.getItem('userRole') === 'admin') {
      return true;
    }
    return false;
  }

  /**
   * Initialize the field controller
   */
  init() {
    const field = document.getElementById(this.fieldId);
    if (!field) return;

    // Field is always visible
    field.parentElement.style.display = 'block';

    if (this.isAdmin) {
      // Admins can edit
      field.disabled = false;
      field.classList.remove('opacity-50', 'cursor-not-allowed');
      field.title = 'Campo editável para administradores';
    } else {
      // Non-admins can only view
      field.disabled = true;
      field.classList.add('opacity-50', 'cursor-not-allowed');
      field.title = 'Apenas administradores podem editar este campo';
      
      // Add visual indicator
      const label = document.querySelector(`label[for="${this.fieldId}"]`);
      if (label) {
        const adminSpan = label.querySelector('span');
        if (!adminSpan) {
          const span = document.createElement('span');
          span.className = 'text-red-600 ml-1';
          span.textContent = '(Somente Leitura)';
          label.appendChild(span);
        }
      }
    }
  }

  /**
   * Update admin status (useful if checking via API)
   */
  setIsAdmin(isAdmin) {
    this.isAdmin = isAdmin;
    this.init();
  }

  /**
   * Validate commission percentage (0-100)
   */
  validateValue(value) {
    if (value === '' || value === null) return true;
    const num = parseFloat(value);
    return !isNaN(num) && num >= 0 && num <= 100;
  }

  /**
   * Format display of commission percentage
   */
  formatDisplay(value) {
    if (!value) return '—';
    return `${parseFloat(value).toFixed(2)}%`;
  }

  /**
   * Reinitialize the controller (useful after form reload via AJAX)
   */
  static reinit() {
    if (window.salesCommissionController) {
      window.salesCommissionController.init();
    } else {
      window.salesCommissionController = new SalesCommissionController();
    }
  }
}

// Initialize on document ready
document.addEventListener('DOMContentLoaded', () => {
  window.salesCommissionController = new SalesCommissionController();
});

// Also reinit when the form is loaded via AJAX (called from painel-main.js)
// Listen for custom event that will be triggered when form is loaded
document.addEventListener('propertyFormLoaded', () => {
  SalesCommissionController.reinit();
});
