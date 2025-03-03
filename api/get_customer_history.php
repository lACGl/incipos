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
    // Müşteri ID'sini al
    $customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($customerId <= 0) {
        throw new Exception('Geçersiz müşteri ID');
    }
    
    // Müşteri bilgilerini al
    $stmt = $conn->prepare("
        SELECT m.*, mp.puan_bakiye, mp.puan_oran, mp.musteri_turu 
        FROM musteriler m
        LEFT JOIN musteri_puanlar mp ON m.id = mp.musteri_id
        WHERE m.id = :id
    ");
    $stmt->bindParam(':id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        throw new Exception('Müşteri bulunamadı');
    }
    
    // Siparişleri al (satış faturaları)
    $stmt = $conn->prepare("
        SELECT sf.*, m.ad as magaza_adi, mp.kazanilan_puan
        FROM satis_faturalari sf
        LEFT JOIN magazalar m ON sf.magaza = m.id
        LEFT JOIN musteri_puan_islemler mp ON sf.id = mp.fatura_id AND mp.islem_tipi = 'kazanma'
        WHERE sf.musteri = :musteri_id
        ORDER BY sf.fatura_tarihi DESC
        LIMIT 50
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Puan geçmişini al
    $stmt = $conn->prepare("
        SELECT * 
        FROM musteri_puan_islemler
        WHERE musteri_id = :musteri_id
        ORDER BY tarih DESC
        LIMIT 100
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $pointHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam alışveriş tutarını hesapla
    $stmt = $conn->prepare("
        SELECT SUM(toplam_tutar) as toplam
        FROM satis_faturalari
        WHERE musteri = :musteri_id AND islem_turu = 'satis'
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $totalSpent = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'] ?: 0;
    
    // Toplam kazanılan puanları hesapla
    $stmt = $conn->prepare("
        SELECT SUM(kazanilan_puan) as toplam
        FROM musteri_puan_islemler
        WHERE musteri_id = :musteri_id AND islem_tipi = 'kazanma'
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $totalPointsEarned = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'] ?: 0;
    
    // Toplam harcanan puanları hesapla
    $stmt = $conn->prepare("
        SELECT SUM(harcanan_puan) as toplam
        FROM musteri_puan_islemler
        WHERE musteri_id = :musteri_id AND islem_tipi = 'harcama'
    ");
    $stmt->bindParam(':musteri_id', $customerId, PDO::PARAM_INT);
    $stmt->execute();
    
    $totalPointsSpent = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'] ?: 0;
    
    // Tüm verileri döndür
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'orders' => $orders,
        'pointHistory' => $pointHistory,
        'totalSpent' => $totalSpent,
        'totalPointsEarned' => $totalPointsEarned,
        'totalPointsSpent' => $totalPointsSpent
    ]);
    
} catch (Exception $e) {
    // Hata durumunda
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}