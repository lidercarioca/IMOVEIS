// Carrega o número do WhatsApp da empresa e expõe globalmente
(function () {
    window
        .getCompanySettings()
        .then((json) => {
            if (json && json.success && json.data && json.data.company_whatsapp) {
                window.company_whatsapp = json.data.company_whatsapp;
            }
        })
        .catch(() => {});
})();
