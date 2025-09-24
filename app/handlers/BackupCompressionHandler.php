<?php
class BackupCompressionHandler {
    private $errorLog;
    private $compressionLevel;

    public function __construct($compressionLevel = 6) {
        $this->errorLog = __DIR__ . '/../../backup_error.log';
        // Nível de compressão entre 1 (mais rápido) e 9 (melhor compressão)
        $this->compressionLevel = max(1, min(9, (int)$compressionLevel));
    }

    /**
     * Comprime um arquivo SQL
     */
    public function compressSqlBackup($sqlFile) {
        try {
            if (!file_exists($sqlFile)) {
                throw new Exception("Arquivo SQL não encontrado: " . $sqlFile);
            }

            $zipFile = $sqlFile . '.zip';
            $zip = new ZipArchive();

            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Não foi possível criar o arquivo ZIP");
            }

            // Configura o nível de compressão
            $zip->setCompressionIndex(0, ZipArchive::CM_DEFLATE, $this->compressionLevel);

            // Adiciona o arquivo SQL ao ZIP com o mesmo nome base
            $zip->addFile($sqlFile, basename($sqlFile));
            $result = $zip->close();

            if ($result) {
                // Verifica se o arquivo ZIP foi criado e é válido
                if (!file_exists($zipFile) || filesize($zipFile) === 0) {
                    throw new Exception("Arquivo ZIP criado está vazio ou inválido");
                }

                // Compara tamanhos para garantir que a compressão foi útil
                $originalSize = filesize($sqlFile);
                $compressedSize = filesize($zipFile);

                error_log(date('Y-m-d H:i:s') . " - Compressão SQL - Original: " . $this->formatBytes($originalSize) . 
                         ", Comprimido: " . $this->formatBytes($compressedSize) . "\n", 3, $this->errorLog);

                // Remove o arquivo SQL original somente se a compressão foi bem sucedida
                if (unlink($sqlFile)) {
                    return [
                        'path' => $zipFile,
                        'original_size' => $originalSize,
                        'compressed_size' => $compressedSize,
                        'ratio' => round(($compressedSize / $originalSize) * 100, 2)
                    ];
                } else {
                    throw new Exception("Não foi possível remover o arquivo SQL original");
                }
            } else {
                throw new Exception("Falha ao fechar o arquivo ZIP");
            }
        } catch (Exception $e) {
            error_log(date('Y-m-d H:i:s') . " - Erro na compressão SQL: " . $e->getMessage() . "\n", 3, $this->errorLog);
            // Se algo der errado, tenta limpar o arquivo ZIP se ele existir
            if (isset($zipFile) && file_exists($zipFile)) {
                unlink($zipFile);
            }
            throw $e;
        }
    }

    /**
     * Descomprime um arquivo SQL
     */
    public function decompressSqlBackup($zipFile, $outputDir) {
        try {
            if (!file_exists($zipFile)) {
                throw new Exception("Arquivo ZIP não encontrado: " . $zipFile);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== TRUE) {
                throw new Exception("Não foi possível abrir o arquivo ZIP");
            }

            // Garante que o diretório de saída existe
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0777, true);
            }

            // Lista os arquivos no ZIP
            $sqlFiles = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
                    $sqlFiles[] = $filename;
                }
            }

            if (empty($sqlFiles)) {
                $zip->close();
                throw new Exception("Nenhum arquivo SQL encontrado no arquivo ZIP");
            }

            // Extrai apenas o primeiro arquivo SQL encontrado
            $sqlFile = $sqlFiles[0];
            $extractedFile = $outputDir . '/' . basename($sqlFile);
            
            if ($zip->extractTo($outputDir, $sqlFile)) {
                $zip->close();
                return $extractedFile;
            } else {
                $zip->close();
                throw new Exception("Falha ao extrair arquivo SQL");
            }
        } catch (Exception $e) {
            error_log(date('Y-m-d H:i:s') . " - Erro na descompressão SQL: " . $e->getMessage() . "\n", 3, $this->errorLog);
            throw $e;
        }
    }

    /**
     * Formata o tamanho em bytes para uma string legível
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
