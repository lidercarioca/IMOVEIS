<?php

/**
 * SELECIONAR TENANT
 * Interface web para selecionar e alternar entre tenants
 */

require 'config/database.php';
require_once 'config/load_env.php';
$paymentsConfig = require 'config/payments.php';
$stripeSyncAvailable = !empty($paymentsConfig['stripe']['secret_key'] ?? '');
require_once 'app/helpers/tenant_url_helpers.php';
require_once __DIR__ . '/src/Infrastructure/Billing/TenantRepository.php';
require_once __DIR__ . '/app/security/TenantContext.php';
require_once 'config/session_manager.php';

// Verificar se usuário está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Verificar se é superadmin
$user_type = $_SESSION['role'] ?? $_SESSION['user_type'] ?? 'user';
$isSuperAdmin = !empty($_SESSION['is_superadmin']) || in_array(strtolower($user_type), ['super_admin', 'superadmin', 'super']);
if (!$isSuperAdmin) {
    http_response_code(403);
    echo '<h1>Acesso negado</h1><p>Somente Superadmin pode acessar esta página.</p>';
    exit;
}

// Inicializar variáveis com os nomes corretos da session (conforme login.php)
$user_name = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Usuário';
$user_email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
$user_type = $_SESSION['role'] ?? $_SESSION['user_type'] ?? 'user';
$current_tenant_id = $_SESSION['tenant_id'] ?? null;
$tenantBillingRepo = new \App\Infrastructure\Billing\TenantRepository($pdo);

// Tentar buscar dados atualizados do banco de dados
try {
    $user_id = $_SESSION['user_id'];

    if (!empty($user_id)) {
        $stmt = $pdo->prepare("SELECT id, username, email, name, type, role FROM users WHERE id = ? LIMIT 1");
        $result = $stmt->execute([$user_id]);

        if ($result) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Usar 'name' se existir, senão usar 'username'
                if (!empty($user['name'])) {
                    $user_name = $user['name'];
                } elseif (!empty($user['username'])) {
                    $user_name = $user['username'];
                }

                // Usar email do BD
                if (!empty($user['email'])) {
                    $user_email = $user['email'];
                }

                // Usar 'role' ou 'type' do BD
                if (!empty($user['role'])) {
                    $user_type = $user['role'];
                } elseif (!empty($user['type'])) {
                    $user_type = $user['type'];
                }
            }
        }
    }
} catch (Exception $e) {
    // Em caso de erro, usar dados da session como fallback
    error_log("Erro ao buscar usuário: " . $e->getMessage());
}

// Tentar buscar dados atualizados do banco de dados usando UserRepository
try {
    $user_id = $_SESSION['user_id'] ?? null;

    if (!empty($user_id)) {
        require_once __DIR__ . '/src/Infrastructure/User/UserRepository.php';
        $userRepo = new \App\Infrastructure\User\UserRepository($pdo);
        $user = $userRepo->findByIdAnyTenant((int)$user_id);

        if ($user) {
            // Usar 'name' se existir, senão usar 'username'
            if (!empty($user['name'])) {
                $user_name = $user['name'];
            } elseif (!empty($user['username'])) {
                $user_name = $user['username'];
            }

            // Usar email do BD
            if (!empty($user['email'])) {
                $user_email = $user['email'];
            }

            // Usar 'role' ou 'type' do BD
            if (!empty($user['role'])) {
                $user_type = $user['role'];
            } elseif (!empty($user['type'])) {
                $user_type = $user['type'];
            }
        }
    }
} catch (Exception $e) {
    // Em caso de erro, usar dados da session como fallback
    error_log("Erro ao buscar usuário: " . $e->getMessage());
}

// Determinar rótulo do tipo de usuário
$user_type_label = 'Usuário';
if ($isSuperAdmin) {
    $user_type_label = 'Super Administrador';
} elseif (in_array($user_type, ['admin', 'administrator'])) {
    $user_type_label = 'Administrador';
} elseif (in_array($user_type, ['manager'])) {
    $user_type_label = 'Gerenciador';
}

function isSuperAdmin($userType = null)
{
    if (!empty($_SESSION['is_superadmin'])) {
        return true;
    }

    $roleToCheck = $userType ?? $_SESSION['role'] ?? '';
    return in_array(strtolower($roleToCheck), ['super_admin', 'superadmin', 'super']);
}

function planHasStripePriceIdColumn(): bool
{
    global $pdo;
    static $hasStripePriceIdColumn = null;

    if ($hasStripePriceIdColumn !== null) {
        return $hasStripePriceIdColumn;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM plans LIKE 'stripe_price_id'");
        $hasStripePriceIdColumn = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Erro ao verificar coluna stripe_price_id na tabela plans: ' . $e->getMessage());
        $hasStripePriceIdColumn = false;
    }

    return $hasStripePriceIdColumn;
}

