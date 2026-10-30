<?php

/**
 * LeadInteractionService - Gerencia histórico de interações com leads
 * 
 * Responsabilidades:
 * - Registrar interações (visualizações, chamadas, emails, etc)
 * - Buscar histórico de interações
 * - Gerar relatórios de atividade
 * - Marcar leads como "vistos"
 */

namespace App\Services;

use App\Infrastructure\Lead\LeadInteractionRepository;
use PDO;

class LeadInteractionService
{

    private PDO $pdo;
    private int $tenantId;
    private LeadInteractionRepository $repository;

    public function __construct(PDO $pdo, int $tenantId)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        require_once __DIR__ . '/../../src/Infrastructure/Lead/LeadInteractionRepository.php';
        $this->repository = new LeadInteractionRepository($pdo);
    }

    /**
     * Tipos de interações suportadas
     */
    const INTERACTION_TYPES = [
        'viewed' => 'Visualizado',
        'called' => 'Chamada',
        'emailed' => 'Email',
        'sms' => 'SMS',
        'whatsapp' => 'WhatsApp',
        'status_change' => 'Mudança de Status',
        'assigned' => 'Atribuído',
        'notes_added' => 'Anotação Adicionada',
        'proposal_sent' => 'Proposta Enviada',
        'follow_up_scheduled' => 'Follow-up Agendado',
        'call_reminder' => 'Lembrete de Chamada',
        'task' => 'Tarefa',
        'lead_created' => 'Lead Criado',
        'comment_added' => 'Comentário Adicionado'
    ];

    /**
     * Registra uma interação com um lead
     * 
     * @param int $leadId ID do lead
     * @param string $type Tipo de interação (viewed, called, emailed, status_change, etc)
     * @param string $title Título resumido
     * @param string|null $description Descrição detalhada
     * @param string|null $oldValue Valor anterior (para status changes)
     * @param string|null $newValue Valor novo (para status changes)
     * @param array|null $metadata Dados adicionais em JSON
     * @param int|null $userId ID do usuário que fez a interação (null = sistema/automático)
     * 
     * @return int|null ID da interação criada
     */
    public function recordInteraction(
        int $leadId,
        string $type,
        string $title,
        ?string $description = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?array $metadata = null,
        ?int $userId = null
    ): ?int {
        try {
            if (!isset(self::INTERACTION_TYPES[$type])) {
                error_log("⚠ Tipo de interação inválido: {$type}");
                return null;
            }

            if (!$this->repository->leadExistsForTenant($leadId, $this->tenantId)) {
                error_log("⚠ Lead {$leadId} não encontrado ou não pertence ao tenant {$this->tenantId}");
                return null;
            }

            $metadataJson = null;
            if ($metadata !== null) {
                $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE);
            }

            $interactionId = $this->repository->insertInteraction(
                $leadId,
                $this->tenantId,
                $userId,
                $type,
                $title,
                $description,
                $oldValue,
                $newValue,
                $metadataJson
            );

            if ($interactionId) {
                error_log("✓ Interação {$type} registrada para lead {$leadId} (ID: {$interactionId})");
                $this->recalculateLeadScore($leadId);
                return $interactionId;
            }

            return null;
        } catch (\Exception $e) {
            error_log("✗ Erro ao registrar interação: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtém histórico de interações de um lead
     * 
     * @param int $leadId ID do lead
     * @param int|null $limit Limite de registros
     * @param int|null $offset Offset para paginação
     * 
     * @return array Array com histórico de interações
     */
    public function getLeadInteractions(int $leadId, ?int $limit = 50, ?int $offset = 0): array
    {
        try {
            if (!$this->repository->leadExistsForTenant($leadId, $this->tenantId)) {
                return [];
            }

            $interactions = $this->repository->fetchInteractions($leadId, $this->tenantId, $limit, $offset);

            foreach ($interactions as &$interaction) {
                $interaction['metadata'] = !empty($interaction['metadata']) ? json_decode($interaction['metadata'], true) : null;
                $interaction['type_label'] = self::INTERACTION_TYPES[$interaction['interaction_type']] ?? $interaction['interaction_type'];
            }

            return $interactions;
        } catch (\Exception $e) {
            error_log("✗ Erro ao buscar interações: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Conta interações de um lead
     * 
     * @param int $leadId ID do lead
     * @param string|null $type Filtrar por tipo (opcional)
     * 
     * @return int Número de interações
     */
    public function countInteractions(int $leadId, ?string $type = null): int
    {
        try {
            return $this->repository->countInteractions($leadId, $this->tenantId, $type);
        } catch (\Exception $e) {
            error_log("✗ Erro ao contar interações: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Registra que um lead foi visualizado
     * 
     * @param int $leadId ID do lead
     * @param int|null $userId ID do usuário que visualizou
     */
    public function recordView(int $leadId, ?int $userId = null): ?int
    {
        return $this->recordInteraction(
            $leadId,
            'viewed',
            'Lead visualizado',
            'O lead foi visualizado no painel',
            null,
            null,
            null,
            $userId
        );
    }

    /**
     * Registra uma chamada com o lead
     * 
     * @param int $leadId ID do lead
     * @param string $notes Anotações sobre a chamada
     * @param int|null $duration Duração em segundos
     * @param int|null $userId ID do usuário que fez a chamada
     */
    public function recordCall(int $leadId, string $notes, ?int $duration = null, ?int $userId = null): ?int
    {
        $metadata = [];
        if ($duration !== null) {
            $metadata['duration_seconds'] = $duration;
        }

        return $this->recordInteraction(
            $leadId,
            'called',
            'Chamada telefônica',
            $notes,
            null,
            null,
            !empty($metadata) ? $metadata : null,
            $userId
        );
    }

    /**
     * Registra envio de email
     * 
     * @param int $leadId ID do lead
     * @param string $subject Assunto do email
     * @param string $notes Notas sobre o email
     * @param int|null $userId ID do usuário
     */
    public function recordEmail(int $leadId, string $subject, string $notes, ?int $userId = null): ?int
    {
        return $this->recordInteraction(
            $leadId,
            'emailed',
            'Email enviado: ' . substr($subject, 0, 50),
            $notes,
            null,
            null,
            ['subject' => $subject],
            $userId
        );
    }

    /**
     * Registra mudança de status
     * 
     * @param int $leadId ID do lead
     * @param string $oldStatus Status anterior
     * @param string $newStatus Novo status
     * @param int|null $userId ID do usuário que fez a mudança
     */
    public function recordStatusChange(int $leadId, string $oldStatus, string $newStatus, ?int $userId = null): ?int
    {
        $statusLabels = [
            'new' => 'Novo',
            'contacted' => 'Contatado',
            'negotiating' => 'Negociando',
            'closed' => 'Fechado',
            'cancelled' => 'Cancelado'
        ];

        $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
        $newLabel = $statusLabels[$newStatus] ?? $newStatus;

        return $this->recordInteraction(
            $leadId,
            'status_change',
            "Status alterado: {$oldLabel} → {$newLabel}",
            "O status do lead foi alterado de {$oldLabel} para {$newLabel}",
            $oldStatus,
            $newStatus,
            null,
            $userId
        );
    }

    /**
     * Registra que lead foi atribuído a um usuário
     * 
     * @param int $leadId ID do lead
     * @param int|null $oldUserId Usuário anterior (null se não havia)
     * @param int|null $newUserId Novo usuário
     * @param int|null $userId ID do usuário que fez a atribuição
     */
    public function recordAssignment(int $leadId, ?int $oldUserId, ?int $newUserId, ?int $userId = null): ?int
    {
        $oldUserName = null;
        $newUserName = null;

        if ($oldUserId) {
            $oldUserName = $this->repository->getUserNameById($oldUserId, $this->tenantId) ?? "Usuário #{$oldUserId}";
        }

        if ($newUserId) {
            $newUserName = $this->repository->getUserNameById($newUserId, $this->tenantId) ?? "Usuário #{$newUserId}";
        }

        $title = "Lead atribuído";
        if ($oldUserName && $newUserName) {
            $title = "Lead reatribuído de {$oldUserName} para {$newUserName}";
        } elseif ($newUserName) {
            $title = "Lead atribuído para {$newUserName}";
        }

        return $this->recordInteraction(
            $leadId,
            'assigned',
            $title,
            "O lead foi atribuído a {$newUserName}",
            (string)$oldUserId,
            (string)$newUserId,
            null,
            $userId
        );
    }

    /**
     * Obtém estatísticas de atividade de um lead
     * 
     * @param int $leadId ID do lead
     * 
     * @return array Estatísticas
     */
    public function getActivityStats(int $leadId): array
    {
        try {
            $types = $this->repository->getActivityStats($leadId, $this->tenantId);

            $stats = [
                'total_interactions' => 0,
                'by_type' => [],
                'last_interaction' => null,
                'viewed_count' => 0,
                'called_count' => 0,
                'emailed_count' => 0
            ];

            foreach ($types as $type) {
                $count = (int)$type['count'];
                $stats['total_interactions'] += $count;
                $stats['by_type'][$type['interaction_type']] = $count;

                if (!$stats['last_interaction'] || strtotime($type['last_activity']) > strtotime($stats['last_interaction'])) {
                    $stats['last_interaction'] = $type['last_activity'];
                }

                if ($type['interaction_type'] === 'viewed') $stats['viewed_count'] = $count;
                if ($type['interaction_type'] === 'called') $stats['called_count'] = $count;
                if ($type['interaction_type'] === 'emailed') $stats['emailed_count'] = $count;
            }

            return $stats;
        } catch (\Exception $e) {
            error_log("✗ Erro ao buscar estatísticas: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Hook: Recalcular score do lead após nova interação
     * 
     * @param int $leadId ID do lead
     */
    private function recalculateLeadScore($leadId)
    {
        try {
            // Importar LeadScoringService
            require_once dirname(__FILE__) . '/LeadScoringService.php';

            $scoringService = new \App\Services\LeadScoringService($this->pdo, $this->tenantId);
            $result = $scoringService->calculateLeadScore($leadId);

            if ($result['success']) {
                error_log("✓ Score recalculado para lead {$leadId}: {$result['score']} ({$result['category']})");
            } else {
                error_log("⚠ Falha ao recalcular score para lead {$leadId}: " . $result['error']);
            }
        } catch (\Exception $e) {
            error_log("✗ Erro no hook de scoring: " . $e->getMessage());
        }
    }
}
