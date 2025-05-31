<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // POST verilerini al
    $ad = $_POST['ad'] ?? null;
    $adres = $_POST['adres'] ?? null;
    $telefon = $_POST['telefon'] ?? null;

    // Validasyon
    if (!$ad || !$adres || !$telefon) {
        throw new Exception('Lütfen tüm zorunlu alanları doldurun');
    }

    // Mağaza ekle
    $sql = "INSERT INTO magazalar (ad, adres, telefon) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$ad, $adres, $telefon]);

    echo json_encode([
        'success' => true,
        'message' => 'Mağaza başarıyla eklendi',
        'magaza_id' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}