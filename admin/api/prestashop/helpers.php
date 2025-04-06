<?php
/**
 * Prestashop Entegrasyonu Yardımcı Fonksiyonlar
 */

/**
 * Veritabanı bağlantısı oluşturur
 * 
 * @return PDO Veritabanı bağlantısı
 */
function getDbConnection() {
    try {
        $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8', DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("SET NAMES utf8");
        return $db;
    } catch (PDOException $e) {
        logMessage("Veritabanı bağlantı hatası: " . $e->getMessage(), 'ERROR');
        die("Veritabanı bağlantı hatası: " . $e->getMessage());
    }
}

/**
 * Log dosyasına mesaj yazar
 * 
 * @param string $message Log mesajı
 * @param string $level Log seviyesi (INFO, ERROR, WARNING)
 * @return void
 */
function logMessage($message, $level = 'INFO') {
    $date = date('Y-m-d H:i:s');
    $logMessage = "[$date] [$level] $message" . PHP_EOL;
    
    // Log dosyası için klasör yoksa oluştur
    $logDir = dirname(PS_LOG_FILE);
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents(PS_LOG_FILE, $logMessage, FILE_APPEND);
    
    // Debug modunda ise ekrana da yazdır
    if (defined('PS_DEBUG') && PS_DEBUG && php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}

/**
 * Web ID'ye göre ürünleri gruplandırarak getirir
 * 
 * @param PDO $db Veritabanı bağlantısı
 * @param string $lastSyncTime Son senkronizasyon zamanı
 * @param int $limit Limit
 * @return array Ürün listesi
 */
function getProductsByWebId($db, $lastSyncTime = null, $limit = null) {
    $sql = "SELECT u.web_id, u.ad, u.satis_fiyati, SUM(u.stok_miktari) as toplam_stok 
            FROM urun_stok u 
            WHERE u.web_id IS NOT NULL AND u.web_id != '' AND u.durum = 'aktif'";
    
    $params = [];
    
    // Son senkronizasyon zamanından sonra değişen ürünleri seç
    if ($lastSyncTime) {
        $sql .= " AND (u.kayit_tarihi >= :last_sync OR EXISTS (
                    SELECT 1 FROM urun_fiyat_gecmisi ufg 
                    WHERE ufg.urun_id = u.id AND ufg.tarih >= :last_sync_price
                ))";
        $params[':last_sync'] = $lastSyncTime;
        $params[':last_sync_price'] = $lastSyncTime;
    }
    
    $sql .= " GROUP BY u.web_id";
    
    // Limit ekle
    if ($limit) {
        $sql .= " LIMIT :limit";
        $params[':limit'] = (int)$limit;
    }
    
    try {
        $stmt = $db->prepare($sql);
        
        // Limit parametresini doğru şekilde bağla
        if ($limit) {
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            unset($params[':limit']);
        }
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logMessage("Toplam " . count($products) . " web_id gruplu ürün bulundu.");
        return $products;
    } catch (PDOException $e) {
        logMessage("Ürün sorgulama hatası: " . $e->getMessage(), 'ERROR');
        return [];
    }
}

/**
 * Son senkronizasyon bilgisini günceller
 * 
 * @param string $timestamp Zaman damgası
 * @return bool Başarılı mı?
 */
function updateLastSyncTime($timestamp = null) {
    $timestamp = $timestamp ?: date('Y-m-d H:i:s');
    
    $file = __DIR__ . '/last_sync.txt';
    
    return file_put_contents($file, $timestamp) !== false;
}

/**
 * Son senkronizasyon zamanını alır
 * 
 * @return string|null Son senkronizasyon zamanı
 */
function getLastSyncTime() {
    $file = __DIR__ . '/last_sync.txt';
    
    if (file_exists($file)) {
        $timestamp = trim(file_get_contents($file));
        
        if ($timestamp) {
            return $timestamp;
        }
    }
    
    // Varsayılan değer
    return defined('LAST_SYNC_TIME') ? LAST_SYNC_TIME : null;
}

/**
 * Senkronizasyon kilit dosyasını kontrol eder
 * 
 * @return bool Kilitli mi?
 */
function isSyncLocked() {
    $lockFile = __DIR__ . '/sync.lock';
    
    if (file_exists($lockFile)) {
        $lockTime = filemtime($lockFile);
        $currentTime = time();
        
        // 1 saatten uzun süren kilitler, ölü kilit olarak kabul edilir
        if ($currentTime - $lockTime > 3600) {
            unlink($lockFile);
            return false;
        }
        
        return true;
    }
    
    return false;
}

/**
 * Senkronizasyon kilit dosyası oluşturur
 * 
 * @return bool Başarılı mı?
 */
function lockSync() {
    $lockFile = __DIR__ . '/sync.lock';
    return file_put_contents($lockFile, date('Y-m-d H:i:s')) !== false;
}

/**
 * Senkronizasyon kilit dosyasını kaldırır
 * 
 * @return bool Başarılı mı?
 */
function unlockSync() {
    $lockFile = __DIR__ . '/sync.lock';
    
    if (file_exists($lockFile)) {
        return unlink($lockFile);
    }
    
    return true;
}