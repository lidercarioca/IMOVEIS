<?php
namespace App\Monitoring;

use App\Logging\LoggerFactory;
use App\Logging\LoggerInterface;
use PDO;
use Exception;

/**
 * AlertManager - Sistema de alertas para monitoramento
 *
 * Responsável por:
 * - Detectar condições anômalas no sistema
 * - Enviar notificações para administradores
 * - Registrar alertas no banco de dados
 * - Escalonamento de alertas críticos
 */
class AlertManager
{
    private PDO $pdo;
    private ?LoggerInterface $logger;
    private array $config;

    // Níveis de severidade
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
        // Initialize logger later to avoid instantiation issues
        $this->logger = null;
    }

    private function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = LoggerFactory::make();
        }
        return $this->logger;
    }

    /**
     * Verificar condições e disparar alertas
     */
    public function checkAndAlert(): array
    {
        $alertsTriggered = [];

        try {
            // Verificar espaço em disco
            $diskAlerts = $this->checkDiskSpace();
            $alertsTriggered = array_merge($alertsTriggered, $diskAlerts);

            // Verificar jobs falhando
            $jobAlerts = $this->checkFailedJobs();
            $alertsTriggered = array_merge($alertsTriggered, $jobAlerts);

            // Verificar conexões de banco
            $dbAlerts = $this->checkDatabaseConnections();
            $alertsTriggered = array_merge($alertsTriggered, $dbAlerts);

            // Verificar erros de aplicação
            $appAlerts = $this->checkApplicationErrors();
            $alertsTriggered = array_merge($alertsTriggered, $appAlerts);

        } catch (Exception $e) {
            // $this->getLogger()->error('Alert checking failed', ['error' => $e->getMessage()]);
        }

        return $alertsTriggered;
    }

    /**
     * Verificar espaço em disco
     */
    private function checkDiskSpace(): array
    {
        $alerts = [];

        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $usagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

        if ($usagePercent > 90) {
            $alerts[] = $this->createAlert(
                'Disk space critically low',
                "Disk usage is {$usagePercent}% - immediate action required",
                self::SEVERITY_CRITICAL,
                ['disk_usage_percent' => $usagePercent, 'free_bytes' => $diskFree]
            );
        } elseif ($usagePercent > 80) {
            $alerts[] = $this->createAlert(
                'Disk space low',
                "Disk usage is {$usagePercent}% - monitor closely",
                self::SEVERITY_HIGH,
                ['disk_usage_percent' => $usagePercent, 'free_bytes' => $diskFree]
            );
        }

        return $alerts;
    }

    /**
     * Verificar jobs falhando
     */
    private function checkFailedJobs(): array
    {
        $alerts = [];

        try {
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as failed_count
                FROM async_jobs
                WHERE status = 'failed'
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $failedCount = (int)$stmt->fetchColumn();

            if ($failedCount > 10) {
                $alerts[] = $this->createAlert(
                    'High job failure rate',
                    "{$failedCount} jobs failed in the last hour",
                    self::SEVERITY_HIGH,
                    ['failed_jobs_count' => $failedCount]
                );
            }
        } catch (Exception $e) {
            // Ignorar erros de query
        }

        return $alerts;
    }

    /**
     * Verificar conexões de banco de dados
     */
    private function checkDatabaseConnections(): array
    {
        $alerts = [];

        try {
            $stmt = $this->pdo->query("SHOW PROCESSLIST");
            $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $activeConnections = count($connections);

            // Alerta se muitas conexões ativas (mais de 50)
            if ($activeConnections > 50) {
                $alerts[] = $this->createAlert(
                    'High database connections',
                    "{$activeConnections} active database connections",
                    self::SEVERITY_MEDIUM,
                    ['active_connections' => $activeConnections]
                );
            }

            // Verificar conexões idle há muito tempo
            $longRunning = 0;
            foreach ($connections as $conn) {
                $time = isset($conn['Time']) ? (int)$conn['Time'] : 0;
                if ($time > 300) { // 5 minutos
                    $longRunning++;
                }
            }

            if ($longRunning > 5) {
                $alerts[] = $this->createAlert(
                    'Long running database connections',
                    "{$longRunning} connections running for more than 5 minutes",
                    self::SEVERITY_MEDIUM,
                    ['long_running_connections' => $longRunning]
                );
            }

        } catch (Exception $e) {
            $alerts[] = $this->createAlert(
                'Database connection check failed',
                'Unable to check database connections: ' . $e->getMessage(),
                self::SEVERITY_HIGH,
                ['error' => $e->getMessage()]
            );
        }

        return $alerts;
    }

    /**
     * Verificar erros de aplicação
     */
    private function checkApplicationErrors(): array
    {
        $alerts = [];

        $errorLogPath = __DIR__ . '/../../../logs/error.log';
        if (file_exists($errorLogPath)) {
            $recentErrors = $this->countRecentErrors($errorLogPath);

            if ($recentErrors > 50) {
                $alerts[] = $this->createAlert(
                    'High error rate',
                    "{$recentErrors} errors logged in the last hour",
                    self::SEVERITY_HIGH,
                    ['error_count' => $recentErrors]
                );
            }
        }

        return $alerts;
    }

    /**
     * Contar erros recentes no log
     */
    private function countRecentErrors(string $logPath): int
    {
        if (!file_exists($logPath)) {
            return 0;
        }

        $count = 0;
        $cutoffTime = time() - 3600; // Última hora

        $handle = fopen($logPath, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $data = json_decode($line, true);
                if ($data && isset($data['timestamp'])) {
                    $logTime = strtotime($data['timestamp']);
                    if ($logTime >= $cutoffTime) {
                        $count++;
                    }
                }
            }
            fclose($handle);
        }

        return $count;
    }

    /**
     * Criar um alerta
     */
    private function createAlert(
        string $title,
        string $message,
        string $severity,
        array $metadata = []
    ): array {
        try {
            // Verificar se alerta similar já existe recentemente
            if ($this->isDuplicateAlert($title, $severity)) {
                return []; // Não criar duplicata
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO system_alerts
                (title, message, severity, metadata, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $title,
                $message,
                $severity,
                json_encode($metadata, JSON_UNESCAPED_UNICODE)
            ]);

            $alertId = $this->pdo->lastInsertId();

            // Notificar administradores se severidade alta
            if (in_array($severity, [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL])) {
                $this->notifyAdministrators($title, $message, $severity);
            }

            // $this->getLogger()->warning("System alert created: {$title}", [
            //     'alert_id' => $alertId,
            //     'severity' => $severity,
            //     'metadata' => $metadata
            // ]);

            return [
                'id' => $alertId,
                'title' => $title,
                'severity' => $severity,
                'message' => $message,
            ];

        } catch (Exception $e) {
            // $this->getLogger()->error('Failed to create alert', [
            //     'title' => $title,
            //     'error' => $e->getMessage()
            // ]);
            return [];
        }
    }

    /**
     * Verificar se é um alerta duplicado
     */
    private function isDuplicateAlert(string $title, string $severity): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id FROM system_alerts
                WHERE title = ?
                AND severity = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                LIMIT 1
            ");
            $stmt->execute([$title, $severity]);
            return $stmt->fetch() !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Notificar administradores
     */
    private function notifyAdministrators(string $title, string $message, string $severity): void
    {
        try {
            // Buscar emails de administradores
            $stmt = $this->pdo->prepare("
                SELECT email FROM users
                WHERE role IN ('admin', 'superadmin')
                AND active = 1
                AND email IS NOT NULL
            ");
            $stmt->execute();
            $adminEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($adminEmails)) {
                return;
            }

            // Enviar email de alerta
            $subject = "[ALERTA SISTEMA - {$severity}] {$title}";
            $body = "Alerta do sistema:\n\n{$message}\n\nSeveridade: " . strtoupper($severity) . "\nTimestamp: " . date('Y-m-d H:i:s');

            // Usar mailer existente se disponível
            if (file_exists(__DIR__ . '/../../utils/mailer.php')) {
                require_once __DIR__ . '/../../utils/mailer.php';
                if (function_exists('sendAppEmail')) {
                    foreach ($adminEmails as $email) {
                        sendAppEmail($email, $subject, $body);
                    }
                }
            }

        } catch (Exception $e) {
            // $this->getLogger()->error('Failed to notify administrators', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Resolver alerta
     */
    public function resolveAlert(int $alertId, ?int $userId = null): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE system_alerts
                SET resolved_at = NOW(), resolved_by = ?, status = 'resolved'
                WHERE id = ?
            ");
            return $stmt->execute([$userId, $alertId]);
        } catch (Exception $e) {
            // $this->getLogger()->error('Failed to resolve alert', ['alert_id' => $alertId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Obter alertas ativos
     */
    public function getActiveAlerts(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT id, title, message, severity, metadata, created_at
                FROM system_alerts
                WHERE status = 'active'
                ORDER BY
                    CASE severity
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    created_at DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
