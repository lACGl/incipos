<?php
// Gerekli dosyaları dahil et
require_once 'session_manager.php';
require_once 'db_connection.php';
require_once 'netgsm_helper.php';

// Fonksiyon tanımlamaları
if (!function_exists('logUserActivity')) {
    function logUserActivity($conn, $kullaniciId, $kullaniciTipi, $islemTipi, $detay = null) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO kullanici_giris_log 
                (kullanici_id, kullanici_tipi, islem_tipi, ip_adresi, tarayici_bilgisi, detay) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $kullaniciId,
                $kullaniciTipi,
                $islemTipi,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $detay
            ]);
        } catch (PDOException $e) {
            error_log("Log kaydı hatası: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('updateSmsVerificationStatus')) {
    function updateSmsVerificationStatus($conn, $kullaniciId, $kullaniciTipi, $durum) {
        try {
            $stmt = $conn->prepare("
                UPDATE sms_dogrulama_log 
                SET durum = ?, dogrulama_tarihi = NOW()
                WHERE kullanici_id = ? AND kullanici_tipi = ? 
                ORDER BY id DESC LIMIT 1
            ");
            
            return $stmt->execute([
                $durum,
                $kullaniciId,
                $kullaniciTipi
            ]);
        } catch (PDOException $e) {
            error_log("SMS güncelleme hatası: " . $e->getMessage());
            return false;
        }
    }
}

// SMS gönderme işlevi - tekrar eden kodu fonksiyona çıkarıyoruz
function sendVerificationSMS($conn, $userId, $userType) {
    try {
        // Veritabanından direkt telefon numarasını al
        $stmt = $conn->prepare("SELECT telefon_no FROM admin_user WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['telefon_no'])) {
            $phoneNumber = $result['telefon_no'];
            
            // Telefon numarası formatını düzenle
            $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber); // Sadece rakamları al
            
            // Eğer başında 0 varsa kaldır
            if (substr($phoneNumber, 0, 1) == '0') {
                $phoneNumber = substr($phoneNumber, 1);
            }
            
            // Eğer başında ülke kodu yoksa ekle
            if (substr($phoneNumber, 0, 2) != '90') {
                $phoneNumber = '90' . $phoneNumber;
            }
            
            $_SESSION['phone'] = $phoneNumber; // Session'a kaydet
            
            // Güvenli rastgele sayı üreteci ile 6 haneli kod
            $_SESSION['verification_code'] = mt_rand(100000, 999999);
            
            // Kullanıcı aktivite log kaydı
            logUserActivity($conn, $userId, $userType, 'dogrulama', 'SMS gönderim başlangıcı');
            
            // NetGSM ile SMS gönderimi
            $netgsm = new NetGSMHelper($conn);
            $result = $netgsm->sendVerificationSMS($phoneNumber, $_SESSION['verification_code']);
            
            // Kullanıcı aktivite log kaydı
            logUserActivity($conn, $userId, $userType, 'dogrulama', 'SMS gönderimi: ' . ($result['success'] ? 'Başarılı' : 'Başarısız - ' . ($result['message'] ?? 'Bilinmeyen hata')));
            
            // Zamanı güncelle
            $_SESSION['verification_start_time'] = time();
            $_SESSION['verify_attempts'] = 0;
            
            return [
                'success' => $result['success'],
                'message' => $result['message'] ?? '',
                'phone' => $phoneNumber
            ];
        } else {
            // Hata durumunda kullanıcı giriş log kaydı
            logUserActivity($conn, $userId, $userType, 'basarisiz', 'Telefon numarası bulunamadı');
            
            return [
                'success' => false,
                'message' => 'Telefon numarası bulunamadı'
            ];
        }
    } catch (PDOException $e) {
        error_log("Database error when fetching phone: " . $e->getMessage());
        
        // Hata durumunda kullanıcı giriş log kaydı
        logUserActivity($conn, $userId, $userType, 'basarisiz', 'Telefon numarası sorgulama hatası: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Veritabanı hatası: ' . $e->getMessage()
        ];
    }
}

// Güvenli oturumu başlat
secure_session_start();

// Kullanıcı oturum açmamışsa giriş sayfasına yönlendir
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// ÖNEMLİ: Eğer SMS doğrulaması zaten yapıldıysa dashboard'a yönlendir
if (isset($_SESSION['sms_verified']) && $_SESSION['sms_verified'] === true) {
    header("Location: admin_dashboard.php");
    exit;
}

// Kullanıcı kimlik doğrulaması
if (!verifyUserIdentity()) {
    // Şüpheli durum tespit edildi, oturumu sonlandır
    logout();
    exit;
}

$message = '';
$disableForm = false;

// Gerekli tabloları kontrol et ve oluştur
checkRequiredTables($conn);

// POST talebini işleme - form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF token kontrolü
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = "Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.";
    } 
    // Yeni kod gönderme işlemi - En önce kontrol ediyoruz
    elseif (isset($_POST['resend_code'])) {
        $timePassed = isset($_SESSION['verification_start_time']) ? (time() - $_SESSION['verification_start_time']) : 0;
        
        if ($timePassed < 60) { // 1 dakikadan az zaman geçtiyse yeni kod göndermeyi engelle
            $message = "Yeni kod göndermek için " . (60 - $timePassed) . " saniye beklemelisiniz.";
            
            // Kullanıcı aktivite log kaydı
            logUserActivity($conn, $_SESSION['user_id'], 'admin', 'basarisiz', 'Yeni kod gönderme talebi çok erken: ' . (60 - $timePassed) . ' saniye kaldı');
        } else {
            // SMS gönderme fonksiyonunu çağır
            $smsResult = sendVerificationSMS($conn, $_SESSION['user_id'], 'admin');
            
            if ($smsResult['success']) {
                $message = "Yeni doğrulama kodu gönderildi.";
            } else {
                $message = "SMS gönderiminde bir sorun oluştu: " . $smsResult['message'];
                $disableForm = true;
            }
        }
    }
    // Doğrulama kodu kontrolü
    elseif (isset($_POST['verification_code'])) {
        // XSS koruması için filtreleme
        $verificationCode = filter_input(INPUT_POST, 'verification_code', FILTER_SANITIZE_NUMBER_INT);
        
        if (isset($_SESSION['verification_code']) && $verificationCode == $_SESSION['verification_code']) {
            // SMS doğrulama logunu güncelle
            updateSmsVerificationStatus($conn, $_SESSION['user_id'], 'admin', 'dogrulandi');
            
            // Kullanıcı aktivite log kaydı
            logUserActivity($conn, $_SESSION['user_id'], 'admin', 'dogrulama', 'Başarılı doğrulama');
            
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_start_time']);
            
            // SMS doğrulaması başarılı olarak işaretle
            markSmsVerified();
            
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $_SESSION['verify_attempts'] = isset($_SESSION['verify_attempts']) ? $_SESSION['verify_attempts'] + 1 : 1;
            $remainingAttempts = 3 - $_SESSION['verify_attempts'];
            
            // Kullanıcı aktivite log kaydı
            logUserActivity($conn, $_SESSION['user_id'], 'admin', 'basarisiz', 'Hatalı doğrulama kodu. Kalan deneme: ' . $remainingAttempts);
            
            if ($_SESSION['verify_attempts'] >= 3) {
                // SMS doğrulama logunu güncelle
                updateSmsVerificationStatus($conn, $_SESSION['user_id'], 'admin', 'basarisiz');
                
                unset($_SESSION['verification_start_time']);
                unset($_SESSION['verification_code']);
                $message = "Çok fazla hatalı giriş yaptınız. Lütfen tekrar giriş yapın.";
                header("Location: index.php");
                exit;
            } else {
                $message = "Doğrulama kodu hatalı. Kalan deneme hakkı: $remainingAttempts";
            }
        }
    }
}

