<?php

namespace App\Infrastructure\Alert;

use PDO;

class AlertConfigRepository
{
    private PDO $pdo;
    private const ALERT_TYPES = [
        'inactive_lead' => [
            'nome' => 'Lead Inativo',
            'descricao' => 'Alertar quando lead não tem interação',
            'unidade' => 'dias',
        ],
        'inactive_property' => [
            'nome' => 'Imóvel Inativo',
            'descricao' => 'Alertar quando imóvel fica sem atividade',
            'unidade' => 'dias',
        ],
        'qualified_leads_threshold' => [
            'nome' => 'Muitos Leads Qualificados',
            'descricao' => 'Alertar quando muitos leads estão qualificados',
            'unidade' => 'leads',
        ],
        'sales_goal_warning' => [
            'nome' => 'Meta de Vendas Próxima',
            'descricao' => 'Alertar quando meta de vendas está perto',
            'unidade' => '%',
        ],
        'conversion_rate_drop' => [
            'nome' => 'Queda de Taxa de Conversão',
            'descricao' => 'Alertar quando taxa de conversão cai abaixo do limite',
            'unidade' => '%',
        ],
    ];

    private const DEFAULT_THRESHOLDS = [
        'inactive_lead' => 30,
        'inactive_property' => 60,
        'qualified_leads_threshold' => 10,
        'sales_goal_warning' => 80,
        'conversion_rate_drop' => 5,
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByTenantAndUser(int $tenantId, int $userId): array
    {
        $sql = "SELECT * FROM alerts_config WHERE tenant_id = :tenant_id AND user_id = :user_id ORDER BY alert_type ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveConfig(int $tenantId, int $userId, string $alertType, int $threshold, bool $enabled): bool
    {
        $sql = "SELECT id FROM alerts_config WHERE tenant_id = :tenant_id AND user_id = :user_id AND alert_type = :alert_type LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':alert_type' => $alertType,
        ]);

        if ($stmt->fetch()) {
            $sql = "UPDATE alerts_config
                    SET threshold = :threshold, enabled = :enabled, updated_at = NOW()
                    WHERE tenant_id = :tenant_id AND user_id = :user_id AND alert_type = :alert_type";
        } else {
            $sql = "INSERT INTO alerts_config
                    (tenant_id, user_id, alert_type, threshold, enabled, created_at, updated_at)
                    VALUES (:tenant_id, :user_id, :alert_type, :threshold, :enabled, NOW(), NOW())";
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id' => $userId,
            ':alert_type' => $alertType,
            ':threshold' => $threshold,
            ':enabled' => $enabled ? 1 : 0,
        ]);
    }

    public static function getAlertTypes(): array
    {
        return self::ALERT_TYPES;
    }

    public static function getDefaultThresholds(): array
    {
        return self::DEFAULT_THRESHOLDS;
    }
}
