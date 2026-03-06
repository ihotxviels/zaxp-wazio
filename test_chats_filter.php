<?php
require_once 'config.php';

echo "<h1>Chat Filter Diagnostic (v3)</h1>";

// Tenta pegar a instância do usuário ou a primeira do banco
$instanceName = '03_chip_campanha';
$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT instance_token FROM crm_instances WHERE instance_name = ?");
$stmt->execute([$instanceName]);
$token = $stmt->fetchColumn();

if (!$token) {
    echo "Instance $instanceName not found in DB. Searching for ANY connected instance...<br>";
    $stmt = $pdo->query("SELECT instance_name, instance_token FROM crm_instances WHERE (status = 'connected' OR status = 'open') LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $instanceName = $row['instance_name'];
        $token = $row['instance_token'];
    }
}

if (!$token) {
    echo "No valid instances/tokens found.";
    exit;
}

echo "<h2>Testing Instance: $instanceName</h2>";

$tests = [
    "Default (Empty Array)" => [],
    "Where Empty Array" => ['where' => []],
    "Non-Archived" => ['where' => ['archive' => false]],
    "Archived Only" => ['where' => ['archive' => true]],
    "All with Limit" => ['limit' => 100]
];

foreach ($tests as $label => $body) {
    echo "<h3>Test: $label</h3>";
    $res = uazapi_api("/chat/find", $body, 'POST', $token);

    if (isset($res['chats'])) {
        echo "Count (chats): " . count($res['chats']) . "<br>";
        if (count($res['chats']) > 0) {
            echo "Example ID: " . ($res['chats'][0]['id'] ?? 'N/A') . "<br>";
        }
    } elseif (isset($res['data'])) {
        echo "Count (data): " . count($res['data']) . "<br>";
    } else {
        echo "Response Error: " . ($res['message'] ?? 'Unknown Error') . "<br>";
        echo "<pre>" . substr(print_r($res, true), 0, 500) . "</pre>";
    }
}
