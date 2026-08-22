<?php
// Endpoint untuk menyajikan Code.gs payload ke browser Apps Script
header('Content-Type: text/plain');
header('Access-Control-Allow-Origin: *');
$f = __DIR__ . '/code_payload.txt';
if (file_exists($f)) {
    echo file_get_contents($f);
} else {
    http_response_code(404);
    echo 'Not found';
}
?>
