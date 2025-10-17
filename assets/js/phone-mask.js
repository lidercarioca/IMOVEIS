document.addEventListener('DOMContentLoaded', function() {
  function mascararTelefone(input) {
    // Remove tudo que não é número
    let value = input.value.replace(/\D/g, '');
    
    // Limita a 11 dígitos
    value = value.substring(0, 11);
    
    // Aplica a máscara
    if (value.length <= 11) {
      value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
      value = value.replace(/(\d)(\d{4})$/, '$1-$2');
    }
    
    input.value = value;
  }

  // Aplica a máscara em todos os inputs de telefone
  const phoneInputs = document.querySelectorAll('input[type="tel"]');
  phoneInputs.forEach(function(input) {
    input.addEventListener('input', function() {
      mascararTelefone(this);
    });

    // Garante que apenas números sejam digitados
    input.addEventListener('keypress', function(e) {
      if(!/\d/.test(e.key)) {
        e.preventDefault();
      }
    });
  });
});