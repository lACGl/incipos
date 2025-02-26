<?php
session_start();
require_once '../db_connection.php';

// Hata raporlamasını açalım
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hataları gösterme ama logla

// Her zaman JSON header'ı gönder
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $fatura_id = $_GET['id'] ?? null;
    
    // Debug için gelen parametreleri logla
    error_log('Fatura ID: ' . $fatura_id);
    
    if (!$fatura_id) {
        throw new Exception('Fatura ID gerekli');
    }

    $sql = "SELECT 
        afd.*, 
        us.barkod,
        us.ad,
        us.kod,
        COALESCE((
            SELECT SUM(afda.miktar) 
            FROM alis_fatura_detay_aktarim afda 
            WHERE afda.fatura_id = afd.fatura_id 
            AND afda.urun_id = afd.urun_id
        ), 0) as aktarilan_miktar,
        afd.miktar - COALESCE((
            SELECT SUM(afda.miktar) 
            FROM alis_fatura_detay_aktarim afda 
            WHERE afda.fatura_id = afd.fatura_id 
            AND afda.urun_id = afd.urun_id
        ), 0) as kalan_miktar
    FROM 
        alis_fatura_detay afd
        JOIN urun_stok us ON us.id = afd.urun_id
    WHERE 
        afd.fatura_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$fatura_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug için SQL ve sonuçları logla
    error_log('SQL: ' . $sql);
    error_log('Params: ' . $fatura_id);
    error_log('Products: ' . print_r($products, true));

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    error_log('Fatura ürünleri hatası: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}