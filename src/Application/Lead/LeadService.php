<?php

namespace App\Application\Lead;

use App\Domain\Lead\Lead;
use App\Infrastructure\Lead\LeadRepository;
use App\Infrastructure\PortalIntegration\PortalIntegrationRepository;
use PDO;

require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/NotificationManager.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/utils/EmailNotificationService.php';
require_once dirname(dirname(dirname(__DIR__))) . '/app/services/LeadInteractionService.php';

class LeadService
{
    private LeadRepository $repository;
    private PDO $pdo;
    private PortalIntegrationRepository $portalIntegrationRepository;

    public function __construct(LeadRepository $repository)
    {
        $this->repository = $repository;
        $this->pdo = $repository->getPdo();
        $this->portalIntegrationRepository = new PortalIntegrationRepository($this->pdo);
    }

    public function listLeads(int $tenantId, ?int $userId, bool $isAdmin, array $queryParams = [], int $page = 1, int $limit = 10): array
    {
        $filters = [
            'tenant_id' => $tenantId,
            'status' => $queryParams['status'] ?? 'all',
            'search' => $queryParams['q'] ?? '',
        ];

        if (!$isAdmin && $userId !== null) {
            $filters['assigned_user_id_or_property_owner'] = $userId;
        }

        $createdAfter = $this->resolveCreatedAfter($queryParams['period'] ?? 'all');
        if ($createdAfter !== null) {
            $filters['created_after'] = $createdAfter;
        }

        $offset = max(0, ($page - 1) * $limit);
        $data = $this->repository->listLeads($filters, $limit, $offset);
        $total = $this->repository->countLeads($filters);
        $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $total,
                'per_page' => $limit,
                'offset' => $offset,
            ],
            'tenant_isolation' => [
                'tenant_id' => $tenantId,
                'is_admin' => $isAdmin,
            ],
        ];
    }

    public function getLead(int $tenantId, int $leadId): ?Lead
    {
        return $this->repository->findLeadByIdAndTenant($leadId, $tenantId);
    }

    public function createLead(int $tenantId, array $data, ?int $utmTrackingId = null): int
    {
        $lead = new Lead([
            'tenant_id' => $tenantId,
            'property_id' => $data['property_id'] ?? null,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'name' => $data['name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'message' => $data['message'] ?? '',
            'status' => $data['status'] ?? 'new',
            'source' => $data['source'] ?? 'site',
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $data['updated_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return $this->repository->createLead($lead, $utmTrackingId);
    }

    public function resolveTenantForLeadSubmission(?int $propertyId, ?int $submittedTenantId): array
    {
        $tenantId = null;
        $resolvedPropertyId = $propertyId;

        if ($submittedTenantId !== null && $submittedTenantId > 0) {
            $tenantId = $submittedTenantId;
        } elseif ($propertyId !== null) {
            $propertyTenantId = $this->repository->getTenantIdForProperty($propertyId);
            if ($propertyTenantId !== null) {
                $tenantId = $propertyTenantId;
            } else {
                $resolvedPropertyId = null;
            }
        }

        return [
            'tenant_id' => $tenantId,
            'property_id' => $resolvedPropertyId,
        ];
    }

    public function submitLeadFromContact(array $payload, ?int $utmTrackingId = null): array
    {
        $submittedTenantId = isset($payload['tenant_id']) && $payload['tenant_id'] !== null && $payload['tenant_id'] !== ''
            ? (int)$payload['tenant_id']
            : null;
        $resolvedPropertyId = isset($payload['property_id']) && $payload['property_id'] !== null && $payload['property_id'] !== ''
            ? (int)$payload['property_id']
            : null;

        $tenantContext = $this->resolveTenantForLeadSubmission($resolvedPropertyId, $submittedTenantId);
        $tenantId = $tenantContext['tenant_id'];
        $propertyId = $tenantContext['property_id'];

        if ($tenantId === null || $tenantId <= 0) {
            throw new \InvalidArgumentException('Não foi possível resolver o tenant do lead sem uma referência válida.');
        }

        $existingLead = $this->repository->findExistingLeadForContact($tenantId, $payload['email'], $payload['phone']);
        $isNewLead = false;
        $propertyTitle = null;
        $assignedUserId = null;

        if ($propertyId !== null) {
            $propertyContext = $this->repository->getPropertyTitleAndAssignedUser($propertyId, $tenantId);
            if ($propertyContext !== null) {
                $propertyTitle = $propertyContext['title'];
                $assignedUserId = $propertyContext['assigned_user_id'] !== null ? (int)$propertyContext['assigned_user_id'] : null;
            } else {
                $propertyId = null;
            }
        }

        if ($existingLead) {
            $leadId = $existingLead->id;
            $this->repository->updateLead($leadId, $tenantId, [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'property_id' => $propertyId,
                'message' => $payload['message'],
                'status' => $payload['status'],
                'source' => $payload['source'],
            ]);
        } else {
            $leadId = $this->createLead($tenantId, [
                'name' => $payload['name'],
                'email' => $payload['email'],
                'phone' => $payload['phone'],
                'property_id' => $propertyId,
                'message' => $payload['message'],
                'status' => $payload['status'],
                'source' => $payload['source'],
                'created_at' => $payload['created_at'],
                'updated_at' => $payload['updated_at'],
            ], $utmTrackingId);
            $isNewLead = true;
        }

        if ($assignedUserId) {
            $this->assignLead($tenantId, $leadId, $assignedUserId);
        }

        if ($isNewLead) {
            try {
                $usersToNotify = $this->repository->getAdminsByTenant($tenantId);
                if ($assignedUserId && !in_array($assignedUserId, $usersToNotify, true)) {
                    $usersToNotify[] = $assignedUserId;
                }
                $usersToNotify = array_unique($usersToNotify);

                $notificationManager = new \NotificationManager($this->pdo, $tenantId);
                foreach ($usersToNotify as $userId) {
                    $notificationLink = 'painel.php?tab=leads&lead_id=' . (int)$leadId;
                    $notificationManager->createNotification(
                        'lead',
                        'Novo Lead Recebido',
                        "Lead recebido de {$payload['name']} ({$payload['email']})" . ($propertyTitle ? " sobre o imóvel: {$propertyTitle}" : ''),
                        $notificationLink,
                        $userId
                    );
                }

                $emailService = new \EmailNotificationService($this->pdo, $tenantId);
                $emailService->notifyNewLead($payload['name'], $payload['email'], $propertyTitle);

                $this->sendFacebookPixelLeadEvent($tenantId, $payload['email'], $payload['phone'], $propertyId, $payload['source']);
            } catch (\Exception $e) {
                error_log('Erro ao enviar notificações ou pixel: ' . $e->getMessage());
            }
        }

        if ($isNewLead) {
            try {
                $interactionService = new \App\Services\LeadInteractionService($this->pdo, $tenantId);
                $interactionService->recordInteraction(
                    $leadId,
                    'lead_created',
                    'Lead criado',
                    'Lead criado através de: ' . ucfirst($payload['source']),
                    null,
                    null,
                    ['source' => $payload['source']],
                    null
                );
            } catch (\Exception $e) {
                error_log('[LeadService] Erro ao registrar interação de lead criado: ' . $e->getMessage());
            }
        }

        return [
            'lead_id' => $leadId,
            'is_new' => $isNewLead,
            'property_title' => $propertyTitle,
            'assigned_user_id' => $assignedUserId,
        ];
    }

    private function sendFacebookPixelLeadEvent(int $tenantId, string $email, string $phone, ?int $propertyId, string $source): void
    {
        $integration = $this->portalIntegrationRepository->findByTenantAndSlug($tenantId, 'facebook_pixel');
        if (!$integration || !$integration['enabled']) {
            return;
        }

        $credentials = $integration['credentials'] ?? [];
        $pixelId = trim((string)($credentials['pixel_id'] ?? ''));
        $accessToken = trim((string)($credentials['access_token'] ?? ''));
        $appSecret = trim((string)($credentials['app_secret'] ?? ''));
        $graphVersion = trim((string)($credentials['graph_api_version'] ?? 'v16.0')) ?: 'v16.0';
        if ($pixelId === '' || $accessToken === '') {
            return;
        }

        $userData = [];
        if ($email !== '') {
            $userData['em'] = hash('sha256', mb_strtolower(trim($email), 'UTF-8'));
        }
        if ($phone !== '') {
            $normalizedPhone = preg_replace('/[^0-9]/', '', trim($phone));
            if ($normalizedPhone !== '') {
                $userData['ph'] = hash('sha256', mb_strtolower($normalizedPhone, 'UTF-8'));
            }
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $userData['client_ip_address'] = $_SERVER['REMOTE_ADDR'];
        }
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $userData['client_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        $eventData = [
            'event_name' => 'Lead',
            'event_time' => time(),
            'action_source' => 'website',
            'user_data' => $userData,
            'custom_data' => [
                'source' => $source,
                'property_id' => $propertyId ? (string)$propertyId : '',
            ],
        ];

        $payload = [
            'data' => [$eventData],
            'access_token' => $accessToken,
        ];

        $url = sprintf('https://graph.facebook.com/%s/%s/events', rawurlencode($graphVersion), rawurlencode($pixelId));
        if ($appSecret !== '') {
            $proof = hash_hmac('sha256', $accessToken, $appSecret);
            $url .= '?appsecret_proof=' . rawurlencode($proof);
        }

        if (!function_exists('curl_init')) {
            error_log('sendFacebookPixelLeadEvent: cURL não disponível, evento do Facebook Pixel não será enviado');
            return;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError !== '') {
            throw new \Exception('Erro cURL ao enviar evento do Facebook Pixel: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \Exception('Resposta inválida do Facebook Pixel: ' . substr((string)$response, 0, 512));
        }

        if (!empty($decoded['error'])) {
            $errorMessage = $decoded['error']['message'] ?? 'Erro desconhecido do Facebook';
            throw new \Exception('Facebook Pixel API: ' . $errorMessage);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception('Facebook Pixel retornou HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 512));
        }
    }

    public function updateLeadWithAudit(int $tenantId, int $leadId, array $data, ?int $userId, bool $isAdmin): bool
    {
        if (!$this->repository->leadExistsForTenant($tenantId, $leadId)) {
            throw new \Exception('Lead não encontrado.');
        }

        if (!$isAdmin && $userId !== null && !$this->repository->canUserAccessLead($tenantId, $leadId, $userId)) {
            throw new \Exception('Acesso negado. Você não tem permissão para atualizar esse lead.');
        }

        $previous = $this->repository->getLeadState($tenantId, $leadId);
        $updated = $this->repository->updateLead($leadId, $tenantId, $data);

        if ($updated && $previous) {
            try {
                $interactionService = new \App\Services\LeadInteractionService($this->pdo, $tenantId);

                if (isset($data['status']) && $data['status'] !== $previous['status']) {
                    $interactionService->recordStatusChange(
                        $leadId,
                        $previous['status'] ?? 'new',
                        $data['status'],
                        $userId
                    );
                }

                if (array_key_exists('proposal_sent', $data)) {
                    $newProposalSent = $data['proposal_sent'] ? 1 : 0;
                    if ($newProposalSent && !$previous['proposal_sent']) {
                        $interactionService->recordInteraction(
                            $leadId,
                            'proposal_sent',
                            'Proposta enviada',
                            'Uma proposta foi enviada para este lead',
                            null,
                            null,
                            ['sent_at' => date('Y-m-d H:i:s')],
                            $userId
                        );
                    }
                }

                if (array_key_exists('notes', $data) && !empty($data['notes'])) {
                    $interactionService->recordInteraction(
                        $leadId,
                        'notes_added',
                        'Anotação adicionada',
                        $data['notes'],
                        null,
                        null,
                        null,
                        $userId
                    );
                }
            } catch (\Exception $e) {
                error_log('[LeadService] Erro ao registrar interação de atualização: ' . $e->getMessage());
            }
        }

        return $updated;
    }

    public function touchLead(int $tenantId, int $leadId, ?int $userId, bool $isAdmin): bool
    {
        if (!$this->repository->leadExistsForTenant($tenantId, $leadId)) {
            throw new \Exception('Lead não encontrado.');
        }

        if (!$isAdmin && $userId !== null && !$this->repository->canUserAccessLead($tenantId, $leadId, $userId)) {
            throw new \Exception('Acesso negado. Você não tem permissão para atualizar esse lead.');
        }

        return $this->repository->touchLead($leadId, $tenantId);
    }

    public function deleteLeadWithAccess(int $tenantId, int $leadId, ?int $userId, bool $isAdmin): bool
    {
        if (!$this->repository->leadExistsForTenant($tenantId, $leadId)) {
            throw new \Exception('Lead não encontrado.');
        }

        if (!$isAdmin && $userId !== null && !$this->repository->canUserAccessLead($tenantId, $leadId, $userId)) {
            throw new \Exception('Acesso negado. Você não tem permissão para deletar esse lead.');
        }

        return $this->deleteLead($tenantId, $leadId);
    }

    public function deleteLeadsWithAccess(int $tenantId, array $leadIds, ?int $userId, bool $isAdmin): bool
    {
        if (empty($leadIds)) {
            throw new \Exception('IDs não fornecidos corretamente');
        }

        if (!$isAdmin && $userId !== null) {
            $accessibleCount = $this->repository->countAccessibleLeadsForUser($tenantId, $userId, $leadIds);
            if ($accessibleCount !== count($leadIds)) {
                throw new \Exception('Acesso negado. Você não tem permissão para deletar esses leads.');
            }
        }

        return $this->deleteLeads($tenantId, $leadIds);
    }

    public function assignLeadWithNotification(int $tenantId, int $leadId, ?int $assignedUserId): bool
    {
        $lead = $this->getLead($tenantId, $leadId);
        if (!$lead) {
            throw new \Exception('Lead não encontrado.');
        }

        $previousUserId = $lead->assignedUserId ?? null;
        $assigned = $this->assignLead($tenantId, $leadId, $assignedUserId);

        try {
            $interactionService = new \App\Services\LeadInteractionService($this->pdo, $tenantId);
            $interactionService->recordAssignment(
                $leadId,
                $previousUserId,
                $assignedUserId,
                null
            );
        } catch (\Exception $e) {
            error_log('[LeadService] Erro ao registrar interação de atribuição: ' . $e->getMessage());
        }

        if ($assignedUserId !== null) {
            try {
                $propertyTitle = null;
                if ($lead->propertyId !== null) {
                    $propertyTitle = $this->repository->getPropertyTitle($lead->propertyId, $tenantId);
                }

                $notificationManager = new \NotificationManager($this->pdo, $tenantId);
                $notificationManager->notifyLeadAssigned(
                    $leadId,
                    $lead->name,
                    $lead->email,
                    $assignedUserId,
                    $lead->phone,
                    $propertyTitle
                );
            } catch (\Exception $e) {
                error_log('[LeadService] Erro ao notificar atribuição de lead: ' . $e->getMessage());
            }
        }

        return $assigned;
    }

    public function updateLead(int $tenantId, int $leadId, array $data): bool
    {
        return $this->repository->updateLead($leadId, $tenantId, $data);
    }

    public function deleteLeads(int $tenantId, array $leadIds): bool
    {
        return $this->repository->deleteLeads($leadIds, $tenantId);
    }

    public function deleteLead(int $tenantId, int $leadId): bool
    {
        return $this->repository->deleteLead($leadId, $tenantId);
    }

    public function assignLead(int $tenantId, int $leadId, ?int $assignedUserId): bool
    {
        return $this->repository->assignLeadToUser($leadId, $tenantId, $assignedUserId);
    }

    public function getAssignedUserId(int $tenantId, int $leadId): ?int
    {
        return $this->repository->getAssignedUserId($leadId, $tenantId);
    }

    public function getInactiveLeads(int $tenantId, int $days = 7, int $limit = 50, ?int $filterUserId = null, ?int $currentUserId = null, bool $isAdmin = false): array
    {
        $rows = $this->repository->getInactiveLeads($tenantId, $days, $limit, $filterUserId, $currentUserId, $isAdmin);

        $leads = [];
        foreach ($rows as $lead) {
            $daysSinceInteraction = isset($lead['days_since_interaction']) && $lead['days_since_interaction'] !== null ? (int)$lead['days_since_interaction'] : 99999;

            $leads[] = [
                'id' => isset($lead['id']) ? (int)$lead['id'] : null,
                'name' => $lead['name'] ?? null,
                'email' => $lead['email'] ?? null,
                'phone' => $lead['phone'] ?? null,
                'status' => $lead['status'] ?? null,
                'assigned_user_id' => isset($lead['assigned_user_id']) ? ($lead['assigned_user_id'] !== null ? (int)$lead['assigned_user_id'] : null) : null,
                'assigned_user_name' => $lead['assigned_user_name'] ?? null,
                'created_at' => $lead['lead_created_at'] ?? null,
                'last_interaction_at' => $lead['last_interaction_at'] ?? null,
                'days_without_contact' => $daysSinceInteraction,
                'total_interactions' => isset($lead['total_interactions']) ? (int)$lead['total_interactions'] : 0,
                'recent_interactions' => !empty($lead['recent_interactions']) ? explode(',', $lead['recent_interactions']) : [],
                'temperature' => $this->classifyLeadTemperature($daysSinceInteraction),
                'priority' => $this->calculateAlertPriority($daysSinceInteraction, $lead['status'] ?? null),
                'recommendation' => $this->generateRecommendation($daysSinceInteraction, $lead['status'] ?? null, $lead['name'] ?? '')
            ];
        }

        $stats = [
            'total_checked' => count($rows),
            'critical' => count(array_filter($leads, fn($l) => ($l['priority'] ?? null) === 'critical')),
            'high' => count(array_filter($leads, fn($l) => ($l['priority'] ?? null) === 'high')),
            'medium' => count(array_filter($leads, fn($l) => ($l['priority'] ?? null) === 'medium')),
            'low' => count(array_filter($leads, fn($l) => ($l['priority'] ?? null) === 'low')),
        ];

        return [
            'data' => $leads,
            'stats' => $stats,
            'filters' => [
                'days' => $days,
                'limit' => $limit,
                'user_id' => $filterUserId && $filterUserId > 0 ? $filterUserId : null,
            ],
        ];
    }

    private function classifyLeadTemperature($daysSinceInteraction)
    {
        if ($daysSinceInteraction < 3) return 'hot';
        if ($daysSinceInteraction < 7) return 'warm';
        if ($daysSinceInteraction < 14) return 'cool';
        return 'frozen';
    }

    private function calculateAlertPriority($daysSinceInteraction, $status)
    {
        if ($daysSinceInteraction < 7) return 'low';
        if ($daysSinceInteraction < 14) return 'medium';
        if ($daysSinceInteraction < 30) return 'high';
        return 'critical';
    }

    private function generateRecommendation($daysSinceInteraction, $status, $leadName)
    {
        if ($daysSinceInteraction < 7) {
            return "📞 Manter contato regular com $leadName";
        }
        if ($daysSinceInteraction < 14) {
            return "⚠️ $leadName está esfriando - agende uma chamada";
        }
        if ($daysSinceInteraction < 30) {
            return "🔴 URGENTE: $leadName está muito frio - contato imediato necessário";
        }
        return "❌ CRÍTICO: $leadName pode estar perdido - estratégia de reengajamento necessária";
    }

    private function resolveCreatedAfter(string $period): ?string
    {
        switch ($period) {
            case 'today':
                return date('Y-m-d 00:00:00');
            case 'week':
                return date('Y-m-d H:i:s', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d H:i:s', strtotime('-30 days'));
            default:
                return null;
        }
    }
}
