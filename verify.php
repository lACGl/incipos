<?php
// Hata ayıklama modunu etkinleştir (canlıda kapatın)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Oturum güvenlik ayarları - session_start() ÖNCESİNDE olmalı
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Geliştirme için 0, canlı için 1
ini_set('session.cookie_samesite', 'Lax');

// Sonra session'ı başlat
session_start();

require_once 'admin/db_connection.php'; // Veritabanı bağlantısını sağlayın
require_once 'admin/netgsm_helper.php'; // NetGSM yardımcı sınıfı

// Gerekli tablolar var mı kontrol et ve yoksa oluştur
checkRequiredTables($conn);

// Sistem ayarlarını al
function getSistemAyari($conn, $anahtar, $varsayilan = '') {
    try {
        $stmt = $conn->prepare("SELECT deger FROM sistem_ayarlari WHERE anahtar = ?");
        $stmt->execute([$anahtar]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['deger'] : $varsayilan;
    } catch (PDOException $e) {
        error_log("Sistem ayarı okuma hatası: " . $e->getMessage());
        return $varsayilan;
    }
}

// reCAPTCHA v3 anahtarlarını veritabanından al
$recaptchaSiteKey = getSistemAyari($conn, 'recaptcha_site_key', '6LcoLDQrAAAAAD86dvxMOm4KYtfAa4bxAeQHOODw'); 
$recaptchaSecretKey = getSistemAyari($conn, 'recaptcha_secret_key', '6LcoLDQrAAAAAD-eT_JtLjSw_TU-rBsFq2li-5lf');

// Ban durumu kontrolü
function checkUserBanned($conn, $userId, $userType) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM kullanici_ban 
            WHERE kullanici_id = ? AND kullanici_tipi = ? AND ban_bitis > NOW()
        ");
        $stmt->execute([$userId, $userType]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Ban kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

// Ban ekleme fonksiyonu
function banUser($conn, $userId, $userType, $sebep, $ipAdresi) {
    try {
        // Ban bitiş saati (şu anki zaman + 12 saat)
        $banBitis = date('Y-m-d H:i:s', strtotime('+12 hours'));
        
        $stmt = $conn->prepare("
            INSERT INTO kullanici_ban 
            (kullanici_id, kullanici_tipi, ban_baslangic, ban_bitis, sebep, ip_adresi) 
            VALUES (?, ?, NOW(), ?, ?, ?)
        ");
        $stmt->execute([$userId, $userType, $banBitis, $sebep, $ipAdresi]);
        
        // Kullanıcı aktivite log kaydı
        logUserActivity($conn, $userId, $userType, 'basarisiz', 'Hesap 12 saat banlandı: ' . $sebep);
        
        return true;
    } catch (PDOException $e) {
        error_log("Ban ekleme hatası: " . $e->getMessage());
        return false;
    }
}

// reCAPTCHA v3 doğrulama fonksiyonu - file_get_contents yerine cURL kullanır
function verifyRecaptchaV3($secretKey, $recaptchaResponse, $minScore = 0.3) {
    if (empty($recaptchaResponse)) {
        error_log("reCAPTCHA yanıtı boş");
        return false;
    }
    
    // cURL kullanarak Google reCAPTCHA API'sine istek gönder
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secretKey,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL doğrulaması ile sorun yaşanırsa
    
    $result = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($result === FALSE) {
        error_log("reCAPTCHA API isteği başarısız oldu: " . $curlError);
        return false;
    }
    
    $resultData = json_decode($result, true);
    error_log("reCAPTCHA yanıtı: " . $result);
    
    // Skor kontrolü (0.0 - 1.0 arasında, 1.0 en güvenilir)
    if (isset($resultData['success']) && $resultData['success'] === true) {
        if (isset($resultData['score'])) {
            $score = $resultData['score'];
            error_log("reCAPTCHA skoru: " . $score);
            
            if ($score >= $minScore) {
                return true;
            } else {
                error_log("reCAPTCHA skoru çok düşük: " . $score . " (minimum: " . $minScore . ")");
            }
        } else {
            error_log("reCAPTCHA yanıtında skor bulunamadı");
        }
    } else {
        $errorCodes = isset($resultData['error-codes']) ? implode(', ', $resultData['error-codes']) : 'Bilinmeyen hata';
        error_log("reCAPTCHA doğrulama başarısız oldu: " . $errorCodes);
    }
    
    return false;
}

// Önce header yönlendirmelerini yapmadan önce içerik çıktısı olup olmadığını kontrol et
$outputStarted = false;
$bufferContents = '';

// Output buffer başlat
ob_start();

// Kullanıcı oturum açmamışsa giriş sayfasına yönlendir
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (!$outputStarted) {
        header("Location: login.php");
        exit;
    }
}

// Mağaza seçilmemiş ise yönlendir
if (!isset($_SESSION['magaza_id'])) {
    // Kasiyer ise login sayfasına, yönetici ise mağaza seçimine yönlendir
    if (!$outputStarted) {
        if ($_SESSION['yetki'] == 'kasiyer') {
            header("Location: login.php");
        } else {
            header("Location: select_store.php");
        }
        exit;
    }
}

// Ban durumunu kontrol et
$userBanned = checkUserBanned($conn, $_SESSION['user_id'], $_SESSION['yetki']);
if ($userBanned && !$outputStarted) {
    // Kalan ban süresini hesapla
    $banBitis = new DateTime($userBanned['ban_bitis']);
    $now = new DateTime();
    $interval = $now->diff($banBitis);
    
    $kalanSure = '';
    if ($interval->h > 0) {
        $kalanSure .= $interval->h . ' saat ';
    }
    if ($interval->i > 0) {
        $kalanSure .= $interval->i . ' dakika';
    }
    
    // Session bilgilerini temizle
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    
    // Ban sayfasına yönlendir ve kalan süreyi göster
    header("Location: login.php?banned=1&time=" . urlencode($kalanSure));
    exit;
}

$message = '';
$disableForm = false;
$recaptchaDebug = []; // reCAPTCHA hata ayıklama bilgisi

// Doğrulama kodu ve süre başlatma
if (!isset($_SESSION['verification_start_time'])) {
    $_SESSION['verification_start_time'] = time();
    $_SESSION['verification_code'] = rand(100000, 999999); // 6 haneli kod
    $_SESSION['verify_attempts'] = 0; // Hatalı girişleri sıfırla
    
    // Kasiyerin telefon numarasını al
    $phoneNumber = $_SESSION['user_phone'] ?? '';
    
    if (empty($phoneNumber)) {
        // Telefon numarası kaydedilmemişse veya boş ise veritabanından al
        try {
            if ($_SESSION['yetki'] == 'kasiyer' || $_SESSION['yetki'] == 'personel') {
                $stmt = $conn->prepare("SELECT telefon_no FROM personel WHERE id = ?");
            } else {
                $stmt = $conn->prepare("SELECT telefon_no FROM admin_user WHERE id = ?");
            }
            
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['telefon_no'])) {
                $phoneNumber = $result['telefon_no'];
                $_SESSION['user_phone'] = $phoneNumber; // Session'a kaydet
            }
        } catch (PDOException $e) {
            $message = "Veritabanı hatası: " . $e->getMessage();
            $disableForm = true;
            error_log("Veritabanı hatası: " . $e->getMessage());
            
            // Hata durumunda kullanıcı giriş log kaydı
            logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'basarisiz', 'Telefon numarası sorgulama hatası: ' . $e->getMessage());
        }
    }
    
    if (empty($phoneNumber)) {
        $message = "Telefon numarası bulunamadı. Lütfen profilinizde telefon numaranızı tanımlayın veya yönetici ile iletişime geçin.";
        $disableForm = true;
        
        // Hata durumunda kullanıcı giriş log kaydı
        logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'basarisiz', 'Telefon numarası bulunamadı');
    } else {
        // NetGSM ile SMS gönderimi
        $netgsm = new NetGSMHelper($conn);
        $result = $netgsm->sendVerificationSMS($phoneNumber, $_SESSION['verification_code']);
        
        // Kullanıcı aktivite log kaydı
        logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'dogrulama', 'SMS gönderimi: ' . ($result['success'] ? 'Başarılı' : 'Başarısız - ' . ($result['message'] ?? 'Bilinmeyen hata')));
        
        if (!$result['success']) {
            $message = "SMS gönderiminde bir sorun oluştu: " . $result['message'];
            $disableForm = true;
        }
    }
}

