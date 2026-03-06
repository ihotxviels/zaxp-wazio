<?php
require_once "../config/media_api.php";

$file = $_FILES['file'] ?? [];
$tmp = $file['tmp_name'] ?? '';
$name = $file['name'] ?? 'audio.mp3';

if (!$tmp) {
    die(json_encode(['error' => 'Nenhum arquivo enviado']));
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => MEDIA_API . "/transcribe",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "file" => new CURLFile($tmp, mime_content_type($tmp), $name)
    ]
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo json_encode(['error' => "CURL Error: $err"]);
} else {
    if (json_decode($response) === null) {
        echo json_encode(['error' => 'Invalid JSON from Node', 'raw' => $response]);
    } else {
        echo $response;
    }
}
