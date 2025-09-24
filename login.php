<?php
// Headers de segurança
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src \'self\' https://ka-f.fontawesome.com https://cdnjs.cloudflare.com https://*.jsdelivr.net https://cdn.jsdelivr.net;');

require_once 'app/security/Security.php';
require_once 'config/database.php';

// Inicializa configurações de segurança (já inclui session_start)
Security::init();

if (isset($_SESSION['user_id'])) {
    header("Location: painel.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verifica CSRF token
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        $error = "Token de segurança inválido";
        header("HTTP/1.1 403 Forbidden");
        exit;
    }

    $username = Security::sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    // Verifica tentativas de login
    if (!Security::validateLoginAttempts($username)) {
        $error = "Muitas tentativas de login. Tente novamente em 15 minutos.";
        header("HTTP/1.1 429 Too Many Requests");
        exit;
    }
    
    try {
        $sql = "SELECT * FROM users WHERE username = :username AND active = 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Registra login bem-sucedido
            Security::recordLoginAttempt($username, true);
            
            // Limpa sessão anterior se existir
            session_unset();
            
            // Regenera ID da sessão
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['last_activity'] = time();
            $_SESSION['last_regeneration'] = time();
            
            // Atualiza último login
            $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
            $update->execute(['id' => $user['id']]);
            
            header("Location: painel.php");
            exit;
        }
        
        // Se chegou aqui, login falhou
        Security::recordLoginAttempt($username, false);
        $error = "Usuário ou senha inválidos";
        
    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        $error = "Erro ao processar login. Por favor, tente novamente.";
    }
}

// Verifica se há mensagem de erro na URL
if (isset($_GET['error']) && $_GET['error'] === 'session_expired') {
    $error = "Sua sessão expirou. Por favor, faça login novamente.";
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
                            <img src="assets/imagens/logo/logo.png" alt="RR Imóveis" class="img-fluid">
                            <h4>Painel Administrativo</h4>
                        </div>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
