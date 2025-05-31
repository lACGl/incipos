<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

// JSON formatında yanıt döndür
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // ID parametresi kontrolü
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Geçersiz personel ID');
    }

    $personel_id = (int)$_GET['id'];

    // Personel bilgilerini al
    $stmt = $conn->prepare("
        SELECT p.*, m.ad as magaza_adi 
        FROM personel p
        LEFT JOIN magazalar m ON p.magaza_id = m.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$personel_id]);
    $personel = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$personel) {
        throw new Exception('Personel bulunamadı');
    }

    // Güvenlik için şifre alanını kaldır
    unset($personel['sifre']);

    // Yanıtı direkt olarak JSON encode et - success key'i ekleme
    echo json_encode($personel);
    
} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>