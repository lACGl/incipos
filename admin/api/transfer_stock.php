<?php
session_start();
require_once '../db_connection.php';
require_once '../helpers/stock_functions.php';

header('Content-Type: application/json');

// Yetkisiz erişimi engelle
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // JSON verilerini al
    $requestData = json_decode(file_get_contents('php://input'), true);
    
    // Gerekli tüm parametreleri kontrol et
    $requiredParams = ['urun_id', 'kaynak_tip', 'kaynak_id', 'hedef_tip', 'hedef_id', 'miktar'];
    foreach ($requiredParams as $param) {
        if (!isset($requestData[$param])) {
            throw new Exception('Eksik parametre: ' . $param);
        }
    }
    
    $urunId = $requestData['urun_id'];
    $kaynakTip = $requestData['kaynak_tip'];
    $kaynakId = $requestData['kaynak_id'];
    $hedefTip = $requestData['hedef_tip'];
    $hedefId = $requestData['hedef_id'];
    $miktar = floatval($requestData['miktar']);
    $aciklama = isset($requestData['aciklama']) ? $requestData['aciklama'] : '';
    
    if ($miktar <= 0) {
        throw new Exception('Miktar 0\'dan büyük olmalıdır');
    }
    
    // Ürün bilgilerini al
    $stmt = $conn->prepare("SELECT id, barkod, ad FROM urun_stok WHERE id = ?");
    $stmt->execute([$urunId]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$urun) {
        throw new Exception('Ürün bulunamadı');
    }
    
    // Kaynak ve Hedef isimlerini al
    $kaynakAdi = '';
    $hedefAdi = '';
    
    if ($kaynakTip === 'depo') {
        $stmt = $conn->prepare("SELECT ad FROM depolar WHERE id = ?");
        $stmt->execute([$kaynakId]);
        $kaynakAdi = $stmt->fetchColumn() ?: "Depo #$kaynakId";
    } else {
        $stmt = $conn->prepare("SELECT ad FROM magazalar WHERE id = ?");
        $stmt->execute([$kaynakId]);
        $kaynakAdi = $stmt->fetchColumn() ?: "Mağaza #$kaynakId";
    }
    
    if ($hedefTip === 'depo') {
        $stmt = $conn->prepare("SELECT ad FROM depolar WHERE id = ?");
        $stmt->execute([$hedefId]);
        $hedefAdi = $stmt->fetchColumn() ?: "Depo #$hedefId";
    } else {
        $stmt = $conn->prepare("SELECT ad FROM magazalar WHERE id = ?");
        $stmt->execute([$hedefId]);
        $hedefAdi = $stmt->fetchColumn() ?: "Mağaza #$hedefId";
    }
    
    // Transfer açıklamasını oluştur
    $transferAciklama = "Transfer: $kaynakAdi -> $hedefAdi";
    if (!empty($aciklama)) {
        $transferAciklama .= " - $aciklama";
    }
    
    // Kaynak stok kontrolü
    $kaynakStok = 0;
    if ($kaynakTip === 'depo') {
        $stmt = $conn->prepare("SELECT stok_miktari FROM depo_stok WHERE depo_id = ? AND urun_id = ?");
        $stmt->execute([$kaynakId, $urunId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $kaynakStok = $result ? floatval($result['stok_miktari']) : 0;
    } else {
        $stmt = $conn->prepare("SELECT stok_miktari FROM magaza_stok WHERE magaza_id = ? AND barkod = ?");
        $stmt->execute([$kaynakId, $urun['barkod']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $kaynakStok = $result ? floatval($result['stok_miktari']) : 0;
    }
    
    if ($kaynakStok < $miktar) {
        throw new Exception("Kaynak lokasyonda yeterli stok yok. Mevcut: {$kaynakStok}, İstenen: {$miktar}");
    }
    
    // Transaction başlat
    $conn->beginTransaction();
    
    // 1. Kaynaktan stok azalt
    if ($kaynakTip === 'depo') {
        $stmt = $conn->prepare("UPDATE depo_stok SET stok_miktari = stok_miktari - ?, son_guncelleme = NOW() 
                           WHERE depo_id = ? AND urun_id = ?");
        $stmt->execute([$miktar, $kaynakId, $urunId]);
        
        // Stok hareketi ekle
        $stmt = $conn->prepare("INSERT INTO stok_hareketleri 
                           (urun_id, miktar, hareket_tipi, aciklama, tarih, kullanici_id, depo_id, magaza_id) 
                           VALUES (?, ?, 'cikis', ?, NOW(), ?, ?, NULL)");
        $stmt->execute([
            $urunId,
            $miktar,
            $transferAciklama,
            $_SESSION['user_id'] ?? null,
            $kaynakId
        ]);
    } else {
        $stmt = $conn->prepare("UPDATE magaza_stok SET stok_miktari = stok_miktari - ?, son_guncelleme = NOW() 
                           WHERE magaza_id = ? AND barkod = ?");
        $stmt->execute([$miktar, $kaynakId, $urun['barkod']]);
        
        // Stok hareketi ekle
        $stmt = $conn->prepare("INSERT INTO stok_hareketleri 
                           (urun_id, miktar, hareket_tipi, aciklama, tarih, kullanici_id, depo_id, magaza_id) 
                           VALUES (?, ?, 'cikis', ?, NOW(), ?, NULL, ?)");
        $stmt->execute([
            $urunId,
            $miktar,
            $transferAciklama,
            $_SESSION['user_id'] ?? null,
            $kaynakId
        ]);
    }
    
    // 2. Hedefe stok ekle
    if ($hedefTip === 'depo') {
        // Depo stoku var mı kontrol et
        $stmt = $conn->prepare("SELECT id FROM depo_stok WHERE depo_id = ? AND urun_id = ?");
        $stmt->execute([$hedefId, $urunId]);
        
        if ($stmt->rowCount() > 0) {
            // Mevcut stoku güncelle
            $stmt = $conn->prepare("UPDATE depo_stok SET stok_miktari = stok_miktari + ?, son_guncelleme = NOW() 
                               WHERE depo_id = ? AND urun_id = ?");
            $stmt->execute([$miktar, $hedefId, $urunId]);
        } else {
            // Yeni stok kaydı oluştur
            $stmt = $conn->prepare("INSERT INTO depo_stok (depo_id, urun_id, stok_miktari, son_guncelleme) 
                               VALUES (?, ?, ?, NOW())");
            $stmt->execute([$hedefId, $urunId, $miktar]);
        }
        
        // Stok hareketi ekle
        $stmt = $conn->prepare("INSERT INTO stok_hareketleri 
                           (urun_id, miktar, hareket_tipi, aciklama, tarih, kullanici_id, depo_id, magaza_id) 
                           VALUES (?, ?, 'giris', ?, NOW(), ?, ?, NULL)");
        $stmt->execute([
            $urunId,
            $miktar,
            $transferAciklama,
            $_SESSION['user_id'] ?? null,
            $hedefId
        ]);
    } else {
        // Mağaza stoku var mı kontrol et
        $stmt = $conn->prepare("SELECT id FROM magaza_stok WHERE magaza_id = ? AND barkod = ?");
        $stmt->execute([$hedefId, $urun['barkod']]);
        
        if ($stmt->rowCount() > 0) {
            // Mevcut stoku güncelle
            $stmt = $conn->prepare("UPDATE magaza_stok SET stok_miktari = stok_miktari + ?, son_guncelleme = NOW() 
                               WHERE magaza_id = ? AND barkod = ?");
            $stmt->execute([$miktar, $hedefId, $urun['barkod']]);
        } else {
            // Satış fiyatını al
            $stmt = $conn->prepare("SELECT satis_fiyati FROM urun_stok WHERE id = ?");
            $stmt->execute([$urunId]);
            $satisFiyati = $stmt->fetchColumn();
            
            // Yeni stok kaydı oluştur
            $stmt = $conn->prepare("INSERT INTO magaza_stok (barkod, magaza_id, stok_miktari, satis_fiyati, son_guncelleme) 
                               VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$urun['barkod'], $hedefId, $miktar, $satisFiyati]);
        }
        
        // Stok hareketi ekle
        $stmt = $conn->prepare("INSERT INTO stok_hareketleri 
                           (urun_id, miktar, hareket_tipi, aciklama, tarih, kullanici_id, depo_id, magaza_id) 
                           VALUES (?, ?, 'giris', ?, NOW(), ?, NULL, ?)");
        $stmt->execute([
            $urunId,
            $miktar,
            $transferAciklama,
            $_SESSION['user_id'] ?? null,
            $hedefId
        ]);
    }
    
    // Transaction'ı tamamla
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Stok transferi başarıyla tamamlandı'
    ]);
    
} catch (Exception $e) {
    // Hata durumunda rollback yap
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}