$timePassed = time() - $_SESSION['verification_start_time'];
$timeoutSeconds = 180; // 3 dakika

if ($timePassed > $timeoutSeconds && !$outputStarted) {
    // Süre dolduğunda kullanıcı aktivite log kaydı
    logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'basarisiz', 'Doğrulama süresi doldu');
    
    // SMS doğrulama logunu güncelle
    updateSmsVerificationStatus($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'suresi_doldu');
    
    // Session bilgilerini temizle
    unset($_SESSION['verification_start_time']);
    unset($_SESSION['verification_code']);
    header("Location: login.php");
    exit;
}

// DEBUG: POST verilerini kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log('POST verisi: ' . json_encode($_POST));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verification_code'])) {
        // reCAPTCHA kontrolü - hata ayıklama amacıyla şimdilik geçici olarak devre dışı bırak
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        
        if (empty($recaptchaResponse)) {
            error_log("reCAPTCHA yanıtı bulunamadı");
            $recaptchaDebug[] = "reCAPTCHA yanıtı bulunamadı. Form, g-recaptcha-response alanı içermiyor.";
            $recaptchaValid = false;
        } else {
            error_log("reCAPTCHA yanıtı mevcut, doğrulanıyor: " . substr($recaptchaResponse, 0, 20) . "...");
            $recaptchaValid = verifyRecaptchaV3($recaptchaSecretKey, $recaptchaResponse, 0.3); // Eşik değeri 0.3'e düşürüldü
            
            if (!$recaptchaValid) {
                $recaptchaDebug[] = "reCAPTCHA doğrulaması başarısız oldu. Skor çok düşük veya hata oluştu.";
            }
        }
        
        if (!$recaptchaValid) {
            $message = "Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.";
            // Başarısız reCAPTCHA log kaydı
            logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'basarisiz', 'reCAPTCHA doğrulaması başarısız');
        } else {
            $verificationCode = $_POST['verification_code'];
            if ($verificationCode == $_SESSION['verification_code']) {
                // SMS doğrulama logunu güncelle
                updateSmsVerificationStatus($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'dogrulandi');
                
                // Kullanıcı aktivite log kaydı
                logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'dogrulama', 'Başarılı doğrulama');
                
                // Session bilgilerini temizle
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_start_time']);
                
                // SMS doğrulaması başarılı olarak işaretle
                $_SESSION['verified'] = true;
                
                // POS sayfasına yönlendir
                if (!$outputStarted) {
                    header("Location: pos.php");
                    exit;
                }
            } else {
                $_SESSION['verify_attempts'] = isset($_SESSION['verify_attempts']) ? $_SESSION['verify_attempts'] + 1 : 1;
                $remainingAttempts = 3 - $_SESSION['verify_attempts'];
                
                // Kullanıcı aktivite log kaydı
                logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'basarisiz', 'Hatalı doğrulama kodu. Kalan deneme: ' . $remainingAttempts);
                
                if ($_SESSION['verify_attempts'] >= 3 && !$outputStarted) {
                    // SMS doğrulama logunu güncelle
                    updateSmsVerificationStatus($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'basarisiz');
                    
                    // Kullanıcıyı banla
                    banUser($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'Çok fazla hatalı SMS doğrulama kodu girişi', $_SERVER['REMOTE_ADDR']);
                    
                    // Session bilgilerini temizle
                    unset($_SESSION['verification_start_time']);
                    unset($_SESSION['verification_code']);
                    
                    // Giriş sayfasına yönlendir ve ban bilgisini göster
                    header("Location: login.php?banned=1&time=12 saat");
                    exit;
                } else {
                    $message = "Doğrulama kodu hatalı. Kalan deneme hakkı: $remainingAttempts";
                }
            }
        }
    }
}

