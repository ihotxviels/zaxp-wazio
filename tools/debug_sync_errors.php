<?php
require_once __DIR__ . '/config.php';

$pdo = get_db_connection();
if (!$pdo)
    die("No DB connection");

$res = uazapi_api('/instance/all', [], 'GET');
$instanciasApi = extrair_instancias($res);

echo "Found " . count($instanciasApi) . " instances from API.\n";

foreach ($instanciasApi as $i) {
    try {
        $name = $i['name'];
        $token = $i['token'];
        $status = strtolower($i['status'] ?? 'disconnected');
        $connected = ($status === 'open' || $status === 'connected');
        $number = $i['owner'] ?? '';
        $webhook = $i['webhook_url'] ?? '';

        $sql = "INSERT INTO crm_instances (instance_name, instance_token, instance_number, status, connected, webhook_url, last_checked, updated_at)
                VALUES (:name, :token, :number, :status, :conn, :webhook, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON CONFLICT (instance_name) DO UPDATE SET
                    status = EXCLUDED.status,
                    connected = EXCLUDED.connected,
                    instance_token = EXCLUDED.instance_token,
                    last_checked = CURRENT_TIMESTAMP";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':token' => $token,
            ':number' => $number,
            ':status' => $status,
            ':conn' => $connected ? 1 : 0, // Using 1/0 for boolean
            ':webhook' => $webhook
        ]);
        echo "Instance $name: SUCCESS\n";
    } catch (Exception $e) {
        echo "Instance " . $i['name'] . ": ERROR - " . $e->getMessage() . "\n";
    }
}
