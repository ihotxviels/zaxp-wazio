<?php
require_once __DIR__ . '/config.php';
$res = uazapi_api('/instance/all', [], 'GET');
if (!empty($res) && is_array($res)) {
    $first = $res[0];
    file_put_contents('uazapi_detailed.json', json_encode($first, JSON_PRETTY_PRINT));
    echo "First item saved to uazapi_detailed.json\n";

    // Also check for 'webhook' and 'proxy' in the first 5 items
    for ($i = 0; $i < min(5, count($res)); $i++) {
        echo "Item $i: " . ($res[$i]['name'] ?? 'no name') . " ";
        echo (isset($res[$i]['webhook']) ? '[Webhook OK] ' : '[No Webhook] ');
        echo (isset($res[$i]['proxy']) ? '[Proxy OK] ' : '[No Proxy] ');
        echo "\n";
    }
} else {
    echo "NO DATA\n";
}
