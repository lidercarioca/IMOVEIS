document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    const steps = Array.from(document.querySelectorAll('.wizard-step'));
    const stepTabs = Array.from(document.querySelectorAll('.step-indicator .step'));
    const currentStepLabel = document.getElementById('currentStepLabel');
    const currentStepPercent = document.getElementById('currentStepPercent');
    const progressBar = document.getElementById('wizardProgressBar');
    const nextBtn = document.getElementById('nextStepButton');
    const prevBtn = document.getElementById('prevStepButton');
    const submitBtn = document.getElementById('submitButton');
    const overlay = document.getElementById('submissionOverlay');
    const stepStatusValidate = document.getElementById('stepStatusValidate');
    const stepStatusCreate = document.getElementById('stepStatusCreate');
    const stepStatusSetup = document.getElementById('stepStatusSetup');
    const stepStatusAdmin = document.getElementById('stepStatusAdmin');
    const stepStatusFinalize = document.getElementById('stepStatusFinalize');

    const slugText = document.querySelector('.slug-text');
    const slugUrlPreview = document.getElementById('slugUrlPreview');
    const slugCopy = document.querySelector('.slug-copy');
    const config = window.onboardingConfig || {};
    const baseScheme = config.baseScheme || window.location.protocol.replace(':', '');
    const baseHost = config.baseHost || window.location.host;

    const fields = {
        companyName: document.getElementById('companyName'),
        companyEmail: document.getElementById('companyEmail'),
        companyPhone: document.getElementById('companyPhone'),
        companyWhatsApp: document.getElementById('companyWhatsApp'),
        companyAddress: document.getElementById('companyAddress'),
        companyZipCode: document.getElementById('companyZipCode'),
        companyCity: document.getElementById('companyCity'),
        companyState: document.getElementById('companyState'),
        companyCnpj: document.getElementById('companyCnpj'),
        companyWebsite: document.getElementById('companyWebsite'),
        adminName: document.getElementById('adminName'),
        adminUsername: document.getElementById('adminUsername'),
        adminEmail: document.getElementById('adminEmail'),
        adminPassword: document.getElementById('adminPassword'),
        adminPasswordConfirm: document.getElementById('adminPasswordConfirm'),
    };

    const summaryFields = {
        summaryCompanyName: document.getElementById('summaryCompanyName'),
        summaryCompanyEmail: document.getElementById('summaryCompanyEmail'),
        summaryCompanyPhone: document.getElementById('summaryCompanyPhone'),
        summaryCompanyWhatsApp: document.getElementById('summaryCompanyWhatsApp'),
        summaryCompanyAddress: document.getElementById('summaryCompanyAddress'),
        summaryAdminName: document.getElementById('summaryAdminName'),
        summaryAdminUsername: document.getElementById('summaryAdminUsername'),
        summaryAdminEmail: document.getElementById('summaryAdminEmail'),
    };

    const storageKey = 'onboardingFormDraft';
    let currentStep = 1;

    function generateSlug(text) {
        if (!text) {
            return 'seu-dominio';
        }
        let slug = text.toLowerCase();
        slug = slug.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        slug = slug.replace(/[^a-z0-9]+/g, '');
        return slug.substring(0, 50) || 'seu-dominio';
    }

    function maskPhone(value) {
        const digits = value.replace(/\D/g, '');
        if (digits.length <= 2) {
            return digits;
        }
        if (digits.length <= 6) {
            return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
        }
        if (digits.length <= 10) {
            return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
        }
        return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7, 11)}`;
    }

    function maskZipCode(value) {
        const digits = value.replace(/\D/g, '').slice(0, 8);
        if (digits.length <= 5) {
            return digits;
        }
        return `${digits.slice(0, 5)}-${digits.slice(5)}`;
    }

    function maskCnpj(value) {
        const digits = value.replace(/\D/g, '').slice(0, 14);
        if (digits.length <= 2) {
            return digits;
        }
        if (digits.length <= 5) {
            return `${digits.slice(0, 2)}.${digits.slice(2)}`;
        }
        if (digits.length <= 8) {
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
        }
        if (digits.length <= 12) {
            return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
        }
        return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
    }

    function isEmailValid(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
    }

    function isPhoneValid(value) {
        const digits = value.replace(/\D/g, '');
        return digits.length >= 10 && digits.length <= 11;
    }

    function isZipCodeValid(value) {
        const digits = value.replace(/\D/g, '');
        return digits.length === 8;
    }

    function isCnpjValid(value) {
        const digits = value.replace(/\D/g, '');
        return digits.length === 14;
    }

    function updateSlugPreview() {
        const slug = generateSlug(fields.companyName.value);
        slugText.textContent = slug;
        if (slugUrlPreview) {
            slugUrlPreview.textContent = `${baseScheme}://${slug}.${baseHost}/`;
        }
    }

    function setStep(step) {
        if (step < 1 || step > steps.length) {
            return;
        }
        currentStep = step;
        steps.forEach((node, index) => {
            const stepNumber = index + 1;
            node.classList.toggle('d-none', stepNumber !== step);
        });
        stepTabs.forEach((tab, index) => {
            tab.classList.toggle('active', index + 1 === step);
        });
        const progress = [33, 66, 100][step - 1] || 33;
        progressBar.style.width = `${progress}%`;
        progressBar.setAttribute('aria-valuenow', progress);
        currentStepLabel.textContent = step;
        currentStepPercent.textContent = `${progress}%`;
        prevBtn.disabled = step === 1;
        nextBtn.classList.toggle('d-none', step === steps.length);
        submitBtn.classList.toggle('d-none', step !== steps.length);
        saveFormDraft();
        if (step === 3) {
            updateSummary();
        }
    }

    function validateField(field) {
        let valid = true;
        const value = field.value.trim();
        field.setCustomValidity('');

        if (field.id === 'companyEmail' || field.id === 'adminEmail') {
            if (!value || !isEmailValid(value)) {
                field.setCustomValidity('Informe um email válido.');
                valid = false;
            }
        }

        if (field.id === 'companyPhone') {
            if (!value || !isPhoneValid(value)) {
                field.setCustomValidity('Informe um telefone válido.');
                valid = false;
            }
        }

        if (field.id === 'companyWhatsApp') {
            if (!value || !isPhoneValid(value)) {
                field.setCustomValidity('Informe um WhatsApp válido.');
                valid = false;
            }
        }

        if (field.id === 'companyZipCode' && value) {
            if (!isZipCodeValid(value)) {
                field.setCustomValidity('Informe um CEP válido.');
                valid = false;
            }
        }

        if (field.id === 'companyCnpj' && value) {
            if (!isCnpjValid(value)) {
                field.setCustomValidity('Informe um CNPJ válido.');
                valid = false;
            }
        }

        if (field.id === 'adminPasswordConfirm') {
            if (value !== fields.adminPassword.value.trim()) {
                field.setCustomValidity('As senhas não conferem.');
                valid = false;
            }
        }

        if (field.id === 'adminUsername') {
            if (value.length < 4 || /\s/.test(value)) {
                field.setCustomValidity('Usuário deve ter no mínimo 4 caracteres e sem espaços.');
                valid = false;
            }
        }

        if (!field.checkValidity()) {
            field.classList.add('is-invalid');
        } else {
            field.classList.remove('is-invalid');
        }

        return valid && field.checkValidity();
    }

    function validateStep(step) {
        const stepFields = [];
        if (step === 1) {
            stepFields.push(
                fields.companyName,
                fields.companyEmail,
                fields.companyPhone,
                fields.companyWhatsApp,
                fields.companyZipCode,
                fields.companyCnpj
            );
        }
        if (step === 2) {
            stepFields.push(
                fields.adminName,
                fields.adminUsername,
                fields.adminEmail,
                fields.adminPassword,
                fields.adminPasswordConfirm
            );
        }

        let valid = true;
        stepFields.forEach((field) => {
            if (!validateField(field)) {
                valid = false;
            }
        });

        return valid;
    }

    function updateSummary() {
        summaryFields.summaryCompanyName.textContent = fields.companyName.value.trim() || '-';
        summaryFields.summaryCompanyEmail.textContent = fields.companyEmail.value.trim() || '-';
        summaryFields.summaryCompanyPhone.textContent = fields.companyPhone.value.trim() || '-';
        summaryFields.summaryCompanyWhatsApp.textContent =
            fields.companyWhatsApp.value.trim() || '-';
        const address = [
            fields.companyAddress.value.trim(),
            fields.companyCity.value.trim(),
            fields.companyState.value.trim(),
            fields.companyZipCode.value.trim(),
        ]
            .filter(Boolean)
            .join(', ');
        summaryFields.summaryCompanyAddress.textContent = address || '-';
        summaryFields.summaryAdminName.textContent = fields.adminName.value.trim() || '-';
        summaryFields.summaryAdminUsername.textContent = fields.adminUsername.value.trim() || '-';
        summaryFields.summaryAdminEmail.textContent = fields.adminEmail.value.trim() || '-';
    }

    function showOverlay() {
        overlay.classList.remove('d-none');
        overlay.setAttribute('aria-hidden', 'false');
        stepStatusValidate.classList.add('active');
        [stepStatusCreate, stepStatusSetup, stepStatusAdmin, stepStatusFinalize].forEach((item) =>
            item.classList.remove('active')
        );
        setTimeout(() => stepStatusCreate.classList.add('active'), 250);
        setTimeout(() => stepStatusSetup.classList.add('active'), 550);
        setTimeout(() => stepStatusAdmin.classList.add('active'), 850);
        setTimeout(() => stepStatusFinalize.classList.add('active'), 1100);
    }

    function saveFormDraft() {
        const draft = {
            step: currentStep,
            data: {
                company_name: fields.companyName.value.trim(),
                company_email: fields.companyEmail.value.trim(),
                company_phone: fields.companyPhone.value.trim(),
                company_whatsapp: fields.companyWhatsApp.value.trim(),
                company_address: fields.companyAddress.value.trim(),
                company_zip_code: fields.companyZipCode.value.trim(),
                company_city: fields.companyCity.value.trim(),
                company_state: fields.companyState.value.trim(),
                company_cnpj: fields.companyCnpj.value.trim(),
                company_website: fields.companyWebsite.value.trim(),
                admin_name: fields.adminName.value.trim(),
                admin_username: fields.adminUsername.value.trim(),
                admin_email: fields.adminEmail.value.trim(),
            },
        };
        localStorage.setItem(storageKey, JSON.stringify(draft));
    }

    function restoreFormDraft() {
        try {
            const draft = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (!draft || typeof draft !== 'object') {
                return;
            }

            const data = draft.data || {};
            Object.keys(data).forEach((key) => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    field.value = data[key];
                }
            });

            if (draft.step && Number.isInteger(draft.step)) {
                currentStep = Math.min(Math.max(draft.step, 1), steps.length);
            }
        } catch (e) {
            console.warn('Não foi possível restaurar o formulário do LocalStorage.');
        }
    }

    function togglePassword(targetId) {
        const input = document.getElementById(targetId);
        if (!input) {
            return;
        }
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    nextBtn.addEventListener('click', function () {
        if (!validateStep(currentStep)) {
            return;
        }
        setStep(currentStep + 1);
    });

    prevBtn.addEventListener('click', function () {
        setStep(currentStep - 1);
    });

    submitBtn.addEventListener('click', function () {
        if (!validateStep(currentStep)) {
            return;
        }
        if (currentStep !== steps.length) {
            return;
        }
        saveFormDraft();
        showOverlay();
        nextBtn.disabled = true;
        prevBtn.disabled = true;
        submitBtn.disabled = true;
        localStorage.removeItem(storageKey);
        setTimeout(() => form.submit(), 700);
    });

    slugCopy.addEventListener('click', function () {
        const text = slugText.textContent.trim();
        navigator.clipboard.writeText(text).then(() => {
            const originalText = this.textContent;
            this.textContent = '✓';
            setTimeout(() => {
                this.textContent = originalText;
            }, 1500);
        });
    });

    document.querySelectorAll('.toggle-password-btn').forEach((button) => {
        button.addEventListener('click', function () {
            togglePassword(this.dataset.target);
        });
    });

    Object.values(fields).forEach((field) => {
        if (!field) {
            return;
        }
        field.addEventListener('input', function () {
            if (this.id === 'companyPhone' || this.id === 'companyWhatsApp') {
                this.value = maskPhone(this.value);
            }
            if (this.id === 'companyZipCode') {
                this.value = maskZipCode(this.value);
            }
            if (this.id === 'companyCnpj') {
                this.value = maskCnpj(this.value);
            }
            if (this.id === 'companyName') {
                updateSlugPreview();
            }
            validateField(this);
            saveFormDraft();
        });
    });

    fields.adminPassword.addEventListener('input', function () {
        const strengthText = document.getElementById('passwordStrengthText');
        const fill = document.getElementById('strengthMeterFill');
        const value = this.value;
        const score = [/.{8,}/, /[A-Z]/, /[a-z]/, /\d/, /[^A-Za-z0-9]/].reduce(
            (acc, regex) => acc + (regex.test(value) ? 1 : 0),
            0
        );
        const percent = (score / 5) * 100;
        fill.style.width = `${percent}%`;
        fill.style.backgroundColor =
            score <= 1
                ? '#dc2626'
                : score === 2
                  ? '#f59e0b'
                  : score === 3
                    ? '#fbbf24'
                    : score === 4
                      ? '#22c55e'
                      : '#16a34a';
        strengthText.textContent =
            score <= 1
                ? 'Muito fraca'
                : score === 2
                  ? 'Fraca'
                  : score === 3
                    ? 'Boa'
                    : score === 4
                      ? 'Forte'
                      : 'Muito forte';
        validateField(this);
    });

    form.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            const activeSubmit = currentStep === steps.length ? submitBtn : nextBtn;
            if (activeSubmit) {
                event.preventDefault();
                activeSubmit.click();
            }
        }
    });

    restoreFormDraft();
    updateSlugPreview();
    setStep(currentStep);
});
