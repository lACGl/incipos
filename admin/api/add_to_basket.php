<?php
// Sepete ürün eklemek için API
session_start();
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// POST verilerini al
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Hata ayıklama
error_log("Sepete eklenmeye çalışılan veri: " . print_r($data, true));

// Veri kontrolü
if (!$data || !isset($data['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz veri formatı']);
    exit;
}

// Girdi verilerini düzenle
$product_id = intval($data['product_id']);
$product_name = isset($data['product_name']) ? $data['product_name'] : '';
$price = isset($data['price']) ? floatval($data['price']) : 0;
$quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
$sales_price = isset($data['sales_price']) ? floatval($data['sales_price']) : 0;

// Eksik veriler için veritabanından tamamlama yapabiliriz
if (empty($product_name) || $price <= 0) {
    require_once '../db_connection.php';
    try {
        $query = "SELECT ad, alis_fiyati, satis_fiyati FROM urun_stok WHERE id = :id AND durum = 'aktif'";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':id', $product_id);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            if (empty($product_name)) $product_name = $product['ad'];
            if ($price <= 0) $price = $product['alis_fiyati'];
            if ($sales_price <= 0) $sales_price = $product['satis_fiyati'];
        }
    } catch (PDOException $e) {
        error_log("Veritabanı hatası: " . $e->getMessage());
    }
}

// Geçerlilik kontrolü
if ($product_id <= 0 || $price <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz değerler. Pozitif sayılar girmelisiniz.']);
    exit;
}

// Session değişkenini oluştur veya mevcut olanı kullan
if (!isset($_SESSION['purchase_order_basket']) || !is_array($_SESSION['purchase_order_basket'])) {
    $_SESSION['purchase_order_basket'] = [];
}

// Ürün zaten sepette var mı kontrol et
$found = false;
foreach ($_SESSION['purchase_order_basket'] as $key => $item) {
    if ($item['id'] == $product_id) {
        $_SESSION['purchase_order_basket'][$key]['quantity'] += $quantity;
        $found = true;
        break;
    }
}

// Eğer ürün sepette yoksa ekle
if (!$found) {
    $_SESSION['purchase_order_basket'][] = [
        'id' => $product_id,
        'name' => $product_name,
        'quantity' => $quantity,
        'price' => $price,
        'sales_price' => $sales_price
    ];
}

// Güncel sepet içeriğini kaydet
$_SESSION['purchase_order_basket'] = array_values($_SESSION['purchase_order_basket']);

// Sepet içeriğini logla
error_log("Güncel sepet içeriği: " . json_encode($_SESSION['purchase_order_basket']));

// Sepetteki ürün sayısını hesapla
$basket_count = count($_SESSION['purchase_order_basket']);

// Başarı yanıtı döndür
echo json_encode([
    'success' => true, 
    'message' => 'Ürün sepete eklendi',
    'basket_count' => $basket_count,
    'product_name' => $product_name,
    'basket' => $_SESSION['purchase_order_basket']
]);
?>