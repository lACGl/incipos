<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Ürün ID gerekli');
    }

    // Ana ürün bilgilerini al
    $sql = "SELECT us.*, 
            d.ad as departman,
            ag.ad as ana_grup,
            alg.ad as alt_grup,
            b.ad as birim
            FROM urun_stok us
            LEFT JOIN departmanlar d ON us.departman_id = d.id
            LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
            LEFT JOIN alt_gruplar alg ON us.alt_grup_id = alg.id
            LEFT JOIN birimler b ON us.birim_id = b.id
            WHERE us.id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Ürün bulunamadı');
    }

    // Tarih formatlamaları
    $product['kayit_tarihi'] = $product['kayit_tarihi'] ? date('Y-m-d', strtotime($product['kayit_tarihi'])) : null;
    $product['indirim_baslangic_tarihi'] = $product['indirim_baslangic_tarihi'] ? date('Y-m-d', strtotime($product['indirim_baslangic_tarihi'])) : null;
    $product['indirim_bitis_tarihi'] = $product['indirim_bitis_tarihi'] ? date('Y-m-d', strtotime($product['indirim_bitis_tarihi'])) : null;

    echo json_encode([
        'success' => true,
        'product' => $product
    ]);

} catch (Exception $e) {
    error_log('Ürün detayları hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}