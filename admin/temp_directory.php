<?php
// temp_directory.php - Bu dosyayı ana dizine yerleştirerek çalıştırın
// PDF dosyalarının saklanacağı geçici dizini oluşturur

// Geçici dizin yolunu tanımla
$temp_dir = __DIR__ . '/temp';

// Dizin yoksa oluştur
if (!file_exists($temp_dir)) {
    mkdir($temp_dir, 0755, true);
    echo "Geçici dizin oluşturuldu: " . $temp_dir . PHP_EOL;
} else {
    echo "Geçici dizin zaten mevcut: " . $temp_dir . PHP_EOL;
}

// Dizin yazılabilir değilse, izinleri düzenle
if (!is_writable($temp_dir)) {
    chmod($temp_dir, 0755);
    echo "Dizin izinleri düzenlendi." . PHP_EOL;
}

// Eski geçici dosyaları temizle (opsiyonel)
// 1 günden eski dosyaları sil
$files = glob($temp_dir . '/*');
$now = time();

foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= 86400) { // 86400 = 24 saat
            unlink($file);
            echo "Eski dosya silindi: " . basename($file) . PHP_EOL;
        }
    }
}

echo "İşlem tamamlandı." . PHP_EOL;
?>