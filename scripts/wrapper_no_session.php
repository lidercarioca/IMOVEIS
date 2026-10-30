<?php
require_once 'C:/xampp/htdocs/config/session_manager.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'C:/xampp/htdocs/api/v1/leads/getInactiveLeads.php';
