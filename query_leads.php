<?php
$conn = new mysqli('localhost', 'root', '', '');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$result = $conn->query('SHOW DATABASES');
while ($row = $result->fetch_array()) {
    $db_name = $row[0];
    if ($db_name !== 'information_schema' && $db_name !== 'mysql' && $db_name !== 'performance_schema' && $db_name !== 'phpmyadmin' && $db_name !== 'test') {
        $conn->select_db($db_name);
        $table_check = $conn->query("SHOW TABLES LIKE 'leads'");
        if ($table_check && $table_check->num_rows > 0) {
            echo "Database: $db_name\n";
            $query = 'SELECT COUNT(*) as total FROM leads WHERE tenant_id = 1';
            $result_leads = $conn->query($query);
            if ($result_leads) {
                $row = $result_leads->fetch_assoc();
                echo "Total leads in tenant 1: " . $row['total'] . "\n";
            } else {
                echo "Query error: " . $conn->error . "\n";
            }
            break;
        }
    }
}
$conn->close();
?>
