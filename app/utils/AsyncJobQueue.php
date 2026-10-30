<?php

/**
 * AsyncJobQueue - Gerenciador de fila de jobs assíncronos
 * Executa tarefas pesadas em background sem bloquear a requisição
 */

class AsyncJobQueue
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Enfileira um job para ser processado em background
     */
    public function enqueue($jobType, $data, $priority = 'normal')
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO async_jobs (job_type, job_data, status, priority, created_at)
                VALUES (?, ?, 'pending', ?, NOW())
            ");

            $result = $stmt->execute([
                $jobType,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                $priority
            ]);

            $jobId = $this->pdo->lastInsertId();
            error_log("[AsyncJobQueue] Job enfileirado: ID={$jobId}, Type={$jobType}, Priority={$priority}");

            // Processa JOB IMEDIATAMENTE se for high priority (notificações)
            // ou tenta via CURL em background para outros
            if ($result) {
                if ($priority === 'high') {
                    error_log("[AsyncJobQueue] Processando job HIGH PRIORITY imediatamente");
                    // Processa imediatamente (notificações são críticas)
                    try {
                        $this->processPending(1);
                    } catch (Exception $e) {
                        error_log("[AsyncJobQueue] Erro ao processar job high-priority: " . $e->getMessage());
                    }
                } else {
                    // Tenta via CURL em background
                    $this->triggerAsyncProcessor();
                }
            }

            return $jobId;
        } catch (Exception $e) {
            error_log("[AsyncJobQueue] Erro ao enfileirar job: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispara o processador de jobs via CURL não-bloqueant
     */
    private function triggerAsyncProcessor()
    {
        try {
            $processorUrl = getenv('ASYNC_JOB_PROCESSOR_URL') ?: 'http://localhost/api/v1/utils/process-async-jobs.php';
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $processorUrl,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_TIMEOUT_MS => 100,           // Timeout curto
                CURLOPT_CONNECTTIMEOUT_MS => 50,
                CURLOPT_NOSIGNAL => 1,
                CURLOPT_FORBID_REUSE => true,
                CURLOPT_FRESH_CONNECT => true,
                CURLOPT_HTTPHEADER => [
                    'X-Async-Trigger: true'
                ]
            ]);

            curl_exec($ch);
        } catch (Exception $e) {
            // Falha silenciosa - job será processado na próxima chamada
        }
    }

    /**
     * Processa jobs pendentes
     */
    public function processPending($limit = 10)
    {
        try {
            // Pegar jobs pendentes ordenados por prioridade
            $stmt = $this->pdo->prepare("
                SELECT id, job_type, job_data 
                FROM async_jobs 
                WHERE status = 'pending'
                ORDER BY 
                    CASE WHEN priority = 'high' THEN 0 
                         WHEN priority = 'normal' THEN 1 
                         ELSE 2 
                    END,
                    created_at ASC
                LIMIT ?
            ");

            $stmt->execute([$limit]);
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $processed = 0;
            foreach ($jobs as $job) {
                try {
                    $this->executeJob($job);
                    $processed++;
                } catch (Exception $e) {
                    error_log("[AsyncJobQueue] Erro ao executar job {$job['id']}: " . $e->getMessage());
                    // Marca como falho
                    $this->markJobFailed($job['id'], $e->getMessage());
                }
            }

            return [
                'success' => true,
                'processed' => $processed
            ];
        } catch (Exception $e) {
            error_log("[AsyncJobQueue] Erro ao processar jobs: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obter métricas do estado da fila de jobs
     */
    public function getMetrics($limit = 10)
    {
        try {
            $stmt = $this->pdo->query("SELECT status, COUNT(*) AS total FROM async_jobs GROUP BY status");
            $counts = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $counts[$row['status']] = (int)$row['total'];
            }

            $stmt = $this->pdo->prepare("SELECT id, job_type, status, error_message, created_at, completed_at FROM async_jobs WHERE status = 'failed' ORDER BY completed_at DESC LIMIT ?");
            $stmt->execute([$limit]);
            $failedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'counts' => $counts,
                'recent_failed_jobs' => $failedJobs,
            ];
        } catch (Exception $e) {
            error_log("[AsyncJobQueue] Erro ao coletar métricas da fila: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Executa um job individual baseado no tipo
     */
    private function executeJob($job)
    {
        $jobId = $job['id'];
        $jobType = $job['job_type'];
        $data = json_decode($job['job_data'], true);

        error_log("[AsyncJobQueue] Executando job: ID={$jobId}, Type={$jobType}");

        // Marca como em processamento
        $stmt = $this->pdo->prepare("
            UPDATE async_jobs SET status = 'processing', started_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$jobId]);

        // Executar job baseado no tipo
        switch ($jobType) {
            case 'process_property_images':
                error_log("[AsyncJobQueue] Processando imagens para propriedade {$data['property_id']}");
                $this->processPropertyImages($data, $jobId);
                break;

            case 'create_financial_records':
                error_log("[AsyncJobQueue] Criando registros financeiros para propriedade {$data['property_id']}");
                $this->createFinancialRecords($data, $jobId);
                break;

            case 'send_notifications':
                error_log("[AsyncJobQueue] Enviando notificações para propriedade {$data['property_id']}");
                $this->sendNotifications($data, $jobId);
                break;

            default:
                throw new Exception("Job type desconhecido: {$jobType}");
        }

        // Marca como completo
        $stmt = $this->pdo->prepare("
            UPDATE async_jobs SET status = 'completed', completed_at = NOW() WHERE id = ?
        ");
        $stmt->execute([$jobId]);
    }

    /**
     * Processa imagens do imóvel
     */
    private function processPropertyImages($data, $jobId)
    {
        $propertyId = $data['property_id'];
        $imagensAntigas = $data['imagens_antigas'] ?? [];
        $novasImagens = $data['novas_imagens'] ?? [];

        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . "/assets/imagens/{$propertyId}/";

        // Criar diretório se não existir
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Deletar imagens antigas
        if (!empty($imagensAntigas)) {
            $stmtImgs = $this->pdo->prepare(
                "SELECT image_url FROM property_images WHERE property_id = ?"
            );
            $stmtImgs->execute([$propertyId]);
            $imagensAtuais = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);

            foreach ($imagensAtuais as $img) {
                if (!in_array($img, $imagensAntigas, true)) {
                    $path = $uploadDir . $img;
                    if (file_exists($path)) {
                        @unlink($path);
                    }
                    $this->pdo->prepare(
                        "DELETE FROM property_images WHERE property_id = ? AND image_url = ?"
                    )->execute([$propertyId, $img]);
                }
            }
        }

        error_log("[AsyncJobQueue] Imagens processadas para propriedade {$propertyId}");
    }

    /**
     * Cria registros financeiros (comissões, transações)
     */
    private function createFinancialRecords($data, $jobId)
    {
        $propertyId = $data['property_id'];
        $status = $data['status'];
        $oldStatus = $data['old_status'];
        $price = $data['price'];
        $userId = $data['user_id'];
        $commission = $data['commission_percentage'] ?? 6.00;
        $title = $data['title'] ?? 'Propriedade';

        // Verificar se deve criar venda
        $statusChanged = ($oldStatus !== $status) && ($status === 'vendido' || $status === 'alugado');

        if (!$statusChanged) {
            return;
        }

        try {
            // property_sales
            $propertyService = new \App\Service\PropertyService($this->pdo);
            $tenantId = $propertyService->getTenantIdForProperty((int)$propertyId);

            if ($tenantId === null || $tenantId <= 0) {
                return;
            }

            $stmtCheckTable = $this->pdo->prepare("
                SELECT 1 FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'property_sales'
            ");
            $stmtCheckTable->execute();

            if ($stmtCheckTable->fetch()) {
                $stmtCheckSale = $this->pdo->prepare("
                    SELECT id FROM property_sales 
                    WHERE property_id = ? AND tenant_id = ? AND deleted_at IS NULL
                ");
                $stmtCheckSale->execute([$propertyId, $tenantId]);

                if (!$stmtCheckSale->fetch()) {
                    $commissionValue = ($price * $commission) / 100;

                    $userRepository = new \App\Infrastructure\User\UserRepository($this->pdo);
                    $userRow = $userRepository->findById((int)$userId, $tenantId);
                    $username = $userRow['username'] ?? '';

                    $stmtSale = $this->pdo->prepare("
                        INSERT INTO property_sales 
                        (tenant_id, property_id, user_id, username, property_title, property_price, commission_6percent, status, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW(), NOW())
                    ");

                    $stmtSale->execute([
                        $tenantId,
                        $propertyId,
                        $userId,
                        $username,
                        $title,
                        $price,
                        $commissionValue
                    ]);
                }
            }
        } catch (Exception $e) {
            error_log("[AsyncJobQueue] Erro ao criar registros financeiros: " . $e->getMessage());
        }
    }

    /**
     * Envia notificacoes
     */
    private function sendNotifications($data, $jobId)
    {
        require_once __DIR__ . '/NotificationManager.php';
        require_once __DIR__ . '/EmailNotificationService.php';

        $propertyId = $data['property_id'];
        $title = $data['title'];
        $status = $data['status'];
        $price = $data['price'];

        try {
            // Obter tenant_id usando PropertyService para evitar SQL direto neste helper
            $service = new \App\Service\PropertyService($this->pdo);
            $tenantId = $service->getTenantIdForProperty((int)$propertyId);

            if ($tenantId === null || $tenantId <= 0) {
                return;
            }

            // Notificacao no painel COM ISOLAMENTO DE TENANT
            $notificationManager = new NotificationManager($this->pdo, $tenantId);
            $notificationManager->notifyPropertyStatus($propertyId, $title, $status);

            // Email
            $emailService = new EmailNotificationService($this->pdo, $tenantId);
            $emailService->notifyPropertyStatus($title, $status, $price);

            // Push notifications removidas do sistema em 2026-02-10

        } catch (Exception $e) {
            error_log("[AsyncJobQueue] Erro ao enviar notificacoes: " . $e->getMessage());
        }
    }

    /**
     * Marca job como falhado
     */
    private function markJobFailed($jobId, $error)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE async_jobs 
                SET status = 'failed', error_message = ?, completed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$error, $jobId]);
        } catch (Exception $e) {
            error_log("[AsyncJobQueue] Erro ao marcar job como falhado: " . $e->getMessage());
        }
    }
}
