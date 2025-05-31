<?php
/**
 * Prestashop Entegrasyonu Web Arayüzü
 * 
 * Bu dosya, Prestashop entegrasyonunun manuel olarak web üzerinden çalıştırılmasını sağlar
 */

// Hata göstermeyi aktifleştir
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Session kontrolü
if (file_exists('../../session_manager.php')) {
    require_once '../../session_manager.php';
    // Session kontrolünü try-catch bloğunda yap
    try {
        if (function_exists('checkUserSession')) {
            checkUserSession(); 
        } else {
            // checkUserSession fonksiyonu yoksa basit bir session kontrolü yap
            secure_session_start();
            if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
                header("Location: ../../index.php");
                exit();
            }
        }
    } catch (Exception $e) {
        echo "Session hatası: " . $e->getMessage();
    }
} else {
    // Session kontrolü dosyası yoksa bir oturum başlat
    secure_session_start();
}

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

// Prestashop API sınıfını dahil et
require_once __DIR__ . '/prestashopAPI.php';

// Log dosyasının yolunu elle tanımla - config.php'deki değeri geçersiz kılar
$logFilePath = __DIR__ . '/logs/prestashop_sync_' . date('Y-m-d') . '.log';

// Log dosyasının bulunduğu klasörü kontrol et ve yoksa oluştur
$logDir = dirname($logFilePath);
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// Log dosyasını oluştur (eğer yoksa)
if (!file_exists($logFilePath)) {
    file_put_contents($logFilePath, "Log dosyası " . date('Y-m-d H:i:s') . " tarihinde oluşturuldu.\n");
    chmod($logFilePath, 0666); // Okuma/yazma izinleri
}

// Aksiyon kontrolü
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Sonuç mesajları
$messages = [];