// Sadece doğrulama kodu yoksa veya sayfaya ilk defa erişiliyorsa SMS gönder
// Bu blok, yalnızca sayfa ilk kez yüklendiğinde ve POST işlemi yoksa çalışacak
if (!isset($_SESSION['verification_start_time']) && $_SERVER['REQUEST_METHOD'] != 'POST') {
    // SMS gönderme fonksiyonunu çağır
    $smsResult = sendVerificationSMS($conn, $_SESSION['user_id'], 'admin');
    
    if (!$smsResult['success']) {
        $message = "SMS gönderiminde bir sorun oluştu: " . $smsResult['message'];
        $disableForm = true;
    }
}

// Süre kontrolü
$timeoutSeconds = 180; // 3 dakika
$timePassed = isset($_SESSION['verification_start_time']) ? (time() - $_SESSION['verification_start_time']) : 0;
$remainingTime = $timeoutSeconds - $timePassed;

if ($timePassed > $timeoutSeconds) {
    // Süre dolduğunda kullanıcı aktivite log kaydı
    logUserActivity($conn, $_SESSION['user_id'], 'admin', 'basarisiz', 'Doğrulama süresi doldu');
    
    // SMS doğrulama logunu güncelle
    updateSmsVerificationStatus($conn, $_SESSION['user_id'], 'admin', 'suresi_doldu');
    
    unset($_SESSION['verification_start_time']);
    unset($_SESSION['verification_code']);
    header("Location: index.php");
    exit;
}

/**
 * Gerekli tabloların varlığını kontrol et, yoksa oluştur
 * @param PDO $conn Veritabanı bağlantısı
 */
