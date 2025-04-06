<?php
/**
 * Prestashop Senkronizasyon Script (GET-EDIT Yaklaşımı)
 * 
 * Bu script WebPOS'taki web_id ile Prestashop ID'si eşleşen ürünlerin
 * fiyat ve stok bilgilerini güncellemek için Prestashop API'sinin
 * önerdiği get-edit workflow'u kullanır. Böylece diğer ürün bilgileri korunur.
 */

// Hata ayıklama
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Zaman aşımı limiti (saniye)
set_time_limit(0);

// Memory limit
ini_set('memory_limit', '512M');

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

// Web modunda çalışıyorsa başlık bilgilerini ayarla
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Prestashop GET-EDIT Senkronizasyon Başlatılıyor...\n\n";
    ob_implicit_flush(true);
    if (function_exists('ob_end_flush')) {
        @ob_end_flush();
    }
}

// Script başlangıç zamanı
$startTime = microtime(true);

// Log dosyasının yolunu belirle
$logFile = __DIR__ . '/logs/prestashop_sync.log';

// Log klasörünü oluştur
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Sonuç istatistikleri için dizi
$stats = [
    'success' => [],
    'partial' => [],
    'failed' => []
];

/**
 * Log mesajını yazma
 * 
 * @param string $message Log mesajı
 * @param string $level Log seviyesi (INFO, ERROR, WARNING, SUCCESS, PARTIAL, FAILED)
 */
function logToFile($message, $level = 'INFO') {
    global $logFile;
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date] [$level] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    echo $logMessage;
}

// Başlangıç logu
logToFile("Prestashop GET-EDIT senkronizasyon başlatıldı");

// Senkronizasyon zaten çalışıyorsa çık
if (isSyncLocked()) {
    $message = "Senkronizasyon zaten devam ediyor. Çıkılıyor...";
    logToFile($message, 'WARNING');
    exit(1);
}

// Senkronizasyonu kilitle
lockSync();

