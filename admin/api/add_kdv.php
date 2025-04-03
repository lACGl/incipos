<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $oran = $data['oran'] ?? null;

    if (!is_numeric($oran) || $oran < 0 || $oran > 100) {
        throw new Exception('Geçerli bir KDV oranı giriniz (0-100 arası)');
    }

    // Aynı oran var mı kontrol et
    $stmt = $conn->prepare("SELECT DISTINCT kdv_orani FROM urun_stok WHERE kdv_orani = ?");
    $stmt->execute([$oran]);
    if ($stmt->fetchColumn()) {
        throw new Exception('Bu KDV oranı zaten mevcut');
    }

    echo json_encode([
        'success' => true,
        'message' => 'KDV oranı başarıyla eklendi',
        'oran' => $oran
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}