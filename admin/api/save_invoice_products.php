<?php
require_once '../session_manager.php';
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Raw input'u al
    $input = file_get_contents('php://input');
    error_log('Gelen Raw Veri: ' . $input);
    
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Geçersiz JSON verisi');
    }
    
    $fatura_id = intval($data['fatura_id'] ?? 0);
    $products = $data['products'] ?? [];
    $is_complete = $data['is_complete'] ?? false; // Bu anahtar parametredir!
    
    if (!$fatura_id) {
        throw new Exception('Fatura ID gerekli');
    }
    
    if (empty($products)) {
        throw new Exception('En az bir ürün gerekli');
    }
    
    // Faturanın varlığını kontrol et
    $stmt = $conn->prepare("SELECT id, durum FROM alis_faturalari WHERE id = ?");
    $stmt->execute([$fatura_id]);
    $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$fatura) {
        throw new Exception('Fatura bulunamadı');
    }
    
    // Eğer fatura zaten tamamlanmışsa (aktarildi durumunda), ürün eklemeyi engelle
    if ($fatura['durum'] === 'aktarildi') {
        throw new Exception('Bu fatura zaten tamamlanmış. Üzerine ürün ekleyemezsiniz.');
    }
    
    // Eğer fatura aktarım bekliyor durumunda ve tekrar kaydet denirse, engelle
    if ($fatura['durum'] === 'aktarim_bekliyor' && !$is_complete) {
        throw new Exception('Bu fatura aktarım bekliyor durumunda. Sadece aktarım yapabilir veya faturayı tekrar bitirmeyi seçebilirsiniz.');
    }
    
    // Transaction başlat
    $conn->beginTransaction();
    
    try {
        // Mevcut ürünleri sil
        $deleteStmt = $conn->prepare("DELETE FROM alis_fatura_detay WHERE fatura_id = ?");
        $deleteStmt->execute([$fatura_id]);
        
        error_log('Mevcut ürünler silindi, fatura ID: ' . $fatura_id);
        
        // Yeni ürünleri ekle
        $insertStmt = $conn->prepare("
            INSERT INTO alis_fatura_detay 
            (fatura_id, urun_id, miktar, birim_fiyat, iskonto1, iskonto2, iskonto3, kdv_orani, toplam_tutar) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $toplam_fatura_tutari = 0;
        $toplam_kdv = 0;
        
        foreach ($products as $product) {
            $urun_id = intval($product['urun_id'] ?? $product['id'] ?? 0);
            $miktar = floatval($product['miktar'] ?? 1);
            $birim_fiyat = floatval($product['birim_fiyat'] ?? 0);
            $iskonto1 = floatval($product['iskonto1'] ?? 0);
            $iskonto2 = floatval($product['iskonto2'] ?? 0);
            $iskonto3 = floatval($product['iskonto3'] ?? 0);
            $kdv_orani = floatval($product['kdv_orani'] ?? 0);
            
            // Toplam tutarı hesapla
            $ara_toplam = $miktar * $birim_fiyat;
            
            // İskontolar
            if ($iskonto1 > 0) {
                $ara_toplam = $ara_toplam * (1 - ($iskonto1 / 100));
            }
            if ($iskonto2 > 0) {
                $ara_toplam = $ara_toplam * (1 - ($iskonto2 / 100));
            }
            if ($iskonto3 > 0) {
                $ara_toplam = $ara_toplam * (1 - ($iskonto3 / 100));
            }
            
            $toplam_tutar = $ara_toplam;
            
            // KDV hesapla
            $kdv_tutari = $toplam_tutar * ($kdv_orani / 100);
            
            $toplam_fatura_tutari += $toplam_tutar;
            $toplam_kdv += $kdv_tutari;
            
            // Ürünü veritabanına ekle
            $insertStmt->execute([
                $fatura_id,
                $urun_id,
                $miktar,
                $birim_fiyat,
                $iskonto1,
                $iskonto2,
                $iskonto3,
                $kdv_orani,
                $toplam_tutar
            ]);
            
            error_log("Ürün eklendi - ID: $urun_id, Miktar: $miktar, Fiyat: $birim_fiyat, Toplam: $toplam_tutar");
        }
        
        // *** YENİ DURUM MANTIGI ***
        if ($is_complete) {
            // "Faturayı Bitir" butonuna basıldı
            $yeni_durum = 'aktarim_bekliyor';
            $mesaj = 'Fatura tamamlandı ve aktarım bekliyor';
        } else {
            // "Kaydet" butonuna basıldı
            if (count($products) > 0) {
                $yeni_durum = 'urun_girildi';
                $mesaj = 'Ürünler kaydedildi, faturaya devam edebilirsiniz';
            } else {
                $yeni_durum = 'bos';
                $mesaj = 'Boş fatura kaydedildi';
            }
        }
        
        $net_tutar = $toplam_fatura_tutari + $toplam_kdv;
        
        $updateStmt = $conn->prepare("
            UPDATE alis_faturalari 
            SET durum = ?, 
                toplam_tutar = ?, 
                kdv_tutari = ?, 
                net_tutar = ?
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $yeni_durum,
            $toplam_fatura_tutari,
            $toplam_kdv,
            $net_tutar,
            $fatura_id
        ]);
        
        // Transaction'ı onayla
        $conn->commit();
        
        error_log("Fatura güncellendi - Durum: $yeni_durum, Toplam: $toplam_fatura_tutari, KDV: $toplam_kdv, Net: $net_tutar");
        
        echo json_encode([
            'success' => true,
            'message' => $mesaj,
            'fatura_id' => $fatura_id,
            'product_count' => count($products),
            'toplam_tutar' => $toplam_fatura_tutari,
            'kdv_tutari' => $toplam_kdv,
            'net_tutar' => $net_tutar,
            'durum' => $yeni_durum,
            'is_complete' => $is_complete
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // Transaction'ı geri al
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('Ürün kaydetme hatası: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>