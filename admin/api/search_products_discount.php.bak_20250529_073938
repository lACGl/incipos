<?php
/**
 * Ürün arama API
 * 
 * GET parametreleri:
 * - q: Arama sorgusu (barkod veya ürün adı)
 */

session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Sorgu parametresini kontrol et
if (!isset($_GET['q']) || strlen($_GET['q']) < 3) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$search_term = '%' . $_GET['q'] . '%';

try {
    // Ürünleri ara
    $query = "SELECT id, kod, barkod, ad, satis_fiyati, stok_miktari, durum 
             FROM urun_stok 
             WHERE (barkod LIKE :search_term OR ad LIKE :search_term OR kod LIKE :search_term) 
               AND durum = 'aktif' 
             ORDER BY stok_miktari DESC 
             LIMIT 30";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':search_term', $search_term, PDO::PARAM_STR);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sonuçları JSON olarak döndür
    header('Content-Type: application/json');
    echo json_encode($products);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>