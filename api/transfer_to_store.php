<?php
session_start();
require_once '../db_connection.php';
require_once 'depo_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Geçersiz veri formatı');
    }

    $fatura_id = $input['fatura_id'] ?? null;
    $magaza_id = $input['magaza_id'] ?? null;
    $products = $input['products'] ?? [];

    if (!$fatura_id || !$magaza_id || empty($products)) {
        throw new Exception('Gerekli bilgiler eksik');
    }

    $conn->beginTransaction();

    foreach ($products as $product) {
        if (!isset($product['selected']) || !$product['selected']) continue;

        // Transfer edilecek miktarı kontrol et
        $transfer_miktar = floatval($product['transfer_miktar']);
        if ($transfer_miktar <= 0) continue;

        // Depo stoğunu kontrol et
        $stmt = $conn->prepare("
            SELECT stok_miktari 
            FROM depo_stok 
            WHERE depo_id = 1 AND urun_id = ?
        ");
        $stmt->execute([$product['urun_id']]);
        $depo_stok = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$depo_stok || $depo_stok['stok_miktari'] < $transfer_miktar) {
            throw new Exception("'{$product['ad']}' ürünü için depoda yeterli stok yok");
        }

        // Depo stoğunu azalt
        $stmt = $conn->prepare("
            UPDATE depo_stok 
            SET stok_miktari = stok_miktari - ?,
                son_guncelleme = NOW()
            WHERE depo_id = 1 AND urun_id = ?
        ");
        $stmt->execute([$transfer_miktar, $product['urun_id']]);

        // Depodan çıkış hareketi
        $stmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi, aciklama, 
                tarih, kullanici_id, depo_id, maliyet
            ) VALUES (
                ?, ?, 'cikis', 'Depodan mağazaya transfer',
                NOW(), ?, 1, ?
            )
        ");
        $stmt->execute([
            $product['urun_id'],
            $transfer_miktar,
            $_SESSION['user_id'] ?? null,
            $product['birim_fiyat']
        ]);

        // Mağazaya giriş hareketi
        $stmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi, aciklama,
                tarih, kullanici_id, magaza_id, maliyet, satis_fiyati
            ) VALUES (
                ?, ?, 'giris', 'Depodan transfer',
                NOW(), ?, ?, ?, ?
            )
        ");
        $stmt->execute([
            $product['urun_id'],
            $transfer_miktar,
            $_SESSION['user_id'] ?? null,
            $magaza_id,
            $product['birim_fiyat'],
            $product['satis_fiyati']
        ]);

        // Mağaza stoğunu güncelle
        $stmt = $conn->prepare("
            INSERT INTO magaza_stok (barkod, magaza_id, stok_miktari, satis_fiyati)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                stok_miktari = stok_miktari + ?,
                satis_fiyati = ?
        ");
        $stmt->execute([
            $product['barkod'],
            $magaza_id,
            $transfer_miktar,
            $product['satis_fiyati'],
            $transfer_miktar,
            $product['satis_fiyati']
        ]);

        // Aktarım kaydını ekle
        $stmt = $conn->prepare("
            INSERT INTO alis_fatura_detay_aktarim (
                fatura_id, urun_id, miktar, 
                aktarim_tarihi, magaza_id
            ) VALUES (?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $fatura_id,
            $product['urun_id'],
            $transfer_miktar,
            $magaza_id
        ]);
    }

    // Toplam ve aktarılan miktarları hesapla
    $stmt = $conn->prepare("
        SELECT 
            (SELECT SUM(miktar) FROM alis_fatura_detay WHERE fatura_id = ?) as total_miktar,
            (SELECT COALESCE(SUM(miktar), 0) FROM alis_fatura_detay_aktarim WHERE fatura_id = ?) as aktarilan_miktar
    ");
    $stmt->execute([$fatura_id, $fatura_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fatura durumunu belirle
    $yeni_durum = 'urun_girildi';
    if ($result['aktarilan_miktar'] >= $result['total_miktar']) {
        $yeni_durum = 'aktarildi';
    } elseif ($result['aktarilan_miktar'] > 0) {
        $yeni_durum = 'kismi_aktarildi';
    }

    // Faturayı güncelle
    $stmt = $conn->prepare("
        UPDATE alis_faturalari 
        SET durum = ?,
            aktarilan_miktar = ?,
            aktarim_tarihi = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$yeni_durum, $result['aktarilan_miktar'], $fatura_id]);

    // Aktarım log kaydını ekle
    $stmt = $conn->prepare("
        INSERT INTO alis_fatura_aktarim (
            fatura_id, magaza_id, kullanici_id
        ) VALUES (?, ?, ?)
    ");
    $stmt->execute([$fatura_id, $magaza_id, $_SESSION['user_id'] ?? null]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ürünler başarıyla mağazaya aktarıldı'
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Transfer Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}