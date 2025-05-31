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

// POST verilerini kontrol et
if (!isset($_POST['siparis_id']) || !isset($_POST['yeni_durum'])) {
    echo json_encode(['success' => false, 'message' => 'Eksik veya geçersiz veri']);
    exit;
}

$siparis_id = intval($_POST['siparis_id']);
$yeni_durum = $_POST['yeni_durum'];
$aciklama = isset($_POST['aciklama']) ? $_POST['aciklama'] : '';
$add_to_stock = isset($_POST['add_to_stock']) && $_POST['add_to_stock'] == 1;
$depo_id = isset($_POST['depo_id']) ? intval($_POST['depo_id']) : 1; // Varsayılan depo ID 1

// Durum geçerliliğini kontrol et
$valid_durumlar = ['beklemede', 'onaylandi', 'iptal', 'tamamlandi'];
if (!in_array($yeni_durum, $valid_durumlar)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz durum değeri']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Mevcut durumu kontrol et - aynı duruma tekrar güncelleme yapılmaması için
    $check_query = "SELECT durum FROM siparisler WHERE id = :siparis_id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':siparis_id', $siparis_id);
    $check_stmt->execute();
    
    $mevcut_durum = $check_stmt->fetchColumn();
    
    if (!$mevcut_durum) {
        throw new Exception('Sipariş bulunamadı');
    }
    
    // Eğer durum aynıysa, güncelleme yapmadan başarılı dön
    if ($mevcut_durum == $yeni_durum) {
        echo json_encode(['success' => true, 'message' => 'Sipariş zaten bu durumda', 'no_change' => true]);
        exit;
    }
    
    // Siparişin durumunu güncelle
    $update_query = "UPDATE siparisler SET durum = :durum";
    
    // Eğer onaylandıysa, onay tarihini ekle
    if ($yeni_durum == 'onaylandi') {
        $update_query .= ", onay_tarihi = NOW()";
    }
    
    // Eğer tamamlandıysa, teslim tarihini ekle
    if ($yeni_durum == 'tamamlandi') {
        $update_query .= ", teslim_tarihi = NOW()";
    }
    
    $update_query .= " WHERE id = :siparis_id";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bindParam(':durum', $yeni_durum);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->execute();
    
    // Log ekle
    $log_query = "INSERT INTO siparis_log (siparis_id, islem_tipi, aciklama, kullanici_id) 
                  VALUES (:siparis_id, 'durum_degisiklik', :aciklama, :kullanici_id)";
    $log_aciklama = "Sipariş durumu '" . $yeni_durum . "' olarak güncellendi. " . $aciklama;
    
    $stmt = $conn->prepare($log_query);
    $stmt->bindParam(':siparis_id', $siparis_id);
    $stmt->bindParam(':aciklama', $log_aciklama);
    $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
    $stmt->execute();
    
    // Eğer sipariş tamamlandıysa ve ürünler stoğa eklenecekse
    if ($yeni_durum == 'tamamlandi' && $add_to_stock) {
        require_once '../helpers/stock_functions.php';
        
        // Sipariş detaylarını al
        $detail_query = "SELECT sd.urun_id, sd.miktar, sd.birim_fiyat FROM siparis_detay sd WHERE sd.siparis_id = :siparis_id";
        $stmt = $conn->prepare($detail_query);
        $stmt->bindParam(':siparis_id', $siparis_id);
        $stmt->execute();
        $detaylar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Her ürünü stoğa ekle
        foreach ($detaylar as $detay) {
            $urun_id = $detay['urun_id'];
            $miktar = $detay['miktar'];
            $birim_fiyat = $detay['birim_fiyat'];
            
            // Depo stoğunu güncelle
            updateDepoStock($urun_id, $depo_id, $miktar, $conn);
            
            // Stok hareketi ekle
            $hareket_params = [
                'urun_id' => $urun_id,
                'miktar' => $miktar,
                'hareket_tipi' => 'giris',
                'aciklama' => "Sipariş #" . $siparis_id . " ile stoğa eklendi.",
                'belge_no' => "SIP-" . $siparis_id,
                'tarih' => date('Y-m-d H:i:s'),
                'kullanici_id' => $_SESSION['user_id'],
                'depo_id' => $depo_id,
                'maliyet' => $birim_fiyat
            ];
            
            addStockMovement($hareket_params, $conn);
            
            // Ürün fiyat geçmişini güncelle
            $fiyat_params = [
                'urun_id' => $urun_id,
                'islem_tipi' => 'alis',
                'yeni_fiyat' => $birim_fiyat,
                'aciklama' => "Sipariş #" . $siparis_id . " ile güncellendi.",
                'kullanici_id' => $_SESSION['user_id']
            ];
            
            addPriceHistory($fiyat_params, $conn);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Sipariş durumu başarıyla güncellendi.'
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