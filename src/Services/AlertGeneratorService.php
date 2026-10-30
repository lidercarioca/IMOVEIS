<?php

namespace App\Services;

use App\Infrastructure\Alert\AlertGeneratorRepository;
use App\Infrastructure\Alert\AlertRepository;
use PDO;

class AlertGeneratorService
{
    private AlertGeneratorRepository $generatorRepository;
    private AlertRepository $alertRepository;
    private ?int $userId;
    private bool $isAdmin;
    private int $tenantId;

    public function __construct(
        PDO $pdo,
        ?int $userId,
        bool $isAdmin,
        int $tenantId,
        ?AlertGeneratorRepository $generatorRepository = null,
        ?AlertRepository $alertRepository = null
    ) {
        $this->generatorRepository = $generatorRepository ?? new AlertGeneratorRepository($pdo);
        $this->alertRepository = $alertRepository ?? new AlertRepository($pdo);
        $this->userId = $userId;
        $this->isAdmin = $isAdmin;
        $this->tenantId = $tenantId;
    }

    public function generateAndPersistAlerts(): array
    {
        $alerts = $this->generateAllAlerts();
        $created = 0;

        foreach ($alerts as $alert) {
            if ($this->alertRepository->createGeneratedAlert($this->tenantId, $this->userId, $alert)) {
                $created++;
            }
        }

        return [
            'success' => true,
            'created' => $created,
            'candidates' => count($alerts),
        ];
    }

    public function generateAllAlerts(): array
    {
        $alerts = [];

        $alerts = array_merge($alerts, $this->createStoppedPropertyAlerts());
        $alerts = array_merge($alerts, $this->createInactiveLeadAlerts());
        $alerts = array_merge($alerts, $this->createLeadsWithoutVisitAlerts());
        $alerts = array_merge($alerts, $this->createPropertiesWithoutViewsAlerts());
        $alerts = array_merge($alerts, $this->createHighConversionLeadsAlerts());
        $alerts = array_merge($alerts, $this->createLowConversionRateAlerts());
        $alerts = array_merge($alerts, $this->createSalesMilestoneAlerts());
        $alerts = array_merge($alerts, $this->createUncompletedSchedulesAlerts());

        $alerts = $this->filterResolvedAlerts($alerts);

        return array_values($alerts);
    }

