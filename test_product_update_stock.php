<?php

$url = 'http://127.0.0.1:8000/api/products/3/update-stock';

$data = [
    "stock" => 5,
    "operation" => "add" // set | add | subtract
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true, // 👈 POST ES CORRECTO
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($data),
]);

$response = curl_exec($ch);

if ($response === false) {
    echo "❌ cURL ERROR:\n";
    echo curl_error($ch);
} else {
    echo "✅ RESPUESTA DE LA API:\n";
    echo $response;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "\nHTTP CODE: $httpCode\n";

curl_close($ch);
