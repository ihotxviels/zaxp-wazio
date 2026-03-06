<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "--- DEBUG UAZAPI INSTANCES ---\n";
if (!defined('WAZIO_BASE'))
    die("WAZIO_BASE NOT DEFINED\n");
if (!defined('WAZIO_TOKEN'))
    die("WAZIO_TOKEN NOT DEFINED\n");

echo "Base URL: " . WAZIO_BASE . "\n";
echo "Token: " . mask_token(WAZIO_TOKEN) . "\n";

$res = uazapi_api('/instance/all', [], 'GET');

if (isset($res['erro'])) {
    echo "API ERROR: " . $res['erro'] . "\n";
    if (isset($res['raw'])) {
        echo "RAW RESPONSE:\n" . $res['raw'] . "\n";
    }
} else {
    echo "API SUCCESS\n";
    $fmt = extrair_instancias($res);
    echo "Count Extracted: " . count($fmt) . "\n";
    foreach ($fmt as $i) {
        echo "- " . $i['name'] . " | " . $i['status'] . " | " . ($i['token'] ? 'Has Token' : 'No Token') . "\n";
    }

    if (count($fmt) === 0) {
        echo "\nFull Result Structure:\n";
        print_r($res);
    }
}
