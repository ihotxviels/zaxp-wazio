<?php
$url = 'https://zapx1analytics.wazio.com/instance/all';
$token = 'CLa2LD2E1AtLWWgw1HiCjvFxj3LAipXjBKizJRiQybEs5lM1mz';

echo "Testing URL: $url\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        'token: ' . $token,
        'admintoken: ' . $token
    ]
]);

$res = curl_exec($ch);
$err = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "HTTP CODE: " . $info['http_code'] . "\n";
if ($err)
    echo "CURL ERROR: $err\n";
echo "RESPONSE: " . substr($res, 0, 500) . "...\n";
