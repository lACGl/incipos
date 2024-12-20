<?php

session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Toplam ürün sayısı ve değeri
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_products,
            COALESCE(SUM(stok_miktari * alis_fiyati), 0) as total_value,
            COUNT(CASE WHEN stok_miktari < 10 THEN 1 END) as low_stock
        FROM urun_stok
        WHERE durum = 'aktif'
    ");
    $stockInfo = $stmt->fetch(PDO::FETCH_ASSOC);

    // Bugünkü satışlar (mağaza bazlı)
    $stmt = $conn->query("
        SELECT 
            m.ad as name,
            COALESCE(SUM(sf.toplam_tutar), 0) as sales
        FROM magazalar m
        LEFT JOIN satis_faturalari sf ON m.id = sf.magaza 
            AND DATE(sf.fatura_tarihi) = CURDATE()
        GROUP BY m.id, m.ad
        ORDER BY sales DESC
    ");
    $dailySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Toplam günlük satış
    $dailyTotal = array_sum(array_column($dailySales, 'sales'));

    echo json_encode([
        'success' => true,
        'summary' => [
            'totalProducts' => (int)$stockInfo['total_products'],
            'totalValue' => (float)$stockInfo['total_value'],
            'lowStock' => (int)$stockInfo['low_stock'],
            'dailySales' => $dailySales,
            'dailyTotal' => $dailyTotal
        ]
    ]);

} catch (Exception $e) {
    error_log('Dashboard summary error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Veriler alınırken bir hata oluştu'
    ]);
}