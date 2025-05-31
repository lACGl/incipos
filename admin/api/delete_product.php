<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'error' => 'Yetkisiz erişim']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Ürün ID gerekli']);
    exit;
}

try {
    $conn->beginTransaction();

    // Önce ürünün barkodunu al
    $stmt = $conn->prepare("SELECT barkod FROM urun_stok WHERE id = ?");
    $stmt->execute([$id]);
    $barkod = $stmt->fetchColumn();

    // İlişkili kayıtları sil
    // 1. Mağaza stoklarını sil
    $stmt = $conn->prepare("DELETE FROM magaza_stok WHERE barkod = ?");
    $stmt->execute([$barkod]);

    // 2. Depo stoklarını sil
    $stmt = $conn->prepare("DELETE FROM depo_stok WHERE urun_id = ?");
    $stmt->execute([$id]);

    // 3. Stok hareketlerini sil
    $stmt = $conn->prepare("DELETE FROM stok_hareketleri WHERE urun_id = ?");
    $stmt->execute([$id]);

    // 4. Alış fatura detaylarını sil
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay WHERE urun_id = ?");
    $stmt->execute([$id]);

    // 5. Alış fatura aktarımlarını sil
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay_aktarim WHERE urun_id = ?");
    $stmt->execute([$id]);

    // 6. Satış fatura detaylarını sil
    $stmt = $conn->prepare("DELETE FROM satis_fatura_detay WHERE urun_id = ?");
    $stmt->execute([$id]);

    // 7. Fiyat geçmişini sil
    $stmt = $conn->prepare("DELETE FROM urun_fiyat_gecmisi WHERE urun_id = ?");
    $stmt->execute([$id]);

    // Son olarak ürünü sil
    $stmt = $conn->prepare("DELETE FROM urun_stok WHERE id = ?");
    $stmt->execute([$id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ürün ve ilişkili tüm kayıtlar başarıyla silindi'
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
    exit;
}