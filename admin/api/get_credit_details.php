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
        throw new Exception('Geçersiz borç ID');
    }

    $credit_id = (int)$_GET['id'];

    // Borç bilgilerini getir
    $stmt = $conn->prepare("
        SELECT 
            mb.*, 
            m.ad as musteri_adi, 
            m.soyad as musteri_soyadi,
            mag.ad as magaza_adi
        FROM musteri_borclar mb
        JOIN musteriler m ON mb.musteri_id = m.id
        LEFT JOIN magazalar mag ON mb.magaza_id = mag.id
        WHERE mb.borc_id = ?
    ");
    $stmt->execute([$credit_id]);
    $credit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$credit) {
        throw new Exception('Borç kaydı bulunamadı');
    }

    // Borç detaylarını getir
    $stmt = $conn->prepare("
        SELECT * FROM musteri_borc_detaylar 
        WHERE borc_id = ?
        ORDER BY detay_id
    ");
    $stmt->execute([$credit_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Borç ödemelerini getir
    $stmt = $conn->prepare("
        SELECT mbo.*, p.ad as personel_adi
        FROM musteri_borc_odemeler mbo
        LEFT JOIN personel p ON mbo.kullanici_id = p.id
        WHERE mbo.borc_id = ?
        ORDER BY mbo.odeme_tarihi DESC
    ");
    $stmt->execute([$credit_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'credit' => $credit,
        'details' => $details,
        'payments' => $payments
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}