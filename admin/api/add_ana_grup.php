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
    $departman_id = $data['departman_id'] ?? null;

    if (!$ad) {
        throw new Exception('Ana grup adı gerekli');
    }

    // Ana grup adı benzersiz olmalı
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ana_gruplar WHERE ad = ?");
    $stmt->execute([$ad]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu isimde bir ana grup zaten mevcut');
    }

    // Departman ID varsa kontrol et
    if ($departman_id) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM departmanlar WHERE id = ?");
        $stmt->execute([$departman_id]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Seçilen departman bulunamadı');
        }
    }

    // Ana grup ekle
    $stmt = $conn->prepare("INSERT INTO ana_gruplar (ad, departman_id) VALUES (?, ?)");
    $stmt->execute([$ad, $departman_id]);
    
    $id = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Ana grup başarıyla eklendi',
        'id' => $id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}