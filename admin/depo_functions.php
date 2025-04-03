<?php

function addToDepoStock($conn, $urunId, $miktar, $birimFiyat, $depoId = 1) {
    try {
        $conn->beginTransaction();

        // Önce depo stoğunu kontrol et
        $stmt = $conn->prepare("
            SELECT id, stok_miktari 
            FROM depo_stok 
            WHERE depo_id = ? AND urun_id = ?
        ");
        $stmt->execute([$depoId, $urunId]);
        $depoStok = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($depoStok) {
            // Mevcut stoku güncelle
            $stmt = $conn->prepare("
                UPDATE depo_stok 
                SET stok_miktari = stok_miktari + ?,
                    son_guncelleme = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$miktar, $depoStok['id']]);
        } else {
            // Yeni stok kaydı oluştur
            $stmt = $conn->prepare("
                INSERT INTO depo_stok (depo_id, urun_id, stok_miktari)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$depoId, $urunId, $miktar]);
        }

        // Stok hareketi ekle
        $stmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi, 
                aciklama, tarih, kullanici_id, 
                depo_id, maliyet
            ) VALUES (
                ?, ?, 'giris', 
                'Depoya giriş', NOW(), ?, 
                ?, ?
            )
        ");
        $stmt->execute([
            $urunId, 
            $miktar, 
            $_SESSION['user_id'] ?? null,
            $depoId,
            $birimFiyat
        ]);

        $conn->commit();
        return true;

    } catch (Exception $e) {
        $conn->rollBack();
        error_log('Depo stok hatası: ' . $e->getMessage());
        throw $e;
    }
}

function transferFromDepoToStore($conn, $urunId, $miktar, $depoId, $magazaId, $satisFiyati) {
    try {
        $conn->beginTransaction();

        // Depo stoğunu kontrol et
        $stmt = $conn->prepare("
            SELECT stok_miktari, maliyet 
            FROM depo_stok 
            WHERE depo_id = ? AND urun_id = ?
        ");
        $stmt->execute([$depoId, $urunId]);
        $depoStok = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$depoStok || $depoStok['stok_miktari'] < $miktar) {
            throw new Exception('Depoda yeterli stok yok');
        }

        // Depo stoğunu azalt
        $stmt = $conn->prepare("
            UPDATE depo_stok 
            SET stok_miktari = stok_miktari - ?,
                son_guncelleme = NOW()
            WHERE depo_id = ? AND urun_id = ?
        ");
        $stmt->execute([$miktar, $depoId, $urunId]);

        // Depodan çıkış hareketi
        $stmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi, 
                aciklama, tarih, kullanici_id, 
                depo_id, maliyet
            ) VALUES (
                ?, ?, 'cikis', 
                'Mağazaya transfer', NOW(), ?, 
                ?, ?
            )
        ");
        $stmt->execute([
            $urunId, 
            $miktar, 
            $_SESSION['user_id'] ?? null,
            $depoId,
            $depoStok['maliyet']
        ]);

        // Mağaza stok kaydını güncelle
        $stmt = $conn->prepare("
            INSERT INTO magaza_stok (barkod, magaza_id, stok_miktari, satis_fiyati)
            SELECT barkod, ?, ?, ?
            FROM urun_stok
            WHERE id = ?
            ON DUPLICATE KEY UPDATE
                stok_miktari = stok_miktari + ?,
                satis_fiyati = ?
        ");
        $stmt->execute([
            $magazaId, 
            $miktar, 
            $satisFiyati, 
            $urunId,
            $miktar,
            $satisFiyati
        ]);

        // Mağazaya giriş hareketi
        $stmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi,
                aciklama, tarih, kullanici_id,
                magaza_id, maliyet, satis_fiyati
            ) VALUES (
                ?, ?, 'giris',
                'Depodan transfer', NOW(), ?,
                ?, ?, ?
            )
        ");
        $stmt->execute([
            $urunId,
            $miktar,
            $_SESSION['user_id'] ?? null,
            $magazaId,
            $depoStok['maliyet'],
            $satisFiyati
        ]);

        $conn->commit();
        return true;

    } catch (Exception $e) {
        $conn->rollBack();
        error_log('Depo transfer hatası: ' . $e->getMessage());
        throw $e;
    }
}