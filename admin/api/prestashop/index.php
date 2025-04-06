<?php
/**
 * Prestashop Entegrasyonu Web Arayüzü
 * 
 * Bu dosya, Prestashop entegrasyonunun manuel olarak web üzerinden çalıştırılmasını sağlar
 */

// Config dosyasını dahil et
require_once __DIR__ . '/config.php';

// Yardımcı fonksiyonları dahil et
require_once __DIR__ . '/helpers.php';

// Prestashop API sınıfını dahil et
require_once __DIR__ . '/prestashopAPI.php';

// Aksiyon kontrolü
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Sonuç mesajları
$messages = [];

switch ($action) {
    case 'sync':
    // Manuel senkronizasyon
    if (isSyncLocked()) {
        $messages[] = ['type' => 'warning', 'text' => 'Senkronizasyon zaten devam ediyor. Lütfen bekleyin.'];
    } else {
        // exec() olmadan senkronizasyon yapabiliriz
        $messages[] = ['type' => 'success', 'text' => 'Senkronizasyon başlatılıyor... Lütfen bekleyin.'];
        // Redirect to the sync script
        header('Location: sync.php');
        exit;
    }
    break;
        
    case 'stopsync':
        // Senkronizasyonu durdur
        if (isSyncLocked()) {
            // Kilit dosyasını kaldır
            unlockSync();
            $messages[] = ['type' => 'success', 'text' => 'Senkronizasyon durduruldu.'];
        } else {
            $messages[] = ['type' => 'info', 'text' => 'Senkronizasyon zaten çalışmıyor.'];
        }
        break;
        
    case 'clearlog':
        // Log dosyasını temizle
        if (file_exists(PS_LOG_FILE)) {
            file_put_contents(PS_LOG_FILE, "");
            $messages[] = ['type' => 'success', 'text' => 'Log dosyası temizlendi.'];
        } else {
            $messages[] = ['type' => 'warning', 'text' => 'Log dosyası bulunamadı.'];
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
        
    case 'viewlog':
        // Log dosyasını görüntüle
        $logContent = '';
        if (file_exists(PS_LOG_FILE)) {
            // Tüm satırları oku
            $lines = file(PS_LOG_FILE);
            
            // Satırları ters çevir (en yeni en üstte)
            $lines = array_reverse($lines);
            
            // Son 200 satırı göster
            $lines = array_slice($lines, 0, 200);
            
            $logContent = implode('', $lines);
            $logContent = nl2br(htmlspecialchars($logContent));
        } else {
            $messages[] = ['type' => 'warning', 'text' => 'Log dosyası bulunamadı.'];
        }
        break;
        
    case 'getlogs':
        // AJAX için log içeriğini döndür
        header('Content-Type: application/json');
        $response = ['success' => false, 'data' => ''];
        
        if (file_exists(PS_LOG_FILE)) {
            // Satırları oku ve ters çevir (en yeni en üstte)
            $lines = file(PS_LOG_FILE);
            $lines = array_reverse($lines);
            
            // Son 100 satırı al (daha fazla gösteriyoruz, özetler için)
            $lines = array_slice($lines, 0, 100);
            $logContent = implode('', $lines);
            
            // HTML formatını güvenli hale getir
            $logContent = htmlspecialchars($logContent);
            
            // [SUCCESS], [PARTIAL], [FAILED] gibi etiketleri korumak için
            // log seviyelerini önce vurgula
            $logContent = preg_replace('/\[(INFO|ERROR|WARNING|DEBUG|SUCCESS|PARTIAL|FAILED)\]/', '[$1]', $logContent);
            
            // Satır sonlarını <br> etiketlerine dönüştür
            $logContent = nl2br($logContent);
            
            $response = [
                'success' => true, 
                'data' => $logContent,
                'syncStatus' => isSyncLocked() ? 'running' : 'stopped'
            ];
        }
        
        echo json_encode($response);
        exit;
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
        
    // Diğer case'lerden sonra, switch bloğu kapanmadan önce
case 'get_sync_status':
    // Senkronizasyon durumunu AJAX isteği için döndür
    header('Content-Type: application/json');
    
    // Son özet dosyasını kontrol et
    $summaryFile = __DIR__ . '/logs/last_summary.json';
    $isRunning = isSyncLocked();
    $summaryHtml = '';
    
    if (file_exists($summaryFile)) {
        $stats = json_decode(file_get_contents($summaryFile), true);
        
        // Devam eden senkronizasyon için geçici özet dosyasını da kontrol et
        $tempSummaryFile = __DIR__ . '/logs/current_sync.json';
        if ($isRunning && file_exists($tempSummaryFile)) {
            // Devam eden senkronizasyonun durumunu göster
            $currentStats = json_decode(file_get_contents($tempSummaryFile), true);
            if ($currentStats) {
                $stats = $currentStats;
            }
        }
        
        // Özet HTML'ini oluştur
        ob_start();
?>
<h3>Son Senkronizasyon Özeti</h3>
<?php
$total = $stats['success_count'] + $stats['partial_count'] + $stats['error_count'] + $stats['skipped_count'];
$timestamp = isset($stats['timestamp']) ? $stats['timestamp'] : 'Bilinmiyor';
$isCurrentSync = $isRunning && isset($stats['processed_count']) && isset($stats['total_count']);
?>

<p><strong>Senkronizasyon Zamanı:</strong> <?php echo $timestamp; ?></p>
<p><strong>Toplam İşlenen:</strong> <?php echo $total; ?> ürün
<?php if ($isCurrentSync): ?>
   (<?php echo $stats['processed_count']; ?>/<?php echo $stats['total_count']; ?> işleniyor...)
<?php endif; ?>
</p>

<?php
// Başarı yüzdesi
$successPercent = ($total > 0) ? round(($stats['success_count'] / $total) * 100, 1) : 0;
?>
<div class="progress-bar-container">
    <div class="progress-bar" style="width:<?php echo $successPercent; ?>%"><?php echo $successPercent; ?>%</div>
</div>

<div class="stats-grid">
    <div class="stat-box stat-success">
        <h4>Başarılı</h4>
        <div class="stat-number"><?php echo $stats['success_count']; ?></div>
        <div class="stat-percent"><?php echo ($total > 0 ? round(($stats['success_count'] / $total) * 100, 1) : 0); ?>%</div>
    </div>
    
    <div class="stat-box stat-partial">
        <h4>Kısmen Başarılı</h4>
        <div class="stat-number"><?php echo $stats['partial_count']; ?></div>
        <div class="stat-percent"><?php echo ($total > 0 ? round(($stats['partial_count'] / $total) * 100, 1) : 0); ?>%</div>
    </div>
    
    <div class="stat-box stat-failed">
        <h4>Başarısız</h4>
        <div class="stat-number"><?php echo $stats['error_count']; ?></div>
        <div class="stat-percent"><?php echo ($total > 0 ? round(($stats['error_count'] / $total) * 100, 1) : 0); ?>%</div>
    </div>
    
    <div class="stat-box stat-skipped">
        <h4>Atlanan</h4>
        <div class="stat-number"><?php echo $stats['skipped_count']; ?></div>
        <div class="stat-percent"><?php echo ($total > 0 ? round(($stats['skipped_count'] / $total) * 100, 1) : 0); ?>%</div>
    </div>
</div>

<?php if (isset($stats['execution_time_so_far'])): ?>
<p><strong>Şu Ana Kadar Geçen Süre:</strong> <?php echo $stats['execution_time_so_far']; ?> saniye</p>
<?php elseif (isset($stats['execution_time'])): ?>
<p><strong>Toplam Süre:</strong> <?php echo $stats['execution_time']; ?> saniye</p>
<?php endif; ?>

<?php
        $summaryHtml = ob_get_clean();
    }
    
    echo json_encode([
        'success' => true,
        'is_running' => $isRunning,
        'summaryHtml' => $summaryHtml
    ]);
    exit;
    break;
}

// Son senkronizasyon zamanı
$lastSyncTime = getLastSyncTime();

// Senkronizasyon durumu
$syncStatus = isSyncLocked() ? 'Devam ediyor' : 'Beklemede';

// Toplam ürün sayısı
$totalProducts = 'Bilinmiyor';
$cacheFile = __DIR__ . '/cache/product_count.txt';
if (file_exists($cacheFile)) {
    $totalProducts = file_get_contents($cacheFile);
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İnciPos - Prestashop Entegrasyonu</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }
    h1, h2 {
        color: #333;
    }
    .container {
        margin-bottom: 30px;
    }
    .actions {
        margin: 20px 0;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .btn {
        display: inline-block;
        padding: 8px 16px;
        background-color: #4CAF50;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        cursor: pointer;
        border: none;
        font-size: 14px;
        font-family: inherit;
    }
    .btn-stop {
        background-color: #f44336;
    }
    .btn-test {
        background-color: #2196F3;
    }
    .btn-test-full {
        background-color: #3F51B5;
    }
    .btn-log {
        background-color: #607D8B;
    }
    .btn-clear {
        background-color: #FF9800;
    }
    .btn-cache {
        background-color: #FF5722;
    }
    .status {
        margin: 20px 0;
        padding: 15px;
        border-radius: 4px;
        background-color: #f9f9f9;
        border-left: 5px solid #ccc;
    }
    .status.running {
        border-left-color: #4CAF50;
        background-color: #f1f8e9;
    }
    .status.stopped {
        border-left-color: #FF5722;
    }
    .message {
        padding: 10px 15px;
        margin: 10px 0;
        border-radius: 4px;
    }
    .message.success {
        background-color: #dff0d8;
        border-left: 5px solid #3c763d;
    }
    .message.info {
        background-color: #d9edf7;
        border-left: 5px solid #31708f;
    }
    .message.warning {
        background-color: #fcf8e3;
        border-left: 5px solid #8a6d3b;
    }
    .message.error {
        background-color: #f2dede;
        border-left: 5px solid #a94442;
    }
    .log-container {
        background-color: #f5f5f5;
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
        height: 500px;
        overflow-y: auto;
        font-family: monospace;
        font-size: 14px;
        position: relative;
    }
    #log-content {
        white-space: pre-wrap;
    }
    .log-actions {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        gap: 5px;
    }
    .live-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background-color: #4CAF50;
        margin-right: 5px;
        animation: blink 1s infinite;
    }
    @keyframes blink {
        0% { opacity: 0; }
        50% { opacity: 1; }
        100% { opacity: 0; }
    }
    .auto-refresh {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
        margin-right: 10px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 24px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider {
        background-color: #4CAF50;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    .flex-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    /* Log renklendirme stilleri */
    .log-line {
        padding: 2px 5px;
        border-radius: 3px;
        margin-bottom: 2px;
    }
    .log-line-INFO {
        background-color: transparent;
    }
    .log-line-ERROR {
        background-color: #ffebee;
        color: #d32f2f;
    }
    .log-line-WARNING {
        background-color: #fff8e1;
        color: #ff8f00;
    }
    .log-line-DEBUG {
        background-color: #e1f5fe;
        color: #0288d1;
    }
    .log-line-SUCCESS {
        background-color: #e8f5e9;
        color: #388e3c;
        font-weight: bold;
    }
    .log-line-PARTIAL {
        background-color: #fff3e0;
        color: #ef6c00;
        font-weight: bold;
    }
    .log-line-FAILED {
        background-color: #ffebee;
        color: #d32f2f;
        font-weight: bold;
    }
    
    /* İstatistik kutuları */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin: 15px 0;
    }
    .stat-box {
        padding: 15px;
        border-radius: 5px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .stat-box h4 {
        margin: 0 0 8px 0;
    }
    .stat-number {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .stat-percent {
        font-size: 14px;
        opacity: 0.8;
    }
    .stat-success {
        background-color: #e8f5e9;
        color: #388e3c;
    }
    .stat-partial {
        background-color: #fff3e0;
        color: #ef6c00;
    }
    .stat-failed {
        background-color: #ffebee;
        color: #d32f2f;
    }
    .stat-skipped {
        background-color: #f5f5f5;
        color: #757575;
    }
    
    /* İlerleme Çubuğu */
    .progress-bar-container {
        width: 100%;
        height: 20px;
        background-color: #f5f5f5;
        border-radius: 10px;
        margin: 10px 0;
        overflow: hidden;
        box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    }
    .progress-bar {
        height: 100%;
        background-color: #4CAF50;
        color: white;
        text-align: center;
        line-height: 20px;
        font-size: 12px;
        font-weight: bold;
        border-radius: 10px;
        transition: width 0.5s ease-in-out;
    }
    
    /* Tablo stilleri */
    .table-container {
        overflow-x: auto;
        max-height: 300px;
        margin-top: 10px;
    }
    .result-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .result-table th, .result-table td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    .result-table th {
        background-color: #f5f5f5;
        font-weight: bold;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .result-table tbody tr:hover {
        background-color: #f9f9f9;
    }
    .success-table th {
        background-color: #e8f5e9;
        color: #2e7d32;
    }
    .partial-table th {
        background-color: #fff3e0;
        color: #e65100;
    }
    .failed-table th {
        background-color: #ffebee;
        color: #c62828;
    }
    
    /* Özet istatistik stilleri */
    .stats-summary {
        margin-top: 10px;
    }
    
    /* Açılır/kapanır bölümler için ek stiller */
    .collapsible-section {
        margin: 15px 0;
        border-radius: 5px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .collapsible-section h4 {
        margin: 0;
        padding: 12px 15px;
        cursor: pointer;
        background-color: #f9f9f9;
        border-radius: 5px 5px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background-color 0.3s;
    }
    .collapsible-section h4:hover {
        background-color: #f0f0f0;
    }
    .collapsible-content {
        border-top: 1px solid #eee;
        padding: 15px;
        background-color: white;
        max-height: 300px;
        overflow-y: auto;
    }
    .toggle-icon {
        font-weight: bold;
        margin-left: 10px;
    }
    .error-list li {
        color: #d32f2f;
        margin-bottom: 5px;
    }
    .partial-list li {
        color: #ef6c00;
        margin-bottom: 5px;
    }
</style>
    <script>
        // Sayfa yüklendiğinde çalışacak
        document.addEventListener('DOMContentLoaded', function() {
            // Canlı log takip değişkenleri
            let logUpdateInterval;
            let autoRefresh = true;
            
            // Log güncelleme fonksiyonu
            function updateLogs() {
                fetch('?action=getlogs')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Formatlanmış log içeriğini oluştur
                            let formattedContent = '';
                            const logLines = data.data.split('<br />');
                            
                            logLines.forEach(line => {
                                // Log seviyesini bul: [INFO], [ERROR], [WARNING], vb.
                                let logClass = 'log-line-INFO'; // varsayılan
                                
                                const levelMatch = line.match(/\[(INFO|ERROR|WARNING|DEBUG|SUCCESS|PARTIAL|FAILED)\]/);
                                if (levelMatch) {
                                    logClass = 'log-line-' + levelMatch[1];
                                }
                                
                                // Her satırı bir <div> içine al ve uygun sınıfı ekle
                                formattedContent += `<div class="log-line ${logClass}">${line}</div>`;
                            });
                            
                            document.getElementById('log-content').innerHTML = formattedContent;
                            
                            // Log konteynırının en üste scroll yap (en yeni kayıtlar üstte)
                            const logContainer = document.querySelector('.log-container');
                            logContainer.scrollTop = 0;
                            
                            // Senkronizasyon durumunu güncelle
                            const statusElement = document.getElementById('sync-status');
                            if (data.syncStatus === 'running') {
                                statusElement.textContent = 'Devam ediyor';
                                document.querySelector('.status').classList.add('running');
                                document.querySelector('.status').classList.remove('stopped');
                            } else {
                                statusElement.textContent = 'Beklemede';
                                document.querySelector('.status').classList.add('stopped');
                                document.querySelector('.status').classList.remove('running');
                            }
                        }
                    })
                    .catch(error => console.error('Log güncellenemedi:', error));
            }
            
            // Otomatik güncellemeyi başlat/durdur
            function toggleAutoRefresh() {
                const checkbox = document.getElementById('auto-refresh');
                autoRefresh = checkbox.checked;
                
                if (autoRefresh) {
                    // 2 saniyede bir güncelle
                    logUpdateInterval = setInterval(updateLogs, 2000);
                    document.querySelector('.live-indicator').style.display = 'inline-block';
                } else {
                    clearInterval(logUpdateInterval);
                    document.querySelector('.live-indicator').style.display = 'none';
                }
            }
            
            // Otomatik güncelleştirme ayarı
            const checkbox = document.getElementById('auto-refresh');
            if (checkbox) {
                checkbox.addEventListener('change', toggleAutoRefresh);
                
                // Sayfa yüklendiğinde otomatik güncellemeyi başlat
                if (checkbox.checked) {
                    toggleAutoRefresh();
                }
            }
            
            // Senkronizasyon sürerken durumu gösteren özet fonksiyonu
            function updateSyncSummary() {
                fetch('?action=get_sync_status')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Özet bölümünü güncelle
                            const statusSection = document.querySelector('.status-section');
                            if (statusSection && data.summaryHtml) {
                                statusSection.innerHTML = data.summaryHtml;
                            }
                            
                            // Duruma göre işlemlere devam et
                            if (data.is_running) {
                                // Senkronizasyon hala çalışıyorsa, birkaç saniye sonra tekrar kontrol et
                                setTimeout(updateSyncSummary, 2000);
                            }
                        }
                    })
                    .catch(error => console.error('Özet güncellenemedi:', error));
            }
            
            // updateSyncSummary fonksiyonunu global alanda tanımla
            window.updateSyncSummary = updateSyncSummary;
            
            // Manuel güncelleştirme butonu
            const refreshButton = document.getElementById('refresh-logs');
            if (refreshButton) {
                refreshButton.addEventListener('click', updateLogs);
            }
            
            // İlk yüklemede güncelle
            updateLogs();
            
            // updateLogs fonksiyonunu global alanda tanımla
            window.updateLogs = updateLogs;
            
            // Senkronizasyonu başlat butonunu bul
            const syncButton = document.querySelector('a[href="?action=sync"]');
            
            if (syncButton) {
                // Butona tıklanınca AJAX isteği yap
                syncButton.addEventListener('click', function(event) {
                    event.preventDefault(); // Sayfa yönlendirmesini engelle
                    
                    // Senkronizasyon başladı mesajını göster
                    const message = document.createElement('div');
                    message.className = 'message success';
                    message.textContent = 'Senkronizasyon başlatılıyor... Lütfen bekleyin.';
                    
                    // Mesajları gösterdiğiniz div'i bul ve mesajı ekle
                    const messagesDiv = document.querySelector('.messages') || document.createElement('div');
                    if (!document.querySelector('.messages')) {
                        messagesDiv.className = 'messages';
                        document.querySelector('.container').appendChild(messagesDiv);
                    }
                    messagesDiv.innerHTML = ''; // Eski mesajları temizle
                    messagesDiv.appendChild(message);
                    
                    // AJAX isteği gönder
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', 'sync.php', true);
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4) {
                            // Senkronizasyon başladı, özet durumunu güncelle
                            updateSyncSummary();
                            
                            // Auto-refresh'i aktif et ve logları güncelle
                            if (document.getElementById('auto-refresh')) {
                                document.getElementById('auto-refresh').checked = true;
                                const event = new Event('change');
                                document.getElementById('auto-refresh').dispatchEvent(event);
                            }
                            
                            // Log içeriğini hemen güncelle
                            if (window.updateLogs) {
                                window.updateLogs();
                            }
                            
                            // Tamamlandı mesajı
                            message.textContent = 'Senkronizasyon başlatıldı. Güncellemeler otomatik gösterilecek.';
                            
                            // 5 saniye sonra mesajı kaldır
                            setTimeout(function() {
                                message.style.opacity = '0';
                                setTimeout(function() {
                                    message.remove();
                                }, 500);
                            }, 5000);
                        }
                    };
                    xhr.send();
                });
            }
        });
        
        // Açılır/kapanır bölümleri kontrol etmek için
        function toggleCollapsible(element) {
            const content = element.nextElementSibling;
            const icon = element.querySelector('.toggle-icon');
            
            if (content.style.display === "none") {
                content.style.display = "block";
                icon.textContent = "-";
            } else {
                content.style.display = "none";
                icon.textContent = "+";
            }
        }
    </script>
