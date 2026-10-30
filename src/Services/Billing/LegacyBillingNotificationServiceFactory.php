<?php

namespace App\Services\Billing;

use App\Infrastructure\Billing\NotificationLogRepository;
use App\Infrastructure\Billing\TenantRepository;
use App\Infrastructure\Mail\MailerService;
use App\Infrastructure\User\UserRepository;
use PDO;

/**
 * Factory temporária para criar o serviço de notificações de billing no código legacy.
 */
class LegacyBillingNotificationServiceFactory
{
    public static function createFromPdo(PDO $pdo): BillingNotificationService
    {
        return new BillingNotificationService(
            new TenantRepository($pdo),
            new UserRepository($pdo),
            new MailerService(),
            new NotificationLogRepository($pdo)
        );
    }

    public static function createFromGlobalPdo(): BillingNotificationService
    {
        global $pdo;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new \RuntimeException('PDO não disponível para LegacyBillingNotificationServiceFactory.');
        }

        return self::createFromPdo($pdo);
    }
}
