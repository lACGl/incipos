<?php
/**
 * Ürün fiyatını güncelleyen AJAX dosyası
 * Kar marjı optimizasyonu için ürün fiyatını günceller
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
    'message' => ''
];

// POST parametrelerini kontrol et
if (isset($_POST['urun_id']) && isset($_POST['yeni_fiyat'])) {
    $urun_id = intval($_POST['urun_id']);
    $yeni_fiyat = floatval($_POST['yeni_fiyat']);
    $aciklama = isset($_POST['aciklama']) ? $_POST['aciklama'] : 'Kar marjı optimizasyonu';
    
    try {
        // Transaction başlat
        $conn->beginTransaction();
        
        // Mevcut ürün bilgilerini al
        $stmt = $conn->prepare("SELECT id, ad, satis_fiyati FROM urun_stok WHERE id = ? AND durum = 'aktif'");
        $stmt->execute([$urun_id]);
        $urun = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($urun) {
            $eski_fiyat = $urun['satis_fiyati'];
            
            // Fiyat değişmediyse işlem yapma
            if ($eski_fiyat == $yeni_fiyat) {
                $result['success'] = true;
                $result['message'] = 'Fiyat değişmedi.';
            } else {
                // Ürün fiyatını güncelle
                $update_stmt = $conn->prepare("UPDATE urun_stok SET satis_fiyati = :yeni_fiyat WHERE id = :urun_id");
                $update_stmt->bindParam(':yeni_fiyat', $yeni_fiyat);
                $update_stmt->bindParam(':urun_id', $urun_id);
                $update_stmt->execute();
                
                // Fiyat geçmişine kayıt ekle
                $log_stmt = $conn->prepare("
                    INSERT INTO urun_fiyat_gecmisi (urun_id, islem_tipi, eski_fiyat, yeni_fiyat, aciklama, kullanici_id)
                    VALUES (:urun_id, 'satis_fiyati_guncelleme', :eski_fiyat, :yeni_fiyat, :aciklama, :kullanici_id)
                ");
                $log_stmt->bindParam(':urun_id', $urun_id);
                $log_stmt->bindParam(':eski_fiyat', $eski_fiyat);
                $log_stmt->bindParam(':yeni_fiyat', $yeni_fiyat);
                $log_stmt->bindParam(':aciklama', $aciklama);
                $log_stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
                $log_stmt->execute();
                
                // Transaction tamamla
                $conn->commit();
                
                $result['success'] = true;
                $result['message'] = 'Ürün fiyatı başarıyla güncellendi.';
            }
        } else {
            $result['message'] = 'Ürün bulunamadı.';
        }
    } catch (PDOException $e) {
        // Hata durumunda rollback
        $conn->rollBack();
        $result['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
} else {
    $result['message'] = 'Gerekli parametreler eksik.';
}

// JSON olarak yanıt ver
header('Content-Type: application/json');
echo json_encode($result);
?>