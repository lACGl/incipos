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
    // POST verilerini al
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['order_ids']) || !isset($data['customer_id'])) {
        throw new Exception('Eksik veya geçersiz veri');
    }

    $orderIds = $data['order_ids'];
    $customerId = (int)$data['customer_id'];
    
    if (empty($orderIds) || $customerId <= 0) {
        throw new Exception('Geçersiz sipariş ID veya müşteri ID');
    }

    $conn->beginTransaction();
    
    // Her siparişi güncelle
    $updateStmt = $conn->prepare("
        UPDATE satis_faturalari 
        SET odeme_turu = 'borc',
            musteri_id = ?,
            aciklama = CONCAT(IFNULL(aciklama, ''), ' - Borç olarak güncellenmiştir')
        WHERE id = ? AND islem_turu = 'satis'
    ");
    
    $updateCount = 0;
    foreach ($orderIds as $orderId) {
        $updateStmt->execute([$customerId, $orderId]);
        $updateCount += $updateStmt->rowCount();
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $updateCount . ' adet sipariş borç olarak güncellendi'
    ]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>