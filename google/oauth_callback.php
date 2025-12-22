<?php
/**
 * Endpoint: Callback OAuth
 * Google redireciona aqui após autorização
 * Troca o code por um token de acesso
 * 
 * Fluxo:
 * 1. Usuário clica em oauth.php
 * 2. É redirecionado para Google Consent Screen
 * 3. Autoriza o acesso
 * 4. Google redireciona para este arquivo com ?code=...
 * 5. Trocamos code por access_token + refresh_token
 * 6. Salvamos em config/google_token.json
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/GmailService.php';

try {
    $g = new GmailService();
    $client = $g->getClient();

    if (!isset($_GET['code'])) {
        // Usuário cancelou a autorização
        if (isset($_GET['error'])) {
            throw new Exception('Erro Google: ' . $_GET['error']);
        }
        throw new Exception('Código OAuth não fornecido');
    }

    // Troca o code por tokens
    $code = $_GET['code'];
    $token = $client->fetchAccessTokenWithAuthCode($code);
    
    if (isset($token['error'])) {
        throw new Exception('Erro ao obter token: ' . json_encode($token));
    }

    // Salva o token (contém access_token + refresh_token)
    $tokenPath = __DIR__ . '/../config/google_token.json';
    @mkdir(dirname($tokenPath), 0755, true);
    file_put_contents($tokenPath, json_encode($token));
    chmod($tokenPath, 0600); // Segurança: somente owner lê

    echo "<h1>✓ Autorização concluída com sucesso!</h1>";
    echo "<p>Token salvo em: <code>" . $tokenPath . "</code></p>";
    echo "<h2>Próximos passos:</h2>";
    echo "<ul>";
    echo "<li>Teste o polling: <code>php google/poll_unread.php</code></li>";
    echo "<li>Agende o polling: Windows Task Scheduler a cada 3-5 minutos</li>";
    echo "<li>Para Pub/Sub: execute <code>php google/watch.php</code></li>";
    echo "</ul>";
    echo "<p><a href=\"/painel.php\">Voltar ao painel</a></p>";

} catch (Exception $e) {
    http_response_code(500);
    echo "<h1>✗ Erro na autorização OAuth</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href=\"/painel.php\">Voltar</a></p>";
    exit(1);
}

?>
