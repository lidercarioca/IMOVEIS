<?php

use App\Security\RateLimiter;
use App\Observability\RequestTracker;
use App\Application\User\UserService;
use App\Infrastructure\User\UserRepository;

require_once 'app/security/Security.php';
Security::setSecurityHeaders();
Security::blockLogsAccess();

require_once __DIR__ . '/src/Observability/RequestTracker.php';
require_once __DIR__ . '/src/Security/RateLimiter.php';
$securityLimits = require __DIR__ . '/config/security.php';
require_once 'config/database.php';

Security::init();

// Helper de logging de ações do usuário
require_once __DIR__ . '/app/utils/logger_functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: painel.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = Security::sanitizeInput($_POST['username'] ?? '');
    $ip = RequestTracker::resolveClientIp();

    if ($ip === null) {
        error_log(sprintf("LOGIN_BAD_IP: usuario=%s", $username ?: 'desconhecido'));
        $error = "Não foi possível validar sua conexão. Tente novamente.";
        http_response_code(400);
    } else {
        $rateId = $ip . '|' . strtolower($username);
        $rateLimiter = new RateLimiter();
        $loginMaxAttempts = $securityLimits['login_max_attempts'] ?? 5;
        $loginDecay = $securityLimits['login_decay_seconds'] ?? 60;
        if (!$rateLimiter->isAllowed($rateId, $loginMaxAttempts, $loginDecay)) {
            $headerInfo = $rateLimiter->getHeaderInfo($rateId, $loginMaxAttempts, $loginDecay);
            error_log(sprintf("LOGIN_RATE_LIMIT: IP=%s usuario=%s retry_after=%d", $ip, $username ?: 'desconhecido', $headerInfo['reset_in']));
            $error = sprintf("Muitas tentativas de login. Aguarde %d segundos.", $headerInfo['reset_in']);
            header("HTTP/1.1 429 Too Many Requests");
        } elseif (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
            error_log(sprintf(
                "LOGIN_CSRF_FAIL: IP=%s usuario=%s csrf_present=%s session_has_token=%s",
                $ip,
                $_POST['username'] ?? 'desconhecido',
                isset($_POST['csrf_token']) ? 'true' : 'false',
                isset($_SESSION['csrf_token']) ? 'true' : 'false'
            ));
            $error = "Token de segurança inválido. Atualize esta página e tente novamente.";
            header("HTTP/1.1 403 Forbidden");
        } else {

            $password = $_POST['password'] ?? '';
            try {
                $userService = new UserService(new UserRepository($pdo));
                $user = $userService->authenticateUser($username, $password);
                if ($user) {
                    // Login bem-sucedido: resetar contador de tentativas
                    $rateLimiter->reset($rateId, $loginDecay);
                    session_unset();
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['tenant_id'] = $user['tenant_id'];
                    $_SESSION['is_superadmin'] = (int)($user['is_superadmin'] ?? 0);
                    $_SESSION['last_activity'] = time();
                    $_SESSION['last_regeneration'] = time();
                    log_user_action('login_success', [
                        'user_id' => $user['id'],
                        'username' => $user['username']
                    ]);
                    // Redirecionar SUPERADMIN para seleção de tenant
                    if ($_SESSION['is_superadmin']) {
                        header("Location: select_tenant.php");
                    } else {
                        header("Location: painel.php");
                    }
                    exit;
                }
                // Falha: não revela se usuário existe
                $error = "Usuário ou senha inválidos";
            } catch (PDOException $e) {
                error_log("Erro no login: " . $e->getMessage());
                $error = "Erro ao processar login. Por favor, tente novamente.";
            }
        }
    }
}

if (isset($_GET['error']) && $_GET['error'] === 'session_expired') {
    $error = "Sua sessão expirou. Por favor, faça login novamente.";
}

