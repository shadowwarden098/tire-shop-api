<?php

$url = "http://localhost:8000/api/expenses";

$data = [
    "description"    => "Compra de llantas al proveedor",
    "category"       => "compra_inventario",
    "amount_usd"     => 1000.00,
    "payment_method" => "transferencia",
    "supplier"       => "Distribuidora XYZ",
    "expense_date"   => "2026-02-20"
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($data),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "✅ RESPUESTA DE LA API:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "HTTP CODE: $httpCode\n";