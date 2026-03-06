<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

$stmt = $pdo->query("SELECT instance_name, last_checked FROM crm_instances ORDER BY last_checked DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "LATEST UPDATES:\n";
foreach ($rows as $r) {
    echo "- " . $r['instance_name'] . ": " . $r['last_checked'] . "\n";
}
