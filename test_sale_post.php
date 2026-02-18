<?php

$url = "http://localhost:8000/api/sales";

$data = [
    "customer_id" => 1,
    "items" => [
        [
            "product_id" => 1,
            "quantity" => 4,
            "unit_price_pen" => 450.00
        ]
    ],
    "payment_method" => "efectivo",
    "payment_status" => "paid"
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
