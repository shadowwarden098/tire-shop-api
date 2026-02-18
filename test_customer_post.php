<?php

$url = "http://localhost:8000/api/customers";

$data = [
    "name" => "Juan Pérez García",
    "document_type" => "DNI",
    "document_number" => "12345678",
    "phone" => "987654321",
    "email" => "juan@example.com",
    "address" => "Av. Principal 123",
    "city" => "Lima",
    "district" => "San Isidro",
    "customer_type" => "individual"
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
var_dump($response);
echo "\nHTTP CODE: " . $httpCode;
