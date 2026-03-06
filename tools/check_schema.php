<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

$stmt = $pdo->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'crm_instances'");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Table crm_instances structure:\n";
echo json_encode($cols, JSON_PRETTY_PRINT) . "\n";
