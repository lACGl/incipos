<?php
// indirim_handler.php - Helpers dizinine yerleştirin

/**
 * Ürün için aktif indirimleri kontrol eder ve uygular
 * @param int $urunId Ürün ID
 * @param PDO $conn Veritabanı bağlantısı
 * @return array İndirim bilgileri
 */
function checkAndApplyDiscounts($urunId, $conn) {
    try {
        $bugun = date('Y-m-d');
        
        // Önce ürünün bilgilerini al
        $stmt = $conn->prepare("
            SELECT id, barkod, satis_fiyati, indirimli_fiyat, 
                   indirim_baslangic_tarihi, indirim_bitis_tarihi
            FROM urun_stok
            WHERE id = ?
        ");
        $stmt->execute([$urunId]);
        $urun = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$urun) {
            error_log("Ürün bulunamadı: " . $urunId);
            return [
                'success' => false,
                'message' => 'Ürün bulunamadı'
            ];
        }
        
        // Aktif indirimleri sorgula
        $stmt = $conn->prepare("
            SELECT i.id as indirim_id, i.indirim_turu, i.indirim_degeri, 
                   i.baslangic_tarihi, i.bitis_tarihi, i.uygulama_turu, i.filtre_degeri
            FROM indirimler i
            WHERE i.durum = 'aktif'
              AND i.baslangic_tarihi <= ?
              AND i.bitis_tarihi >= ?
              AND (
                   i.uygulama_turu = 'tum'
                   OR (i.uygulama_turu = 'secili' AND FIND_IN_SET(?, i.filtre_degeri))
                   OR (i.uygulama_turu = 'departman' AND ? IN (
                          SELECT id FROM urun_stok 
                          WHERE departman_id = i.filtre_degeri
                       ))
                   OR (i.uygulama_turu = 'ana_grup' AND ? IN (
                          SELECT id FROM urun_stok 
                          WHERE ana_grup_id = i.filtre_degeri
                       ))
              )
            ORDER BY i.indirim_degeri DESC
            LIMIT 1
        ");
        $stmt->execute([$bugun, $bugun, $urunId, $urunId, $urunId]);
        $indirim = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // İndirim varsa fiyatı hesapla
        if ($indirim) {
            $yeni_indirimli_fiyat = null;
            
            // İndirim türüne göre hesaplama yap
            if ($indirim['indirim_turu'] == 'yuzde') {
                $indirimOrani = floatval($indirim['indirim_degeri']);
                $yeni_indirimli_fiyat = $urun['satis_fiyati'] - ($urun['satis_fiyati'] * $indirimOrani / 100);
            } else { // 'tutar' ise
                $indirimTutari = floatval($indirim['indirim_degeri']);
                $yeni_indirimli_fiyat = $urun['satis_fiyati'] - $indirimTutari;
                // Negatif fiyat olmasını engelle
                if ($yeni_indirimli_fiyat < 0) {
                    $yeni_indirimli_fiyat = 0;
                }
            }
            
            // İndirimli fiyatı yuvarla
            $yeni_indirimli_fiyat = round($yeni_indirimli_fiyat, 2);
            
            // İndirim detayına kaydet/güncelle
            $detay_kontrol = $conn->prepare("
                SELECT id FROM indirim_detay
                WHERE indirim_id = ? AND urun_id = ?
            ");
            $detay_kontrol->execute([$indirim['indirim_id'], $urunId]);
            $detay = $detay_kontrol->fetch(PDO::FETCH_ASSOC);
            
            if ($detay) {
                // Varolan detayı güncelle
                $update_detay = $conn->prepare("
                    UPDATE indirim_detay
                    SET eski_fiyat = ?, indirimli_fiyat = ?, uygulama_tarihi = NOW()
                    WHERE id = ?
                ");
                $update_detay->execute([$urun['satis_fiyati'], $yeni_indirimli_fiyat, $detay['id']]);
            } else {
                // Yeni detay ekle
                $insert_detay = $conn->prepare("
                    INSERT INTO indirim_detay 
                    (indirim_id, urun_id, eski_fiyat, indirimli_fiyat, uygulama_tarihi)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $insert_detay->execute([
                    $indirim['indirim_id'], 
                    $urunId, 
                    $urun['satis_fiyati'], 
                    $yeni_indirimli_fiyat
                ]);
            }
            
            // Ürün tablosunu güncelle
            $update_urun = $conn->prepare("
                UPDATE urun_stok
                SET indirimli_fiyat = ?,
                    indirim_baslangic_tarihi = ?,
                    indirim_bitis_tarihi = ?
                WHERE id = ?
            ");
            $update_urun->execute([
                $yeni_indirimli_fiyat,
                $indirim['baslangic_tarihi'],
                $indirim['bitis_tarihi'],
                $urunId
            ]);
            
            // Tüm mağazaların fiyatını indirimli fiyat olarak güncelle
            $update_magazalar = $conn->prepare("
                UPDATE magaza_stok
                SET satis_fiyati = ?
                WHERE barkod = ?
            ");
            $update_magazalar->execute([$yeni_indirimli_fiyat, $urun['barkod']]);
            
            error_log("İndirim uygulandı. Ürün ID: $urunId, Eski Fiyat: {$urun['satis_fiyati']}, İndirimli Fiyat: $yeni_indirimli_fiyat");
            
            return [
                'success' => true,
                'indirim_var' => true,
                'eski_fiyat' => $urun['satis_fiyati'],
                'indirimli_fiyat' => $yeni_indirimli_fiyat,
                'indirim_id' => $indirim['indirim_id'],
                'indirim_turu' => $indirim['indirim_turu'],
                'indirim_degeri' => $indirim['indirim_degeri'],
                'baslangic_tarihi' => $indirim['baslangic_tarihi'],
                'bitis_tarihi' => $indirim['bitis_tarihi']
            ];
        } else {
            // İndirim yoksa ve varsa indirim bilgilerini temizle
            if ($urun['indirimli_fiyat'] !== null) {
                $update_urun = $conn->prepare("
                    UPDATE urun_stok
                    SET indirimli_fiyat = NULL,
                        indirim_baslangic_tarihi = NULL,
                        indirim_bitis_tarihi = NULL
                    WHERE id = ?
                ");
                $update_urun->execute([$urunId]);
                
                // Mağazaların fiyatını normal fiyata çek
                $update_magazalar = $conn->prepare("
                    UPDATE magaza_stok
                    SET satis_fiyati = ?
                    WHERE barkod = ?
                ");
                $update_magazalar->execute([$urun['satis_fiyati'], $urun['barkod']]);
                
                error_log("İndirim kaldırıldı. Ürün ID: $urunId, Normal Fiyat: {$urun['satis_fiyati']}");
            }
            
            return [
                'success' => true,
                'indirim_var' => false,
                'fiyat' => $urun['satis_fiyati']
            ];
        }
    } catch (Exception $e) {
        error_log("checkAndApplyDiscounts() hatası: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Ürün fiyatı güncellendiğinde indirimleri yeniden hesaplar
 * @param int $urunId Ürün ID
 * @param float $yeni_fiyat Yeni satış fiyatı
 * @param PDO $conn Veritabanı bağlantısı
 * @return array İşlem sonucu
 */
function updatePriceAndRecalculateDiscounts($urunId, $yeni_fiyat, $conn) {
    try {
        // Ürün bilgilerini al
        $stmt = $conn->prepare("
            SELECT barkod, satis_fiyati, indirimli_fiyat
            FROM urun_stok
            WHERE id = ?
        ");
        $stmt->execute([$urunId]);
        $urun = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$urun) {
            error_log("Ürün bulunamadı: " . $urunId);
            return [
                'success' => false,
                'message' => 'Ürün bulunamadı'
            ];
        }
        
        // Önce normal fiyatı güncelle
        $update_fiyat = $conn->prepare("
            UPDATE urun_stok
            SET satis_fiyati = ?
            WHERE id = ?
        ");
        $update_fiyat->execute([$yeni_fiyat, $urunId]);
        
        error_log("Ürün fiyatı güncellendi. Ürün ID: $urunId, Eski: {$urun['satis_fiyati']}, Yeni: $yeni_fiyat");
        
        // Şimdi indirimleri yeniden hesapla
        $indirim_sonuc = checkAndApplyDiscounts($urunId, $conn);
        
        if ($indirim_sonuc['success']) {
            if ($indirim_sonuc['indirim_var']) {
                return [
                    'success' => true,
                    'message' => 'Fiyat güncellendi ve indirim yeniden hesaplandı',
                    'normal_fiyat' => $yeni_fiyat,
                    'indirimli_fiyat' => $indirim_sonuc['indirimli_fiyat']
                ];
            } else {
                // İndirim yoksa mağaza fiyatlarını güncelle
                $update_magazalar = $conn->prepare("
                    UPDATE magaza_stok
                    SET satis_fiyati = ?
                    WHERE barkod = ?
                ");
                $update_magazalar->execute([$yeni_fiyat, $urun['barkod']]);
                
                return [
                    'success' => true,
                    'message' => 'Fiyat güncellendi (indirim yok)',
                    'normal_fiyat' => $yeni_fiyat
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Fiyat güncellendi fakat indirim hesaplanamadı: ' . $indirim_sonuc['message']
            ];
        }
    } catch (Exception $e) {
        error_log("updatePriceAndRecalculateDiscounts() hatası: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}