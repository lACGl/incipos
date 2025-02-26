<?php
session_start();
require_once '../db_connection.php';

// Hata raporlama ayarları
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hataları gösterme ama logla

// JSON header
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Gelen veriyi logla
    $rawInput = file_get_contents('php://input');
    error_log('Gelen Raw Veri: ' . $rawInput);
    
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

    $fatura_id = $data['fatura_id'];
    $hedef_id = $data['hedef_id'];
    $hedef_tipi = $data['hedef_tipi']; // 'magaza' veya 'depo'
    $products = $data['products'];

    // Hedef tipi kontrolü
    if (!in_array($hedef_tipi, ['magaza', 'depo'])) {
        throw new Exception('Geçersiz hedef tipi: ' . $hedef_tipi);
    }

    // Fatura durumunu kontrol et
    $checkStmt = $conn->prepare("SELECT durum FROM alis_faturalari WHERE id = ?");
    $checkStmt->execute([$fatura_id]);
    $faturaData = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$faturaData) {
        throw new Exception('Fatura bulunamadı');
    }
    
    if ($faturaData['durum'] === 'aktarildi') {
        throw new Exception('Bu fatura zaten tamamen aktarılmış');
    }

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
    
    // Seçili ürünleri işle
    foreach ($products as $product) {
        // Ürün verilerini detaylı logla
        error_log("İşlenen ürün: " . print_r($product, true));
        
        // 'selected' değeri yoksa veya false ise, skip
        if (!isset($product['selected']) || $product['selected'] === false) {
            error_log("Ürün seçili değil, atlanıyor");
            continue;
        }

        // Transfer miktarını kontrol et
        $transferMiktar = floatval($product['transfer_miktar'] ?? 0);
        if ($transferMiktar <= 0) {
            error_log("Transfer miktarı geçersiz, atlanıyor: " . $transferMiktar);
            continue;
        }

        // Ürün ID'sini kontrol et
        if (!isset($product['urun_id']) || empty($product['urun_id'])) {
            error_log("Ürün ID'si bulunamadı");
            throw new Exception("Ürün ID'si bulunamadı");
        }
        
        error_log("Ürün bilgileri sorgulanıyor. Ürün ID: " . $product['urun_id'] . ", Fatura ID: " . $fatura_id);
        
        // Ürün bilgilerini al
        $stmt = $conn->prepare("
            SELECT 
                us.id,
                us.barkod,
                us.ad,
                us.satis_fiyati as mevcut_satis_fiyati,
                afd.birim_fiyat,
                afd.miktar as fatura_miktar,
                COALESCE((
                    SELECT SUM(afda.miktar) 
                    FROM alis_fatura_detay_aktarim afda 
                    WHERE afda.fatura_id = ? 
                    AND afda.urun_id = us.id
                ), 0) as aktarilan_miktar
            FROM urun_stok us
            JOIN alis_fatura_detay afd ON afd.urun_id = us.id
            WHERE us.id = ? AND afd.fatura_id = ?
        ");
        $stmt->execute([$fatura_id, $product['urun_id'], $fatura_id]);
        $urun = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$urun) {
            throw new Exception("Ürün bilgisi bulunamadı: " . $product['urun_id']);
        }

        error_log("Ürün işleniyor: ID: {$urun['id']}, Barkod: {$urun['barkod']}, Ad: {$urun['ad']}, Transfer Miktar: $transferMiktar");

        // Kalan aktarılabilir miktarı kontrol et
        $kalanMiktar = $urun['fatura_miktar'] - $urun['aktarilan_miktar'];
        if ($transferMiktar > $kalanMiktar) {
            throw new Exception("'{$urun['ad']}' için aktarılmak istenen miktar ($transferMiktar) kalan miktardan ($kalanMiktar) fazla olamaz.");
        }

        // GENEL ÜRÜN FİYAT GÜNCELLEMESİ
        // Her zaman ana tablodaki satış fiyatını güncelle (mevcut değerden farklı olup olmadığına bakmadan)
        if (isset($product['satis_fiyati'])) {
            $stmt = $conn->prepare("
                UPDATE urun_stok 
                SET satis_fiyati = ? 
                WHERE id = ?
            ");
            $stmt->execute([$product['satis_fiyati'], $product['urun_id']]);
            error_log("Ürün satış fiyatı güncellendi. Ürün ID: {$product['urun_id']}, Yeni Fiyat: {$product['satis_fiyati']}");
            
            // Eğer fiyat değiştiyse geçmişe kaydet
            if ($product['satis_fiyati'] != $urun['mevcut_satis_fiyati']) {
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
                        'Faturadan aktarım sırasında fiyat güncelleme',
                        ?,
                        ?,
                        NOW()
                    )
                ");
                $stmt->execute([
                    $product['urun_id'],
                    $urun['mevcut_satis_fiyati'],
                    $product['satis_fiyati'],
                    $fatura_id,
                    $_SESSION['user_id'] ?? null
                ]);
                error_log("Fiyat değişikliği kaydedildi.");
            }
        }

        // Hedef türüne göre farklı işlemler yap
        if ($hedef_tipi === 'magaza') {
            // Mağaza stoğunu güncelle - Fiyat bilgisiyle birlikte
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
                $product['satis_fiyati'], // Satış fiyatını ekliyoruz
                $transferMiktar,
                $product['satis_fiyati']  // Güncelleme için de satış fiyatını ekliyoruz
            ]);
            $updatedRows = $stmt->rowCount();
            error_log("Mağaza stoğu güncellendi. Etkilenen satır: $updatedRows");

            // Stok hareketi ekle - Mağazaya giriş
            $stmt = $conn->prepare("
                INSERT INTO stok_hareketleri (
                    urun_id, 
                    miktar, 
                    hareket_tipi, 
                    aciklama,
                    tarih, 
                    kullanici_id, 
                    magaza_id, 
                    maliyet, 
                    satis_fiyati
                ) VALUES (
                    ?, ?, 'giris', 'Faturadan mağazaya aktarım',
                    NOW(), ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $product['urun_id'],
                $transferMiktar,
                $_SESSION['user_id'] ?? null,
                $hedef_id,
                $urun['birim_fiyat'],
                $product['satis_fiyati'] // Hareketlerde fiyat bilgisi tutmak için
            ]);
            error_log("Mağazaya giriş hareketi kaydedildi. Ürün ID: {$product['urun_id']}, Miktar: $transferMiktar");
        } else { // hedef_tipi === 'depo'
            // Depo stoğunu güncelle
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
                $product['urun_id'],
                $transferMiktar,
                $transferMiktar
            ]);
            $updatedRows = $stmt->rowCount();
            error_log("Depo stoğu güncellendi. Etkilenen satır: $updatedRows");

            // Stok hareketi ekle - Depoya giriş
            $stmt = $conn->prepare("
                INSERT INTO stok_hareketleri (
                    urun_id, 
                    miktar, 
                    hareket_tipi, 
                    aciklama,
                    tarih, 
                    kullanici_id, 
                    depo_id, 
                    maliyet, 
                    satis_fiyati
                ) VALUES (
                    ?, ?, 'giris', 'Faturadan depoya aktarım',
                    NOW(), ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $product['urun_id'],
                $transferMiktar,
                $_SESSION['user_id'] ?? null,
                $hedef_id,
                $urun['birim_fiyat'],
                $product['satis_fiyati'] // Stok hareketlerinde fiyat bilgisini tutmak yararlı olabilir
            ]);
            error_log("Depoya giriş hareketi kaydedildi. Ürün ID: {$product['urun_id']}, Miktar: $transferMiktar");
        }

        // Aktarım kaydını ekle
        $stmt = $conn->prepare("
            INSERT INTO alis_fatura_detay_aktarim (
                fatura_id, 
                urun_id, 
                miktar, 
                aktarim_tarihi, 
                magaza_id,
                depo_id
            ) VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([
            $fatura_id,
            $product['urun_id'],
            $transferMiktar,
            $hedef_tipi === 'magaza' ? $hedef_id : null,
            $hedef_tipi === 'depo' ? $hedef_id : null
        ]);
        error_log("Aktarım kaydı eklendi. Fatura ID: $fatura_id, Ürün ID: {$product['urun_id']}, Miktar: $transferMiktar");

        // Toplam aktarılan miktarı güncelle
        $totalTransferredQuantity += $transferMiktar;
        $processedItems++;
    }

    if ($processedItems === 0) {
        error_log("İşlenen ürün sayısı: 0");
        error_log("Gelen ürünler: " . print_r($products, true));
        error_log("JSON veri: " . $rawInput);
        throw new Exception("Hiçbir ürün transfer edilmedi. Lütfen ürün seçtiğinizden ve transfer miktarı girdiğinizden emin olun.");
    }

    // Fatura toplam ve aktarılan miktarlarını hesapla
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
    $stmt->execute([$fatura_id, $fatura_id]);
    $miktarlar = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fatura durumunu belirle
    $yeniDurum = 'urun_girildi'; // Varsayılan
    if ($miktarlar) {
        if ($miktarlar['aktarilan_miktar'] >= $miktarlar['toplam_miktar']) {
            $yeniDurum = 'aktarildi';
        } else if ($miktarlar['aktarilan_miktar'] > 0) {
            $yeniDurum = 'kismi_aktarildi';
        }
    }

    error_log("Fatura durumu güncelleniyor. Yeni Durum: $yeniDurum, Toplam Miktar: {$miktarlar['toplam_miktar']}, Aktarılan Miktar: {$miktarlar['aktarilan_miktar']}");

    // Fatura durumunu güncelle
    $stmt = $conn->prepare("
        UPDATE alis_faturalari 
        SET durum = ?, 
            aktarim_tarihi = NOW(),
            aktarilan_miktar = ?
        WHERE id = ?
    ");
    $stmt->execute([$yeniDurum, $miktarlar['aktarilan_miktar'], $fatura_id]);
    error_log("Fatura durumu güncellendi. Etkilenen satır: " . $stmt->rowCount());

    // Ürün ana stok miktarlarını güncelle
    foreach ($products as $product) {
        if (!isset($product['selected']) || !$product['selected']) {
            continue;
        }
        
        $transferMiktar = floatval($product['transfer_miktar'] ?? 0);
        if ($transferMiktar <= 0) {
            continue;
        }
        
        // Ürünün stok durumunu hesapla
        $stmt = $conn->prepare("
            SELECT 
                barkod 
            FROM urun_stok 
            WHERE id = ?
        ");
        $stmt->execute([$product['urun_id']]);
        $urn = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($urn) {
            $stmt = $conn->prepare("
                SELECT 
                    COALESCE(SUM(ds.stok_miktari), 0) as depo_stok,
                    COALESCE((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = ?), 0) as magaza_stok
                FROM depo_stok ds
                RIGHT JOIN urun_stok us ON ds.urun_id = us.id
                WHERE us.id = ?
                GROUP BY us.id
            ");
            $stmt->execute([$urn['barkod'], $product['urun_id']]);
            $stokBilgisi = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stokBilgisi) {
                // Eğer hiç stok kaydı yoksa, sadece magaza stoğunu al
                $stmt = $conn->prepare("
                    SELECT 
                        0 as depo_stok,
                        COALESCE(SUM(ms.stok_miktari), 0) as magaza_stok
                    FROM magaza_stok ms
                    WHERE ms.barkod = ?
                ");
                $stmt->execute([$urn['barkod']]);
                $stokBilgisi = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            $toplamStok = ($stokBilgisi['depo_stok'] ?? 0) + ($stokBilgisi['magaza_stok'] ?? 0);
            
            // Ana ürün tablosundaki stok miktarını güncelle
            $stmt = $conn->prepare("
                UPDATE urun_stok 
                SET stok_miktari = ?
                WHERE id = ?
            ");
            $stmt->execute([$toplamStok, $product['urun_id']]);
            error_log("Ürün ana stok miktarı güncellendi. ID: {$product['urun_id']}, Yeni Miktar: $toplamStok");
        }
    }

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
            'durum' => $yeniDurum
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