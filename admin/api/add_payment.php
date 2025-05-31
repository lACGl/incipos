<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // POST verileri kontrolü
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['borc_id']) || !isset($data['odeme_tutari']) || !isset($data['odeme_tarihi'])) {
        throw new Exception('Eksik veya geçersiz veri');
    }
    
    $borc_id = (int)$data['borc_id'];
    $odeme_tutari = (float)$data['odeme_tutari'];
    $odeme_tarihi = $data['odeme_tarihi'];
    $odeme_yontemi = isset($data['odeme_yontemi']) ? $data['odeme_yontemi'] : 'nakit';
    $aciklama = isset($data['aciklama']) ? $data['aciklama'] : null;
    
    // Kullanıcı ID'sini doğrulama
    $kullanici_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Kullanıcı ID'sinin personel tablosunda var olup olmadığını kontrol et
    if ($kullanici_id !== null) {
        $stmt = $conn->prepare("SELECT id FROM personel WHERE id = ?");
        $stmt->execute([$kullanici_id]);
        if (!$stmt->fetch()) {
            // Veritabanında bu ID ile personel yoksa null olarak ayarla
            $kullanici_id = null;
        }
    }
    
    // Borç kontrolü
    $stmt = $conn->prepare("
        SELECT b.*, 
               COALESCE((SELECT SUM(o.odeme_tutari) FROM musteri_borc_odemeler o WHERE o.borc_id = b.borc_id), 0) as odenen_tutar
        FROM musteri_borclar b
        WHERE b.borc_id = ?
    ");
    $stmt->execute([$borc_id]);
    $borc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$borc) {
        throw new Exception('Borç kaydı bulunamadı');
    }
    
    // Kalan borç hesapla - indirim tutarını düş
    $odenen_tutar = (float)$borc['odenen_tutar'];
    $net_tutar = (float)$borc['toplam_tutar'] - (float)$borc['indirim_tutari']; 
    $kalan_borc = $net_tutar - $odenen_tutar;
    
    // Ödeme tutarı kontrolü
    if ($odeme_tutari <= 0) {
        throw new Exception('Ödeme tutarı pozitif olmalıdır');
    }
    
    if ($odeme_tutari > $kalan_borc) {
        throw new Exception('Ödeme tutarı kalan borçtan fazla olamaz. Kalan borç: ' . number_format($kalan_borc, 2));
    }
    
    // İşlemi başlat
    $conn->beginTransaction();
    
    // Ödeme kaydını ekle
    $stmt = $conn->prepare("
        INSERT INTO musteri_borc_odemeler (
            borc_id, 
            odeme_tutari, 
            odeme_tarihi, 
            odeme_yontemi, 
            aciklama, 
            kullanici_id
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $borc_id,
        $odeme_tutari,
        $odeme_tarihi,
        $odeme_yontemi,
        $aciklama,
        $kullanici_id // Doğrulanmış kullanıcı ID veya null
    ]);
    
    // Borç tamamen ödendi mi kontrol et
    $yeni_odenen = $odenen_tutar + $odeme_tutari;
    $odendi_mi = ($yeni_odenen >= $net_tutar) ? 1 : 0;
    
    // Borç durumunu güncelle
    $stmt = $conn->prepare("UPDATE musteri_borclar SET odendi_mi = ? WHERE borc_id = ?");
    $stmt->execute([$odendi_mi, $borc_id]);
    
    $conn->commit();
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'message' => 'Ödeme başarıyla kaydedildi',
        'odendi_mi' => $odendi_mi,
        'kalan_borc' => round($net_tutar - $yeni_odenen, 2)
    ]);
    
} catch (PDOException $e) {
    // Veritabanı hatası
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
    
} catch (Exception $e) {
    // Genel hata
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}