<?php

$url = 'http://127.0.0.1:8000/api/products';

$data = [
    "name" => "Llanta Michelin Primacy 4",
    "brand" => "Michelin",
    "model" => "Primacy 4",
    "size" => "205/55R16",
    "category" => "Automóvil",
    "cost_usd" => 85.00,
    "price_usd" => 120.00,
    "stock" => 20,
    "min_stock" => 5,
    "supplier" => "Distribuidora Lima"
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($data)
]);

$response = curl_exec($ch);

if ($response === false) {
    echo "❌ cURL ERROR:\n";
    echo curl_error($ch);
} else {
    echo "✅ RESPUESTA DE LA API:\n";
    var_dump($response);
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "\nHTTP CODE: $httpCode\n";

curl_close($ch);
