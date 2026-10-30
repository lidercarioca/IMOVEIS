<?php

namespace App\Services;

use App\Infrastructure\Lead\LeadFollowupRepository;
use App\Infrastructure\Lead\LeadRepository;
use PDO;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * LeadFollowupService
 * Gerencia sequências de follow-up automático para leads
 */
class LeadFollowupService
{
    private PDO $pdo;
    private $tenantId;
    private LeadFollowupRepository $repository;

    private LeadRepository $leadRepository;

    public function __construct(PDO $pdo, int $tenantId)
    {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant inválido para follow-up.');
        }

        $this->pdo = $pdo;
        $this->tenantId = $tenantId;
        require_once __DIR__ . '/../../src/Infrastructure/Lead/LeadFollowupRepository.php';
        require_once __DIR__ . '/../../src/Infrastructure/Lead/LeadRepository.php';
        $this->repository = new LeadFollowupRepository($pdo);
        $this->leadRepository = new LeadRepository($pdo);
    }

    /**
     * Verificar e agendar follow-ups baseado em triggers
     */
    public function checkAndScheduleFollowups($leadId = null)
    {
        try {
            // Buscar sequências ativas
            $sequences = $this->getActiveSequences();

            $scheduled = 0;
            $checked = 0;

            foreach ($sequences as $sequence) {
                $checked++;
                $leadIds = $this->getLeadIdsForSequence($sequence, $leadId);

                foreach ($leadIds as $id) {
                    $scheduled += $this->scheduleSequenceSteps($sequence, $id);
                }
            }

            return [
                'success' => true,
                'sequences_checked' => $checked,
                'followups_scheduled' => $scheduled
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obter lista de leads que correspondem à trigger de uma sequência
     */
    private function getLeadIdsForSequence(array $sequence, $leadId = null)
    {
        $condition = $sequence['trigger_condition'];
        $value = $sequence['trigger_value'];

        switch ($condition) {
            case 'lead_created':
                return $leadId ? [$leadId] : [];

            case 'status_changed':
                if ($leadId) {
                    $leadState = $this->leadRepository->getLeadState($this->tenantId, $leadId);
                    return $leadState && $leadState['status'] === $value ? [$leadId] : [];
                }

                return $this->repository->getLeadIdsForStatus($value, $this->tenantId);

            case 'score_changed':
                if ($leadId) {
                    $leadState = $this->leadRepository->getLeadState($this->tenantId, $leadId);
                    return $leadState && $leadState['score'] >= (int)$value ? [$leadId] : [];
                }

                return $this->repository->getLeadIdsForScoreThreshold((int)$value, $this->tenantId);

            case 'inactivity_days':
                $days = (int)$value;
                if ($leadId) {
                    $inactiveIds = $this->repository->getInactiveLeadIds($days, $this->tenantId);
                    return in_array($leadId, $inactiveIds, true) ? [$leadId] : [];
                }

                return $this->repository->getInactiveLeadIds($days, $this->tenantId);

            default:
                return [];
        }
    }

    /**
     * Agendar passos de uma sequência para um lead
     */
    private function scheduleSequenceSteps($sequence, $leadId)
    {
        if (!$leadId) {
            return 0;
        }

        $steps = $this->repository->getActiveStepsForSequence($sequence['id']);
        $scheduled = 0;

        foreach ($steps as $step) {
            $scheduledAt = $this->calculateScheduledTime($step);

            if (!$this->repository->hasPendingScheduledFollowup($leadId, $sequence['id'], $step['id'], $this->tenantId)) {
                if ($this->repository->scheduleFollowupStep(
                    $leadId,
                    $this->tenantId,
                    $sequence['id'],
                    $step['id'],
                    $scheduledAt,
                    $step['template_subject'] ?? '',
                    $step['template_body'] ?? '',
                    json_encode($step)
                )) {
                    $scheduled++;
                }
            }
        }

        return $scheduled;
    }

    /**
     * Executar follow-ups pendentes
     * @param bool $forceExecute Se true, executa todos os pendentes, independente do horário agendado
     */
    public function executePendingFollowups($forceExecute = false, $maxTimeSeconds = 90)
    {
        try {
            $startTime = microtime(true);

            $pendingFollowups = $this->repository->getPendingFollowupsForExecution($this->tenantId);

            $executed = 0;
            $failed = 0;
            $skipped = 0;

            foreach ($pendingFollowups as $followup) {
                // ⚠️ VERIFICAR TIMEOUT A CADA ITERAÇÃO
                $elapsed = microtime(true) - $startTime;
                if ($elapsed > ($maxTimeSeconds - 5)) {
                    // Deixar 5 segundos de margem para cleanup
                    error_log("[FOLLOWUP] Timeout iminente. Processados: {$executed}, Falhados: {$failed}, Skipped: {$skipped}");
                    $skipped = count($pendingFollowups) - ($executed + $failed);
                    break;
                }

                $result = $this->executeFollowup($followup);
                if ($result['success']) {
                    $executed++;
                } else {
                    $failed++;
                }
            }

            $totalTime = round(microtime(true) - $startTime, 2);

            return [
                'success' => true,
                'pending_found' => count($pendingFollowups),
                'executed' => $executed,
                'failed' => $failed,
                'skipped' => $skipped,
                'total_time' => $totalTime,
                'force_execute' => $forceExecute
            ];
        } catch (Exception $e) {
            error_log("[FOLLOWUP ERROR] " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Criar nova sequência de follow-up
     */
    public function createSequence($data)
    {
        try {
            $sequenceId = $this->repository->createSequence([
                'tenant_id' => $this->tenantId,
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'trigger_condition' => $data['trigger_condition'],
                'trigger_value' => $data['trigger_value'],
                'is_active' => $data['is_active'] ?? true
            ]);

            if (!$sequenceId) {
                throw new Exception('Falha ao criar sequência');
            }

            if (isset($data['steps']) && is_array($data['steps'])) {
                foreach ($data['steps'] as $stepData) {
                    $this->createStep($sequenceId, $stepData);
                }
            }

            return [
                'success' => true,
                'sequence_id' => $sequenceId
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Criar passo para uma sequência
     */
    private function createStep($sequenceId, $stepData)
    {
        $this->repository->createStep($sequenceId, [
            'step_order' => $stepData['step_order'],
            'delay_days' => $stepData['delay_days'],
            'delay_hours' => $stepData['delay_hours'] ?? 0,
            'action_type' => $stepData['action_type'],
            'template_subject' => $stepData['template_subject'] ?? '',
            'template_body' => $stepData['template_body'] ?? '',
            'is_active' => $stepData['is_active'] ?? true
        ]);
    }

    /**
     * Atualizar sequência de follow-up
     */
    public function updateSequence($sequenceId, $data)
    {
        try {
            $updated = $this->repository->updateSequence($sequenceId, $this->tenantId, [
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'trigger_condition' => $data['trigger_condition'],
                'trigger_value' => $data['trigger_value'],
                'is_active' => $data['is_active'] ?? true
            ]);

            if (!$updated) {
                throw new Exception('Falha ao atualizar sequência');
            }

            if (isset($data['steps']) && is_array($data['steps'])) {
                $this->repository->deleteStepsForSequence($sequenceId);

                foreach ($data['steps'] as $stepData) {
                    $this->createStep($sequenceId, $stepData);
                }
            }

            return [
                'success' => true
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getSequenceByIdWithSteps($sequenceId)
    {
        return $this->repository->getSequenceByIdWithSteps($sequenceId, $this->tenantId);
    }

    public function listSequences()
    {
        return $this->repository->listSequences($this->tenantId);
    }

    public function toggleSequenceActive($sequenceId, $isActive)
    {
        return $this->repository->toggleSequenceActive($sequenceId, $isActive, $this->tenantId);
    }

    public function getDiagnoseReport()
    {
        return $this->repository->getDiagnoseReport($this->tenantId);
    }

    /**
     * Excluir sequência de follow-up
     */
    public function deleteSequence($sequenceId)
    {
        try {
            $deleted = $this->repository->deleteSequence($sequenceId, $this->tenantId);
            if (!$deleted) {
                throw new Exception('Falha ao excluir sequência');
            }

            return [
                'success' => true
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Buscar sequências ativas
     */
    private function getActiveSequences()
    {
        return $this->repository->getActiveSequences($this->tenantId);
    }

    /**
     * Verificar se condição de trigger é satisfeita
     */
    private function checkTriggerCondition($sequence, $leadId = null)
    {
        $condition = $sequence['trigger_condition'];
        $value = $sequence['trigger_value'];

        switch ($condition) {
            case 'lead_created':
                return $leadId !== null;

            case 'status_changed':
                if (!$leadId) {
                    return false;
                }

                $leadState = $this->leadRepository->getLeadState($this->tenantId, $leadId);
                return $leadState && $leadState['status'] === $value;

            case 'score_changed':
                if (!$leadId) {
                    return false;
                }

                $leadState = $this->leadRepository->getLeadState($this->tenantId, $leadId);
                return $leadState && $leadState['score'] >= (int)$value;

            case 'inactivity_days':
                $days = (int)$value;
                if ($leadId) {
                    $inactiveIds = $this->repository->getInactiveLeadIds($days, $this->tenantId);
                    return in_array($leadId, $inactiveIds, true);
                }

                return !empty($this->repository->getInactiveLeadIds($days, $this->tenantId));

            default:
                return false;
        }
    }

    /**
     * Calcular horário agendado para um passo
     */
    private function calculateScheduledTime($step)
    {
        $delayDays = $step['delay_days'];
        $delayHours = $step['delay_hours'];

        $scheduledTime = time() + ($delayDays * 24 * 60 * 60) + ($delayHours * 60 * 60);

        return date('Y-m-d H:i:s', $scheduledTime);
    }

    /**
     * Executar um follow-up específico
     */
    private function executeFollowup($followup)
    {
        try {
            // Validar dados básicos
            if (empty($followup['id'])) {
                return ['success' => false, 'error' => 'ID do follow-up não disponível'];
            }

            $actionType = $followup['action_type'] ?? null;

            switch ($actionType) {
                case 'email':
                    $result = $this->sendFollowupEmail($followup);
                    break;

                case 'sms':
                    $result = $this->sendFollowupSMS($followup);
                    break;

                case 'call_reminder':
                    $result = $this->createCallReminder($followup);
                    break;

                case 'task':
                    $result = $this->createTask($followup);
                    break;

                default:
                    $result = ['success' => false, 'error' => 'Tipo de ação desconhecido: ' . $actionType];
            }

            $status = $result['success'] ? 'executed' : 'failed';
            $errorMessage = $result['success'] ? null : ($result['error'] ?? 'Erro desconhecido');

            try {
                if ($result['success']) {
                    $this->repository->markScheduledFollowupExecuted($followup['id'], $this->tenantId, $errorMessage);
                } else {
                    $this->repository->markScheduledFollowupFailed($followup['id'], $this->tenantId, $errorMessage);
                }
            } catch (Exception $updateError) {
                error_log("[FOLLOWUP UPDATE ERROR] Erro ao atualizar status: " . $updateError->getMessage());
            }

            return $result;
        } catch (Exception $e) {
            error_log("[FOLLOWUP EXECUTE ERROR] " . $e->getMessage());

            try {
                $this->repository->markScheduledFollowupFailed($followup['id'] ?? 0, $this->tenantId, $e->getMessage());
            } catch (Exception $ignoreError) {
                // Ignorar erros de atualização
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enviar email de follow-up
     */
    private function sendFollowupEmail($followup)
    {
        try {
            // Autoload já carregado no ponto de entrada (execute.php ou followup_cron.php)
            require __DIR__ . '/../../config/notification.php';

            $subject = $this->replaceVariables($followup['template_subject'] ?? '', $followup);
            $body = $this->replaceVariables($followup['template_body'] ?? '', $followup);
            $email = $followup['email'] ?? null;

            if (empty($email)) {
                return ['success' => false, 'error' => 'Email não disponível para o lead'];
            }

            $mail = new PHPMailer(true);
            try {
                // Configuração SMTP
                $mail->isSMTP();
                $mail->Host = $SMTP_CONFIG['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $SMTP_CONFIG['username'];
                $mail->Password = $SMTP_CONFIG['password'];
                $mail->SMTPSecure = $SMTP_CONFIG['secure'];
                $mail->Port = $SMTP_CONFIG['port'];

                // ⚠️ TIMEOUTS CRÍTICOS - evita travamento
                $mail->Timeout = 10;           // Timeout de socket
                $mail->SMTPKeepAlive = false;  // Fecha conexão após cada email
                $mail->SingleTo = true;         // Um email por vez
                $mail->SMTPDebug = 0;           // Sem debug output

                $mail->setFrom($SMTP_CONFIG['username'], $FROM_NAME ?? 'Sistema');
                $mail->addAddress($email, $followup['lead_name'] ?? '');
                $mail->Subject = $subject;
                $mail->isHTML(true);
                $mail->Body = nl2br($body);
                $mail->CharSet = 'UTF-8';

                // Criar timeout para o send também
                set_time_limit(15); // 15 segundos por email
                $mail->send();

                error_log("[FOLLOWUP] Email enviado para {$email}: {$subject}");
                return ['success' => true, 'message' => 'Email enviado com sucesso'];
            } catch (PHPMailerException $e) {
                error_log("[FOLLOWUP ERROR] PHPMailer: " . $e->getMessage());
                return ['success' => false, 'error' => 'Erro ao enviar email: ' . $e->getMessage()];
            }
        } catch (Exception $e) {
            error_log("[FOLLOWUP ERROR] Erro ao enviar email: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enviar SMS de follow-up
     */
    private function sendFollowupSMS($followup)
    {
        try {
            // Aqui seria integrada com um serviço de SMS
            // Por enquanto, apenas simula o envio

            $body = $this->replaceVariables($followup['template_body'] ?? '', $followup);
            $phone = $followup['phone'] ?? null;

            if (empty($phone)) {
                return ['success' => false, 'error' => 'Telefone não disponível para o lead'];
            }

            error_log("[FOLLOWUP] SMS enviado para {$phone}: {$body}");

            return ['success' => true, 'message' => 'SMS enviado com sucesso'];
        } catch (Exception $e) {
            error_log("[FOLLOWUP ERROR] Erro ao enviar SMS: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Criar lembrete de ligação
     */
    private function createCallReminder($followup)
    {
        try {
            // Simular criação de lembrete de ligação
            // TODO: integrar com LeadInteractionService quando estiver disponível

            $title = $this->replaceVariables($followup['template_subject'] ?? 'Lembrete de Ligação', $followup);
            $description = $this->replaceVariables($followup['template_body'] ?? 'Fazer ligação para {lead_name}', $followup);

            error_log("[FOLLOWUP] Call Reminder para lead {$followup['lead_id']}: {$title}");

            return ['success' => true, 'message' => 'Lembrete de ligação criado'];
        } catch (Exception $e) {
            error_log("[FOLLOWUP ERROR] Erro ao criar call reminder: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Criar tarefa
     */
    private function createTask($followup)
    {
        try {
            $leadId = $followup['lead_id'];
            $scheduledAt = $followup['scheduled_at'] ?? date('Y-m-d H:i:s');
            $title = $this->replaceVariables($followup['template_subject'] ?? 'Nova Tarefa', $followup);
            $description = $this->replaceVariables($followup['template_body'] ?? 'Tarefa para {{lead_name}}', $followup);

            $lead = $this->leadRepository->findLeadByIdAndTenant($leadId, $this->tenantId);
            if (!$lead) {
                return ['success' => false, 'error' => 'Lead não encontrado'];
            }

            $propertyId = $lead->propertyId;
            $userId = $lead->assignedUserId;

            if (!$propertyId) {
                error_log("[FOLLOWUP] Nenhum imóvel vinculado ao lead $leadId. Não foi possível criar tarefa.");
                return ['success' => false, 'error' => 'Nenhum imóvel vinculado ao lead'];
            }

            if (!$userId) {
                error_log("[FOLLOWUP] Nenhum corretor atribuído ao lead $leadId. Não foi possível criar tarefa.");
                return ['success' => false, 'error' => 'Nenhum corretor atribuído ao lead'];
            }

            $taskId = $this->repository->createFollowupTask([
                'lead_id' => $leadId,
                'tenant_id' => $this->tenantId,
                'property_id' => $propertyId,
                'user_id' => $userId,
                'title' => $title,
                'description' => $description,
                'due_date' => $scheduledAt,
                'status' => 'pendente'
            ]);

            if ($taskId) {
                error_log("[FOLLOWUP] Task criada para lead {$leadId}: {$title} (ID: $taskId)");
                return ['success' => true, 'message' => 'Tarefa criada', 'task_id' => $taskId];
            }

            error_log("[FOLLOWUP ERROR] Falha ao inserir tarefa no banco para lead $leadId");
            return ['success' => false, 'error' => 'Falha ao inserir tarefa no banco'];
        } catch (Exception $e) {
            error_log("[FOLLOWUP ERROR] Erro ao criar task: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Substituir variáveis nos templates
     */
    // SUBSTITUA APENAS ESTE MÉTODO NO SEU ARQUIVO
    private function replaceVariables($template, $followup)
    {
        if (empty($template)) {
            return '';
        }

        $variables = [
            // padrão atual
            '{{nome}}' => $followup['lead_name'] ?? 'Cliente',
            '{{lead_name}}' => $followup['lead_name'] ?? 'Lead',
            '{{lead_email}}' => $followup['email'] ?? '',
            '{{lead_phone}}' => $followup['phone'] ?? '',
            '{{sequence_name}}' => $followup['sequence_name'] ?? '',
            '{{company_name}}' => $followup['company_name'] ?? 'Imobiliária',

            // template principal
            '{{seu_nome}}' => $followup['user_name'] ?? 'Corretor',
            '{{email}}' => $followup['email'] ?? '',
            '{{telefone}}' => !empty($followup['user_phone'])
                ? $followup['user_phone']
                : 'Telefone não informado',
            '{{nome_imobiliaria}}' => $followup['company_name'] ?? 'Imobiliária',

            // adicionais
            '{{imovel_titulo}}' => $followup['property_title'] ?? 'Imóvel não informado',

            '{{valor_interesse}}' => isset($followup['property_value'])
                ? 'R$ ' . number_format($followup['property_value'], 2, ',', '.')
                : 'Sob consulta',

            '{{data_ultimo_contato}}' => !empty($followup['last_interaction'])
                ? date('d/m/Y', strtotime($followup['last_interaction']))
                : 'Não informado'
        ];

        return str_replace(array_keys($variables), array_values($variables), $template);
    }

    /**
     * Buscar estatísticas de follow-up
     */
    public function getFollowupStats()
    {
        return $this->repository->getFollowupStats($this->tenantId);
    }
}
