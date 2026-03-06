<?php
require_once "../config/media_api.php";

$url = $_POST['url'] ?? '';

if (!$url) {
    die(json_encode(["error" => "URL inválida"]));
}

$result = callMediaAPI("/download", [
    "url" => $url
]);

if ($result === null) {
    echo json_encode(["error" => "Falha na decodificação JSON da API"]);
} else {
    echo json_encode($result);
}
