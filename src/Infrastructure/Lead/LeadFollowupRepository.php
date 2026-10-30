<?php

namespace App\Infrastructure\Lead;

use PDO;

class LeadFollowupRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSequenceByIdWithSteps(int $sequenceId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM lead_followup_sequences
            WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$sequenceId, $tenantId]);
        $sequence = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sequence) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM lead_followup_steps
            WHERE sequence_id = ?
            ORDER BY step_order ASC"
        );
        $stmt->execute([$sequenceId]);
        $sequence['steps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $sequence;
    }

    public function listSequences(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.*,
                   COUNT(st.id) as steps_count,
                   COUNT(fs.id) as scheduled_count
            FROM lead_followup_sequences s
            LEFT JOIN lead_followup_steps st ON s.id = st.sequence_id
            LEFT JOIN lead_followup_scheduled fs ON s.id = fs.sequence_id AND fs.status = 'pending'
            WHERE s.tenant_id = ?
            GROUP BY s.id
            ORDER BY s.created_at DESC"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggleSequenceActive(int $sequenceId, bool $isActive, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE lead_followup_sequences
            SET is_active = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?"
        );

        return $stmt->execute([$isActive, $sequenceId, $tenantId]);
    }

    public function getDiagnoseReport(int $tenantId): array
    {
        $report = [];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM lead_followup_sequences WHERE tenant_id = ? AND is_active = TRUE");
        $stmt->execute([$tenantId]);
        $report['active_sequences'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM lead_followup_scheduled WHERE tenant_id = ? AND status = 'pending'");
        $stmt->execute([$tenantId]);
        $report['total_pending'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM lead_followup_scheduled WHERE tenant_id = ? AND status = 'pending' AND scheduled_at <= NOW()");
        $stmt->execute([$tenantId]);
        $report['pending_ready'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $report['pending_future'] = $report['total_pending'] - $report['pending_ready'];

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM lead_followup_scheduled WHERE tenant_id = ? AND status = 'executed' AND DATE(executed_at) = CURDATE()");
        $stmt->execute([$tenantId]);
        $report['executed_today'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM lead_followup_scheduled WHERE tenant_id = ? AND status = 'failed'");
        $stmt->execute([$tenantId]);
        $report['failed'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $this->pdo->prepare(
            "SELECT scheduled_at, l.name as lead_name, seq.name as sequence_name
            FROM lead_followup_scheduled fs
            LEFT JOIN leads l ON fs.lead_id = l.id
            LEFT JOIN lead_followup_sequences seq ON fs.sequence_id = seq.id
            WHERE fs.tenant_id = ? AND fs.status = 'pending'
            ORDER BY fs.scheduled_at ASC
            LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $report['next_followup'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $this->pdo->prepare(
            "SELECT executed_at, l.name as lead_name, seq.name as sequence_name, status
            FROM lead_followup_scheduled fs
            LEFT JOIN leads l ON fs.lead_id = l.id
            LEFT JOIN lead_followup_sequences seq ON fs.sequence_id = seq.id
            WHERE fs.tenant_id = ?
            ORDER BY fs.updated_at DESC
            LIMIT 5"
        );
        $stmt->execute([$tenantId]);
        $report['recent_followups'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $report;
    }

    public function getActiveStepsForSequence(int $sequenceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM lead_followup_steps
            WHERE sequence_id = ? AND is_active = TRUE
            ORDER BY step_order ASC"
        );
        $stmt->execute([$sequenceId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLeadIdsForStatus(string $status, int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM leads WHERE status = ? AND tenant_id = ?');
        $stmt->execute([$status, $tenantId]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    public function getLeadIdsForScoreThreshold(int $scoreThreshold, int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM leads WHERE score <= ? AND tenant_id = ?');
        $stmt->execute([$scoreThreshold, $tenantId]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    public function getInactiveLeadIds(int $daysWithoutContact, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.id
            FROM leads l
            LEFT JOIN (
                SELECT lead_id, MAX(created_at) as last_contact
                FROM lead_interactions
                WHERE tenant_id = ?
                GROUP BY lead_id
            ) li ON l.id = li.lead_id
            WHERE l.tenant_id = ?
              AND (li.last_contact IS NULL OR li.last_contact <= DATE_SUB(NOW(), INTERVAL ? DAY))"
        );
        $stmt->execute([$tenantId, $tenantId, $daysWithoutContact]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    public function hasPendingScheduledFollowup(int $leadId, int $sequenceId, int $stepId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM lead_followup_scheduled
            WHERE lead_id = ? AND sequence_id = ? AND step_id = ? AND tenant_id = ? AND status = 'pending'
            LIMIT 1"
        );
        $stmt->execute([$leadId, $sequenceId, $stepId, $tenantId]);

        return (bool)$stmt->fetchColumn();
    }

    public function scheduleFollowupStep(int $leadId, int $tenantId, int $sequenceId, int $stepId, string $scheduledAt, string $subject, string $body, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO lead_followup_scheduled
            (lead_id, tenant_id, sequence_id, step_id, scheduled_at, subject, body, data, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())"
        );

        return $stmt->execute([$leadId, $tenantId, $sequenceId, $stepId, $scheduledAt, $subject, $body, $data]);
    }

    public function getPendingFollowupsForExecution(int $tenantId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fs.*, l.email as lead_email, l.name as lead_name, seq.name as sequence_name
            FROM lead_followup_scheduled fs
            LEFT JOIN leads l ON fs.lead_id = l.id AND fs.tenant_id = l.tenant_id
            LEFT JOIN lead_followup_sequences seq ON fs.sequence_id = seq.id AND fs.tenant_id = seq.tenant_id
            WHERE fs.tenant_id = ? AND fs.status = 'pending' AND fs.scheduled_at <= NOW()
            ORDER BY fs.scheduled_at ASC
            LIMIT ?"
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markScheduledFollowupExecuted(int $scheduledId, int $tenantId, ?string $message = null): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE lead_followup_scheduled
            SET status = 'executed', executed_at = NOW(), updated_at = NOW(), error_message = ?
            WHERE id = ? AND tenant_id = ?"
        );

        return $stmt->execute([$message, $scheduledId, $tenantId]);
    }

    public function markScheduledFollowupFailed(int $scheduledId, int $tenantId, string $errorMessage): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE lead_followup_scheduled
            SET status = 'failed', updated_at = NOW(), error_message = ?
            WHERE id = ? AND tenant_id = ?"
        );

        return $stmt->execute([$errorMessage, $scheduledId, $tenantId]);
    }

    public function createSequence(array $data): ?int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO lead_followup_sequences
            (tenant_id, name, description, trigger_condition, trigger_value, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );

        $result = $stmt->execute([
            $data['tenant_id'],
            $data['name'],
            $data['description'],
            $data['trigger_condition'],
            $data['trigger_value'],
            $data['is_active'] ? 1 : 0
        ]);

        return $result ? (int)$this->pdo->lastInsertId() : null;
    }

    public function createStep(int $sequenceId, array $step): ?int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO lead_followup_steps
            (sequence_id, step_order, delay_days, delay_hours, action_type, template_subject, template_body, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $result = $stmt->execute([
            $sequenceId,
            $step['step_order'],
            $step['delay_days'],
            $step['delay_hours'] ?? 0,
            $step['action_type'],
            $step['template_subject'] ?? '',
            $step['template_body'] ?? '',
            $step['is_active'] ? 1 : 0
        ]);

        return $result ? (int)$this->pdo->lastInsertId() : null;
    }

    public function deleteScheduledFollowupsForSequence(int $sequenceId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM lead_followup_scheduled WHERE sequence_id = ? AND tenant_id = ?');
        return $stmt->execute([$sequenceId, $tenantId]);
    }

    public function deleteStepsForSequence(int $sequenceId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM lead_followup_steps WHERE sequence_id = ?');
        return $stmt->execute([$sequenceId]);
    }

    public function getFollowupStats(int $tenantId): array
    {
        $stats = [];

        $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM lead_followup_sequences WHERE tenant_id = ? AND is_active = TRUE');
        $stmt->execute([$tenantId]);
        $stats['active_sequences'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM lead_followup_scheduled WHERE tenant_id = ? AND status = "pending"');
        $stmt->execute([$tenantId]);
        $stats['pending_followups'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM lead_followup_scheduled WHERE tenant_id = ? AND status = "executed" AND DATE(executed_at) = CURDATE()');
        $stmt->execute([$tenantId]);
        $stats['executed_today'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        return $stats;
    }

    public function updateSequence(int $sequenceId, int $tenantId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE lead_followup_sequences
            SET name = ?, description = ?, trigger_condition = ?, trigger_value = ?, is_active = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?"
        );

        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['trigger_condition'],
            $data['trigger_value'],
            $data['is_active'] ? 1 : 0,
            $sequenceId,
            $tenantId
        ]);
    }

    public function deleteSequence(int $sequenceId, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM lead_followup_sequences WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([$sequenceId, $tenantId]);
    }

    public function getActiveSequences(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lead_followup_sequences WHERE tenant_id = ? AND is_active = TRUE ORDER BY created_at DESC');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createFollowupTask(array $data): ?int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO agendamentos
            (lead_id, tenant_id, property_id, user_id, title, description, due_date, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );

        $result = $stmt->execute([
            $data['lead_id'],
            $data['tenant_id'],
            $data['property_id'],
            $data['user_id'],
            $data['title'],
            $data['description'],
            $data['due_date'],
            $data['status']
        ]);

        return $result ? (int)$this->pdo->lastInsertId() : null;
    }
}