function checkRequiredTables($conn) {
    try {
        // sms_log tablosunda kullanici_tipi alanı var mı kontrol et, yoksa ekle
        $stmt = $conn->prepare("SHOW COLUMNS FROM sms_log LIKE 'kullanici_tipi'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("ALTER TABLE `sms_log` ADD COLUMN `kullanici_tipi` ENUM('admin', 'personel') DEFAULT NULL AFTER `kullanici_id`");
        }
        
        // Kullanıcı giriş log tablosu var mı kontrol et, yoksa oluştur
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
        
        // SMS doğrulama log tablosu var mı kontrol et, yoksa oluştur
        $stmt = $conn->prepare("SHOW TABLES LIKE 'sms_dogrulama_log'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `sms_dogrulama_log` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `kullanici_id` int(11) NOT NULL,
                `kullanici_tipi` ENUM('admin', 'personel') NOT NULL,
                `telefon` VARCHAR(20) NOT NULL,
                `dogrulama_kodu` VARCHAR(10) NOT NULL,
                `gonderim_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `dogrulama_tarihi` DATETIME DEFAULT NULL,
                `durum` ENUM('beklemede', 'dogrulandi', 'suresi_doldu', 'basarisiz') DEFAULT 'beklemede',
                `ip_adresi` VARCHAR(45) DEFAULT NULL,
                `detay` TEXT DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_kullanici_id` (`kullanici_id`),
                KEY `idx_kullanici_tipi` (`kullanici_tipi`),
                KEY `idx_telefon` (`telefon`),
                KEY `idx_gonderim_tarihi` (`gonderim_tarihi`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // SMS log tablosundaki yabancı anahtar kısıtlamasını kaldır
        $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                              WHERE CONSTRAINT_SCHEMA = DATABASE() 
                              AND TABLE_NAME = 'sms_log' 
                              AND CONSTRAINT_NAME = 'sms_log_ibfk_1'");
        $stmt->execute();
        $constraintExists = $stmt->fetchColumn();
        
        if ($constraintExists > 0) {
            $conn->exec("ALTER TABLE `sms_log` DROP FOREIGN KEY `sms_log_ibfk_1`");
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
    <title>İnciPOS - Doğrulama</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        let remainingTime = <?php echo $remainingTime; ?>;
        function countdown() {
            if (remainingTime <= 0) {
                window.location.href = 'index.php';
            } else {
                document.getElementById('remaining-time').innerText = remainingTime;
                remainingTime--;
                setTimeout(countdown, 1000);
            }
        }
        window.onload = countdown;
    </script>
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h1 class="login-title">Doğrulama</h1>
                <p class="login-subtitle">Lütfen telefonunuza gönderilen kodu girin</p>
            </div>
            
            <?php if ($message): ?>
                <div class="login-message <?php echo strpos($message, 'hata') !== false || strpos($message, 'sorun') !== false ? 'error-message' : 'success-message'; ?>">
                    <i class="fas <?php echo strpos($message, 'hata') !== false || strpos($message, 'sorun') !== false ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($disableForm): ?>
                <div class="alert-warning">
                    <p>SMS doğrulama işlemi yapılamadı. Lütfen yönetici ile iletişime geçin veya tekrar giriş yapmayı deneyin.</p>
                    <a href="index.php" class="login-button mt-3">
                        <i class="fas fa-arrow-left"></i> GİRİŞ SAYFASINA DÖN
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="verify.php" class="login-form">
                    <!-- CSRF token ekle -->
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="verification_code"><i class="fas fa-key"></i> Doğrulama Kodu</label>
                        <input type="text" name="verification_code" id="verification_code" class="login-input" placeholder="6 haneli kodu girin" required>
                    </div>
                    
                    <div class="verification-timer">
                        <i class="fas fa-clock"></i> Kalan süre: <span id="remaining-time" class="countdown"><?php echo $remainingTime; ?></span> saniye
                    </div>
                    
                    <button type="submit" class="login-button">
                        <i class="fas fa-check-circle"></i> DOĞRULA
                    </button>
                </form>

                <form method="POST" action="verify.php" class="mt-3">
                    <!-- CSRF token ekle (ayrı form için) -->
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="resend_code" value="1">
                    
                    <button type="submit" class="secondary-button w-full">
                        <i class="fas fa-sync"></i> Yeni Kod Gönder
                    </button>
                </form>
                
                <a href="index.php" class="back-button mt-3 block text-center">
                    <i class="fas fa-arrow-left"></i> GERİ DÖN
                </a>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['verification_code']) && ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1')): ?>
                <div class="debug-code">
                    <p>Geliştirme modu: <strong><?php echo $_SESSION['verification_code']; ?></strong></p>
                    <p>Telefon: <strong><?php echo isset($_SESSION['phone']) ? $_SESSION['phone'] : 'Bulunamadı'; ?></strong></p>
                </div>
            <?php endif; ?>
            
            <div class="login-footer">
                <p>© <?php echo date('Y'); ?> İnciPOS - Tüm Hakları Saklıdır</p>
            </div>
        </div>
    </div>
</body>
</html>