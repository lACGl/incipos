<?php
/**
 * Barkod Yöneticisi Başlatma Dosyası
 */

// Session ve veritabanı kontrolü - doğrudan erişim için
if (!isset($conn)) {
    // Session yönetimi ve yetkisiz erişim kontrolü
    require_once '../../session_manager.php';
    // Session kontrolü
    checkUserSession();
    // Veritabanı bağlantısı
    require_once '../../db_connection.php';
}

// Sabit yolları tanımla
define('BARCODE_MANAGER_DIR', __DIR__);
define('BARCODE_MANAGER_URL', './');
define('BARCODE_MANAGER_ASSETS', BARCODE_MANAGER_URL . 'assets');

// Fonksiyon ve yardımcı dosyalarını dahil et
require_once BARCODE_MANAGER_DIR . '/includes/functions.php';

// Composer otomatik yükleme (varsa)
$composer_autoload = BARCODE_MANAGER_DIR . '/vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Eklenti kurulum kontrolü
if (!barcode_check_installed()) {
    barcode_install();
}