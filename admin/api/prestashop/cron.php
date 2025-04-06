<?php
/**
 * Prestashop Entegrasyonu Cron Job
 * 
 * Bu dosya, cron job olarak çalıştırılarak otomatik senkronizasyon sağlar.
 * 
 * Örnek cron job ayarı (günde bir kez, gece yarısı çalıştırma):
 * 0 0 * * * /usr/bin/php /path/to/admin/api/prestashop/cron.php >> /path/to/cron.log 2>&1
 */

// CLI üzerinden çalıştırıldığını doğrula
if (php_sapi_name() !== 'cli') {
    echo "Bu script yalnızca komut satırından çalıştırılabilir.";
    exit(1);
}

// Tam dosya yollarını kullanarak dosyaları dahil et
$basePath = __DIR__;

// Config dosyasını dahil et
require_once $basePath . '/config.php';

// Sync.php'yi çalıştır
require_once $basePath . '/sync.php';