if (isset($_GET['social_error']) && !empty($_GET['social_error'])) {
    $socialError = trim($_GET['social_error']);
    $error = htmlspecialchars($socialError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RR Imóveis</title>
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

        .login-card {
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

        .alert-danger {
            background-color: rgba(var(--bs-danger-rgb), 0.1);
            color: var(--bs-danger);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card login-card">
                    <div class="card-body p-4">
                        <div class="brand">
                            <img src="assets/imagens/logo/logo_padrao.png" alt="RR Imóveis" class="img-fluid">
                            <h4>Painel Administrativo</h4>
                        </div>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                            <div class="mb-3">
                                <label class="form-label">Usuário</label>
                                <input type="text" name="username" class="form-control"
                                    required autofocus autocomplete="username">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Senha</label>
                                <input type="password" name="password" class="form-control"
                                    required autocomplete="current-password">
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Entrar</button>
                        </form>

                        <?php
                        $googleLoginEnabled = !empty(getenv('GOOGLE_CLIENT_ID')) && !empty(getenv('GOOGLE_CLIENT_SECRET'));
                        $facebookLoginEnabled = !empty(getenv('FACEBOOK_CLIENT_ID')) && !empty(getenv('FACEBOOK_CLIENT_SECRET'));
                        if ($googleLoginEnabled || $facebookLoginEnabled):
                        ?>
                            <div class="mt-4 text-center">
                                <small class="text-muted">ou entre com</small>
                            </div>
                            <div class="d-grid gap-2 mt-2">
                                <?php if ($googleLoginEnabled): ?>
                                    <a href="/social/google.php" class="btn btn-outline-secondary w-100">Entrar com Google</a>
                                <?php endif; ?>
                                <?php if ($facebookLoginEnabled): ?>
                                    <a href="/social/facebook.php" class="btn btn-outline-secondary w-100">Entrar com Facebook</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3 text-center">
                            <p class="mb-2 text-muted">
                                <a href="forgot-password.php" class="text-decoration-none">
                                    Esqueceu a senha?
                                </a>
                            </p>
                            <p class="mb-0 text-muted small">
                                Não tem empresa cadastrada?
                                <a href="cadastro_empresa.php" class="text-decoration-none fw-semibold">
                                    Cadastre aqui
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/auth/auth-manager.js"></script>
    <script>
        (function() {
            const loginForm = document.querySelector('form[action=""]') || document.querySelector('form');
            if (!loginForm || !window.AuthManager || typeof window.AuthManager.setTokens !== 'function') {
                return;
            }

            const submitButton = loginForm.querySelector('button[type="submit"], input[type="submit"]');
            const existingError = document.getElementById('js-auth-login-error') || (function() {
                const errorDiv = document.createElement('div');
                errorDiv.id = 'js-auth-login-error';
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.style.display = 'none';
                loginForm.parentNode.insertBefore(errorDiv, loginForm);
                return errorDiv;
            })();

            function showError(message) {
                if (!existingError) return;
                existingError.textContent = message;
                existingError.style.display = 'block';
            }

            function continueLegacySubmit() {
                loginForm.__skipJwtEnhancement = true;
                if (typeof loginForm.requestSubmit === 'function') {
                    loginForm.requestSubmit(submitButton || undefined);
                    return;
                }

                if (submitButton && typeof submitButton.click === 'function') {
                    submitButton.click();
                    return;
                }

                HTMLFormElement.prototype.submit.call(loginForm);
            }

            loginForm.addEventListener('submit', async function(event) {
                if (loginForm.__skipJwtEnhancement) {
                    loginForm.__skipJwtEnhancement = false;
                    loginForm.__authManagerSubmitInProgress = false;
                    return;
                }

                if (loginForm.__authManagerSubmitInProgress) {
                    return;
                }

                if (!window.AuthManager || typeof window.AuthManager.setTokens !== 'function') {
                    return;
                }

                event.preventDefault();
                loginForm.__authManagerSubmitInProgress = true;

                const formData = new FormData(loginForm);
                const username = String(formData.get('username') || '').trim();
                const password = String(formData.get('password') || '');

                if (!username || !password) {
                    continueLegacySubmit();
                    return;
                }

                try {
                    const response = await fetch('/api/v1/auth/login.php', {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            username,
                            password
                        }),
                    });

                    let data = null;
                    const contentType = response.headers.get('content-type') || '';
                    if (contentType.includes('application/json')) {
                        try {
                            data = await response.json();
                        } catch (jsonParseError) {
                            data = null;
                        }
                    }

                    if (data && data.success) {
                        window.AuthManager.setTokens(data);
                        continueLegacySubmit();
                        return;
                    }

                    if (data && data.error) {
                        showError(data.error);
                        loginForm.__authManagerSubmitInProgress = false;
                        return;
                    }

                    continueLegacySubmit();
                } catch (err) {
                    continueLegacySubmit();
                }
            });
        })();
    </script>
</body>

</html>