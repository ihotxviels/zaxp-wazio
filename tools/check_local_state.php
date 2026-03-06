<?php
require_once __DIR__ . '/config.php';

echo "--- LOCAL DB CHECK ---\n";
$pdo = get_db_connection();
if (!$pdo)
    die("DB Connection Failed\n");

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM crm_instances");
    echo "Count in crm_instances: " . $stmt->fetchColumn() . "\n";

    $stmt = $pdo->query("SELECT * FROM crm_instances LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);
} catch (Exception $e) {
    echo "Error querying crm_instances: " . $e->getMessage() . "\n";
}

echo "\n--- UAZAPI CONNECTIVITY CHECK ---\n";
$url = WAZIO_BASE . '/instance/all';
echo "URL: $url\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => ['token: ' . WAZIO_TOKEN, 'admintoken: ' . WAZIO_TOKEN]
]);
$res = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP Code: " . $info['http_code'] . "\n";
if ($err)
    echo "CURL Error: $err\n";
echo "Response Length: " . strlen($res) . "\n";
echo "Response Start: " . substr($res, 0, 100) . "\n";
