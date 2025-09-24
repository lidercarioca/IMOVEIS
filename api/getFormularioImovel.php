<?php
require_once '../auth.php';
require_once '../app/security/Security.php';
Security::init();
checkAuth();

// Carrega o template do formulário
include '../views/admin/adicionar-imovel.html';
?>