switch ($action) {
    case 'match_barcodes':
        // Manuel barkod eşleştirme
        if (isSyncLocked()) {
            $messages[] = ['type' => 'warning', 'text' => 'Senkronizasyon zaten devam ediyor. Lütfen bekleyin.'];
        } else {
            // AJAX isteği mi kontrol et
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                // AJAX isteği için işlemi arka planda başlat
                include_once __DIR__ . '/barcode_match.php';
                exit;
            } else {
                // Normal sayfa isteği için mesaj göster, ancak sayfa yönlendirmesi yapma
                $messages[] = ['type' => 'success', 'text' => 'Barkod eşleştirme işlemi başlatılıyor... Lütfen bekleyin.'];
                
                // AJAX isteği olmadan doğrudan çalıştır
                echo '<script>
                    fetch("?action=match_barcodes", {
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    });
                </script>';
            }
        }
        break;
        
    case 'sync':
        // Manuel senkronizasyon
        if (isSyncLocked()) {
            $messages[] = ['type' => 'warning', 'text' => 'Senkronizasyon zaten devam ediyor. Lütfen bekleyin.'];
        } else {
            // AJAX isteği mi kontrol et
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                // AJAX isteği için işlemi arka planda başlat
                include_once __DIR__ . '/sync.php';
                exit;
            } else {
                // Normal sayfa isteği için mesaj göster, ancak sayfa yönlendirmesi yapma
                $messages[] = ['type' => 'success', 'text' => 'Senkronizasyon başlatılıyor... Lütfen bekleyin.'];
                
                // AJAX isteği olmadan doğrudan çalıştır
                echo '<script>
                    fetch("?action=sync", {
                        headers: {
                            "X-Requested-With": "XMLHttpRequest"
                        }
                    });
                </script>';
            }
        }
        break;
        
    case 'stopsync':
        // Senkronizasyonu durdur
        // PID dosyasının yolu
        $pidFile = __DIR__ . '/sync.pid';
        
        // Kilit dosyasının yolu
        $lockFile = __DIR__ . '/sync.lock';
        
        // Kilit dosyası var mı kontrol et
        if (file_exists($lockFile)) {
            // PID dosyası varsa, process ID'sini al
            if (file_exists($pidFile)) {
                $pid = trim(file_get_contents($pidFile));
                
                // Process çalışıyor mu kontrol et ve durdurmaya çalış
                if ($pid && function_exists('posix_kill') && posix_kill($pid, 0)) {
                    // Önce nazikçe durdurmaya çalış (SIGTERM)
                    posix_kill($pid, 15);
                    
                    // Biraz bekle
                    sleep(2);
                    
                    // Hala çalışıyorsa zorla durdur (SIGKILL)
                    if (posix_kill($pid, 0)) {
                        posix_kill($pid, 9);
                    }
                    
                    $messages[] = ['type' => 'success', 'text' => 'Senkronizasyon durduruldu.'];
                } else {
                    // Windows sistemlerde veya posix_kill olmayan durumlarda
                    $messages[] = ['type' => 'info', 'text' => 'İşlem durdurulamadı, kilit dosyası siliniyor.'];
                }
            }
            
            // Kilit dosyasını sil
            if (unlink($lockFile)) {
                $messages[] = ['type' => 'success', 'text' => 'Kilit dosyası silindi.'];
            } else {
                $messages[] = ['type' => 'warning', 'text' => 'Kilit dosyası silinemedi! Manuel olarak silinmesi gerekebilir.'];
            }
            
            // PID dosyasını da sil
            if (file_exists($pidFile) && unlink($pidFile)) {
                $messages[] = ['type' => 'success', 'text' => 'PID dosyası silindi.'];
            }
            
            // Yardımcı fonksiyonu çağır (eğer helpers.php içinde varsa)
            if (function_exists('unlockSync')) {
                unlockSync();
            }
        } else {
            $messages[] = ['type' => 'info', 'text' => 'Senkronizasyon zaten çalışmıyor.'];
        }
        break;
        
    case 'clearlog':
        // Log dosyasını temizle
        if (file_exists($logFilePath)) {
            file_put_contents($logFilePath, "");
            $messages[] = ['type' => 'success', 'text' => 'Log dosyası temizlendi.'];
        } else {
            $messages[] = ['type' => 'warning', 'text' => 'Log dosyası bulunamadı.'];
        }
        break;
        
    case 'clearcache':
        // Önbelleği temizle
        $cacheDir = __DIR__ . '/cache';
        if (file_exists($cacheDir)) {
            array_map('unlink', glob("$cacheDir/*"));
            $messages[] = ['type' => 'success', 'text' => 'Önbellek başarıyla temizlendi.'];
        } else {
            $messages[] = ['type' => 'warning', 'text' => 'Önbellek klasörü bulunamadı.'];
        }
        break;
        
    case 'test':
        // Hızlı API bağlantı testi sayfasına yönlendir
        header('Location: api_test_fast.php');
        exit;
        break;
        
    case 'fulltest':
        // Tam API bağlantı testi sayfasına yönlendir
        header('Location: api_test_full.php');
        exit;
        break;
        
    case 'getlogs':
        // AJAX için log içeriğini döndür
        header('Content-Type: application/json');
        
        $response = ['success' => false, 'data' => '', 'error' => ''];
        
        try {
            if (file_exists($logFilePath) && is_readable($logFilePath)) {
                // Satırları oku ve ters çevir (en yeni en üstte)
                $lines = file($logFilePath);
                
                if ($lines === false) {
                    throw new Exception("Log dosyası okunamadı. Dosya izinlerini kontrol edin.");
                }
                
                $lines = array_reverse($lines);
                
                // Son 100 satırı al
                $lines = array_slice($lines, 0, 100);
                $logContent = implode('', $lines);
                
                // HTML formatını güvenli hale getir
                $logContent = htmlspecialchars($logContent);
                
                // Log seviyelerini vurgula
                $logContent = preg_replace('/\[(INFO|ERROR|WARNING|DEBUG|SUCCESS|PARTIAL|FAILED)\]/', '<span class="log-$1">[$1]</span>', $logContent);
                
                // Satır sonlarını <br> etiketlerine dönüştür
                $logContent = nl2br($logContent);
                
                $response = [
                    'success' => true, 
                    'data' => $logContent,
                    'syncStatus' => isSyncLocked() ? 'running' : 'stopped'
                ];
            } else {
                $response['error'] = "Log dosyası bulunamadı veya okunamıyor.";
            }
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
        
        echo json_encode($response);
        exit;
        break;
}