function normalizeBillingPlanInput(array $postData): array
{
    return [
        'code' => strtolower(trim($postData['code'] ?? '')),
        'name' => trim($postData['name'] ?? ''),
        'description' => trim($postData['description'] ?? ''),
        'monthly_price' => str_replace(',', '.', trim($postData['monthly_price'] ?? '0')),
        'max_users' => max(1, intval($postData['max_users'] ?? 1)),
        'max_properties' => max(0, intval($postData['max_properties'] ?? 0)),
        'trial_days' => max(0, intval($postData['trial_days'] ?? 0)),
        'is_trial' => isset($postData['is_trial']) ? 1 : 0,
        'is_active' => isset($postData['is_active']) ? 1 : 0,
        'display_order' => intval($postData['display_order'] ?? 0),
        'stripe_price_id' => planHasStripePriceIdColumn() ? trim($postData['stripe_price_id'] ?? '') : null,
    ];
}

function validateBillingPlanInput(array $planData, bool $isUpdate = false): array
{
    $errors = [];

    if (!$isUpdate) {
        if ($planData['code'] === '' || !preg_match('/^[a-z0-9_\-]+$/', $planData['code'])) {
            $errors[] = 'Código do plano inválido. Use letras minúsculas, números, hífen ou underscore.';
        }
    }

    if ($planData['name'] === '') {
        $errors[] = 'Nome do plano é obrigatório.';
    }

    if ($planData['monthly_price'] === '') {
        $planData['monthly_price'] = '0';
    }

    if (!is_numeric($planData['monthly_price'])) {
        $errors[] = 'Preço mensal inválido.';
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => $planData,
    ];
}

function createBillingPlan(array $planData): array
{
    global $pdo;

    $validation = validateBillingPlanInput($planData, false);
    if (!$validation['valid']) {
        return [
            'success' => false,
            'message' => implode(' ', $validation['errors']),
        ];
    }

    $planData = $validation['data'];

    try {
        $stmt = $pdo->prepare('SELECT id FROM plans WHERE code = ? LIMIT 1');
        $stmt->execute([$planData['code']]);
        if ($stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Já existe um plano com este código. Escolha outro código.',
            ];
        }

        $priceValue = number_format((float)$planData['monthly_price'], 2, '.', '');

        if (planHasStripePriceIdColumn()) {
            $stmt = $pdo->prepare('INSERT INTO plans (code, name, description, max_users, max_properties, monthly_price, trial_days, is_trial, is_active, display_order, stripe_price_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([
                $planData['code'],
                $planData['name'],
                $planData['description'],
                $planData['max_users'],
                $planData['max_properties'],
                $priceValue,
                $planData['trial_days'],
                $planData['is_trial'],
                $planData['is_active'],
                $planData['display_order'],
                $planData['stripe_price_id'] ?: null,
            ]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO plans (code, name, description, max_users, max_properties, monthly_price, trial_days, is_trial, is_active, display_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([
                $planData['code'],
                $planData['name'],
                $planData['description'],
                $planData['max_users'],
                $planData['max_properties'],
                $priceValue,
                $planData['trial_days'],
                $planData['is_trial'],
                $planData['is_active'],
                $planData['display_order'],
            ]);
        }

        return [
            'success' => true,
            'message' => 'Plano criado com sucesso.',
        ];
    } catch (Throwable $e) {
        error_log('Erro ao criar plano: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao criar plano. Verifique o log.',
        ];
    }
}

