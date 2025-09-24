<?php
require_once 'config/database.php';

$sql = "SELECT type, COUNT(*) as total FROM properties GROUP BY type";
$result = $pdo->query($sql);

header('Content-Type: application/json');
echo json_encode($result->fetchAll(), JSON_PRETTY_PRINT);
?>
