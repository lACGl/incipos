<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $urun_id = $_POST['urun_id'] ?? null;
    $source_location = $_POST['source_location'] ?? null;
    $target_location = $_POST['target_location'] ?? null;
    $miktar = floatval($_POST['miktar'] ?? 0);

    if (!$urun_id || !$source_location || !$target_location || !$miktar) {
        throw new Exception('Eksik parametreler');
    }

    if ($source_location === $target_location) {
        throw new Exception('Kaynak ve hedef aynı olamaz');
    }

    $conn->beginTransaction();

    // Ürün bilgilerini al
    $stmt = $conn->prepare("SELECT barkod, satis_fiyati FROM urun_stok WHERE id = ?");
    $stmt->execute([$urun_id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urun) {
        throw new Exception('Ürün bulunamadı');
    }

    // Kaynak ve hedef tiplerini ve ID'lerini parse et
    list($source_type, $source_id) = explode('_', $source_location);
    list($target_type, $target_id) = explode('_', $target_location);

    // Kaynak stok kontrolü ve azaltma
    if ($source_type === 'depo') {
        $stmt = $conn->prepare("
            SELECT stok_miktari FROM depo_stok 
            WHERE depo_id = ? AND urun_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$source_id, $urun_id]);
        $source_stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$source_stock || $source_stock['stok_miktari'] < $miktar) {
            throw new Exception('Kaynak depoda yeterli stok yok');
        }

        $stmt = $conn->prepare("
            UPDATE depo_stok 
            SET stok_miktari = stok_miktari - ?,
                son_guncelleme = NOW()
            WHERE depo_id = ? AND urun_id = ?
        ");
        $stmt->execute([$miktar, $source_id, $urun_id]);
    } else {
        $stmt = $conn->prepare("
            SELECT stok_miktari FROM magaza_stok 
            WHERE magaza_id = ? AND barkod = ?
            FOR UPDATE
        ");
        $stmt->execute([$source_id, $urun['barkod']]);
        $source_stock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$source_stock || $source_stock['stok_miktari'] < $miktar) {
            throw new Exception('Kaynak mağazada yeterli stok yok');
        }

        $stmt = $conn->prepare("
            UPDATE magaza_stok 
            SET stok_miktari = stok_miktari - ?
            WHERE magaza_id = ? AND barkod = ?
        ");
        $stmt->execute([$miktar, $source_id, $urun['barkod']]);
    }

    // Hedef stok artırma
    if ($target_type === 'depo') {
        $stmt = $conn->prepare("
            INSERT INTO depo_stok (depo_id, urun_id, stok_miktari, son_guncelleme)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                stok_miktari = stok_miktari + ?,
                son_guncelleme = NOW()
        ");
        $stmt->execute([$target_id, $urun_id, $miktar, $miktar]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO magaza_stok (magaza_id, barkod, stok_miktari, satis_fiyati)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                stok_miktari = stok_miktari + ?
        ");
        $stmt->execute([$target_id, $urun['barkod'], $miktar, $urun['satis_fiyati'], $miktar]);
    }

    // Stok hareketlerini kaydet
    $stmt = $conn->prepare("
        INSERT INTO stok_hareketleri (
            urun_id, miktar, hareket_tipi, 
            aciklama, tarih, kullanici_id, 
            depo_id, magaza_id, satis_fiyati
        ) VALUES 
        (?, ?, 'cikis', ?, NOW(), ?, ?, ?, ?),
        (?, ?, 'giris', ?, NOW(), ?, ?, ?, ?)
    ");

    $source_depo_id = $source_type === 'depo' ? $source_id : null;
    $source_magaza_id = $source_type === 'magaza' ? $source_id : null;
    $target_depo_id = $target_type === 'depo' ? $target_id : null;
    $target_magaza_id = $target_type === 'magaza' ? $target_id : null;

    $stmt->execute([
        // Çıkış hareketi
        $urun_id, $miktar, 'Transfer çıkış', $_SESSION['user_id'],
        $source_depo_id, $source_magaza_id, $urun['satis_fiyati'],
        // Giriş hareketi
        $urun_id, $miktar, 'Transfer giriş', $_SESSION['user_id'],
        $target_depo_id, $target_magaza_id, $urun['satis_fiyati']
    ]);

$conn->commit();

echo json_encode([
    'success' => true,
    'message' => 'Transfer işlemi başarıyla tamamlandı',
    'toast' => true,
    'position' => 'top-end',
    'timer' => 3000
]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}