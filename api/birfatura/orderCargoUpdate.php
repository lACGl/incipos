<?php
// Yardımcı fonksiyonları dahil et
if (!function_exists('sendResponse')) {
    require_once __DIR__ . '/helpers.php';
}

// API isteğini logla
if (!isset($log_file)) {
    $log_file = __DIR__ . '/api_log.txt';
}
file_put_contents($log_file, date('Y-m-d H:i:s') . " - orderCargoUpdate endpoint çalıştırılıyor\n", FILE_APPEND);

// Token kontrolü
$token = getFlexibleToken($headers);
$expected_token = 'KeHxXtvWK6QovGL';

if ($token !== $expected_token) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Token hatası. Beklenen: $expected_token, Gelen: $token\n", FILE_APPEND);
    sendResponse(['error' => 'Unauthorized access'], 401);
}

// POST metodu kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Method not allowed'], 405);
}

// JSON verisini al
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Kargo güncelleme isteği: " . $jsonData . "\n", FILE_APPEND);

// Gelen verileri kontrol et ve logla
$orderId = $data['orderId'] ?? null;
$orderStatusId = $data['orderStatusId'] ?? null;
$cargoTrackingCode = $data['cargoTrackingCode'] ?? null;
$cargoCompany = $data['cargoCompany'] ?? null;

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Sipariş ID: $orderId, Durum: $orderStatusId, Kargo Kodu: $cargoTrackingCode\n", FILE_APPEND);

// Başarılı yanıt
$response = [
    "success" => true,
    "message" => "Sipariş kargo bilgileri güncellendi"
];

sendResponse($response);
?>