try {
    // Veritabanı bağlantısı oluştur
    $db = getDbConnection();
    
    // WebPOS'tan web_id'ye göre ürünleri al
    $sql = "SELECT web_id, 
            satis_fiyati, 
            stok_miktari as toplam_stok
            FROM urun_stok 
            WHERE web_id IS NOT NULL AND web_id != '' AND durum = 'aktif'
            GROUP BY web_id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sonuçları loglayalım
    logToFile("WebPOS'tan " . count($products) . " ürün alındı (web_id'ye göre gruplandırılmış)");
    
    if (empty($products)) {
        $message = "Senkronize edilecek ürün bulunamadı";
        logToFile($message);
        
        // Senkronizasyon kilidini kaldır
        unlockSync();
        exit(0);
    }
    
    $totalCount = count($products);
    $message = "$totalCount adet ürün senkronize edilecek";
    logToFile($message);
    
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    $partialCount = 0;
    
    // API erişim bilgileri 
    $api_key = PS_API_KEY;
    $shop_url = rtrim(PS_SHOP_URL, '/');
    
    // Her bir ürün için senkronizasyon işlemi yap
    foreach ($products as $index => $product) {
        $webId = $product['web_id']; // Bu, Prestashop ID'sini içeriyor
        $price = $product['satis_fiyati'];
        $stock = intval($product['toplam_stok']);
        
        $message = "İşleniyor (" . ($index + 1) . "/$totalCount): web_id=$webId";
        logToFile($message);
        
        // Boş/Geçersiz değerleri kontrol et
        if (empty($webId) || $price === null || $stock === null) {
            logToFile("Geçersiz ürün verileri: web_id=$webId", 'WARNING');
            $skippedCount++;
            $stats['failed'][] = [
                'id' => $webId,
                'reason' => 'Geçersiz ürün verileri'
            ];
            continue;
        }
        
        // Web_id değerini temizle ve doğrula
        $webId = trim($webId);
        
        // Web_id sayısal değil ise atla
        if (!is_numeric($webId)) {
            logToFile("Web_id sayısal değil: $webId", 'WARNING');
            $skippedCount++;
            $stats['failed'][] = [
                'id' => $webId,
                'reason' => 'Web_id sayısal değil'
            ];
            continue;
        }
        
        // Prestashop ürün ID'si olarak web_id kullan
        $prestashopId = intval($webId);
        
        if ($prestashopId <= 0) {
            logToFile("Geçersiz Prestashop ID: $webId", 'WARNING');
            $skippedCount++;
            $stats['failed'][] = [
                'id' => $webId,
                'reason' => 'Geçersiz Prestashop ID'
            ];
            continue;
        }
        
        logToFile("İşleniyor: PS ID=$prestashopId, Fiyat=$price, Stok=$stock");
        
        // Fiyat ve stok değerlerini düzelt
        $price = floatval($price);
        $stock = intval($stock);
        
        // Ürün güncelleme
        $priceSuccess = false;
        $stockSuccess = false;
        $priceErrorReason = "";
        $stockErrorReason = "";
        
        // ADIM 1: GET-EDIT yaklaşımı ile fiyat güncelleme
        try {
            logToFile("Ürün bilgileri alınıyor (GET): PS ID=$prestashopId");
            
            // Önce ürünün tüm bilgilerini al
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $shop_url . '/api/products/' . $prestashopId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($http_code == 200 && !empty($response)) {
                // Yanıt var, XML'i ayrıştır
                $xml = simplexml_load_string($response);
                
                if ($xml) {
                    // Sadece fiyat alanını değiştir, diğer tüm bilgiler korunacak
                    $currentPrice = (string)$xml->product->price;
                    logToFile("Mevcut fiyat: $currentPrice, Yeni fiyat: $price");
                    
                    // Ürün durumunu (active/inactive) korumak için sakla
                    $currentActive = isset($xml->product->active) ? (string)$xml->product->active : '1';
                    
                    // XML'den yazılamaz alanları kaldır
                    if (isset($xml->product->manufacturer_name)) {
                        unset($xml->product->manufacturer_name);
                    }
                    
                    // position alanını kaldır
                    if (isset($xml->product->position_in_category)) {
                        unset($xml->product->position_in_category);
                    }
                    
                    // Prestashop XML'inde yazılamayan diğer alanları da kaldıralım
                    $removeFields = [
                        'position', 
                        'position_in_category', 
                        'manufacturer_name', 
                        'quantity',
                        'id_default_image',
                        'id_default_combination',
                        'associations',
                        'indexed'
                    ];
                    
                    foreach ($removeFields as $field) {
                        if (isset($xml->product->$field)) {
                            unset($xml->product->$field);
                        }
                    }
                    
                    // WebPOS'tan alınan vergili fiyatı, vergisiz fiyata çevirme
                    // Ürünün KDV oranını veritabanından al
                    $sql = "SELECT kdv_orani FROM urun_stok WHERE web_id = :web_id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([':web_id' => $prestashopId]);
                    $kdvOrani = $stmt->fetchColumn();
                    
                    if ($kdvOrani) {
                        // Vergili fiyattan vergisiz fiyata çevirme formülü
                        $kdvKatsayisi = (100 + floatval($kdvOrani)) / 100;
                        $vergisizFiyat = $price / $kdvKatsayisi;
                        $vergisizFiyat = round($vergisizFiyat, 6); // Prestashop 6 ondalık basamak kullanır
                        
                        logToFile("KDV oranı: %{$kdvOrani}, Vergili fiyat: {$price}, Vergisiz fiyat: {$vergisizFiyat}");
                        
                        // Vergisiz fiyatı XML'e ekle
                        $xml->product->price = $vergisizFiyat;
                    } else {
                        // KDV oranı bulunamadıysa normal fiyatı kullan
                        $xml->product->price = $price;
                    }
                    
                    // Ürün durumunu koru
                    $xml->product->active = $currentActive;
                    
                    // Güncellenmiş XML'i geri gönder
                    $updatedXml = $xml->asXML();
                    
                    // Bu kısmı loglayalım, XML içeriğini görmek için
                    logToFile("Fiyat güncelleme XML hazırlandı. Gönderiliyor...");
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $shop_url . '/api/products/' . $prestashopId);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $updatedXml);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    
                    curl_close($ch);
                    
                    if ($http_code >= 200 && $http_code < 300) {
                        logToFile("Fiyat güncelleme başarılı: PS ID=$prestashopId, Fiyat=$price");
                        $priceSuccess = true;
                    } else {
                        $priceErrorReason = "HTTP=$http_code, Hata=$error";
                        logToFile("Fiyat güncelleme hatası: PS ID=$prestashopId, $priceErrorReason", 'ERROR');
                        
                        if (!empty($response)) {
                            logToFile("Fiyat API Yanıtı: " . substr($response, 0, 500), 'DEBUG');
                        }
                    }
                } else {
                    $priceErrorReason = "XML ayrıştırma hatası";
                    logToFile("Ürün XML ayrıştırma hatası: PS ID=$prestashopId", 'ERROR');
                }
            } else {
                $priceErrorReason = "HTTP=$http_code, Hata=$error";
                logToFile("Ürün bilgileri alınamadı: PS ID=$prestashopId, $priceErrorReason", 'ERROR');
            }
        } catch (Exception $e) {
            $priceErrorReason = $e->getMessage();
            logToFile("Fiyat güncelleme hatası: " . $priceErrorReason, 'ERROR');
        }
        
        // ADIM 2: Stok güncelleme - önce GET ile stok detaylarını al
        try {
            // Stok ID'sini al
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $shop_url . '/api/stock_availables?filter[id_product]=' . $prestashopId . '&display=full');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($http_code == 200 && !empty($response)) {
                // SimpleXML ile analiz et
                $xml = simplexml_load_string($response);
                
                if ($xml) {
                    $stockInfos = $xml->xpath('//stock_available');
                    if (!empty($stockInfos)) {
                        $stockId = (string)$stockInfos[0]->id;
                        logToFile("Stok ID bulundu: StockID=$stockId, PS ID=$prestashopId");
                        
                        // Şimdi stok detaylarını al
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $shop_url . '/api/stock_availables/' . $stockId);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                        curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                        
                        $response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        
                        curl_close($ch);
                        
                        if ($http_code == 200 && !empty($response)) {
                            $stockXml = simplexml_load_string($response);
                            
                            if ($stockXml) {
                                // Mevcut depends_on_stock değerini kontrol et ve kaydet
                                $has_depends_on_stock = isset($stockXml->stock_available->depends_on_stock);
                                $current_depends_on_stock = $has_depends_on_stock ? 
                                    (string)$stockXml->stock_available->depends_on_stock : '0';
                                
                                logToFile("Mevcut depends_on_stock değeri: $current_depends_on_stock", 'DEBUG');
                                
                                // Stok miktarını değiştir
                                $stockXml->stock_available->quantity = $stock;
                                
                                // depends_on_stock değerini 0 yap (manuel stok kontrolü için)
                                if ($has_depends_on_stock) {
                                    // Mevcut değeri değiştir
                                    $stockXml->stock_available->depends_on_stock = '0';
                                } else {
                                    // Değer yoksa ekle
                                    $stockXml->stock_available->addChild('depends_on_stock', '0');
                                }
                                
                                // out_of_stock değeri eğer yoksa varsayılan olarak '1' ekle
                                if (!isset($stockXml->stock_available->out_of_stock)) {
                                    $stockXml->stock_available->addChild('out_of_stock', '1');
                                }
                                
                                // id_product_attribute değerini koru (kombinasyonlar için önemli)
                                if (!isset($stockXml->stock_available->id_product_attribute)) {
                                    $stockXml->stock_available->addChild('id_product_attribute', '0');
                                }
                                
                                // Güncellenmiş XML'i geri gönder (EDIT)
                                $updatedStockXml = $stockXml->asXML();
                                
                                logToFile("Stok güncelleniyor (EDIT): PS ID=$prestashopId, StockID=$stockId, Yeni stok=$stock, depends_on_stock=0");
                                
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $shop_url . '/api/stock_availables/' . $stockId);
                                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $updatedStockXml);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                                curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
                                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                
                                $response = curl_exec($ch);
                                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                $error = curl_error($ch);
                                
                                curl_close($ch);
                                
                                if ($http_code >= 200 && $http_code < 300) {
                                    logToFile("Stok güncelleme başarılı: PS ID=$prestashopId, StockID=$stockId, Stok=$stock");
                                    $stockSuccess = true;
                                } else {
                                    $stockErrorReason = "HTTP=$http_code, Hata=$error";
                                    logToFile("Stok güncelleme hatası: PS ID=$prestashopId, StockID=$stockId, $stockErrorReason", 'ERROR');
                                    
                                    if (!empty($response)) {
                                        logToFile("Stok API Yanıtı: " . substr($response, 0, 400), 'DEBUG');
                                    }
                                }
                            } else {
                                $stockErrorReason = "XML analizi başarısız";
                                logToFile("Stok XML analizi başarısız: PS ID=$prestashopId, StockID=$stockId", 'ERROR');
                            }
                        } else {
                            $stockErrorReason = "HTTP=$http_code";
                            logToFile("Stok detayları alınamadı: PS ID=$prestashopId, StockID=$stockId, $stockErrorReason", 'ERROR');
                        }
                    } else {
                        $stockErrorReason = "Stok kaydı bulunamadı";
                        logToFile("Stok kaydı bulunamadı: PS ID=$prestashopId", 'ERROR');
                    }
                } else {
                    $stockErrorReason = "XML analizi başarısız";
                    logToFile("Stok XML analizi başarısız: PS ID=$prestashopId", 'ERROR');
                }
            } else {
                $stockErrorReason = "HTTP=$http_code";
                logToFile("Stok bilgileri alınamadı: PS ID=$prestashopId, $stockErrorReason", 'ERROR');
                
                if (!empty($response)) {
                    logToFile("Stok GET yanıtı: " . substr($response, 0, 200), 'DEBUG');
                }
            }
        } catch (Exception $e) {
            $stockErrorReason = $e->getMessage();
            logToFile("Stok güncelleme hatası: " . $stockErrorReason, 'ERROR');
        }
        
        // Güncelleme durumunu belirle
        if ($priceSuccess && $stockSuccess) {
            $successCount++;
            logToFile("Güncelleme tamamen başarılı: PS ID=$prestashopId", 'SUCCESS');
            $stats['success'][] = [
                'id' => $prestashopId, 
                'price' => $price, 
                'stock' => $stock
            ];
        } elseif ($priceSuccess || $stockSuccess) {
            $partialCount++;
            
            $reason = "";
            $updated = [];
            
            if ($priceSuccess) {
                $updated[] = "fiyat";
                $reason = "Stok güncellenemedi: $stockErrorReason";
            } else {
                $updated[] = "stok";
                $reason = "Fiyat güncellenemedi: $priceErrorReason";
            }
            
            logToFile("Güncelleme kısmen başarılı: PS ID=$prestashopId - Güncellenenler: " . implode(", ", $updated), 'PARTIAL');
            
            $stats['partial'][] = [
                'id' => $prestashopId,
                'reason' => $reason,
                'price_success' => $priceSuccess,
                'stock_success' => $stockSuccess,
                'updated' => $updated
            ];
        } else {
            $errorCount++;
            $reason = "Fiyat: $priceErrorReason, Stok: $stockErrorReason";
            logToFile("Güncelleme tamamen başarısız: PS ID=$prestashopId - $reason", 'FAILED');
            
            $stats['failed'][] = [
                'id' => $prestashopId,
                'reason' => $reason
            ];
        }
        
        // Her 5 ürün işlendikten sonra veya son üründe geçici özeti kaydet
            if ($index % 5 === 0 || $index === count($products) - 1) {
                $currentTime = microtime(true);
                $elapsedTime = round($currentTime - $startTime, 2);
                
                $tempStats = [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'success_count' => $successCount,
                    'partial_count' => $partialCount,
                    'error_count' => $errorCount,
                    'skipped_count' => $skippedCount,
                    'total_count' => count($products),
                    'processed_count' => $index + 1,
                    'execution_time_so_far' => $elapsedTime,
                    'success' => $stats['success'],
                    'partial' => $stats['partial'],
                    'failed' => $stats['failed']
                ];
                
                // Geçici özeti kaydet
                $tempSummaryFile = __DIR__ . '/logs/current_sync.json';
                file_put_contents($tempSummaryFile, json_encode($tempStats, JSON_PRETTY_PRINT));
            }
    }
    
    // Senkronizasyon sonucunu logla
