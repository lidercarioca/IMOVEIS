<?php

/**
 * Partial compartilhado para a página pública de tenant indisponível.
 * Este arquivo não deve inicializar sessão, banco, bootstrap ou resolver tenant.
 */
$tenantName = isset($tenantName) ? (string) $tenantName : 'Empresa';
?>
<div style="max-width:720px;margin:60px auto;padding:30px;background:#fff3cd;border:1px solid #ffeeba;color:#856404;border-radius:8px;font-family:Segoe UI, Arial, sans-serif;">
    <h1 style="margin-top:0;">Site temporariamente indisponível</h1>
    <p>A empresa <strong><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></strong> está desativada.</p>
    <p>Por favor, contate o administrador ou superadmin para reativação.</p>
</div>