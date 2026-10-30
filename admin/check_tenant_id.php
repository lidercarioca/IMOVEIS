<?php

/**
 * Verifica a configuração de tenant_id do usuário admin
 * Hardening: só executa via CLI. Bloqueia acesso HTTP imediatamente.
 */

// SECURITY: impedir execução via HTTP — este utilitário é CLI-only
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Access denied. This utility is CLI-only.\n";
    exit;
}

require_once __DIR__ . '/../config/database.php';

echo "=== Verificação de tenant_id ===\n\n";

// Procurar pelo usuário admin
$stmt = $pdo->prepare("SELECT id, username, role, is_superadmin, tenant_id FROM users WHERE username = 'admin'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "Usuário admin encontrado:\n";
    echo "  ID: " . $user['id'] . "\n";
    echo "  Username: " . $user['username'] . "\n";
    echo "  Role: " . $user['role'] . "\n";
    echo "  Is Superadmin: " . ($user['is_superadmin'] ? 'YES' : 'NO') . "\n";
    echo "  Tenant ID: " . ($user['tenant_id'] ?? 'NULL') . "\n";

    if (!$user['tenant_id']) {
        echo "\n⚠️ PROBLEMA: tenant_id do admin é NULL ou 0!\n";
        echo "Corrigindo via UserService...\n";

        try {
            require_once __DIR__ . '/../src/Infrastructure/User/UserRepository.php';
            require_once __DIR__ . '/../src/Application/User/UserService.php';

            $userRepository = new \App\Infrastructure\User\UserRepository($pdo);
            $userService = new \App\Application\User\UserService($userRepository);

            // Tentativa segura: usar UserService para atualizar apenas se contexto/validação estiver clara.
            // NÃO aplicar fallback automático nem operações diretas de UPDATE neste script.
            $userService->updateUser($user['id'], 1, ['tenant_id' => 1]);
            echo "✓ Admin tenant_id atualizado para 1 via UserService\n";
        } catch (\Exception $e) {
            // Não executar fallback automático. Informar e abortar para intervenção manual.
            echo "✗ Erro ao usar UserService: " . $e->getMessage() . "\n";
            echo "Ação interrompida: NÃO será executado UPDATE direto. Verifique manualmente.\n";
        }
    }
} else {
    echo "❌ Usuário admin não encontrado\n";
}

// Verifica se há outros usuários com tenant_id NULL
echo "\n\nVerificando outros usuários com tenant_id NULL:\n";
$stmt = $pdo->prepare("SELECT id, username, tenant_id FROM users WHERE tenant_id IS NULL OR tenant_id = 0 LIMIT 10");
$stmt->execute();
$nullTenantUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($nullTenantUsers)) {
    echo "✓ Nenhum usuário com tenant_id NULL\n";
} else {
    echo "⚠️ Encontrados " . count($nullTenantUsers) . " usuários com tenant_id NULL/0:\n";
    foreach ($nullTenantUsers as $u) {
        echo "  - ID:" . $u['id'] . " | Username: " . $u['username'] . "\n";
    }

    echo "\nCorrigindo via UserService...\n";
    try {
        require_once __DIR__ . '/../src/Infrastructure/User/UserRepository.php';
        require_once __DIR__ . '/../src/Application/User/UserService.php';

        $userRepository = new \App\Infrastructure\User\UserRepository($pdo);
        $userService = new \App\Application\User\UserService($userRepository);

        $updated = 0;
        foreach ($nullTenantUsers as $u) {
            try {
                $userService->updateUser($u['id'], 1, ['tenant_id' => 1]);
                $updated++;
            } catch (\Exception $e) {
                error_log("Erro ao atualizar user ID " . $u['id'] . ": " . $e->getMessage());
            }
        }
        echo "✓ " . $updated . "/" . count($nullTenantUsers) . " usuários atualizados via UserService\n";
    } catch (\Exception $e) {
        // NÃO executar fallback UPDATE direto; abortar e reportar
        echo "✗ Erro ao usar UserService: " . $e->getMessage() . "\n";
        echo "Ação interrompida: NÃO será executado UPDATE direto. Verifique manualmente.\n";
    }
}
