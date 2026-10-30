<?php

namespace App\Jobs;

use PDO;
use Exception;

/**
 * JobScheduler - Agendador de jobs recorrentes
 *
 * Gerencia jobs que precisam ser executados periodicamente:
 * - Limpeza de dados antigos
 * - Processamento de filas
 * - Envio de relatórios
 * - Manutenção do sistema
 */
class JobScheduler
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Registrar um job recorrente
     */
    public function schedule(
        string $jobType,
        string $cronExpression,
        array $data = [],
        ?int $userId = null
    ): int {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO scheduled_jobs
                (job_type, cron_expression, job_data, user_id, next_run, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $nextRun = $this->calculateNextRun($cronExpression);

            $stmt->execute([
                $jobType,
                $cronExpression,
                json_encode($data, JSON_UNESCAPED_UNICODE),
                $userId,
                $nextRun
            ]);

            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("[JobScheduler] Erro ao agendar job: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Processar jobs agendados que estão prontos para execução
     */
    public function processDueJobs(): array
    {
        $results = [
            'processed' => 0,
            'executed' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        try {
            $now = date('Y-m-d H:i:s');

            // Buscar jobs que precisam ser executados
            $stmt = $this->pdo->prepare("
                SELECT id, job_type, cron_expression, job_data, user_id
                FROM scheduled_jobs
                WHERE next_run <= ? AND active = 1
                ORDER BY next_run ASC
                LIMIT ?
            ");

            $batchSize = $this->config['process_batch_size'] ?? 10;
            $stmt->execute([$now, $batchSize]);
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($jobs as $job) {
                $results['processed']++;

                try {
                    $this->executeScheduledJob($job);
                    $results['executed']++;

                    // Atualizar próximo horário de execução
                    $this->updateNextRun($job['id'], $job['cron_expression']);
                } catch (Exception $e) {
                    $results['failed']++;
                    error_log("[JobScheduler] Erro ao executar job {$job['id']}: " . $e->getMessage());

                    // Marcar como falhado se falhar muitas vezes
                    $this->incrementFailureCount($job['id']);
                }
            }
        } catch (Exception $e) {
            error_log("[JobScheduler] Erro ao processar jobs agendados: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Executar um job agendado específico
     */
    private function executeScheduledJob(array $job): void
    {
        $jobType = $job['job_type'];
        $data = json_decode($job['job_data'], true);

        switch ($jobType) {
            case 'cleanup_old_logs':
                $this->cleanupOldLogs($data);
                break;

            case 'process_async_jobs':
                $this->processAsyncJobs($data);
                break;

            case 'send_daily_reports':
                $this->sendDailyReports($data, $job['user_id']);
                break;

            case 'maintenance_check':
                $this->runMaintenanceCheck($data);
                break;

            default:
                throw new Exception("Tipo de job desconhecido: {$jobType}");
        }
    }

    /**
     * Limpar logs antigos
     */
    private function cleanupOldLogs(array $data): void
    {
        $daysOld = $data['days_old'] ?? 30;
        $logDir = $data['log_dir'] ?? (__DIR__ . '/../../logs');

        if (!is_dir($logDir)) {
            return;
        }

        $cutoffTime = time() - ($daysOld * 86400);
        $files = scandir($logDir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $logDir . DIRECTORY_SEPARATOR . $file;
            if (is_file($filePath) && filemtime($filePath) < $cutoffTime) {
                @unlink($filePath);
            }
        }

        error_log("[JobScheduler] Limpeza de logs antigos executada: {$daysOld} dias");
    }

    /**
     * Processar fila de jobs assíncronos
     */
    private function processAsyncJobs(array $data): void
    {
        require_once __DIR__ . '/../../app/utils/AsyncJobQueue.php';

        $queue = new \AsyncJobQueue($this->pdo);
        $limit = $data['limit'] ?? 20;
        $result = $queue->processPending($limit);

        error_log("[JobScheduler] Processamento de jobs assíncronos: " . json_encode($result));
    }

    /**
     * Enviar relatórios diários
     */
    private function sendDailyReports(array $data, ?int $userId): void
    {
        // Implementação básica - pode ser expandida
        error_log("[JobScheduler] Envio de relatórios diários para usuário {$userId}");
    }

    /**
     * Executar verificações de manutenção
     */
    private function runMaintenanceCheck(array $data): void
    {
        // Verificar espaço em disco, conexões ativas, etc.
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsagePercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 2);

        error_log("[JobScheduler] Verificação de manutenção: Disco usado {$diskUsagePercent}%");
    }

    /**
     * Calcular próximo horário de execução baseado em expressão cron
     */
    private function calculateNextRun(string $cronExpression): string
    {
        // Implementação simplificada - apenas suporta "* * * * *" (a cada minuto)
        // Para produção, usar uma biblioteca como mtdowling/cron-expression
        if ($cronExpression === '* * * * *') {
            return date('Y-m-d H:i:s', strtotime('+1 minute'));
        }

        // Padrão: executar em 1 hora
        return date('Y-m-d H:i:s', strtotime('+1 hour'));
    }

    /**
     * Atualizar próximo horário de execução
     */
    private function updateNextRun(int $jobId, string $cronExpression): void
    {
        $nextRun = $this->calculateNextRun($cronExpression);

        $stmt = $this->pdo->prepare("
            UPDATE scheduled_jobs
            SET next_run = ?, last_run = NOW(), run_count = run_count + 1
            WHERE id = ?
        ");
        $stmt->execute([$nextRun, $jobId]);
    }

    /**
     * Incrementar contador de falhas
     */
    private function incrementFailureCount(int $jobId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE scheduled_jobs
            SET failure_count = failure_count + 1,
                last_failure = NOW(),
                active = CASE WHEN failure_count >= 5 THEN 0 ELSE 1 END
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);
    }
}
