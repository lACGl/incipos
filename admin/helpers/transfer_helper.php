<?php
// transfer_helper.php - Bu dosyayı helpers dizinine yerleştirin

/**
 * Ürünün toplam stok miktarını hesaplar
 * @param int $urunId Ürün ID
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarısı
 */
function updateTotalStock($urunId, $conn) {
    try {
        // Hata ayıklama logu
        error_log("updateTotalStock() çağrıldı. Ürün ID: $urunId");
        
        // Mağaza stoklarını topla
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(stok_miktari), 0) as magaza_stok 
            FROM magaza_stok 
            WHERE barkod = (SELECT barkod FROM urun_stok WHERE id = ?)
        ");
        $stmt->execute([$urunId]);
        $magazaStok = $stmt->fetch(PDO::FETCH_ASSOC)['magaza_stok'] ?? 0;
        
        // Depo stoklarını topla
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(stok_miktari), 0) as depo_stok 
            FROM depo_stok 
            WHERE urun_id = ?
        ");
        $stmt->execute([$urunId]);
        $depoStok = $stmt->fetch(PDO::FETCH_ASSOC)['depo_stok'] ?? 0;
        
        // Toplam stok
        $toplamStok = $magazaStok + $depoStok;
        
        // Ürün stok miktarını güncelle
        $stmt = $conn->prepare("
            UPDATE urun_stok 
            SET stok_miktari = ? 
            WHERE id = ?
        ");
        $stmt->execute([$toplamStok, $urunId]);
        
        error_log("Ürün stok güncellendi. Ürün ID: $urunId, Mağaza Stok: $magazaStok, Depo Stok: $depoStok, Toplam: $toplamStok");
        
        return true;
    } catch (Exception $e) {
        error_log("updateTotalStock() hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Ürünün aktarılabilir miktarını kontrol eder
 * @param int $faturaId Fatura ID
 * @param int $urunId Ürün ID
 * @param PDO $conn Veritabanı bağlantısı
 * @return array Kalan miktar bilgisi
 */
function checkTransferableQuantity($faturaId, $urunId, $conn) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                afd.miktar as fatura_miktar,
                COALESCE((
                    SELECT SUM(afda.miktar) 
                    FROM alis_fatura_detay_aktarim afda 
                    WHERE afda.fatura_id = ? 
                    AND afda.urun_id = ?
                ), 0) as aktarilan_miktar
            FROM alis_fatura_detay afd
            WHERE afd.fatura_id = ? AND afd.urun_id = ?
        ");
        $stmt->execute([$faturaId, $urunId, $faturaId, $urunId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Ürün faturada bulunamadı',
                'kalan_miktar' => 0
            ];
        }
        
        $faturaMiktar = floatval($result['fatura_miktar']);
        $aktarilanMiktar = floatval($result['aktarilan_miktar']);
        $kalanMiktar = $faturaMiktar - $aktarilanMiktar;
        
        return [
            'success' => true,
            'fatura_miktar' => $faturaMiktar,
            'aktarilan_miktar' => $aktarilanMiktar,
            'kalan_miktar' => $kalanMiktar
        ];
    } catch (Exception $e) {
        error_log("checkTransferableQuantity() hatası: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'kalan_miktar' => 0
        ];
    }
}

/**
 * Ürünün satış fiyatını tüm mağazalarda günceller
 * @param int $urunId Ürün ID
 * @param float $satisFiyati Yeni satış fiyatı
 * @param float $eskiFiyat Eski satış fiyatı
 * @param int $faturaId Fatura ID
 * @param PDO $conn Veritabanı bağlantısı
 * @return bool İşlem başarısı
 */
