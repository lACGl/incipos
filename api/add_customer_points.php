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
    $description = isset($_POST['description']) ? trim($_POST['description']) : 'Manuel puan ekleme';
    
    // Validasyon
    if ($customerId <= 0) {
        throw new Exception('Geçersiz müşteri ID');
    }
    
    if ($points <= 0) {
        throw new Exception('Puan miktarı pozitif olmalıdır');
    }
    
    // Veritabanı işlemleri
    $conn->beginTransaction();
    
    // Müşteri puan tablosunda kaydı kontrol et
    $stmt = $conn->prepare("
        SELECT * FROM musteri_puanlar
        WHERE musteri_id = :musteri_id
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $customerPoints = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customerPoints) {
        // Mevcut puan bakiyesini güncelle
        $stmt = $conn->prepare("
            UPDATE musteri_puanlar
            SET puan_bakiye = puan_bakiye + :puan,
                son_guncelleme = NOW()
            WHERE musteri_id = :musteri_id
        ");
        $stmt->bindParam(':puan', $points, PDO::PARAM_STR);
        $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Yeni puan kaydı oluştur
        $stmt = $conn->prepare("
            INSERT INTO musteri_puanlar (musteri_id, puan_bakiye, puan_oran, son_guncelleme)
            VALUES (:musteri_id, :puan, 1.00, NOW())
        ");
        $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
        $stmt->bindParam(':puan', $points, PDO::PARAM_STR);
        $stmt->execute();
    }
    
    // Puan işlemini kaydet
    $stmt = $conn->prepare("
        INSERT INTO musteri_puan_islemler (
            musteri_id, islem_tipi, kazanilan_puan, 
            harcanan_puan, aciklama, tarih
        ) VALUES (
            :musteri_id, 'kazanma', :puan, 
            0, :aciklama, NOW()
        )
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->bindParam(':puan', $points, PDO::PARAM_STR);
    $stmt->bindParam(':aciklama', $description, PDO::PARAM_STR);
    $stmt->execute();
    
    $conn->commit();
    
    // Başarılı sonuç döndür
    echo json_encode([
        'success' => true,
        'message' => 'Puan başarıyla eklendi',
        'added_points' => $points
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