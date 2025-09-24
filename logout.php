<?php
// Headers de segurança
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; connect-src \'self\' https://ka-f.fontawesome.com https://cdnjs.cloudflare.com https://*.jsdelivr.net https://cdn.jsdelivr.net;');

session_start();
session_destroy();
header("Location: login.php");
exit;
?>