function updateProductPriceAllStores($urunId, $satisFiyati, $eskiFiyat, $faturaId, $conn) {
    try {
        // Ürün barkodunu ve indirim bilgilerini al
        $stmt = $conn->prepare("
            SELECT 
                barkod, 
                indirimli_fiyat, 
                indirim_baslangic_tarihi, 
                indirim_bitis_tarihi,
                satis_fiyati
            FROM urun_stok 
            WHERE id = ?
        ");
        $stmt->execute([$urunId]);
        $urun = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$urun) {
            error_log("Ürün bulunamadı. Ürün ID: $urunId");
            return false;
        }
        
        $barkod = $urun['barkod'];
        $indirimli_fiyat = $urun['indirimli_fiyat'];
        $indirim_baslangic_tarihi = $urun['indirim_baslangic_tarihi'];
        $indirim_bitis_tarihi = $urun['indirim_bitis_tarihi'];
        $mevcut_satis_fiyati = $urun['satis_fiyati'];
        
        // İndirim hesaplaması
        $yeni_indirimli_fiyat = null;
        $bugun = date('Y-m-d');
        $indirimAktif = false;
        
        // İndirim aktif mi kontrol et
        if ($indirimli_fiyat && $indirim_baslangic_tarihi && $indirim_bitis_tarihi) {
            if ($bugun >= $indirim_baslangic_tarihi && $bugun <= $indirim_bitis_tarihi) {
                $indirimAktif = true;
                
                // İndirim oranını hesapla ve yeni fiyata uygula
                if ($mevcut_satis_fiyati > 0) {
                    $indirimOrani = (($mevcut_satis_fiyati - $indirimli_fiyat) / $mevcut_satis_fiyati) * 100;
                    $yeni_indirimli_fiyat = $satisFiyati - (($satisFiyati * $indirimOrani) / 100);
                    $yeni_indirimli_fiyat = round($yeni_indirimli_fiyat, 2); // 2 basamağa yuvarla
                    
                    error_log("İndirim güncelleniyor. Ürün ID: $urunId, Eski Fiyat: $mevcut_satis_fiyati, " .
                             "Eski İndirimli: $indirimli_fiyat, İndirim Oranı: $indirimOrani%, " .
                             "Yeni Fiyat: $satisFiyati, Yeni İndirimli: $yeni_indirimli_fiyat");
                }
            }
        }
        
        // Ana tablodaki satış fiyatını ve gerekirse indirimli fiyatı güncelle
        if ($indirimAktif && $yeni_indirimli_fiyat !== null) {
            $stmt = $conn->prepare("
                UPDATE urun_stok 
                SET satis_fiyati = ?, indirimli_fiyat = ? 
                WHERE id = ?
            ");
            $stmt->execute([$satisFiyati, $yeni_indirimli_fiyat, $urunId]);
        } else {
            $stmt = $conn->prepare("UPDATE urun_stok SET satis_fiyati = ? WHERE id = ?");
            $stmt->execute([$satisFiyati, $urunId]);
        }
        
        error_log("Ana ürün satış fiyatı güncellendi. Ürün ID: $urunId, Yeni Fiyat: $satisFiyati" . 
                 ($indirimAktif ? ", Yeni İndirimli Fiyat: $yeni_indirimli_fiyat" : ""));
        
        // TÜM mağazalardaki satış fiyatını güncelle
        // Mağazalarda indirimli fiyat tutulmadığı için sadece satış fiyatını güncelliyoruz
        $stmt = $conn->prepare("
            UPDATE magaza_stok 
            SET satis_fiyati = ?, son_guncelleme = NOW() 
            WHERE barkod = ?
        ");
        $stmt->execute([$indirimAktif && $yeni_indirimli_fiyat !== null ? $yeni_indirimli_fiyat : $satisFiyati, $barkod]);
        $updatedStores = $stmt->rowCount();
        error_log("Tüm mağazalardaki fiyat güncellendi. Etkilenen mağaza sayısı: $updatedStores, Barkod: $barkod");
        
        // Fiyat değişikliğini geçmişe kaydet
        if ($satisFiyati != $eskiFiyat) {
            $stmt = $conn->prepare("
                INSERT INTO urun_fiyat_gecmisi (
                    urun_id, 
                    islem_tipi, 
                    eski_fiyat, 
                    yeni_fiyat,
                    aciklama,
                    fatura_id,
                    kullanici_id,
                    tarih
                ) VALUES (
                    ?, 
                    'satis_fiyati_guncelleme', 
                    ?, 
                    ?,
                    'Faturadan aktarım sırasında tüm mağazalarda fiyat güncelleme',
                    ?,
                    ?,
                    NOW()
                )
            ");
            $stmt->execute([
                $urunId,
                $eskiFiyat,
                $satisFiyati,
                $faturaId,
                $_SESSION['user_id'] ?? null
            ]);
            error_log("Fiyat değişikliği kaydedildi.");
            
            // İndirimli fiyat değişikliğini de kaydet
            if ($indirimAktif && $yeni_indirimli_fiyat !== null && $indirimli_fiyat != $yeni_indirimli_fiyat) {
                $stmt = $conn->prepare("
                    INSERT INTO urun_fiyat_gecmisi (
                        urun_id, 
                        islem_tipi, 
                        eski_fiyat, 
                        yeni_fiyat,
                        aciklama,
                        fatura_id,
                        kullanici_id,
                        tarih
                    ) VALUES (
                        ?, 
                        'indirimli_fiyat_guncelleme', 
                        ?, 
                        ?,
                        'Faturadan aktarım sırasında indirimli fiyat güncelleme',
                        ?,
                        ?,
                        NOW()
                    )
                ");
                $stmt->execute([
                    $urunId,
                    $indirimli_fiyat,
                    $yeni_indirimli_fiyat,
                    $faturaId,
                    $_SESSION['user_id'] ?? null
                ]);
                error_log("İndirimli fiyat değişikliği kaydedildi.");
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("updateProductPriceAllStores() hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * Faturadaki toplam ve aktarılan miktarları hesaplar
 * @param int $faturaId Fatura ID
 * @param PDO $conn Veritabanı bağlantısı
 * @return array Toplam ve aktarılan miktar bilgisi
 */
function calculateInvoiceQuantities($faturaId, $conn) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                SUM(afd.miktar) as toplam_miktar,
                COALESCE((
                    SELECT SUM(afda.miktar) 
                    FROM alis_fatura_detay_aktarim afda 
                    WHERE afda.fatura_id = ?
                ), 0) as aktarilan_miktar
            FROM alis_fatura_detay afd
            WHERE afd.fatura_id = ?
        ");
        $stmt->execute([$faturaId, $faturaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Fatura detayları bulunamadı',
                'toplam_miktar' => 0,
                'aktarilan_miktar' => 0,
                'durum' => 'urun_girildi'
            ];
        }
        
        $toplamMiktar = floatval($result['toplam_miktar']);
        $aktarilanMiktar = floatval($result['aktarilan_miktar']);
        
        // Fatura durumunu belirle
        $durum = 'urun_girildi'; // Varsayılan
        if ($aktarilanMiktar >= $toplamMiktar) {
            $durum = 'aktarildi';
        } else if ($aktarilanMiktar > 0) {
            $durum = 'kismi_aktarildi';
        }
        
        return [
            'success' => true,
            'toplam_miktar' => $toplamMiktar,
            'aktarilan_miktar' => $aktarilanMiktar,
            'durum' => $durum
        ];
    } catch (Exception $e) {
        error_log("calculateInvoiceQuantities() hatası: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'toplam_miktar' => 0,
            'aktarilan_miktar' => 0,
            'durum' => 'urun_girildi'
        ];
    }
}
?>