<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
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
        
        // Arama teriminin uzunluğuna göre işlem belirle
        $termLength = strlen($term);
        
        // SQL sorgusu için değişkenler
        $conditions = [];
        $params = [];
        
        // Barkodun son 6 hanesi ile arama özelliği
        if ($termLength === 6 && is_numeric($term)) {
            // Son 6 hane ile arama (barkod sonu ile eşleşme)
            $conditions[] = "barkod LIKE :barkod_end";
            $params[':barkod_end'] = '%' . $term;
        } else {
            // Arama terimini kelimelere böl
            $keywords = explode(' ', $term);
            
            foreach ($keywords as $index => $keyword) {
                if (strlen($keyword) >= 2) { // En az 2 karakterli kelimeleri ara
                    $param_name = ":term{$index}";
                    $conditions[] = "(barkod LIKE {$param_name} OR kod LIKE {$param_name} OR ad LIKE {$param_name})";
                    $params[$param_name] = '%' . $keyword . '%';
                }
            }
        }
        
        // Koşul oluştur
        $whereClause = count($conditions) > 0 ? implode(' AND ', $conditions) : "1=0";
        
        // Ürün ara
        $query = "SELECT * FROM urun_stok 
                  WHERE {$whereClause}
                  AND durum = 'aktif'
                  ORDER BY 
                    CASE 
                        WHEN barkod = :exact_term THEN 1
                        WHEN kod = :exact_term THEN 2
                        WHEN barkod LIKE :barkod_end_exact THEN 3
                        WHEN ad LIKE :start_term THEN 4
                        ELSE 5
                    END
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        
        // Tam eşleşme ve başlangıç terimleri için parametreler
        $stmt->bindValue(':exact_term', $term, PDO::PARAM_STR);
        $stmt->bindValue(':barkod_end_exact', '%' . $term, PDO::PARAM_STR);
        $stmt->bindValue(':start_term', $term . '%', PDO::PARAM_STR);
        
        // Diğer parametreleri bağla
        foreach ($params as $param_name => $param_value) {
            $stmt->bindValue($param_name, $param_value, PDO::PARAM_STR);
        }
        
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