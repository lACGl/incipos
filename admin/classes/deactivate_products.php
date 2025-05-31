<?php
require_once 'session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // Gelen veriyi al ve JSON olarak çöz
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
        throw new Exception('Geçersiz veya eksik ürün ID listesi');
    }

    // Ürün ID'lerini al ve güvenli hale getir
    $ids = array_map('intval', $data['ids']);

    // Ürünleri pasife almak için sorgu
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = "UPDATE urun_stok SET durum = 'pasif' WHERE id IN ($placeholders)";

    $stmt = $conn->prepare($query);
    $stmt->execute($ids);

    echo json_encode([
        'success' => true,
        'message' => count($ids) . ' ürün başarıyla pasife alındı'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Ürün pasife alınırken bir hata oluştu: ' . $e->getMessage()
    ]);
}
