<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // ID kontrolü
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Geçersiz müşteri ID');
    }

    $customer_id = (int)$_GET['id'];

    // Müşteri bilgilerini al
    $stmt = $conn->prepare("
        SELECT * FROM musteriler WHERE id = ?
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Müşteri bulunamadı');
    }

// Müşterinin borçlarını getir
$stmt = $conn->prepare("
    SELECT 
        mb.*, 
        (SELECT SUM(mbo.odeme_tutari) FROM musteri_borc_odemeler mbo WHERE mbo.borc_id = mb.borc_id) as odenen_tutar,
        m.ad as magaza_adi
    FROM musteri_borclar mb
    LEFT JOIN magazalar m ON mb.magaza_id = m.id
    WHERE mb.musteri_id = ?
    ORDER BY mb.borc_tarihi DESC
");
    $stmt->execute([$customer_id]);
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toplam borç ve ödeme miktarlarını hesapla
$stmt = $conn->prepare("
    SELECT 
        SUM(mb.toplam_tutar) as toplam_borc_brut,
        SUM(mb.indirim_tutari) as toplam_indirim,
        SUM(mb.toplam_tutar - mb.indirim_tutari) as toplam_borc_net,
        SUM(CASE WHEN mb.odendi_mi = 0 THEN (mb.toplam_tutar - mb.indirim_tutari) ELSE 0 END) as odenmemis_borc,
        (SELECT SUM(mbo.odeme_tutari) FROM musteri_borc_odemeler mbo 
         JOIN musteri_borclar mb2 ON mbo.borc_id = mb2.borc_id 
         WHERE mb2.musteri_id = ?) as toplam_odeme
    FROM musteri_borclar mb
    WHERE mb.musteri_id = ?
");
    $stmt->execute([$customer_id, $customer_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'credits' => $credits,
        'summary' => $summary
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}