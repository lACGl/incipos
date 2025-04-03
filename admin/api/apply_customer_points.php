<?php
session_start();
require_once '../db_connection.php';

// JSON veri tipinde yanıt döndüreceğimizi belirt
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

/**
 * Müşteri puanı kullanma
 * ----------------------
 * Bu API, satış işleminde müşteri puanı kullanımını doğrulamak için kullanılır
 * Satış tamamlanmadan sadece kullanılabilirlik kontrolü için
 */

try {
    // POST verisini al
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    // Veri kontrolü
    if (!isset($inputData['customer_id']) || !isset($inputData['points'])) {
        throw new Exception('Geçersiz veri formatı');
    }
    
    $customerId = intval($inputData['customer_id']);
    $points = floatval($inputData['points']);
    
    if ($customerId <= 0) {
        throw new Exception('Geçersiz müşteri ID');
    }
    
    if ($points <= 0) {
        throw new Exception('Kullanılacak puan 0\'dan büyük olmalıdır');
    }
    
    // Müşteri puan bakiyesini kontrol et
    $stmt = $conn->prepare("
        SELECT puan_bakiye 
        FROM musteri_puanlar 
        WHERE musteri_id = ?
    ");
    $stmt->execute([$customerId]);
    $currentPoints = $stmt->fetchColumn();
    
    if ($currentPoints === false) {
        throw new Exception('Müşteri puan kaydı bulunamadı');
    }
    
    if ($points > $currentPoints) {
        throw new Exception('Yetersiz puan bakiyesi. Mevcut bakiye: ' . $currentPoints);
    }
    
    // Bu aşamada puan henüz düşülmez, sadece satış tamamlanma aşamasında düşülür
    // Burada sadece kontrolü yapılır
    
    echo json_encode([
        'success' => true,
        'message' => 'Puanlar kullanılabilir',
        'customer_id' => $customerId,
        'available_points' => $currentPoints,
        'points_to_use' => $points
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}