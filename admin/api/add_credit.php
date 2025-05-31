<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // Hata göstermeyi kapat ama loglama devam etsin
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    // POST verileri kontrolü
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['musteri_id']) || !isset($data['toplam_tutar']) || !isset($data['borc_tarihi']) || !isset($data['urunler'])) {
        throw new Exception('Eksik veya geçersiz veri');
    }

    $musteri_id = (int)$data['musteri_id'];
    $toplam_tutar = (float)$data['toplam_tutar'];
    $indirim_tutari = isset($data['indirim_tutari']) ? (float)$data['indirim_tutari'] : 0;
    $borc_tarihi = $data['borc_tarihi'];
    $fis_no = isset($data['fis_no']) ? $data['fis_no'] : null;
    $magaza_id = isset($data['magaza_id']) ? (int)$data['magaza_id'] : null;
    $urunler = $data['urunler'];

    // Müşteri kontrolü
    $stmt = $conn->prepare("SELECT id FROM musteriler WHERE id = ?");
    $stmt->execute([$musteri_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Müşteri bulunamadı');
    }

    // İşlemi başlat
    $conn->beginTransaction();

    // Borç kaydını ekle
    $stmt = $conn->prepare("
        INSERT INTO musteri_borclar (
            musteri_id, 
            toplam_tutar, 
            indirim_tutari, 
            borc_tarihi, 
            fis_no, 
            odendi_mi, 
            magaza_id,
            olusturma_tarihi
        ) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
    ");
    $stmt->execute([
        $musteri_id,
        $toplam_tutar,
        $indirim_tutari,
        $borc_tarihi,
        $fis_no,
        $magaza_id
    ]);

    $borc_id = $conn->lastInsertId();

    // Ürünleri ekle
    $stmt = $conn->prepare("
        INSERT INTO musteri_borc_detaylar (
            borc_id, 
            urun_adi, 
            miktar, 
            tutar, 
            urun_id,
            olusturma_tarihi
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");

    foreach ($urunler as $urun) {
        $stmt->execute([
            $borc_id,
            $urun['ad'],
            (int)$urun['miktar'],
            (float)$urun['toplam'], // Her ürünün toplam tutarını kullan
            isset($urun['id']) ? (int)$urun['id'] : null
        ]);
    }

    $conn->commit();

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'message' => 'Borç kaydı başarıyla eklendi',
        'borc_id' => $borc_id
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}