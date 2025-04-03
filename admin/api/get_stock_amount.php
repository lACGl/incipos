<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Yetkisiz erişimi engelle
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Parametreleri kontrol et
    if (!isset($_GET['product_id'], $_GET['location_type'], $_GET['location_id'])) {
        throw new Exception('Eksik parametreler');
    }
    
    $productId = $_GET['product_id'];
    $locationType = $_GET['location_type'];
    $locationId = $_GET['location_id'];
    
    // Ürün kontrolü
    $stmt = $conn->prepare("SELECT id, barkod FROM urun_stok WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Ürün bulunamadı');
    }
    
    $stockAmount = 0;
    
    // Lokasyon tipine göre stok miktarını getir
    if ($locationType === 'depo') {
        // Depo stoğu
        $stmt = $conn->prepare("SELECT stok_miktari FROM depo_stok WHERE depo_id = ? AND urun_id = ?");
        $stmt->execute([$locationId, $productId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stockAmount = $result ? floatval($result['stok_miktari']) : 0;
    } else {
        // Mağaza stoğu
        $stmt = $conn->prepare("SELECT stok_miktari FROM magaza_stok WHERE magaza_id = ? AND barkod = ?");
        $stmt->execute([$locationId, $product['barkod']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stockAmount = $result ? floatval($result['stok_miktari']) : 0;
    }
    
    echo json_encode([
        'success' => true,
        'stock_amount' => $stockAmount
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}