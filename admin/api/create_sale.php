<?php
session_start();
require_once '../db_connection.php';
require_once '../helpers/stock_functions.php';  


// JSON veri tipinde yanıt döndüreceğimizi belirt
header('Content-Type: application/json');

// Hata raporlama ayarları
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hataları gösterme ama logla
ini_set('log_errors', 1);

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

// Gelen JSON verilerini logla (hata ayıklama için)
$raw_post_data = file_get_contents('php://input');
error_log('Gelen POS verisi: ' . $raw_post_data);

// Hata yakalama
try {
    // JSON verilerini al
    $inputData = json_decode($raw_post_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON çözme hatası: ' . json_last_error_msg());
    }
    
    // Veri kontrolü yap
    if (!$inputData || !isset($inputData['sepet']) || empty($inputData['sepet'])) {
        throw new Exception('Geçersiz veri formatı veya boş sepet');
    }
    
    // Satış verilerini hazırla
    $fisNo = $inputData['fisNo'] ?? null;
    $islemTuru = $inputData['islemTuru'] ?? 'satis';
    $magazaId = $inputData['magazaId'] ?? null;
    $kasiyerId = $inputData['kasiyerId'] ?? null;
    $musteriId = $inputData['musteriId'] ?? null;
    $odemeYontemi = $inputData['odemeYontemi'] ?? 'nakit';
    $kullanilacakPuan = floatval($inputData['kullanilacakPuan'] ?? 0);
    $sepet = $inputData['sepet'];
    $genelToplam = floatval($inputData['genelToplam'] ?? 0);
    
    // Ödeme yöntemine özel veriler
    $alinanTutar = isset($inputData['alinanTutar']) ? floatval($inputData['alinanTutar']) : 0;
    $banka = $inputData['banka'] ?? null;
    $taksit = $inputData['taksit'] ?? '1';
    
    // Zorunlu alanları kontrol et
    if (!$fisNo || !$magazaId || !$kasiyerId || !$odemeYontemi) {
        throw new Exception('Zorunlu alanlar eksik (Fiş No, Mağaza, Kasiyer, Ödeme Yöntemi)');
    }
    
    // Sepet toplamlarını hesapla
    $toplamTutar = 0;
    $kdvTutari = 0;
    $indirimTutari = 0;
    $sepetBrutTutar = 0;
    
    foreach ($sepet as $item) {
        $brutTutar = $item['birim_fiyat'] * $item['miktar'];
        $sepetBrutTutar += $brutTutar;
        
        // İndirim varsa
        if (isset($item['indirim']) && $item['indirim'] > 0) {
            if ($item['indirim_turu'] === 'yuzde') {
                $itemIndirim = $brutTutar * ($item['indirim'] / 100);
            } else {
                $itemIndirim = $item['indirim'];
            }
            $indirimTutari += $itemIndirim;
        }
        
        $netTutar = $item['toplam'];
        $toplamTutar += $netTutar;
        
        // KDV hesaplama
        $kdvOrani = isset($item['kdv_orani']) ? floatval($item['kdv_orani']) : 18;
        $itemKdv = $netTutar - ($netTutar / (1 + ($kdvOrani / 100)));
        $kdvTutari += $itemKdv;
    }
    
    // Puan kullanılıyorsa toplam tutardan düş
    if ($kullanilacakPuan > 0) {
        $toplamTutar = max(0, $toplamTutar - $kullanilacakPuan);
    }
    
    // Transaction başlat
    $conn->beginTransaction();
    
    // Satış faturası oluştur
    $stmt = $conn->prepare("
        INSERT INTO satis_faturalari (
            fatura_turu, magaza, fatura_seri, fatura_no, 
            fatura_tarihi, toplam_tutar, personel, 
            musteri, kdv_tutari, indirim_tutari, 
            net_tutar, odeme_turu, islem_turu, 
            aciklama, kredi_karti_banka, musteri_id
        ) VALUES (
            'perakende', ?, 'POS', ?, 
            NOW(), ?, ?, 
            ?, ?, ?, 
            ?, ?, ?, 
            ?, ?, ?
        )
    ");
    
    $stmt->execute([
        $magazaId,
        $fisNo,
        $toplamTutar,
        $kasiyerId,
        $musteriId,
        $kdvTutari,
        $indirimTutari,
        $toplamTutar - $kdvTutari, // Net tutar: toplam - kdv
        $odemeYontemi,
        $islemTuru,
        "POS satış sistemi",
        $odemeYontemi === 'kredi_karti' ? $banka : null,
        $musteriId
    ]);
    
    $faturaId = $conn->lastInsertId();
    
    // Satış detaylarını ekle
    $detayStmt = $conn->prepare("
        INSERT INTO satis_fatura_detay (
            fatura_id, urun_id, miktar, 
            birim_fiyat, kdv_orani, indirim_orani, 
            toplam_tutar
        ) VALUES (
            ?, ?, ?, 
            ?, ?, ?, 
            ?
        )
    ");
    
    foreach ($sepet as $item) {
        $urunId = $item['id'];
        $miktar = $item['miktar'];
        $birimFiyat = $item['birim_fiyat'];
        $kdvOrani = isset($item['kdv_orani']) ? floatval($item['kdv_orani']) : 18;
        
        // İndirim oranını hesapla
        $indirimOrani = 0;
        if (isset($item['indirim']) && $item['indirim'] > 0) {
            if ($item['indirim_turu'] === 'yuzde') {
                $indirimOrani = $item['indirim'];
            } else {
                // Tutar bazlı indirimi orana çevir
                $indirimOrani = ($item['indirim'] / ($birimFiyat * $miktar)) * 100;
            }
        }
        
        $detayStmt->execute([
            $faturaId,
            $urunId,
            $miktar,
            $birimFiyat,
            $kdvOrani,
            $indirimOrani,
            $item['toplam']
        ]);
        
        // Stok güncellemesi (stok_hareketleri tablosuna kayıt)
        $hareket_tipi = ($islemTuru === 'satis') ? 'cikis' : 'giris';
        $aciklama = ($islemTuru === 'satis') ? 'POS satış' : 'POS iade';
        
        $stokStmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi, 
                aciklama, belge_no, tarih, 
                kullanici_id, magaza_id, satis_fiyati
            ) VALUES (
                ?, ?, ?, 
                ?, ?, NOW(), 
                ?, ?, ?
            )
        ");
        
        $stokStmt->execute([
            $urunId,
            $miktar,
            $hareket_tipi,
            $aciklama,
            $fisNo,
            $_SESSION['user_id'] ?? null,
            $magazaId,
            $birimFiyat
        ]);
        
        // Mağaza stoğunu güncelle
        // Önce ürünün barkodunu al
        $barkodStmt = $conn->prepare("SELECT barkod FROM urun_stok WHERE id = ?");
        $barkodStmt->execute([$urunId]);
        $barkod = $barkodStmt->fetchColumn();
        
        if ($barkod) {
            if ($islemTuru === 'satis') {
                // Satış ise stok azalt
                $stokUpdateStmt = $conn->prepare("
                    UPDATE magaza_stok 
                    SET stok_miktari = stok_miktari - ?
                    WHERE magaza_id = ? AND barkod = ?
                ");
            } else {
                // İade ise stok artır
                $stokUpdateStmt = $conn->prepare("
                    UPDATE magaza_stok 
                    SET stok_miktari = stok_miktari + ?
                    WHERE magaza_id = ? AND barkod = ?
                ");
            }
            
            $stokUpdateStmt->execute([$miktar, $magazaId, $barkod]);
            
            // Eğer kayıt yapılamadıysa (satır yoksa), yeni kayıt ekle (iade durumunda)
            if ($islemTuru === 'iade' && $stokUpdateStmt->rowCount() === 0) {
                $stokInsertStmt = $conn->prepare("
                    INSERT INTO magaza_stok (
                        barkod, magaza_id, stok_miktari, 
                        satis_fiyati
                    ) VALUES (
                        ?, ?, ?, 
                        ?
                    )
                ");
                
                $stokInsertStmt->execute([
                    $barkod,
                    $magazaId,
                    $miktar,
                    $birimFiyat
                ]);
            }
			// Satış işleminden etkilenen tüm ürünlerin toplam stoklarını güncelle
			foreach ($sepet as $item) {
				$urunId = $item['id'];
				updateTotalStock($urunId, $conn);
			}
        }
    }
    
    // Kullanılan puanları düş ve kaydı oluştur
    if ($kullanilacakPuan > 0 && $musteriId) {
        // Müşteri puanlarını güncelle
        $puanUpdateStmt = $conn->prepare("
            UPDATE musteri_puanlar
            SET puan_bakiye = puan_bakiye - ?
            WHERE musteri_id = ?
        ");
        $puanUpdateStmt->execute([$kullanilacakPuan, $musteriId]);
        
        // Puan harcama kaydı ekle
        $puanHarcamaStmt = $conn->prepare("
            INSERT INTO puan_harcama (
                fatura_id, musteri_id, 
                harcanan_puan, tarih
            ) VALUES (
                ?, ?, 
                ?, NOW()
            )
        ");
        $puanHarcamaStmt->execute([
            $faturaId,
            $musteriId,
            $kullanilacakPuan
        ]);
    }
    
    // Kazanılan puanları hesapla ve ekle
    if ($musteriId && $toplamTutar > 0 && $islemTuru === 'satis') {
        // Müşteri puan oranını al
$puanOranStmt = $conn->prepare("
    SELECT puan_oran 
    FROM musteri_puanlar 
    WHERE musteri_id = ?
");
$puanOranStmt->execute([$musteriId]);
$puanOran = $puanOranStmt->fetchColumn() ?: 1; // Varsayılan oran 1

// Veritabanından gelen oran yüzde değeri olarak kabul edilir (5 ise %5)
// 100'e bölerek dönüştürme yapalım
$puanOran = $puanOran / 100;

// Kazanılan puan miktarı
$kazanilanPuan = $toplamTutar * $puanOran;
        
        // Müşteri puanlarını güncelle
        $puanUpdateStmt = $conn->prepare("
            UPDATE musteri_puanlar
            SET puan_bakiye = puan_bakiye + ?,
                son_alisveris_tarihi = NOW()
            WHERE musteri_id = ?
        ");
        $puanUpdateStmt->execute([$kazanilanPuan, $musteriId]);
        
        // Eğer kayıt yoksa yeni oluştur
        if ($puanUpdateStmt->rowCount() === 0) {
            $puanInsertStmt = $conn->prepare("
                INSERT INTO musteri_puanlar (
                    musteri_id, puan_bakiye, 
                    puan_oran, son_alisveris_tarihi
                ) VALUES (
                    ?, ?, 
                    ?, NOW()
                )
            ");
            $puanInsertStmt->execute([
                $musteriId,
                $kazanilanPuan,
                $puanOran
            ]);
        }
        
        // Puan kazanma kaydı ekle
        $puanKazanmaStmt = $conn->prepare("
            INSERT INTO puan_kazanma (
                fatura_id, musteri_id, 
                kazanilan_puan, odeme_tutari, 
                tarih
            ) VALUES (
                ?, ?, 
                ?, ?, 
                NOW()
            )
        ");
        $puanKazanmaStmt->execute([
            $faturaId,
            $musteriId,
            $kazanilanPuan,
            $toplamTutar
        ]);
    }
    
    // Borç ekleme işlemi (ödeme yöntemi borç ise)
    if ($odemeYontemi === 'borc' && $musteriId) {
        $borcStmt = $conn->prepare("
            INSERT INTO musteri_borclar (
                musteri_id, toplam_tutar, 
                indirim_tutari, borc_tarihi, 
                fis_no, odendi_mi, 
                olusturma_tarihi, magaza_id
            ) VALUES (
                ?, ?, 
                ?, NOW(), 
                ?, 0, 
                NOW(), ?
            )
        ");
        $borcStmt->execute([
            $musteriId,
            $toplamTutar,
            $indirimTutari,
            $fisNo,
            $magazaId
        ]);
        
        $borcId = $conn->lastInsertId();
        
        // Borç detaylarını ekle
        $borcDetayStmt = $conn->prepare("
            INSERT INTO musteri_borc_detaylar (
                borc_id, urun_adi, 
                miktar, tutar, 
                urun_id, olusturma_tarihi
            ) VALUES (
                ?, ?, 
                ?, ?, 
                ?, NOW()
            )
        ");
        
        foreach ($sepet as $item) {
            $borcDetayStmt->execute([
                $borcId,
                $item['ad'],
                $item['miktar'],
                $item['toplam'],
                $item['id']
            ]);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Satış başarıyla kaydedildi',
        'invoiceId' => $faturaId,
        'fisNo' => $fisNo
    ]);
    
} catch (Exception $e) {
    // Hata durumunda işlemi geri al
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log('POS Satış Hatası: ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Satış kaydedilirken bir hata oluştu: ' . $e->getMessage()
    ]);
}