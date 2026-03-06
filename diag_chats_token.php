<?php
require_once 'config.php';

echo "<h1>Diagnostic: Chat Retrieval with Instance Token</h1>";

$pdo = get_db_connection();
$stmt = $pdo->query("SELECT instance_name, instance_token, status FROM crm_instances WHERE status = 'connected' OR status = 'open' LIMIT 5");
$insts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($insts)) {
    echo "No connected instances found in DB.<br>";
    // Sincroniza primeiro
    echo "Synchronizing...<br>";
    sync_all_uazapi_instances(true);
    $stmt = $pdo->query("SELECT instance_name, instance_token, status FROM crm_instances WHERE status = 'connected' OR status = 'open' LIMIT 5");
    $insts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

foreach ($insts as $inst) {
    $name = $inst['instance_name'];
    $token = $inst['instance_token'];
    echo "<h2>Testing Instance: $name</h2>";
    echo "Token: $token<br>";

    // Test POST with Instance Token
    echo "<h3>POST /chat/find (with Instance Token)</h3>";
    $res = uazapi_api("/chat/find", ['instanceName' => $name], 'POST', $token);
    echo "<pre>";
    print_r($res);
    echo "</pre>";

    // Test GET with Instance Token
    echo "<h3>GET /chat/find (with Instance Token)</h3>";
    $resGet = uazapi_api("/chat/find?instanceName=$name", [], 'GET', $token);
    echo "<pre>";
    print_r($resGet);
    echo "</pre>";
}
