<?php
// Hata ayıklama
ini_set('display_errors', 1);
error_reporting(E_ALL);

// API isteğini logla
$log_file = __DIR__ . '/api_log.txt';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Gelen istek: " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

// Headerları al
$headers = getAllHeaders();
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Headers: " . print_r($headers, true), FILE_APPEND);

// Request URI'yi işle
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

file_put_contents($log_file, date('Y-m-d H:i:s') . " - İşlenen path: " . $path . "\n", FILE_APPEND);

// Post verilerini logla
$post_data = file_get_contents('php://input');
file_put_contents($log_file, date('Y-m-d H:i:s') . " - POST data: " . $post_data . "\n", FILE_APPEND);

// URL yapısını analiz et ve endpoint'i belirle
if (strpos($path, 'orderStatus') !== false) {
    require_once __DIR__ . '/orderStatus.php';
} elseif (strpos($path, 'paymentMethods') !== false) {
    require_once __DIR__ . '/paymentMethods.php';
} elseif (strpos($path, 'orders') !== false && strpos($path, 'orderStatus') === false && strpos($path, 'orderCargoUpdate') === false) {
    require_once __DIR__ . '/orders.php';
} elseif (strpos($path, 'orderCargoUpdate') !== false) {
    require_once __DIR__ . '/orderCargoUpdate.php';
} elseif (strpos($path, 'invoiceLinkUpdate') !== false) {
    require_once __DIR__ . '/invoiceLinkUpdate.php';
} elseif (strpos($path, 'stockUpdate') !== false) {
    // Yeni eklenen stock update endpoint'i
    require_once __DIR__ . '/stockUpdate.php';
} else {
    // Varsayılan olarak sipariş durumlarını göster
    require_once __DIR__ . '/orderStatus.php';
}
?>