// Yeni kod gönderme işlemi
if (isset($_POST['resend_code'])) {
    // reCAPTCHA v3 kontrolü - geçici olarak atla
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
    
    if (empty($recaptchaResponse)) {
        error_log("Yeni kod: reCAPTCHA yanıtı bulunamadı");
        $recaptchaDebug[] = "Yeni kod: reCAPTCHA yanıtı bulunamadı. Form, g-recaptcha-response alanı içermiyor.";
        $recaptchaValid = false;
    } else {
        error_log("Yeni kod: reCAPTCHA yanıtı mevcut, doğrulanıyor: " . substr($recaptchaResponse, 0, 20) . "...");
        $recaptchaValid = verifyRecaptchaV3($recaptchaSecretKey, $recaptchaResponse, 0.3);
        
        if (!$recaptchaValid) {
            $recaptchaDebug[] = "Yeni kod: reCAPTCHA doğrulaması başarısız oldu. Skor çok düşük veya hata oluştu.";
        }
    }
    
    // GEÇİCİ: Geliştirme için reCAPTCHA kontrolünü atlayın - CANLIYA GEÇMEDEN ÖNCE KALDIR!
    $recaptchaValid = true;
    
    if (!$recaptchaValid) {
        $message = "Güvenlik doğrulaması başarısız oldu. Lütfen tekrar deneyin.";
        // Başarısız reCAPTCHA log kaydı
        logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'basarisiz', 'reCAPTCHA doğrulaması başarısız (yeni kod gönderme)');
    } else {
        if ($timePassed < 60) { // 1 dakikadan az zaman geçtiyse yeni kod göndermeyi engelle
            $message = "Yeni kod göndermek için " . (60 - $timePassed) . " saniye beklemelisiniz.";
            
            // Kullanıcı aktivite log kaydı
            logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'basarisiz', 'Yeni kod gönderme talebi çok erken: ' . (60 - $timePassed) . ' saniye kaldı');
        } else {
            $_SESSION['verification_code'] = rand(100000, 999999); // Yeni kod oluştur
            
            // Kasiyerin telefon numarasını al
            $phoneNumber = $_SESSION['user_phone'];
            
            // NetGSM ile SMS gönderimi
            $netgsm = new NetGSMHelper($conn);
            $result = $netgsm->sendVerificationSMS($phoneNumber, $_SESSION['verification_code']);
            
            // Kullanıcı aktivite log kaydı
            logUserActivity($conn, $_SESSION['user_id'], $_SESSION['yetki'], 'dogrulama', 'Yeni SMS gönderimi: ' . ($result['success'] ? 'Başarılı' : 'Başarısız - ' . ($result['message'] ?? 'Bilinmeyen hata')));
            
            if ($result['success']) {
                $message = "Yeni doğrulama kodu gönderildi.";
                $_SESSION['verification_start_time'] = time(); // Süreyi sıfırla
            } else {
                $message = "SMS gönderiminde bir sorun oluştu: " . $result['message'];
                $disableForm = true;
            }
        }
    }
}

