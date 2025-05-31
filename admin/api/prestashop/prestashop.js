// Sayfa yüklendiğinde çalışacak
document.addEventListener('DOMContentLoaded', function() {
    // Canlı log takip değişkenleri
    let logUpdateInterval;
    let autoRefresh = true;
    let currentLogType = 'sync'; // 'sync' veya 'barcode'
    
    // Log güncelleme fonksiyonu - Senkronizasyon logları için
    function updateSyncLogs() {
        fetch('?action=getlogs')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Formatlanmış log içeriğini oluştur
                    updateLogContent(data.data);
                    
                    // Senkronizasyon durumunu güncelle
                    updateSyncStatus(data.syncStatus);
                }
            })
            .catch(error => console.error('Log güncellenemedi:', error));
    }
    
    // Barkod eşleştirme loglarını güncelleme
    function updateBarcodeLogs() {
        fetch('?action=get_barcode_match_logs')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Formatlanmış log içeriğini oluştur
                    updateLogContent(data.data);
                    
                    // Senkronizasyon durumunu güncelle
                    updateSyncStatus(data.syncStatus);
                }
            })
            .catch(error => console.error('Barkod eşleştirme logları güncellenemedi:', error));
    }
    
    // Log içeriğini güncelleme fonksiyonu - Her iki log tipi için ortak
    function updateLogContent(logData) {
        let formattedContent = '';
        const logLines = logData.split('<br />');
        
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
    }
    
    // Senkronizasyon durumunu güncelleme
    function updateSyncStatus(status) {
        const statusElement = document.getElementById('sync-status');
        if (status === 'running') {
            statusElement.textContent = 'Devam ediyor';
            document.querySelector('.status').classList.add('running');
            document.querySelector('.status').classList.remove('stopped');
        } else {
            statusElement.textContent = 'Beklemede';
            document.querySelector('.status').classList.add('stopped');
            document.querySelector('.status').classList.remove('running');
        }
    }
    
    // Otomatik güncellemeyi başlat/durdur
    function toggleAutoRefresh() {
        const checkbox = document.getElementById('auto-refresh');
        autoRefresh = checkbox.checked;
        
        // Varsa mevcut interval'i temizle
        if (logUpdateInterval) {
            clearInterval(logUpdateInterval);
            logUpdateInterval = null;
        }
        
        if (autoRefresh) {
            // 2 saniyede bir güncelle - hangi log tipine göre güncelleyeceğini belirle
            if (currentLogType === 'sync') {
                logUpdateInterval = setInterval(updateSyncLogs, 2000);
            } else {
                logUpdateInterval = setInterval(updateBarcodeLogs, 2000);
            }
            document.querySelector('.live-indicator').style.display = 'inline-block';
        } else {
            document.querySelector('.live-indicator').style.display = 'none';
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
    
    // Log türünü değiştir ve güncellemeleri yeniden başlat
    function switchToLogType(logType) {
        // Log türünü ayarla
        currentLogType = logType;
        
        // Başlığı güncelle
        const logTitle = document.querySelector('.flex-row h2');
        if (logType === 'sync') {
            logTitle.textContent = 'Log Dosyası (Canlı Takip)';
        } else {
            logTitle.textContent = 'Barkod Eşleştirme Logları (Canlı Takip)';
        }
        
        // Mevcut interval'i temizle ve yeni log türüne göre yeniden başlat
        if (logUpdateInterval) {
            clearInterval(logUpdateInterval);
        }
        
        // Otomatik güncelleme aktifse yeniden başlat
        if (autoRefresh) {
            if (logType === 'sync') {
                logUpdateInterval = setInterval(updateSyncLogs, 2000);
                // İlk güncellemeyi hemen yap
                updateSyncLogs();
            } else {
                logUpdateInterval = setInterval(updateBarcodeLogs, 2000);
                // İlk güncellemeyi hemen yap
                updateBarcodeLogs();
            }
        } else {
            // Otomatik güncelleme kapalıysa, bir kerelik güncelleme yap
            if (logType === 'sync') {
                updateSyncLogs();
            } else {
                updateBarcodeLogs();
            }
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
    
    // Manuel güncelleştirme butonu
    const refreshButton = document.getElementById('refresh-logs');
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            if (currentLogType === 'sync') {
                updateSyncLogs();
            } else {
                updateBarcodeLogs();
            }
        });
    }
    
    // Senkronizasyonu başlat butonu
    const syncButton = document.querySelector('.actions').querySelector('a[href="?action=sync"]');
    if (syncButton) {
        // Butona tıklanınca AJAX isteği yap
        syncButton.addEventListener('click', function(event) {
            event.preventDefault(); // Sayfa yönlendirmesini engelle
            
            // Log türünü senkronizasyon olarak ayarla
            switchToLogType('sync');
            
            // Senkronizasyon başladı mesajını göster
            const message = document.createElement('div');
            message.className = 'message success';
            message.textContent = 'Senkronizasyon başlatılıyor... Lütfen bekleyin.';
            
            // Mesajları gösterdiğimiz div'i bul ve mesajı ekle
            const messagesDiv = document.querySelector('.messages') || document.createElement('div');
            if (!document.querySelector('.messages')) {
                messagesDiv.className = 'messages';
                document.querySelector('.container').appendChild(messagesDiv);
            }
            messagesDiv.innerHTML = ''; // Eski mesajları temizle
            messagesDiv.appendChild(message);
            
            // AJAX isteği gönder
            fetch('index.php?action=sync', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(() => {
                // Senkronizasyon başladı, özet durumunu güncelle
                updateSyncSummary();
                
                // Auto-refresh'i aktif et
                if (document.getElementById('auto-refresh')) {
                    document.getElementById('auto-refresh').checked = true;
                    toggleAutoRefresh();
                }
                
                // Sayfayı otomatik olarak log bölümüne kaydır
                document.querySelector('.log-container').scrollIntoView({ behavior: 'smooth' });
                
                // Tamamlandı mesajı
                message.textContent = 'Senkronizasyon başlatıldı. Güncellemeler otomatik gösterilecek.';
                
                // 5 saniye sonra mesajı kaldır
                setTimeout(function() {
                    message.style.opacity = '0';
                    message.style.transition = 'opacity 0.5s';
                    setTimeout(function() {
                        if (message.parentNode) {
                            message.parentNode.removeChild(message);
                        }
                    }, 500);
                }, 5000);
            }).catch(error => {
                message.className = 'message error';
                message.textContent = 'Senkronizasyon başlatılırken hata oluştu: ' + error;
            });
        });
    }
    
    // Barkod eşleştirme butonu
    const barcodeButton = document.querySelector('.actions').querySelector('a[href="?action=match_barcodes"]');
    if (barcodeButton) {
        barcodeButton.addEventListener('click', function(event) {
            event.preventDefault(); // Sayfa yönlendirmesini engelle
            
            // Log türünü barkod eşleştirme olarak ayarla
            switchToLogType('barcode');
            
            // Barkod eşleştirme başladı mesajını göster
            const message = document.createElement('div');
            message.className = 'message success';
            message.textContent = 'Barkod eşleştirme başlatılıyor... Lütfen bekleyin.';
            
            // Mesajları gösterdiğimiz div'i bul ve mesajı ekle
            const messagesDiv = document.querySelector('.messages') || document.createElement('div');
            if (!document.querySelector('.messages')) {
                messagesDiv.className = 'messages';
                document.querySelector('.container').appendChild(messagesDiv);
            }
            messagesDiv.innerHTML = ''; // Eski mesajları temizle
            messagesDiv.appendChild(message);
            
            // AJAX isteği gönder
            fetch('index.php?action=match_barcodes', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(() => {
                // Auto-refresh'i aktif et
                if (document.getElementById('auto-refresh')) {
                    document.getElementById('auto-refresh').checked = true;
                    toggleAutoRefresh();
                }
                
                // Sayfayı otomatik olarak log bölümüne kaydır
                document.querySelector('.log-container').scrollIntoView({ behavior: 'smooth' });
                
                // Tamamlandı mesajı
                message.textContent = 'Barkod eşleştirme başlatıldı. Güncellemeler otomatik gösterilecek.';
                
                // 5 saniye sonra mesajı kaldır
                setTimeout(function() {
                    message.style.opacity = '0';
                    message.style.transition = 'opacity 0.5s';
                    setTimeout(function() {
                        if (message.parentNode) {
                            message.parentNode.removeChild(message);
                        }
                    }, 500);
                }, 5000);
            }).catch(error => {
                message.className = 'message error';
                message.textContent = 'Barkod eşleştirme başlatılırken hata oluştu: ' + error;
            });
        });
    }
    
    // İlk yüklemede senkronizasyon loglarını güncelle
    updateSyncLogs();
    
    // Fonksiyonları global alanda tanımla
    window.updateSyncLogs = updateSyncLogs;
    window.updateBarcodeLogs = updateBarcodeLogs;
    window.updateSyncSummary = updateSyncSummary;
    window.switchToLogType = switchToLogType;
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