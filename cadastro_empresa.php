<?php

/**
 * Página de Cadastro de Nova Empresa
 * 
 * Formulário para onboarding de novos clientes SaaS
 * Reutiliza a estrutura existente do sistema
 */

require_once 'config/session_manager.php';
require_once 'app/security/Security.php';
require_once 'app/security/CSRF.php';
require_once 'config/load_env.php';

// Se já está logado, redireciona para painel
if (isset($_SESSION['user_id'])) {
    header("Location: painel.php");
    exit;
}

// Gera token CSRF
$csrfToken = CSRF::generateToken();

// Base para URLs públicas de tenant
$siteUrl = getenv('SITE_URL') ?: getenv('NGROK_URL') ?: 'http://localhost';
$parsedSiteUrl = parse_url($siteUrl);
$baseHost = $parsedSiteUrl['host'] ?? $siteUrl;
$baseHost = preg_replace('/^www\./i', '', $baseHost);
$baseScheme = (stripos($baseHost, 'localhost') !== false || stripos($baseHost, '127.0.0.1') !== false) ? 'http' : 'https';

// Capturar mensagem de erro da URL se existir
$error = $_GET['error'] ?? '';
$error = '';
$success = '';
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Empresa - RR Imóveis SaaS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: var(--bs-primary);
            --primary-dark: var(--bs-primary-dark);
            --primary-light: var(--bs-primary-light);
        }

        body {
            background-image: url('assets/imagens/background-form.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(4px);
            z-index: 0;
        }

        .container-register {
            position: relative;
            z-index: 1;
            max-width: 700px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .register-card {
            background: #fff;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .register-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 45%, #111827 100%);
            color: #ffffff;
            padding: 2.5rem 2rem;
            text-align: center;
            position: relative;
            z-index: 1;
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.08);
        }

        .register-header h2 {
            margin-bottom: 0.5rem;
            font-weight: 800;
            font-size: 1.75rem;
            color: #ffffff;
            text-shadow: 0 2px 18px rgba(0, 0, 0, 0.35);
        }

        .register-header p {
            margin: 0;
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.92);
            text-shadow: 0 1px 8px rgba(0, 0, 0, 0.18);
        }

        .register-body {
            padding: 2.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--bs-gray-700);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-label .required {
            color: #dc3545;
            margin-left: 0.25rem;
        }

        .form-control {
            border: 1px solid var(--bs-gray-300);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.25);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        .invalid-feedback {
            display: block;
            margin-top: 0.4rem;
            font-size: 0.88rem;
            color: #dc3545;
        }

        .section-card {
            background: #f8fbff;
            border: 1px solid rgba(59, 130, 246, 0.08);
            border-radius: 1rem;
            padding: 1.75rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.04);
        }

        .input-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
        }

        .input-grid .form-group {
            margin-bottom: 1.25rem;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(59, 130, 246, 0.14);
            border-radius: 1.5rem;
            padding: 2rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 24px 70px rgba(32, 45, 85, 0.08);
            backdrop-filter: blur(12px);
        }

        .step-indicator {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.55);
            color: #475569;
            font-size: 0.9rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            transition: all 0.2s ease;
        }

        .summary-card {
            margin: 1.75rem auto 0;
            padding: 1.4rem 1.6rem;
            background: #ffffff;
            border: 1px solid rgba(99, 102, 241, 0.18);
            border-radius: 1.25rem;
            color: #0f172a;
            text-align: left;
            max-width: 620px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .summary-card strong {
            display: block;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            color: #111827;
        }

        .summary-card ul {
            margin: 0;
            padding-left: 1.25rem;
            list-style: disc;
            color: #334155;
        }

        .summary-card li {
            margin-bottom: 0.5rem;
            line-height: 1.6;
            color: #334155;
        }

        .step.active {
            background: linear-gradient(135deg, #3b82f6, #6366f1);
            color: white;
            border-color: rgba(59, 130, 246, 0.35);
            box-shadow: 0 12px 30px rgba(59, 130, 246, 0.18);
        }

        .step span {
            display: inline-flex;
            width: 1.85rem;
            height: 1.85rem;
            border-radius: 999px;
            align-items: center;
            justify-content: center;
            background: rgba(59, 130, 246, 0.12);
            color: #2563eb;
            font-weight: 700;
        }

        .form-control {
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 0.95rem;
            padding: 0.95rem 1rem;
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background: #ffffff;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
            color: #0f172a;
        }

        .invalid-feedback {
            color: #dc2626;
            font-size: 0.9rem;
            margin-top: 0.35rem;
        }

        @media (max-width: 900px) {
            .input-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .register-body {
                padding: 1.5rem;
            }

            .register-header {
                padding: 2rem 1.5rem;
            }
        }

        .btn-register {
            background: linear-gradient(135deg, #4f46e5, #2563eb);
            border: none;
            color: white;
            font-weight: 700;
            padding: 1rem 1.6rem;
            border-radius: 1.25rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            width: 100%;
            font-size: 1rem;
            letter-spacing: 0.02em;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(59, 130, 246, 0.25);
            color: white;
        }

        .btn-register:active {
            transform: translateY(0);
        }

        .link-login {
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .link-login a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 700;
        }

        .link-login a:hover {
            text-decoration: underline;
        }

        .header-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.9rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.18);
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .alert {
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 18px 35px rgba(15, 23, 42, 0.06);
        }

        .section-title {
            font-size: 0.82rem;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
        }

        .section-title::before {
            content: '';
            width: 30px;
            height: 3px;
            background: linear-gradient(90deg, #2563eb, #3b82f6);
            border-radius: 999px;
        }

        .password-requirements {
            font-size: 0.86rem;
            color: #475569;
            margin-top: 0.65rem;
        }

        .password-requirements ul {
            margin: 0.5rem 0 0 1.1rem;
            padding: 0;
            list-style: disc;
        }

        .password-requirements li {
            margin-bottom: 0.25rem;
            line-height: 1.5;
        }

        .strength-meter {
            height: 4px;
            background-color: #e2e8f0;
            border-radius: 999px;
            overflow: hidden;
        }

        .strength-meter-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }

        .slug-preview {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(148, 163, 184, 0.18);
        }

        .slug-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
            display: block;
            margin-bottom: 0.75rem;
        }

        .slug-display {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1rem;
            background: #f1f5f9;
            border-radius: 0.85rem;
            border: 1px solid rgba(148, 163, 184, 0.25);
        }

        .slug-text {
            flex: 1;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.95rem;
            color: #0f172a;
            font-weight: 500;
            word-break: break-all;
        }

        .slug-copy {
            cursor: pointer;
            font-size: 1.1rem;
            opacity: 0.7;
            transition: opacity 0.2s ease;
            flex-shrink: 0;
        }

        .slug-copy:hover {
            opacity: 1;
        }

        .wizard-step {
            transition: opacity 0.3s ease;
        }

        .wizard-actions {
            gap: 0.75rem;
        }

        .btn-step {
            min-width: 120px;
        }

        .toggle-password-btn {
            position: absolute;
            top: 38px;
            right: 1rem;
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 1rem;
            cursor: pointer;
            padding: 0.2rem 0.35rem;
        }

        .toggle-password-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .submission-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            padding: 1.5rem;
        }

        .submission-overlay.d-none {
            display: none;
        }

        .submission-card {
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 2rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 24px 75px rgba(15, 23, 42, 0.18);
        }

        .submission-steps li {
            margin-bottom: 0.7rem;
            color: #475569;
        }

        .submission-steps li.active {
            color: #0f172a;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="container-register">
        <div class="register-card">
            <div class="register-header">
                <div class="header-tag">Cadastro de empresa</div>
                <h2>Inicie seu SaaS imobiliário em poucos passos</h2>
                <p>Um formulário moderno, simples e seguro para você começar sua empresa na plataforma.</p>
                <div class="wizard-progress mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="text-white-50">Passo <strong id="currentStepLabel">1</strong> de 3</div>
                        <div class="text-white-50" id="currentStepPercent">33%</div>
                    </div>
                    <div class="progress" style="height: 12px; border-radius: 999px; background: rgba(255,255,255,0.22);">
                        <div id="wizardProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-light" role="progressbar" style="width: 33%;" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <div class="step-indicator">
                    <div class="step active"><span>1</span>Empresa</div>
                    <div class="step"><span>2</span>Administrador</div>
                    <div class="step"><span>3</span>Confirmação</div>
                </div>
                <div class="summary-card">
                    <strong>O que será criado:</strong>
                    <ul>
                        <li>Empresa no sistema com 7 dias de período de avaliação</li>
                        <li>Conta de administrador com acesso completo</li>
                        <li>Configuração inicial pronta para ser usada</li>
                    </ul>
                </div>
            </div>

            <div class="register-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>❌ Erro:</strong> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form id="registerForm" method="POST" action="cadastro_empresa_action.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="wizard-step active" data-step="1" aria-label="Passo 1: Empresa">
                        <div class="section-title">📋 Empresa</div>
                        <div class="section-card">
                            <div class="form-group">
                                <label for="companyName" class="form-label">Nome da Empresa <span class="required">*</span></label>
                                <input type="text" id="companyName" name="company_name" class="form-control" placeholder="Ex: Soluções Imobiliárias Ltda" required minlength="3" maxlength="100" autocomplete="organization">
                                <div class="invalid-feedback">O nome da empresa deve ter entre 3 e 100 caracteres.</div>
                            </div>

                            <div class="input-grid">
                                <div class="form-group">
                                    <label for="companyEmail" class="form-label">Email da Empresa <span class="required">*</span></label>
                                    <input type="email" id="companyEmail" name="company_email" class="form-control" placeholder="Ex: contato@empresa.com" autocomplete="email">
                                    <div class="invalid-feedback">Informe um email válido.</div>
                                </div>

                                <div class="form-group">
                                    <label for="companyPhone" class="form-label">Telefone <span class="required">*</span></label>
                                    <input type="tel" id="companyPhone" name="company_phone" class="form-control" placeholder="(11) 9999-9999" autocomplete="tel">
                                    <div class="invalid-feedback">Informe um telefone válido com DDD.</div>
                                </div>

                                <div class="form-group">
                                    <label for="companyWhatsApp" class="form-label">WhatsApp <span class="required">*</span></label>
                                    <input type="tel" id="companyWhatsApp" name="company_whatsapp" class="form-control" placeholder="(11) 9 9999-9999" autocomplete="tel">
                                    <div class="invalid-feedback">Informe um WhatsApp válido com DDD.</div>
                                </div>
                            </div>

                            <div class="input-grid">
                                <div class="form-group">
                                    <label for="companyAddress" class="form-label">Endereço</label>
                                    <input type="text" id="companyAddress" name="company_address" class="form-control" placeholder="Rua, número, bairro" autocomplete="street-address">
                                </div>

                                <div class="form-group">
                                    <label for="companyZipCode" class="form-label">CEP</label>
                                    <input type="text" id="companyZipCode" name="company_zip_code" class="form-control" placeholder="00000-000" autocomplete="postal-code">
                                    <div class="invalid-feedback">Informe um CEP válido ou deixe em branco.</div>
                                </div>
                            </div>

                            <div class="input-grid">
                                <div class="form-group">
                                    <label for="companyCity" class="form-label">Cidade</label>
                                    <input type="text" id="companyCity" name="company_city" class="form-control" placeholder="Ex: São Paulo" autocomplete="address-level2">
                                </div>

                                <div class="form-group">
                                    <label for="companyState" class="form-label">Estado</label>
                                    <input type="text" id="companyState" name="company_state" class="form-control" placeholder="Ex: SP" autocomplete="address-level1">
                                </div>
                            </div>

                            <div class="input-grid">
                                <div class="form-group">
                                    <label for="companyCnpj" class="form-label">CNPJ</label>
                                    <input type="text" id="companyCnpj" name="company_cnpj" class="form-control" placeholder="00.000.000/0000-00" autocomplete="off">
                                </div>

                                <div class="form-group">
                                    <label for="companyWebsite" class="form-label">Site da Empresa</label>
                                    <input type="url" id="companyWebsite" name="company_website" class="form-control" placeholder="https://www.empresa.com.br" autocomplete="url">
                                </div>
                            </div>

                            <div class="slug-preview">
                                <label class="slug-label">Subdomínio da empresa:</label>
                                <div class="slug-display">
                                    <span class="slug-text">seudominio</span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary slug-copy" title="Copiar subdomínio">📋</button>
                                </div>
                                <div class="slug-url-preview">
                                    <span class="slug-url-label">URL pública:</span>
                                    <span class="slug-url-text" id="slugUrlPreview"><?php echo htmlspecialchars($baseScheme . '://' . 'seu-dominio' . '.' . $baseHost . '/'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-step d-none" data-step="2" aria-label="Passo 2: Administrador">
                        <div class="section-title">👤 Administrador</div>
                        <div class="section-card">
                            <div class="input-grid">
                                <div class="form-group">
                                    <label for="adminName" class="form-label">Nome <span class="required">*</span></label>
                                    <input type="text" id="adminName" name="admin_name" class="form-control" placeholder="Ex: João Silva" required minlength="3" maxlength="100" autocomplete="name">
                                    <div class="invalid-feedback">O nome deve ter entre 3 e 100 caracteres.</div>
                                </div>

                                <div class="form-group">
                                    <label for="adminUsername" class="form-label">Usuário <span class="required">*</span></label>
                                    <input type="text" id="adminUsername" name="admin_username" class="form-control" placeholder="Ex: joao_silva" required minlength="4" maxlength="50" pattern="[a-zA-Z0-9_\-]+" autocomplete="username">
                                    <div class="invalid-feedback">Usuário deve ter no mínimo 4 caracteres e sem espaços.</div>
                                </div>

                                <div class="form-group">
                                    <label for="adminEmail" class="form-label">Email <span class="required">*</span></label>
                                    <input type="email" id="adminEmail" name="admin_email" class="form-control" placeholder="Ex: admin@empresa.com" required autocomplete="email">
                                    <div class="invalid-feedback">Informe um email válido.</div>
                                </div>
                            </div>

                            <div class="input-grid">
                                <div class="form-group position-relative">
                                    <label for="adminPassword" class="form-label">Senha <span class="required">*</span></label>
                                    <input type="password" id="adminPassword" name="admin_password" class="form-control" placeholder="Digite uma senha forte" required minlength="8" autocomplete="new-password">
                                    <button type="button" class="toggle-password-btn" data-target="adminPassword" aria-label="Mostrar senha">👁</button>
                                    <div class="invalid-feedback">A senha deve ter no mínimo 8 caracteres.</div>
                                    <div class="password-requirements">
                                        <strong>Força da senha:</strong>
                                        <div class="strength-meter">
                                            <div id="strengthMeterFill" class="strength-meter-fill"></div>
                                        </div>
                                        <div id="passwordStrengthText" class="text-muted" style="font-size:0.88rem; margin-top:0.4rem;">Digite a senha para ver a força</div>
                                    </div>
                                </div>

                                <div class="form-group position-relative">
                                    <label for="adminPasswordConfirm" class="form-label">Confirmar Senha <span class="required">*</span></label>
                                    <input type="password" id="adminPasswordConfirm" name="admin_password_confirm" class="form-control" placeholder="Repita a senha" required autocomplete="new-password">
                                    <button type="button" class="toggle-password-btn" data-target="adminPasswordConfirm" aria-label="Mostrar senha">👁</button>
                                    <div class="invalid-feedback">As senhas não conferem.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-step d-none" data-step="3" aria-label="Passo 3: Confirmação">
                        <div class="section-title">✅ Confirmação</div>
                        <div class="section-card summary-card">
                            <strong>Verifique antes de criar</strong>
                            <ul>
                                <li><strong>Empresa:</strong> <span id="summaryCompanyName">-</span></li>
                                <li><strong>Email empresa:</strong> <span id="summaryCompanyEmail">-</span></li>
                                <li><strong>Telefone:</strong> <span id="summaryCompanyPhone">-</span></li>
                                <li><strong>WhatsApp:</strong> <span id="summaryCompanyWhatsApp">-</span></li>
                                <li><strong>Endereço:</strong> <span id="summaryCompanyAddress">-</span></li>
                                <li><strong>Administrador:</strong> <span id="summaryAdminName">-</span></li>
                                <li><strong>Usuário:</strong> <span id="summaryAdminUsername">-</span></li>
                                <li><strong>Email admin:</strong> <span id="summaryAdminEmail">-</span></li>
                                <li><strong>Plano:</strong> Período de avaliação</li>
                            </ul>
                        </div>
                        <div class="alert alert-info" role="alert">
                            Após criar a empresa, você será direcionado ao painel automaticamente.
                        </div>
                    </div>

                    <div class="wizard-actions d-flex justify-content-between align-items-center mt-4">
                        <button type="button" id="prevStepButton" class="btn btn-outline-secondary btn-step" disabled>Voltar</button>
                        <div class="d-flex gap-3">
                            <button type="button" id="nextStepButton" class="btn btn-outline-primary btn-step">Próximo</button>
                            <button type="button" id="submitButton" class="btn btn-register d-none">Criar Empresa</button>
                        </div>
                    </div>
                </form>

                <div id="submissionOverlay" class="submission-overlay d-none" aria-hidden="true">
                    <div class="submission-card text-center">
                        <div class="spinner-border text-primary mb-3" role="status"><span class="visually-hidden">Carregando...</span></div>
                        <h4>Criando empresa...</h4>
                        <p class="text-muted mb-4">Aguarde enquanto validamos os dados e preparamos seu ambiente.</p>
                        <ul class="list-unstyled submission-steps text-start mx-auto" style="max-width:320px;">
                            <li id="stepStatusValidate">✓ Validando dados</li>
                            <li id="stepStatusCreate">⏳ Criando empresa</li>
                            <li id="stepStatusSetup">⏳ Configurando sistema</li>
                            <li id="stepStatusAdmin">⏳ Criando administrador</li>
                            <li id="stepStatusFinalize">⏳ Finalizando</li>
                        </ul>
                    </div>
                </div>

                <div class="link-login">Já possui conta? <a href="login.php">Faça login aqui</a></div>
            </div>
        </div>
    </div>

    <script>
        window.onboardingConfig = {
            baseScheme: '<?php echo htmlspecialchars($baseScheme); ?>',
            baseHost: '<?php echo htmlspecialchars($baseHost); ?>'
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/onboarding-wizard.js"></script>
</body>

</html>