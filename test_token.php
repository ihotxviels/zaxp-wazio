<?php
require_once 'config.php';

echo "<h1>Deep Token & Instance Validation</h1>";
$res = uazapi_api("/instance/all", [], 'GET');

if (!is_array($res) || isset($res['code'])) {
    echo "<h2 style='color:red;'>FAILED TO FETCH INSTANCES</h2>";
    echo "<pre>";
    print_r($res);
    echo "</pre>";
    exit;
}

echo "<h2>Found " . count($res) . " instances in UAZAPI:</h2>";
echo "<table border='1' style='border-collapse:collapse; width:100%'>";
echo "<tr><th>Name</th><th>Status</th><th>Owner</th><th>Chat Count (Total/Unread)</th></tr>";

foreach ($res as $inst) {
    $name = $inst['instanceName'] ?? ($inst['name'] ?? 'N/A');
    $status = $inst['status'] ?? ($inst['connectionStatus'] ?? 'N/A');
    $owner = $inst['owner'] ?? ($inst['number'] ?? 'N/A');
    $token = $inst['instanceToken'] ?? ($inst['token'] ?? ($inst['hash'] ?? ''));

    // Tenta buscar estatísticas de chat para cada uma
    $chats = uazapi_api("/chat/find", ['instanceName' => $name], 'POST', $token);
    $total = $chats['totalChatsStats']['total_chats']['total'] ?? '??';
    $unread = $chats['totalChatsStats']['total_chats']['unread'] ?? '??';
    $chatListCount = isset($chats['chats']) ? count($chats['chats']) : (isset($chats['data']) ? count($chats['data']) : 'NO_KEY');

    echo "<tr>";
    echo "<td>$name</td>";
    echo "<td>$status</td>";
    echo "<td>$owner</td>";
    echo "<td>$total / $unread (List: $chatListCount)</td>";
    echo "</html>";
}
echo "</table>";
