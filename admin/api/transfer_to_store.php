<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';
require_once '../helpers/transfer_helper.php';
require_once '../helpers/indirim_handler.php';

// Hata raporlama ayarları
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hata görüntüleme kapalı ama log açık

// JSON header
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Gelen veriyi logla
    $rawInput = file_get_contents('php://input');
    error_log('Gelen Ham Veri: ' . $rawInput);
    
    // JSON verisini parse et
    $data = json_decode($rawInput, true);
    
    // JSON parse hatasını kontrol et
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON parse hatası: ' . json_last_error_msg());
    }
    
    // Gerekli verilerin kontrolü
    if (!isset($data['fatura_id']) || !isset($data['hedef_id']) || !isset($data['hedef_tipi']) || !isset($data['products'])) {
        throw new Exception('Geçersiz veri formatı veya eksik bilgi');
    }

    $fatura_id = intval($data['fatura_id']);
    $hedef_id = intval($data['hedef_id']);
    $hedef_tipi = $data['hedef_tipi']; // 'magaza' veya 'depo'
    $products = $data['products'];

    // Fatura ID kontrolü
    if ($fatura_id <= 0) {
        throw new Exception('Geçersiz fatura ID: ' . $fatura_id);
    }
    
    // Hedef ID kontrolü
    if ($hedef_id <= 0) {
        throw new Exception('Geçersiz hedef ID: ' . $hedef_id);
    }

    // Hedef tipi kontrolü
    if (!in_array($hedef_tipi, ['magaza', 'depo'])) {
        throw new Exception('Geçersiz hedef tipi: ' . $hedef_tipi);
    }

    // Fatura durumunu kontrol et - YENİ KONTROLLER EKLENDİ
    $checkStmt = $conn->prepare("SELECT durum FROM alis_faturalari WHERE id = ?");
    $checkStmt->execute([$fatura_id]);
    $faturaData = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$faturaData) {
        throw new Exception('Fatura bulunamadı');
    }
    
    // Yeni durum kontrolleri
    if ($faturaData['durum'] === 'bos') {
        throw new Exception('Bu faturada henüz ürün bulunmuyor. Önce ürün ekleyin.');
    }
    
    if ($faturaData['durum'] === 'urun_girildi') {
        throw new Exception('Bu fatura henüz tamamlanmamış. Önce "Faturayı Bitir" ile tamamlayın.');
    }
    
    if ($faturaData['durum'] === 'aktarildi') {
        throw new Exception('Bu fatura zaten tamamen aktarılmış');
    }
    
    // sadece 'aktarim_bekliyor' ve 'kismi_aktarildi' durumlarında aktarım yapılabilir
    if (!in_array($faturaData['durum'], ['aktarim_bekliyor', 'kismi_aktarildi'])) {
        throw new Exception('Bu fatura durumu aktarıma uygun değil: ' . $faturaData['durum']);
    }

    // Transaction başlat
    $conn->beginTransaction();
    error_log("Transfer işlemi başladı. Fatura ID: $fatura_id, Hedef Tipi: $hedef_tipi, Hedef ID: $hedef_id");

    // İşlenecek ürün sayacı
    $processedItems = 0;
    $totalTransferredQuantity = 0;

    // Gelen ürünleri logla
    error_log("Gelen ürünler: " . print_r($products, true));
    
    if (empty($products)) {
        throw new Exception("Transfer edilecek ürün bulunamadı.");
    }
    
    // Seçili ürünleri filtrele
    $selectedProducts = array_filter($products, function($product) {
        return isset($product['selected']) && $product['selected'] === true;
    });
    
    if (empty($selectedProducts)) {
        throw new Exception("Hiçbir ürün seçilmedi.");
    }
    
    error_log("Seçilen ürün sayısı: " . count($selectedProducts));
    
    // Seçili ürünleri işle
    foreach ($selectedProducts as $product) {
        // Ürün verilerini logla
        error_log("İşlenen ürün: " . print_r($product, true));
        
        // Transfer miktarını kontrol et
        $transferMiktar = floatval($product['transfer_miktar'] ?? 0);
        if ($transferMiktar <= 0) {
            error_log("Transfer miktarı geçersiz, atlanıyor: " . $transferMiktar);
            continue;
        }

        // Ürün ID kontrolü
        if (!isset($product['urun_id']) || empty($product['urun_id'])) {
            error_log("Ürün ID'si bulunamadı");
            throw new Exception("Ürün ID'si bulunamadı");
        }
        
        $urunId = intval($product['urun_id']);
        
        error_log("Ürün bilgileri sorgulanıyor. Ürün ID: $urunId, Fatura ID: $fatura_id");
        
        // Ürün bilgilerini al
        $stmt = $conn->prepare("
            SELECT 
                us.id,
                us.barkod,
                us.ad,
                us.satis_fiyati as mevcut_satis_fiyati,
                COALESCE(afd.birim_fiyat, 0) as birim_fiyat,
                COALESCE(afd.miktar, 0) as fatura_miktar
            FROM urun_stok us
            LEFT JOIN alis_fatura_detay afd ON afd.urun_id = us.id AND afd.fatura_id = ?
            WHERE us.id = ?
        ");
        $stmt->execute([$fatura_id, $urunId]);
        $urun = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$urun) {
            throw new Exception("Ürün bilgisi bulunamadı: ID $urunId");
        }
        
        if (!$urun['birim_fiyat'] || !$urun['fatura_miktar']) {
            throw new Exception("Bu ürün bu faturada bulunamadı: {$urun['ad']}");
        }

        error_log("Ürün işleniyor: ID: {$urun['id']}, Barkod: {$urun['barkod']}, Ad: {$urun['ad']}, Transfer Miktar: $transferMiktar");

        // Aktarılabilir miktarı kontrol et
        $transferableResult = checkTransferableQuantity($fatura_id, $urunId, $conn);
        if (!$transferableResult['success']) {
            throw new Exception("Ürünün aktarılabilir miktarı hesaplanamadı: {$urun['ad']}");
        }
        
        $kalanMiktar = $transferableResult['kalan_miktar'];
        if ($transferMiktar > $kalanMiktar) {
            throw new Exception("'{$urun['ad']}' için aktarılmak istenen miktar ($transferMiktar) kalan miktardan ($kalanMiktar) fazla olamaz.");
        }

        // Ürün satış fiyatını kontrol et
        $satisFiyati = floatval($product['satis_fiyati'] ?? 0);
        if ($satisFiyati <= 0) {
            throw new Exception("'{$urun['ad']}' için geçerli bir satış fiyatı girmelisiniz.");
        }

        // Ürünün normal satış fiyatını güncelle ve indirimleri yeniden hesapla
        $priceUpdateResult = updatePriceAndRecalculateDiscounts($urunId, $satisFiyati, $conn);
        if (!$priceUpdateResult['success']) {
            error_log("Ürün fiyatı güncellenirken bir sorun oluştu. Ürün ID: $urunId, Hata: " . $priceUpdateResult['message']);
            // Hata oluşsa bile işleme devam et - kritik bir hata değil
        } else {
            error_log("Ürün fiyatı güncellendi. Ürün ID: $urunId, " . 
                      (isset($priceUpdateResult['indirimli_fiyat']) ? 
                       "Normal: {$priceUpdateResult['normal_fiyat']}, İndirimli: {$priceUpdateResult['indirimli_fiyat']}" : 
                       "Fiyat: {$priceUpdateResult['normal_fiyat']}"));
        }

        // Hedef türüne göre işlem yap
        if ($hedef_tipi === 'magaza') {
            // Mağaza stoğunu güncelle
            try {
                $stmt = $conn->prepare("
                    INSERT INTO magaza_stok (
                        barkod, 
                        magaza_id, 
                        stok_miktari, 
                        satis_fiyati,
                        son_guncelleme
                    ) VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        stok_miktari = stok_miktari + ?,
                        satis_fiyati = ?,
                        son_guncelleme = NOW()
                ");
                $stmt->execute([
                    $urun['barkod'],
                    $hedef_id,
                    $transferMiktar,
                    $satisFiyati, 
                    $transferMiktar,
                    $satisFiyati
                ]);
                $updatedRows = $stmt->rowCount();
                error_log("Mağaza stoğu güncellendi. Etkilenen satır: $updatedRows");
            } catch (Exception $e) {
                error_log("Mağaza stok güncelleme hatası: " . $e->getMessage());
                throw new Exception("Mağaza stok güncelleme hatası: " . $e->getMessage());
            }

            // Stok hareketi ekle
            try {
                $stmt = $conn->prepare("
                    INSERT INTO stok_hareketleri (
                        urun_id, 
                        miktar, 
                        hareket_tipi, 
                        aciklama,
                        belge_no,
                        tarih, 
                        kullanici_id, 
                        magaza_id, 
                        maliyet, 
                        satis_fiyati
                    ) VALUES (
                        ?, ?, 'giris', 'Faturadan mağazaya aktarım',
                        ?, NOW(), ?, ?, ?, ?
                    )
                ");
                $stmt->execute([
                    $urunId,
                    $transferMiktar,
                    $fatura_id,
                    $_SESSION['user_id'] ?? null,
                    $hedef_id,
                    $urun['birim_fiyat'],
                    $satisFiyati
                ]);
                error_log("Mağazaya giriş hareketi kaydedildi. Ürün ID: $urunId, Miktar: $transferMiktar");
            } catch (Exception $e) {
                error_log("Stok hareketi kayıt hatası: " . $e->getMessage());
                throw new Exception("Stok hareketi kayıt hatası: " . $e->getMessage());
            }
        } else { // hedef_tipi === 'depo'
            // Depo stoğunu güncelle
            try {
                $stmt = $conn->prepare("
                    INSERT INTO depo_stok (
                        depo_id, 
                        urun_id, 
                        stok_miktari, 
                        son_guncelleme
                    ) VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        stok_miktari = stok_miktari + ?,
                        son_guncelleme = NOW()
                ");
                $stmt->execute([
                    $hedef_id,
                    $urunId,
                    $transferMiktar,
                    $transferMiktar
                ]);
                $updatedRows = $stmt->rowCount();
                error_log("Depo stoğu güncellendi. Etkilenen satır: $updatedRows");
            } catch (Exception $e) {
                error_log("Depo stok güncelleme hatası: " . $e->getMessage());
                throw new Exception("Depo stok güncelleme hatası: " . $e->getMessage());
            }

            // Stok hareketi ekle
            try {
                $stmt = $conn->prepare("
                    INSERT INTO stok_hareketleri (
                        urun_id, 
                        miktar, 
                        hareket_tipi, 
                        aciklama,
                        belge_no,
                        tarih, 
                        kullanici_id, 
                        depo_id, 
                        maliyet, 
                        satis_fiyati
                    ) VALUES (
                        ?, ?, 'giris', 'Faturadan depoya aktarım',
                        ?, NOW(), ?, ?, ?, ?
                    )
                ");
                $stmt->execute([
                    $urunId,
                    $transferMiktar,
                    $fatura_id,
                    $_SESSION['user_id'] ?? null,
                    $hedef_id,
                    $urun['birim_fiyat'],
                    $satisFiyati
                ]);
                error_log("Depoya giriş hareketi kaydedildi. Ürün ID: $urunId, Miktar: $transferMiktar");
            } catch (Exception $e) {
                error_log("Stok hareketi kayıt hatası: " . $e->getMessage());
                throw new Exception("Stok hareketi kayıt hatası: " . $e->getMessage());
            }
        }

        // Aktarım kaydını ekle
        try {
            $stmt = $conn->prepare("
                INSERT INTO alis_fatura_detay_aktarim (
                    fatura_id, 
                    urun_id, 
                    miktar, 
                    kalan_miktar,
                    aktarim_tarihi, 
                    magaza_id,
                    depo_id
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $fatura_id,
                $urunId,
                $transferMiktar,
                $kalanMiktar - $transferMiktar,
                $hedef_tipi === 'magaza' ? $hedef_id : null,
                $hedef_tipi === 'depo' ? $hedef_id : null
            ]);
            error_log("Aktarım kaydı eklendi. Fatura ID: $fatura_id, Ürün ID: $urunId, Miktar: $transferMiktar");
        } catch (Exception $e) {
            error_log("Aktarım kaydı ekleme hatası: " . $e->getMessage());
            throw new Exception("Aktarım kaydı ekleme hatası: " . $e->getMessage());
        }

        // Toplam aktarılan miktarı güncelle
        $totalTransferredQuantity += $transferMiktar;
        $processedItems++;
        
        // Ürünün toplam stok durumunu güncelle
        updateTotalStock($urunId, $conn);
    }

    if ($processedItems === 0) {
        error_log("İşlenen ürün sayısı: 0");
        throw new Exception("Hiçbir ürün transfer edilmedi. Lütfen ürün seçtiğinizden ve transfer miktarı girdiğinizden emin olun.");
    }

    // *** YENİ DURUM HESAPLAMA MANTIGI ***
    // Fatura miktarlarını hesapla ve durumu belirle
    $invoiceQuantities = calculateInvoiceQuantitiesWithNewLogic($fatura_id, $conn);
    if (!$invoiceQuantities['success']) {
        throw new Exception("Fatura miktarları hesaplanamadı: " . $invoiceQuantities['message']);
    }

    // Fatura durumunu güncelle
    try {
        $stmt = $conn->prepare("
            UPDATE alis_faturalari 
            SET durum = ?, 
                aktarim_tarihi = NOW(),
                aktarilan_miktar = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $invoiceQuantities['durum'], 
            $invoiceQuantities['aktarilan_miktar'], 
            $fatura_id
        ]);
        error_log("Fatura durumu güncellendi: " . $invoiceQuantities['durum']);
    } catch (Exception $e) {
        error_log("Fatura durumu güncelleme hatası: " . $e->getMessage());
        throw new Exception("Fatura durumu güncelleme hatası: " . $e->getMessage());
    }

    // Aktarım kaydını ekle
    try {
        $stmt = $conn->prepare("
            INSERT INTO alis_fatura_aktarim (
                fatura_id,
                magaza_id,
                depo_id,
                aktarim_tarihi,
                kullanici_id
            ) VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $fatura_id,
            $hedef_tipi === 'magaza' ? $hedef_id : null,
            $hedef_tipi === 'depo' ? $hedef_id : null,
            $_SESSION['user_id'] ?? null
        ]);
        error_log("Aktarım kaydı eklendi.");
    } catch (Exception $e) {
        error_log("Aktarım kaydı ekleme hatası: " . $e->getMessage());
        throw new Exception("Aktarım kaydı ekleme hatası: " . $e->getMessage());
    }

    // İşlemi tamamla
    $conn->commit();
    $hedefTipiAdi = $hedef_tipi === 'magaza' ? 'mağazaya' : 'depoya';
    error_log("Transfer işlemi başarıyla tamamlandı. Aktarılan Toplam Miktar: $totalTransferredQuantity");

    echo json_encode([
        'success' => true,
        'message' => "Ürünler başarıyla $hedefTipiAdi aktarıldı",
        'aktarilan_miktar' => $totalTransferredQuantity,
        'islem_bilgisi' => [
            'fatura_id' => $fatura_id,
            'hedef_tipi' => $hedef_tipi,
            'hedef_id' => $hedef_id,
            'urun_sayisi' => $processedItems,
            'durum' => $invoiceQuantities['durum']
        ]
    ]);

} catch (Exception $e) {
    error_log('Transfer hatası: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if ($conn->inTransaction()) {
        $conn->rollBack();
        error_log("Transaction geri alındı");
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// *** YENİ FONKSİYON: Gelişmiş durum hesaplama ***
function calculateInvoiceQuantitiesWithNewLogic($fatura_id, $conn) {
    try {
        // Faturadaki tüm ürünlerin toplam ve aktarılan miktarlarını hesapla
        $stmt = $conn->prepare("
            SELECT 
                SUM(afd.miktar) as toplam_miktar,
                COALESCE(SUM(COALESCE(aktarim.aktarilan_miktar, 0)), 0) as toplam_aktarilan
            FROM alis_fatura_detay afd
            LEFT JOIN (
                SELECT 
                    fatura_id,
                    urun_id,
                    SUM(miktar) as aktarilan_miktar
                FROM alis_fatura_detay_aktarim 
                WHERE fatura_id = ?
                GROUP BY fatura_id, urun_id
            ) as aktarim ON afd.fatura_id = aktarim.fatura_id AND afd.urun_id = aktarim.urun_id
            WHERE afd.fatura_id = ?
        ");
        $stmt->execute([$fatura_id, $fatura_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return ['success' => false, 'message' => 'Fatura ürünleri bulunamadı'];
        }
        
        $toplamMiktar = floatval($result['toplam_miktar']);
        $toplamAktarilan = floatval($result['toplam_aktarilan']);
        
        // Durum belirleme mantığı
        if ($toplamAktarilan == 0) {
            $durum = 'aktarim_bekliyor';
        } elseif ($toplamAktarilan >= $toplamMiktar) {
            $durum = 'aktarildi'; // Tamamen aktarıldı
        } else {
            $durum = 'kismi_aktarildi'; // Kısmen aktarıldı
        }
        
        return [
            'success' => true,
            'durum' => $durum,
            'toplam_miktar' => $toplamMiktar,
            'aktarilan_miktar' => $toplamAktarilan
        ];
        
    } catch (Exception $e) {
        error_log('Durum hesaplama hatası: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}