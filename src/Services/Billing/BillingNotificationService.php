<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\NotificationLogRepository;
use App\Infrastructure\Billing\TenantRepository;
use App\Infrastructure\Mail\MailerService;
use App\Infrastructure\User\UserRepository;

/**
 * BillingNotificationService - orquestra notificações de billing em PSR-4.
 */
class BillingNotificationService
{
    private TenantRepository $tenantRepository;
    private UserRepository $userRepository;
    private MailerService $mailer;
    private NotificationLogRepository $notificationLogRepository;

    public function __construct(
        TenantRepository $tenantRepository,
        UserRepository $userRepository,
        MailerService $mailer,
        NotificationLogRepository $notificationLogRepository
    ) {
        $this->tenantRepository = $tenantRepository;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        $this->notificationLogRepository = $notificationLogRepository;
    }

    public function sendFailedPaymentNotification(int $tenantId, ?string $invoiceId = null, ?float $amount = null): array
    {
        $tenant = $this->tenantRepository->getTenantById($tenantId);
        if (!$tenant) {
            return $this->buildResult(false, 'Empresa não encontrada', 'stripe_payment_failed', $tenantId);
        }

        $admin = $this->resolveTenantAdmin($tenantId);
        if (!$admin || empty($admin['email'])) {
            return $this->buildResult(false, 'Administrador da empresa não encontrado', 'stripe_payment_failed', $tenantId);
        }

        $subject = 'Falha de pagamento da assinatura - Ação necessária';
        $bodyText = "Olá {$admin['name']},\n\n";
        $bodyText .= "Detectamos uma falha no pagamento da sua assinatura para a empresa '{$tenant['name']}'.\n";

        if ($amount !== null) {
            $bodyText .= "Valor da fatura: R$ " . $this->formatCurrency($amount) . "\n";
        }

        if ($invoiceId) {
            $bodyText .= "ID da fatura Stripe: {$invoiceId}\n";
        }

        $bodyText .= "\nPara manter o acesso ao sistema, atualize sua forma de pagamento ou renove o plano em nosso painel.\n";
        $bodyText .= "\nAtenciosamente,\nEquipe de Suporte";

        $bodyHtml = '<h2>Falha no pagamento da assinatura</h2>';
        $bodyHtml .= '<p>Olá <strong>' . htmlspecialchars($admin['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>,</p>';
        $bodyHtml .= '<p>Detectamos uma falha no pagamento da sua assinatura para a empresa <strong>' . htmlspecialchars($tenant['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>.</p>';

        if ($amount !== null) {
            $bodyHtml .= '<p><strong>Valor da fatura:</strong> R$ ' . htmlspecialchars($this->formatCurrency($amount), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        if ($invoiceId) {
            $bodyHtml .= '<p><strong>ID da fatura Stripe:</strong> ' . htmlspecialchars($invoiceId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        $bodyHtml .= '<p>Para manter o acesso ao sistema, atualize sua forma de pagamento ou renove o plano em nosso painel.</p>';
        $bodyHtml .= '<p>Atenciosamente,<br>Equipe de Suporte</p>';

        return $this->sendNotification('stripe_payment_failed', $tenantId, $admin['email'], $subject, $bodyText, $bodyHtml);
    }

    public function sendSubscriptionCancellationNotification(int $tenantId, ?string $subscriptionId = null): array
    {
        $tenant = $this->tenantRepository->getTenantById($tenantId);
        if (!$tenant) {
            return $this->buildResult(false, 'Empresa não encontrada', 'stripe_subscription_deleted', $tenantId);
        }

        $admin = $this->resolveTenantAdmin($tenantId);
        if (!$admin || empty($admin['email'])) {
            return $this->buildResult(false, 'Administrador da empresa não encontrado', 'stripe_subscription_deleted', $tenantId);
        }

        $subject = 'Assinatura cancelada - Acesso bloqueado';
        $bodyText = "Olá {$admin['name']},\n\n";
        $bodyText .= "Sua assinatura para a empresa '{$tenant['name']}' foi cancelada ou encerrada.\n";

        if ($subscriptionId) {
            $bodyText .= "ID da assinatura Stripe: {$subscriptionId}\n";
        }

        $bodyText .= "\nComo consequência, o acesso ao sistema foi suspenso. Por favor, renegocie o plano ou reative o pagamento para voltar a usar o serviço.\n";
        $bodyText .= "\nAtenciosamente,\nEquipe de Suporte";

        $bodyHtml = '<h2>Assinatura cancelada</h2>';
        $bodyHtml .= '<p>Olá <strong>' . htmlspecialchars($admin['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>,</p>';
        $bodyHtml .= '<p>Sua assinatura para a empresa <strong>' . htmlspecialchars($tenant['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> foi cancelada ou encerrada.</p>';

        if ($subscriptionId) {
            $bodyHtml .= '<p><strong>ID da assinatura Stripe:</strong> ' . htmlspecialchars($subscriptionId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        $bodyHtml .= '<p>Como consequência, o acesso ao sistema foi suspenso. Por favor, renegocie o plano ou reative o pagamento para voltar a usar o serviço.</p>';
        $bodyHtml .= '<p>Atenciosamente,<br>Equipe de Suporte</p>';

        return $this->sendNotification('stripe_subscription_deleted', $tenantId, $admin['email'], $subject, $bodyText, $bodyHtml);
    }

    public function sendExpirationWarning(int $tenantId): array
    {
        $tenant = $this->tenantRepository->getTenantById($tenantId);
        if (!$tenant) {
            return $this->buildResult(false, 'Empresa não encontrada', 'expiracao_3dias', $tenantId);
        }

        $admin = $this->resolveTenantAdmin($tenantId);
        if (!$admin || empty($admin['email'])) {
            return $this->buildResult(false, 'Administrador da empresa não encontrado', 'expiracao_3dias', $tenantId);
        }

        $expiration = strtotime($tenant['data_expiracao']);
        if ($expiration === false) {
            return $this->buildResult(false, 'Data de expiração inválida', 'expiracao_3dias', $tenantId);
        }

        $daysRemaining = (int) ceil(($expiration - time()) / 86400);
        $subject = "Sua assinatura vence em {$daysRemaining} dias - Ação Necessária";

        $bodyText = "Olá {$admin['name']},\n\n";
        $bodyText .= "Sua assinatura do plano '{$tenant['plano']}' para a empresa '{$tenant['name']}' vencerá em {$daysRemaining} dias.\n\n";
        $bodyText .= 'Data de expiração: ' . date('d/m/Y H:i', $expiration) . "\n\n";
        $bodyText .= "Para manter seu acesso ininterrupto, por favor renove sua assinatura em nosso painel.\n\n";
        $bodyText .= 'Acesse: http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/planos.php' . "\n\n";
        $bodyText .= "Atenciosamente,\nEquipe de Suporte";

        $bodyHtml = '<h2>Aviso de Expiração de Assinatura</h2>';
        $bodyHtml .= '<p>Olá <strong>' . htmlspecialchars($admin['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong>,</p>';
        $bodyHtml .= '<p>Sua assinatura do plano <strong>' . htmlspecialchars($tenant['plano'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> para a empresa <strong>' . htmlspecialchars($tenant['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong> vencerá em <strong>' . $daysRemaining . ' dias</strong>.</p>';
        $bodyHtml .= '<p><strong>Data de expiração:</strong> ' . htmlspecialchars(date('d/m/Y H:i', $expiration), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        $bodyHtml .= '<p>Para manter seu acesso ininterrupto, por favor renove sua assinatura em nosso painel.</p>';
        $bodyHtml .= '<p><a href="http://' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '/planos.php" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Renovar Agora</a></p>';
        $bodyHtml .= '<p>Atenciosamente,<br>Equipe de Suporte</p>';

        return $this->sendNotification('expiracao_3dias', $tenantId, $admin['email'], $subject, $bodyText, $bodyHtml);
    }

    private function resolveTenantAdmin(int $tenantId): ?array
    {
        $admins = $this->userRepository->findByRoleAndTenant('admin', $tenantId);
        return $admins[0] ?? null;
    }

    private function sendNotification(string $type, int $tenantId, string $to, string $subject, string $bodyText, string $bodyHtml): array
    {
        $emailSent = $this->mailer->send($to, $subject, $bodyText, $bodyHtml);
        $result = [
            'success' => $emailSent,
            'message' => $emailSent ? 'Email enviado com sucesso' : 'Falha ao enviar email',
            'email_sent' => $emailSent,
            'notification_type' => $type,
            'tenant_id' => $tenantId,
            'log_status' => null,
            'error' => null,
        ];

        if ($emailSent) {
            $saved = $this->notificationLogRepository->recordSend($tenantId, $type, 'enviado');
            $result['log_status'] = $saved ? 'enviado' : 'failed_to_log';
            if (!$saved) {
                $result['error'] = 'Falha ao registrar log de notificação';
                $result['success'] = false;
                $result['message'] = 'Email enviado, mas falha ao registrar log';
            }
        } else {
            $result['error'] = 'MailerService retornou false';
        }

        return $result;
    }

    private function buildResult(bool $success, string $message, string $type, int $tenantId): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'email_sent' => false,
            'notification_type' => $type,
            'tenant_id' => $tenantId,
            'log_status' => null,
            'error' => $success ? null : $message,
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2, ',', '.');
    }
}
