<?php

session_start();
require_once '../db_connection.php';

// CORS ayarları
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = [
    'success' => false,
    'message' => '',
    'product' => null
];

// Arama terimini kontrol et
if (isset($_GET['term']) && !empty($_GET['term'])) {
    $term = trim($_GET['term']);
    
    try {
        // Bugünün tarihini al
        $now = date('Y-m-d');
        
        // Ürün ara (barkod, kod veya ad ile)
        $query = "SELECT * FROM urun_stok 
                  WHERE (barkod LIKE :term OR kod LIKE :term OR ad LIKE :term)
                  AND durum = 'aktif'
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $like_term = "%{$term}%";
        $stmt->bindParam(':term', $like_term);
        $stmt->execute();
        
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // İndirim kontrolü
            if ($product['indirimli_fiyat'] != null && 
                $product['indirim_baslangic_tarihi'] != null && 
                $product['indirim_bitis_tarihi'] != null && 
                $now >= $product['indirim_baslangic_tarihi'] && 
                $now <= $product['indirim_bitis_tarihi']) {
                
                // Ürünün güncel indirimli fiyatı
                $product['active_price'] = $product['indirimli_fiyat'];
                $product['has_discount'] = true;
                $product['discount_rate'] = round(100 - (($product['indirimli_fiyat'] / $product['satis_fiyati']) * 100), 2);
            } else {
                // İndirim yok veya süresi geçmiş, normal fiyat
                $product['active_price'] = $product['satis_fiyati'];
                $product['has_discount'] = false;
                $product['discount_rate'] = 0;
            }
            
            $response['success'] = true;
            $response['product'] = $product;
        } else {
            $response['message'] = 'Ürün bulunamadı';
        }
    } catch (PDOException $e) {
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Geçersiz istek. Arama terimi belirtilmeli.';
}

// JSON yanıtı döndür
echo json_encode($response);
?>