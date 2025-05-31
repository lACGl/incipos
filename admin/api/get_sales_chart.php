<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

// Varsayılan periyodu haftalık olarak ayarla
$period = isset($_GET['period']) ? $_GET['period'] : 'week';

try {
    $labels = [];
    $values = [];
    
    switch ($period) {
        case 'week':
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
                $labels[] = $dateObj->format('d M');
                $values[] = $value;
            }
            break;
            
        case 'month':
            // Son 30 günün verilerini haftalık olarak al
            $stmt = $conn->query("
                SELECT 
                    WEEK(fatura_tarihi) as week,
                    MIN(DATE(fatura_tarihi)) as week_start,
                    COALESCE(SUM(toplam_tutar), 0) as weekly_total
                FROM satis_faturalari
                WHERE fatura_tarihi >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY WEEK(fatura_tarihi)
                ORDER BY WEEK(fatura_tarihi)
            ");
            
            $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($chartData as $row) {
                $dateObj = new DateTime($row['week_start']);
                $labels[] = $dateObj->format('d M') . ' Haftası';
                $values[] = (float)$row['weekly_total'];
            }
            break;
            
        case 'year':
            // Son 12 ayın verilerini al
            $stmt = $conn->query("
                SELECT 
                    YEAR(fatura_tarihi) as year,
                    MONTH(fatura_tarihi) as month,
                    COALESCE(SUM(toplam_tutar), 0) as monthly_total
                FROM satis_faturalari
                WHERE fatura_tarihi >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY YEAR(fatura_tarihi), MONTH(fatura_tarihi)
                ORDER BY YEAR(fatura_tarihi), MONTH(fatura_tarihi)
            ");
            
            $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Son 12 ayı hazırla
            $currentDate = new DateTime();
            $currentDate->setTime(0, 0, 0);
            $months = [];
            
            for ($i = 11; $i >= 0; $i--) {
                $date = clone $currentDate;
                $date->modify("-$i months");
                $monthKey = $date->format('Y-m');
                $months[$monthKey] = [
                    'label' => $date->format('M Y'),
                    'value' => 0
                ];
            }
            
            // Varolan verileri ekle
            foreach ($chartData as $row) {
                $monthStr = sprintf('%04d-%02d', $row['year'], $row['month']);
                if (isset($months[$monthStr])) {
                    $months[$monthStr]['value'] = (float)$row['monthly_total'];
                }
            }
            
            // Grafiğe ekle
            foreach ($months as $month) {
                $labels[] = $month['label'];
                $values[] = $month['value'];
            }
            break;
    }
    
    echo json_encode([
        'success' => true,
        'period' => $period,
        'labels' => $labels,
        'values' => $values
    ]);

} catch (Exception $e) {
    error_log('Sales chart data error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Grafik verileri alınırken bir hata oluştu: ' . $e->getMessage()
    ]);
} 