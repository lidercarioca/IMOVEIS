<?php
namespace App\Monitoring;

use App\Cache\CacheFactory;
use App\Logging\LoggerFactory;
use App\Logging\LoggerInterface;
use PDO;
use Exception;

/**
 * MetricsCollector - Coleta métricas do sistema para monitoramento
 *
 * Responsável por:
 * - Coletar métricas de performance (CPU, memória, disco)
 * - Métricas de negócio (usuários ativos, jobs processados)
 * - Armazenar métricas em cache/arquivo para análise
 * - Fornecer dados para dashboards de monitoramento
 */
class MetricsCollector
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private $cache;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->logger = LoggerFactory::make();
        $this->cache = CacheFactory::make();
    }

    /**
     * Coletar todas as métricas do sistema
     */
    public function collectAllMetrics(): array
    {
        $metrics = [
            'timestamp' => time(),
            'system' => $this->collectSystemMetrics(),
            'database' => $this->collectDatabaseMetrics(),
            'application' => $this->collectApplicationMetrics(),
            'business' => $this->collectBusinessMetrics(),
        ];

        // Armazenar métricas em cache para histórico
        $this->storeMetrics($metrics);

        return $metrics;
    }

    /**
     * Coletar métricas do sistema operacional
     */
    private function collectSystemMetrics(): array
    {
        $metrics = [];

        try {
            // CPU e memória (Windows)
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $metrics = $this->collectWindowsMetrics();
            } else {
                // Linux/Unix
                $metrics = $this->collectUnixMetrics();
            }
        } catch (Exception $e) {
            $this->logger->warning('Failed to collect system metrics', ['error' => $e->getMessage()]);
            $metrics = ['error' => $e->getMessage()];
        }

        return $metrics;
    }

    /**
     * Métricas do Windows
     */
    private function collectWindowsMetrics(): array
    {
        $metrics = [];

        // Espaço em disco
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $metrics['disk'] = [
            'free_bytes' => $diskFree,
            'total_bytes' => $diskTotal,
            'usage_percent' => round((($diskTotal - $diskFree) / $diskTotal) * 100, 2),
        ];

        // Memória (aproximada via PHP)
        $memoryUsage = memory_get_peak_usage(true);
        $metrics['memory'] = [
            'php_peak_bytes' => $memoryUsage,
            'php_peak_mb' => round($memoryUsage / 1024 / 1024, 2),
        ];

        return $metrics;
    }

    /**
     * Métricas do Unix/Linux
     */
    private function collectUnixMetrics(): array
    {
        $metrics = [];

        // Espaço em disco
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $metrics['disk'] = [
            'free_bytes' => $diskFree,
            'total_bytes' => $diskTotal,
            'usage_percent' => round((($diskTotal - $diskFree) / $diskTotal) * 100, 2),
        ];

        // Memória via /proc/meminfo se disponível
        $memInfo = $this->getLinuxMemoryInfo();
        $metrics['memory'] = $memInfo ?: [
            'php_peak_bytes' => memory_get_peak_usage(true),
            'php_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        return $metrics;
    }

    /**
     * Obter informações de memória do Linux
     */
    private function getLinuxMemoryInfo(): ?array
    {
        if (!file_exists('/proc/meminfo')) {
            return null;
        }

        $meminfo = file_get_contents('/proc/meminfo');
        if (!$meminfo) {
            return null;
        }

        $matches = [];
        preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches);
        $total = isset($matches[1]) ? (int)$matches[1] * 1024 : 0;

        preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches);
        $available = isset($matches[1]) ? (int)$matches[1] * 1024 : 0;

        if ($total > 0) {
            return [
                'total_bytes' => $total,
                'available_bytes' => $available,
                'used_bytes' => $total - $available,
                'usage_percent' => round((($total - $available) / $total) * 100, 2),
            ];
        }

        return null;
    }

    /**
     * Coletar métricas do banco de dados
     */
    private function collectDatabaseMetrics(): array
    {
        $metrics = [];

        try {
            // Conexões ativas
            $stmt = $this->pdo->query("SHOW PROCESSLIST");
            $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $metrics['active_connections'] = count($connections);

            // Tamanho das tabelas principais
            $tableSizes = $this->getTableSizes();
            $metrics['table_sizes'] = $tableSizes;

            // Queries lentas (se houver tabela de monitoramento)
            $slowQueries = $this->getSlowQueries();
            $metrics['slow_queries'] = $slowQueries;

        } catch (Exception $e) {
            $this->logger->warning('Failed to collect database metrics', ['error' => $e->getMessage()]);
            $metrics = ['error' => $e->getMessage()];
        }

        return $metrics;
    }

    /**
     * Obter tamanhos das tabelas
     */
    private function getTableSizes(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT
                    table_name,
                    ROUND(data_length / 1024 / 1024, 2) as data_mb,
                    ROUND(index_length / 1024 / 1024, 2) as index_mb,
                    ROUND((data_length + index_length) / 1024 / 1024, 2) as total_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND (data_length + index_length) > 0
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obter queries lentas (placeholder)
     */
    private function getSlowQueries(): array
    {
        // Implementação futura - requer monitoramento de queries
        return [];
    }

    /**
     * Coletar métricas da aplicação
     */
    private function collectApplicationMetrics(): array
    {
        $metrics = [];

        try {
            // Jobs assíncronos
            $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM async_jobs GROUP BY status");
            $jobCounts = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $jobCounts[$row['status']] = (int)$row['count'];
            }
            $metrics['async_jobs'] = $jobCounts;

            // Usuários ativos (última hora)
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT user_id) as active_users
                FROM user_sessions
                WHERE last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND active = 1
            ");
            $stmt->execute();
            $metrics['active_users_last_hour'] = (int)$stmt->fetchColumn();

            // Logs de erro (última hora)
            $errorLogPath = __DIR__ . '/../../logs/error.log';
            if (file_exists($errorLogPath)) {
                $recentErrors = $this->countRecentLogEntries($errorLogPath, 3600);
                $metrics['error_logs_last_hour'] = $recentErrors;
            }

        } catch (Exception $e) {
            $this->logger->warning('Failed to collect application metrics', ['error' => $e->getMessage()]);
            $metrics = ['error' => $e->getMessage()];
        }

        return $metrics;
    }

    /**
     * Coletar métricas de negócio
     */
    private function collectBusinessMetrics(): array
    {
        $metrics = [];

        try {
            // Propriedades ativas
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL");
            $metrics['active_properties'] = (int)$stmt->fetchColumn();

            // Leads do mês atual
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM leads
                WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $stmt->execute();
            $metrics['leads_this_month'] = (int)$stmt->fetchColumn();

            // Vendas do mês atual
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM property_sales
                WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
            ");
            $stmt->execute();
            $metrics['sales_this_month'] = (int)$stmt->fetchColumn();

        } catch (Exception $e) {
            $this->logger->warning('Failed to collect business metrics', ['error' => $e->getMessage()]);
            $metrics = ['error' => $e->getMessage()];
        }

        return $metrics;
    }

    /**
     * Contar entradas recentes no log
     */
    private function countRecentLogEntries(string $logPath, int $secondsAgo): int
    {
        if (!file_exists($logPath)) {
            return 0;
        }

        $cutoffTime = time() - $secondsAgo;
        $count = 0;

        $handle = fopen($logPath, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                // Assumindo formato JSON com timestamp
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
     * Armazenar métricas em cache para histórico
     */
    private function storeMetrics(array $metrics): void
    {
        try {
            $key = 'metrics_history_' . date('Y-m-d-H-i');
            $this->cache->set($key, $metrics, 86400); // 24 horas
        } catch (Exception $e) {
            // Não falhar se não conseguir armazenar
        }
    }

    /**
     * Obter histórico de métricas
     */
    public function getMetricsHistory(int $hours = 24): array
    {
        $history = [];
        $now = time();

        for ($i = 0; $i < $hours; $i++) {
            $timestamp = $now - ($i * 3600);
            $key = 'metrics_history_' . date('Y-m-d-H-i', $timestamp);
            $metrics = $this->cache->get($key);
            if ($metrics) {
                $history[] = $metrics;
            }
        }

        return array_reverse($history);
    }
}
