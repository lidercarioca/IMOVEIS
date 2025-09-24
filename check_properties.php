<?php
require_once '../config/database.php';

$sql = "SELECT id, title, type, status FROM properties WHERE status != 'inactive'";
$result = $pdo->query($sql);

header('Content-Type: application/json');
echo json_encode($result->fetchAll(), JSON_PRETTY_PRINT);
?>
