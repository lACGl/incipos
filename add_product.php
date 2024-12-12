<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Debug için gelen verileri logla
    error_log('POST data: ' . print_r($_POST, true));

    // Zorunlu alanları kontrol et
    $required_fields = ['kod', 'barkod', 'ad', 'kdv_orani', 'alis_fiyati', 'satis_fiyati'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        error_log('Eksik alanlar: ' . implode(', ', $missing_fields));
        throw new Exception('Eksik alanlar: ' . implode(', ', $missing_fields));
    }

    // Form verilerini al
    $kod = trim($_POST['kod']);
    $barkod = trim($_POST['barkod']);
    $ad = trim($_POST['ad']);
    $kdv_orani = floatval($_POST['kdv_orani']);
    $alis_fiyati = floatval($_POST['alis_fiyati']);
    $satis_fiyati = floatval($_POST['satis_fiyati']);
    
    // Opsiyonel alanlar
    $web_id = isset($_POST['web_id']) ? trim($_POST['web_id']) : null;
    $yil = isset($_POST['yil']) ? intval($_POST['yil']) : null;
    $stok_miktari = isset($_POST['stok_miktari']) ? floatval($_POST['stok_miktari']) : 0;
    $indirimli_fiyat = isset($_POST['indirimli_fiyat']) && $_POST['indirimli_fiyat'] !== '' ? 
        floatval($_POST['indirimli_fiyat']) : null;
    
    // Departman ve gruplar
    $departman_id = isset($_POST['departman']) && $_POST['departman'] !== '' ? 
        intval($_POST['departman']) : null;
    $birim_id = isset($_POST['birim']) && $_POST['birim'] !== '' ? 
        intval($_POST['birim']) : null;
    $ana_grup_id = isset($_POST['ana_grup']) && $_POST['ana_grup'] !== '' ? 
        intval($_POST['ana_grup']) : null;
    $alt_grup_id = isset($_POST['alt_grup']) && $_POST['alt_grup'] !== '' ? 
        intval($_POST['alt_grup']) : null;

    // Benzersizlik kontrolü
    $stmt = $conn->prepare("SELECT COUNT(*) FROM urun_stok WHERE barkod = ? OR kod = ?");
    $stmt->execute([$barkod, $kod]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu barkod veya kod zaten kullanımda');
    }

    // Ürünü ekle
    $sql = "INSERT INTO urun_stok (
        kod, barkod, ad, web_id, yil,
        kdv_orani, alis_fiyati, satis_fiyati, indirimli_fiyat,
        stok_miktari, departman_id, birim_id, ana_grup_id, alt_grup_id,
        kayit_tarihi, durum
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        NOW(), 'aktif'
    )";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $kod, $barkod, $ad, $web_id, $yil,
        $kdv_orani, $alis_fiyati, $satis_fiyati, $indirimli_fiyat,
        $stok_miktari, $departman_id, $birim_id, $ana_grup_id, $alt_grup_id
    ]);

    $urun_id = $conn->lastInsertId();

    // Stok kaydı
    if ($stok_miktari > 0) {
        // Depo stoğu ekle
        $stmt = $conn->prepare("
            INSERT INTO depo_stok (depo_id, urun_id, stok_miktari, son_guncelleme)
            VALUES (1, ?, ?, NOW())
        ");
        $stmt->execute([$urun_id, $stok_miktari]);

        // Stok hareketi ekle
        $stmt = $conn->prepare("
            INSERT INTO stok_hareketleri (
                urun_id, miktar, hareket_tipi,
                aciklama, tarih, kullanici_id,
                depo_id, maliyet, satis_fiyati
            ) VALUES (
                ?, ?, 'giris',
                'İlk stok girişi', NOW(), ?,
                1, ?, ?
            )
        ");
        $stmt->execute([
            $urun_id,
            $stok_miktari,
            $_SESSION['user_id'] ?? null,
            $alis_fiyati,
            $satis_fiyati
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Ürün başarıyla eklendi',
        'urun_id' => $urun_id
    ]);

} catch (Exception $e) {
    error_log('Ürün ekleme hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ürün eklenirken bir hata oluştu: ' . $e->getMessage()
    ]);
}