function updateBillingPlan(int $planId, array $planData): array
{
    global $pdo;

    if ($planId <= 0) {
        return [
            'success' => false,
            'message' => 'Plano inválido para atualização.',
        ];
    }

    $validation = validateBillingPlanInput($planData, true);
    if (!$validation['valid']) {
        return [
            'success' => false,
            'message' => implode(' ', $validation['errors']),
        ];
    }

    $planData = $validation['data'];
    $priceValue = number_format((float)$planData['monthly_price'], 2, '.', '');

    try {
        if (planHasStripePriceIdColumn()) {
            $stmt = $pdo->prepare('UPDATE plans SET name = ?, description = ?, max_users = ?, max_properties = ?, monthly_price = ?, trial_days = ?, is_trial = ?, is_active = ?, display_order = ?, stripe_price_id = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([
                $planData['name'],
                $planData['description'],
                $planData['max_users'],
                $planData['max_properties'],
                $priceValue,
                $planData['trial_days'],
                $planData['is_trial'],
                $planData['is_active'],
                $planData['display_order'],
                $planData['stripe_price_id'] ?: null,
                $planId,
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE plans SET name = ?, description = ?, max_users = ?, max_properties = ?, monthly_price = ?, trial_days = ?, is_trial = ?, is_active = ?, display_order = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([
                $planData['name'],
                $planData['description'],
                $planData['max_users'],
                $planData['max_properties'],
                $priceValue,
                $planData['trial_days'],
                $planData['is_trial'],
                $planData['is_active'],
                $planData['display_order'],
                $planId,
            ]);
        }

        return [
            'success' => true,
            'message' => 'Plano atualizado com sucesso.',
        ];
    } catch (Throwable $e) {
        error_log('Erro ao atualizar plano: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erro ao atualizar plano. Verifique o log.',
        ];
    }
}

$planMessage = '';
$activeTab = 'tab-select';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['active_tab'])) {
    $activeTab = $_REQUEST['active_tab'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_action'])) {
    if (!isSuperAdmin($user_type)) {
        $planMessage = 'Apenas Superadmin pode gerenciar planos.';
    } else {
        $planMessage = handlePlanAction($pdo, $_POST);
    }
    if (empty($activeTab)) {
        $activeTab = 'tab-plans';
    }
}

function handlePlanAction(PDO $pdo, array $postData): string
{
    $action = $postData['plan_action'];

    if ($action === 'create_plan') {
        $planData = normalizeBillingPlanInput($postData);
        $result = createBillingPlan($planData);
        return $result['message'] ?? ($result['success'] ? 'Plano criado com sucesso.' : 'Erro ao criar plano. Verifique o log.');
    }

    if ($action === 'update_plan') {
        $planId = intval($postData['plan_id'] ?? 0);
        $planData = normalizeBillingPlanInput($postData);

        if ($planId <= 0) {
            return 'Plano inválido para atualização.';
        }

        $result = updateBillingPlan($planId, $planData);
        return $result['message'] ?? ($result['success'] ? 'Plano atualizado com sucesso.' : 'Erro ao atualizar plano. Verifique o log.');
    }

    if ($action === 'toggle_plan') {
        $planId = intval($postData['plan_id'] ?? 0);
        if ($planId <= 0) {
            return 'Plano inválido para alteração de status.';
        }

        $status = ($postData['new_status'] ?? '') === 'activate' ? 1 : 0;
        try {
            $stmt = $pdo->prepare('UPDATE plans SET is_active = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $planId]);
            return $status ? 'Plano ativado.' : 'Plano desativado.';
        } catch (Exception $e) {
            error_log('Erro ao alterar status do plano: ' . $e->getMessage());
            return 'Erro ao alterar status do plano. Verifique o log.';
        }
    }

    return 'Ação de plano desconhecida.';
}

function fetchPlans(PDO $pdo): array
{
    try {
        $stmt = $pdo->prepare('SELECT * FROM plans ORDER BY display_order ASC, id ASC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Erro ao buscar planos: ' . $e->getMessage());
        return [];
    }
}

function renderPlansManagerPanel(PDO $pdo, string $planMessage = ''): string
{
    global $paymentsConfig;

    $plans = fetchPlans($pdo);
    $showStripePriceId = planHasStripePriceIdColumn();
    $stripeSyncAvailable = !empty($paymentsConfig['stripe']['secret_key'] ?? '');
    $html = '<div class="plans-panel">';
    $html .= '<div class="plans-panel-header"><div><h2>Gerenciamento de Planos</h2><p>Defina preço, limite de usuários e descrição de cada plano.</p></div>';

    if ($showStripePriceId) {
        if ($stripeSyncAvailable) {
            $html .= '<div style="text-align:right;"><button type="button" id="syncStripePriceIdsBtn" onclick="syncStripePriceIds()" style="padding: 0.75rem 1rem; background: #1976d2; color: #fff; border: none; border-radius: 0.5rem; cursor: pointer;">Sincronizar Stripe Price IDs</button></div>';
        } else {
            $html .= '<div style="margin-top: 0.75rem; color: #d32f2f; font-weight: 600;">Stripe não configurado. Defina STRIPE_SECRET_KEY para habilitar a sincronização de preços.</div>';
        }
    }

    $html .= '</div>';
    if ($showStripePriceId) {
        $html .= '<div id="stripeSyncFeedback" style="margin: 1rem 0;"></div>';
    }

    if ($planMessage) {
        $html .= '<div class="plan-message">' . htmlspecialchars($planMessage) . '</div>';
    }

    $html .= '<div class="plans-grid">';

    foreach ($plans as $plan) {
        $html .= '<div class="plan-card">';
        $html .= '<form method="POST" class="plan-form">';
        $html .= '<input type="hidden" name="plan_action" value="update_plan">';
        $html .= '<input type="hidden" name="active_tab" value="tab-plans">';
        $html .= '<input type="hidden" name="plan_id" value="' . intval($plan['id']) . '">';
        $html .= '<div class="plan-card-header"><strong>' . htmlspecialchars($plan['name']) . '</strong><span class="plan-badge">' . htmlspecialchars($plan['code']) . '</span></div>';
        $html .= '<div class="plan-field"><label>Nome do plano</label><input type="text" name="name" value="' . htmlspecialchars($plan['name']) . '" required></div>';
        $html .= '<div class="plan-field"><label>Descrição</label><textarea name="description" rows="2">' . htmlspecialchars($plan['description']) . '</textarea></div>';
        $html .= '<div class="plan-field"><label>Preço mensal (R$)</label><input type="text" name="monthly_price" value="' . number_format((float)$plan['monthly_price'], 2, ',', '.') . '"></div>';
        $html .= '<div class="plan-field"><label>Limite de usuários</label><input type="number" min="1" name="max_users" value="' . intval($plan['max_users']) . '"></div>';
        $html .= '<div class="plan-field"><label>Limite de imóveis</label><input type="number" min="0" name="max_properties" value="' . intval($plan['max_properties'] ?? 0) . '"></div>';
        $html .= '<div class="plan-field"><label>Dias de trial</label><input type="number" min="0" name="trial_days" value="' . intval($plan['trial_days']) . '"></div>';
        if ($showStripePriceId) {
            $html .= '<div class="plan-field"><label>Stripe Price ID</label><input type="text" name="stripe_price_id" value="' . htmlspecialchars($plan['stripe_price_id'] ?? '', ENT_QUOTES) . '"></div>';
        }
        $html .= '<div class="plan-field plan-field-inline">';
        $html .= '<label><input type="checkbox" name="is_trial" value="1"' . ($plan['is_trial'] ? ' checked' : '') . '> Trial</label>';
        $html .= '<label><input type="checkbox" name="is_active" value="1"' . ($plan['is_active'] ? ' checked' : '') . '> Ativo</label>';
        $html .= '</div>';
        $html .= '<div class="plan-field"><label>Ordem</label><input type="number" name="display_order" value="' . intval($plan['display_order']) . '"></div>';
        $html .= '<div class="plan-actions">';
        $html .= '<button type="submit" class="plan-save-btn">Salvar alterações</button>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '<form method="POST" class="plan-toggle-form">';
        $html .= '<input type="hidden" name="plan_action" value="toggle_plan">';
        $html .= '<input type="hidden" name="active_tab" value="tab-plans">';
        $html .= '<input type="hidden" name="plan_id" value="' . intval($plan['id']) . '">';
        $html .= '<input type="hidden" name="new_status" value="' . ($plan['is_active'] ? 'deactivate' : 'activate') . '">';
        $html .= '<button type="submit" class="plan-toggle-btn ' . ($plan['is_active'] ? 'deactivate' : 'activate') . '">' . ($plan['is_active'] ? 'Desativar plano' : 'Ativar plano') . '</button>';
        $html .= '</form>';
        $html .= '</div>';
    }

    $html .= '</div>'; // plans-grid

    $html .= '<div class="plan-create-card">';
    $html .= '<h3>Criar novo plano</h3>';
    $html .= '<form method="POST" class="plan-form">';
    $html .= '<input type="hidden" name="plan_action" value="create_plan">';
    $html .= '<input type="hidden" name="active_tab" value="tab-plans">';
    $html .= '<div class="plan-field"><label>Código do plano</label><input type="text" name="code" placeholder="ex: basico" required></div>';
    $html .= '<div class="plan-field"><label>Nome do plano</label><input type="text" name="name" placeholder="Nome do plano" required></div>';
    $html .= '<div class="plan-field"><label>Descrição</label><textarea name="description" rows="3" placeholder="Breve descrição"></textarea></div>';
    $html .= '<div class="plan-field"><label>Preço mensal (R$)</label><input type="text" name="monthly_price" placeholder="0,00" value="0,00"></div>';
    $html .= '<div class="plan-field"><label>Limite de usuários</label><input type="number" min="1" name="max_users" value="1"></div>';
    $html .= '<div class="plan-field"><label>Limite de imóveis</label><input type="number" min="0" name="max_properties" value="0"></div>';
    $html .= '<div class="plan-field"><label>Dias de trial</label><input type="number" min="0" name="trial_days" value="0"></div>';
    if ($showStripePriceId) {
        $html .= '<div class="plan-field"><label>Stripe Price ID</label><input type="text" name="stripe_price_id" value=""></div>';
    }
    $html .= '<div class="plan-field plan-field-inline">';
    $html .= '<label><input type="checkbox" name="is_trial" value="1"> Trial</label>';
    $html .= '<label><input type="checkbox" name="is_active" value="1" checked> Ativo</label>';
    $html .= '</div>';
    $html .= '<div class="plan-field"><label>Ordem</label><input type="number" name="display_order" value="0"></div>';
    $html .= '<div class="plan-actions">';
    $html .= '<button type="submit" class="plan-save-btn">Criar plano</button>';
    $html .= '</div>';
    $html .= '</form>';
    $html .= '</div>';

    $html .= '</div>'; // plans-panel

    return $html;
}

function deleteTenantCompletely(PDO $pdo, int $tenantId): void
{
    // Excluir registros em todas as tabelas que possuem tenant_id (exceto tenants)
    $sql = "SELECT TABLE_NAME FROM information_schema.columns WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'tenant_id' AND TABLE_NAME != 'tenants'";
    $tables = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
    }

    // Excluir tenant
    $stmt = $pdo->prepare("DELETE FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
}

// Processar exclusão de tenant (apenas super admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tenant_id'])) {
    $deleteTenantId = intval($_POST['delete_tenant_id']);

    header('Content-Type: application/json');

    if (!isSuperAdmin($user_type)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Apenas superadmin pode excluir tenants.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Verificar existência
        $stmt = $pdo->prepare("SELECT id, name FROM tenants WHERE id = ?");
        $stmt->execute([$deleteTenantId]);
        $tenantToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenantToDelete) {
            throw new Exception('Tenant não encontrado.');
        }

        if ($tenantToDelete['id'] === ($current_tenant_id ?? 0)) {
            unset($_SESSION['tenant_id'], $_SESSION['selected_tenant_id'], $_SESSION['tenant_name'], $_SESSION['tenant_slug']);
        }

        deleteTenantCompletely($pdo, $deleteTenantId);

        $pdo->commit();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Tenant e todos dados relacionados excluídos com sucesso.']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro ao excluir tenant: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir tenant: ' . $e->getMessage()]);
        exit;
    }
}

