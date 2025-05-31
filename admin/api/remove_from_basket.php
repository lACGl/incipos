<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Ürün ID kontrolü
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz ürün ID']);
    exit;
}

$product_id = intval($_GET['id']);

// Session değişkenini kontrol et
if (!isset($_SESSION['purchase_order_basket']) || !is_array($_SESSION['purchase_order_basket'])) {
    $_SESSION['purchase_order_basket'] = [];
    echo json_encode(['success' => true, 'message' => 'Sepet zaten boş', 'basket_count' => 0, 'basket' => []]);
    exit;
}

// Ürünü sepetten kaldır
$found = false;
foreach ($_SESSION['purchase_order_basket'] as $key => $item) {
    if ($item['id'] == $product_id) {
        unset($_SESSION['purchase_order_basket'][$key]);
        $found = true;
        break;
    }
}

// Dizi anahtarlarını yeniden indeksle
$_SESSION['purchase_order_basket'] = array_values($_SESSION['purchase_order_basket']);

// Sepetteki ürün sayısını hesapla
$basket_count = count($_SESSION['purchase_order_basket']);

// Başarı yanıtı döndür
echo json_encode([
    'success' => true, 
    'message' => $found ? 'Ürün sepetten kaldırıldı' : 'Ürün sepette bulunamadı',
    'basket_count' => $basket_count,
    'basket' => $_SESSION['purchase_order_basket']
]);
?>