$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

// Özet bilgilendirme
$summary = "Senkronizasyon tamamlandı. Başarılı: $successCount, Kısmen Başarılı: $partialCount, Başarısız: $errorCount, Atlanan: $skippedCount, Toplam Süre: $executionTime saniye";
logToFile($summary);

// BAŞLIKLAR: Her bir kategorinin başlığını daha belirgin hale getir
logToFile("=======================================================================", 'SUCCESS');
logToFile("=================== SENKRONIZASYON SONUÇLARI =========================", 'SUCCESS');
logToFile("=======================================================================", 'SUCCESS');

// BAŞARILI ÜRÜNLER
if (!empty($stats['success'])) {
    logToFile("=======================================================================", 'SUCCESS');
    logToFile("BAŞARILI ÜRÜNLER (" . count($stats['success']) . " ADET)", 'SUCCESS');
    logToFile("=======================================================================", 'SUCCESS');
    
    // Her bir başarılı ürünü detaylı olarak raporla
    foreach ($stats['success'] as $item) {
        $id = isset($item['id']) ? $item['id'] : 'Bilinmiyor';
        $price = isset($item['price']) ? $item['price'] : 'Bilinmiyor';
        $stock = isset($item['stock']) ? $item['stock'] : 'Bilinmiyor';
        
        // Ürün adını veritabanından al (eğer varsa)
        $productName = "Bilinmiyor";
        try {
            $sql = "SELECT ad FROM urun_stok WHERE web_id = :web_id LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':web_id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['ad'])) {
                $productName = $result['ad'];
            }
        } catch (Exception $e) {
            // Hata durumunda sessizce devam et
        }
        
        logToFile("  ✓ ID: $id - \"$productName\" - Fiyat: $price - Stok: $stock", 'SUCCESS');
    }
    
    // Çok fazla başarılı ürün olduğunda hepsini gösterme
    if (count($stats['success']) > 100) {
        logToFile("  ... ve " . (count($stats['success']) - 100) . " adet daha.", 'SUCCESS');
    }
}

