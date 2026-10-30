<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

if (file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
}

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', $projectRoot);
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', $projectRoot);
}

date_default_timezone_set('America/Sao_Paulo');
error_reporting(E_ALL);

if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}
