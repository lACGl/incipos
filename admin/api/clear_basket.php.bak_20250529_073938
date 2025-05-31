<?php
session_start();
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Sepeti temizle
unset($_SESSION['purchase_order_basket']);
$_SESSION['purchase_order_basket'] = [];

// Başarı yanıtı döndür
echo json_encode([
    'success' => true, 
    'message' => 'Sepet başarıyla temizlendi',
    'basket_count' => 0
]);
?>