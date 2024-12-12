<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $barkod = $_GET['barkod'] ?? null;
    
    if (!$barkod) {
        throw new Exception('Barkod gerekli');
    }

    // Tüm mağazalardaki stokları getir (depo dahil)
    $sql = "SELECT 
                ms.magaza_id,
                m.ad as magaza_adi,
                COALESCE(ms.stok_miktari, 0) as stok_miktari
            FROM 
                magazalar m
                LEFT JOIN magaza_stok ms ON m.id = ms.magaza_id AND ms.barkod = ?
            ORDER BY 
                CASE WHEN m.id = 5 THEN 0 ELSE 1 END,  -- Depo (ID=5) her zaman ilk sırada
                m.ad";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$barkod]);
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'stocks' => $stocks
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}