<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

$stmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'crm_instances'");
echo "COLUMNS:\n";
while ($row = $stmt->fetchColumn()) {
    echo "- $row\n";
}
echo "DONE\n";
