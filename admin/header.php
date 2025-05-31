<?php
// Önce session yönetim fonksiyonlarını yükle
require_once 'session_manager.php';
// Güvenli oturumu başlat
secure_session_start();

// Session kontrolü - Yetkisiz erişimleri engelle
checkUserSession();

// Veritabanı bağlantısı
require_once 'db_connection.php';

// Aktif kullanıcı bilgilerini al
$stmt = $conn->prepare("SELECT * FROM admin_user WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Kullanıcı bulunmadıysa veya silindiyse çıkış yap
if (!$user) {
    logout();
    exit;
}

// Son aktivite zamanını kontrol et ve güncelle (inaktif kullanıcıları çıkarmak için)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // 30 dakika inaktif kalmışsa çıkış yap
    logout();
    exit;
}
$_SESSION['last_activity'] = time();

// Aktif sayfayı belirle
$current_page = basename($_SERVER['PHP_SELF']);

$start_time = microtime(true);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İnciPos Admin</title>
    <link rel="icon" href="data:;base64,iVBORw0KGgo=">
    <link rel="stylesheet" href="/admin/assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script>
        // Sayfa yüklenmeye başladığı zamanı kaydet (sayfa başında)
        window.pageStartTime = Date.now();
        
        // CSRF Token doğrulama fonksiyonu - AJAX istekleri için
        function getCSRFToken() {
            return '<?php echo generateCSRFToken(); ?>';
        }
        
        // AJAX isteklerinde CSRF Token kullanımı için
        function fetchWithCSRF(url, options = {}) {
            if (!options.headers) {
                options.headers = {};
            }
            options.headers['X-CSRF-Token'] = getCSRFToken();
            return fetch(url, options);
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <div class="flex-grow flex flex-col">
    <!-- Header -->
    <header id="header" class="bg-white shadow-md fixed top-0 left-0 right-0 z-50">
	
<!-- Sistem Ayarları Modal -->
<div id="settingsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Sistem Ayarları</h3>
            <button onclick="closeSettings()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <!-- Formların içine CSRF token eklenmeli -->
        <input type="hidden" id="settings_csrf_token" value="<?php echo generateCSRFToken(); ?>">
    </div>
</div>
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center h-16">
                <!-- Logo ve Başlık -->
                <div class="flex items-center">
                    <a href="#" class="flex items-center">
                        <span class="text-xl font-bold text-gray-800">İnciPos</span>
                    </a>
                </div>

                <!-- Ana Menü -->
                <nav class="hidden md:flex space-x-4">
                    <a href="https://pos.incikirtasiye.com/admin/admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'bg-blue-500 text-white' : 'text-gray-600 hover:text-gray-900'; ?> px-3 py-2 rounded-md text-sm font-medium">
                        Dashboard
                    </a>
                    <a href="/admin/stock_list.php" class="<?php echo $current_page == 'stock_list.php' ? 'bg-blue-500 text-white' : 'text-gray-600 hover:text-gray-900'; ?> px-3 py-2 rounded-md text-sm font-medium">
                        Stok Listesi
                    </a>
                    <a href="/admin/purchase_invoices.php" class="<?php echo $current_page == 'purchase_invoices.php' ? 'bg-blue-500 text-white' : 'text-gray-600 hover:text-gray-900'; ?> px-3 py-2 rounded-md text-sm font-medium">
                        Alış Faturaları
                    </a>
                    <a href="/admin/sales_invoices.php" class="<?php echo $current_page == 'sales_invoices.php' ? 'bg-blue-500 text-white' : 'text-gray-600 hover:text-gray-900'; ?> px-3 py-2 rounded-md text-sm font-medium">
                        Satış Faturaları
                    </a>
                    <a href="/admin/customers.php" class="<?php echo $current_page == 'customers.php' ? 'bg-blue-500 text-white' : 'text-gray-600 hover:text-gray-900'; ?> px-3 py-2 rounded-md text-sm font-medium">
                        Müşteriler
                    </a><a href="/admin/reports.php" class="<?php echo $current_page == 'reports.php' ? 'bg-blue-500 text-white' : 'text-gray-600 hover:text-gray-900'; ?> px-3 py-2 rounded-md text-sm font-medium">
                        Raporlar
                    </a>
					</a><a href="/admin/settings.php" class="<?php echo $current_page == 'settings.php' ? 'bg-blue-500 text-white' : 'text-gray-600 hover:text-gray-900'; ?> px-3 py-2 rounded-md text-sm font-medium">
                        Ayarlar
                    </a>
                </nav>

                <!-- Kullanıcı Menüsü -->
                <div class="flex items-center">
                    <div class="relative ml-3">
                        <div>
                            <button type="button" class="flex text-sm rounded-full focus:outline-none" id="user-menu-button">
                                <span class="sr-only">Menüyü aç</span>
                                <div class="h-8 w-8 rounded-full bg-gray-300 flex items-center justify-center">
                                    <span class="text-sm font-medium text-gray-600">
                                        <?php echo substr(htmlspecialchars($user['kullanici_adi']), 0, 1); ?>
                                    </span>
                                </div>
                            </button>
                        </div>

                        <!-- Dropdown menü -->
                        <div class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5" id="user-menu">
                            <div class="px-4 py-2 text-xs text-gray-500">
                                <?php echo htmlspecialchars($user['kullanici_adi']); ?>
                            </div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">Çıkış Yap</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Ana içerik için padding -->
    <div style="" class="pt-24">
    
    <!-- CSRF Token için gizli alan (AJAX istekleri için) -->
    <input type="hidden" id="global_csrf_token" value="<?php echo generateCSRFToken(); ?>">