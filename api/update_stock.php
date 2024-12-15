<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $amount = floatval($data['amount'] ?? 0);
    $operation = $data['operation'] ?? null;
    $location = $data['location'] ?? 'depo';
    $magaza_id = $data['magaza_id'] ?? null;
    $price = floatval($data['price'] ?? 0);

    if (!$id || !$amount || !$operation) {
        throw new Exception('Eksik parametreler');
    }

    $conn->beginTransaction();

    // Ürün bilgilerini al
    $stmt = $conn->prepare("SELECT satis_fiyati, barkod FROM urun_stok WHERE id = ?");
    $stmt->execute([$id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urun) {
        throw new Exception('Ürün bulunamadı');
    }

    // Eğer fiyat belirtilmemişse son işlem fiyatını bul
    if (!$price) {
        $stmt = $conn->prepare("
            SELECT maliyet 
            FROM stok_hareketleri 
            WHERE urun_id = ? AND maliyet IS NOT NULL
            ORDER BY tarih DESC 
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $lastPrice = $stmt->fetch(PDO::FETCH_COLUMN);
        $price = $lastPrice ?: $urun['satis_fiyati'];
    }

    // Depo varlığını kontrol et
    if ($location === 'depo') {
        $stmt = $conn->prepare("SELECT id FROM depolar WHERE id = 1");
        $stmt->execute();
        if (!$stmt->fetch()) {
            throw new Exception('Ana depo bulunamadı');
        }

        // Depo stok kontrolü
        $stmt = $conn->prepare("
            SELECT stok_miktari 
            FROM depo_stok 
            WHERE depo_id = 1 AND urun_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$id]);
        $current_stock = $stmt->fetch(PDO::FETCH_ASSOC);

        // Stok çıkarma kontrolü
        if ($operation === 'remove' && (!$current_stock || $current_stock['stok_miktari'] < $amount)) {
            throw new Exception('Depoda yeterli stok yok');
        }

        // Stok güncelleme veya ekleme
        if ($current_stock) {
            $new_amount = $operation === 'add' ? 
                         $current_stock['stok_miktari'] + $amount : 
                         $current_stock['stok_miktari'] - $amount;

            $stmt = $conn->prepare("
                UPDATE depo_stok 
                SET stok_miktari = ?, son_guncelleme = NOW()
                WHERE depo_id = 1 AND urun_id = ?
            ");
            $stmt->execute([$new_amount, $id]);
        } else {
            if ($operation === 'remove') {
                throw new Exception('Depoda stok bulunamadı');
            }
            
            $stmt = $conn->prepare("
                INSERT INTO depo_stok (depo_id, urun_id, stok_miktari, son_guncelleme)
                VALUES (1, ?, ?, NOW())
            ");
            $stmt->execute([$id, $amount]);
        }

        // Depo stok hareketi kaydet
        $stmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi, aciklama,
                tarih, kullanici_id, depo_id, maliyet, satis_fiyati
            ) VALUES (
                ?, ?, ?, 'Manuel stok girişi',
                NOW(), ?, 1, ?, ?
            )
        ");
        $stmt->execute([
            $id,
            $amount,
            $operation === 'add' ? 'giris' : 'cikis',
            $_SESSION['user_id'] ?? null,
            $price,
            $urun['satis_fiyati']
        ]);
    } else {
        // Mağaza işlemleri
        if (!$magaza_id) {
            throw new Exception('Mağaza seçilmedi');
        }

        // Mağaza varlığını kontrol et
        $stmt = $conn->prepare("SELECT id FROM magazalar WHERE id = ?");
        $stmt->execute([$magaza_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Mağaza bulunamadı');
        }

        // Mağaza stok kontrolü
        $stmt = $conn->prepare("
            SELECT stok_miktari
            FROM magaza_stok 
            WHERE magaza_id = ? AND barkod = ?
            FOR UPDATE
        ");
        $stmt->execute([$magaza_id, $urun['barkod']]);
        $current_stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($operation === 'remove' && (!$current_stock || $current_stock['stok_miktari'] < $amount)) {
            throw new Exception('Mağazada yeterli stok yok');
        }

        // Mağaza stok hareketi kaydet
        $stmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi, aciklama,
                tarih, kullanici_id, magaza_id, maliyet, satis_fiyati
            ) VALUES (
                ?, ?, ?, 'Manuel stok girişi',
                NOW(), ?, ?, ?, ?
            )
        ");
        $stmt->execute([
            $id,
            $amount,
            $operation === 'add' ? 'giris' : 'cikis',
            $_SESSION['user_id'] ?? null,
            $magaza_id,
            $price,
            $urun['satis_fiyati']
        ]);

        // Mağaza stoğunu güncelle
        if ($current_stock) {
            $new_amount = $operation === 'add' ? 
                         $current_stock['stok_miktari'] + $amount : 
                         $current_stock['stok_miktari'] - $amount;

            $stmt = $conn->prepare("
                UPDATE magaza_stok 
                SET stok_miktari = ?
                WHERE magaza_id = ? AND barkod = ?
            ");
            $stmt->execute([$new_amount, $magaza_id, $urun['barkod']]);
        } else {
            if ($operation === 'remove') {
                throw new Exception('Mağazada stok bulunamadı');
            }

            $stmt = $conn->prepare("
                INSERT INTO magaza_stok (
                    barkod, magaza_id, stok_miktari, satis_fiyati
                ) VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $urun['barkod'],
                $magaza_id,
                $amount,
                $urun['satis_fiyati']
            ]);
        }
    }

    // Genel stok toplamını güncelle
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COALESCE(SUM(stok_miktari), 0) FROM depo_stok WHERE urun_id = ?) as depo_stok,
            (SELECT COALESCE(SUM(stok_miktari), 0) FROM magaza_stok WHERE barkod = ?) as magaza_stok
    ");
    $stmt->execute([$id, $urun['barkod']]);
    $total_stock = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = floatval($total_stock['depo_stok']) + floatval($total_stock['magaza_stok']);

    $stmt = $conn->prepare("UPDATE urun_stok SET stok_miktari = ? WHERE id = ?");
    $stmt->execute([$total, $id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Stok başarıyla güncellendi',
        'new_stock' => $total
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Stok güncelleme hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}