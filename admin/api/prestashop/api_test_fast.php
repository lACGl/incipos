<?php
/**
 * Prestashop API Bağlantı Testi (Ultra Hızlı)
 * 
 * Bu dosya sadece bir ürün sorgusu yaparak hızlı bir API bağlantı testi gerçekleştirir.
 */

// Hata göstermeyi aktifleştir
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Maksimum çalışma süresi (5 saniye)
set_time_limit(5);

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

echo "<h1>Prestashop API Bağlantı Testi (Ultra Hızlı)</h1>";

// API Bilgileri
echo "<h2>API Konfigürasyonu</h2>";
echo "<p>API Anahtarı: " . PS_API_KEY . "</p>";
echo "<p>Shop URL: " . PS_SHOP_URL . "</p>";

// Zamanı ölç
$startTime = microtime(true);

// URL'yi temizle
$url = rtrim(PS_SHOP_URL, '/');

// Sadece bir ürünü sorgula, minimum veri çek
$testUrl = $url . '/api/products?display=[id]&output_format=JSON&limit=1';

echo "<p>Test URL: $testUrl</p>";

// cURL başlat
$curl = curl_init();

// cURL ayarları
curl_setopt($curl, CURLOPT_URL, $testUrl);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($curl, CURLOPT_USERPWD, PS_API_KEY . ':');
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Yönlendirmeleri takip et
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_MAXREDIRS, 2);

// HTTPS için SSL sertifika doğrulamasını devre dışı bırakma
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

// Zaman aşımı ayarları - 3 saniye
curl_setopt($curl, CURLOPT_TIMEOUT, 3);
curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);

// İsteği gönder
$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);

// cURL oturumunu kapat
curl_close($curl);

// Bitiş zamanı
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

// Sonuçları göster
echo "<h3>HTTP Durum Kodu: $httpCode (İşlem süresi: $executionTime saniye)</h3>";

if ($error) {
    echo "<p style='color:red'>cURL Hatası: $error</p>";
}

// Yanıt içeriğini kontrol et
if (!empty($response)) {
    // JSON formatına dönüştürmeyi dene
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p style='color:green'>Bağlantı başarılı! Yanıt geçerli JSON formatında.</p>";
        
        if (isset($decodedResponse['products'])) {
            $count = count($decodedResponse['products']);
            echo "<div style='background-color: #dff0d8; border-left: 5px solid #3c763d; padding: 15px; margin: 15px 0;'>";
            echo "<h2 style='color: #3c763d;'>Prestashop API bağlantısı başarılı!</h2>";
            echo "<p>Prestashop API'ye bağlantı kuruldu ve sistem yanıt veriyor.</p>";
            echo "</div>";
            
            // Ürün sayısını önbellekten al (varsa)
            $cacheFile = __DIR__ . '/cache/product_count.txt';
            $productCount = "bilinmiyor";
            
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
                $productCount = file_get_contents($cacheFile);
                echo "<p>Toplam ürün sayısı (önbellekten): <strong>$productCount</strong></p>";
            } else {
                echo "<p>Ürün sayısı önbellekte bulunamadı. Tam ürün sayısını görmek için 'Tam API Testi'ni çalıştırın.</p>";
            }
        } else {
            echo "<p style='color:orange'>Ürün listesi bulunamadı veya boş.</p>";
        }
    } else {
        echo "<p style='color:red'>JSON çözme hatası: " . json_last_error_msg() . "</p>";
        
        // İlk 500 karakteri göster
        $preview = substr($response, 0, 500);
        echo "<p>Yanıt önizlemesi (ilk 500 karakter):</p>";
        echo "<pre>" . htmlspecialchars($preview) . "</pre>";
    }
} else {
    echo "<p style='color:red'>Boş yanıt!</p>";
}

echo "<p><a href='index.php'>Geri Dön</a></p>";
?>