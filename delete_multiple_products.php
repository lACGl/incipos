<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    die(json_encode(['error' => 'Silinecek ürün seçilmedi']));
}

try {
    $conn->beginTransaction();

    // Önce ürünlerin barkodlarını al
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT barkod FROM urun_stok WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $barkodlar = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($barkodlar)) {
        // Barkodlar için placeholder oluştur
        $barkod_placeholders = str_repeat('?,', count($barkodlar) - 1) . '?';
        
        // Mağaza stoklarını sil
        $stmt = $conn->prepare("DELETE FROM magaza_stok WHERE barkod IN ($barkod_placeholders)");
        $stmt->execute($barkodlar);
    }

    // ID'ler için placeholder oluştur
    $id_placeholders = str_repeat('?,', count($ids) - 1) . '?';

    // İlişkili kayıtları sil
    // 1. Depo stoklarını sil
    $stmt = $conn->prepare("DELETE FROM depo_stok WHERE urun_id IN ($id_placeholders)");
    $stmt->execute($ids);

    // 2. Stok hareketlerini sil
    $stmt = $conn->prepare("DELETE FROM stok_hareketleri WHERE urun_id IN ($id_placeholders)");
    $stmt->execute($ids);

    // 3. Alış fatura detaylarını sil
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay WHERE urun_id IN ($id_placeholders)");
    $stmt->execute($ids);

    // 4. Alış fatura aktarımlarını sil
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay_aktarim WHERE urun_id IN ($id_placeholders)");
    $stmt->execute($ids);

    // 5. Satış fatura detaylarını sil
    $stmt = $conn->prepare("DELETE FROM satis_fatura_detay WHERE urun_id IN ($id_placeholders)");
    $stmt->execute($ids);

    // 6. Fiyat geçmişini sil
    $stmt = $conn->prepare("DELETE FROM urun_fiyat_gecmisi WHERE urun_id IN ($id_placeholders)");
    $stmt->execute($ids);

    // Son olarak ürünleri sil
    $stmt = $conn->prepare("DELETE FROM urun_stok WHERE id IN ($id_placeholders)");
    $stmt->execute($ids);

    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => count($ids) . ' ürün ve ilişkili kayıtları başarıyla silindi'
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log('Çoklu ürün silme hatası: ' . $e->getMessage());
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}