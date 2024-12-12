<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $fatura_id = $_GET['id'] ?? null;
    
    if (!$fatura_id) {
        throw new Exception('Fatura ID gerekli');
    }

    $sql = "SELECT 
                afd.*, 
                us.barkod,
                us.ad,
                us.kod
            FROM 
                alis_fatura_detay afd
                JOIN urun_stok us ON us.id = afd.urun_id
            WHERE 
                afd.fatura_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$fatura_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'products' => $products
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}