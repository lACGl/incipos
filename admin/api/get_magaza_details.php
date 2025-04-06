<?php
session_start();
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
        throw new Exception('Geçersiz mağaza ID');
    }

    $magaza_id = (int)$_GET['id'];

    // Mağaza bilgilerini al
    $stmt = $conn->prepare("SELECT * FROM magazalar WHERE id = ?");
    $stmt->execute([$magaza_id]);
    $magaza = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$magaza) {
        throw new Exception('Mağaza bulunamadı');
    }

    // Yanıtı direkt olarak JSON encode et - success key'i ekleme
    echo json_encode($magaza);
    
} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>