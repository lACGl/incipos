<?php
/**
 * Prestashop Entegrasyonu Konfigürasyon Dosyası
 */

// Debug modu (geliştirme sırasında açık, üretime alınırken kapatılmalı)
define('PS_DEBUG', true);

// Prestashop API bilgileri
define('PS_API_KEY', 'YUUF26AGAILQB531JDUB94ZWMT9RFREE');
define('PS_SHOP_URL', 'https://incikirtasiye.com');

// Veritabanı bağlantı bilgileri (mevcut veritabanı bağlantısını kullanabilirsiniz)
define('DB_HOST', 'localhost');
define('DB_NAME', 'incikir2_pos');
define('DB_USER', 'incikir2_posadmin');
define('DB_PASS', 'vD3YjbzpPYsc');

// Log dosyası
define('PS_LOG_FILE', __DIR__ . '/logs/prestashop_sync.log');

// Senkronizasyon ayarları
define('SYNC_INTERVAL', '1 day'); // Günde bir kez
define('BATCH_SIZE', 50); // Her seferde işlenecek maksimum ürün sayısı
define('TIMEOUT', 30); // API isteği zaman aşımı (saniye)

// Web ID alanının adı
define('WEB_ID_FIELD', 'web_id');

// Otomatik olarak mevcut zamanı alma
define('LAST_SYNC_TIME', date('Y-m-d H:i:s', strtotime('-1 day'))); // Son 24 saatteki değişimler