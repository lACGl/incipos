<?php
session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // ID kontrolü
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Geçersiz müşteri ID');
    }

    $customer_id = (int)$_GET['id'];

    // Müşteri bilgilerini al
    $stmt = $conn->prepare("
        SELECT m.*, mp.puan_bakiye, mp.puan_oran, mp.musteri_turu
        FROM musteriler m
        LEFT JOIN musteri_puanlar mp ON m.id = mp.musteri_id
        WHERE m.id = ?
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Müşteri bulunamadı');
    }

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'customer' => $customer
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}