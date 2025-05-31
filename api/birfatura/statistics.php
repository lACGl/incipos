<?php
// Veritabanı değişikliği gerektirmeyen istatistik fonksiyonları

/**
 * Son X gün içindeki sipariş sayısını döndürür
 * @param PDO $db Veritabanı bağlantısı
 * @param int $days Gün sayısı
 * @return int Sipariş sayısı
 */
function getOrderCountLastDays($db, $days = 7) {
    $query = "SELECT COUNT(*) FROM satis_faturalari 
              WHERE islem_turu = 'satis' 
              AND fatura_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $db->prepare($query);
    $stmt->execute(array($days));
    return $stmt->fetchColumn();
}

/**
 * Son X gün içindeki toplam ciroyu döndürür
 * @param PDO $db Veritabanı bağlantısı
 * @param int $days Gün sayısı
 * @return float Toplam ciro
 */
function getTotalRevenueLastDays($db, $days = 7) {
    $query = "SELECT SUM(toplam_tutar) FROM satis_faturalari 
              WHERE islem_turu = 'satis' 
              AND fatura_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $db->prepare($query);
    $stmt->execute(array($days));
    $result = $stmt->fetchColumn();
    return $result ? $result : 0;
}

/**
 * Son X gün içindeki ödeme türlerine göre dağılımı döndürür
 * @param PDO $db Veritabanı bağlantısı
 * @param int $days Gün sayısı
 * @return array Ödeme türlerine göre dağılım
 */
