<?php
session_start();
require_once 'db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

// POST verilerini al
$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['ids'] ?? [];

if (empty($ids)) {
    die(json_encode(['error' => 'İşlem yapılacak ürün seçilmedi']));
}

try {
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $params = array_merge(['aktif'], $ids);
    
    // Ürünlerin durumunu güncelle
    $sql = "UPDATE urun_stok SET durum = ? WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'message' => count($ids) . ' ürün aktif duruma alındı'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}
?>