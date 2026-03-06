<?php
require_once __DIR__ . '/config.php';
$res = uazapi_api('/instance/all', [], 'GET');
if (!empty($res) && is_array($res)) {
    $first = $res[0]['instance'] ?? $res[0];
    echo "KEYS: " . implode(', ', array_keys($first)) . "\n";
    if (isset($first['webhook'])) {
        echo "WEBHOOK: " . json_encode($first['webhook']) . "\n";
    }
    if (isset($first['proxy'])) {
        echo "PROXY: " . json_encode($first['proxy']) . "\n";
    }
} else {
    echo "NO DATA\n";
}
