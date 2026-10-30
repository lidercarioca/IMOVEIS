<?php

namespace App\Services;

use App\Infrastructure\Lead\LeadScoringRepository;
use PDO;
use Exception;

/**
 * LeadScoringService
 * Calcula e gerencia scores de leads baseado em interações
 */
class LeadScoringService
{
    private PDO $pdo;
    private $tenantId;
    private LeadScoringRepository $repository;

    // Limites de categoria
    const HOT_THRESHOLD = 70;
    const WARM_THRESHOLD = 40;

    public function __construct(PDO $pdo, $tenantId = 1)
    {
        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        require_once __DIR__ . '/../../src/Infrastructure/Lead/LeadScoringRepository.php';
        $this->repository = new LeadScoringRepository($pdo);
    }

    /**
     * Calcular score para um lead específico
     */
    public function calculateLeadScore($leadId)
    {
        try {
            // Buscar dados do lead
            $lead = $this->getLeadData($leadId);
            if (!$lead) {
                throw new Exception("Lead não encontrado: $leadId");
            }

            // Calcular componentes do score
            $interactionScore = $this->calculateInteractionScore($leadId);
            $recencyScore = $this->calculateRecencyScore($lead['last_interaction']);
            $statusScore = $this->calculateStatusScore($lead['status']);
            $valueScore = $this->calculateValueScore($lead['estimated_value']);

            // Score total (máximo 100)
            $totalScore = min(100, $interactionScore + $recencyScore + $statusScore + $valueScore);

            // Determinar categoria
            $category = $this->determineCategory($totalScore);

            // Atualizar lead
            $this->updateLeadScore($leadId, $totalScore, $category);

            // Registrar no histórico
            $this->logScoreChange($leadId, $lead['current_score'], $totalScore, $lead['current_category'], $category);

            return [
                'success' => true,
                'lead_id' => $leadId,
                'score' => $totalScore,
                'category' => $category,
                'components' => [
                    'interaction' => $interactionScore,
                    'recency' => $recencyScore,
                    'status' => $statusScore,
                    'value' => $valueScore
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Recalcular scores para todos os leads de um tenant
     */
    public function recalculateAllScores()
    {
        try {
            $leadIds = $this->repository->getAllLeadIdsByTenant($this->tenantId);
            $results = [];

            foreach ($leadIds as $leadId) {
                $results[] = $this->calculateLeadScore($leadId);
            }

            $successCount = count(array_filter($results, fn($r) => $r['success']));

            return [
                'success' => true,
                'total_processed' => count($leadIds),
                'successful' => $successCount,
                'failed' => count($leadIds) - $successCount,
                'results' => $results
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Buscar dados do lead
     */
    private function getLeadData($leadId)
    {
        return $this->repository->getLeadData($leadId, $this->tenantId);
    }

    /**
     * Calcular score baseado em interações (0-40 pontos)
     */
    private function calculateInteractionScore($leadId)
    {
        $configs = $this->repository->getScoringConfig($this->tenantId);
        $interactions = $this->repository->getInteractionCountsForLead($leadId, $this->tenantId);

        $score = 0;
        foreach ($interactions as $type => $count) {
            $weight = $configs[$type]['weight'] ?? 1.0;
            $maxPoints = $configs[$type]['max_points'] ?? 20;
            $score += min($maxPoints, $count * $weight * 5);
        }

        return min(40, $score);
    }

    /**
     * Calcular score baseado em recência (0-30 pontos)
     */
    private function calculateRecencyScore($lastInteraction)
    {
        if (!$lastInteraction) {
            return 0; // Nunca interagiu
        }

        $daysSince = (time() - strtotime($lastInteraction)) / (60 * 60 * 24);

        if ($daysSince <= 1) return 30;
        if ($daysSince <= 3) return 25;
        if ($daysSince <= 7) return 20;
        if ($daysSince <= 14) return 15;
        if ($daysSince <= 30) return 10;
        if ($daysSince <= 60) return 5;
        return 0;
    }

    /**
     * Calcular score baseado no status (0-20 pontos)
     */
    private function calculateStatusScore($status)
    {
        $statusScores = [
            'qualified' => 20,
            'proposal' => 18,
            'negotiating' => 15,
            'contacted' => 10,
            'new' => 5,
            'lost' => 0,
            'archived' => 0
        ];

        return $statusScores[$status] ?? 0;
    }

    /**
     * Calcular score baseado no valor estimado (0-10 pontos)
     */
    private function calculateValueScore($estimatedValue)
    {
        if (!$estimatedValue) return 0;

        if ($estimatedValue >= 100000) return 10;
        if ($estimatedValue >= 50000) return 8;
        if ($estimatedValue >= 25000) return 6;
        if ($estimatedValue >= 10000) return 4;
        if ($estimatedValue >= 5000) return 2;
        return 1;
    }

    /**
     * Determinar categoria baseado no score
     */
    private function determineCategory($score)
    {
        if ($score >= self::HOT_THRESHOLD) return 'hot';
        if ($score >= self::WARM_THRESHOLD) return 'warm';
        return 'cold';
    }

    /**
     * Atualizar score do lead
     */
    private function updateLeadScore($leadId, $score, $category)
    {
        $this->repository->updateLeadScore($leadId, $this->tenantId, $score, $category);
    }

    /**
     * Registrar mudança no histórico
     */
    private function logScoreChange($leadId, $oldScore, $newScore, $oldCategory, $newCategory)
    {
        $this->repository->logScoreChange($leadId, $this->tenantId, $oldScore, $newScore, $oldCategory, $newCategory);
    }

    /**
     * Obter estatísticas de scoring
     */
    public function getScoringStats()
    {
        return $this->repository->getScoringStats($this->tenantId);
    }

    public function getScoreDistribution(): array
    {
        return $this->repository->getScoreDistribution($this->tenantId);
    }

    public function getTopLeadsByScore(int $limit = 10): array
    {
        return $this->repository->getTopLeadsByScore($this->tenantId, $limit);
    }
}
