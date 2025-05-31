<?php
/**
 * Birfatura API Dashboard
 * Veritabanı değişikliği gerektirmeyen versiyon
 */

// Output buffering başlat
ob_start();

// Hata ayıklama
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/statistics.php';

// API şifresi
$token = 'KeHxXtvWK6QovGL';

// Log dosyası
$logFile = __DIR__ . '/api_log.txt';

// Veritabanı bağlantısı
try {
    // Güvenli bağlantı - harici dosyadan
    require_once '../../admin/db_connection.php';
    
    // İstatistik verilerini al (db_connection.php'den gelen $conn kullanılır)
    $statisticsHTML = getStatisticsHTML($conn, $logFile);
    
} catch (PDOException $e) {
    $errorMessage = "Veritabanı bağlantı hatası: " . $e->getMessage();
    $statisticsHTML = "<div class='error'>$errorMessage</div>";
}

// API log dosyasını oku (son 20 satır)
$logContent = '';
if (file_exists($logFile)) {
    $logs = file($logFile);
    $logs = array_slice($logs, -20); // Son 20 satır
    $logContent = implode('', $logs);
    $logContent = htmlspecialchars($logContent);
}

// Test edilecek endpoint
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Endpoint test edilecekse
$testResult = null;
if ($endpoint) {
    // Test verisi
    $testData = array();
    if ($endpoint == 'orders') {
        $testData = array(
            'startDateTime' => date('d.m.Y H:i:s', strtotime('-7 days')),
            'endDateTime' => date('d.m.Y H:i:s'),
            'orderStatusId' => 1
        );
    } elseif ($endpoint == 'orderCargoUpdate') {
        $testData = array(
            'orderId' => 1,
            'orderStatusId' => 1,
            'cargoTrackingCode' => 'TEST' . rand(100000, 999999),
            'cargoCompany' => 'Test Kargo'
        );
    } elseif ($endpoint == 'invoiceLinkUpdate') {
        $testData = array(
            'orderId' => 1,
            'faturaUrl' => 'https://example.com/invoice.pdf',
            'faturaNo' => 'INV' . rand(1000, 9999),
            'faturaTarihi' => date('Y-m-d')
        );
    } elseif ($endpoint == 'stockUpdate') {
        $testData = array(
            'productId' => 1,
            'barcode' => '',
            'quantity' => 10,
            'updateType' => 'set'
        );
    }

    // API isteği yapılandırması (gerçek API'ye gönderilmez, yerel dosyaları çağırır)
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $endpoint . '.php';
    $ch = curl_init();

    // CURL yapılandırma
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Token: ' . $token
    ));

    // İsteği gönder
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);
    
    $testResult = array(
        'url' => $url,
        'data' => $testData,
        'response' => $response ? json_decode($response, true) : null,
        'httpCode' => $httpCode,
        'error' => $error
    );
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İnciPos - Birfatura API Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background-color: #343a40; color: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; }
        .header h1 { margin: 0; }
        .section { background-color: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .nav { display: flex; flex-wrap: wrap; margin-bottom: 20px; background-color: #e9ecef; padding: 10px; border-radius: 5px; }
        .nav a { margin-right: 15px; margin-bottom: 5px; color: #495057; text-decoration: none; padding: 8px 12px; }
        .nav a:hover, .nav a.active { color: #fff; background-color: #007bff; border-radius: 3px; }
        .statistics-container { margin-bottom: 30px; }
        .stats-cards { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        .stats-card { flex: 1; min-width: 200px; padding: 15px; background-color: #e9ecef; border-radius: 5px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .stats-table th, .stats-table td { border: 1px solid #dee2e6; padding: 8px; text-align: left; }
        .stats-table th { background-color: #e9ecef; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .stats-grid-item { background-color: #f8f9fa; padding: 15px; border-radius: 5px; }
        .log-container { background-color: #f8f9fa; padding: 15px; border-radius: 5px; height: 300px; overflow-y: auto; font-family: monospace; white-space: pre-wrap; }
        .error { color: #dc3545; padding: 15px; border: 1px solid #dc3545; border-radius: 5px; }
        .success { color: #28a745; }
        .test-result { padding: 15px; margin-top: 20px; border-radius: 5px; }
        .test-result.success { background-color: #d4edda; border: 1px solid #c3e6cb; }
        .test-result.error { background-color: #f8d7da; border: 1px solid #f5c6cb; }
        pre { background-color: #f5f5f5; padding: 10px; border-radius: 3px; overflow: auto; }
        .btn { display: inline-block; padding: 8px 16px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px; }
        .btn:hover { background-color: #0069d9; }
        .btn-secondary { background-color: #6c757d; }
        .btn-secondary:hover { background-color: #5a6268; }
        
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr; }
            .nav { flex-direction: column; }
            .nav a { margin-right: 0; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Birfatura API Dashboard</h1>
            <p>POS ve Birfatura entegrasyon merkezi</p>
        </div>
        
        <div class="nav">
            <a href="dashboard.php" class="<?php echo empty($endpoint) ? 'active' : ''; ?>">Ana Sayfa</a>
            <a href="?endpoint=orderStatus" class="<?php echo $endpoint == 'orderStatus' ? 'active' : ''; ?>">Sipariş Durumları</a>
            <a href="?endpoint=paymentMethods" class="<?php echo $endpoint == 'paymentMethods' ? 'active' : ''; ?>">Ödeme Yöntemleri</a>
            <a href="?endpoint=orders" class="<?php echo $endpoint == 'orders' ? 'active' : ''; ?>">Siparişler</a>
            <a href="?endpoint=orderCargoUpdate" class="<?php echo $endpoint == 'orderCargoUpdate' ? 'active' : ''; ?>">Kargo Güncelleme</a>
            <a href="?endpoint=invoiceLinkUpdate" class="<?php echo $endpoint == 'invoiceLinkUpdate' ? 'active' : ''; ?>">Fatura Link Güncelleme</a>
            <a href="?endpoint=stockUpdate" class="<?php echo $endpoint == 'stockUpdate' ? 'active' : ''; ?>">Stok Güncelleme</a>
        </div>
        
        <?php if ($endpoint): ?>
        <div class="section">
            <h2>API Test: <?php echo ucfirst(str_replace('Update', ' Güncelleme', str_replace('Status', ' Durumları', str_replace('Methods', ' Yöntemleri', $endpoint)))); ?></h2>
            
            <?php if ($testResult): ?>
            <div class="test-result <?php echo ($testResult['httpCode'] >= 200 && $testResult['httpCode'] < 300) ? 'success' : 'error'; ?>">
                <h3>Test Sonucu</h3>
                <p>URL: <?php echo $testResult['url']; ?></p>
                <p>HTTP Kodu: <?php echo $testResult['httpCode']; ?></p>
                
                <?php if ($testResult['error']): ?>
                <p>Hata: <?php echo $testResult['error']; ?></p>
                <?php else: ?>
                <h4>Gönderilen Veri:</h4>
                <pre><?php echo json_encode($testResult['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                
                <h4>Yanıt:</h4>
                <pre><?php echo json_encode($testResult['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px;">
                <a href="dashboard.php" class="btn btn-secondary">Ana Sayfaya Dön</a>
            </div>
        </div>
        <?php else: ?>
        <div class="section">
            <?php echo $statisticsHTML; ?>
        </div>
        
        <div class="section">
            <h2>API Testi</h2>
            <p>API endpoint'lerini test etmek için yukarıdaki linkleri kullanabilirsiniz.</p>
            
            <div style="margin-top: 20px;">
                <h3>Hızlı Test</h3>
                <a href="?endpoint=orders" class="btn">Siparişleri Listele</a>
                <a href="?endpoint=orderStatus" class="btn">Sipariş Durumlarını Getir</a>
                <a href="?endpoint=paymentMethods" class="btn">Ödeme Yöntemlerini Getir</a>
            </div>
        </div>
        
        <div class="section">
            <h2>API Log (Son Kayıtlar)</h2>
            <div class="log-container"><?php echo $logContent ? $logContent : 'Log kaydı bulunamadı.'; ?></div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>