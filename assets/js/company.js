// Carrega o número do WhatsApp da empresa e expõe globalmente
fetch('api/getCompanySettings.php')
  .then(res => res.json())
  .then(json => {
    if (json.success && json.data && json.data.company_whatsapp) {
      window.company_whatsapp = json.data.company_whatsapp;
    }
  });
