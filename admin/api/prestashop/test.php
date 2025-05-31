<?php
/**
 * Prestashop Barkod Analizi Scripti
 * Bu script, Prestashop'taki barkod durumunu analiz eder
 */

// Hata ayıklama
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

// Web modunda çalışıyorsa başlık bilgilerini ayarla
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Prestashop Barkod Analizi Başlatılıyor...\n\n";
    ob_implicit_flush(true);
    if (function_exists('ob_end_flush')) {
        @ob_end_flush();
    }
}

// Script başlangıç zamanı
$startTime = microtime(true);

// Log fonksiyonu
function logToScreen($message) {
    echo $message . PHP_EOL;
}

// Başlangıç logu
logToScreen("Prestashop Barkod Analizi Başlatıldı");

// API fonksiyonu
function makeApiRequest($url, $api_key, $timeout = 30) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'httpCode' => $httpCode,
        'response' => $response,
        'error' => $error
    ];
}

try {
    // API erişim bilgileri
    $api_key = PS_API_KEY;
    $shop_url = rtrim(PS_SHOP_URL, '/');
    
    // Çeşitli barkod durumlarını takip etmek için sayaçlar
    $totalProducts = 0;
    $withReferenceCount = 0;
    $emptyReferenceCount = 0;
    $uniqueReferenceCount = 0;
    $duplicateReferenceCount = 0;
    
    // Tüm barkodları saklamak için
    $allReferences = [];
    $uniqueReferences = [];
    $duplicateReferences = [];
    
    // Sayfa başına ürün sayısı
    $limit = 250;
    $page = 1;
    $maxPage = 30; // En fazla 7500 ürün analiz et
    
    logToScreen("Prestashop'tan ürünler alınıyor...");
    
    // Tüm sayfaları işle
    while ($page <= $maxPage) {
        logToScreen("Sayfa $page işleniyor...");
        
        // Sadece ihtiyacımız olan alanları çek
        $fields = "id,reference,active";
        $requestUrl = $shop_url . "/api/products?display=[$fields]&limit=$limit&page=$page&output_format=JSON";
        
        $result = makeApiRequest($requestUrl, $api_key, 60);
        
        if ($result['httpCode'] != 200) {
            logToScreen("API Hatası: HTTP " . $result['httpCode'] . ", Hata: " . $result['error']);
            break;
        }
        
        $data = json_decode($result['response'], true);
        
        if (!isset($data['products']) || !is_array($data['products']) || empty($data['products'])) {
            logToScreen("Sayfa $page: Ürün bulunamadı veya son sayfaya ulaşıldı");
            break;
        }
        
        $products = $data['products'];
        $pageCount = count($products);
        $totalProducts += $pageCount;
        
        logToScreen("Sayfa $page: $pageCount ürün bulundu");
        
        // Her ürünü işle
        foreach ($products as $product) {
            if (isset($product['id'])) {
                $productId = $product['id'];
                $reference = isset($product['reference']) ? trim($product['reference']) : '';
                $isActive = isset($product['active']) ? (bool)$product['active'] : false;
                
                if (!empty($reference)) {
                    $withReferenceCount++;
                    
                    // Bu barkodu daha önce görmüş müyüz?
                    if (!isset($allReferences[$reference])) {
                        $allReferences[$reference] = [$productId];
                        $uniqueReferences[$reference] = $productId;
                        $uniqueReferenceCount++;
                    } else {
                        // Bu barkod daha önce kullanılmış
                        $allReferences[$reference][] = $productId;
                        
                        // Eğer ilk kez yinelenen bir barkod ise, sayacı artır
                        if (count($allReferences[$reference]) == 2) {
                            $duplicateReferenceCount++;
                            // Tekrarlayan barkodu kaydet
                            $duplicateReferences[$reference] = $allReferences[$reference];
                            // Artık benzersiz değil
                            unset($uniqueReferences[$reference]);
                        }
                    }
                } else {
                    $emptyReferenceCount++;
                }
            }
        }
        
        // Sonraki sayfa
        $page++;
        
        // Belleği temizle
        unset($data);
        unset($products);
        
        // Eğer bu sayfada tam ürün yoksa, son sayfadayızdır
        if ($pageCount < $limit) {
            logToScreen("Son sayfaya ulaşıldı");
            break;
        }
        
        // API throttling önlemi
        usleep(300000); // 0.3 saniye
    }
    
    // Sonuçları raporla
    logToScreen("\n--- Analiz Sonuçları ---");
    logToScreen("Toplam analiz edilen ürün: $totalProducts");
    logToScreen("Barkod alanı dolu olan ürün: $withReferenceCount");
    logToScreen("Barkod alanı boş olan ürün: $emptyReferenceCount");
    logToScreen("Benzersiz barkod sayısı: $uniqueReferenceCount");
    logToScreen("Tekrarlanan barkod sayısı: $duplicateReferenceCount");
    
    // Boşluk ve özel karakter analizi
    $trimmedDifferentCount = 0;
    $hasSpaceCount = 0;
    $hasSpecialCharCount = 0;
    
    foreach ($uniqueReferences as $reference => $productId) {
        $trimmedReference = trim($reference);
        if ($reference !== $trimmedReference) {
            $trimmedDifferentCount++;
        }
        
        if (strpos($reference, ' ') !== false) {
            $hasSpaceCount++;
        }
        
        if (preg_match('/[^a-zA-Z0-9]/', $reference)) {
            $hasSpecialCharCount++;
        }
    }
    
    logToScreen("\n--- Barkod Format Analizi ---");
    logToScreen("Başında/sonunda boşluk içeren barkod: $trimmedDifferentCount");
    logToScreen("İçinde boşluk içeren barkod: $hasSpaceCount");
    logToScreen("Özel karakter içeren barkod: $hasSpecialCharCount");
    
    // Uzunluk dağılımı
    $lengthDistribution = [];
    foreach ($uniqueReferences as $reference => $productId) {
        $length = strlen($reference);
        if (!isset($lengthDistribution[$length])) {
            $lengthDistribution[$length] = 0;
        }
        $lengthDistribution[$length]++;
    }
    
    logToScreen("\n--- Barkod Uzunluk Dağılımı ---");
    ksort($lengthDistribution);
    foreach ($lengthDistribution as $length => $count) {
        logToScreen("$length karakter: $count adet");
    }
    
    // İlk 10 tekrarlanan barkodu göster
    if (count($duplicateReferences) > 0) {
        logToScreen("\n--- Tekrarlanan Barkodlar (İlk 10) ---");
        $i = 0;
        foreach ($duplicateReferences as $reference => $productIds) {
            if ($i++ >= 10) break;
            logToScreen("Barkod: $reference, Ürün ID'leri: " . implode(", ", $productIds));
        }
    }
    
    // Bitiş zamanı
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    logToScreen("\nAnaliz tamamlandı. Toplam süre: $executionTime saniye");
    
} catch (Exception $e) {
    logToScreen("Hata: " . $e->getMessage());
}