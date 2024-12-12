<?php

function getVarsayilanStokLokasyonu($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT deger 
            FROM sistem_ayarlari 
            WHERE anahtar = 'varsayilan_stok_lokasyonu'
        ");
        $stmt->execute();
        $deger = $stmt->fetchColumn();

        // Eğer değer numerik değilse ve "magaza_" prefix'i varsa
        if ($deger && preg_match('/^magaza_(\d+)$/', $deger, $matches)) {
            return $matches[1]; // Sadece sayısal kısmı döndür
        }

        return $deger ?: 'depo';
    } catch (Exception $e) {
        error_log('Varsayılan stok lokasyonu hatası: ' . $e->getMessage());
        return 'depo';
    }
}

function stokEkle($conn, $urunId, $miktar, $birimFiyat, $satisFiyati = null, $lokasyon = null) {
    try {
        // Lokasyon belirtilmemişse varsayılanı al
        if ($lokasyon === null) {
            $lokasyon = getVarsayilanStokLokasyonu($conn);
        }

        // Lokasyon değeri "magaza_X" formatındaysa X'i al
        if (is_string($lokasyon) && preg_match('/^magaza_(\d+)$/', $lokasyon, $matches)) {
            $lokasyon = $matches[1];
        }
        
        // Önce ürün bilgilerini al
        $stmt = $conn->prepare("SELECT barkod FROM urun_stok WHERE id = ?");
        $stmt->execute([$urunId]);
        $urun = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$urun) {
            throw new Exception('Ürün bulunamadı');
        }

        if ($lokasyon === 'depo') {
            // Depoya ekle
            $stmt = $conn->prepare("
                INSERT INTO depo_stok (depo_id, urun_id, stok_miktari, son_guncelleme)
                VALUES (1, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    stok_miktari = stok_miktari + ?,
                    son_guncelleme = NOW()
            ");
            $stmt->execute([$urunId, $miktar, $miktar]);
            
            // Stok hareketi ekle
            $stmt = $conn->prepare("
                INSERT INTO stok_hareketleri (
                    urun_id, miktar, hareket_tipi, aciklama,
                    tarih, kullanici_id, depo_id, maliyet, satis_fiyati
                ) VALUES (
                    ?, ?, 'giris', 'Manuel stok girişi',
                    NOW(), ?, 1, ?, ?
                )
            ");
            $stmt->execute([
                $urunId, 
                $miktar, 
                $_SESSION['user_id'] ?? null,
                $birimFiyat,
                $satisFiyati ?? ($birimFiyat * 1.2)
            ]);
        } else {
            // Mağaza ID'sinin geçerli olduğunu kontrol et
            $stmt = $conn->prepare("SELECT id FROM magazalar WHERE id = ?");
            $stmt->execute([$lokasyon]);
            if (!$stmt->fetch()) {
                throw new Exception('Geçersiz mağaza ID: ' . $lokasyon);
            }

            // Mağazaya ekle
            $stmt = $conn->prepare("
                INSERT INTO magaza_stok (barkod, magaza_id, stok_miktari, satis_fiyati)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    stok_miktari = stok_miktari + ?,
                    satis_fiyati = ?
            ");
            $stmt->execute([
                $urun['barkod'],
                $lokasyon,
                $miktar,
                $satisFiyati ?? ($birimFiyat * 1.2),
                $miktar,
                $satisFiyati ?? ($birimFiyat * 1.2)
            ]);
            
            // Stok hareketi ekle
            $stmt = $conn->prepare("
                INSERT INTO stok_hareketleri (
                    urun_id, miktar, hareket_tipi, aciklama,
                    tarih, kullanici_id, magaza_id, maliyet, satis_fiyati
                ) VALUES (
                    ?, ?, 'giris', 'Manuel stok girişi',
                    NOW(), ?, ?, ?, ?
                )
            ");
            $stmt->execute([
                $urunId,
                $miktar,
                $_SESSION['user_id'] ?? null,
                $lokasyon,
                $birimFiyat,
                $satisFiyati ?? ($birimFiyat * 1.2)
            ]);
        }

        // Genel stok tablosunu güncelle
        $stmt = $conn->prepare("
            UPDATE urun_stok 
            SET stok_miktari = stok_miktari + ? 
            WHERE id = ?
        ");
        $stmt->execute([$miktar, $urunId]);

        return true;

    } catch (Exception $e) {
        throw $e;
    }
}