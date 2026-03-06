<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
$tables = ['crm_instances', 'crm_labels', 'crm_settings', 'crm_logs'];
foreach ($tables as $t) {
    $stmt = $pdo->query("SELECT count(*) FROM information_schema.tables WHERE table_name = '$t'");
    echo "$t: " . ($stmt->fetchColumn() > 0 ? 'EXISTS' : 'MISSING') . "\n";
}
