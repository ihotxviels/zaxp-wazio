<?php
require_once __DIR__ . '/config.php';

$output = "LOG DE TESTE DB SYNC - " . date('Y-m-d H:i:s') . "\n";
$output .= "--------------------------------------------------\n";

try {
    $pdo = get_db_connection();
    if ($pdo) {
        $output .= "DB Connected Successfully!\n";

        // Test query
        $stmt = $pdo->query("SELECT current_user, current_database()");
        $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $output .= "DB Info: " . json_encode($dbInfo) . "\n";

        $output .= "Running sync_all_uazapi_instances()...\n";
        $res = sync_all_uazapi_instances();

        if ($res['ok']) {
            $output .= "Sync Status: SUCCESS\n";
            $output .= "Instances Found: " . $res['count'] . "\n";
            $output .= "Stats: " . json_encode($res['stats']) . "\n";

            // Verifying the table content
            $stmt = $pdo->query("SELECT instance_name, status, last_checked FROM crm_instances LIMIT 5");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $output .= "Sample DB Rows:\n" . json_encode($rows, JSON_PRETTY_PRINT) . "\n";

        } else {
            $output .= "Sync Status: FAILED\n";
            $output .= "Error: " . ($res['erro'] ?? 'Unknown error') . "\n";
        }
    } else {
        $output .= "DB Connection Failed (get_db_connection returned null).\n";
    }
} catch (Exception $e) {
    $output .= "EXCEPTION: " . $e->getMessage() . "\n";
}

file_put_contents(__DIR__ . '/test_db_sync_log.txt', $output);
echo "Test completed. Check test_db_sync_log.txt\n";
