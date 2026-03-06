<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

$stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'crm_instances'");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents(__DIR__ . '/schema_info.json', json_encode($cols, JSON_PRETTY_PRINT));
echo "Schema saved to schema_info.json\n";
