<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';
require_once __DIR__ . '/../stock_functions.php';

// CORS ayarları
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = [
    'success' => false,
    'message' => '',
    'invoiceId' => null
];

// POST verilerini al
$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    try {
        $conn->beginTransaction();
        
        // Satış faturası oluştur - Eksik alanları ekledik
        $insertInvoice = "INSERT INTO satis_faturalari (
                            fatura_turu, 
                            magaza, 
                            fatura_seri,           /* Eklendi */
                            fatura_no, 
                            fatura_tarihi, 
                            toplam_tutar, 
                            personel, 
                            musteri_id, 
                            kdv_tutari,            /* Eklendi */
                            indirim_tutari, 
                            net_tutar, 
                            odeme_turu, 
                            islem_turu,
                            kredi_karti_banka,     /* Eklendi */
                            aciklama
                          ) VALUES (
                            'perakende', 
                            :magaza, 
                            :fatura_seri,          /* Eklendi */
                            :fatura_no, 
                            NOW(), 
                            :toplam_tutar, 
                            :personel, 
                            :musteri_id, 
                            :kdv_tutari,           /* Eklendi */
                            :indirim_tutari, 
                            :net_tutar, 
                            :odeme_turu, 
                            :islem_turu,
                            :kredi_karti_banka,    /* Eklendi */
                            :aciklama
                          )";
        
        $stmt = $conn->prepare($insertInvoice);
        
        // Toplam fiyatları hesapla
        $araToplam = 0;
        $toplamIndirim = 0;
        
        // KDV hesaplama - YENİ
        $toplamKdvTutari = 0;
        
        // Sepetteki her ürün için orijinal ve indirimli fiyat farklarını hesapla
        foreach ($input['sepet'] as $item) {
            // Ürünün normal fiyatını al (indirimli olmayan hali)
            $normalFiyat = isset($item['original_price']) ? 
                           $item['original_price'] : 
                           $item['birim_fiyat'];
                           
            // Gerçek birim fiyat (indirimli veya normal)
            $gercekBirimFiyat = $item['birim_fiyat'];
            
            // Ara toplamı orijinal fiyatlar üzerinden hesapla
            $araToplam += $normalFiyat * $item['miktar'];
            
            // Eğer indirimli fiyat varsa, indirim tutarını hesapla
            $birimIndirim = $normalFiyat - $gercekBirimFiyat;
            if ($birimIndirim > 0) {
                $toplamIndirim += $birimIndirim * $item['miktar'];
            }
            
            // Ayrıca manuel indirim varsa ekle
            if (isset($item['indirim']) && $item['indirim'] > 0) {
                if ($item['indirim_turu'] === 'yuzde') {
                    $manuelIndirim = ($gercekBirimFiyat * $item['miktar']) * ($item['indirim'] / 100);
                } else {
                    $manuelIndirim = $item['indirim'];
                }
                $toplamIndirim += $manuelIndirim;
            }
            
            // KDV hesaplama - Her ürün için KDV tutarını hesapla ve topla
            $kdvOrani = isset($item['kdv_orani']) ? floatval($item['kdv_orani']) : 0;
            
            // İndirimli tutarı al
            $itemTotal = $gercekBirimFiyat * $item['miktar'];
            
            // Manuel indirim varsa düş
            if (isset($item['indirim']) && $item['indirim'] > 0) {
                if ($item['indirim_turu'] === 'yuzde') {
                    $itemTotal *= (1 - ($item['indirim'] / 100));
                } else {
                    $itemTotal -= $item['indirim'];
                }
            }
            
            // KDV tutarını hesapla: (Toplam / (1 + KDV Oranı/100)) * (KDV Oranı/100)
            // Alternatif KDV hesaplayış = KDV dahil fiyattan KDV'yi ayırma formülü
            if ($kdvOrani > 0) {
                $kdvTutari = ($itemTotal / (1 + ($kdvOrani / 100))) * ($kdvOrani / 100);
                $toplamKdvTutari += $kdvTutari;
            }
        }
        
        // Net tutarı hesapla
        $netTutar = $araToplam - $toplamIndirim;
        
        // Fatura seri numarasını oluştur - Fiş numarasının ilk kısmını kullan veya varsayılan
        $faturaSeri = 'POS';
        if (isset($input['fisNo']) && strpos($input['fisNo'], '-') !== false) {
            $faturaSeri = explode('-', $input['fisNo'])[0];
        }
        
        // Fatura parametrelerini bind et
        $stmt->bindParam(':fatura_seri', $faturaSeri); // Eklendi
        $stmt->bindParam(':magaza', $input['magazaId']);
        $stmt->bindParam(':fatura_no', $input['fisNo']);
        $stmt->bindParam(':toplam_tutar', $araToplam);
        $stmt->bindParam(':personel', $input['kasiyerId']);
        $stmt->bindParam(':musteri_id', $input['musteriId']);
        $stmt->bindParam(':kdv_tutari', $toplamKdvTutari); // Eklendi
        $stmt->bindParam(':indirim_tutari', $toplamIndirim);
        $stmt->bindParam(':net_tutar', $netTutar);
        $stmt->bindParam(':odeme_turu', $input['odemeYontemi']);
        $stmt->bindParam(':islem_turu', $input['islemTuru']);
        
        // Kredi kartı işlemiyse banka bilgisini ekle
        $krediKartiBanka = null;
        if ($input['odemeYontemi'] === 'kredi_karti' && isset($input['banka'])) {
            $krediKartiBanka = $input['banka'];
        }
        $stmt->bindParam(':kredi_karti_banka', $krediKartiBanka); // Eklendi
        
        // Açıklama alanı
        $aciklama = "POS üzerinden satış";
        // Ödeme yöntemine göre ek bilgiler
        if ($input['odemeYontemi'] === 'kredi_karti' && isset($input['banka'])) {
            $aciklama .= " - " . $input['banka'];
            if (isset($input['taksit']) && $input['taksit'] > 1) {
                $aciklama .= " " . $input['taksit'] . " taksit";
            }
        } elseif ($input['odemeYontemi'] === 'nakit' && isset($input['alinanTutar'])) {
            $aciklama .= " - Alınan: " . $input['alinanTutar'] . " TL";
        }
        
        $stmt->bindParam(':aciklama', $aciklama);
        $stmt->execute();
        
        $invoiceId = $conn->lastInsertId();
        
        // Fatura detaylarını ekle
        foreach ($input['sepet'] as $item) {
            // Orijinal ve indirimli birim fiyatları kontrol et
            $normalBirimFiyat = isset($item['original_price']) ? 
                               $item['original_price'] : 
                               $item['birim_fiyat'];
                               
            $indirimOrani = 0;
            
            // Birim bazında indirim oranını hesapla
            if ($normalBirimFiyat > 0 && $normalBirimFiyat > $item['birim_fiyat']) {
                $indirimOrani = (($normalBirimFiyat - $item['birim_fiyat']) / $normalBirimFiyat) * 100;
            }
            
            // Eğer manuel indirim varsa ekle
            if (isset($item['indirim']) && $item['indirim'] > 0) {
                if ($item['indirim_turu'] === 'yuzde') {
                    $indirimOrani += $item['indirim'];
                } else {
                    // Tutar bazlı indirimi orana çevir
                    $kalemToplam = $item['birim_fiyat'] * $item['miktar'];
                    if ($kalemToplam > 0) {
                        $indirimOrani += ($item['indirim'] / $kalemToplam) * 100;
                    }
                }
            }
            
            // KDV oranını al veya varsayılan değer kullan
            $kdvOrani = isset($item['kdv_orani']) ? floatval($item['kdv_orani']) : 0;
            
            $insertDetail = "INSERT INTO satis_fatura_detay (
                                fatura_id, 
                                urun_id, 
                                miktar, 
                                birim_fiyat, 
                                kdv_orani,             /* Eklendi */
                                indirim_orani, 
                                toplam_tutar
                             ) VALUES (
                                :fatura_id, 
                                :urun_id, 
                                :miktar, 
                                :birim_fiyat, 
                                :kdv_orani,            /* Eklendi */
                                :indirim_orani, 
                                :toplam_tutar
                             )";
            
            $stmt = $conn->prepare($insertDetail);
            $stmt->bindParam(':fatura_id', $invoiceId);
            $stmt->bindParam(':urun_id', $item['id']);
            $stmt->bindParam(':miktar', $item['miktar']);
            $stmt->bindParam(':birim_fiyat', $normalBirimFiyat); // Orijinal fiyat
            $stmt->bindParam(':kdv_orani', $kdvOrani); // Eklendi
            $stmt->bindParam(':indirim_orani', $indirimOrani);
            
            // Toplam tutarı hesapla (indirimli birim fiyat x miktar)
            $itemTotal = $item['birim_fiyat'] * $item['miktar'];
            
            // Manuel indirim varsa düş
            if (isset($item['indirim']) && $item['indirim'] > 0) {
                if ($item['indirim_turu'] === 'yuzde') {
                    $itemTotal *= (1 - ($item['indirim'] / 100));
                } else {
                    $itemTotal -= $item['indirim'];
                }
            }
            
            $stmt->bindParam(':toplam_tutar', $itemTotal);
            $stmt->execute();
            
            // Stok güncelleme
            $stokHareket = [
                'urun_id' => $item['id'],
                'miktar' => $item['miktar'] * ($input['islemTuru'] === 'satis' ? -1 : 1), // İade ise ters işaret
                'hareket_tipi' => $input['islemTuru'] === 'satis' ? 'cikis' : 'giris',
                'aciklama' => 'POS ' . ($input['islemTuru'] === 'satis' ? 'satış' : 'iade'),
                'belge_no' => $input['fisNo'],
                'tarih' => date('Y-m-d H:i:s'),
                'kullanici_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
                'magaza_id' => $input['magazaId'],
                'satis_fiyati' => $item['birim_fiyat']
            ];
            
            // Stok hareketini ekle
            addStockMovement($stokHareket, $conn);
            
            // Mağaza stoğunu güncelle
            if (isset($item['barkod']) && !empty($item['barkod'])) {
                $stokDegisim = $item['miktar'] * ($input['islemTuru'] === 'satis' ? -1 : 1);
                updateMagazaStock($item['barkod'], $input['magazaId'], $stokDegisim, $conn);
            }
        }
        
        // Müşteri işlemleri
        if ($input['musteriId']) {
            // Puan kullanımı
            if (isset($input['kullanilacakPuan']) && $input['kullanilacakPuan'] > 0) {
                $insertPuanKullanim = "INSERT INTO puan_harcama (
                                        fatura_id, 
                                        musteri_id, 
                                        harcanan_puan, 
                                        tarih
                                    ) VALUES (
                                        :fatura_id, 
                                        :musteri_id, 
                                        :harcanan_puan, 
                                        NOW()
                                    )";
                
                $stmt = $conn->prepare($insertPuanKullanim);
                $stmt->bindParam(':fatura_id', $invoiceId);
                $stmt->bindParam(':musteri_id', $input['musteriId']);
                $stmt->bindParam(':harcanan_puan', $input['kullanilacakPuan']);
                $stmt->execute();
                
                // Müşteri puan bakiyesini güncelle
                $updatePuan = "UPDATE musteri_puanlar SET 
                               puan_bakiye = puan_bakiye - :kullanilan_puan,
                               son_alisveris_tarihi = NOW()
                               WHERE musteri_id = :musteri_id";
                
                $stmt = $conn->prepare($updatePuan);
                $stmt->bindParam(':kullanilan_puan', $input['kullanilacakPuan']);
                $stmt->bindParam(':musteri_id', $input['musteriId']);
                $stmt->execute();
            }
            
            // Puan kazanımı (sadece satış işleminde)
            if ($input['islemTuru'] === 'satis') {
                // Müşteri puan oranını al
                $queryPuanOran = "SELECT puan_oran FROM musteri_puanlar WHERE musteri_id = :musteri_id";
                $stmt = $conn->prepare($queryPuanOran);
                $stmt->bindParam(':musteri_id', $input['musteriId']);
                $stmt->execute();
                $puanOran = $stmt->fetchColumn();
                
                if ($puanOran) {
                    // Puan hesapla (net tutar üzerinden)
                    $kazanilanPuan = $netTutar * ($puanOran / 100);
                    
                    // Puan kaydını ekle
                    $insertPuanKazanim = "INSERT INTO puan_kazanma (
                                            fatura_id, 
                                            musteri_id, 
                                            kazanilan_puan, 
                                            odeme_tutari, 
                                            tarih
                                        ) VALUES (
                                            :fatura_id, 
                                            :musteri_id, 
                                            :kazanilan_puan, 
                                            :odeme_tutari, 
                                            NOW()
                                        )";
                    
                    $stmt = $conn->prepare($insertPuanKazanim);
                    $stmt->bindParam(':fatura_id', $invoiceId);
                    $stmt->bindParam(':musteri_id', $input['musteriId']);
                    $stmt->bindParam(':kazanilan_puan', $kazanilanPuan);
                    $stmt->bindParam(':odeme_tutari', $netTutar);
                    $stmt->execute();
                    
                    // Müşteri puan bakiyesini güncelle
                    $updatePuan = "UPDATE musteri_puanlar SET 
                                   puan_bakiye = puan_bakiye + :kazanilan_puan,
                                   son_alisveris_tarihi = NOW()
                                   WHERE musteri_id = :musteri_id";
                    
                    $stmt = $conn->prepare($updatePuan);
                    $stmt->bindParam(':kazanilan_puan', $kazanilanPuan);
                    $stmt->bindParam(':musteri_id', $input['musteriId']);
                    $stmt->execute();
                }
            }
        }
        
        // Borç kaydı (Ödeme yöntemi 'borc' ise)
        if ($input['odemeYontemi'] === 'borc' && $input['musteriId']) {
            $insertBorc = "INSERT INTO musteri_borclar (
                            musteri_id, 
                            toplam_tutar,
                            borc_tarihi, 
                            fis_no, 
                            magaza_id
                        ) VALUES (
                            :musteri_id, 
                            :toplam_tutar,
                            NOW(), 
                            :fis_no, 
                            :magaza_id
                        )";
            
            $stmt = $conn->prepare($insertBorc);
            $stmt->bindParam(':musteri_id', $input['musteriId']);
            $stmt->bindParam(':toplam_tutar', $netTutar);
            $stmt->bindParam(':fis_no', $input['fisNo']);
            $stmt->bindParam(':magaza_id', $input['magazaId']);
            $stmt->execute();
            
            // Borç detaylarını ekle
            $borcId = $conn->lastInsertId();
            
            foreach ($input['sepet'] as $item) {
                $insertBorcDetay = "INSERT INTO musteri_borc_detaylar (
                                    borc_id, 
                                    urun_adi, 
                                    miktar, 
                                    tutar, 
                                    urun_id
                                ) VALUES (
                                    :borc_id, 
                                    :urun_adi, 
                                    :miktar, 
                                    :tutar, 
                                    :urun_id
                                )";
                
                $stmt = $conn->prepare($insertBorcDetay);
                $stmt->bindParam(':borc_id', $borcId);
                $stmt->bindParam(':urun_adi', $item['ad']);
                $stmt->bindParam(':miktar', $item['miktar']);
                $stmt->bindParam(':tutar', $item['toplam']);
                $stmt->bindParam(':urun_id', $item['id']);
                $stmt->execute();
            }
        }
        
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'Satış başarıyla kaydedildi';
        $response['invoiceId'] = $invoiceId;
    } catch (Exception $e) {
        $conn->rollBack();
        $response['message'] = 'Hata: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Geçersiz veri formatı';
}

echo json_encode($response);
?>