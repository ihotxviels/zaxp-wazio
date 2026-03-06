<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

$stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'crm_instances'");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['column_name'] . " (" . $r['data_type'] . ")\n";
}
