<?php
require_once __DIR__ . '/config.php';
$res = uazapi_api('/instance/all', [], 'GET');
echo "UAZAPI RESPONSE (ALL INSTANCES):\n";
print_r($res);
