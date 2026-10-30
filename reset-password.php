<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/app/security/Security.php';
require_once __DIR__ . '/app/utils/InputValidator.php';

use App\Application\User\UserService;
use App\Infrastructure\User\UserRepository;

// Inicializa segurança (sessão)
Security::init();

// GET -> exibe formulário de reset
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';

    if (!$token) {
        http_response_code(400);
        echo 'Token ausente ou inválido.';
        exit;
    }

    // Valida token antes de exibir form via serviço
    try {
        $userRepository = new UserRepository($pdo);
        $userService = new UserService($userRepository);
        $status = $userService->getPasswordResetStatus($token);

        if ($status === 'invalid' || $status === 'used') {
            http_response_code(404);
            echo 'Token inválido ou já foi utilizado.';
            exit;
        }

        if ($status === 'expired') {
            http_response_code(410);
            echo 'Token expirado. Solicite uma nova redefinição de senha.';
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log('Erro ao validar token: ' . $e->getMessage());
        echo 'Erro ao validar token.';
        exit;
    }

    // Token válido, exibe formulário
?>
    <!doctype html>
    <html lang="pt-br">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Redefinir Senha</title>
        <link rel="stylesheet" href="/assets/css/output.css">
        <style>
            body {
                background: #f5f5f5;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }

            .container {
                background: white;
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                max-width: 400px;
                width: 100%;
            }

            h2 {
                margin-top: 0;
            }

            .form-group {
                margin-bottom: 1.5rem;
            }

            label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
            }

            input {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 1rem;
            }

            input:focus {
                outline: none;
                border-color: #007bff;
                box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            }

            .help-text {
                font-size: 0.875rem;
                color: #666;
                margin-top: 0.5rem;
            }

            button {
                width: 100%;
                padding: 0.75rem;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 1rem;
                cursor: pointer;
            }

            button:hover {
                background: #0056b3;
            }

            .error {
                color: #d9534f;
                margin-bottom: 1rem;
            }

            .success {
                color: #5cb85c;
                margin-bottom: 1rem;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <h2>Redefinir Senha</h2>
            <div id="message"></div>
            <form id="resetForm" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="new_password">Nova Senha</label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password" required>
                    <div class="help-text">Mínimo 8 caracteres, incluindo pelo menos 1 letra maiúscula, 1 minúscula e 1 número.</div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmar Senha</label>
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                </div>
                <button type="submit">Redefinir Senha</button>
            </form>
        </div>

        <script>
            document.getElementById('resetForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const msgDiv = document.getElementById('message');
                const newPw = document.getElementById('new_password').value;
                const confirmPw = document.getElementById('confirm_password').value;

                if (newPw !== confirmPw) {
                    msgDiv.innerHTML = '<div class="error">As senhas não coincidem.</div>';
                    return;
                }

                fetch('<?php echo $_SERVER['REQUEST_URI']; ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams(new FormData(this))
                    })
                    .then(r => r.text())
                    .then(text => {
                        try {
                            const json = JSON.parse(text);
                            if (json.success) {
                                msgDiv.innerHTML = '<div class="success">' + json.message + '</div>';
                                setTimeout(() => {
                                    window.location.href = '/login.php';
                                }, 2000);
                            } else {
                                msgDiv.innerHTML = '<div class="error">' + json.message + '</div>';
                            }
                        } catch (e) {
                            msgDiv.innerHTML = '<div class="error">' + text + '</div>';
                        }
                    })
                    .catch(err => {
                        msgDiv.innerHTML = '<div class="error">Erro na requisição: ' + err.message + '</div>';
                    });
            });
        </script>
    </body>

    </html>
<?php
    exit;
}

// POST -> processa reset de senha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $token = InputValidator::getToken($_POST['token'] ?? '', 'Token de redefinição', true);
        $new = InputValidator::getString($_POST['new_password'] ?? '', 'Nova senha', true, 500);
        $confirm = InputValidator::getString($_POST['confirm_password'] ?? '', 'Confirmação de senha', true, 500);

        if ($new !== $confirm) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'As senhas não coincidem']);
            exit;
        }

        // Valida força da senha
        if (!Security::validatePassword($new)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'A senha não atende aos requisitos mínimos (min 8 caracteres, ao menos 1 maiúscula, 1 minúscula e 1 número)'
            ]);
            exit;
        }

        $userRepository = new UserRepository($pdo);
        $userService = new UserService($userRepository);

        $row = $userService->validatePasswordResetToken($token);
        if ($row === null) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Token inválido']);
            exit;
        }

        $userId = (int)($row['user_id'] ?? 0);

        $ok = $userService->completePasswordReset($token, $new);

        if (!$ok) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Falha ao atualizar a senha. Tente novamente']);
            exit;
        }

        error_log("Senha redefinida com sucesso para user_id: {$userId}");

        echo json_encode(['success' => true, 'message' => 'Senha redefinida com sucesso! Você será redirecionado para o login']);
    } catch (Exception $e) {
        http_response_code(500);
        error_log('resetPassword error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro interno ao processar a solicitação']);
    }

    exit;
}

// Método não permitido
http_response_code(405);
echo 'Método não permitido';
