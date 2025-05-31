<?php
/**
 * Prestashop Senkronizasyon Durdurma Script
 * 
 * Bu script çalışan senkronizasyon işlemini güvenli bir şekilde durdurur.
 */

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

// Web modunda çalışıyorsa başlık bilgilerini ayarla
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "Prestashop senkronizasyon durdurma işlemi başlatılıyor...\n";

// Kilit dosyasının yolu
$lockFile = __DIR__ . '/sync.lock';

// PID dosyasının yolu (varsa)
$pidFile = __DIR__ . '/sync.pid';

// Kilit dosyası var mı kontrol et
if (file_exists($lockFile)) {
    // PID dosyası varsa, process ID'sini al
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        
        // Process çalışıyor mu kontrol et ve durdurmaya çalış
        if ($pid && posix_kill($pid, 0)) {
            echo "PID: $pid olan senkronizasyon işlemi bulundu, durduruluyor...\n";
            
            // Önce nazikçe durdurmaya çalış (SIGTERM)
            posix_kill($pid, 15);
            
            // Biraz bekle
            sleep(2);
            
            // Hala çalışıyorsa zorla durdur (SIGKILL)
            if (posix_kill($pid, 0)) {
                echo "İşlem hala çalışıyor, zorla durduruluyor...\n";
                posix_kill($pid, 9);
            }
            
            echo "Senkronizasyon işlemi durduruldu.\n";
        } else {
            echo "PID: $pid olan işlem bulunamadı. Muhtemelen zaten sonlanmış.\n";
        }
    } else {
        echo "PID dosyası bulunamadı, ama kilit dosyası mevcut.\n";
    }
    
    // Kilit dosyasını sil
    if (unlink($lockFile)) {
        echo "Kilit dosyası silindi.\n";
    } else {
        echo "Kilit dosyası silinemedi! Manuel olarak silinmesi gerekebilir: $lockFile\n";
    }
    
    // PID dosyasını da sil
    if (file_exists($pidFile) && unlink($pidFile)) {
        echo "PID dosyası silindi.\n";
    }
    
    // Yardımcı fonksiyonu çağır (eğer helpers.php içinde varsa)
    if (function_exists('unlockSync')) {
        unlockSync();
        echo "unlockSync() fonksiyonu çağrıldı.\n";
    }
    
    echo "Senkronizasyon işlemi başarıyla durduruldu ve kilitler temizlendi.\n";
} else {
    echo "Aktif bir senkronizasyon işlemi bulunamadı. Kilit dosyası mevcut değil.\n";
}

// Son çalışan işlemi kontrol et
exec("ps aux | grep prestashop_sync | grep -v grep", $output);
if (!empty($output)) {
    echo "\nHala çalışan senkronizasyon işlemleri bulundu:\n";
    foreach ($output as $line) {
        echo $line . "\n";
    }
    echo "\nBu işlemleri manuel olarak durdurmanız gerekebilir.\n";
    echo "Örnek: kill -9 [PID]\n";
}

echo "\nİşlem tamamlandı.\n";