// KISMEN BAŞARILI ÜRÜNLER
if (!empty($stats['partial'])) {
    logToFile("=======================================================================", 'PARTIAL');
    logToFile("KISMEN BAŞARILI ÜRÜNLER (" . count($stats['partial']) . " ADET)", 'PARTIAL');
    logToFile("=======================================================================", 'PARTIAL');
    
    foreach ($stats['partial'] as $item) {
        $id = isset($item['id']) ? $item['id'] : 'Bilinmiyor';
        $reason = isset($item['reason']) ? $item['reason'] : 'Bilinmiyor';
        $updated = isset($item['updated']) ? implode(", ", $item['updated']) : 'Bilinmiyor';
        
        // Ürün adını veritabanından al
        $productName = "Bilinmiyor";
        try {
            $sql = "SELECT ad FROM urun_stok WHERE web_id = :web_id LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':web_id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['ad'])) {
                $productName = $result['ad'];
            }
        } catch (Exception $e) {
            // Hata durumunda sessizce devam et
        }
        
        $priceSuccess = isset($item['price_success']) && $item['price_success'] ? "✓" : "✗";
        $stockSuccess = isset($item['stock_success']) && $item['stock_success'] ? "✓" : "✗";
        
        logToFile("  ⚠ ID: $id - \"$productName\" - Fiyat: $priceSuccess - Stok: $stockSuccess", 'PARTIAL');
        logToFile("    → Güncellenenler: $updated", 'PARTIAL');
        logToFile("    → Hata nedeni: $reason", 'PARTIAL');
    }
}

