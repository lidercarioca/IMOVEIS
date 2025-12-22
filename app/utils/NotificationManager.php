<?php
class NotificationManager {
    private $pdo;
    private $settings;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * Carrega as configurações de notificação
     */
    private function loadSettings() {
        $stmt = $this->pdo->query("SELECT * FROM company_settings WHERE id = 1");
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se uma notificação está ativada
     */
    private function isNotificationEnabled($type) {
        $setting = 'notify_' . $type;
        return isset($this->settings[$setting]) && $this->settings[$setting] == 1;
    }

    /**
     * Cria uma nova notificação
     */
    public function createNotification($type, $title, $message, $link = null, $userId = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO notifications (type, title, message, link, user_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$type, $title, $message, $link, $userId]);
    }

    /**
     * Cria uma nova mensagem
     */
    public function createMessage($fromName, $fromEmail, $subject, $message, $propertyId = null, $userId = null) {
        // Salva apenas a mensagem, que será exibida na área de Mensagens (ícone de envelope)
        $stmt = $this->pdo->prepare("
            INSERT INTO messages (from_name, from_email, subject, message, property_id, user_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ok = $stmt->execute([$fromName, $fromEmail, $subject, $message, $propertyId, $userId]);
        if ($ok) {
            return (int)$this->pdo->lastInsertId();
        }
        return false;
    }

    /**
     * Notifica sobre um novo lead
     */
    public function notifyNewLead($leadName, $leadEmail, $propertyTitle = null) {
        // Verifica se as notificações de novos leads estão ativadas
        if (!$this->isNotificationEnabled('new_lead')) {
            return false;
        }

        $title = "Novo Lead Recebido";
        $message = "Lead recebido de {$leadName} ({$leadEmail})";
        if ($propertyTitle) {
            $message .= " sobre o imóvel: {$propertyTitle}";
        }
        
        return $this->createNotification('lead', $title, $message, 'painel.php?tab=leads');
    }

    /**
     * Notifica sobre alteração de status de imóvel
     */
    public function notifyPropertyStatus($propertyId, $propertyTitle, $newStatus) {
        // Verifica se as notificações de status estão ativadas
        if (!$this->isNotificationEnabled('property_status')) {
            return false;
        }

        $statusText = $newStatus === 'vendido' ? 'Vendido' : 'Alugado';
        $title = "Imóvel {$statusText}";
        $message = "O imóvel \"{$propertyTitle}\" foi marcado como {$statusText}";
        
        return $this->createNotification(
            'property', 
            $title, 
            $message, 
            "painel.php?tab=properties&property={$propertyId}"
        );
    }

    /**
     * Notifica sobre novo imóvel cadastrado
     */
    public function notifyNewProperty($propertyId, $propertyTitle) {
        // Verifica se as notificações de novos imóveis estão ativadas
        if (!$this->isNotificationEnabled('new_property')) {
            return false;
        }

        $title = "Novo Imóvel Cadastrado";
        $message = "Um novo imóvel foi cadastrado: \"{$propertyTitle}\"";
        
        return $this->createNotification(
            'property', 
            $title, 
            $message, 
            "painel.php?tab=properties&property={$propertyId}"
        );
    }

    /**
     * Notifica sobre nova mensagem do formulário de contato
     */
    public function notifyContactForm($name, $email, $message) {
        // Verifica se as notificações de formulário de contato estão ativadas
        if (!$this->isNotificationEnabled('contact_form')) {
            return false;
        }

        $title = "Nova Mensagem de Contato";
        $message = "Mensagem recebida de {$name} ({$email}): " . substr($message, 0, 100) . "...";
        
        return $this->createNotification('contact', $title, $message, 'painel.php?tab=messages');
    }

    /**
     * Notifica sobre novo agendamento
     */
    public function notifyNewAgendamento($propertyTitle, $dataAgendamento, $userName) {
        // Verifica se as notificações de agendamento estão ativadas
        if (!$this->isNotificationEnabled('agendamento')) {
            return false;
        }

        $title = "Novo Agendamento Criado";
        $dataFormatada = date('d/m/Y H:i', strtotime($dataAgendamento));
        $message = "Novo agendamento para o imóvel \"{$propertyTitle}\" em {$dataFormatada} por {$userName}";
        
        return $this->createNotification('agendamento', $title, $message, 'painel.php?tab=agendamentos');
    }

    /**
     * Notifica sobre alteração de status de agendamento
     */
    public function notifyAgendamentoStatusChange($propertyTitle, $novoStatus, $userName) {
        // Verifica se as notificações de agendamento estão ativadas
        if (!$this->isNotificationEnabled('agendamento')) {
            return false;
        }

        $statusTexto = [
            'confirmado' => 'Confirmado',
            'cancelado' => 'Cancelado',
            'realizado' => 'Realizado'
        ];

        $titulo = isset($statusTexto[$novoStatus]) ? $statusTexto[$novoStatus] : ucfirst($novoStatus);
        $title = "Agendamento {$titulo}";
        $message = "O agendamento para \"{$propertyTitle}\" foi marcado como {$titulo} por {$userName}";
        
        return $this->createNotification('agendamento', $title, $message, 'painel.php?tab=agendamentos');
    }
}
