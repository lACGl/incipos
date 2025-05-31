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
    $kod = $_POST['kod'] ?? null;
    $ad = $_POST['ad'] ?? null;
    $adres = $_POST['adres'] ?? '';
    $telefon = $_POST['telefon'] ?? '';
    
    // Validasyon
    if (!$kod || !$ad) {
        throw new Exception('Depo kodu ve adı zorunludur');
    }

    // Depo kodunun benzersiz olup olmadığını kontrol et
    $stmt = $conn->prepare("SELECT COUNT(*) FROM depolar WHERE kod = ?");
    $stmt->execute([$kod]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Bu depo kodu zaten kullanımda');
    }

    // Depo ekle
    $sql = "INSERT INTO depolar (
        kod, ad, adres, telefon, 
        depo_tipi, durum, kayit_tarihi
    ) VALUES (
        ?, ?, ?, ?, 
        'ara_depo', 'aktif', NOW()
    )";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$kod, $ad, $adres, $telefon]);

    echo json_encode([
        'success' => true,
        'message' => 'Depo başarıyla eklendi',
        'depo_id' => $conn->lastInsertId()
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}