    private function createStoppedPropertyAlerts(): array
    {
        $items = $this->generatorRepository->findStoppedPropertyAlerts($this->tenantId, $this->userId, $this->isAdmin, 30);
        $alerts = [];

        foreach ($items as $property) {
            $alerts[] = [
                'id' => 'stopped_' . $property['property_id'],
                'type' => 'stopped_property',
                'entity_id' => $property['property_id'],
                'related_entity_type' => 'property',
                'status' => 'pending',
                'severity' => 'warning',
                'title' => '⏸️ Imóvel Paralisado',
                'description' => $property['title'] . ' (parado por ' . $property['days_without_movement'] . ' dias)',
                'color' => '#d97706',
                'icon' => 'exclamation-triangle',
                'action_url' => '#properties?view=' . $property['property_id'],
                'timestamp' => $property['updated_at'] ?? date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    private function createInactiveLeadAlerts(): array
    {
        $items = $this->generatorRepository->findInactiveLeadAlerts($this->tenantId, $this->userId, $this->isAdmin, 7);
        $alerts = [];

        foreach ($items as $lead) {
            $alerts[] = [
                'id' => 'cold_lead_' . $lead['id'],
                'type' => 'cold_lead',
                'entity_id' => $lead['id'],
                'related_entity_type' => 'lead',
                'status' => 'pending',
                'severity' => 'info',
                'title' => '❄️ Lead Inativo',
                'description' => $lead['name'] . ' sem resposta por ' . $lead['dias_inativo'] . ' dias',
                'color' => '#3b82f6',
                'icon' => 'snowflake',
                'action_url' => '#leads?view=' . $lead['id'],
                'timestamp' => $lead['updated_at'] ?? date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    private function createLeadsWithoutVisitAlerts(): array
    {
        $items = $this->generatorRepository->findLeadsWithoutVisitAlerts($this->tenantId, $this->userId, $this->isAdmin, 2, 10);
        $alerts = [];

        foreach ($items as $lead) {
            $alerts[] = [
                'id' => 'lead_no_visit_' . $lead['id'],
                'type' => 'lead_without_visit',
                'entity_id' => $lead['id'],
                'related_entity_type' => 'lead',
                'status' => 'pending',
                'severity' => 'warning',
                'title' => '👁️ Lead sem Visita',
                'description' => $lead['name'] . ' sem visita agendada há ' . $lead['dias_sem_visita'] . ' dias',
                'color' => '#ec4899',
                'icon' => 'eye-off',
                'action_url' => '#leads?view=' . $lead['id'],
                'timestamp' => $lead['created_at'] ?? date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    private function createPropertiesWithoutViewsAlerts(): array
    {
        $items = $this->generatorRepository->findPropertiesWithoutViewsAlerts($this->tenantId, $this->userId, $this->isAdmin, 1, 10);
        $alerts = [];

        foreach ($items as $property) {
            $alerts[] = [
                'id' => 'prop_no_view_' . $property['id'],
                'type' => 'property_without_views',
                'entity_id' => $property['id'],
                'related_entity_type' => 'property',
                'status' => 'pending',
                'severity' => 'info',
                'title' => '🏢 Imóvel sem Visualizações',
                'description' => $property['title'] . ' sem visualizações há ' . $property['dias_sem_view'] . ' dias',
                'color' => '#06b6d4',
                'icon' => 'eye-off',
                'action_url' => '#properties?view=' . $property['id'],
                'timestamp' => $property['created_at'] ?? date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    private function createHighConversionLeadsAlerts(): array
    {
        $items = $this->generatorRepository->findHighConversionLeadsAlerts($this->tenantId, $this->userId, $this->isAdmin, 10);
        $alerts = [];

        foreach ($items as $row) {
            $alerts[] = [
                'id' => 'high_conv_' . $row['assigned_user_id'],
                'type' => 'high_conversion_leads',
                'entity_id' => $row['assigned_user_id'],
                'related_entity_type' => 'user',
                'status' => 'pending',
                'severity' => 'success',
                'title' => '🚀 Muitos Leads Qualificados',
                'description' => 'Você tem ' . $row['lead_count'] . ' leads qualificados esperando follow-up',
                'color' => '#10b981',
                'icon' => 'trending-up',
                'action_url' => '#leads?status=qualified',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    private function createLowConversionRateAlerts(): array
    {
        $items = $this->generatorRepository->findLowConversionRateAlerts($this->tenantId, $this->userId, $this->isAdmin, 25);
        $alerts = [];

        foreach ($items as $row) {
            $alerts[] = [
                'id' => 'low_conv_' . $row['assigned_user_id'],
                'type' => 'low_conversion_rate',
                'entity_id' => $row['assigned_user_id'],
                'related_entity_type' => 'user',
                'status' => 'pending',
                'severity' => 'critical',
                'title' => '📉 Taxa de Conversão Baixa',
                'description' => 'Sua taxa de conversão este mês é ' . $row['conversion_rate'] . '% (' . $row['total_sales'] . ' de ' . $row['total_leads'] . ' leads)',
                'color' => '#ef4444',
                'icon' => 'alert-circle',
                'action_url' => '#relatorios',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    private function createSalesMilestoneAlerts(): array
    {
        $items = $this->generatorRepository->findSalesMilestoneAlerts($this->tenantId, $this->userId, $this->isAdmin, 10);
        $alerts = [];

        foreach ($items as $row) {
            $alerts[] = [
                'id' => 'sales_mile_' . $row['user_id'],
                'type' => 'sales_milestone',
                'entity_id' => $row['user_id'],
                'related_entity_type' => 'user',
                'status' => 'pending',
                'severity' => 'success',
                'title' => '🎉 Meta de Vendas Atingida',
                'description' => 'Parabéns! Você completou ' . $row['sales_count'] . ' vendas este mês (R$ ' . number_format($row['total_value'], 2, ',', '.') . ')',
                'color' => '#8b5cf6',
                'icon' => 'award',
                'action_url' => '#relatorios',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    private function createUncompletedSchedulesAlerts(): array
    {
        $items = $this->generatorRepository->findUncompletedScheduleAlerts($this->tenantId, $this->userId, $this->isAdmin, 10);
        $alerts = [];

        foreach ($items as $schedule) {
            $alerts[] = [
                'id' => 'uncompleted_schedule_' . $schedule['id'],
                'type' => 'uncompleted_schedules',
                'entity_id' => $schedule['id'],
                'related_entity_type' => 'schedule',
                'status' => 'pending',
                'severity' => 'warning',
                'title' => '📅 Agendamento não realizado',
                'description' => 'Agendamento para ' . ($schedule['contact_name'] ?? 'contato desconhecido') . ' em ' . ($schedule['data_agendamento'] ?? '') . ' não foi realizado',
                'color' => '#f59e0b',
                'icon' => 'calendar-x',
                'action_url' => '#agendamentos?view=' . $schedule['id'],
                'timestamp' => $schedule['data_agendamento'] ?? date('Y-m-d H:i:s'),
            ];
        }

        return $alerts;
    }

    private function filterResolvedAlerts(array $alerts): array
    {
        if ($this->userId === null) {
            return $alerts;
        }

        $resolvedKeys = $this->alertRepository->findResolvedAlertKeys($this->tenantId, $this->userId);

        return array_values(array_filter($alerts, function (array $alert) use ($resolvedKeys) {
            $key = ($alert['type'] ?? $alert['alert_type'] ?? '') . '::' . ($alert['entity_id'] ?? $alert['related_entity_id'] ?? 'null');
            return !isset($resolvedKeys[$key]);
        }));
    }
}
