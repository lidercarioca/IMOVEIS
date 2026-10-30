<?php

/**
 * EmailNotificationService - Serviço de envio de emails de notificação
 * Envia emails reais baseado nas configurações de notificação da empresa
 */

require_once __DIR__ . '/mailer.php';

class EmailNotificationService
{
    private $pdo;
    private $settings = [];
    private $tenantId;
    private $companySettingsService;

    public function __construct($pdo, ?int $tenantId = null, ?\App\Service\CompanySettingsService $companySettingsService = null)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId !== null ? (int) $tenantId : null;
        $this->companySettingsService = $companySettingsService ?? new \App\Service\CompanySettingsService($pdo);
        $this->loadSettings();
    }

    /**
     * Carrega as configurações de notificação
     */
    private function loadSettings()
    {
        if ($this->tenantId === null) {
            $this->settings = [];
            return;
        }

        $settings = $this->companySettingsService->getContactInfoForTenant($this->tenantId);
        $this->settings = $settings ?? [];
    }

    /**
     * Verifica se uma notificação de email está ativada
     */
    private function isNotificationEnabled($type)
    {
        $setting = 'notify_' . $type;
        return isset($this->settings[$setting]) && $this->settings[$setting] == 1;
    }

    /**
     * Obtém o email de notificação apropriado
     */
    private function getNotificationEmail($type)
    {
        // Se for lead, usa email específico de leads
        if ($type === 'new_lead' && !empty($this->settings['email_leads'])) {
            return $this->settings['email_leads'];
        }
        // Para outros eventos, usa email geral de notificações
        if (!empty($this->settings['email_notifications'])) {
            return $this->settings['email_notifications'];
        }
        // Fallback para email principal
        return $this->settings['company_email'] ?? null;
    }

    /**
     * Notifica sobre novo lead por email
     */
    public function notifyNewLead($leadName, $leadEmail, $propertyTitle = null)
    {
        if (!$this->isNotificationEnabled('new_lead')) {
            return false;
        }

        $recipient = $this->getNotificationEmail('new_lead');
        if (!$recipient) {
            error_log("Email de notificação de leads não configurado");
            return false;
        }

        $subject = "Novo Lead Recebido - {$leadName}";
        $body = "Um novo lead foi recebido:\n\n";
        $body .= "Nome: {$leadName}\n";
        $body .= "Email: {$leadEmail}\n";
        if ($propertyTitle) {
            $body .= "Imóvel de Interesse: {$propertyTitle}\n";
        }
        $body .= "\n\nAcesse o painel para mais detalhes.";

        $html = "<h2>Novo Lead Recebido</h2>";
        $html .= "<p><strong>Nome:</strong> {$leadName}</p>";
        $html .= "<p><strong>Email:</strong> {$leadEmail}</p>";
        if ($propertyTitle) {
            $html .= "<p><strong>Imóvel de Interesse:</strong> {$propertyTitle}</p>";
        }

        return sendAppEmail($recipient, $subject, $body, $html);
    }

    /**
     * Notifica sobre novo imóvel cadastrado
     */
    public function notifyNewProperty($propertyTitle, $propertyPrice = null)
    {
        if (!$this->isNotificationEnabled('new_property')) {
            return false;
        }

        $recipient = $this->getNotificationEmail('notifications');
        if (!$recipient) {
            error_log("Email de notificação não configurado");
            return false;
        }

        $subject = "Novo Imóvel Cadastrado - {$propertyTitle}";
        $body = "Um novo imóvel foi cadastrado:\n\n";
        $body .= "Título: {$propertyTitle}\n";
        if ($propertyPrice) {
            $body .= "Preço: R$ " . number_format($propertyPrice, 2, ',', '.') . "\n";
        }
        $body .= "\n\nAcesse o painel para mais detalhes e gerenciar o imóvel.";

        $html = "<h2>Novo Imóvel Cadastrado</h2>";
        $html .= "<p><strong>Título:</strong> {$propertyTitle}</p>";
        if ($propertyPrice) {
            $html .= "<p><strong>Preço:</strong> R$ " . number_format($propertyPrice, 2, ',', '.') . "</p>";
        }

        return sendAppEmail($recipient, $subject, $body, $html);
    }

    /**
     * Notifica sobre alteração de status de imóvel
     */
    public function notifyPropertyStatus($propertyTitle, $newStatus, $propertyPrice = null)
    {
        if (!$this->isNotificationEnabled('property_status')) {
            return false;
        }

        $recipient = $this->getNotificationEmail('notifications');
        if (!$recipient) {
            error_log("Email de notificação não configurado");
            return false;
        }

        $statusText = [
            'vendido' => 'Vendido',
            'alugado' => 'Alugado',
            'reservado' => 'Reservado',
            'active' => 'Ativo',
            'pending' => 'Pendente',
            'inactive' => 'Inativo'
        ];

        $status = $statusText[$newStatus] ?? ucfirst($newStatus);

        $subject = "Alteração de Status - {$propertyTitle}";
        $body = "Um imóvel teve seu status alterado:\n\n";
        $body .= "Título: {$propertyTitle}\n";
        $body .= "Novo Status: {$status}\n";
        if ($propertyPrice) {
            $body .= "Preço: R$ " . number_format($propertyPrice, 2, ',', '.') . "\n";
        }

        $html = "<h2>Alteração de Status</h2>";
        $html .= "<p><strong>Imóvel:</strong> {$propertyTitle}</p>";
        $html .= "<p><strong>Novo Status:</strong> {$status}</p>";
        if ($propertyPrice) {
            $html .= "<p><strong>Preço:</strong> R$ " . number_format($propertyPrice, 2, ',', '.') . "</p>";
        }

        return sendAppEmail($recipient, $subject, $body, $html);
    }

    /**
     * Notifica sobre mensagem do formulário de contato
     */
    public function notifyContactForm($name, $email, $message, $propertyTitle = null)
    {
        if (!$this->isNotificationEnabled('contact_form')) {
            return false;
        }

        $recipient = $this->getNotificationEmail('notifications');
        if (!$recipient) {
            error_log("Email de notificação não configurado");
            return false;
        }

        $subject = "Nova Mensagem de Contato - {$name}";
        $body = "Uma nova mensagem foi recebida através do formulário de contato:\n\n";
        $body .= "Nome: {$name}\n";
        $body .= "Email: {$email}\n";
        if ($propertyTitle) {
            $body .= "Imóvel: {$propertyTitle}\n";
        }
        $body .= "\nMensagem:\n{$message}";

        $html = "<h2>Nova Mensagem de Contato</h2>";
        $html .= "<p><strong>Nome:</strong> {$name}</p>";
        $html .= "<p><strong>Email:</strong> {$email}</p>";
        if ($propertyTitle) {
            $html .= "<p><strong>Imóvel:</strong> {$propertyTitle}</p>";
        }
        $html .= "<p><strong>Mensagem:</strong></p>";
        $html .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>";

        return sendAppEmail($recipient, $subject, $body, $html);
    }

    /**
     * Notifica sobre novo agendamento
     */
    public function notifyNewAgendamento($propertyTitle, $dataAgendamento, $leadName, $leadEmail)
    {
        if (!$this->isNotificationEnabled('agendamento')) {
            return false;
        }

        $recipient = $this->getNotificationEmail('notifications');
        if (!$recipient) {
            error_log("Email de notificação não configurado");
            return false;
        }

        $dataFormatada = date('d/m/Y H:i', strtotime($dataAgendamento));

        $subject = "Novo Agendamento - {$propertyTitle}";
        $body = "Um novo agendamento foi criado:\n\n";
        $body .= "Imóvel: {$propertyTitle}\n";
        $body .= "Data/Hora: {$dataFormatada}\n";
        $body .= "Cliente: {$leadName}\n";
        $body .= "Email: {$leadEmail}\n";

        $html = "<h2>Novo Agendamento</h2>";
        $html .= "<p><strong>Imóvel:</strong> {$propertyTitle}</p>";
        $html .= "<p><strong>Data/Hora:</strong> {$dataFormatada}</p>";
        $html .= "<p><strong>Cliente:</strong> {$leadName}</p>";
        $html .= "<p><strong>Email:</strong> {$leadEmail}</p>";

        return sendAppEmail($recipient, $subject, $body, $html);
    }

    /**
     * Notifica sobre alteração de status de agendamento
     */
    public function notifyAgendamentoStatusChange($propertyTitle, $novoStatus, $leadName, $dataAgendamento)
    {
        if (!$this->isNotificationEnabled('agendamento')) {
            return false;
        }

        $recipient = $this->getNotificationEmail('notifications');
        if (!$recipient) {
            error_log("Email de notificação não configurado");
            return false;
        }

        $statusTexto = [
            'confirmado' => 'Confirmado',
            'cancelado' => 'Cancelado',
            'realizado' => 'Realizado'
        ];

        $status = $statusTexto[$novoStatus] ?? ucfirst($novoStatus);
        $dataFormatada = date('d/m/Y H:i', strtotime($dataAgendamento));

        $subject = "Agendamento {$status} - {$propertyTitle}";
        $body = "Um agendamento foi alterado:\n\n";
        $body .= "Imóvel: {$propertyTitle}\n";
        $body .= "Cliente: {$leadName}\n";
        $body .= "Data/Hora: {$dataFormatada}\n";
        $body .= "Novo Status: {$status}\n";

        $html = "<h2>Agendamento {$status}</h2>";
        $html .= "<p><strong>Imóvel:</strong> {$propertyTitle}</p>";
        $html .= "<p><strong>Cliente:</strong> {$leadName}</p>";
        $html .= "<p><strong>Data/Hora:</strong> {$dataFormatada}</p>";
        $html .= "<p><strong>Novo Status:</strong> {$status}</p>";

        return sendAppEmail($recipient, $subject, $body, $html);
    }
}
