<?php

/**
 * Script para adicionar método getInactiveLeads ao LeadRepository
 * Uso: php scripts/add_get_inactive_leads_method.php
 */

$filePath = __DIR__ . '/../src/Infrastructure/Lead/LeadRepository.php';
$fileContent = file_get_contents($filePath);

// Procurar pelo ponto de inserção (antes de "private function buildFilters")
$searchStr = '    private function buildFilters(string $sql, array $filters): array';
$newMethod = '    public function getInactiveLeads(int $tenantId, int $days = 7, int $limit = 50, ?int $filterUserId = null, ?int $currentUserId = null, bool $isAdmin = false): array
    {
        $sql = "
            SELECT 
                l.id,
                l.name,
                l.email,
                l.phone,
                l.status,
                l.created_at as lead_created_at,
                l.assigned_user_id,
                u.name as assigned_user_name,
                sub.last_interaction_at,
                DATEDIFF(NOW(), sub.last_interaction_at) as days_since_interaction,
                sub.total_interactions,
                sub.recent_interactions
            FROM leads l
            LEFT JOIN users u ON l.assigned_user_id = u.id
            LEFT JOIN (
                SELECT 
                    li.lead_id,
                    MAX(li.created_at) as last_interaction_at,
                    COUNT(li.id) as total_interactions,
                    GROUP_CONCAT(DISTINCT li.interaction_type ORDER BY li.created_at DESC LIMIT 3) as recent_interactions
                FROM lead_interactions li
                GROUP BY li.lead_id
            ) sub ON l.id = sub.lead_id
            WHERE l.tenant_id = ?
            AND l.status NOT IN (\'converted\', \'lost\', \'archived\')
            AND (
                sub.last_interaction_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                OR sub.last_interaction_at IS NULL
            )
        ";

        $params = [$tenantId, $days];

        if ($filterUserId !== null && $filterUserId > 0) {
            $sql .= " AND l.assigned_user_id = ?";
            $params[] = $filterUserId;
        }

        if (!$isAdmin && $currentUserId !== null && $currentUserId > 0) {
            $sql .= " AND (l.assigned_user_id = ? OR l.property_id IN (SELECT id FROM properties WHERE assigned_user_id = ? AND tenant_id = ?))";
            $params[] = $currentUserId;
            $params[] = $currentUserId;
            $params[] = $tenantId;
        }

        $sql .= " ORDER BY days_since_interaction DESC, sub.last_interaction_at ASC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    ' . $searchStr;

if (strpos($fileContent, $searchStr) === false) {
    echo "ERROR: Não foi possível encontrar o ponto de inserção\n";
    exit(1);
}

$newContent = str_replace($searchStr, $newMethod, $fileContent);

file_put_contents($filePath, $newContent);
echo "✓ Método getInactiveLeads() adicionado ao LeadRepository\n";

// Verificar sintaxe
$output = shell_exec('php -l ' . escapeshellarg($filePath) . ' 2>&1');
if (strpos($output, 'No syntax errors') === false) {
    echo "ERROR: Erro de sintaxe após adicionar método:\n" . $output;
    exit(1);
}

echo "✓ Sintaxe verificada com sucesso\n";
exit(0);
