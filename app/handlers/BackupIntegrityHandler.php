<?php
class BackupIntegrityHandler {
    private $errorLog;

    public function __construct() {
        $this->errorLog = __DIR__ . '/../../backup_error.log';
    }

    /**
     * Valida um arquivo de backup SQL
     */
    public function validateSqlBackup($filePath) {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("Arquivo de backup não encontrado: " . $filePath);
            }

            if (!is_readable($filePath)) {
                throw new Exception("Arquivo de backup não pode ser lido: " . $filePath);
            }

            $fileSize = filesize($filePath);
            if ($fileSize === 0) {
                throw new Exception("Arquivo de backup está vazio");
            }

            // Lê o início do arquivo para verificar a estrutura básica
            $handle = fopen($filePath, 'r');
            $header = fread($handle, 1024); // Lê os primeiros 1024 bytes
            fclose($handle);

            // Verifica elementos essenciais no cabeçalho
            $requiredElements = [
                'SET FOREIGN_KEY_CHECKS=0',
                'DROP TABLE IF EXISTS',
                'CREATE TABLE'
            ];

            foreach ($requiredElements as $element) {
                if (stripos($header, $element) === false) {
                    throw new Exception("Estrutura do arquivo SQL inválida: falta '{$element}'");
                }
            }

            // Verifica a integridade do arquivo SQL completo
            $content = file_get_contents($filePath);
            
            // Verifica se há comandos SQL básicos
            if (!preg_match('/CREATE TABLE/i', $content)) {
                throw new Exception("Arquivo não contém comandos CREATE TABLE");
            }
            
            if (!preg_match('/INSERT INTO/i', $content)) {
                error_log(date('Y-m-d H:i:s') . " - Aviso: Arquivo não contém comandos INSERT INTO\n", 3, $this->errorLog);
                // Não lança exceção pois podem existir tabelas vazias
            }

            // Verifica se o arquivo termina corretamente
            if (!preg_match('/SET FOREIGN_KEY_CHECKS=1;[\s]*$/i', $content)) {
                throw new Exception("Arquivo SQL não termina corretamente");
            }

            return true;
        } catch (Exception $e) {
            error_log(date('Y-m-d H:i:s') . " - Erro na validação do backup SQL: " . $e->getMessage() . "\n", 3, $this->errorLog);
            throw $e;
        }
    }

    /**
     * Valida um arquivo de backup de mídia (ZIP)
     */
    public function validateMediaBackup($filePath) {
        try {
            if (!file_exists($filePath)) {
                throw new Exception("Arquivo de backup de mídia não encontrado: " . $filePath);
            }

            if (!is_readable($filePath)) {
                throw new Exception("Arquivo de backup de mídia não pode ser lido: " . $filePath);
            }

            $zip = new ZipArchive();
            $result = $zip->open($filePath);
            
            if ($result !== TRUE) {
                throw new Exception("Arquivo ZIP inválido ou corrompido");
            }

            // Verifica se o ZIP não está vazio
            if ($zip->numFiles < 1) {
                $zip->close();
                throw new Exception("Arquivo ZIP está vazio");
            }

            // Testa a integridade de cada arquivo no ZIP
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) {
                    $zip->close();
                    throw new Exception("Erro ao ler arquivo dentro do ZIP: índice " . $i);
                }
            }

            $zip->close();
            return true;
        } catch (Exception $e) {
            error_log(date('Y-m-d H:i:s') . " - Erro na validação do backup de mídia: " . $e->getMessage() . "\n", 3, $this->errorLog);
            throw $e;
        }
    }

    /**
     * Gera um checksum do arquivo para verificação futura
     */
    public function generateChecksum($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("Arquivo não encontrado: " . $filePath);
        }
        return hash_file('sha256', $filePath);
    }

    /**
     * Verifica se o checksum do arquivo corresponde ao esperado
     */
    public function verifyChecksum($filePath, $expectedChecksum) {
        $currentChecksum = $this->generateChecksum($filePath);
        return $currentChecksum === $expectedChecksum;
    }
}