// BAŞARISIZ ÜRÜNLER
if (!empty($stats['failed'])) {
    logToFile("=======================================================================", 'FAILED');
    logToFile("BAŞARISIZ ÜRÜNLER (" . count($stats['failed']) . " ADET)", 'FAILED');
    logToFile("=======================================================================", 'FAILED');
    
    foreach ($stats['failed'] as $item) {
        $id = isset($item['id']) ? $item['id'] : 'Bilinmiyor';
        $reason = isset($item['reason']) ? $item['reason'] : 'Bilinmiyor';
        
        // Ürün adını veritabanından al
        $productName = "Bilinmiyor";
        try {
            $sql = "SELECT ad FROM urun_stok WHERE web_id = :web_id LIMIT 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([':web_id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['ad'])) {
                $productName = $result['ad'];
            }
        } catch (Exception $e) {
            // Hata durumunda sessizce devam et
        }
        
        logToFile("  ✗ ID: $id - \"$productName\"", 'FAILED');
        logToFile("    → Hata nedeni: $reason", 'FAILED');
    }
}

// ATLANAN ÜRÜNLER
if ($skippedCount > 0) {
    logToFile("=======================================================================", 'WARNING');
    logToFile("ATLANAN ÜRÜNLER (" . $skippedCount . " ADET)", 'WARNING');
    logToFile("=======================================================================", 'WARNING');
    // Not: Skipped ürünler genellikle zaten stats['failed'] içindedir
}

