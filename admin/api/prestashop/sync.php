<?php
require_once '../../session_manager.php'; // Otomatik eklendi
/**
 * Prestashop Senkronizasyon Script (GET-EDIT Yaklaşımı)
 * Optimize edilmiş, oturum korumalı versiyon
 */

// Mevcut oturumu koru
$currentSession = null;
if (session_status() === PHP_SESSION_ACTIVE) {
    // Mevcut oturum verilerini kaydet
    $currentSession = $_SESSION;
    // Oturumu kapat (ancak silme)
    session_write_close();
}

// Arka planda çalışacak şekilde yapılandır
if (php_sapi_name() !== 'cli') {
    // Tarayıcı için çıktı
    header('Content-Type: text/plain; charset=utf-8');
    echo "Prestashop GET-EDIT Senkronizasyon Başlatılıyor...\n\n";
    ob_implicit_flush(true);
    if (function_exists('ob_end_flush')) {
        @ob_end_flush();
    }
    // Tarayıcı bağlantısının kesilmesini önle
    ignore_user_abort(true);
}

// Hata ayıklama (üretimde kapatılabilir)
ini_set('display_errors', 0);
error_reporting(E_ERROR);

// Zaman aşımı limiti (saniye)
set_time_limit(0);

// Memory limit - RAM kullanımını optimize et
ini_set('memory_limit', '512M');

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

// Script başlangıç zamanı
$startTime = microtime(true);

// Log dosyasının yolunu belirle
$logFile = __DIR__ . '/logs/prestashop_sync_' . date('Y-m-d') . '.log';

// Log klasörünü oluştur
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Loglama için buffer tanımla
$logBuffer = [];
$logFlushCount = 20; // Bu sayıda log birikince toplu yazılacak

/**
 * Log mesajını buffer'a ekle
 */
function logToBuffer($message, $level = 'INFO') {
    global $logBuffer;
    
    // ERROR ve WARNING dışındaki logları azalt
    if ($level === 'INFO' && strpos($message, 'İlerleme') === false && strpos($message, 'başlatıldı') === false 
        && strpos($message, 'tamamlandı') === false && strpos($message, 'Batch') === false) {
        return;
    }
    
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date] [$level] $message";
    
    // Log mesajını diziye ekle
    $logBuffer[] = $logMessage;
    
    // Ekrana yazdır
    echo $logMessage . PHP_EOL;
}

/**
 * Buffer'daki logları dosyaya yaz
 */
function flushLogs() {
    global $logBuffer, $logFile;
    
    if (empty($logBuffer)) {
        return;
    }
    
    // Tüm logları tek seferde yaz
    file_put_contents($logFile, implode(PHP_EOL, $logBuffer) . PHP_EOL, FILE_APPEND);
    
    // Buffer'ı temizle
    $logBuffer = [];
}

// Script bitiminde kalan logları yazmayı sağla
register_shutdown_function('flushLogs');

// PID dosyasını oluştur
$pidFile = __DIR__ . '/sync.pid';
file_put_contents($pidFile, getmypid());

// Başlangıç logu
logToBuffer("Prestashop GET-EDIT senkronizasyon başlatıldı");

// Senkronizasyon zaten çalışıyorsa çık
if (isSyncLocked()) {
    logToBuffer("Senkronizasyon zaten devam ediyor. Çıkılıyor...", 'WARNING');
    flushLogs();
    
    // PID dosyasını temizle
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
    
    exit(1);
}

// Senkronizasyonu kilitle
lockSync();

