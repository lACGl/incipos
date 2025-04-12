<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // JSON verisini al
    $data = json_decode(file_get_contents('php://input'), true);

    // Debug için gelen veriyi logla
    error_log('POST data: ' . print_r($data, true));

    // Zorunlu alanlar
    $required_fields = ['kod', 'barkod', 'ad', 'kdv_orani', 'alis_fiyati', 'satis_fiyati'];
    $missing_fields = [];

    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing_fields[] = $field;
        }
    }

    if (!empty($missing_fields)) {
        error_log('Eksik alanlar: ' . implode(', ', $missing_fields));
        throw new Exception('Eksik alanlar: ' . implode(', ', $missing_fields));
    }

    // Form verilerini al
    $kod = trim($data['kod']);
    $barkod = trim($data['barkod']);
    $ad = trim($data['ad']);
    $kdv_orani = floatval($data['kdv_orani']);
    $alis_fiyati = floatval($data['alis_fiyati']);
    $satis_fiyati = floatval($data['satis_fiyati']);

    // Opsiyonel alanlar
    $web_id = isset($data['web_id']) ? trim($data['web_id']) : null;
    $yil = isset($data['yil']) ? intval($data['yil']) : null;
    $stok_miktari = isset($data['stok_miktari']) ? floatval($data['stok_miktari']) : 0;
    $indirimli_fiyat = isset($data['indirimli_fiyat']) && $data['indirimli_fiyat'] !== '' ? floatval($data['indirimli_fiyat']) : null;

    // Departman ve gruplar
    $departman_id = isset($data['departman']) && $data['departman'] !== '' ? intval($data['departman']) : null;
    $birim_id = isset($data['birim']) && $data['birim'] !== '' ? intval($data['birim']) : null;
    $ana_grup_id = isset($data['ana_grup']) && $data['ana_grup'] !== '' ? intval($data['ana_grup']) : null;
    $alt_grup_id = isset($data['alt_grup']) && $data['alt_grup'] !== '' ? intval($data['alt_grup']) : null;

    // Benzersizlik kontrolü
    $stmt = $conn->prepare("SELECT COUNT(*) FROM urun_stok WHERE barkod = ? OR kod = ?");
    $stmt->execute([$barkod, $kod]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu barkod veya kod zaten kullanımda');
    }

    // Resim yolunu barkod temelli olarak oluştur
    $resim_yolu = 'files/img/products/' . $barkod . '_1.jpg';

    // Ürünü ekle
    $sql = "INSERT INTO urun_stok (
        kod, barkod, ad, web_id, yil,
        kdv_orani, alis_fiyati, satis_fiyati, indirimli_fiyat,
        stok_miktari, departman_id, birim_id, ana_grup_id, alt_grup_id,
        kayit_tarihi, durum, resim_yolu
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        NOW(), 'aktif', ?
    )";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $kod, $barkod, $ad, $web_id, $yil,
        $kdv_orani, $alis_fiyati, $satis_fiyati, $indirimli_fiyat,
        $stok_miktari, $departman_id, $birim_id, $ana_grup_id, $alt_grup_id,
        $resim_yolu
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
        'urun_id' => $urun_id,
        'resim_yolu' => $resim_yolu
    ]);

} catch (Exception $e) {
    error_log('Ürün ekleme hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ürün eklenirken bir hata oluştu: ' . $e->getMessage()
    ]);
}