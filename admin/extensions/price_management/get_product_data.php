<?php
/**
 * Ürün verilerini getiren AJAX dosyası
 * Kod veya barkod ile ürün bilgilerini JSON formatında döndürür
 */

// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';

// Session kontrolü
checkUserSession();

// Veritabanı bağlantısı
require_once '../../db_connection.php';

// Sonuç dizisi
$result = [
    'success' => false,
    'message' => '',
    'product' => null
];

// Ürün kodu veya barkodu kontrolü
if (isset($_GET['kod']) && !empty($_GET['kod'])) {
    $kod = trim($_GET['kod']);
    
    try {
        // Ürün bilgilerini getir
        $stmt = $conn->prepare("
            SELECT 
                id, kod, barkod, ad, alis_fiyati, satis_fiyati, 
                departman_id, ana_grup_id, alt_grup_id
            FROM urun_stok
            WHERE (kod = :kod OR barkod = :kod) AND durum = 'aktif'
            LIMIT 1
        ");
        $stmt->bindParam(':kod', $kod);
        $stmt->execute();
        
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            // Kategori bilgilerini getir
            $departman_adi = '';
            $ana_grup_adi = '';
            $alt_grup_adi = '';
            
            if ($product['departman_id']) {
                $stmt = $conn->prepare("SELECT ad FROM departmanlar WHERE id = ?");
                $stmt->execute([$product['departman_id']]);
                $departman_adi = $stmt->fetchColumn();
            }
            
            if ($product['ana_grup_id']) {
                $stmt = $conn->prepare("SELECT ad FROM ana_gruplar WHERE id = ?");
                $stmt->execute([$product['ana_grup_id']]);
                $ana_grup_adi = $stmt->fetchColumn();
            }
            
            if ($product['alt_grup_id']) {
                $stmt = $conn->prepare("SELECT ad FROM alt_gruplar WHERE id = ?");
                $stmt->execute([$product['alt_grup_id']]);
                $alt_grup_adi = $stmt->fetchColumn();
            }
            
            // Kategori bilgilerini ekle
            $product['departman_adi'] = $departman_adi;
            $product['ana_grup_adi'] = $ana_grup_adi;
            $product['alt_grup_adi'] = $alt_grup_adi;
            
            $result['success'] = true;
            $result['product'] = $product;
        } else {
            $result['message'] = 'Ürün bulunamadı.';
        }
    } catch (PDOException $e) {
        $result['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
} else {
    $result['message'] = 'Geçerli bir ürün kodu veya barkod girilmedi.';
}

// JSON olarak yanıt ver
header('Content-Type: application/json');
echo json_encode($result);
?>