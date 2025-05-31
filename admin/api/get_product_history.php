<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Ürün ID gerekli');
    }

    // Debug için ürün ID'sini logla
    error_log('Requesting product history for ID: ' . $id);

    // Stok hareketlerini al
    $stmt = $conn->prepare("
        SELECT 
            sh.*,
            CASE 
                WHEN sh.depo_id IS NOT NULL THEN 'Ana Depo'
                WHEN sh.magaza_id IS NOT NULL THEN m.ad
                ELSE 'Bilinmiyor'
            END as magaza_adi,
            au.kullanici_adi
        FROM stok_hareketleri sh
        LEFT JOIN magazalar m ON m.id = sh.magaza_id
        LEFT JOIN admin_user au ON sh.kullanici_id = au.id
        WHERE sh.urun_id = ?
        ORDER BY sh.tarih DESC
    ");
    $stmt->execute([$id]);
    $stok_hareketleri = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug için sonuçları logla
    error_log('Stock movements found: ' . count($stok_hareketleri));

    echo json_encode([
        'success' => true,
        'stok_hareketleri' => $stok_hareketleri
    ]);

} catch (Exception $e) {
    error_log('Product history error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}