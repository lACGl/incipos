<?php
/**
 * Prestashop API Tam Bağlantı Testi
 * 
 * Bu dosya, tüm ürünleri sayar ve rastgele örnek ürünleri gösterir.
 * İlk çalıştırmada zaman alabilir, ancak sonuçları önbelleğe kaydeder.
 */

// Hata göstermeyi aktifleştir
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

echo "<h1>Prestashop API Tam Bağlantı Testi</h1>";

// API Bilgileri
echo "<h2>API Konfigürasyonu</h2>";
echo "<p>API Anahtarı: " . PS_API_KEY . "</p>";
echo "<p>Shop URL: " . PS_SHOP_URL . "</p>";

// Zamanı ölç
$startTime = microtime(true);

// URL'yi temizle
$url = rtrim(PS_SHOP_URL, '/');

// Önbellekten ürün sayısını kontrol et
$cacheDir = __DIR__ . '/cache';
$cacheFile = $cacheDir . '/product_count.txt';

// Önbellek klasörünü oluştur
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// Rastgele örnekleme için parametre ekle
$randomPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($randomPage < 1) $randomPage = 1;

// Ürünleri belirli bir sayfadan al
function getProductsFromPage($url, $apiKey, $page = 1, $limit = 10) {
    $offset = ($page - 1) * $limit;
    // Ürün sayfalarını al - daha fazla veri çekiyoruz ve rastgele sıralama istiyoruz
    $pageUrl = $url . '/api/products?display=[id,reference,name,price]&output_format=JSON&limit=' . $limit . '&sort=[id_DESC]&offset=' . $offset;
    
    // cURL başlat
    $curl = curl_init();
    
    // cURL ayarları
    curl_setopt($curl, CURLOPT_URL, $pageUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, $apiKey . ':');
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    
    // İsteği gönder
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    
    // cURL oturumunu kapat
    curl_close($curl);
    
    if ($httpCode == 200 && !$error) {
        return json_decode($response, true);
    }
    
    return null;
}

// Önce toplam ürün sayısını al
$useCache = false;
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
    $useCache = true;
    $productCount = file_get_contents($cacheFile);
    
    echo "<div style='background-color: #d9edf7; border-left: 5px solid #31708f; padding: 15px; margin: 15px 0;'>";
    echo "<h3>Önbellek Kullanılıyor</h3>";
    echo "<p>Ürün sayısı önbellekten alındı. Son güncelleme: " . date('Y-m-d H:i:s', filemtime($cacheFile)) . "</p>";
    echo "</div>";
}

if (!$useCache) {
    echo "<p>Ürün sayısı hesaplanıyor, lütfen bekleyin...</p>";
    flush();
    
    // Ürün sayfalarını al
    $testUrl = $url . '/api/products?display=[id]&output_format=JSON';
    
    // cURL başlat
    $curl = curl_init();
    
    // cURL ayarları
    curl_setopt($curl, CURLOPT_URL, $testUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($curl, CURLOPT_USERPWD, PS_API_KEY . ':');
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    
    // İsteği gönder
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    
    // cURL oturumunu kapat
    curl_close($curl);
    
    if ($httpCode == 200 && !$error) {
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['products'])) {
            $productCount = count($decodedResponse['products']);
            file_put_contents($cacheFile, $productCount);
        } else {
            echo "<p style='color:red'>API yanıtı çözülemedi: " . json_last_error_msg() . "</p>";
            $productCount = 0;
        }
    } else {
        echo "<p style='color:red'>API isteği başarısız: " . ($error ?: "HTTP Kodu: $httpCode") . "</p>";
        $productCount = 0;
    }
}

// Rastgele sayfadan ürünleri al (10 ürün/sayfa)
$totalPages = ceil($productCount / 10);
if ($randomPage > $totalPages) $randomPage = $totalPages;

// Ürünleri belirtilen sayfadan getir
$exampleProducts = [];
$pageResults = getProductsFromPage($url, PS_API_KEY, $randomPage, 10);

if ($pageResults && isset($pageResults['products'])) {
    $exampleProducts = $pageResults['products'];
}

// Bitiş zamanı
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

// Sonuçları göster
echo "<div style='background-color: #dff0d8; border-left: 5px solid #3c763d; padding: 15px; margin: 15px 0;'>";
echo "<h2 style='color: #3c763d;'>Prestashop API bağlantısı başarılı!</h2>";
echo "<p><strong>Toplam ürün sayısı:</strong> $productCount</p>";
echo "<p><strong>İşlem süresi:</strong> $executionTime saniye</p>";
echo "</div>";

// Sayfalama navigasyonu
echo "<div style='margin: 20px 0;'>";
echo "<h3>Örnek Ürünler (Sayfa $randomPage/$totalPages)</h3>";
echo "<div style='margin-bottom: 10px;'>";

$maxPages = 10;
$startPage = max(1, $randomPage - floor($maxPages / 2));
$endPage = min($totalPages, $startPage + $maxPages - 1);

if ($randomPage > 1) {
    echo "<a href='?page=1' style='margin-right: 5px; padding: 5px 10px; background: #f0f0f0; text-decoration: none;'>«</a>";
    echo "<a href='?page=" . ($randomPage - 1) . "' style='margin-right: 5px; padding: 5px 10px; background: #f0f0f0; text-decoration: none;'>‹</a>";
}

for ($i = $startPage; $i <= $endPage; $i++) {
    if ($i == $randomPage) {
        echo "<span style='margin-right: 5px; padding: 5px 10px; background: #4CAF50; color: white;'>$i</span>";
    } else {
        echo "<a href='?page=$i' style='margin-right: 5px; padding: 5px 10px; background: #f0f0f0; text-decoration: none;'>$i</a>";
    }
}

if ($randomPage < $totalPages) {
    echo "<a href='?page=" . ($randomPage + 1) . "' style='margin-right: 5px; padding: 5px 10px; background: #f0f0f0; text-decoration: none;'>›</a>";
    echo "<a href='?page=$totalPages' style='margin-right: 5px; padding: 5px 10px; background: #f0f0f0; text-decoration: none;'>»</a>";
}

echo "</div></div>";

// Örnek ürünleri göster
if (!empty($exampleProducts)) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f2f2f2;'><th>ID</th><th>Referans</th><th>Ad</th><th>Fiyat</th></tr>";
    
    foreach ($exampleProducts as $product) {
        $id = $product['id'];
        $reference = isset($product['reference']) ? $product['reference'] : '-';
        
        // İsim alanını işle (dile göre farklı olabilir)
        $name = '-';
        if (isset($product['name'])) {
            if (isset($product['name']['language'])) {
                if (is_array($product['name']['language'])) {
                    $name = reset($product['name']['language']);
                } else {
                    $name = $product['name']['language'];
                }
            } else if (is_string($product['name'])) {
                $name = $product['name'];
            }
        }
        
        // Fiyat
        $price = isset($product['price']) ? $product['price'] : '0.00';
        
        echo "<tr><td>$id</td><td>$reference</td><td>$name</td><td>$price</td></tr>";
    }
    
    echo "</table>";
}

echo "<p><a href='index.php' style='display: inline-block; padding: 8px 16px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px;'>Geri Dön</a></p>";
?>