<?php
/**
 * WebPOS - Prestashop Barkod Eşleştirme ve Web ID Güncelleme
 * 
 * Bu script, WebPOS ürünlerinin barkodlarını Prestashop'taki ürün referanslarıyla
 * eşleştirir ve web_id alanını günceller. Kategoriden bağımsız çalışır.
 */

// Hata ayıklama
ini_set('display_errors', 1);
error_reporting(E_ALL);

// AJAX isteği kontrolü - Sadece bu satırı ekledik
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Zaman aşımı limiti (saniye)
set_time_limit(0);

// Memory limit
ini_set('memory_limit', '1024M');

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

// Web modunda çalışıyorsa başlık bilgilerini ayarla
if (php_sapi_name() !== 'cli' && !$isAjax) { // Burayı güncelledik
    header('Content-Type: text/plain; charset=utf-8');
    echo "WebPOS - Prestashop Barkod Eşleştirme Başlatılıyor...\n\n";
    ob_implicit_flush(true);
    if (function_exists('ob_end_flush')) {
        @ob_end_flush();
    }
}

// Script başlangıç zamanı
$startTime = microtime(true);

// Log dosyasının adını değiştirdik
$logFile = __DIR__ . '/logs/barcode_match.log';

// Log klasörünü oluştur
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

file_put_contents($logFile, ""); // Log dosyasını temizle

// Log fonksiyonu - AJAX desteği ekledik
function logToFile($message, $level = 'INFO') {
    global $logFile, $isAjax;
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // AJAX değilse ekrana yazdır
    if (!$isAjax) {
        echo $logMessage;
    }
    
    // AJAX isteklerinde çıktı tamponunu temizle
    if ($isAjax && ob_get_level() > 0) {
        ob_flush();
        flush();
    }
}

// Başlangıç logu
logToFile("WebPOS - Prestashop Barkod Eşleştirme başlatıldı (Tüm Ürünler)");

// Senkronizasyon zaten çalışıyorsa çık
if (isSyncLocked()) {
    logToFile("Başka bir senkronizasyon zaten devam ediyor. Çıkılıyor...", 'WARNING');
    if ($isAjax) { // AJAX isteğiyse sadece çık, hata kodu döndürme
        exit;
    } else {
        exit(1);
    }
}

// Senkronizasyonu kilitle
lockSync();

