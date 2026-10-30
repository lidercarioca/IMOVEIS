<?php
class BackupRotationHandler {
    private $backupDir;
    private $maxBackups;
    private $minBackupsToKeep;
    private $errorLog;

    public function __construct($backupDir, $maxBackups = 10, $minBackupsToKeep = 3) {
        $this->backupDir = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->maxBackups = max(1, (int)$maxBackups);
        $this->minBackupsToKeep = min(max(1, (int)$minBackupsToKeep), $this->maxBackups);
        $this->errorLog = __DIR__ . '/../../backup_error.log';
    }

    /**
     * Executa a rotação dos backups
     */
    public function rotate() {
        try {
            $this->rotateSqlBackups();
            $this->rotateMediaBackups();
            return true;
        } catch (Exception $e) {
            error_log(date('Y-m-d H:i:s') . " - Erro na rotação de backups: " . $e->getMessage() . "\n", 3, $this->errorLog);
            throw $e;
        }
    }

    /**
     * Rotaciona os backups SQL
     */
    private function rotateSqlBackups() {
        $pattern = $this->backupDir . "db_backup_*.sql";
        $backups = glob($pattern);
        
        if (empty($backups)) {
            error_log(date('Y-m-d H:i:s') . " - Nenhum backup SQL encontrado para rotação\n", 3, $this->errorLog);
            return;
        }

        // Ordena por data de modificação, mais recente primeiro
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Mantém apenas o número máximo de backups configurado
        if (count($backups) > $this->maxBackups) {
            $toDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($toDelete as $backup) {
                if (count($backups) <= $this->minBackupsToKeep) {
                    break; // Garante que pelo menos minBackupsToKeep são mantidos
                }
                
                try {
                    if (unlink($backup)) {
                        error_log(date('Y-m-d H:i:s') . " - Backup SQL removido: " . basename($backup) . "\n", 3, $this->errorLog);
                        array_pop($backups);
                    } else {
                        error_log(date('Y-m-d H:i:s') . " - Falha ao remover backup SQL: " . basename($backup) . "\n", 3, $this->errorLog);
                    }
                } catch (Exception $e) {
                    error_log(date('Y-m-d H:i:s') . " - Erro ao remover backup SQL: " . $e->getMessage() . "\n", 3, $this->errorLog);
                }
            }
        }
    }

    /**
     * Rotaciona os backups de mídia
     */
    private function rotateMediaBackups() {
        $pattern = $this->backupDir . "media_*.zip";
        $backups = glob($pattern);
        
        if (empty($backups)) {
            error_log(date('Y-m-d H:i:s') . " - Nenhum backup de mídia encontrado para rotação\n", 3, $this->errorLog);
            return;
        }

        // Ordena por data de modificação, mais recente primeiro
        usort($backups, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        // Mantém apenas o número máximo de backups configurado
        if (count($backups) > $this->maxBackups) {
            $toDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($toDelete as $backup) {
                if (count($backups) <= $this->minBackupsToKeep) {
                    break; // Garante que pelo menos minBackupsToKeep são mantidos
                }
                
                try {
                    if (unlink($backup)) {
                        error_log(date('Y-m-d H:i:s') . " - Backup de mídia removido: " . basename($backup) . "\n", 3, $this->errorLog);
                        array_pop($backups);
                    } else {
                        error_log(date('Y-m-d H:i:s') . " - Falha ao remover backup de mídia: " . basename($backup) . "\n", 3, $this->errorLog);
                    }
                } catch (Exception $e) {
                    error_log(date('Y-m-d H:i:s') . " - Erro ao remover backup de mídia: " . $e->getMessage() . "\n", 3, $this->errorLog);
                }
            }
        }
    }

    /**
     * Retorna estatísticas dos backups
     */
    public function getStats() {
        $stats = [
            'sql' => [
                'count' => 0,
                'total_size' => 0,
                'oldest' => null,
                'newest' => null
            ],
            'media' => [
                'count' => 0,
                'total_size' => 0,
                'oldest' => null,
                'newest' => null
            ]
        ];

        // Estatísticas dos backups SQL
        $sqlFiles = glob($this->backupDir . "db_backup_*.sql");
        if (!empty($sqlFiles)) {
            $stats['sql']['count'] = count($sqlFiles);
            foreach ($sqlFiles as $file) {
                $size = filesize($file);
                $time = filemtime($file);
                $stats['sql']['total_size'] += $size;
                if (!$stats['sql']['oldest'] || $time < $stats['sql']['oldest']) {
                    $stats['sql']['oldest'] = $time;
                }
                if (!$stats['sql']['newest'] || $time > $stats['sql']['newest']) {
                    $stats['sql']['newest'] = $time;
                }
            }
        }

        // Estatísticas dos backups de mídia
        $mediaFiles = glob($this->backupDir . "media_*.zip");
        if (!empty($mediaFiles)) {
            $stats['media']['count'] = count($mediaFiles);
            foreach ($mediaFiles as $file) {
                $size = filesize($file);
                $time = filemtime($file);
                $stats['media']['total_size'] += $size;
                if (!$stats['media']['oldest'] || $time < $stats['media']['oldest']) {
                    $stats['media']['oldest'] = $time;
                }
                if (!$stats['media']['newest'] || $time > $stats['media']['newest']) {
                    $stats['media']['newest'] = $time;
                }
            }
        }

        return $stats;
    }
}
