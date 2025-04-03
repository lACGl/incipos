<?php
session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // POST verileri kontrolü
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Geçersiz istek metodu');
    }

    // JSON verileri al
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        throw new Exception('Geçersiz müşteri ID');
    }

    $customer_id = (int)$data['id'];

    // Müşterinin var olduğunu kontrol et
    $stmt = $conn->prepare("SELECT id FROM musteriler WHERE id = ?");
    $stmt->execute([$customer_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Müşteri bulunamadı');
    }

    // İlişkili işlemleri kontrol et (satış faturaları vs.)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM satis_faturalari WHERE musteri_id = ?");
    $stmt->execute([$customer_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] > 0) {
        // Güvenli silme: İlişkili kayıtlar varsa sadece pasife al
        $stmt = $conn->prepare("UPDATE musteriler SET durum = 'pasif' WHERE id = ?");
        $stmt->execute([$customer_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Müşteri pasif duruma alındı (İlişkili satış fatura kayıtları var)',
            'soft_delete' => true
        ]);
    } else {
        // İlişkili kayıt yoksa tamamen sil
        $conn->beginTransaction();
        
        // Puan kayıtlarını sil
        $stmt = $conn->prepare("DELETE FROM musteri_puanlar WHERE musteri_id = ?");
        $stmt->execute([$customer_id]);
        
        // Puan işlem geçmişini sil
        $stmt = $conn->prepare("DELETE FROM puan_kazanma WHERE musteri_id = ?");
        $stmt->execute([$customer_id]);
        
        $stmt = $conn->prepare("DELETE FROM puan_harcama WHERE musteri_id = ?");
        $stmt->execute([$customer_id]);
        
        // Müşteriyi sil
        $stmt = $conn->prepare("DELETE FROM musteriler WHERE id = ?");
        $stmt->execute([$customer_id]);
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Müşteri başarıyla silindi',
            'hard_delete' => true
        ]);
    }

} catch (PDOException $e) {
    // Veritabanı hatası
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}