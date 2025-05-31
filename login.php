<?php
// Oturum güvenlik ayarları
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // HTTPS için
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'admin/db_connection.php';

// CSRF token oluştur (eğer yoksa)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gerekli tabloları oluştur
function createRequiredTables($conn) {
    try {
        // kullanici_ban tablosu var mı kontrol et, yoksa oluştur
        $stmt = $conn->prepare("SHOW TABLES LIKE 'kullanici_ban'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `kullanici_ban` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `kullanici_id` int(11) DEFAULT NULL,
                `kullanici_tipi` ENUM('admin', 'personel') DEFAULT NULL,
                `ip_adresi` VARCHAR(45) NOT NULL,
                `ban_baslangic` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ban_bitis` DATETIME NOT NULL,
                `sebep` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_kullanici_id` (`kullanici_id`),
                KEY `idx_kullanici_tipi` (`kullanici_tipi`),
                KEY `idx_ban_bitis` (`ban_bitis`),
                KEY `idx_ip_adresi` (`ip_adresi`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // kullanici_giris_log tablosu var mı kontrol et, yoksa oluştur
        $stmt = $conn->prepare("SHOW TABLES LIKE 'kullanici_giris_log'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `kullanici_giris_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `kullanici_id` int(11) DEFAULT NULL,
                `kullanici_tipi` ENUM('admin', 'personel') DEFAULT NULL,
                `islem_tipi` ENUM('giris', 'cikis', 'dogrulama', 'basarisiz') NOT NULL,
                `ip_adresi` VARCHAR(45) DEFAULT NULL,
                `tarayici_bilgisi` VARCHAR(255) DEFAULT NULL,
                `tarih` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `detay` TEXT DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_kullanici_id` (`kullanici_id`),
                KEY `idx_kullanici_tipi` (`kullanici_tipi`),
                KEY `idx_islem_tipi` (`islem_tipi`),
                KEY `idx_tarih` (`tarih`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (PDOException $e) {
        error_log("Tablo oluşturma hatası: " . $e->getMessage());
    }
}

// Ban durumu kontrolü
function checkUserBanned($conn, $ip) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM kullanici_ban 
            WHERE ip_adresi = ? AND ban_bitis > NOW()
        ");
        $stmt->execute([$ip]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Ban kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

// Ban ekleme fonksiyonu - DÜZELTİLMİŞ
function banUser($conn, $ip, $sebep, $userId, $userType) {
    try {
        // Ban bitiş saati (şu anki zaman + 12 saat)
        $banBitis = date('Y-m-d H:i:s', strtotime('+12 hours'));
        
        $stmt = $conn->prepare("
            INSERT INTO kullanici_ban 
            (kullanici_id, kullanici_tipi, ip_adresi, ban_baslangic, ban_bitis, sebep) 
            VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([$userId, $userType, $ip, $banBitis, $sebep]);
        
        // Kullanıcı aktivite log kaydı
        logUserActivity($conn, 'basarisiz', $ip, $userId, $userType, 'Kullanıcı banlandı: ' . $sebep);
        
        return true;
    } catch (PDOException $e) {
        error_log("Ban ekleme hatası: " . $e->getMessage());
        return false;
    }
}

// Kullanıcı giriş log kaydı fonksiyonu - DÜZELTİLMİŞ
function logUserActivity($conn, $islemTipi, $ipAdresi, $kullaniciId, $kullaniciTipi, $detay) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO kullanici_giris_log 
            (kullanici_id, kullanici_tipi, islem_tipi, ip_adresi, tarayici_bilgisi, detay) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $kullaniciId,
            $kullaniciTipi,
            $islemTipi,
            $ipAdresi,
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $detay
        ]);
    } catch (PDOException $e) {
        error_log("Kullanıcı giriş log kaydı oluşturulurken hata: " . $e->getMessage());
    }
}

// Başarısız giriş denemelerini kontrol et
function checkFailedLoginAttempts($conn, $ip) {
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM kullanici_giris_log 
            WHERE ip_adresi = ? AND islem_tipi = 'basarisiz' AND tarih > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        ");
        $stmt->execute([$ip]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Başarısız giriş kontrolü hatası: " . $e->getMessage());
        return 0;
    }
}

// Gerekli tabloları oluştur
createRequiredTables($conn);

// Eğer zaten giriş yapılmışsa ve doğrulanmışsa POS sayfasına yönlendir
if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && isset($_SESSION['verified']) && $_SESSION['verified']) {
    header("Location: pos.php");
    exit;
}

$error = '';

// IP adresini al
$ip = $_SERVER['REMOTE_ADDR'];

// Ban durumunu kontrol et
$bannedUser = checkUserBanned($conn, $ip);
if ($bannedUser) {
    // Kalan ban süresini hesapla
    $banBitis = new DateTime($bannedUser['ban_bitis']);
    $now = new DateTime();
    $interval = $now->diff($banBitis);
    
    $kalanSure = '';
    if ($interval->d > 0) {
        $kalanSure .= $interval->d . ' gün ';
    }
    if ($interval->h > 0) {
        $kalanSure .= $interval->h . ' saat ';
    }
    if ($interval->i > 0) {
        $kalanSure .= $interval->i . ' dakika';
    }
    
    $error = "Hesabınız çok fazla hatalı giriş denemesi nedeniyle $kalanSure süreyle kısıtlanmıştır.";
} else {
    // Form gönderilmişse
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF token kontrolü
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Güvenlik doğrulaması başarısız oldu. Lütfen sayfayı yenileyip tekrar deneyin.';
        } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = 'Kullanıcı adı ve şifre gereklidir.';
            } else {
                // Personel tablosundan kullanıcıyı kontrol et
                $stmt = $conn->prepare("SELECT p.id, p.ad, p.kullanici_adi, p.sifre, p.yetki_seviyesi, p.telefon_no, p.magaza_id, m.ad AS magaza_adi 
                                      FROM personel p 
                                      LEFT JOIN magazalar m ON p.magaza_id = m.id 
                                      WHERE p.kullanici_adi = ? AND p.durum = 'aktif'");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['sifre'])) {
                    // Başarılı giriş logunu kaydet - DÜZELTİLMİŞ
                    logUserActivity($conn, 'giris', $ip, $user['id'], $user['yetki_seviyesi'], 'Başarılı giriş');
                    
                    // Session bilgilerini ayarla
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['ad'];
                    $_SESSION['yetki'] = $user['yetki_seviyesi'];
                    $_SESSION['user_phone'] = $user['telefon_no']; // Kasiyerin telefon numarasını kaydet
                    
                    // Yetki kontrolü
                    if ($user['yetki_seviyesi'] == 'kasiyer') {
                        // Kasiyer ise sadece kendi mağazasını kullanabilir
                        if (!empty($user['magaza_id'])) {
                            $_SESSION['magaza_id'] = $user['magaza_id'];
                            $_SESSION['magaza_adi'] = $user['magaza_adi'];
                            
                            // Doğrulama sayfasına yönlendir
                            header("Location: verify.php");
                            exit;
                        } else {
                            $error = 'Kasiyer için mağaza tanımlanmamış. Lütfen yönetici ile iletişime geçin.';
                            session_destroy();
                        }
                    } else {
                        // Müdür veya müdür yardımcısı ise mağaza seçebilir
                        header("Location: select_store.php");
                        exit;
                    }
                } else {
                    // Başarısız giriş logunu kaydet - DÜZELTİLMİŞ
                    $userId = null;
                    $userType = null;
                    
                    // Kullanıcı adı doğru ama şifre yanlış ise kullanıcı ID'sini kaydedelim
                    if ($user) {
                        $userId = $user['id'];
                        $userType = $user['yetki_seviyesi'];
                    }
                    
                    logUserActivity($conn, 'basarisiz', $ip, $userId, $userType, 'Hatalı giriş denemesi: ' . $username);
                    
                    // Başarısız giriş denemelerini kontrol et
                    $failedAttempts = checkFailedLoginAttempts($conn, $ip);
                    
                    if ($failedAttempts >= 3) {
                        // 3 başarısız giriş - ban ekle - DÜZELTİLMİŞ
                        banUser($conn, $ip, 'Çok fazla hatalı giriş denemesi', $userId, $userType);
                        
                        $error = 'Çok fazla hatalı giriş denemesi yaptınız. Hesabınız 12 saat süreyle kısıtlanmıştır.';
                    } else {
                        // Kalan deneme hakkını göster
                        $remainingAttempts = 3 - $failedAttempts;
                        $error = 'Geçersiz kullanıcı adı veya şifre. Kalan deneme hakkı: ' . $remainingAttempts;
                    }
                }
            }
        }
    }
}

// Ban mesajı gösterimi (doğrulama sayfasından yönlendirme olursa)
if (isset($_GET['banned']) && $_GET['banned'] == 1) {
    $banTime = isset($_GET['time']) ? $_GET['time'] : '12 saat';
    $error = "Hesabınız çok fazla hatalı giriş denemesi nedeniyle $banTime süreyle kısıtlanmıştır.";
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İnci Kırtasiye - Giriş</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-blue-700">İnci Kırtasiye</h1>
            <p class="text-gray-600">Satış Yönetim Sistemine Hoşgeldiniz</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$bannedUser): ?>
            <form method="POST" action="">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="mb-4">
                    <label for="username" class="block text-gray-700 text-sm font-bold mb-2">Kullanıcı Adı</label>
                    <input id="username" name="username" type="text" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" autocomplete="username">
                </div>
                
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Şifre</label>
                    <input id="password" name="password" type="password" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" autocomplete="current-password">
                </div>
                
                <div class="flex items-center justify-between">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                        Giriş Yap
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>