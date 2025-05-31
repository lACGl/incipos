<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
// Header'ları ayarla
header('Content-Type: application/json');

// Veritabanı bağlantısını dahil et
require_once '../db_connection.php';

// Müşteri ID'sini kontrol et
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Müşteri ID eksik'
    ]);
    exit;
}

$customer_id = intval($_GET['id']);

try {
    // Müşteri bilgilerini al
    $query = "SELECT * FROM musteriler WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        echo json_encode([
            'success' => false,
            'message' => 'Müşteri bulunamadı'
        ]);
        exit;
    }
    
    // İletişim bilgilerini al (Örnek: bu alanlar veritabanınıza göre değişebilir)
    $email = $customer['email'] ?? '';
    $phone = $customer['telefon'] ?? '';
    
    echo json_encode([
        'success' => true,
        'email' => $email,
        'phone' => $phone,
        'name' => $customer['ad'] . ' ' . $customer['soyad']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Bir hata oluştu: ' . $e->getMessage()
    ]);
}