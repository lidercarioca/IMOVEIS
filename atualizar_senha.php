<?php

require_once 'config/database.php';

try {
    // Usando bcrypt para hash da senha (compatível com o padrão da tabela)
    $novaSenha = password_hash('admin123', PASSWORD_BCRYPT);
    
    $sql = "UPDATE users SET 
            password = :senha,
            last_login = CURRENT_TIMESTAMP
            WHERE username = 'admin'";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':senha', $novaSenha);
    
    if ($stmt->execute()) {
        echo "Senha do administrador atualizada com sucesso!";
    } else {
        echo "Erro ao atualizar a senha.";
    }

} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage();
}
?>