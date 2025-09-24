<?php
class SystemBackupHandler {
    private $rootDir;
    private $backupDir;
    private $errorLog;
    private $includePaths;
    private $excludePaths;

    public function __construct($backupDir) {
        $this->rootDir = __DIR__ . '/../../';
        $this->backupDir = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->errorLog = __DIR__ . '/../../backup_error.log';
        
        // Diretórios e arquivos para incluir no backup
        $this->includePaths = [
            'config/',           // Arquivos de configuração
            'views/',            // Templates e views
            'assets/js/',        // Arquivos JavaScript
            'assets/css/',       // Arquivos CSS (se existirem)
            'app/',              // Handlers e classes do sistema
            'api/',              // APIs do sistema
            '*.php',            // Arquivos PHP na raiz
            '*.log',            // Arquivos de log
            '.htaccess',        // Configurações do Apache
            'properties.json'    // Configurações de propriedades
        ];
        
        // Diretórios e arquivos para excluir do backup
        $this->excludePaths = [
            'backups/',         // Pasta de backups
            'node_modules/',    // Módulos npm (se existirem)
            'vendor/',          // Dependências Composer (se existirem)
            '*.git*',           // Arquivos git
            '*.zip',           // Arquivos ZIP
            '*.tmp',           // Arquivos temporários
            '*~'               // Arquivos de backup temporários
        ];
    }

    public function backup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $systemBackupFile = $this->backupDir . 'system_' . $timestamp . '.zip';
            
            $zip = new ZipArchive();
            if ($zip->open($systemBackupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Não foi possível criar o arquivo ZIP do sistema");
            }

            // Adiciona cada pasta/arquivo incluído
            foreach ($this->includePaths as $includePath) {
                $this->addPathToZip($this->rootDir . $includePath, $zip);
            }

            $zip->close();

            if (!file_exists($systemBackupFile) || filesize($systemBackupFile) === 0) {
                throw new Exception("Arquivo de backup do sistema está vazio ou não foi criado");
            }

            return [
                'file' => basename($systemBackupFile),
                'size' => filesize($systemBackupFile)
            ];
        } catch (Exception $e) {
            error_log(date('Y-m-d H:i:s') . ' - Erro no backup do sistema: ' . $e->getMessage() . "\n", 3, $this->errorLog);
            throw $e;
        }
    }

    private function addPathToZip($path, $zip) {
        // Se é um padrão glob
        if (strpos($path, '*') !== false) {
            $files = glob($path);
            foreach ($files as $file) {
                if ($this->shouldExclude($file)) continue;
                
                $localPath = str_replace($this->rootDir, '', $file);
                if (is_file($file)) {
                    $zip->addFile($file, $localPath);
                }
            }
            return;
        }

        // Se é um diretório
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($this->shouldExclude($file)) continue;

                $localPath = str_replace($this->rootDir, '', $file->getRealPath());
                if ($file->isDir()) {
                    $zip->addEmptyDir($localPath);
                } else {
                    $zip->addFile($file->getRealPath(), $localPath);
                }
            }
        } 
        // Se é um arquivo
        elseif (is_file($path)) {
            if (!$this->shouldExclude($path)) {
                $localPath = str_replace($this->rootDir, '', $path);
                $zip->addFile($path, $localPath);
            }
        }
    }

    private function shouldExclude($path) {
        $relativePath = str_replace($this->rootDir, '', $path);
        foreach ($this->excludePaths as $excludePath) {
            if (fnmatch($excludePath, $relativePath)) {
                return true;
            }
        }
        return false;
    }
}