// Senkronizasyon durumu
$syncStatus = isSyncLocked() ? 'Devam ediyor' : 'Beklemede';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İnciPos - Prestashop Entegrasyonu</title>
    <link rel="stylesheet" href="prestashop.css">
    <style>
        /* Log seviyelerine göre renklendirme */
        .log-INFO { color: #2196F3; font-weight: bold; }
        .log-ERROR { color: #F44336; font-weight: bold; }
        .log-WARNING { color: #FF9800; font-weight: bold; }
        .log-DEBUG { color: #9E9E9E; font-weight: bold; }
        .log-SUCCESS { color: #4CAF50; font-weight: bold; }
        .log-PARTIAL { color: #9C27B0; font-weight: bold; }
        .log-FAILED { color: #F44336; font-weight: bold; }
        
        /* Hata mesajları */
        .error-message {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            padding: 10px;
            margin: 10px 0;
            color: #b71c1c;
        }
    </style>
</head>
<body>
    <h1>Prestashop Entegrasyonu</h1>
    
    <div class="container">
        <div class="actions">
            <a href="?action=sync" class="btn">Senkronizasyonu Başlat</a>
            <a href="?action=match_barcodes" class="btn" style="background-color: #9C27B0;">Ürünleri Eşleştir</a>
            <a href="?action=stopsync" class="btn btn-stop" onclick="return confirm('Senkronizasyonu durdurmak istediğinizden emin misiniz?');">Senkronizasyonu Durdur</a>
            <a href="?action=test" class="btn btn-test">Hızlı API</a>
            <a href="?action=fulltest" class="btn btn-test-full">Tam API</a>
            <a href="?action=clearlog" class="btn btn-clear" onclick="return confirm('Log dosyasını temizlemek istediğinizden emin misiniz?');">Logu Temizle</a>
            <a href="?action=clearcache" class="btn btn-cache" onclick="return confirm('Önbelleği temizlemek istediğinizden emin misiniz?');">Önbelleği Temizle</a>
            <a href="https://pos.incikirtasiye.com/admin/" class="btn">Geri Dön</a>
        </div>
        
        <div class="alert">
            <span class="closebtn">&times;</span>  
            <strong>Dikkat!</strong> İşlemler sunucu kaynaklarını tüketmemek için yavaş çalışır, lütfen sabırlı olun ve sayfayı kapatmayın. Sayfayı kapatmanız veri kaybına yol açabilir.
        </div>
        
        <div class="alert warning">
            <span class="closebtn">&times;</span>  
            <strong>Uyarı!</strong> Tüm ürünleri "Tam Api Testi" ile önbelleğe aldıktan sonra Senkronizasyonu başlatın.
        </div>
        
        <!-- Mesajlar için div -->
        <div class="messages">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['type']; ?>">
                        <?php echo $message['text']; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="flex-row">
            <h2>Log Dosyası (Canlı Takip)</h2>
            <div class="auto-refresh">
                <label class="switch">
                    <input type="checkbox" id="auto-refresh" checked>
                    <span class="slider"></span>
                </label>
                <span><span class="live-indicator"></span> Canlı Takip</span>
            </div>
        </div>
        
        <div class="log-container">
            <div class="log-actions">
                <button id="refresh-logs" class="btn btn-log" style="padding: 4px 8px; font-size: 12px;">Yenile</button>
            </div>
            <div id="log-content"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sayfa yüklendiğinde logları al
        refreshLogs();
        
        // Auto-refresh toggle
        const autoRefreshCheckbox = document.getElementById('auto-refresh');
        let refreshInterval;
        
        function startAutoRefresh() {
            refreshInterval = setInterval(refreshLogs, 3000);
            document.querySelector('.live-indicator').classList.add('active');
        }
        
        function stopAutoRefresh() {
            clearInterval(refreshInterval);
            document.querySelector('.live-indicator').classList.remove('active');
        }
        
        autoRefreshCheckbox.addEventListener('change', function() {
            if (this.checked) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        
        // İlk başta auto-refresh aktifse başlat
        if (autoRefreshCheckbox.checked) {
            startAutoRefresh();
        }
        
        // Manuel yenileme butonu
        document.getElementById('refresh-logs').addEventListener('click', refreshLogs);
        
        // Uyarı kapatma düğmeleri
        const closeButtons = document.querySelectorAll('.closebtn');
        closeButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                this.parentElement.style.display = 'none';
            });
        });
        
        // AJAX ile logları yenileme
        function refreshLogs() {
            fetch('?action=getlogs')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('log-content').innerHTML = data.data;
                        
                        // Log container'ı en BAŞA kaydır (yeni loglar en üstte)
                        const logContainer = document.querySelector('.log-container');
                        logContainer.scrollTop = 0;
                    } else if (data.error) {
                        document.getElementById('log-content').innerHTML = 
                            '<div class="error-message">' + data.error + '</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('log-content').innerHTML = 
                        '<div class="error-message">Log alınamadı</div>';
                });
        }
    });
    </script>
</body>
</html>