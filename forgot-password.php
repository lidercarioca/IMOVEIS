<?php
require_once 'app/security/Security.php';

// Inicializa segurança (sessão)
Security::init();

// Se já está logado, redireciona para o painel
if (isset($_SESSION['user_id'])) {
    header("Location: painel.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - RR Imóveis</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: var(--bs-primary);
            --primary-dark: var(--bs-primary-dark);
            --primary-light: var(--bs-primary-light);
        }
        
        body {
            background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.1) 0%, rgba(var(--bs-primary-rgb), 0.05) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .recovery-card {
            background: #fff;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .brand {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
        }
        
        .brand img {
            max-width: 180px;
            height: auto;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }
        
        .brand img:hover {
            transform: scale(1.05);
        }
        
        .brand h4 {
            color: var(--bs-gray-700);
            font-weight: 500;
            margin: 0;
        }
        
        .form-control {
            border: 1px solid var(--bs-gray-300);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--bs-gray-700);
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 0.25rem 0.5rem rgba(var(--bs-primary-rgb), 0.3);
        }
        
        .alert {
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .alert-info {
            background-color: rgba(var(--bs-info-rgb), 0.1);
            color: var(--bs-info);
        }
        
        .alert-success {
            background-color: rgba(var(--bs-success-rgb), 0.1);
            color: var(--bs-success);
        }
        
        .alert-danger {
            background-color: rgba(var(--bs-danger-rgb), 0.1);
            color: var(--bs-danger);
        }
        
        .help-text {
            font-size: 0.875rem;
            color: var(--bs-gray-600);
            margin-top: 0.5rem;
        }
        
        .text-center a {
            color: var(--bs-primary);
            text-decoration: none;
        }
        
        .text-center a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card recovery-card">
                    <div class="card-body p-4">
                        <div class="brand">
                            <img src="assets/imagens/logo/logo.png" alt="RR Imóveis" class="img-fluid">
                            <h4>Recuperar Senha</h4>
                        </div>
                        
                        <div id="message"></div>
                        
                        <form id="forgotForm">
                            <div class="mb-3">
                                <label class="form-label">E-mail</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       required autofocus>
                                <div class="help-text">Insira o e-mail associado à sua conta</div>
                            </div>
                            
                            <button type="submit" id="submitBtn" class="btn btn-primary w-100">
                                Enviar Link de Recuperação
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="login.php" class="small">Voltar ao Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const messageDiv = document.getElementById('message');
            const submitBtn = document.getElementById('submitBtn');
            const email = document.getElementById('email').value;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Enviando...';
            messageDiv.innerHTML = '';
            
            fetch('/api/requestPasswordReset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Sucesso!</strong> Se o e-mail estiver cadastrado, você receberá um link para redefinir sua senha.
                            Verifique sua caixa de entrada (e pasta de spam).
                        </div>
                    `;
                    document.getElementById('forgotForm').reset();
                    submitBtn.innerHTML = 'Link Enviado!';
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Enviar Link de Recuperação';
                    }, 3000);
                } else {
                    messageDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>Erro:</strong> ${data.message || 'Ocorreu um erro ao processar sua solicitação.'}
                        </div>
                    `;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Enviar Link de Recuperação';
                }
            })
            .catch(error => {
                messageDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>Erro:</strong> Falha na conexão. Tente novamente.
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Enviar Link de Recuperação';
                console.error('Erro:', error);
            });
        });
    </script>
</body>
</html>
