<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // POST verilerini al
    $ad = $_POST['ad'] ?? null;
    $telefon = $_POST['telefon'] ?? null;
    $adres = $_POST['adres'] ?? null;
    $sehir = $_POST['sehir'] ?? null;
    $eposta = $_POST['eposta'] ?? null;

    // Validasyon
    if (!$ad || !$telefon || !$adres || !$sehir) {
        throw new Exception('Lütfen tüm zorunlu alanları doldurun');
    }

    // Tedarikçi ekle
    $sql = "INSERT INTO tedarikciler (ad, telefon, adres, sehir, eposta) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$ad, $telefon, $adres, $sehir, $eposta]);

    echo json_encode([
        'success' => true,
        'message' => 'Tedarikçi başarıyla eklendi',
        'tedarikci_id' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}