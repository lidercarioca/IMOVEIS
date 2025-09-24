<?php
class MediaBackupHandler {
    private $mediaDir;
    private $backupDir;
    private $errorLog;

    public function __construct($backupDir) {
        $this->mediaDir = __DIR__ . '/../../assets/imagens/';
        $this->backupDir = rtrim($backupDir, '/\\') . DIRECTORY_SEPARATOR;
        $this->errorLog = __DIR__ . '/../../backup_error.log';
    }

    public function backup() {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $mediaBackupDir = $this->backupDir . 'media_backup_' . $timestamp;
            
            if (!file_exists($mediaBackupDir)) {
                mkdir($mediaBackupDir, 0777, true);
            }

            $zip = new ZipArchive();
            $zipFile = $mediaBackupDir . '.zip';

            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Não foi possível criar o arquivo ZIP");
            }

            $this->addFolderToZip($this->mediaDir, $zip);
            $zip->close();

            // Remover diretório temporário se existir
            if (file_exists($mediaBackupDir)) {
                $this->removeDirectory($mediaBackupDir);
            }

            return basename($zipFile);
        } catch (Exception $e) {
            error_log(date('Y-m-d H:i:s') . ' - Erro no backup de mídia: ' . $e->getMessage() . "\n", 3, $this->errorLog);
            throw $e;
        }
    }

    private function addFolderToZip($folder, $zip, $subfolder = '') {
        $handle = opendir($folder);
        while (false !== ($entry = readdir($handle))) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }

            $filePath = $folder . $entry;
            $zipPath = $subfolder . $entry;

            if (is_dir($filePath)) {
                $zip->addEmptyDir($zipPath);
                $this->addFolderToZip($filePath . DIRECTORY_SEPARATOR, $zip, $zipPath . '/');
            } else {
                $zip->addFile($filePath, $zipPath);
            }
        }
        closedir($handle);
    }

    private function removeDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->removeDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }
}
