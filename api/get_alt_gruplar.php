<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $ana_grup_id = $_GET['ana_grup_id'] ?? null;
    
    if ($ana_grup_id) {
        // Belirli bir ana gruba ait alt grupları getir
        $stmt = $conn->prepare("SELECT id, ad FROM alt_gruplar WHERE ana_grup_id = ? ORDER BY ad");
        $stmt->execute([$ana_grup_id]);
    } else {
        // Tüm alt grupları getir
        $stmt = $conn->query("
            SELECT ag.id, ag.ad, ag.ana_grup_id, ang.ad as ana_grup_ad
            FROM alt_gruplar ag
            LEFT JOIN ana_gruplar ang ON ag.ana_grup_id = ang.id
            ORDER BY ang.ad, ag.ad
        ");
    }
    
    $alt_gruplar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $alt_gruplar
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}