// Bundan sonraki tüm kod aynı kalacak, sadece try-catch bloğu sonundaki finally bloğunu ekleyelim
try {
    // Veritabanı bağlantısı
    $db = getDbConnection();
    logToFile("Veritabanı bağlantısı başarılı");
    
    // API erişim bilgileri
    $api_key = PS_API_KEY;
    $shop_url = rtrim(PS_SHOP_URL, '/');
    
    // API isteği yapma fonksiyonu
    function makeApiRequest($url, $api_key, $timeout = 60) {
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
    
    // Prestashop'tan tüm ürünleri ve barkodları al
    logToFile("Prestashop'tan tüm ürünler alınıyor...");
    
    $prestashopBarcodes = [];
    $totalProducts = 0;
    $totalPages = 0;
    $barcodeCount = 0;
    
    // Önce toplam ürün sayısını öğrenelim
    $countUrl = $shop_url . "/api/products?output_format=JSON&limit=1";
    $result = makeApiRequest($countUrl, $api_key);
    
    if ($result['httpCode'] == 200) {
        try {
            $data = json_decode($result['response'], true);
            if (isset($data['products'])) {
                // Prestashop API tüm ID'leri dönmediği için son ürünün ID'sini tahmin edelim
                $maxProductId = 6500; // Tahmini maksimum ürün ID'si
                logToFile("Tahmini maksimum ürün ID'si: $maxProductId");
                
                // ID bazlı sorgulama yapabiliriz, tüm ürünleri almak için
                // Küçük gruplar halinde ürünleri alalım (500'er)
                $batchSize = 500;
                $totalBatches = ceil($maxProductId / $batchSize);
                
                logToFile("Toplam $totalBatches grup halinde ürünler alınacak");
                
                for ($batch = 0; $batch < $totalBatches; $batch++) {
                    $startId = $batch * $batchSize + 1;
                    $endId = ($batch + 1) * $batchSize;
                    
                    logToFile("Ürün ID aralığı $startId-$endId işleniyor...");
                    
                    // ID filtresiyle ürünleri alalım
                    $fields = "id,reference,active,name";
                    $batchUrl = $shop_url . "/api/products?display=[$fields]&filter[id]=[$startId,$endId]&limit=500&output_format=JSON";
                    
                    $result = makeApiRequest($batchUrl, $api_key, 120); // 2 dakika zaman aşımı
                    
                    if ($result['httpCode'] != 200) {
                        logToFile("Batch $batch API hatası: HTTP " . $result['httpCode'] . ", Hata: " . $result['error'], 'ERROR');
                        continue;
                    }
                    
                    $data = json_decode($result['response'], true);
                    
                    if (!isset($data['products']) || !is_array($data['products'])) {
                        logToFile("Batch $batch: Ürün bulunamadı veya boş yanıt");
                        continue;
                    }
                    
                    $products = $data['products'];
                    $batchCount = count($products);
                    $totalProducts += $batchCount;
                    
                    logToFile("Batch $batch: $batchCount ürün bulundu");
                    
                    // Her ürünü işle
                    $batchBarcodeCount = 0;
                    foreach ($products as $product) {
                        if (!isset($product['id'])) continue;
                        
                        $productId = (int)$product['id'];
                        $reference = isset($product['reference']) ? trim($product['reference']) : '';
                        $isActive = isset($product['active']) ? (bool)$product['active'] : false;
                        
                        // Ürün adını al
                        $productName = "Bilinmiyor";
                        if (isset($product['name'])) {
                            if (is_array($product['name'])) {
                                if (isset($product['name']['language'])) {
                                    if (is_array($product['name']['language'])) {
                                        $productName = trim(reset($product['name']['language']));
                                    } else {
                                        $productName = trim($product['name']['language']);
                                    }
                                }
                            } else {
                                $productName = trim($product['name']);
                            }
                        }
                        
                        // Barkodu normalize et
                        if (!empty($reference)) {
                            $normalizedReference = strtoupper(str_replace(' ', '', $reference));
                            
                            // Her ürün ID için tek bir barkod saklayalım (en son gördüğümüz)
                            $prestashopBarcodes[$normalizedReference] = [
                                'id' => $productId,
                                'original_barcode' => $reference,
                                'name' => $productName,
                                'active' => $isActive
                            ];
                            
                            $batchBarcodeCount++;
                        }
                    }
                    
                    $barcodeCount += $batchBarcodeCount;
                    logToFile("Batch $batch: $batchBarcodeCount barkodlu ürün bulundu");
                    
                    // Belleği temizle
                    unset($data);
                    unset($products);
                    
                    // API throttling önlemi
                    usleep(500000); // 0.5 saniye bekle
                }
            }
        } catch (Exception $e) {
            logToFile("JSON işleme hatası: " . $e->getMessage(), 'ERROR');
        }
    } else {
        logToFile("Ürün sayısı alınamadı: " . $result['error'], 'ERROR');
    }
    
    $uniqueBarcodeCount = count($prestashopBarcodes);
    logToFile("Toplam $totalProducts Prestashop ürünü işlendi");
    logToFile("Benzersiz barkod sayısı: $uniqueBarcodeCount");
    
    // İlk 5 barkod örneğini göster
    $count = 0;
    foreach ($prestashopBarcodes as $barcode => $productInfo) {
        if ($count < 5) {
            logToFile("Örnek barkod: $barcode -> ID: " . $productInfo['id'] . ", Ad: \"" . $productInfo['name'] . "\"");
            $count++;
        } else {
            break;
        }
    }
    
    // WebPOS'tan barkodlu ürünleri al
    logToFile("WebPOS'tan ürünleri alınıyor...");
    
    $stmt = $db->prepare("
        SELECT id, barkod, ad, web_id 
        FROM urun_stok 
        WHERE 
            barkod IS NOT NULL 
            AND barkod != '' 
            AND durum = 'aktif'
    ");
    
    $stmt->execute();
    $webPosProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalWebPosProducts = count($webPosProducts);
    logToFile("WebPOS'tan toplam $totalWebPosProducts ürün alındı");
    
    // İstatistikler
    $updatedCount = 0;
    $alreadyUpToDateCount = 0;
    $noMatchCount = 0;
    $errorCount = 0;
    
    // Sonuçları sakla
    $updates = [];
    $noMatches = [];
    $errors = [];
    
    // Eşleştirme ve güncelleme
    logToFile("Eşleştirme ve güncelleme başlatılıyor...");
    
    foreach ($webPosProducts as $product) {
        $webPosId = $product['id'];
        $webPosBarkod = trim($product['barkod']);
        $webPosAd = $product['ad'];
        $currentWebId = $product['web_id'];
        
        // Boş barkod kontrolü
        if (empty($webPosBarkod)) {
            continue;
        }
        
        // Barkodu normalize et
        $normalizedBarkod = strtoupper(str_replace(' ', '', $webPosBarkod));
        
        // Bu barkod Prestashop'ta var mı?
        if (isset($prestashopBarcodes[$normalizedBarkod])) {
            $prestashopInfo = $prestashopBarcodes[$normalizedBarkod];
            $prestashopId = $prestashopInfo['id'];
            $prestashopName = $prestashopInfo['name'];
            
            // Zaten güncel mi?
            if ($currentWebId == $prestashopId) {
                $alreadyUpToDateCount++;
                continue;
            }
            
            // Web ID'yi güncelle
            try {
                $updateStmt = $db->prepare("UPDATE urun_stok SET web_id = :web_id WHERE id = :id");
                $updateStmt->execute([
                    ':web_id' => $prestashopId,
                    ':id' => $webPosId
                ]);
                
                if ($updateStmt->rowCount() > 0) {
                    logToFile("Ürün güncellendi: WebPOS ID=$webPosId, Ad=\"$webPosAd\", Barkod=$webPosBarkod, Eski Web ID=" . ($currentWebId ?: 'Boş') . ", Yeni Web ID=$prestashopId, PS Ad=\"$prestashopName\"", 'SUCCESS');
                    $updatedCount++;
                    $updates[] = [
                        'id' => $webPosId,
                        'barkod' => $webPosBarkod,
                        'ad' => $webPosAd,
                        'old_web_id' => $currentWebId ?: null,
                        'new_web_id' => $prestashopId,
                        'ps_name' => $prestashopName
                    ];
                } else {
                    logToFile("Ürün güncellenemedi: WebPOS ID=$webPosId, Barkod=$webPosBarkod", 'WARNING');
                    $errorCount++;
                    $errors[] = [
                        'id' => $webPosId,
                        'barkod' => $webPosBarkod,
                        'ad' => $webPosAd,
                        'reason' => 'Veritabanı güncellemesi başarısız'
                    ];
                }
            } catch (Exception $e) {
                logToFile("Güncelleme hatası: " . $e->getMessage() . ", WebPOS ID=$webPosId", 'ERROR');
                $errorCount++;
                $errors[] = [
                    'id' => $webPosId,
                    'barkod' => $webPosBarkod,
                    'ad' => $webPosAd,
                    'reason' => 'Hata: ' . $e->getMessage()
                ];
            }
        } else {
            // Eşleşme yok
            $noMatchCount++;
            
            // İlk 20 eşleşmeyen ürünü kaydet
            if (count($noMatches) < 20) {
                $noMatches[] = [
                    'id' => $webPosId,
                    'barkod' => $webPosBarkod,
                    'ad' => $webPosAd
                ];
            }
        }
    }
    
    // İşlem sonu
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    // Eşleşmeyen ürün örnekleri
    if (!empty($noMatches)) {
        logToFile("\nEşleşme bulunamayan ürün örnekleri:", 'INFO');
        foreach ($noMatches as $index => $product) {
            logToFile(($index + 1) . ". ID: " . $product['id'] . ", Barkod: " . $product['barkod'] . ", Ad: " . $product['ad'], 'INFO');
        }
    }
    
        // Özet
    logToFile("=======================================================================", 'SUCCESS');
    logToFile("İşlem tamamlandı.", 'SUCCESS');
    logToFile("Toplam Prestashop ürünü: $totalProducts", 'SUCCESS');
    logToFile("Benzersiz barkod sayısı: $uniqueBarcodeCount", 'SUCCESS');
    logToFile("Toplam WebPOS ürünü: $totalWebPosProducts", 'SUCCESS');
    logToFile("Güncellenen ürün sayısı: $updatedCount", 'SUCCESS');
    logToFile("Zaten güncel olan ürün sayısı: $alreadyUpToDateCount", 'SUCCESS');
    logToFile("Eşleşme bulunamayan ürün sayısı: $noMatchCount", 'SUCCESS');
    logToFile("Hatalı ürün sayısı: $errorCount", 'SUCCESS');
    logToFile("Toplam çalışma süresi: $executionTime saniye", 'SUCCESS');
    logToFile("=======================================================================", 'SUCCESS');
    
    // Özeti kaydet - Summary dosyasının adını değiştirdik
    $summaryFile = __DIR__ . '/logs/barcode_match_summary.json';
    $summary = [
        'timestamp' => date('Y-m-d H:i:s'),
        'prestashop_count' => $totalProducts,
        'prestashop_barcode_count' => $uniqueBarcodeCount,
        'webpos_count' => $totalWebPosProducts,
        'success_count' => $updatedCount,
        'already_uptodate_count' => $alreadyUpToDateCount,
        'skipped_count' => $noMatchCount,
        'error_count' => $errorCount,
        'execution_time' => $executionTime,
        'updates' => $updates,
        'no_matches_examples' => $noMatches,
        'errors' => $errors
    ];
    file_put_contents($summaryFile, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    logToFile("Özet kaydedildi: $summaryFile");
    
} catch (Exception $e) {
    logToFile("İşlem hatası: " . $e->getMessage(), 'ERROR');
} finally {
    // Her durumda kilidi kaldır
    unlockSync();
}

logToFile("Barkod eşleştirme ve web_id güncelleme tamamlandı");