$remainingTime = $timeoutSeconds - $timePassed;

// Kullanıcı telefon numarasını maskeleme (Son 3 rakamı göster)
$maskedPhone = '';
if (!empty($_SESSION['user_phone'])) {
    $phone = $_SESSION['user_phone'];
    $len = strlen($phone);
    if ($len > 3) {
        $maskedPhone = str_repeat('*', $len - 3) . substr($phone, -3);
    } else {
        $maskedPhone = $phone;
    }
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
        
        // Ban tablosu var mı kontrol et, yoksa oluştur
        $stmt = $conn->prepare("SHOW TABLES LIKE 'kullanici_ban'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `kullanici_ban` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `kullanici_id` int(11) NOT NULL,
                `kullanici_tipi` ENUM('admin', 'personel') NOT NULL,
                `ban_baslangic` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ban_bitis` DATETIME NOT NULL,
                `sebep` VARCHAR(255) NOT NULL,
                `ip_adresi` VARCHAR(45) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_kullanici_id` (`kullanici_id`),
                KEY `idx_kullanici_tipi` (`kullanici_tipi`),
                KEY `idx_ban_bitis` (`ban_bitis`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // Sistem ayarları tablosu var mı kontrol et, yoksa oluştur
        $stmt = $conn->prepare("SHOW TABLES LIKE 'sistem_ayarlari'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            $conn->exec("CREATE TABLE `sistem_ayarlari` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `anahtar` VARCHAR(50) NOT NULL,
                `deger` TEXT DEFAULT NULL,
                `aciklama` VARCHAR(255) DEFAULT NULL,
                `guncelleme_tarihi` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `anahtar` (`anahtar`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        // Sistem ayarları tablosunda reCAPTCHA anahtarları var mı kontrol et, yoksa ekle
        $stmt = $conn->prepare("SELECT COUNT(*) FROM sistem_ayarlari WHERE anahtar = 'recaptcha_site_key'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $stmt = $conn->prepare("INSERT INTO sistem_ayarlari (anahtar, deger, aciklama) VALUES (?, ?, ?)");
            $stmt->execute(['recaptcha_site_key', '6LcoLDQrAAAAAD86dvxMOm4KYtfAa4bxAeQHOODw', 'Google reCAPTCHA site anahtarı']);
            $stmt->execute(['recaptcha_secret_key', '6LcoLDQrAAAAAD-eT_JtLjSw_TU-rBsFq2li-5lf', 'Google reCAPTCHA gizli anahtarı']);
        }
        
        // SMS log tablosundaki yabancı anahtar kısıtlamasını kaldır
        $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
                              WHERE CONSTRAINT_SCHEMA = DATABASE() 
                              AND TABLE_NAME = 'sms_log' 
                              AND CONSTRAINT_NAME = 'sms_log_ibfk_1'");
        $stmt->execute();
        $constraintExists = $stmt->fetchColumn();
        
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

/**
 * Kullanıcı giriş/aktivite log kayıt fonksiyonu
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $kullaniciId Kullanıcı ID
 * @param string $kullaniciTipi Kullanıcı tipi (admin/personel)
 * @param string $islemTipi İşlem tipi (giris/cikis/dogrulama/basarisiz)
 * @param string $detay İşlem detayları
 */
function logUserActivity($conn, $kullaniciId, $kullaniciTipi, $islemTipi, $detay = null) {
    try {
        $stmt = $conn->prepare("
            INSERT INTO kullanici_giris_log 
            (kullanici_id, kullanici_tipi, islem_tipi, ip_adresi, tarayici_bilgisi, detay) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $kullaniciId,
            $kullaniciTipi == 'admin' ? 'admin' : 'personel',
            $islemTipi,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $detay
        ]);
    } catch (PDOException $e) {
        error_log("Kullanıcı giriş log kaydı oluşturulurken hata: " . $e->getMessage());
    }
}

/**
 * SMS doğrulama durumunu güncelleme fonksiyonu
 * @param PDO $conn Veritabanı bağlantısı
 * @param int $kullaniciId Kullanıcı ID
 * @param string $kullaniciTipi Kullanıcı tipi (admin/personel)
 * @param string $durum Doğrulama durumu
 */
function updateSmsVerificationStatus($conn, $kullaniciId, $kullaniciTipi, $durum) {
    try {
        $stmt = $conn->prepare("
            UPDATE sms_dogrulama_log 
            SET durum = ?, dogrulama_tarihi = NOW()
            WHERE kullanici_id = ? AND kullanici_tipi = ? 
            ORDER BY id DESC LIMIT 1
        ");
        
        $stmt->execute([
            $durum,
            $kullaniciId,
            $kullaniciTipi == 'admin' ? 'admin' : 'personel'
        ]);
    } catch (PDOException $e) {
        error_log("SMS doğrulama log kaydı güncellenirken hata: " . $e->getMessage());
    }
}

// Tüm çıktıları temizleyip HTML kodu başlamadan hemen önce buffer'ı bitir
$outputStarted = true;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İnci Kırtasiye - Doğrulama</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- reCAPTCHA v3 için JavaScript -->
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptchaSiteKey); ?>"></script>
    <script>
        // Sayfa yüklendiğinde reCAPTCHA tokenını al
        function executeRecaptcha(action) {
            console.log("executeRecaptcha çağrıldı: " + action);
            return new Promise((resolve, reject) => {
                grecaptcha.ready(function() {
                    console.log("grecaptcha ready");
                    grecaptcha.execute('<?php echo htmlspecialchars($recaptchaSiteKey); ?>', {action: action})
                        .then(function(token) {
                            console.log("Token alındı: " + token.substring(0, 20) + "...");
                            resolve(token);
                        })
                        .catch(function(error) {
                            console.error("Token alınamadı:", error);
                            reject(error);
                        });
                });
            });
        }
        
        // Form gönderiminde reCAPTCHA tokenını ekle
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM yüklendi.");
            
            const forms = document.querySelectorAll('form');
            console.log(forms.length + " form bulundu");
            
            forms.forEach(function(form, index) {
                console.log("Form " + index + " işleniyor");
                
                form.addEventListener('submit', async function(e) {
                    console.log("Form gönderiliyor... Event engellendi");
                    e.preventDefault();
                    
                    try {
                        // Form içinde bir 'resend_code' adında input varsa 'resend' action'ı kullan, yoksa 'verify' kullan
                        const hasResendButton = form.querySelector('button[name="resend_code"]') !== null;
                        const action = hasResendButton ? 'resend' : 'verify';
                        console.log("Action: " + action);
                        
                        const token = await executeRecaptcha(action);
                        console.log("Token alındı ve form için hazırlanıyor");
                        
                        // Hidden input yoksa ekle, varsa güncelle
                        let tokenInput = form.querySelector('input[name="g-recaptcha-response"]');
                        if (!tokenInput) {
                            console.log("Token input bulunamadı, oluşturuluyor");
                            tokenInput = document.createElement('input');
                            tokenInput.type = 'hidden';
                            tokenInput.name = 'g-recaptcha-response';
                            form.appendChild(tokenInput);
                        }
                        
                        tokenInput.value = token;
                        console.log("Token input değeri güncellendi");
                        
                        // Form gönderimini hata ayıklama için geciktir
                        console.log("Form 500ms sonra gönderilecek");
                        setTimeout(() => {
                            console.log("Form gönderiliyor...");
                            form.submit();
                        }, 500);
                    } catch (error) {
                        console.error('reCAPTCHA hatası:', error);
                        alert('Güvenlik doğrulaması sırasında bir hata oluştu. Lütfen sayfayı yenileyin ve tekrar deneyin.');
                    }
                });
            });
        });
        
        // Kalan süre sayacı
        let remainingTime = <?php echo $remainingTime; ?>;
        function countdown() {
            if (remainingTime <= 0) {
                window.location.href = 'login.php';
            } else {
                document.getElementById('remaining-time').innerText = remainingTime;
                remainingTime--;
                setTimeout(countdown, 1000);
            }
        }
        window.onload = function() {
            countdown();
            console.log("Sayaç başlatıldı");
        };
    </script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <div class="text-center mb-8">
            <div class="text-5xl text-blue-500 mb-4">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1 class="text-2xl font-bold text-blue-700">Doğrulama</h1>
            <p class="text-gray-600">
                <?php if (!empty($maskedPhone)): ?>
                    Lütfen <?php echo $maskedPhone; ?> numaralı telefonunuza gönderilen doğrulama kodunu girin
                <?php else: ?>
                    Lütfen telefonunuza gönderilen doğrulama kodunu girin
                <?php endif; ?>
            </p>
        </div>
        
        <?php if ($message): ?>
            <div class="<?php echo strpos($message, 'hata') !== false || strpos($message, 'sorun') !== false ? 'bg-red-100 text-red-700 border-red-400' : 'bg-green-100 text-green-700 border-green-400'; ?> border px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($disableForm): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <p>Doğrulama kodu gönderilemedi. Lütfen yönetici ile iletişime geçin.</p>
                <a href="login.php" class="block mt-4 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center">
                    Giriş Sayfasına Dön
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="" class="space-y-6" id="verification-form">
                <div>
                    <label for="verification_code" class="block text-sm font-medium text-gray-700 mb-1">
                        Doğrulama Kodu
                    </label>
                    <input type="text" name="verification_code" id="verification_code" 
                           class="block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           placeholder="6 haneli kodu girin" inputmode="numeric" required autofocus>
                </div>
                
                <div class="text-center text-gray-600">
                    <i class="fas fa-clock mr-1"></i> Kalan süre: 
                    <span id="remaining-time" class="font-medium text-red-600"><?php echo $remainingTime; ?></span> saniye
                </div>
                
                <button type="submit" class="w-full bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-check-circle mr-2"></i> Doğrula
                </button>
                
                <button type="submit" name="resend_code" class="w-full bg-gray-500 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline">
                    <i class="fas fa-sync mr-2"></i> Yeni Kod Gönder
                </button>
                
                <div class="text-center">
                    <a href="login.php" class="text-blue-500 hover:text-blue-700">
                        <i class="fas fa-arrow-left mr-1"></i> Giriş Sayfasına Dön
                    </a>
                </div>
                
                <!-- reCAPTCHA v3 doğrulama için gizli alan, JavaScript tarafından doldurulacak -->
                <input type="hidden" name="g-recaptcha-response" value="">
            </form>
        <?php endif; ?>
        
        <?php if (!empty($recaptchaDebug)): ?>
        <!-- Hata ayıklama bilgileri - CANLIYA GEÇMEDEN ÖNCE KALDIR -->
        <div class="mt-6 p-4 border border-dashed border-red-400 bg-red-50 rounded">
            <p class="text-xs text-red-800 font-medium">reCAPTCHA Hata Ayıklama (CANLIDA KALDIR)</p>
            <ul class="text-xs text-red-700 mt-2 list-disc list-inside">
                <?php foreach ($recaptchaDebug as $debugItem): ?>
                    <li><?php echo htmlspecialchars($debugItem); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>