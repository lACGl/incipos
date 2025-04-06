<?php
// Yardımcı fonksiyonları dahil et
if (!function_exists('sendResponse')) {
    require_once __DIR__ . '/helpers.php';
}

// API isteğini logla
if (!isset($log_file)) {
    $log_file = __DIR__ . '/api_log.txt';
}
file_put_contents($log_file, date('Y-m-d H:i:s') . " - paymentMethods endpoint çalıştırılıyor\n", FILE_APPEND);

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

// Ödeme yöntemleri - Tam olarak Birfatura'nın beklediği formatla
$response = [
    "PaymentMethods" => [
        [
            "Id" => 1,
            "Value" => "Nakit"
        ],
        [
            "Id" => 2,
            "Value" => "Kredi Kartı"
        ]
    ]
];

file_put_contents($log_file, date('Y-m-d H:i:s') . " - Ödeme yöntemleri hazırlandı\n", FILE_APPEND);
sendResponse($response);
?>