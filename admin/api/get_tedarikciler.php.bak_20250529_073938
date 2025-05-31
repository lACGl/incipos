<?php
session_start();
require_once '../db_connection.php';

// CORS ve içerik türü başlıkları
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// Hata raporlaması
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // SQL sorgusu
    $sql = "SELECT id, ad FROM tedarikciler ORDER BY ad";
    
    // Sorguyu çalıştır
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    // Sonuçları al
    $tedarikciler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Yanıtı oluştur
    $response = [
        'success' => true,
        'tedarikciler' => $tedarikciler,
        'count' => count($tedarikciler)
    ];

    // UTF-8 kodlamasını garanti et
    echo json_encode($response, 
        JSON_UNESCAPED_UNICODE | 
        JSON_UNESCAPED_SLASHES | 
        JSON_NUMERIC_CHECK
    );

} catch (Exception $e) {
    // Hata durumunda
    error_log("Tedarikçiler yüklenirken hata: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Tedarikçiler yüklenirken bir hata oluştu: ' . $e->getMessage(),
        'error' => true
    ], JSON_UNESCAPED_UNICODE);
}