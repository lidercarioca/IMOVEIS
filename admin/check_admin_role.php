<?php

/**
 * Verifica o role do usuário admin no banco
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Verificando Usuário Admin no Banco ===\n\n";

try {
    $stmt = $pdo->prepare("SELECT id, username, role, is_superadmin, active FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "✓ Usuário 'admin' encontrado:\n";
        echo "  ID: " . $user['id'] . "\n";
        echo "  Username: " . $user['username'] . "\n";
        echo "  Role: '" . $user['role'] . "'\n";
        echo "  Is Superadmin: " . ($user['is_superadmin'] ? 'SIM' : 'NÃO') . "\n";
        echo "  Ativo: " . ($user['active'] ? 'SIM' : 'NÃO') . "\n";

        // Verifica se isAdmin() vai funcionar
        $role = strtolower(trim($user['role']));
        $isValidRole = $role === 'admin' || $role === 'administrator' || $role === 'superadmin';

        echo "\n  isAdmin() vai retornar: " . ($isValidRole ? 'TRUE ✓' : 'FALSE ✗') . "\n";

        // Verifica se role é válida
        if (!$isValidRole) {
            echo "\n⚠️ PROBLEMA: Role '" . $user['role'] . "' não é reconhecida como admin!\n";
            echo "\nSolução: Corrigir role para 'admin', 'administrator' ou 'superadmin'\n";

            // Oferece a correção usando UserService (centralizado)
            try {
                require_once __DIR__ . '/../src/Infrastructure/User/UserRepository.php';
                require_once __DIR__ . '/../src/Application/User/UserService.php';

                $userRepository = new \App\Infrastructure\User\UserRepository($pdo);
                $userService = new \App\Application\User\UserService($userRepository);

                echo "\nExecutando: UserService::updateUser() para corrigir role...\n";
                $userService->updateUser(
                    $user['id'],
                    1,  // tenant_id (sistema/default)
                    ['role' => 'admin']
                );
                echo "✓ Role corrigida para 'admin' via UserService\n";
            } catch (\Exception $e) {
                echo "✗ Erro ao usar UserService: " . $e->getMessage() . "\n";
                echo "Tentando fallback (UPDATE direto)...\n";
                // Fallback: UPDATE direto como último recurso
                $fixStmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE username = 'admin'");
                $fixStmt->execute();
                echo "✓ Role corrigida via fallback (UPDATE direto)\n";
            }
        }
    } else {
        echo "✗ Usuário 'admin' NÃO encontrado no banco!\n";
        echo "\nVerificando todos os usuários:\n";
        $stmt = $pdo->prepare("SELECT id, username, role, active FROM users LIMIT 5");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $u) {
            echo "  - " . $u['username'] . " (role='" . $u['role'] . "', ativo=" . ($u['active'] ? 'sim' : 'não') . ")\n";
        }
    }
} catch (Exception $e) {
    echo "✗ Erro ao consultar banco: " . $e->getMessage() . "\n";
}