// Processar habilitar/desabilitar tenant (apenas super admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_tenant_id']) && isset($_POST['toggle_action'])) {
    $toggleTenantId = intval($_POST['toggle_tenant_id']);
    $toggleAction = ($_POST['toggle_action'] === 'enable') ? 'active' : 'inactive';

    header('Content-Type: application/json');

    if (!isSuperAdmin($user_type)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Apenas superadmin pode alterar status de tenants.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id, name, status FROM tenants WHERE id = ?");
        $stmt->execute([$toggleTenantId]);
        $tenantToToggle = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenantToToggle) {
            throw new Exception('Tenant não encontrado.');
        }

        if ($tenantToToggle['status'] === $toggleAction) {
            throw new Exception('Tenant já está com status ' . $toggleAction . '.');
        }

        $stmt = $pdo->prepare("UPDATE tenants SET status = ? WHERE id = ?");
        $stmt->execute([$toggleAction, $toggleTenantId]);

        if ($toggleAction === 'inactive' && ($current_tenant_id ?? 0) === $toggleTenantId) {
            unset($_SESSION['tenant_id'], $_SESSION['selected_tenant_id'], $_SESSION['tenant_name'], $_SESSION['tenant_slug']);
        }

        $pdo->commit();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Tenant ' . ($toggleAction === 'active' ? 'habilitado' : 'desabilitado') . ' com sucesso.']);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro ao alterar status do tenant: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao alterar status do tenant: ' . $e->getMessage()]);
        exit;
    }
}

