<?php
/**
 * Script para verificar os tipos de imóveis disponíveis no sistema
 * Retorna um JSON com a contagem de imóveis por tipo
 * 
 * @return JSON Array com tipos de imóveis e suas respectivas quantidades
 */

require_once 'config/database.php';

// Consulta SQL para agrupar imóveis por tipo
$sql = "SELECT type, COUNT(*) as total FROM properties GROUP BY type";
$result = $pdo->query($sql);

header('Content-Type: application/json');
echo json_encode($result->fetchAll(), JSON_PRETTY_PRINT);
?>
