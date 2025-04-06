<?php
/**
 * API test dosyası
 */

// Hata ayıklama
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// API şifresi
$token = 'KeHxXtvWK6QovGL';

// Test edilecek endpoint
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : 'orderStatus';

// Test verisi
$testData = [];
if ($endpoint == 'orders') {
    $testData = [
        'StartDate' => date('Y-m-d', strtotime('-7 days')),
        'EndDate' => date('Y-m-d'),
        'OrderStatusId' => 1
    ];
} elseif ($endpoint == 'orderCargoUpdate') {
    $testData = [
        'OrderId' => 1,
        'OrderStatusId' => 1,
        'CargoTrackingNumber' => 'TEST123456'
    ];
} elseif ($endpoint == 'invoiceLinkUpdate') {
    $testData = [
        'OrderId' => 1,
        'InvoiceLink' => 'https://example.com/invoice.pdf',
        'InvoiceNumber' => 'INV001',
        'InvoiceDate' => date('Y-m-d')
    ];
}

// API isteği yapılandırması
$url = 'https://pos.incikirtasiye.com/admin/api/birfatura/' . $endpoint . '.php';
$ch = curl_init();

// CURL yapılandırma
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Token: ' . $token
]);

// İsteği gönder
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

// Sonuçları göster
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birfatura API Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .header { background-color: #f0f0f0; padding: 10px; margin-bottom: 20px; }
        .result { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .success { background-color: #dff0d8; }
        .error { background-color: #f2dede; }
        pre { background-color: #f5f5f5; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Birfatura API Test</h1>
            <p>Endpoint: <strong><?php echo $url; ?></strong></p>
        </div>
        
        <div class="links">
            <p>
                <a href="?endpoint=orderStatus">Sipariş Durumları</a> | 
                <a href="?endpoint=paymentMethods">Ödeme Yöntemleri</a> | 
                <a href="?endpoint=orders">Siparişler</a> | 
                <a href="?endpoint=orderCargoUpdate">Kargo Güncelleme</a> | 
                <a href="?endpoint=invoiceLinkUpdate">Fatura Link Güncelleme</a>
            </p>
        </div>
        
        <h2>Test Sonucu</h2>
        
        <div class="result <?php echo ($httpCode >= 200 && $httpCode < 300) ? 'success' : 'error'; ?>">
            <?php if ($error): ?>
                <h3>Hata</h3>
                <p><?php echo $error; ?></p>
            <?php else: ?>
                <h3>HTTP Kodu: <?php echo $httpCode; ?></h3>
                <h3>Yanıt:</h3>
                <pre><?php echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
            <?php endif; ?>
        </div>
        
        <h2>Gönderilen Veri</h2>
        <pre><?php echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
    </div>
</body>
</html>