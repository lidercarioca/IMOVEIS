document.addEventListener('DOMContentLoaded', async function () {
    const companyNameElement = document.getElementById('company-name');
    const companyNameFooter = document.getElementById('company-name-footer');
    const aboutTitle = document.getElementById('about-title');
    const aboutCompanyName = document.getElementById('about-company-name');

    // Se nenhum dos elementos existir, não continua
    if (!companyNameElement && !companyNameFooter && !aboutTitle && !aboutCompanyName) {
        return;
    }

    try {
        let data = null;

        const json = await window.getCompanySettings();
        if (json && json.success && json.data) {
            data = json.data;
        }

        if (data && data.company_name) {
            // Atualiza os elementos se eles existirem
            if (companyNameElement) {
                companyNameElement.textContent = data.company_name;
            }
            if (companyNameFooter) {
                companyNameFooter.textContent = data.company_name;
            }
            if (aboutTitle) {
                aboutTitle.textContent = `Sobre a ${data.company_name}`;
            }
            if (aboutCompanyName) {
                aboutCompanyName.textContent = data.company_name;
            }

            // Atualiza todos os elementos com classe company-name-testimonial
            document.querySelectorAll('.company-name-testimonial').forEach((el) => {
                el.textContent = data.company_name;
            });

            // Atualiza elemento de nome da empresa nos depoimentos
            const testimonialCompanyName = document.getElementById('testimonial-company-name');
            if (testimonialCompanyName) {
                testimonialCompanyName.textContent = data.company_name;
            }

            // Atualiza elemento na descrição
            document.querySelectorAll('.company-name-description').forEach((el) => {
                el.textContent = data.company_name;
            });

            // Atualiza atributos alt das imagens com template
            document.querySelectorAll('[data-alt-template]').forEach((el) => {
                const template = el.getAttribute('data-alt-template');
                const newAlt = template.replace('{company}', data.company_name);
                el.setAttribute('alt', newAlt);
            });

            // Atualiza também o título da página
            document.title = `${data.company_name} - Seu Imóvel dos Sonhos`;
        } else {
            throw new Error('Nome da empresa não encontrado nas configurações');
        }
    } catch (error) {
        // Silencioso - fallback para padrão
        const defaultName = 'Sua Imobiliária';
        if (companyNameElement) {
            companyNameElement.textContent = defaultName;
        }
        if (companyNameFooter) {
            companyNameFooter.textContent = defaultName;
        }
        if (aboutTitle) {
            aboutTitle.textContent = `Sobre a ${defaultName}`;
        }
        if (aboutCompanyName) {
            aboutCompanyName.textContent = defaultName;
        }

        // Atualiza todos os elementos com classe company-name-testimonial
        document.querySelectorAll('.company-name-testimonial').forEach((el) => {
            el.textContent = defaultName;
        });

        // Atualiza elemento de nome da empresa nos depoimentos
        const testimonialCompanyName = document.getElementById('testimonial-company-name');
        if (testimonialCompanyName) {
            testimonialCompanyName.textContent = defaultName;
        }

        // Atualiza elemento na descrição
        document.querySelectorAll('.company-name-description').forEach((el) => {
            el.textContent = defaultName;
        });

        // Atualiza atributos alt das imagens com template
        document.querySelectorAll('[data-alt-template]').forEach((el) => {
            const template = el.getAttribute('data-alt-template');
            const newAlt = template.replace('{company}', defaultName);
            el.setAttribute('alt', newAlt);
        });
    }
});

// Sistema para atualizar em tempo real
if ('BroadcastChannel' in window) {
    const channel = new BroadcastChannel('configuracoes_empresa');
    channel.onmessage = (event) => {
        if (event.data && event.data.tipo === 'atualizar_nome') {
            const companyNameElement = document.getElementById('company-name');
            const aboutTitle = document.getElementById('about-title');
            const aboutCompanyName = document.getElementById('about-company-name');
            const testimonialCompanyName = document.getElementById('testimonial-company-name');

            if (companyNameElement && event.data.nome) {
                companyNameElement.textContent = event.data.nome;
                document.title = `${event.data.nome} - Seu Imóvel dos Sonhos`;
            }
            if (aboutTitle && event.data.nome) {
                aboutTitle.textContent = `Sobre a ${event.data.nome}`;
            }
            if (aboutCompanyName && event.data.nome) {
                aboutCompanyName.textContent = event.data.nome;
            }
            if (testimonialCompanyName && event.data.nome) {
                testimonialCompanyName.textContent = event.data.nome;
            }
            if (event.data.nome) {
                const nome = event.data.nome;

                document
                    .querySelectorAll('.company-name-testimonial, .company-name-description')
                    .forEach((el) => {
                        el.textContent = nome;
                    });

                document.querySelectorAll('[data-alt-template]').forEach((el) => {
                    const template = el.getAttribute('data-alt-template');
                    const newAlt = template.replace('{company}', nome);
                    el.setAttribute('alt', newAlt);
                });
            }
        }
    };
}
