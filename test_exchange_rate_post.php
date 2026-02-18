<?php

$url = "http://localhost:8000/api/exchange-rate/update-api";

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json'
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "✅ RESPUESTA DE LA API:\n";
var_dump($response);

echo "\nHTTP CODE: $httpCode\n";
