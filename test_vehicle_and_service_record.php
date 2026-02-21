<?php

// ─────────────────────────────────────────
// PASO 1: Crear vehículo para el cliente id:1
// ─────────────────────────────────────────
echo "=== PASO 1: Crear vehículo ===\n";

$ch = curl_init("http://localhost:8000/api/vehicles");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        "customer_id"  => 1,
        "plate"        => "ABC-123",
        "brand"        => "Toyota",
        "model"        => "Corolla",
        "year"         => 2020,
        "color"        => "Blanco",
        "tire_size"    => "205/55R16",
        "vehicle_type" => "Automóvil",
        "mileage"      => 35000
    ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "HTTP CODE: $httpCode\n\n";

$vehicleData = json_decode($response, true);
$vehicleId = $vehicleData['data']['id'] ?? 1;

// ─────────────────────────────────────────
// PASO 2: Registrar servicio
// ─────────────────────────────────────────
echo "=== PASO 2: Registrar servicio (vehicle_id: $vehicleId) ===\n";

$ch = curl_init("http://localhost:8000/api/service-records");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS     => json_encode([
        "customer_id"    => 1,
        "vehicle_id"     => $vehicleId,
        "service_id"     => 2, // Balanceo de Llantas
        "price_pen"      => 40.00,
        "payment_method" => "efectivo",
        "notes"          => "Balanceo de 4 llantas",
        "mileage"        => 35000
    ]),
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
echo "HTTP CODE: $httpCode\n";