<?php
require 'config.php';
$pdo = get_db_connection();
if (!$pdo) {
    die("Connection failed\n");
}

$output = "Connected to Server: " . DB_HOST . "\n";
$output .= "Target Database: " . DB_NAME . "\n";

$stmt = $pdo->query("SELECT current_database()");
$actualDb = $stmt->fetchColumn();
$output .= "Actual connected DB: " . $actualDb . "\n\n";

$output .= "All Tables in " . $actualDb . ":\n";
$stmt = $pdo->query("SELECT schemaname, tablename FROM pg_catalog.pg_tables WHERE schemaname NOT IN ('pg_catalog', 'information_schema') ORDER BY schemaname, tablename");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tables)) {
    $output .= "No tables found.\n";
} else {
    foreach ($tables as $t) {
        $output .= "- " . $t['schemaname'] . "." . $t['tablename'] . "\n";
    }
}

file_put_contents('db_check_result.txt', $output);
echo "Result saved to db_check_result.txt\n";
?>