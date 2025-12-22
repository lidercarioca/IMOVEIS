<?php
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * GmailService - Wrapper da Gmail API com gerenciamento de token OAuth
 * 
 * Uso:
 *   $service = new GmailService();
 *   $messages = $service->listUnread(50);
 *   $msg = $service->getMessage($id, 'full');
 */

class GmailService {
    private $client;

    public function __construct() {
        $clientId = getenv('GOOGLE_CLIENT_ID');
        $clientSecret = getenv('GOOGLE_CLIENT_SECRET');
        $redirectUri = getenv('GOOGLE_OAUTH_REDIRECT') ?: 
            (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . 
            '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/google/oauth_callback.php';

        if (!$clientId || !$clientSecret) {
            throw new Exception('GOOGLE_CLIENT_ID/SECRET não configurados');
        }

        $this->client = new Google_Client();
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->setRedirectUri($redirectUri);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setScopes([
            Google_Service_Gmail::GMAIL_READONLY,
            Google_Service_Gmail::GMAIL_MODIFY
        ]);

        // Carrega token salvo
        $tokenPath = __DIR__ . '/../config/google_token.json';
        if (file_exists($tokenPath)) {
            $token = json_decode(file_get_contents($tokenPath), true);
            $this->client->setAccessToken($token);
        }

        // Renova token se expirou
        if ($this->client->isAccessTokenExpired()) {
            if ($this->client->getRefreshToken()) {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                file_put_contents($tokenPath, json_encode($newToken));
            } else {
                throw new Exception('Token expirado e sem refresh_token. Reauthorize em /google/oauth.php');
            }
        }
    }

    public function getClient() {
        return $this->client;
    }

    /**
     * Ativa watch no inbox (para Pub/Sub)
     */
    public function watchInbox($topicName) {
        $service = new Google_Service_Gmail($this->client);
        $watchReq = new Google_Service_Gmail_WatchRequest();
        $watchReq->setTopicName($topicName);
        $watchReq->setLabelIds(['INBOX']);
        return $service->users->watch('me', $watchReq);
    }

    /**
     * Lista emails não lidos
     */
    public function listUnread($maxResults = 50) {
        $service = new Google_Service_Gmail($this->client);
        $optParams = ['q' => 'is:unread in:inbox', 'maxResults' => $maxResults];
        $messages = $service->users_messages->listUsersMessages('me', $optParams);
        return $messages->getMessages() ?: [];
    }

    /**
     * Busca mensagem completa
     */
    public function getMessage($id, $format = 'full') {
        $service = new Google_Service_Gmail($this->client);
        return $service->users_messages->get('me', $id, ['format' => $format]);
    }

    /**
     * Parse headers de uma mensagem
     */
    public static function parseHeaders($message) {
        $headers = [];
        if ($message->getPayload() && $message->getPayload()->getHeaders()) {
            foreach ($message->getPayload()->getHeaders() as $h) {
                $headers[$h->getName()] = $h->getValue();
            }
        }
        return $headers;
    }

    /**
     * Marca mensagem como lida
     */
    public function markAsRead($msgId) {
        $service = new Google_Service_Gmail($this->client);
        $modifyReq = new Google_Service_Gmail_ModifyMessageRequest();
        $modifyReq->setRemoveLabelIds(['UNREAD']);
        return $service->users_messages->modify('me', $msgId, $modifyReq);
    }
}

?>
