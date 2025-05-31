<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Session değişkenini kontrol et
if (!isset($_SESSION['purchase_order_basket']) || !is_array($_SESSION['purchase_order_basket'])) {
    $_SESSION['purchase_order_basket'] = [];
}

// Sepetteki ürün sayısını hesapla
$basket_count = count($_SESSION['purchase_order_basket']);

// Başarı yanıtı döndür
echo json_encode([
    'success' => true, 
    'basket_count' => $basket_count,
    'basket' => $_SESSION['purchase_order_basket']
]);
?>