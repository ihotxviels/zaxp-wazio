<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("Falha na conexão\n");

echo "Checking crm_instances table structure:\n";
try {
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'crm_instances' ORDER BY ordinal_position");
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cols)) {
        echo "Table crm_instances NOT FOUND!\n";
    } else {
        foreach ($cols as $c) {
            echo "- " . $c['column_name'] . " (" . $c['data_type'] . ")\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
