<?php

use App\Application\User\UserService;
use App\Infrastructure\Company\CompanySettingsRepository;
use App\Infrastructure\Notification\NotificationRepository;
use App\Infrastructure\User\UserRepository;
use App\Service\CompanySettingsService;

class NotificationManager
{
    private $pdo;
    private $settings;
    private $tenantId;
    private NotificationRepository $notificationRepository;
    private CompanySettingsService $companySettingsService;

    public function __construct($pdo, $tenantId = null)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        $this->notificationRepository = new NotificationRepository($this->pdo);
        $this->companySettingsService = new CompanySettingsService($this->pdo, new CompanySettingsRepository($this->pdo));
        $this->loadSettings();
    }

    /**
     * Carrega as configurações de notificação do tenant atual
     */
    private function loadSettings()
    {
        if (!$this->tenantId) {
            $this->settings = [];
            return;
        }

        $this->settings = $this->companySettingsService->getContactInfoForTenant((int) $this->tenantId) ?? [];
    }

    /**
     * Verifica se uma notificação está ativada
     */
    private function isNotificationEnabled($type)
    {
        $setting = 'notify_' . $type;
        return isset($this->settings[$setting]) && $this->settings[$setting] == 1;
    }

    /**
     * Cria uma nova notificação para todos os admins do tenant
     */
    public function createNotification($type, $title, $message, $link = null, $userId = null)
    {
        $link = $link ?? '';

        // Se não especificar usuário, cria para TODOS os admins do tenant
        if ($userId === null) {
            $userService = new UserService(new UserRepository($this->pdo));
            if ($this->tenantId) {
                $admins = $userService->findAdminsByTenant((int) $this->tenantId);
            } else {
                $admins = [];
            }
            $admins = array_column($admins, 'id');

            error_log("[NotificationManager] Criando notificação para " . count($admins) . " admins. Type: {$type}");

            $inserted = 0;
            foreach ($admins as $adminId) {
                if ($this->notificationRepository->createForUserAndTenant((int) $adminId, (int) $this->tenantId, (string) $type, (string) $title, (string) $message, (string) $link)) {
                    $inserted++;
                    error_log("[NotificationManager] Notificação criada para admin ID {$adminId}");
                }
            }
            error_log("[NotificationManager] Total inseridas: {$inserted}");
            return $inserted > 0;
        }

        return $this->notificationRepository->createForUserAndTenant(
            (int) $userId,
            (int) $this->tenantId,
            (string) $type,
            (string) $title,
            (string) $message,
            (string) $link
        );
    }

    /**
     * Cria uma nova mensagem COM ISOLAMENTO DE TENANT
     */
    public function createMessage($fromName, $fromEmail, $subject, $message, $propertyId = null, $userId = null)
    {
        return $this->notificationRepository->createMessageForTenant(
            (int) $this->tenantId,
            (string) $fromName,
            (string) $fromEmail,
            (string) $subject,
            (string) $message,
            $propertyId !== null ? (int) $propertyId : null,
            $userId !== null ? (int) $userId : null
        );
    }

    /**
     * Notifica sobre um novo lead
     */
    public function notifyNewLead($leadName, $leadEmail, $propertyTitle = null)
    {
        // SEMPRE criar notificação no painel, independentemente da configuração
        // A configuração só controla se envia email (EmailNotificationService)

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
    public function notifyPropertyStatus($propertyId, $propertyTitle, $newStatus)
    {
        // SEMPRE criar notificação no painel

        // Mapear status para texto e emoji
        $statusTexto = [
            'vendido' => 'Vendido',
            'alugado' => 'Alugado',
            'reservado' => 'Reservado',
            'active' => 'Ativo',
            'pending' => 'Pendente',
            'inactive' => 'Inativo'
        ];

        $mensagensStatus = [
            'vendido' => 'O imóvel "' . $propertyTitle . '" foi VENDIDO! ✅',
            'alugado' => 'O imóvel "' . $propertyTitle . '" foi ALUGADO! 🏠',
            'reservado' => 'O imóvel "' . $propertyTitle . '" está RESERVADO. 🔒',
            'active' => 'O imóvel "' . $propertyTitle . '" está ATIVO e disponível. ✨',
            'pending' => 'O imóvel "' . $propertyTitle . '" está PENDENTE de aprovação. ⏳',
            'inactive' => 'O imóvel "' . $propertyTitle . '" foi marcado como INATIVO. ❌'
        ];

        $statusLabel = $statusTexto[$newStatus] ?? ucfirst($newStatus);
        $message = $mensagensStatus[$newStatus] ?? ('O imóvel "' . $propertyTitle . '" foi marcado como ' . $statusLabel . '.');
        $title = "Status de Imóvel: " . $statusLabel;

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
    public function notifyNewProperty($propertyId, $propertyTitle)
    {
        // SEMPRE criar notificação no painel

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
    public function notifyContactForm($name, $email, $message)
    {
        // SEMPRE criar notificação no painel

        $title = "Nova Mensagem de Contato";
        $message = "Mensagem recebida de {$name} ({$email}): " . substr($message, 0, 100) . "...";

        return $this->createNotification('contact', $title, $message, 'painel.php?tab=messages');
    }

    /**
     * Notifica sobre novo agendamento
     */
    public function notifyNewAgendamento($propertyTitle, $dataAgendamento, $userName)
    {
        // SEMPRE criar notificação no painel

        $title = "Novo Agendamento Criado";
        $dataFormatada = date('d/m/Y H:i', strtotime($dataAgendamento));
        $message = "Novo agendamento para o imóvel \"{$propertyTitle}\" em {$dataFormatada} por {$userName}";

        return $this->createNotification('agendamento', $title, $message, 'painel.php?tab=agendamentos');
    }

    /**
     * Notifica sobre alteração de status de agendamento
     */
    public function notifyAgendamentoStatusChange($propertyTitle, $novoStatus, $userName)
    {
        // SEMPRE criar notificação no painel

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

    /**
     * Notifica um usuário sobre novo imóvel atribuído a ele
     * 
     * @param int $propertyId ID do imóvel
     * @param string $propertyTitle Título/nome do imóvel
     * @param int $userId ID do usuário que receberá a atribuição
     * @param string $propertyLocation Localização do imóvel
     * @param float $propertyPrice Preço do imóvel
     * @return bool true se notificação foi criada
     */
    public function notifyPropertyAssigned($propertyId, $propertyTitle, $userId, $propertyLocation = '', $propertyPrice = null)
    {
        if (!$userId) {
            return false;
        }

        // Obter informações do imóvel para a notificação
        $priceInfo = $propertyPrice ? ' | Preço: R$ ' . number_format($propertyPrice, 2, ',', '.') : '';
        $locationInfo = $propertyLocation ? ' | Local: ' . $propertyLocation : '';

        $title = "Novo Imóvel Atribuído";
        $message = "Você recebeu atribuição do imóvel \"{$propertyTitle}\"{$locationInfo}{$priceInfo}";

        // Criar notificação para o usuário específico
        return $this->createNotification(
            'property_assignment',
            $title,
            $message,
            "painel.php?tab=properties&property={$propertyId}",
            $userId  // Atribuir para usuário específico
        );
    }

    /**
     * Notifica um usuário sobre novo lead atribuído a ele
     * 
     * @param int $leadId ID do lead
     * @param string $leadName Nome/Empresa do lead
     * @param string $leadEmail Email do lead
     * @param int $userId ID do usuário que receberá a atribuição
     * @param string $leadPhone Telefone do lead (opcional)
     * @param string $propertyTitle Título da propriedade relacionada (opcional)
     * @return bool true se notificação foi criada
     */
    public function notifyLeadAssigned($leadId, $leadName, $leadEmail, $userId, $leadPhone = '', $propertyTitle = '')
    {
        if (!$userId) {
            return false;
        }

        // Construir mensagem com informações disponíveis
        $phoneInfo = $leadPhone ? ' | Tel: ' . $leadPhone : '';
        $propertyInfo = $propertyTitle ? ' | Imóvel: ' . $propertyTitle : '';

        $title = "Novo Lead Atribuído";
        $message = "Você recebeu atribuição do lead \"{$leadName}\" ({$leadEmail}){$phoneInfo}{$propertyInfo}";

        // Criar notificação para o usuário específico
        return $this->createNotification(
            'lead_assignment',
            $title,
            $message,
            "painel.php?tab=leads",
            $userId  // Atribuir para usuário específico
        );
    }
}
