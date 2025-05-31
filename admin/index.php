<?php
/**
 * İnciPOS - Güvenli Giriş Sayfası
 * 
 * Bu dosya, İnciPOS sisteminin güvenli giriş sayfasını içerir.
 * Güvenlik önlemleri:
 * - Brute force koruması
 * - CSRF koruması
 * - XSS koruması
 * - SQL injection koruması
 * - IP bazlı login attempt limiti
 */

// Gerekli dosyaları dahil et
require_once 'session_manager.php';
require_once 'db_connection.php';

// Güvenli oturumu başlat
secure_session_start();

// Kullanıcı zaten giriş yapmışsa ve SMS doğrulaması da tamamlanmışsa
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['sms_verified']) && $_SESSION['sms_verified'] === true) {
        // Kullanıcı tam olarak giriş yapmış, dashboard'a yönlendir
        header("Location: admin_dashboard.php");
        exit;
    } else {
        // Kullanıcı giriş yapmış ama SMS doğrulaması yapmamış
        header("Location: verify.php");
        exit;
    }
}

// Hata mesajı için değişken
$error_message = '';
$success_message = '';

// Gerekli tabloları ve fonksiyonları kontrol et
check_required_tables_and_functions($conn);

// Form gönderildiğinde işlem yap
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.";
    } else {
        // Giriş işlemi
        process_login($conn);
    }
}

/**
 * Giriş işlemini gerçekleştirir
 */
function process_login($conn) {
    global $error_message, $success_message;
    
    // IP adresi ve zamana göre brute force koruması
    if (is_ip_banned($conn, $_SERVER['REMOTE_ADDR'])) {
        $error_message = "Çok fazla başarısız giriş denemesi. Lütfen daha sonra tekrar deneyin.";
        return;
    }
    
    // Form verilerini güvenli şekilde al
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = $_POST['password'] ?? '';
    
    // Boş kontrolleri
    if (empty($username) || empty($password)) {
        $error_message = "Kullanıcı adı ve şifre gereklidir.";
        log_login_attempt($conn, $username, false, "Eksik bilgi");
        return;
    }
    
    try {
        // Kullanıcıyı veritabanında ara
        $stmt = $conn->prepare("SELECT * FROM admin_user WHERE kullanici_adi = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Kullanıcı bulunamadı veya şifre yanlış
        if (!$user || !password_verify($password, $user['sifre'])) {
            $error_message = "Kullanıcı adı veya şifre hatalı.";
            log_login_attempt($conn, $username, false, "Kullanıcı bulunamadı veya şifre hatalı");
            return;
        }
        
        // Kullanıcı ban kontrolü
        if (is_user_banned($conn, $user['id'], 'admin')) {
            $error_message = "Hesabınız geçici olarak kilitlendi. Lütfen daha sonra tekrar deneyin.";
            log_login_attempt($conn, $username, false, "Kullanıcı banlanmış");
            return;
        }
        
        // Başarılı giriş, kullanıcı bilgilerini oturuma kaydet
        loginUser($user['id'], $user['kullanici_adi']);
        
        // SMS doğrulaması için oturum değişkenini ayarla
        $_SESSION['sms_verified'] = false;
        
        // Başarılı giriş kaydı
        log_login_attempt($conn, $username, true, "Başarılı giriş");
        
        // Kullanıcı aktivite log kaydı
        log_user_activity($conn, $user['id'], 'admin', 'giris', "Başarılı giriş");
        
        // SMS doğrulama sayfasına yönlendir
        header("Location: verify.php");
        exit;
    } catch (PDOException $e) {
        $error_message = "Veritabanı hatası: " . $e->getMessage();
        error_log("Login error: " . $e->getMessage());
    }
}

/**
 * Giriş denemesini loglar
 */
function log_login_attempt($conn, $username, $success, $details = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO login_attempts (ip_adresi, username_attempt, basarili, zaman)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'],
            $username,
            $success ? 1 : 0
        ]);
        
        // Başarısız giriş sayısını kontrol et ve gerekirse IP'yi banla
        if (!$success) {
            check_and_ban_ip($conn, $_SERVER['REMOTE_ADDR'], $username);
        }
    } catch (PDOException $e) {
        error_log("Login attempt log error: " . $e->getMessage());
    }
}

/**
 * Kullanıcı aktivitesini loglar
 */
