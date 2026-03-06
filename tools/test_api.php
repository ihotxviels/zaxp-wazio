<?php
$urls = [
    'https://www.facebook.com/reel/25414136541590950',
    'https://www.kwai.com/@KwaiBrasilOficial/video/5246843672400170048',
    'https://www.youtube.com/shorts/QAufebJHW1A'
];

foreach ($urls as $url) {
    echo "Testing $url...\n";
    
    // Cobalt test
    $host = 'https://co.wuk.sh';
    $payload = json_encode(['url' => $url, 'vQuality' => '1080']);
    $ch = curl_init("$host/api/json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        "Origin: $host",
        "Referer: $host/",
        'User-Agent: Mozilla/5.0'
    ]);
    $res = curl_exec($ch);
    echo "COBALT: $res\n";
    
    // RYzendesu FB
    $ch = curl_init("https://api.ryzendesu.vip/api/downloader/fbdl?url=" . urlencode($url));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    echo "RYZEN FB: " . substr($res, 0, 100) . "\n";
    
    // Vreden
    $ch = curl_init("https://api.vreden.my.id/api/ytmp4?url=" . urlencode($url));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    echo "VREDEN: " . substr($res, 0, 100) . "\n\n";
}
