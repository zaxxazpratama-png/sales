<?php
$url = 'https://script.google.com/macros/s/AKfycbyBJl5txUliQQEiRHE1UpLZW9FqYb9VGNb3nApqcNVW9zoeTrgN5ZTp3qgnK16OZZ_U/exec';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$res  = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err) {
    echo "ERROR cURL: " . $err . "\n";
} else {
    echo "HTTP Code  : " . $code . "\n";
    echo "Response   : " . $res . "\n";
}
