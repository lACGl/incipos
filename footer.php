</div> <!-- Ana içerik div'ini kapat -->
    </div> <!-- flex-grow div'ini kapat -->

    <!-- Footer -->
    <footer class="bg-white shadow-md mt-auto">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col items-center justify-center">
                <p class="text-gray-600 text-sm">
                    © <?php echo date('Y'); ?> İnciPos Admin Paneli. Tüm hakları saklıdır.
                </p>
                <?php if (isset($execution_time)): ?>
                <p class="text-gray-500 text-xs mt-1">
                    Sayfa yüklenme süresi: <?php echo $execution_time; ?> saniye
                </p>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <!-- Genel JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="module" src="assets/js/main.js"></script>
    <script>
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