<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $varsayilan_stok_lokasyonu = $_POST['varsayilan_stok_lokasyonu'] ?? null;
    
    if (!$varsayilan_stok_lokasyonu) {
        throw new Exception('Geçersiz parametre');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO sistem_ayarlari (anahtar, deger) 
        VALUES ('varsayilan_stok_lokasyonu', ?)
        ON DUPLICATE KEY UPDATE deger = ?
    ");
    
    $stmt->execute([$varsayilan_stok_lokasyonu, $varsayilan_stok_lokasyonu]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ayarlar başarıyla güncellendi'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}