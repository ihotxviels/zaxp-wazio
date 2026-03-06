/**
* WAZ.IO — Wazio Service
* Lida com o envio de mensagens diretamente para a API (Wazio/Evolution) via cURL.
*/
class WazioService
{
private $baseUrl;
private $adminToken;

public function __construct()
{
$this->baseUrl = defined('WAZIO_BASE') ? WAZIO_BASE : '';
$this->adminToken = defined('WAZIO_TOKEN') ? WAZIO_TOKEN : '';
}

/**
* Envia mensagem de texto simples
*/
public function sendText($instance, $token, $number, $text)
{
$endpoint = "{$this->baseUrl}/message/sendText";
$payload = [
'number' => $this->cleanNumber($number),
'textMessage' => ['text' => $text],
'options' => [
'delay' => 1200,
'presence' => 'composing'
]
];

return $this->request($endpoint, $token, $payload);
}

/**
* Envia mídia (Imagem, Vídeo, Áudio, Documento)
*/
public function sendMedia($instance, $token, $number, $mediaUrl, $type = 'image', $caption = '')
{
$endpoint = "{$this->baseUrl}/message/sendMedia";
$payload = [
'number' => $this->cleanNumber($number),
'mediaMessage' => [
'mediatype' => $type,
'media' => $mediaUrl,
'caption' => $caption
]
];

return $this->request($endpoint, $token, $payload);
}

/**
* Reage a uma mensagem
*/
public function sendReaction($instance, $token, $number, $messageId, $emoji)
{
$endpoint = "{$this->baseUrl}/message/reaction";
$payload = [
'number' => $this->cleanNumber($number),
'reactionMessage' => [
'key' => ['id' => $messageId],
'reaction' => $emoji
]
];

return $this->request($endpoint, $token, $payload);
}

private function cleanNumber($number)
{
$clean = preg_replace('/[^0-9]/', '', $number);
return $clean;
}

private function request($url, $token, $payload)
{
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
'Content-Type: application/json',
"token: $token"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

return [
'success' => ($httpCode >= 200 && $httpCode < 300), 'code'=> $httpCode,
    'response' => json_decode($response, true)
    ];
    }
    }