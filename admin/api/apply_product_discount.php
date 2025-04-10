<?php

session_start();
require_once '../db_connection.php';
require_once '../stock_functions.php';

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

// Ürün ID veya barkodu kontrol et
if (isset($_GET['id']) || isset($_GET['barkod'])) {
    $param = isset($_GET['id']) ? 'id' : 'barkod';
    $value = isset($_GET['id']) ? $_GET['id'] : $_GET['barkod'];
    
    try {
        // Ürün bilgisini getir
        $query = "SELECT * FROM urun_stok WHERE $param = :value";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':value', $value);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // İndirim kontrolü
            $now = date('Y-m-d');
            
            // İndirimli fiyat var mı ve indirim tarihi geçerli mi kontrol et
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
    $response['message'] = 'Geçersiz istek. Ürün ID veya barkod belirtilmeli.';
}

// JSON yanıtı döndür
echo json_encode($response);
?>