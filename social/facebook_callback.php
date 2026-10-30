<?php
require_once __DIR__ . '/social_login_helper.php';
require_once __DIR__ . '/../src/Infrastructure/User/UserRepository.php';

use App\Infrastructure\User\UserRepository;

$facebookClientId = getenv('FACEBOOK_CLIENT_ID');
$facebookClientSecret = getenv('FACEBOOK_CLIENT_SECRET');
if (empty($facebookClientId) || empty($facebookClientSecret)) {
    redirectWithError('Configuração do login Facebook não está completa.');
}

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
if (empty($code) || empty($state) || !isset($_SESSION['facebook_oauth_state']) || !hash_equals($_SESSION['facebook_oauth_state'], $state)) {
    redirectWithError('Falha na autenticação com Facebook.');
}

$redirectUri = getenv('FACEBOOK_OAUTH_REDIRECT') ?: null;
if (empty($redirectUri)) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $redirectUri = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/social/facebook_callback.php';
}

$tokenUrl = 'https://graph.facebook.com/v16.0/oauth/access_token?' . http_build_query([
    'client_id' => $facebookClientId,
    'redirect_uri' => $redirectUri,
    'client_secret' => $facebookClientSecret,
    'code' => $code
]);

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $tokenUrl);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($curl);
$error = curl_error($curl);
curl_close($curl);

if ($response === false || !empty($error)) {
    redirectWithError('Erro ao obter token do Facebook.');
}

$data = json_decode($response, true);
if (!isset($data['access_token'])) {
    redirectWithError('Token de acesso do Facebook inválido.');
}

$profileUrl = 'https://graph.facebook.com/me?' . http_build_query([
    'fields' => 'id,name,email',
    'access_token' => $data['access_token']
]);

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $profileUrl);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$profileResponse = curl_exec($curl);
$error = curl_error($curl);
curl_close($curl);

if ($profileResponse === false || !empty($error)) {
    redirectWithError('Erro ao ler perfil do Facebook.');
}

$profile = json_decode($profileResponse, true);
if (empty($profile['email'])) {
    redirectWithError('O Facebook não retornou um e-mail válido.');
}

$email = filter_var($profile['email'], FILTER_SANITIZE_EMAIL);
$userRepository = new UserRepository($pdo);
$user = $userRepository->findByEmail($email);

if (!$user || (int)($user['active'] ?? 0) !== 1) {
    $user = null;
}

if (!$user) {
    redirectWithError('Nenhum usuário ativo encontrado para este e-mail do Facebook.');
}

if (!performSocialLogin($pdo, $user, 'facebook')) {
    redirectWithError('Não foi possível efetuar login com Facebook.');
}

if (!empty($_SESSION['is_superadmin'])) {
    header('Location: /select_tenant.php');
} else {
    header('Location: /painel.php');
}
exit;
