  <link rel="stylesheet" href="../../assets/css/ribbons.css">
<?php
require_once '../../auth.php';
require_once '../../check_permissions.php';

// Verifica se o usuário está autenticado
checkAuth();

// Verifica se o usuário é administrador
checkAdmin();

// Inicia o buffer de saída
ob_start();
?>
<!-- users.php: Gerenciamento de Usuários (apenas para admin) -->
<div id="users-section" class="flex justify-center">
  <div class="w-full max-w-3xl bg-white rounded-lg shadow-sm p-8 mt-6">
    <h2 class="text-2xl font-bold mb-6 text-center">Usuários do Sistema</h2>
    <div class="mb-6 flex justify-center">
  <button id="btn-add-user" class="btn-sistema-principal"><i class="fa-solid fa-user-plus"></i>Novo Usuário</button>
    </div>
    <div id="users-list" class="mb-8">
      <table class="table w-full">
        <thead>
          <tr>
            <th class="py-2">ID</th>
            <th class="py-2">Usuário</th>
            <th class="py-2">Nome</th>
            <th class="py-2">E-mail</th>
            <th class="py-2">Tipo</th>
            <th class="py-2">Status</th>
            <th class="py-2">Ações</th>
            <th class="py-2">Excluir</th>
          </tr>
        </thead>
        <tbody>
          <!-- Os usuários serão carregados dinamicamente aqui -->
        </tbody>
      </table>
    </div>
    <div id="user-form-container" style="display:none;">
      <h3 id="user-form-title" class="text-lg font-semibold mb-2">Novo Usuário</h3>
      <form id="user-form" class="space-y-3">
        <input type="hidden" id="user-id" />
        <div>
          <label for="user-username" class="block">Usuário (login)</label>
          <input id="user-username" class="form-control" required disabled />
        </div>
        <div>
          <label for="user-password" class="block">Senha</label>
          <input id="user-password" type="password" class="form-control" />
        </div>
        <div>
          <label for="user-name" class="block">Nome</label>
          <input id="user-name" class="form-control" required />
        </div>
        <div>
          <label for="user-email" class="block">E-mail</label>
          <input id="user-email" type="email" class="form-control" required />
        </div>
        <div>
          <label for="user-role" class="block">Tipo</label>
          <select id="user-role" class="form-control">
            <option value="user">Usuário</option>
            <option value="admin">Administrador</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="btn-sistema-principal">Salvar</button>
          <button type="button" id="btn-cancel-user" class="btn btn-secondary">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
echo $content;
?>
