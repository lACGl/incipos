<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
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

    // Bugünkü satışlar
    $stmt = $conn->query("
        SELECT COALESCE(SUM(toplam_tutar), 0) as daily_sales
        FROM satis_faturalari
        WHERE DATE(fatura_tarihi) = CURDATE()
    ");
    $dailySales = $stmt->fetch(PDO::FETCH_ASSOC)['daily_sales'];

    // Geçen haftanın aynı günü satışlar
    $stmt = $conn->query("
        SELECT COALESCE(SUM(toplam_tutar), 0) as last_week_sales
        FROM satis_faturalari
        WHERE DATE(fatura_tarihi) = DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $lastWeekSales = $stmt->fetch(PDO::FETCH_ASSOC)['last_week_sales'];

    // Büyüme oranı hesaplama
    $salesGrowth = 0;
    if ($lastWeekSales > 0) {
        $salesGrowth = round((($dailySales - $lastWeekSales) / $lastWeekSales) * 100);
    }

    // Sipariş sayısı
    $stmt = $conn->query("
        SELECT COUNT(*) as order_count
        FROM satis_faturalari
        WHERE MONTH(fatura_tarihi) = MONTH(CURDATE()) AND YEAR(fatura_tarihi) = YEAR(CURDATE())
    ");
    $orderCount = $stmt->fetch(PDO::FETCH_ASSOC)['order_count'];

    // Geçen aydaki sipariş sayısı
    $stmt = $conn->query("
        SELECT COUNT(*) as last_month_orders
        FROM satis_faturalari
        WHERE MONTH(fatura_tarihi) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
        AND YEAR(fatura_tarihi) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    ");
    $lastMonthOrders = $stmt->fetch(PDO::FETCH_ASSOC)['last_month_orders'];

    // Sipariş büyüme oranı hesaplama
    $ordersGrowth = 0;
    if ($lastMonthOrders > 0) {
        $ordersGrowth = round((($orderCount - $lastMonthOrders) / $lastMonthOrders) * 100);
    }

    // Toplam müşteri sayısı
    $stmt = $conn->query("
        SELECT COUNT(*) as customer_count
        FROM musteriler
    ");
    $customerCount = $stmt->fetch(PDO::FETCH_ASSOC)['customer_count'];

    // Bu ay eklenen müşteri sayısı
    $stmt = $conn->query("
        SELECT COUNT(*) as new_customers
        FROM musteriler
        WHERE MONTH(kayit_tarihi) = MONTH(CURDATE()) AND YEAR(kayit_tarihi) = YEAR(CURDATE())
    ");
    $newCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['new_customers'];

    // Müşteri büyüme oranı (bu ay eklenen müşterilerin toplama oranı)
    $customersGrowth = 0;
    if ($customerCount > 0) {
        $customersGrowth = round(($newCustomers / $customerCount) * 100);
    }

    // Aylık gelir
    $stmt = $conn->query("
        SELECT COALESCE(SUM(toplam_tutar), 0) as monthly_revenue
        FROM satis_faturalari
        WHERE MONTH(fatura_tarihi) = MONTH(CURDATE()) AND YEAR(fatura_tarihi) = YEAR(CURDATE())
    ");
    $monthlyRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_revenue'];

    // Geçen ayın geliri
    $stmt = $conn->query("
        SELECT COALESCE(SUM(toplam_tutar), 0) as last_month_revenue
        FROM satis_faturalari
        WHERE MONTH(fatura_tarihi) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
        AND YEAR(fatura_tarihi) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
    ");
    $lastMonthRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['last_month_revenue'];

    // Gelir büyüme oranı hesaplama
    $revenueGrowth = 0;
    if ($lastMonthRevenue > 0) {
        $revenueGrowth = round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100);
    }

    // Satış grafiği verileri (son 7 gün)
    $salesChartData = [
        'labels' => [],
        'values' => []
    ];
    
    // Son 7 günün verilerini al
    $stmt = $conn->query("
        SELECT 
            DATE(fatura_tarihi) as date,
            COALESCE(SUM(toplam_tutar), 0) as daily_total
        FROM satis_faturalari
        WHERE fatura_tarihi >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(fatura_tarihi)
        ORDER BY DATE(fatura_tarihi)
    ");
    
    $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Son 7 günü hazırla (boş günleri doldurmak için)
    $currentDate = new DateTime();
    $currentDate->setTime(0, 0, 0);
    $dates = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = clone $currentDate;
        $date->modify("-$i days");
        $dateStr = $date->format('Y-m-d');
        $dates[$dateStr] = 0;
    }
    
    // Varolan verileri ekle
    foreach ($chartData as $row) {
        $dates[$row['date']] = (float)$row['daily_total'];
    }
    
    // Grafiğe ekle
    foreach ($dates as $date => $value) {
        $dateObj = new DateTime($date);
        $salesChartData['labels'][] = $dateObj->format('d M');
        $salesChartData['values'][] = $value;
    }
    
    echo json_encode([
        'success' => true,
        'dailySales' => (float)$dailySales,
        'salesGrowth' => $salesGrowth,
        'orderCount' => (int)$orderCount,
        'ordersGrowth' => $ordersGrowth,
        'customerCount' => (int)$customerCount,
        'customersGrowth' => $customersGrowth,
        'monthlyRevenue' => (float)$monthlyRevenue,
        'revenueGrowth' => $revenueGrowth,
        'salesChartData' => $salesChartData,
        'summary' => [
            'totalProducts' => (int)$stockInfo['total_products'],
            'totalValue' => (float)$stockInfo['total_value'],
            'lowStock' => (int)$stockInfo['low_stock']
        ]
    ]);

} catch (Exception $e) {
    error_log('Dashboard summary error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Veriler alınırken bir hata oluştu: ' . $e->getMessage()
    ]);
}