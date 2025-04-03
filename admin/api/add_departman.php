<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $ad = $data['ad'] ?? null;

    if (!$ad) {
        throw new Exception('Departman adı gerekli');
    }

    // Aynı isimde departman var mı kontrol et
    $stmt = $conn->prepare("SELECT COUNT(*) FROM departmanlar WHERE ad = ?");
    $stmt->execute([$ad]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu departman zaten mevcut');
    }

    // Departman ekle
    $stmt = $conn->prepare("INSERT INTO departmanlar (ad) VALUES (?)");
    $stmt->execute([$ad]);
    
    $id = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Departman başarıyla eklendi',
        'id' => $id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}