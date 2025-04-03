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
    $ana_grup_id = $data['ana_grup_id'] ?? null;

    if (!$ad) {
        throw new Exception('Alt grup adı gerekli');
    }

    if (!$ana_grup_id) {
        throw new Exception('Ana grup seçilmeli');
    }

    // Ana grubun var olduğunu kontrol et
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ana_gruplar WHERE id = ?");
    $stmt->execute([$ana_grup_id]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('Seçilen ana grup bulunamadı');
    }

    // Aynı ana grupta aynı isimde alt grup var mı kontrol et
    $stmt = $conn->prepare("SELECT COUNT(*) FROM alt_gruplar WHERE ad = ? AND ana_grup_id = ?");
    $stmt->execute([$ad, $ana_grup_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu ana grupta bu isimde bir alt grup zaten mevcut');
    }

    // Alt grup ekle
    $stmt = $conn->prepare("INSERT INTO alt_gruplar (ad, ana_grup_id) VALUES (?, ?)");
    $stmt->execute([$ad, $ana_grup_id]);
    
    $id = $conn->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Alt grup başarıyla eklendi',
        'id' => $id
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}