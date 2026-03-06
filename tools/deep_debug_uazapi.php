<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "--- DEEP DEBUG UAZAPI ---\n";
$endpoint = '/instance/all';
$url = WAZIO_BASE . '/' . ltrim($endpoint, '/');
$token = WAZIO_TOKEN;

echo "URL: $url\n";
echo "Token (masked): " . substr($token, 0, 8) . "...\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'token: ' . $token,
        'admintoken: ' . $token
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_VERBOSE => true // Show verbose output
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$errno = curl_errno($ch);
$info = curl_getinfo($ch);

curl_close($ch);

echo "\n--- CURL INFO ---\n";
echo "Errno: $errno\n";
echo "Error: $error\n";
echo "HTTP Code: " . $info['http_code'] . "\n";
echo "Content Type: " . $info['content_type'] . "\n";

echo "\n--- RESPONSE ---\n";
if ($response === false) {
    echo "FAILED TO GET RESPONSE\n";
} else {
    echo "Size: " . strlen($response) . " bytes\n";
    echo "Body: " . substr($response, 0, 1000) . (strlen($response) > 1000 ? '...' : '') . "\n";
}