function log_user_activity($conn, $userId, $userType, $actionType, $details = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO kullanici_giris_log 
            (kullanici_id, kullanici_tipi, islem_tipi, ip_adresi, tarayici_bilgisi, detay) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $userId,
            $userType,
            $actionType,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $details
        ]);
    } catch (PDOException $e) {
        error_log("Log kaydı hatası: " . $e->getMessage());
        return false;
    }
}

/**
 * IP adresinin banlanıp banlanmadığını kontrol eder
 */
function is_ip_banned($conn, $ip) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM login_ban 
            WHERE ip_adresi = ? AND ban_bitis > NOW()
        ");
        $stmt->execute([$ip]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("IP ban check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının banlanıp banlanmadığını kontrol eder
 */
function is_user_banned($conn, $userId, $userType) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM kullanici_ban 
            WHERE kullanici_id = ? AND kullanici_tipi = ? AND ban_bitis > NOW()
        ");
        $stmt->execute([$userId, $userType]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("User ban check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Başarısız giriş denemelerini kontrol eder ve gerekirse IP'yi banlar
 */
function check_and_ban_ip($conn, $ip, $username) {
    try {
        // Son 10 dakika içindeki başarısız giriş denemeleri
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM login_attempts 
            WHERE ip_adresi = ? AND basarili = 0 AND zaman > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $stmt->execute([$ip]);
        $count = $stmt->fetchColumn();
        
        // 5 başarısız denemeden sonra 15 dakika ban
        if ($count >= 5) {
            $stmt = $conn->prepare("
                INSERT INTO login_ban (ip_adresi, username_attempt, ban_baslangic, ban_bitis, sebep)
                VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 'Çok fazla başarısız giriş denemesi')
            ");
            $stmt->execute([$ip, $username]);
        }
    } catch (PDOException $e) {
        error_log("IP ban process error: " . $e->getMessage());
    }
}

/**
 * Gerekli tabloları ve fonksiyonları kontrol eder
 */
function check_required_tables_and_functions($conn) {
    try {
        // Kullanıcı giriş log tablosu kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'kullanici_giris_log'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `kullanici_giris_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `kullanici_id` int(11) NOT NULL,
                `kullanici_tipi` ENUM('admin', 'personel') NOT NULL,
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
        
        // Login attempts tablosu kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'login_attempts'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `login_attempts` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `ip_adresi` varchar(45) NOT NULL,
                `username_attempt` varchar(50) DEFAULT NULL,
                `zaman` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `basarili` tinyint(1) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `idx_ip_adresi` (`ip_adresi`),
                KEY `idx_zaman` (`zaman`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // Login ban tablosu kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'login_ban'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `login_ban` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `ip_adresi` varchar(45) NOT NULL,
                `username_attempt` varchar(50) DEFAULT NULL,
                `ban_baslangic` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ban_bitis` datetime NOT NULL,
                `sebep` varchar(255) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_ip_adresi` (`ip_adresi`),
                KEY `idx_ban_bitis` (`ban_bitis`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // Kullanıcı ban tablosu kontrol et
        $stmt = $conn->prepare("SHOW TABLES LIKE 'kullanici_ban'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `kullanici_ban` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `kullanici_id` int(11) NOT NULL,
                `kullanici_tipi` enum('admin','personel') NOT NULL,
                `ban_baslangic` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ban_bitis` datetime NOT NULL,
                `sebep` varchar(255) NOT NULL,
                `ip_adresi` varchar(45) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_kullanici_id` (`kullanici_id`),
                KEY `idx_kullanici_tipi` (`kullanici_tipi`),
                KEY `idx_ban_bitis` (`ban_bitis`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (PDOException $e) {
        error_log("Tablo kontrol/oluşturma hatası: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İnciPOS - Yönetici Girişi</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="login-title">İnciPOS</h1>
                <p class="login-subtitle">Yönetici Paneli</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="login-message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="login-message success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php" class="login-form">
                <!-- CSRF token ekle -->
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Kullanıcı Adı</label>
                    <input type="text" name="username" id="username" class="login-input" placeholder="Kullanıcı adınızı girin" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Şifre</label>
                    <input type="password" name="password" id="password" class="login-input" placeholder="Şifrenizi girin" required>
                </div>
                
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> GİRİŞ YAP
                </button>
            </form>
            
            <div class="login-footer">
                <p>© <?php echo date('Y'); ?> İnciPOS - Tüm Hakları Saklıdır</p>
            </div>
        </div>
    </div>
    
    <script>
        // Sayfa yüklendiğinde form alanlarını temizle
        window.onload = function() {
            document.getElementById('username').value = '';
            document.getElementById('password').value = '';
        }
    </script>
</body>
</html>