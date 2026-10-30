<?php
/**
 * ARQUIVO DE COMPATIBILIDADE - Carrega funções de autenticação da API
 */

// Carrega função checkApiAuth() do middleware correto
require_once __DIR__ . '/../app/middleware/api_auth.php';