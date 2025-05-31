<?php
/**
 * Barkod Yöneticisi Eklentisi
 * 
 * İnciPOS sistemine entegre barkod oluşturma ve yazdırma eklentisi
 * 
 * @version 2.0
 * @author İnciPOS
 */
// Session yönetimi ve yetkisiz erişim kontrolü
require_once '../../session_manager.php';
// Session kontrolü
checkUserSession();
// Veritabanı bağlantısı
require_once '../../db_connection.php';
// Eklenti başlatma dosyasını dahil et
require_once __DIR__ . '/init.php';

// Sayfa işleyişi
$action = isset($_GET['action']) ? $_GET['action'] : 'main';

// AJAX istekleri için özel işlem
if ($action === 'ajax') {
    // AJAX istekleri için ayrı bir işlem - hiçbir çıktı oluşturmuyoruz
    require_once __DIR__ . '/includes/ajax_handler.php';
    exit; // AJAX işlemi tamamlandı, diğer kodları çalıştırmaya gerek yok
}

// Buradan sonraki kodlar sadece AJAX olmayan istekler için çalışır
// CSS ve JS dosyalarını kaydet
echo '<link rel="stylesheet" href="./assets/css/style.css">';
// Font Awesome (ikonlar için)
echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">';
// Kendi JS dosyamızı ekle
echo '<script src="./assets/js/barcode.js"></script>';

// İşlem yönlendirmesi
switch ($action) {
    case 'print_single':
        require_once __DIR__ . '/templates/print_single.php';
        break;
        
    case 'print_batch':
        require_once __DIR__ . '/templates/print_batch.php';
        break;
        
    case 'print_multiple':
        require_once __DIR__ . '/templates/print_multiple.php';
        break;
        
    case 'settings':
        // Yetki kontrolü - herkes erişebilir
        require_once __DIR__ . '/templates/settings.php';
        break;
        
    case 'print_test':
        require_once __DIR__ . '/templates/print_test.php';
        break;
        
    case 'main':
    default:
        require_once __DIR__ . '/templates/main.php';
        break;
}