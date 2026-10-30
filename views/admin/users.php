<?php
require_once '../../auth.php';

checkAuth();
checkAdmin();
?>

<link rel="stylesheet" href="../../assets/css/ribbons.css">
<link rel="stylesheet" href="../../assets/css/users-module.css">

<!-- USERS MODULE -->
<section id="users-section" class="users-module">

  <div class="users-container">

    <!-- HEADER -->
    <header class="users-header">
      <h2>Usuários do Sistema</h2>

      <button id="btn-add-user" class="btn-primary">
        <i class="fa-solid fa-user-plus"></i>
        <span>Novo Usuário</span>
      </button>
    </header>

    <!-- TABLE -->
    <div class="users-table-wrapper">

      <table class="users-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuário</th>
            <th>Nome</th>
            <th>E-mail</th>
            <th>Tipo</th>
            <th>Status</th>
            <th colspan="2">Ações</th>
          </tr>
        </thead>

        <tbody>
          <!-- JS render -->
        </tbody>

      </table>

    </div>

    <!-- EMPTY STATE -->
    <div id="users-empty" class="users-empty" style="display:none;">
      Nenhum usuário encontrado.
    </div>

    <!-- FORM MODAL -->
    <div id="user-form-container" class="modal" style="display:none;">

      <div class="modal-content">

        <header class="modal-header">
          <h3 id="user-form-title">Novo Usuário</h3>
        </header>

        <form id="user-form" class="form">

          <input type="hidden" id="user-id" />

          <!-- USERNAME -->
          <div class="form-group">
            <label for="user-username">Usuário</label>
            <input
              id="user-username"
              type="text"
              autocomplete="off"
              required />
          </div>

          <!-- PASSWORD -->
          <div class="form-group">
            <label for="user-password">Senha</label>
            <input
              id="user-password"
              type="password"
              autocomplete="new-password" />
          </div>

          <!-- NAME -->
          <div class="form-group">
            <label for="user-name">Nome</label>
            <input id="user-name" type="text" autocomplete="name" required />
          </div>

          <!-- PHONE -->
          <div class="form-group">
            <label for="user-phone">Telefone</label>
            <input id="user-phone" type="tel" autocomplete="tel" />
          </div>

          <!-- EMAIL -->
          <div class="form-group">
            <label for="user-email">E-mail</label>
            <input id="user-email" type="email" autocomplete="email" required />
          </div>

          <!-- BROKER DEFAULT COMMISSION -->
          <div class="form-group broker-default-commission-field" style="display:none;">
            <label for="user-broker-default-commission">Comissão padrão do corretor (%)</label>
            <input
              id="user-broker-default-commission"
              type="number"
              min="0"
              max="100"
              step="0.01"
              placeholder="Vazio = NULL" />
          </div>

          <!-- ROLE -->
          <div class="form-group">
            <label for="user-role">Tipo</label>
            <select id="user-role">
              <option value="user">Usuário</option>
              <option value="admin">Administrador</option>
            </select>
          </div>

          <!-- ACTIONS -->
          <div class="form-actions">

            <button type="submit" class="btn-primary">
              Salvar
            </button>

            <button type="button" id="btn-cancel-user" class="btn-secondary">
              Cancelar
            </button>

          </div>

        </form>

      </div>

    </div>

  </div>

</section>

<?php
$content = ob_get_clean();
echo $content;
?>