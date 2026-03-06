<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/config.php';

echo "Testing DB Connection...\n";
$pdo = get_db_connection();
if ($pdo) {
    echo "DB Connected Successfully!\n";

    echo "Running sync_all_uazapi_instances()...\n";
    $res = sync_all_uazapi_instances();
    echo "Sync Result: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";

    if (!$res['ok']) {
        echo "Sync error: " . ($res['erro'] ?? 'Unknown error') . "\n";
    } else {
        echo "Sync success! Count: " . $res['count'] . "\n";
    }
} else {
    echo "DB Connection Failed!\n";
}