// Processar seleção de tenant (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_id'])) {
    $tenant_id = intval($_POST['tenant_id']);

    try {
        if (isSuperAdmin($user_type)) {
            $stmt = $pdo->prepare("SELECT id, name, slug, status FROM tenants WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("SELECT id, name, slug, status FROM tenants WHERE id = ? AND status = 'active'");
        }
        $stmt->execute([$tenant_id]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tenant) {
            if (!\TenantContext::selectTenant($tenant['id'])) {
                throw new Exception('Você não possui acesso ao tenant selecionado.');
            }

            // Salvar na session com AMBOS os nomes para compatibilidade
            $_SESSION['tenant_name'] = $tenant['name'];
            $_SESSION['tenant_slug'] = $tenant['slug'];

            // Log para debug
            error_log("Tenant selecionado: ID={$tenant['id']}, Name={$tenant['name']}");

            // Retornar resposta JSON de sucesso
            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Tenant selecionado com sucesso',
                'tenant_id' => $tenant['id'],
                'redirect_url' => '/painel.php'
            ]);
            exit;
        } else {
            throw new Exception('Tenant não encontrado ou inativo');
        }
    } catch (Exception $e) {
        error_log("Erro ao selecionar tenant: " . $e->getMessage());
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao selecionar tenant: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Obter todos os tenants
$tenants = [];
try {
    $statusFilter = isSuperAdmin($user_type) ? '' : "WHERE t.status = 'active'";
    $stmt = $pdo->query("\n        SELECT \n            t.id, \n            t.name, \n            t.slug,\n            t.company_subdomain,\n            t.description,\n            t.status,\n            t.plano,\n            t.data_expiracao,\n            t.limite_usuarios,\n            COUNT(DISTINCT l.id) as lead_count,\n            COUNT(DISTINCT u.id) as user_count\n        FROM tenants t\n        LEFT JOIN leads l ON l.tenant_id = t.id\n        LEFT JOIN users u ON u.tenant_id = t.id\n        " . $statusFilter . "\n        GROUP BY t.id, t.name, t.slug, t.company_subdomain, t.description, t.status, t.plano, t.data_expiracao, t.limite_usuarios\n        ORDER BY t.id\n    ");

    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erro ao carregar tenants: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Empresa</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 20px;
        }

        .user-details h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .user-details p {
            color: #666;
            font-size: 13px;
            margin: 0;
        }

        .user-type-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
        }

        .logout-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            color: #333;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: #e0e0e0;
            border-color: #999;
        }

        .title {
            color: white;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 30px;
            text-align: center;
        }

        .tenants-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .tenant-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .tenant-card:hover {
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
            transform: translateY(-5px);
        }

        .tenant-card.active {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .tenant-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .tenant-card h2 {
            color: #333;
            font-size: 20px;
            margin: 0;
        }

        .status-badge {
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-badge.active {
            background: #4CAF50;
        }

        .status-badge.inactive {
            background: #757575;
        }

        .disable-btn {
            flex: 1;
            padding: 12px 20px;
            background: #f57c00;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .disable-btn:hover {
            background: #ef6c00;
            transform: scale(1.02);
        }

        .enable-btn {
            flex: 1;
            padding: 12px 20px;
            background: #43a047;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .enable-btn:hover {
            background: #388e3c;
            transform: scale(1.02);
        }

        .status-badge:active,
        .disable-btn:active,
        .enable-btn:active {
            transform: scale(0.98);
        }

        .tenant-card p {
            color: #999;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .tenant-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            font-size: 13px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            color: #999;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .plans-panel {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .plans-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
        }

        .plans-panel h2 {
            margin: 0 0 8px;
            font-size: 24px;
            color: #2d3748;
        }

        .plan-message {
            background: #e8f5e9;
            color: #1b5e20;
            border-left: 4px solid #43a047;
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .plan-card,
        .plan-create-card {
            background: #f8fafc;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .plan-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .plan-badge {
            background: #eef2ff;
            color: #4338ca;
            font-size: 12px;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 700;
        }

        .plan-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 14px;
        }

        .plan-field label {
            color: #4a5568;
            font-weight: 600;
        }

        .plan-field input,
        .plan-field textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 14px;
            color: #1f2937;
            background: white;
        }

        .plan-field textarea {
            min-height: 72px;
            resize: vertical;
        }

        .plan-field-inline {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .plan-field-inline label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .plan-actions {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            flex-wrap: wrap;
        }

        .plan-save-btn,
        .plan-toggle-btn {
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .plan-save-btn {
            background: #667eea;
            color: white;
        }

        .plan-save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.18);
        }

        .plan-toggle-btn.activate {
            background: #43a047;
            color: white;
        }

        .plan-toggle-btn.deactivate {
            background: #ef4444;
            color: white;
        }

        .plan-toggle-btn:hover {
            transform: translateY(-1px);
        }

        .tabs {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .tab-button {
            border: none;
            border-radius: 999px;
            padding: 12px 22px;
            background: #e2e8f0;
            color: #1f2937;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tab-button.active,
        .tab-button:hover {
            background: #667eea;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .tab-content.active {
            animation: fadeIn 0.22s ease-in-out;
        }

        .info-value {
            color: #333;
        }

        .tenant-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            margin-top: 10px;
        }

        .tenant-actions button {
            flex: 1 1 110px;
            min-width: 110px;
            max-width: 220px;
            white-space: nowrap;
        }

        .select-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .select-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .select-btn:active {
            transform: scale(0.98);
        }

        .delete-btn {
            flex: 1;
            padding: 12px 20px;
            background: #e53935;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .delete-btn:hover {
            background: #c62828;
            transform: scale(1.02);
        }

        .delete-btn:active {
            transform: scale(0.98);
        }

        .current-badge {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            margin-top: 10px;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #c62828;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 80px;
            height: 80px;
            border: 8px solid rgba(255, 255, 255, 0.3);
            border-top: 8px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.5);
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            position: absolute;
            color: white;
            font-size: 16px;
            margin-top: 120px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .user-info {
                flex-direction: column;
                justify-content: center;
            }

            .title {
                font-size: 24px;
            }

            .tenants-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header com dados do usuário -->
        <div class="header">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                <div class="user-details">
                    <h3>👤 Usuário: <strong><?php echo htmlspecialchars($user_name ?: 'Não identificado'); ?></strong></h3>
                    <p>📧 Email: <strong><?php echo htmlspecialchars($user_email ?: '(não informado)'); ?></strong></p>
                    <div class="user-type-badge">
                        <?php
                        if ($user_type === 'super_admin') {
                            echo '👑 ' . htmlspecialchars($user_type_label);
                        } elseif ($user_type === 'admin') {
                            echo '⚙️ ' . htmlspecialchars($user_type_label);
                        } else {
                            echo '👤 ' . htmlspecialchars($user_type_label);
                        }
                        ?>
                    </div>
                </div>
            </div>
            <a href="/logout.php" class="logout-btn">Logout</a>
        </div>

        <!-- Título -->
        <h1 class="title">Selecione uma empresa</h1>

        <!-- Mensagem de erro -->
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isSuperAdmin($user_type)): ?>
            <div class="tabs">
                <button type="button" class="tab-button <?php echo ($activeTab === 'tab-select') ? 'active' : ''; ?>" onclick="switchTab('tab-select', this)">Selecionar empresa</button>
                <button type="button" class="tab-button <?php echo ($activeTab === 'tab-plans') ? 'active' : ''; ?>" onclick="switchTab('tab-plans', this)">Gerenciar Planos</button>
            </div>

            <div id="tab-select" class="tab-content <?php echo ($activeTab === 'tab-select') ? 'active' : ''; ?>">
                <!-- Grid de Tenants -->
                <div class="tenants-grid">
                <?php else: ?>
                    <div class="tenants-grid">
                    <?php endif; ?>
                    <?php foreach ($tenants as $tenant): ?>
                        <div class="tenant-card <?php echo ($current_tenant_id == $tenant['id']) ? 'active' : ''; ?>">
                            <div class="tenant-card-header">
                                <h2><?php echo htmlspecialchars($tenant['name']); ?></h2>
                                <?php $tenantStatus = $tenant['status'] ?? 'inactive'; ?>
                                <?php $statusLabel = ($tenantStatus === 'active') ? 'Ativo' : 'Inativo'; ?>
                                <span class="status-badge <?php echo ($tenantStatus === 'active') ? 'active' : 'inactive'; ?>"><?php echo $statusLabel; ?></span>
                            </div>

                            <p><?php echo htmlspecialchars($tenant['description'] ?? 'Sem descrição'); ?></p>

                            <div class="tenant-info">
                                <div class="info-item">
                                    <span class="info-label">ID</span>
                                    <span class="info-value"><?php echo $tenant['id']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Subdomínio</span>
                                    <span class="info-value"><?php echo htmlspecialchars($tenant['company_subdomain'] ?: $tenant['slug']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Site</span>
                                    <span class="info-value"><a href="<?php echo htmlspecialchars(getTenantPublicUrl($tenant)); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars(getTenantPublicUrl($tenant)); ?></a></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Leads</span>
                                    <span class="info-value"><?php echo $tenant['lead_count']; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Usuários</span>
                                    <span class="info-value"><?php echo $tenant['user_count']; ?></span>
                                </div>
                            </div>

                            <!-- Seção de Cobrança (NOVO) -->
                            <div class="tenant-info" style="background: #fafafa; border-left: 4px solid #2196F3;">
                                <div class="info-item">
                                    <span class="info-label">Plano</span>
                                    <span class="info-value" style="font-weight: bold;">
                                        <?php echo $tenant['plano'] ? ucfirst($tenant['plano']) : 'N/A'; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Limite</span>
                                    <span class="info-value"><?php echo $tenant['limite_usuarios'] ?? '-'; ?> usuários</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Expiração</span>
                                    <span class="info-value">
                                        <?php
                                        if ($tenant['data_expiracao']) {
                                            $diasRestantes = $tenantBillingRepo->getDaysRemaining((int)$tenant['id']);
                                            echo date('d/m/Y', strtotime($tenant['data_expiracao']));
                                            if ($diasRestantes < 0) {
                                                echo ' <span style="color: #d32f2f;">(Expirado)</span>';
                                            } elseif ($diasRestantes <= 3) {
                                                echo ' <span style="color: #f57c00;">' . $diasRestantes . ' dias</span>';
                                            }
                                        } else {
                                            echo 'Sem data';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Cobrança</span>
                                    <span class="info-value" style="color: #2196F3; font-weight: bold;">
                                        <i class="fas fa-money-bill-wave"></i> Ver
                                    </span>
                                </div>
                            </div>

                            <div class="tenant-actions">
                                <?php $canSelectTenant = $tenantStatus === 'active' || isSuperAdmin($user_type); ?>
                                <button type="button" class="select-btn" onclick="selectTenant(this, <?php echo $tenant['id']; ?>)" <?php echo $canSelectTenant ? '' : 'disabled style="opacity:0.5;cursor:not-allowed;"'; ?>>
                                    ✓ Selecionar empresa
                                </button>

                                <?php if (isSuperAdmin($user_type)): ?>
                                    <?php if ($tenantStatus === 'active'): ?>
                                        <button type="button" class="disable-btn" onclick="confirmToggleTenant(<?php echo $tenant['id']; ?>, 'disable', '<?php echo addslashes(htmlspecialchars($tenant['name'])); ?>')">
                                            ⛔ Desabilitar
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="enable-btn" onclick="confirmToggleTenant(<?php echo $tenant['id']; ?>, 'enable', '<?php echo addslashes(htmlspecialchars($tenant['name'])); ?>')">
                                            ✅ Habilitar
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="delete-btn" onclick="confirmTenantDeletion(<?php echo $tenant['id']; ?>, '<?php echo addslashes(htmlspecialchars($tenant['name'])); ?>')">
                                        🗑️ Excluir
                                    </button>
                                <?php endif; ?>
                            </div>

                            <?php if ($current_tenant_id == $tenant['id']): ?>
                                <div class="current-badge">✓ Empresa atual</div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <?php if (isSuperAdmin($user_type)): ?>
                </div>
                <div id="tab-plans" class="tab-content <?php echo ($activeTab === 'tab-plans') ? 'active' : ''; ?>">
                    <?php echo renderPlansManagerPanel($pdo, $planMessage); ?>
                </div>
            <?php endif; ?>
            </div>

            <!-- Loading Overlay -->
            <div class="loading-overlay" id="loadingOverlay">
                <div>
                    <div class="spinner"></div>
                    <div class="loading-text">Carregando painel...</div>
                </div>
            </div>

            <script>
                function switchTab(tabId, button) {
                    document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));

                    button.classList.add('active');
                    document.getElementById(tabId)?.classList.add('active');
                }

                function selectTenant(button, tenantId) {
                    console.log('🔄 Iniciando seleção de tenant:', tenantId);

                    // Desabilitar o botão para evitar cliques duplos
                    button.disabled = true;
                    button.style.opacity = '0.6';

                    // Mostrar loader
                    const loadingOverlay = document.getElementById('loadingOverlay');
                    loadingOverlay.classList.add('active');

                    // Fazer requisição POST para salvar o tenant na session
                    fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'tenant_id=' + tenantId
                        })
                        .then(response => {
                            console.log('📡 Resposta do servidor:', response.status);

                            if (!response.ok) {
                                throw new Error('Erro HTTP: ' + response.status);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('✅ Dados recebidos:', data);

                            if (data.success) {
                                console.log('🎯 Tenant selecionado com sucesso! Redirecionando...');

                                setTimeout(() => {
                                    const redirectUrl = data.redirect_url || '/painel.php';
                                    console.log('🚀 Redirecionando para:', redirectUrl);
                                    window.location.href = redirectUrl;
                                }, 500);
                            } else {
                                throw new Error(data.message || 'Erro desconhecido');
                            }
                        })
                        .catch(error => {
                            console.error('❌ Erro:', error);
                            console.error('Stack:', error.stack);

                            loadingOverlay.classList.remove('active');
                            button.disabled = false;
                            button.style.opacity = '1';

                            alert('Erro ao selecionar empresa: ' + error.message + '\n\nAbra o Console (F12) para mais detalhes.');
                        });
                }

                function confirmToggleTenant(tenantId, action, tenantName) {
                    const actionLabel = (action === 'enable') ? 'habilitar' : 'desabilitar';
                    if (!confirm('Confirma ' + actionLabel + ' empresa "' + tenantName + '" (ID=' + tenantId + ')?')) {
                        return;
                    }

                    const loadingOverlay = document.getElementById('loadingOverlay');
                    loadingOverlay.classList.add('active');

                    fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'toggle_tenant_id=' + tenantId + '&toggle_action=' + action
                        })
                        .then(response => response.json().then(data => ({
                            status: response.status,
                            body: data
                        })))
                        .then(({
                            status,
                            body
                        }) => {
                            if (status !== 200 || !body.success) {
                                throw new Error(body.message || 'Erro ao alterar status da empresa');
                            }

                            alert('Empresa atualizada com sucesso. A página será recarregada.');
                            window.location.reload();
                        })
                        .catch(error => {
                            console.error('Erro ao alterar status da empresa:', error);
                            alert('Erro ao alterar status da empresa: ' + error.message);
                        })
                        .finally(() => {
                            loadingOverlay.classList.remove('active');
                        });
                }

                function confirmTenantDeletion(tenantId, tenantName) {
                    if (!confirm('Confirma exclusão definitiva da empresa "' + tenantName + '" (ID=' + tenantId + ')? Esta ação é irreversível.')) {
                        return;
                    }

                    const loadingOverlay = document.getElementById('loadingOverlay');
                    loadingOverlay.classList.add('active');

                    fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'delete_tenant_id=' + tenantId
                        })
                        .then(response => response.json().then(data => ({
                            status: response.status,
                            body: data
                        })))
                        .then(({
                            status,
                            body
                        }) => {
                            if (status !== 200 || !body.success) {
                                throw new Error(body.message || 'Erro ao excluir tenant');
                            }

                            alert('Tenant excluído com sucesso. A página será recarregada.');
                            window.location.reload();
                        })
                        .catch(error => {
                            console.error('Erro ao excluir tenant:', error);
                            alert('Erro ao excluir tenant: ' + error.message);
                        })
                        .finally(() => {
                            loadingOverlay.classList.remove('active');
                        });
                }

                async function syncStripePriceIds() {
                    const button = document.getElementById('syncStripePriceIdsBtn');
                    const feedback = document.getElementById('stripeSyncFeedback');

                    if (!button || !feedback) {
                        return;
                    }

                    button.disabled = true;
                    button.style.opacity = '0.6';
                    feedback.textContent = 'Sincronizando Stripe Price IDs...';
                    feedback.style.color = '#333';

                    try {
                        const response = await fetch('api/v1/billing/syncStripePrices.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Falha ao sincronizar os Price IDs.');
                        }

                        feedback.textContent = 'Sincronização concluída. ' + (data.updated_count || 0) + ' plano(s) atualizado(s).';
                        feedback.style.color = '#2e7d32';

                        if (data.updated_count > 0) {
                            setTimeout(() => window.location.reload(), 1200);
                        }
                    } catch (error) {
                        feedback.textContent = error.message || 'Erro ao sincronizar Stripe Price IDs.';
                        feedback.style.color = '#d32f2f';
                        console.error('Erro ao sincronizar Stripe Price IDs:', error);
                    } finally {
                        button.disabled = false;
                        button.style.opacity = '1';
                    }
                }
            </script>
</body>

</html>