<?php
require 'config.php';

$resAll = uazapi_api('/instance/all', [], 'GET');
$insts = $resAll['data'] ?? $resAll['instances'] ?? $resAll;

if (!is_array($insts)) {
    echo "Fail to fetch instances:\n";
    print_r($resAll);
    exit;
}

$offlineInst = null;
foreach ($insts as $i) {
    $st = $i['status'] ?? $i['connectionStatus'] ?? $i['state'] ?? '';
    if (strtolower($st) === 'disconnected' || strtolower($st) === 'close') {
        $offlineInst = $i;
        break;
    }
}

if ($offlineInst) {
    $name = $offlineInst['name'] ?? $offlineInst['instanceName'];
    $tok = $offlineInst['token'] ?? $offlineInst['instanceToken'];
    echo "Testing offline instance: $name\n";

    // Test base /instance/connect?instance=XX
    $r1 = uazapi_api("/instance/connect?instance=$name", [], 'GET', $tok);
    print_r($r1);

    // Test evolution /instance/connect/XX
    $r2 = uazapi_api("/instance/connect/$name", [], 'GET', $tok);
    print_r($r2);

} else {
    echo "No offline instances found.\n";
}
