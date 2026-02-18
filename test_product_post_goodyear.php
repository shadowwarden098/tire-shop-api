<?php

$url = "http://localhost:8000/api/products";

$data = [
    "name" => "Goodyear EfficientGrip Performance",
    "brand" => "Goodyear",
    "model" => "EfficientGrip Performance",
    "size" => "195/65R15",
    "category" => "Automóvil",
    "cost_usd" => 75.00,
    "price_usd" => 110.00,
    "stock" => 25,
    "min_stock" => 8
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "✅ RESPUESTA DE LA API:\n";
var_dump($response);
echo "\n\nHTTP CODE: " . $httpCode . "\n";
