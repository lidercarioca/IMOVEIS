// assets/js/contato-dinamico.js
// Substitui as informações de contato fixas pelo conteúdo do backend

document.addEventListener('DOMContentLoaded', async function() {
  // Só executa se existir o bloco de Informações de Contato ou os links do footer
  const contatoBloco = document.querySelector('.info-contato-dinamico');
  const footerSocialLinks = document.querySelectorAll('[id^="link-"]');
  if (!contatoBloco && footerSocialLinks.length === 0) return;

  try {
    const res = await fetch('/api/getCompanySettings.php');
    const json = await res.json();
    if (!json.success || !json.data) return;
    const data = json.data;

    // Monta HTML dinâmico
    let html = '';
    html += `<div class="d-flex align-items-start mb-4">
              <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                <i class="fas fa-map-marker-alt text-primary"></i>
              </div>
              <div>
                <h4 class="fw-semibold text-dark">Endereço</h4>
                <p class="text-secondary">${data.company_address ? data.company_address.replace(/\n/g, '<br>') : ''}</p>
              </div>
            </div>`;
    html += `<div class="d-flex align-items-start mb-4">
              <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                <i class="fas fa-phone-alt text-primary"></i>
              </div>
              <div>
                <h4 class="fw-semibold text-dark">Telefone</h4>
                <p class="text-secondary">${data.company_phone || ''}</p>
                ${data.company_whatsapp ? `<p class='text-secondary'>${data.company_whatsapp}</p>` : ''}
              </div>
            </div>`;
    html += `<div class="d-flex align-items-start mb-4">
              <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                <i class="fas fa-envelope text-primary"></i>
              </div>
              <div>
                <h4 class="fw-semibold text-dark">E-mail</h4>
                <p class="text-secondary">${data.company_email || ''}</p>
                ${data.company_email2 ? `<p class='text-secondary'>${data.company_email2}</p>` : ''}
              </div>
            </div>`;
   // Exibe horários de atendimento usando os campos corretos
let horarioHtml = '';
if (data.company_weekday_hours || data.company_saturday_hours) {
  if (data.company_weekday_hours) {
    horarioHtml += `Segunda a Sexta: ${data.company_weekday_hours}<br>`;
  }
  if (data.company_saturday_hours) {
    horarioHtml += `Sábado: ${data.company_saturday_hours}`;
  }
} else {
  horarioHtml = 'Segunda a Sexta: 9h às 18h<br>Sábado: 9h às 13h';
}

html += `<div class="d-flex align-items-start mb-4">
  <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
    <i class="fas fa-clock text-primary"></i>
  </div>
  <div>
    <h4 class="fw-semibold text-dark">Horário de Atendimento</h4>
    <p class="text-secondary">${horarioHtml}</p>
  </div>
</div>`;

contatoBloco.innerHTML = html;

    // ...existing code...

// Preencher links das redes sociais dinamicamente
const socialLinks = document.getElementById('social-links-dinamico');
if (socialLinks) {
  let htmlLinks = '<div class="d-flex gap-1">';
  if (data.company_facebook) {
    htmlLinks += `<a href="${data.company_facebook}" target="_blank" rel="noopener" class="bg-primary bg-opacity-10 rounded-circle p-3 me-2 d-inline-flex align-items-center justify-content-center text-primary"><i class="fab fa-facebook-f"></i></a>`;
  }
  if (data.company_instagram) {
    htmlLinks += `<a href="${data.company_instagram}" target="_blank" rel="noopener" class="bg-primary bg-opacity-10 rounded-circle p-3 me-2 d-inline-flex align-items-center justify-content-center text-primary "><i class="fab fa-instagram"></i></a>`;
  }
  if (data.company_linkedin) {
    htmlLinks += `<a href="${data.company_linkedin}" target="_blank" rel="noopener" class="bg-primary bg-opacity-10 rounded-circle p-3 me-2 d-inline-flex align-items-center justify-content-center text-primary "><i class="fab fa-linkedin-in"></i></a>`;
  }
  if (data.company_youtube) {
    htmlLinks += `<a href="${data.company_youtube}" target="_blank" rel="noopener" class="bg-primary bg-opacity-10 rounded-circle p-3 me-2 d-inline-flex align-items-center justify-content-center text-primary "><i class="fab fa-youtube"></i></a>`;
  }
  htmlLinks += '</div>';
  
  if (htmlLinks === '<div class="d-flex gap-1"></div>') {
    htmlLinks = '<div class="text-secondary">Nenhuma rede social cadastrada.</div>';
  }
  socialLinks.innerHTML = htmlLinks;
}

// ...existing code...

    // Atualiza os links do footer
    const footerLinks = {
      'link-facebook-footer': data.company_facebook,
      'link-instagram-footer': data.company_instagram,
      'link-linkedin-footer': data.company_linkedin,
      'link-youtube-footer': data.company_youtube
    };

    Object.entries(footerLinks).forEach(([id, url]) => {
      const link = document.getElementById(id);
      if (link) {
        if (url) {
          link.href = url;
          link.style.display = 'inline-flex';
          link.target = '_blank';
          link.rel = 'noopener';
          
          // Adiciona evento de clique
          link.onclick = function(e) {
            e.preventDefault();
            window.open(this.href, '_blank', 'noopener');
          };
        } else {
          link.style.display = 'none';
        }
      }
    });

  } catch (err) {
    console.error('Erro ao carregar dados:', err);
  }
});
