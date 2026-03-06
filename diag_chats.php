<?php
require_once 'config.php';

echo "<h1>Diagnostic: Chat Retrieval</h1>";

// 1. Listar instâncias para confirmar o nome
echo "<h2>1. Instâncias no Banco:</h2>";
$pdo = get_db_connection();
$stmt = $pdo->query("SELECT instance_name, status, connected FROM crm_instances");
$instancias = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($instancias);
echo "</pre>";

$instance = $_GET['instance'] ?? ($instancias[0]['instance_name'] ?? 'chip_atendimento20');
echo "Testing Instance: <b>$instance</b><br>";

// 2. Testar GET /chat/find
echo "<h2>2. Testando GET /chat/find</h2>";
$resGet = uazapi_api("/chat/find?instanceName=$instance", [], 'GET');
echo "GET Result (First 500 chars): " . substr(print_r($resGet, true), 0, 500) . "...<br>";

// 3. Testar POST /chat/find
echo "<h2>3. Testando POST /chat/find</h2>";
$resPost = uazapi_api("/chat/find", ['instanceName' => $instance], 'POST');
echo "POST Result (First 500 chars): " . substr(print_r($resPost, true), 0, 500) . "...<br>";

// 4. Testar POST /chat/find com token da instancia
echo "<h2>4. Testando POST /chat/find (with Token Sync)</h2>";
$resPostToken = uazapi_api("/chat/find", ['instanceName' => $instance], 'POST', WAZIO_TOKEN);
echo "POST Token Result: " . substr(print_r($resPostToken, true), 0, 500) . "...<br>";
