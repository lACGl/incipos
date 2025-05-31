<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $ad = $data['ad'] ?? null;

    if (!$ad) {
        throw new Exception('Birim adı gerekli');
    }

    // Aynı isimde birim var mı kontrol et
    $stmt = $conn->prepare("SELECT COUNT(*) FROM birimler WHERE ad = ?");
    $stmt->execute([$ad]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu birim zaten mevcut');
    }

    // Birim ekle
    $stmt = $conn->prepare("INSERT INTO birimler (ad) VALUES (?)");
    $stmt->execute([$ad]);
    
    $id = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Birim başarıyla eklendi',
        'id' => $id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}