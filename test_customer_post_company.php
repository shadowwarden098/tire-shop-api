<?php

$url = "http://localhost:8000/api/customers";

$data = [
    "name" => "Transportes Rápidos SAC",
    "document_type" => "RUC",
    "document_number" => "20123456789",
    "phone" => "014567890",
    "email" => "ventas@transportes.com",
    "address" => "Av. Industrial 567",
    "city" => "Lima",
    "district" => "Callao",
    "customer_type" => "company"
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "✅ RESPUESTA DE LA API:\n";
echo $response . "\n";
echo "HTTP CODE: " . $httpCode . "\n";
