<?php
// Sunucu tarafı işlemlerinin süresini ölçmek için
$end_time = microtime(true);
$server_execution_time = round($end_time - $start_time, 2);
?>
</div> <!-- Ana içerik div'ini kapat -->
    </div> <!-- flex-grow div'ini kapat -->
    <!-- Footer -->
    <footer class="bg-white shadow-md mt-auto">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col items-center justify-center">
                <p class="text-gray-600 text-sm">
                    © <?php echo date('Y'); ?> İnciPos Admin Paneli. Tüm hakları saklıdır.
                </p>
                <p class="text-gray-500 text-xs mt-1">
                    PHP işlem süresi: <?php echo $server_execution_time; ?> saniye
                </p>
                <p class="text-gray-500 text-xs mt-1" id="total-load-time">
                    Toplam sayfa yüklenme süresi: Hesaplanıyor...
                </p>
            </div>
        </div>
    </footer>
    <!-- Genel JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="module" src="/admin/assets/js/main.js"></script>
    <script>
        // Sayfa yüklendiğinde çalışacak fonksiyon
        window.addEventListener('load', function() {
            // Eğer head'de pageStartTime tanımlanmadıysa
            if (!window.pageStartTime) {
                document.getElementById('total-load-time').textContent = 
                    'Sayfa yüklenme süresi: Tam ölçüm için head bölümüne kod eklenmeli';
                return;
            }
            
            const loadTime = (Date.now() - window.pageStartTime) / 1000; // saniye cinsinden
            
            // Gerçekçi bir kontrol ekleyelim
            if (loadTime < 0.1) {
                document.getElementById('total-load-time').textContent = 
                    'Sayfa yüklenme süresi: Ölçüm hatası (çok düşük değer)';
            } else {
                document.getElementById('total-load-time').textContent = 
                    `Toplam sayfa yüklenme süresi: ${loadTime.toFixed(2)} saniye`;
            }
        });

        // Kullanıcı menüsü toggle
        document.getElementById('user-menu-button')?.addEventListener('click', function() {
            document.getElementById('user-menu').classList.toggle('hidden');
        });
        
        // Menü dışına tıklandığında menüyü kapat
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('user-menu');
            const button = document.getElementById('user-menu-button');
            
            if (menu && !menu.contains(event.target) && !button.contains(event.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>
    <!-- Sayfa özel scriptleri -->
    <?php 
    if (isset($page_scripts)) {
        echo $page_scripts;
    }
    ?>
</body>
</html>