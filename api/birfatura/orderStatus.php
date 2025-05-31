<?php
// Yardımcı fonksiyonları dahil et
if (!function_exists('sendResponse')) {
    require_once __DIR__ . '/helpers.php';
}

// API isteğini logla
if (!isset($log_file)) {
    $log_file = __DIR__ . '/api_log.txt';
}
file_put_contents($log_file, date('Y-m-d H:i:s') . " - orderStatus endpoint çalıştırılıyor\n", FILE_APPEND);

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

// Sipariş durumları - Tam olarak Birfatura'nın beklediği formatla
$response = [
    "OrderStatus" => [
        [
            "Id" => 1,
            "Value" => "Onaylandı"
        ]
    ]
];

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Sipariş durumları hazırlandı\n", FILE_APPEND);
sendResponse($response);
?>