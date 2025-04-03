<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Debug için log tutma
error_log("Ürün arama başladı - Gelen parametre: " . $_GET['term']);

try {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        throw new Exception('Yetkisiz erişim');
    }

    $search_term = isset($_GET['term']) ? trim($_GET['term']) : '';
    
    if (empty($search_term)) {
        throw new Exception('Arama terimi boş olamaz');
    }

    // SQL sorgusunu hazırla
$sql = "SELECT 
    us.id, 
    us.kod,  
    us.barkod, 
    us.ad, 
    us.stok_miktari, 
    us.satis_fiyati,
    us.alis_fiyati,
    us.kdv_orani 
FROM urun_stok us 
WHERE (
    us.barkod LIKE :term 
    OR us.kod LIKE :term 
    OR us.ad LIKE :term 
)
AND us.durum = 'aktif'
LIMIT 10";

    $stmt = $conn->prepare($sql);
    $searchParam = '%' . $search_term . '%';
    $stmt->bindParam(':term', $searchParam, PDO::PARAM_STR);
    
    // Debug için sorguyu yazdır
    error_log("SQL Sorgusu: " . $sql);
    error_log("Arama parametresi: " . $searchParam);

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug için sonuç sayısını yazdır
    error_log("Bulunan ürün sayısı: " . count($products));

    if (empty($products)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ürün bulunamadı',
            'search_term' => $search_term
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ]);

} catch (Exception $e) {
    error_log("Ürün arama hatası: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ürün arama sırasında bir hata oluştu: ' . $e->getMessage(),
        'debug' => [
            'search_term' => $search_term ?? null,
            'error' => $e->getMessage()
        ]
    ]);
}