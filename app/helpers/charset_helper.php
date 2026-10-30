<?php
/**
 * Script de Inicialização de Charset Global
 * Garante que TODO o MySQL está em UTF-8MB4
 * 
 * Deve ser incluído no topo de TODOS os arquivos que usam $pdo
 */

function ensureUTF8Charset($pdo) {
    try {
        // Comando crucial que garante UTF-8
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        $pdo->exec("SET SESSION collation_connection = 'utf8mb4_general_ci'");
        
        // Verificar se funcionou
        $stmt = $pdo->query("SELECT @@character_set_connection");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['@@character_set_connection'] !== 'utf8mb4') {
            error_log("AVISO: Character set não foi definido corretamente!");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("ERRO ao definir UTF-8: " . $e->getMessage());
        return false;
    }
}

// Função para forçar UTF-8 em dados antes de enviar
function ensureUTF8Data($data) {
    if (is_string($data)) {
        // Se não estiver em UTF-8, tenta converter
        if (!mb_check_encoding($data, 'UTF-8')) {
            $data = mb_convert_encoding($data, 'UTF-8');
        }
        return $data;
    } elseif (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = ensureUTF8Data($value);
        }
        return $data;
    }
    return $data;
}

?>