logToFile("=======================================================================", 'SUCCESS');
logToFile("======================= SENKRONIZASYON SONU ===========================", 'SUCCESS');
logToFile("=======================================================================", 'SUCCESS');

// Özet istatistikleri kaydet (index.php'de gösterilecek)
$summaryStats = [
    'timestamp' => date('Y-m-d H:i:s'),
    'success_count' => $successCount,
    'partial_count' => $partialCount,
    'error_count' => $errorCount,
    'skipped_count' => $skippedCount,
    'execution_time' => $executionTime,
    'success' => $stats['success'],  // Tüm başarılı ürünleri kaydet
    'partial' => $stats['partial'],  // Tüm kısmen başarılı ürünleri kaydet
    'failed' => $stats['failed']     // Tüm başarısız ürünleri kaydet
];

// Son senkronizasyon zamanını güncelle
    updateLastSyncTime();
    

// Özet dosyasını kaydet
$summaryFile = __DIR__ . '/logs/last_summary.json';
file_put_contents($summaryFile, json_encode($summaryStats, JSON_PRETTY_PRINT));
logToFile("Senkronizasyon özeti kaydedildi: $summaryFile");
    
    
} catch (Exception $e) {
    $errorMsg = "Senkronizasyon hatası: " . $e->getMessage();
    logToFile($errorMsg, 'ERROR');
} finally {
    // Geçici özet dosyasını temizle
    $tempSummaryFile = __DIR__ . '/logs/current_sync.json';
    if (file_exists($tempSummaryFile)) {
        unlink($tempSummaryFile);
    }
    // Her durumda kilidi kaldır
    unlockSync();
}

// Tamamlama mesajı
logToFile("Prestashop GET-EDIT senkronizasyon tamamlandı");