try {
    // Veritabanı bağlantısı oluştur
    $db = getDbConnection();
    
    // Veritabanı bağlantı ayarları
    $db->setAttribute(PDO::ATTR_TIMEOUT, 120); // Timeout artırma
    
    // Batch işleme için değişkenler
    $batchSize = 200; // Her seferde işlenecek ürün sayısı
    $offset = 0;
    $totalProcessed = 0;
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    // API erişim bilgileri 
    $api_key = PS_API_KEY;
    $shop_url = rtrim(PS_SHOP_URL, '/');
    
    // Toplam ürün sayısını al
    $countSql = "SELECT COUNT(*) FROM urun_stok WHERE web_id IS NOT NULL AND web_id != '' AND durum = 'aktif'";
    $totalCount = $db->query($countSql)->fetchColumn();
    
    logToBuffer("Toplam senkronize edilecek ürün: $totalCount");
    flushLogs();
    
    // Batch döngüsü
    do {
        // WebPOS'tan batch olarak ürünleri al - KDV oranını da direkt alalım
        $sql = "SELECT us.web_id, 
            us.satis_fiyati,
            us.kdv_orani,
            IFNULL((SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod), 0) +
            IFNULL((SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id), 0) AS toplam_stok
            FROM urun_stok us
            WHERE us.web_id IS NOT NULL AND us.web_id != '' AND us.durum = 'aktif'
            GROUP BY us.web_id
            LIMIT $offset, $batchSize";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $batchCount = count($products);
        
        // RAM'i temizle
        $stmt = null;
        
        if ($batchCount > 0) {
            logToBuffer("Batch işlemi başlatılıyor: $offset-" . ($offset + $batchCount) . " / $totalCount");
            
            // Her bir ürün için senkronizasyon işlemi yap
            foreach ($products as $index => $product) {
                $webId = trim($product['web_id']);
                $price = $product['satis_fiyati'];
                $stock = intval($product['toplam_stok']);
                $kdvOrani = $product['kdv_orani'];
                
                $totalProcessed++;
                
                // İlerleme göstergesi her 10 üründe bir
                if ($totalProcessed % 10 == 0) {
                    logToBuffer("İlerleme: $totalProcessed / $totalCount ürün işlendi");
                    flushLogs();
                    
                    // Belleği temizle
                    gc_collect_cycles();
                }
                
                // Boş/Geçersiz değerleri kontrol et
                if (empty($webId) || $price === null || $stock === null || !is_numeric($webId)) {
                    $skippedCount++;
                    continue;
                }
                
                // Prestashop ürün ID'si olarak web_id kullan
                $prestashopId = intval($webId);
                
                if ($prestashopId <= 0) {
                    $skippedCount++;
                    continue;
                }
                
                // Fiyat ve stok değerlerini düzelt
                $price = floatval($price);
                $stock = intval($stock);
                
                // Ürün güncelleme
                $priceSuccess = false;
                $stockSuccess = false;
                
                // ADIM 1: Fiyat güncelleme
                try {
                    // cURL ayarlarını yap
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $shop_url . '/api/products/' . $prestashopId);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Bağlantı zaman aşımı
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // İşlem zaman aşımı
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    
                    curl_close($ch);
                    
                    if ($http_code == 200 && !empty($response)) {
                        // RAM kullanımını azaltmak için XML manipülasyonunu optimize edelim
                        $xml = simplexml_load_string($response);
                        
                        if ($xml) {
                            // Mevcut fiyat kontrolü - değişmemişse güncelleme yapma
                            $currentPrice = (string)$xml->product->price;
                            if (abs(floatval($currentPrice) - $price) < 0.01) {
                                $priceSuccess = true;
                            } else {
                                // Vergili fiyattan vergisiz fiyata çevirme
                                if ($kdvOrani) {
                                    $kdvKatsayisi = (100 + floatval($kdvOrani)) / 100;
                                    $vergisizFiyat = $price / $kdvKatsayisi;
                                    $vergisizFiyat = round($vergisizFiyat, 6);
                                    
                                    // XML manipülasyonunu daha hafif hale getirme
                                    $xml->product->price = $vergisizFiyat;
                                } else {
                                    $xml->product->price = $price;
                                }
                                
                                // Gereksiz alanları kaldır
                                foreach (['position', 'position_in_category', 'manufacturer_name', 
                                          'quantity', 'id_default_image', 'id_default_combination', 
                                          'associations', 'indexed'] as $field) {
                                    if (isset($xml->product->$field)) {
                                        unset($xml->product->$field);
                                    }
                                }
                                
                                // Güncellenmiş XML
                                $updatedXml = $xml->asXML();
                                
                                // XML'i temizle - bellek tüketimini azalt
                                $xml = null;
                                
                                // Güncelleme isteği 
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
                                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                                
                                $response = curl_exec($ch);
                                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                
                                curl_close($ch);
                                
                                if ($http_code >= 200 && $http_code < 300) {
                                    $priceSuccess = true;
                                } else {
                                    logToBuffer("Fiyat güncelleme hatası: PS ID=$prestashopId, HTTP=$http_code", 'ERROR');
                                }
                                
                                // Temizlik
                                $updatedXml = null;
                            }
                        } else {
                            logToBuffer("Ürün XML ayrıştırma hatası: PS ID=$prestashopId", 'ERROR');
                        }
                    } else {
                        logToBuffer("Ürün bilgileri alınamadı: PS ID=$prestashopId, HTTP=$http_code", 'ERROR');
                    }
                } catch (Exception $e) {
                    logToBuffer("Fiyat güncelleme hatası: " . $e->getMessage(), 'ERROR');
                }
                
                // Belleği temizle
                $response = null;
                
                // ADIM 2: Stok güncelleme - önce stok bilgilerini al
                try {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $shop_url . '/api/stock_availables?filter[id_product]=' . $prestashopId . '&display=full');
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    
                    curl_close($ch);
                    
                    if ($http_code == 200 && !empty($response)) {
                        $xml = simplexml_load_string($response);
                        
                        if ($xml) {
                            $stockInfos = $xml->xpath('//stock_available');
                            if (!empty($stockInfos)) {
                                $stockId = (string)$stockInfos[0]->id;
                                $currentStock = (string)$stockInfos[0]->quantity;
                                
                                // Stok değişmemiş mi kontrol et
                                if ($currentStock == $stock) {
                                    $stockSuccess = true;
                                } else {
                                    // Stok detaylarını al ve güncelle
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $shop_url . '/api/stock_availables/' . $stockId);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                                    curl_setopt($ch, CURLOPT_USERPWD, $api_key . ':');
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                                    
                                    $response = curl_exec($ch);
                                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    
                                    curl_close($ch);
                                    
                                    if ($http_code == 200 && !empty($response)) {
                                        $stockXml = simplexml_load_string($response);
                                        
                                        if ($stockXml) {
                                            // Sadece gerekli alanları değiştir
                                            $stockXml->stock_available->quantity = $stock;
                                            $stockXml->stock_available->depends_on_stock = '0';
                                            
                                            if (!isset($stockXml->stock_available->out_of_stock)) {
                                                $stockXml->stock_available->addChild('out_of_stock', '1');
                                            }
                                            
                                            $updatedStockXml = $stockXml->asXML();
                                            $stockXml = null;
                                            
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
                                            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                                            
                                            $response = curl_exec($ch);
                                            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                            
                                            curl_close($ch);
                                            
                                            if ($http_code >= 200 && $http_code < 300) {
                                                $stockSuccess = true;
                                            } else {
                                                logToBuffer("Stok güncelleme hatası: PS ID=$prestashopId, HTTP=$http_code", 'ERROR');
                                            }
                                            
                                            $updatedStockXml = null;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    logToBuffer("Stok güncelleme hatası: " . $e->getMessage(), 'ERROR');
                }
                
                // Belleği temizle
                $response = null;
                
                // İşlem sonucunu kaydet
                if ($priceSuccess && $stockSuccess) {
                    $successCount++;
                } elseif ($priceSuccess || $stockSuccess) {
                    $successCount++;
                    logToBuffer("Güncelleme kısmen başarılı: PS ID=$prestashopId", 'WARNING');
                } else {
                    $errorCount++;
                    logToBuffer("Güncelleme başarısız: PS ID=$prestashopId", 'ERROR');
                }
                
                // Her 5 üründe bir buffer'ı temizle
                if (count($logBuffer) >= $logFlushCount) {
                    flushLogs();
                }
                
                // Her 20 üründe bir belleği temizle
                if ($totalProcessed % 20 == 0) {
                    gc_collect_cycles();
                }
            }
            
            // Her batch sonunda buffer'ı temizle
            flushLogs();
            
            // Batch aralarında kısa bir bekleme
            usleep(500000); // 0.5 saniye
        }
        
        // Bir sonraki batch için offset'i güncelle
        $offset += $batchSize;
        
        // Ürünler arasında belleği boşalt
        $products = null;
        gc_collect_cycles();
        
    } while ($batchCount > 0);
    
    // Senkronizasyon sonucunu logla
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    $summary = "Senkronizasyon tamamlandı. Başarılı: $successCount, Başarısız: $errorCount, Atlanan: $skippedCount, Toplam Süre: $executionTime saniye";
    logToBuffer($summary);
    flushLogs();
    
    // Son senkronizasyon zamanını güncelle
    updateLastSyncTime();
    
} catch (Exception $e) {
    $errorMsg = "Senkronizasyon hatası: " . $e->getMessage();
    logToBuffer($errorMsg, 'ERROR');
    flushLogs();
} finally {
    // PID dosyasını temizle
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
    
    // Her durumda kilidi kaldır
    unlockSync();
    
    // Eğer daha önce mevcut bir oturum vardıysa, oturumu yeniden başlat
    if ($currentSession !== null) {
        secure_session_start();
        // Oturum verilerini geri yükle
        $_SESSION = $currentSession;
    }
}

// Tamamlama mesajı
logToBuffer("Prestashop GET-EDIT senkronizasyon tamamlandı");
flushLogs();