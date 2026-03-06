<?php
require 'config.php';
$pdo = get_db_connection();
$stmt = $pdo->query('SELECT instance_name FROM crm_instances');
foreach ($stmt->fetchAll() as $r) {
    echo $r['instance_name'] . PHP_EOL;
}
