<?php
/**
 * Endpoint: Inicia fluxo OAuth para autorizar acesso ao Gmail
 * Redireciona o usuário para a Google Consent Screen
 * 
 * Acesso: http://localhost/google/oauth.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/GmailService.php';

try {
    $g = new GmailService();
    $client = $g->getClient();
    $authUrl = $client->createAuthUrl();
    
    echo "Redirecionando para Google OAuth...\n";
    echo "<a href=\"{$authUrl}\">Clique aqui se não redirecionar automaticamente</a>\n";
    
    header('Location: ' . $authUrl);
    exit();
} catch (Exception $e) {
    http_response_code(500);
    echo "Erro ao iniciar OAuth: " . $e->getMessage();
    exit(1);
}

?>
