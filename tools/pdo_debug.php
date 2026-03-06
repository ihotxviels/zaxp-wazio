<?php
require_once __DIR__ . '/config.php';
$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

$res = uazapi_api('/instance/all', [], 'GET');
$instanciasApi = extrair_instancias($res);

if (empty($instanciasApi))
    die("No instances from API");

$i = $instanciasApi[0]; // Test with the first one
$name = $i['name'];
$token = $i['token'];
$status = strtolower($i['status'] ?? 'disconnected');
$connected = ($status === 'open' || $status === 'connected');
$number = $i['owner'] ?? '';
$webhook = $i['webhook_url'] ?? '';
$pxHost = '';
$pxPort = '';
$pxUser = '';
$pxPass = '';
$pxProto = 'http';

$sql = "INSERT INTO crm_instances (instance_name, instance_token, instance_number, status, connected, webhook_url, proxy_host, proxy_port, proxy_user, proxy_pass, proxy_protocol, last_checked, updated_at)
        VALUES (:name, :token, :number, :status, :conn, :webhook, :pxHost, :pxPort, :pxUser, :pxPass, :pxProto, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ON CONFLICT (instance_name) DO UPDATE SET
            status = EXCLUDED.status,
            connected = EXCLUDED.connected,
            webhook_url = CASE WHEN EXCLUDED.webhook_url != '' THEN EXCLUDED.webhook_url ELSE crm_instances.webhook_url END,
            proxy_host = CASE WHEN EXCLUDED.proxy_host != '' THEN EXCLUDED.proxy_host ELSE crm_instances.proxy_host END,
            proxy_port = CASE WHEN EXCLUDED.proxy_port != '' THEN EXCLUDED.proxy_port ELSE crm_instances.proxy_port END,
            proxy_user = CASE WHEN EXCLUDED.proxy_user != '' THEN EXCLUDED.proxy_user ELSE crm_instances.proxy_user END,
            proxy_pass = CASE WHEN EXCLUDED.proxy_pass != '' THEN EXCLUDED.proxy_pass ELSE crm_instances.proxy_pass END,
            proxy_protocol = EXCLUDED.proxy_protocol,
            instance_token = CASE WHEN EXCLUDED.instance_token != '' THEN EXCLUDED.instance_token ELSE crm_instances.instance_token END,
            instance_number = CASE WHEN EXCLUDED.instance_number != '' THEN EXCLUDED.instance_number ELSE crm_instances.instance_number END,
            last_checked = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP";

$stmt = $pdo->prepare($sql);
$success = $stmt->execute([
    ':name' => $name,
    ':token' => $token,
    ':number' => $number,
    ':status' => $status,
    ':conn' => $connected ? 1 : 0,
    ':webhook' => $webhook,
    ':pxHost' => $pxHost,
    ':pxPort' => $pxPort,
    ':pxUser' => $pxUser,
    ':pxPass' => $pxPass,
    ':pxProto' => $pxProto
]);

if (!$success) {
    echo "QUERY FAILED!\n";
    print_r($stmt->errorInfo());
} else {
    echo "QUERY SUCCESS!\n";
}
