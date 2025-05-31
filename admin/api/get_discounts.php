<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Aktif indirimleri getir
    $query = "SELECT id, ad, indirim_turu, indirim_degeri, baslangic_tarihi, bitis_tarihi, durum 
              FROM indirimler 
              WHERE baslangic_tarihi <= CURRENT_DATE() 
              AND bitis_tarihi >= CURRENT_DATE() 
              ORDER BY durum ASC, olusturulma_tarihi DESC";
    
    $stmt = $conn->query($query);
    $discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sonuçları JSON olarak döndür
    header('Content-Type: application/json');
    echo json_encode($discounts);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?> 