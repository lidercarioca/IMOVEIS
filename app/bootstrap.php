<?php
// Carregar a página requisitada
$currentPage = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
loadPage($currentPage);