function getPaymentMethodStats($db, $days = 7) {
    $query = "SELECT odeme_turu, COUNT(*) as sayi, SUM(toplam_tutar) as toplam
              FROM satis_faturalari 
              WHERE islem_turu = 'satis' 
              AND fatura_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY odeme_turu";
    $stmt = $db->prepare($query);
    $stmt->execute(array($days));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mağazalara göre satış istatistiklerini döndürür
 * @param PDO $db Veritabanı bağlantısı
 * @param int $days Gün sayısı
 * @return array Mağazalara göre satış istatistikleri
 */
function getStoreStats($db, $days = 30) {
    $query = "SELECT m.ad AS magaza_adi, COUNT(sf.id) as siparis_sayisi, SUM(sf.toplam_tutar) as toplam_ciro
              FROM satis_faturalari sf
              JOIN magazalar m ON sf.magaza = m.id
              WHERE sf.islem_turu = 'satis' 
              AND sf.fatura_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY sf.magaza
              ORDER BY toplam_ciro DESC";
    $stmt = $db->prepare($query);
    $stmt->execute(array($days));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Son X gün içinde satılan en popüler ürünleri döndürür
 * @param PDO $db Veritabanı bağlantısı
 * @param int $days Gün sayısı
 * @param int $limit Maksimum ürün sayısı
 * @return array En çok satılan ürünler
 */
function getTopProducts($db, $days = 30, $limit = 5) {
    // LIMIT parametresini direkt sorguya ekleyelim
    $query = "SELECT us.ad as urun_adi, SUM(sfd.miktar) as toplam_miktar, SUM(sfd.toplam_tutar) as toplam_tutar
              FROM satis_fatura_detay sfd
              JOIN satis_faturalari sf ON sfd.fatura_id = sf.id
              JOIN urun_stok us ON sfd.urun_id = us.id
              WHERE sf.islem_turu = 'satis' 
              AND sf.fatura_tarihi >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY sfd.urun_id
              ORDER BY toplam_tutar DESC
              LIMIT " . intval($limit); // limit parametresini doğrudan sorguya ekliyoruz
    
    $stmt = $db->prepare($query);
    $stmt->execute(array($days));
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * API log dosyasından API kullanım istatistiklerini döndürür
 * @param string $logFile Log dosyası yolu
 * @param int $days Son X güne ait logları analiz eder 
 * @return array API endpoint kullanım istatistikleri
 */
function getApiUsageStats($logFile, $days = 7) {
    if (!file_exists($logFile)) {
        return array();
    }
    
    // Son X gün için tarih sınırını belirle
    $dateLimit = new DateTime();
    $dateLimit->modify("-$days days");
    $dateLimit = $dateLimit->format('Y-m-d');
    
    $logs = file($logFile);
    $endpoints = array(
        'orderStatus' => 0,
        'paymentMethods' => 0,
        'orders' => 0,
        'orderCargoUpdate' => 0,
        'invoiceLinkUpdate' => 0,
        'stockUpdate' => 0
    );
    
    foreach ($logs as $line) {
        // Satırdan tarihi çıkar
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
            $logDate = $matches[1];
            if ($logDate < $dateLimit) {
                continue; // Tarih sınırından eski logları atla
            }
            
            // Endpoint çağrılarını say
            foreach ($endpoints as $endpoint => $count) {
                if (strpos($line, "$endpoint endpoint çalıştırılıyor") !== false) {
                    $endpoints[$endpoint]++;
                }
            }
        }
    }
    
    return $endpoints;
}

/**
 * İstatistikleri HTML formatında döndürür
 * @param PDO $db Veritabanı bağlantısı
 * @param string $logFile Log dosyası yolu
 * @return string HTML formatında istatistikler
 */
function getStatisticsHTML($db, $logFile = null) {
    // 7 ve 30 günlük istatistikler
    $orderCount7 = getOrderCountLastDays($db, 7);
    $revenue7 = getTotalRevenueLastDays($db, 7);
    $orderCount30 = getOrderCountLastDays($db, 30);
    $revenue30 = getTotalRevenueLastDays($db, 30);
    $paymentStats = getPaymentMethodStats($db, 30);
    
    // Hata olabilecek sorguları try-catch bloklarına alalım
    try {
        $storeStats = getStoreStats($db, 30);
    } catch (Exception $e) {
        $storeStats = array();
        error_log("Mağaza istatistikleri hatası: " . $e->getMessage());
    }
    
    try {
        $topProducts = getTopProducts($db, 30, 5);
    } catch (Exception $e) {
        $topProducts = array();
        error_log("Ürün istatistikleri hatası: " . $e->getMessage());
    }
    
    // API kullanım istatistikleri
    $apiStats = array();
    if ($logFile && file_exists($logFile)) {
        $apiStats = getApiUsageStats($logFile, 7);
    }
    
    // Para birimini formatla
    $revenue7Formatted = number_format($revenue7, 2, ',', '.') . ' ₺';
    $revenue30Formatted = number_format($revenue30, 2, ',', '.') . ' ₺';
    
    // Ödeme türleri için HTML oluştur
    $paymentMethodsHTML = '';
    foreach ($paymentStats as $stat) {
        $paymentName = ucfirst($stat['odeme_turu'] ? $stat['odeme_turu'] : 'Diğer');
        $paymentCount = $stat['sayi'];
        $paymentTotal = number_format($stat['toplam'], 2, ',', '.') . ' ₺';
        
        $paymentMethodsHTML .= "
            <tr>
                <td>{$paymentName}</td>
                <td>{$paymentCount}</td>
                <td>{$paymentTotal}</td>
            </tr>
        ";
    }
    
    // Mağaza istatistikleri HTML
    $storeStatsHTML = '';
    foreach ($storeStats as $stat) {
        $storeName = $stat['magaza_adi'] ? $stat['magaza_adi'] : 'Bilinmeyen Mağaza';
        $storeOrderCount = $stat['siparis_sayisi'];
        $storeRevenue = number_format($stat['toplam_ciro'], 2, ',', '.') . ' ₺';
        
        $storeStatsHTML .= "
            <tr>
                <td>{$storeName}</td>
                <td>{$storeOrderCount}</td>
                <td>{$storeRevenue}</td>
            </tr>
        ";
    }
    
    // En çok satan ürünler HTML
    $topProductsHTML = '';
    foreach ($topProducts as $product) {
        $productName = $product['urun_adi'] ? $product['urun_adi'] : 'Bilinmeyen Ürün';
        $productQuantity = number_format($product['toplam_miktar'], 0, ',', '.');
        $productRevenue = number_format($product['toplam_tutar'], 2, ',', '.') . ' ₺';
        
        $topProductsHTML .= "
            <tr>
                <td>{$productName}</td>
                <td>{$productQuantity}</td>
                <td>{$productRevenue}</td>
            </tr>
        ";
    }
    
    // API istatistikleri HTML
    $apiStatsHTML = '';
    foreach ($apiStats as $endpoint => $count) {
        $endpointName = ucfirst(str_replace('Update', ' Güncelleme', str_replace('Status', ' Durumları', str_replace('Methods', ' Yöntemleri', $endpoint))));
        
        $apiStatsHTML .= "
            <tr>
                <td>{$endpointName}</td>
                <td>{$count}</td>
            </tr>
        ";
    }
    
    // API toplam çağrı sayısı hesapla
    $totalApiCalls = 0;
    if (!empty($apiStats)) {
        $totalApiCalls = array_sum($apiStats);
        $totalApiCallsDisplay = $totalApiCalls;
    } else {
        $totalApiCallsDisplay = 'Log bulunamadı';
    }
    
    // İstatistik HTML'i
    $html = <<<HTML
    <div class="statistics-container">
        <h2>Satış ve Entegrasyon İstatistikleri</h2>
        
        <div class="stats-cards">
            <div class="stats-card">
                <h3>Son 7 Gün</h3>
                <p>Sipariş Sayısı: <strong>{$orderCount7}</strong></p>
                <p>Toplam Ciro: <strong>{$revenue7Formatted}</strong></p>
            </div>
            
            <div class="stats-card">
                <h3>Son 30 Gün</h3>
                <p>Sipariş Sayısı: <strong>{$orderCount30}</strong></p>
                <p>Toplam Ciro: <strong>{$revenue30Formatted}</strong></p>
            </div>
            
            <div class="stats-card">
                <h3>API Kullanımı (7 Gün)</h3>
                <p>Toplam Çağrı: <strong>{$totalApiCallsDisplay}</strong></p>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stats-grid-item">
                <h3>Ödeme Türleri (Son 30 Gün)</h3>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Ödeme Türü</th>
                            <th>Sipariş Sayısı</th>
                            <th>Toplam Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$paymentMethodsHTML}
                    </tbody>
                </table>
            </div>
            
            <div class="stats-grid-item">
                <h3>Mağaza Performansı (Son 30 Gün)</h3>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Mağaza</th>
                            <th>Sipariş Sayısı</th>
                            <th>Toplam Ciro</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$storeStatsHTML}
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stats-grid-item">
                <h3>En Çok Satan Ürünler (Son 30 Gün)</h3>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Ürün</th>
                            <th>Satış Adedi</th>
                            <th>Toplam Tutar</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$topProductsHTML}
                    </tbody>
                </table>
            </div>
            
            <div class="stats-grid-item">
                <h3>API Kullanımı (Son 7 Gün)</h3>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Çağrı Sayısı</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$apiStatsHTML}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
HTML;

    return $html;
}
?>