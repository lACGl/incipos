<?php
session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // POST verilerini al
    $customerId = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $points = isset($_POST['points']) ? (float)$_POST['points'] : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : 'Manuel puan kullanımı';
    
    // Validasyon
    if ($customerId <= 0) {
        throw new Exception('Geçersiz müşteri ID');
    }
    
    if ($points <= 0) {
        throw new Exception('Puan miktarı pozitif olmalıdır');
    }
    
    // Müşterinin puan bakiyesini kontrol et
    $stmt = $conn->prepare("
        SELECT puan_bakiye FROM musteri_puanlar
        WHERE musteri_id = :musteri_id
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $customerPoints = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customerPoints) {
        throw new Exception('Müşteriye ait puan kaydı bulunamadı');
    }
    
    $currentPoints = (float)$customerPoints['puan_bakiye'];
    
    if ($currentPoints < $points) {
        throw new Exception('Yetersiz puan bakiyesi');
    }
    
    // Veritabanı işlemleri
    $conn->beginTransaction();
    
    // Puan bakiyesini güncelle
    $stmt = $conn->prepare("
        UPDATE musteri_puanlar
        SET puan_bakiye = puan_bakiye - :puan,
            son_guncelleme = NOW()
        WHERE musteri_id = :musteri_id
    ");
    $stmt->bindParam(':puan', $points, PDO::PARAM_STR);
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    // Puan harcama tablosuna kayıt ekle
    $stmt = $conn->prepare("
        INSERT INTO puan_harcama (
            musteri_id, 
            harcanan_puan, 
            tarih
        ) VALUES (
            :musteri_id, 
            :puan, 
            NOW()
        )
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->bindParam(':puan', $points, PDO::PARAM_STR);
    $stmt->execute();
    
    $conn->commit();
    
    // Başarılı sonuç döndür
    echo json_encode([
        'success' => true,
        'message' => 'Puanlar başarıyla kullanıldı',
        'used_points' => $points,
        'remaining_points' => $currentPoints - $points
    ]);
    
} catch (Exception $e) {
    // Hata durumunda rollback yap
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    // Hata mesajı döndür
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}