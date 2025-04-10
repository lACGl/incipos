<?php

session_start();
require_once '../db_connection.php';

// CORS ayarları
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// POST verilerini al
$input = json_decode(file_get_contents('php://input'), true);

$response = [
    'success' => false,
    'message' => '',
    'discounts' => []
];

// Ürün ID'lerini kontrol et
if (isset($input['product_ids']) && is_array($input['product_ids']) && !empty($input['product_ids'])) {
    $productIds = $input['product_ids'];
    
    try {
        // Bugünün tarihini al
        $now = date('Y-m-d');
        
        // ID listesini hazırla
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        
        // Ürünleri getir
        $query = "SELECT id, satis_fiyati, indirimli_fiyat, indirim_baslangic_tarihi, indirim_bitis_tarihi 
                 FROM urun_stok 
                 WHERE id IN ($placeholders)";
        
        $stmt = $conn->prepare($query);
        
        // Parametreleri bağla
        foreach ($productIds as $index => $productId) {
            $stmt->bindValue($index + 1, $productId, PDO::PARAM_INT);
        }
        
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $discounts = [];
        
        foreach ($products as $product) {
            $hasDiscount = false;
            $discountedPrice = null;
            
            // İndirim kontrolü
            if ($product['indirimli_fiyat'] != null && 
                $product['indirim_baslangic_tarihi'] != null && 
                $product['indirim_bitis_tarihi'] != null && 
                $now >= $product['indirim_baslangic_tarihi'] && 
                $now <= $product['indirim_bitis_tarihi']) {
                
                $hasDiscount = true;
                $discountedPrice = $product['indirimli_fiyat'];
            }
            
            $discounts[] = [
                'id' => $product['id'],
                'original_price' => $product['satis_fiyati'],
                'discounted_price' => $discountedPrice,
                'has_discount' => $hasDiscount
            ];
        }
        
        $response['success'] = true;
        $response['discounts'] = $discounts;
        
    } catch (PDOException $e) {
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Geçersiz istek. Ürün ID listesi gerekli.';
}

// JSON yanıtı döndür
echo json_encode($response);
?>