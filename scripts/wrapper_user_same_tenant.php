<?php
require_once 'C:/xampp/htdocs/config/session_manager.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['user_id'] = 100;
$_SESSION['role'] = 'user';
$_SESSION['tenant_id'] = 1;
require_once 'C:/xampp/htdocs/api/v1/leads/getInactiveLeads.php';
