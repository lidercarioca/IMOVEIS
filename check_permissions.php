<?php
/**
 * check_permissions.php
 * 
 * Arquivo de verificação de permissões
 * Redireciona para auth.php que contém as funções de autenticação
 * Mantido para compatibilidade com views antigas
 */

// Incluir o arquivo de autenticação que contém as funções
require_once __DIR__ . '/auth.php';

// As funções checkAuth(), checkAdmin(), isAdmin(), getUserName() 
// estão disponíveis via auth.php
?>
