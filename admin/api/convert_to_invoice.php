<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
// Siparişleri faturaya dönüştürmek için API
require_once '../db_connection.php';
require_once '../helpers/stock_functions.php';

header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// POST verilerini kontrol et
if (!isset($_POST['siparis_id']) || !isset($_POST['fatura_seri']) || !isset($_POST['fatura_no']) || !isset($_POST['fatura_tarihi'])) {
    echo json_encode(['success' => false, 'message' => 'Eksik veya geçersiz veri']);
    exit;
}

$siparis_id = intval($_POST['siparis_id']);
$fatura_seri = trim($_POST['fatura_seri']);
$fatura_no = trim($_POST['fatura_no']);
$fatura_tarihi = $_POST['fatura_tarihi'];
$irsaliye_no = isset($_POST['irsaliye_no']) ? trim($_POST['irsaliye_no']) : null;
$irsaliye_tarihi = isset($_POST['irsaliye_tarihi']) && !empty($_POST['irsaliye_tarihi']) ? $_POST['irsaliye_tarihi'] : null;
$aciklama = isset($_POST['aciklama']) ? trim($_POST['aciklama']) : '';

// Geçerlilik kontrolü
if (empty($fatura_seri) || empty($fatura_no) || empty($fatura_tarihi)) {
    echo json_encode(['success' => false, 'message' => 'Fatura seri, no ve tarihi zorunludur']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Siparişin daha önce faturaya dönüştürülüp dönüştürülmediğini kontrol et
    $check_log_query = "SELECT COUNT(*) as log_count FROM siparis_log 
                       WHERE siparis_id = :siparis_id 
                       AND islem_tipi = 'dönüştürme'";
    $check_stmt = $conn->prepare($check_log_query);
    $check_stmt->bindParam(':siparis_id', $siparis_id);
    $check_stmt->execute();
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['log_count'] > 0) {
        throw new Exception('Bu sipariş zaten faturaya dönüştürülmüş');
    }
    
    // Sipariş bilgilerini al
    $siparis_query = "SELECT s.*, t.id as tedarikci_id 
                      FROM siparisler s
                      JOIN tedarikciler t ON s.tedarikci_id = t.id
                      WHERE s.id = :siparis_id 
                      AND s.durum = 'tamamlandi'";
    
    $stmt = $conn->prepare($siparis_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->execute();
    
    $siparis = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$siparis) {
        throw new Exception('Sipariş bulunamadı veya tamamlanmış durumda değil');
    }
    
    // Sipariş detaylarını al
    $detay_query = "SELECT sd.*, us.ad as urun_adi, us.barkod, us.kdv_orani
                    FROM siparis_detay sd
                    JOIN urun_stok us ON sd.urun_id = us.id
                    WHERE sd.siparis_id = :siparis_id";
    $stmt = $conn->prepare($detay_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->execute();
    $siparis_detaylari = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($siparis_detaylari)) {
        throw new Exception('Sipariş detayları bulunamadı');
    }
    
    // Fatura numarası benzersizlik kontrolü
    $check_query = "SELECT id FROM alis_faturalari WHERE fatura_seri = :fatura_seri AND fatura_no = :fatura_no";
    $stmt = $conn->prepare($check_query);
    $stmt->bindParam(':fatura_seri', $fatura_seri);
    $stmt->bindParam(':fatura_no', $fatura_no);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        throw new Exception('Bu fatura seri ve numarası zaten kullanılmış');
    }
    
    // Fatura oluştur
    $insert_query = "INSERT INTO alis_faturalari (
        fatura_tipi, 
        magaza, 
        fatura_seri, 
        fatura_no, 
        fatura_tarihi, 
        irsaliye_no, 
        irsaliye_tarihi, 
        siparis_no, 
        siparis_tarihi, 
        tedarikci, 
        durum, 
        toplam_tutar, 
        kdv_tutari, 
        net_tutar, 
        aciklama, 
        kayit_tarihi,
        kullanici_id
    ) VALUES (
        'satis',
        NULL,
        :fatura_seri,
        :fatura_no,
        :fatura_tarihi,
        :irsaliye_no,
        :irsaliye_tarihi,
        :siparis_no,
        :siparis_tarihi,
        :tedarikci,
        'urun_girildi',
        :toplam_tutar,
        :kdv_tutari,
        :net_tutar,
        :aciklama,
        NOW(),
        :kullanici_id
    )";
    
    // Sipariş toplam tutarını hesapla
    $toplam_tutar = 0;
    $kdv_tutari = 0;
    $net_tutar = 0;
    
    foreach ($siparis_detaylari as $detay) {
        if (!isset($detay['urun_id']) || !isset($detay['miktar']) || !isset($detay['birim_fiyat'])) {
            continue; // Geçersiz detay, atla
        }
        $miktar = floatval($detay['miktar']);
        $birim_fiyat = floatval($detay['birim_fiyat']);
        $kdv_orani = isset($detay['kdv_orani']) ? floatval($detay['kdv_orani']) : 0;
        
        $ara_toplam = $miktar * $birim_fiyat;
        $net_tutar += $ara_toplam;
        
        $urun_kdv_tutari = $ara_toplam * ($kdv_orani / 100);
        $kdv_tutari += $urun_kdv_tutari;
    }
    
    $toplam_tutar = $net_tutar + $kdv_tutari;
    
    $stmt = $conn->prepare($insert_query);
    $stmt->bindParam(':fatura_seri', $fatura_seri);
    $stmt->bindParam(':fatura_no', $fatura_no);
    $stmt->bindParam(':fatura_tarihi', $fatura_tarihi);
    $stmt->bindParam(':irsaliye_no', $irsaliye_no);
    $stmt->bindParam(':irsaliye_tarihi', $irsaliye_tarihi);
    $stmt->bindValue(':siparis_no', strval($siparis_id)); // Sipariş ID'sini sipariş no olarak kullan
    $stmt->bindValue(':siparis_tarihi', $siparis['tarih']);
    $stmt->bindParam(':tedarikci', $siparis['tedarikci_id']);
    $stmt->bindParam(':toplam_tutar', $toplam_tutar);
    $stmt->bindParam(':kdv_tutari', $kdv_tutari);
    $stmt->bindParam(':net_tutar', $net_tutar);
    $stmt->bindParam(':aciklama', $aciklama);
    $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $fatura_id = $conn->lastInsertId();
    
    // Fatura detaylarını oluştur
    $items_query = "INSERT INTO alis_fatura_detay (
        fatura_id, 
        urun_id, 
        miktar, 
        birim_fiyat, 
        iskonto1, 
        iskonto2, 
        iskonto3, 
        kdv_orani, 
        toplam_tutar
    ) VALUES (
        :fatura_id,
        :urun_id,
        :miktar,
        :birim_fiyat,
        :iskonto1,
        :iskonto2,
        :iskonto3,
        :kdv_orani,
        :toplam_tutar
    )";
    
    $stmt = $conn->prepare($items_query);
    
    foreach ($siparis_detaylari as $detay) {
        if (!isset($detay['urun_id']) || !isset($detay['miktar']) || !isset($detay['birim_fiyat'])) {
            continue; // Geçersiz detay, atla
        }
        
        $miktar = floatval($detay['miktar']);
        $birim_fiyat = floatval($detay['birim_fiyat']);
        $kdv_orani = isset($detay['kdv_orani']) ? floatval($detay['kdv_orani']) : 0;
        
        $ara_toplam = $miktar * $birim_fiyat;
        $urun_kdv_tutari = $ara_toplam * ($kdv_orani / 100);
        $urun_toplam = $ara_toplam + $urun_kdv_tutari;
        
        $stmt->bindParam(':fatura_id', $fatura_id);
        $stmt->bindParam(':urun_id', $detay['urun_id']);
        $stmt->bindParam(':miktar', $miktar);
        $stmt->bindParam(':birim_fiyat', $birim_fiyat);
        $stmt->bindValue(':iskonto1', 0); // Varsayılan olarak 0
        $stmt->bindValue(':iskonto2', 0); // Varsayılan olarak 0
        $stmt->bindValue(':iskonto3', 0); // Varsayılan olarak 0
        $stmt->bindParam(':kdv_orani', $kdv_orani);
        $stmt->bindParam(':toplam_tutar', $urun_toplam);
        $stmt->execute();
    }
    
    // Sipariş loguna kayıt ekle
    $log_query = "INSERT INTO siparis_log (
        siparis_id,
        islem_tipi,
        aciklama,
        kullanici_id,
        tarih
    ) VALUES (
        :siparis_id,
        'dönüştürme',
        :aciklama,
        :kullanici_id,
        NOW()
    )";
    
    $log_aciklama = "Sipariş, " . $fatura_seri . $fatura_no . " numaralı faturaya dönüştürüldü. (Ürünler aktarılmadı)";
    
    $stmt = $conn->prepare($log_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->bindParam(':aciklama', $log_aciklama);
    $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sipariş başarıyla faturaya dönüştürüldü. Ürünleri aktarmak için faturalar sayfasından aktarım yapabilirsiniz.',
        'fatura_id' => $fatura_id
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}