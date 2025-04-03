<?php
session_start();
require_once __DIR__ . '/../db_connection.php'; 
require_once 'ProductManager.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $ids = $data['ids'] ?? [];

    if (empty($ids)) {
        throw new Exception('Silinecek ürün seçilmedi');
    }

    $productManager = new ProductManager($conn);
    $result = $productManager->deleteMultipleProducts($ids);

    echo json_encode([
        'success' => true,
        'message' => count($ids) . ' ürün başarıyla silindi'
    ]);

} catch (Exception $e) {
    error_log('Çoklu ürün silme hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}