</head>
<body>
    <h1>Prestashop Entegrasyonu</h1>
    
    <div class="container">
        <div class="actions">
            <a href="?action=sync" class="btn">Senkronizasyonu Başlat</a>
            <a href="?action=stopsync" class="btn btn-stop" onclick="return confirm('Senkronizasyonu durdurmak istediğinizden emin misiniz?');">Senkronizasyonu Durdur</a>
            <a href="?action=test" class="btn btn-test">Hızlı API Testi</a>
            <a href="?action=fulltest" class="btn btn-test-full">Tam API Testi</a>
            <a href="?action=clearlog" class="btn btn-clear" onclick="return confirm('Log dosyasını temizlemek istediğinizden emin misiniz?');">Log Dosyasını Temizle</a>
            <a href="?action=clearcache" class="btn btn-cache" onclick="return confirm('Önbelleği temizlemek istediğinizden emin misiniz?');">Önbelleği Temizle</a>
        </div>
        
        <div class="status <?php echo isSyncLocked() ? 'running' : 'stopped'; ?>">
            <h3>Durum Bilgileri</h3>
            <p><strong>Son Senkronizasyon:</strong> <?php echo $lastSyncTime ?: 'Henüz senkronizasyon yapılmadı'; ?></p>
            <p><strong>Durum:</strong> <span id="sync-status"><?php echo $syncStatus; ?></span></p>
            <p><strong>Prestashop Ürün Sayısı:</strong> <?php echo $totalProducts; ?></p>
        </div>
        
        <div class="status-section">
            <h3>Son Senkronizasyon Özeti</h3>
            <?php
            // Son senkronizasyonun özet istatistiklerini göster
            $summaryFile = __DIR__ . '/logs/last_summary.json';
            if (file_exists($summaryFile)) {
                $stats = json_decode(file_get_contents($summaryFile), true);
                
                if ($stats) {
                    $total = $stats['success_count'] + $stats['partial_count'] + $stats['error_count'] + $stats['skipped_count'];
                    $timestamp = isset($stats['timestamp']) ? $stats['timestamp'] : 'Bilinmiyor';
                    
                    echo '<div class="stats-summary">';
                    echo '<p><strong>Senkronizasyon Zamanı:</strong> ' . $timestamp . '</p>';
                    echo '<p><strong>Toplam İşlenen:</strong> ' . $total . ' ürün</p>';
                    
                    // Başarı yüzdesi
                    $successPercent = ($total > 0) ? round(($stats['success_count'] / $total) * 100, 1) : 0;
                    echo '<div class="progress-bar-container">';
                    echo '<div class="progress-bar" style="width:' . $successPercent . '%">' . $successPercent . '%</div>';
                    echo '</div>';
                    
                    echo '<div class="stats-grid">';
                    echo '<div class="stat-box stat-success">';
                    echo '<h4>Başarılı</h4>';
                    echo '<div class="stat-number">' . $stats['success_count'] . '</div>';
                    echo '<div class="stat-percent">' . ($total > 0 ? round(($stats['success_count'] / $total) * 100, 1) : 0) . '%</div>';
                    echo '</div>';
                    
                    echo '<div class="stat-box stat-partial">';
                    echo '<h4>Kısmen Başarılı</h4>';
                    echo '<div class="stat-number">' . $stats['partial_count'] . '</div>';
                    echo '<div class="stat-percent">' . ($total > 0 ? round(($stats['partial_count'] / $total) * 100, 1) : 0) . '%</div>';
                    echo '</div>';
                    
                    echo '<div class="stat-box stat-failed">';
                    echo '<h4>Başarısız</h4>';
                    echo '<div class="stat-number">' . $stats['error_count'] . '</div>';
                    echo '<div class="stat-percent">' . ($total > 0 ? round(($stats['error_count'] / $total) * 100, 1) : 0) . '%</div>';
                    echo '</div>';
                    
                    echo '<div class="stat-box stat-skipped">';
                    echo '<h4>Atlanan</h4>';
                    echo '<div class="stat-number">' . $stats['skipped_count'] . '</div>';
                    echo '<div class="stat-percent">' . ($total > 0 ? round(($stats['skipped_count'] / $total) * 100, 1) : 0) . '%</div>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '<p><strong>Toplam Süre:</strong> ' . $stats['execution_time'] . ' saniye</p>';
                    
                    // Başarılı ürünleri göster
                    if (!empty($stats['success'])) {
                        echo '<div class="collapsible-section">';
                        echo '<h4 onclick="toggleCollapsible(this)">Başarılı Ürünler (' . count($stats['success']) . ') <span class="toggle-icon">+</span></h4>';
                        echo '<div class="collapsible-content" style="display:none;">';
                        echo '<div class="table-container">';
                        echo '<table class="result-table success-table">';
                        echo '<thead><tr><th>ID</th><th>Ürün Adı</th><th>Fiyat</th><th>Stok</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($stats['success'] as $item) {
                            $id = isset($item['id']) ? $item['id'] : 'Bilinmiyor';
                            $price = isset($item['price']) ? $item['price'] : 'Bilinmiyor';
                            $stock = isset($item['stock']) ? $item['stock'] : 'Bilinmiyor';
                            
                            // Ürün adını bul (eğer array içinde varsa)
                            $productName = "Bilinmiyor";
                            if (isset($item['product_name'])) {
                                $productName = $item['product_name'];
                            }
                            
                            echo '<tr>';
                            echo '<td>' . $id . '</td>';
                            echo '<td>' . $productName . '</td>';
                            echo '<td>' . $price . '</td>';
                            echo '<td>' . $stock . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>'; // table-container
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    // Kısmen başarılı ürünleri göster
                    if (!empty($stats['partial'])) {
                        echo '<div class="collapsible-section">';
                        echo '<h4 onclick="toggleCollapsible(this)">Kısmen Başarılı Ürünler (' . count($stats['partial']) . ') <span class="toggle-icon">+</span></h4>';
                        echo '<div class="collapsible-content" style="display:none;">';
                        echo '<div class="table-container">';
                        echo '<table class="result-table partial-table">';
                        echo '<thead><tr><th>ID</th><th>Ürün Adı</th><th>Başarılı Alanlar</th><th>Başarısız Nedeni</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($stats['partial'] as $item) {
                            $id = isset($item['id']) ? $item['id'] : 'Bilinmiyor';
                            $updated = isset($item['updated']) ? implode(", ", $item['updated']) : 'Bilinmiyor';
                            $reason = isset($item['reason']) ? $item['reason'] : 'Bilinmiyor';
                            
                            // Ürün adını bul
                            $productName = "Bilinmiyor";
                            if (isset($item['product_name'])) {
                                $productName = $item['product_name'];
                            }
                            
                            echo '<tr>';
                            echo '<td>' . $id . '</td>';
                            echo '<td>' . $productName . '</td>';
                            echo '<td>' . $updated . '</td>';
                            echo '<td>' . $reason . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>'; // table-container
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    // Başarısız ürünleri göster
                    if (!empty($stats['failed'])) {
                        echo '<div class="collapsible-section">';
                        echo '<h4 onclick="toggleCollapsible(this)">Başarısız Ürünler (' . count($stats['failed']) . ') <span class="toggle-icon">+</span></h4>';
                        echo '<div class="collapsible-content" style="display:none;">';
                        echo '<div class="table-container">';
                        echo '<table class="result-table failed-table">';
                        echo '<thead><tr><th>ID</th><th>Ürün Adı</th><th>Başarısızlık Nedeni</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($stats['failed'] as $item) {
                            $id = isset($item['id']) ? $item['id'] : 'Bilinmiyor';
                            $reason = isset($item['reason']) ? $item['reason'] : 'Bilinmiyor';
                            
                            // Ürün adını bul
                            $productName = "Bilinmiyor";
                            if (isset($item['product_name'])) {
                                $productName = $item['product_name'];
                            }
                            
                            echo '<tr>';
                            echo '<td>' . $id . '</td>';
                            echo '<td>' . $productName . '</td>';
                            echo '<td>' . $reason . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>'; // table-container
                        echo '</div>';
                        echo '</div>';
                    }
                    
                    echo '</div>';
                } else {
                    echo '<p>Özet istatistik bulunamadı.</p>';
                }
            } else {
                echo '<p>Henüz senkronizasyon yapılmadı veya özet kaydedilmedi.</p>';
            }
            ?>
        </div>
        
        <?php if (!empty($messages)): ?>
            <div class="messages">
                <?php foreach ($messages as $message): ?>
                    <div class="message <?php echo $message['type']; ?>">
                        <?php echo $message['text']; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
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
            <div id="log-content"><?php echo isset($logContent) ? $logContent : ''; ?></div>
        </div>
    </div>
</body>
</html>