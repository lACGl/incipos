<?php
/*
 * Güçlendirilmiş Session Manager
 * Oturum yönetimi ve yetkilendirme için güvenli yardımcı fonksiyonlar
 */

/**
 * Güvenli oturum başlatma
 * - Session hijacking önleme
 * - Session fixation önleme
 * - Güvenli cookie ayarları
 * 
 * @return bool Oturum başarıyla başlatıldı mı?
 */
function secure_session_start() {
    // Oturum çerezlerinin güvenli ayarları
    $session_name = 'SECURE_SESSION';  // Varsayılan session ismini değiştir
    $secure = true;  // HTTPS üzerinden çerez gönder
    $httponly = true; // JavaScript ile çerezlere erişimi engelle
    
    // Çerez parametrelerini ayarla
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params(
        $cookieParams["lifetime"],
        $cookieParams["path"],
        $cookieParams["domain"],
        $secure,
        $httponly
    );
    
    // Session adını ayarla
    session_name($session_name);
    
    // Session başlatılmadıysa başlat
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Session fixation saldırılarına karşı - her girişte session ID'yi yenile
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
        // Sadece oturum açıksa ve son yenileme 30 dakikadan eskiyse
        if (!isset($_SESSION['last_regeneration']) || 
            (time() - $_SESSION['last_regeneration'] > 1800)) {
            
            // Mevcut session verilerini sakla
            $old_session_data = $_SESSION;
            
            // Session'ı yeniden başlat
            session_regenerate_id(true);
            
            // Eski verileri yeni session'a aktar
            $_SESSION = $old_session_data;
            
            // Yenileme zamanını kaydet
            $_SESSION['last_regeneration'] = time();
        }
    }
    
    return true;
}

// Oturum süresi (8 saat = 28800 saniye)
define('SESSION_LIFETIME', 28800); // 8 saat

// İnaktivite: Uzun mola/toplantı süresini kapsar
$inactivity_timeout = 10800; // 3 saat

/**
 * IP ve tarayıcı kontrolü yapar
 * Session hijacking koruması sağlar
 * 
 * @return bool Kullanıcı bilgileri doğru mu?
 */
function verifyUserIdentity() {
    if (!isset($_SESSION['user_ip']) || !isset($_SESSION['user_agent'])) {
        return false;
    }
    
    // IP ve User-Agent kontrolü
    $current_ip = $_SERVER['REMOTE_ADDR'];
    $current_agent = $_SERVER['HTTP_USER_AGENT'];
    
    // IP ve User-Agent değişmiş mi kontrol et
    if ($_SESSION['user_ip'] !== $current_ip || $_SESSION['user_agent'] !== $current_agent) {
        // Şüpheli durum tespit edildi, oturumu sonlandır
        logout();
        return false;
    }
    
    return true;
}

/**
 * Kullanıcı oturum kontrolü yapar
 * Yetkisiz erişim durumunda login sayfasına yönlendirir
 * 
 * @return bool Oturum aktif mi?
 */
function checkUserSession() {
    // Kimlik doğrulama kontrolü
    if (!verifyUserIdentity()) {
        return false;
    }
    
    // GELİŞTİRİCİ MODU KONTROLÜ - Sadece kullanıcı adı bazlı
    $developer_usernames = [
        'admin',        // Ana admin
        'developer',    // Geliştirici hesabı
        'test',         // Test hesabı
        // Kendi kullanıcı adını buraya ekle
        // 'SENIN_KULLANICI_ADIN'
    ];
    
    // Localhost kontrolü (IP kontrolü olmadan)
    $is_localhost = (
        $_SERVER['SERVER_NAME'] == 'localhost' || 
        $_SERVER['SERVER_NAME'] == '127.0.0.1' ||
        strpos($_SERVER['HTTP_HOST'], 'localhost') !== false
    );
    
    // Geliştirici modu aktif mi?
    $is_developer_mode = (
        $is_localhost || 
        (isset($_SESSION['kullanici_adi']) && in_array($_SESSION['kullanici_adi'], $developer_usernames))
    );
    
    // ÇALIŞMA SAATLERİ KONTROLÜ
    $current_hour = (int)date('H');
    $is_work_hours = ($current_hour >= 8 && $current_hour <= 20); // 08:00 - 20:00
    
    // Session süresi kontrolü
    if ($is_developer_mode) {
        $session_timeout = 86400; // Geliştirici için 24 saat
    } else {
        $session_timeout = SESSION_LIFETIME; // Normal kullanıcı için varsayılan (12 saat)
    }
    
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $session_timeout)) {
        logout();
        return false;
    }
    
    // İNAKTİVİTE KONTROLÜ
    if ($is_developer_mode) {
        // Geliştirici için çok uzun süre (8 saat)
        $inactivity_timeout = 28800;
    } elseif ($is_work_hours) {
        // Normal kullanıcılar için çalışma saatlerinde 3 saat
        $inactivity_timeout = 10800;
    } else {
        // Normal kullanıcılar için çalışma saatleri dışında 1 saat
        $inactivity_timeout = 3600;
    }
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivity_timeout)) {
        if ($is_developer_mode) {
            // Geliştirici için sadece uyarı log, çıkış yapma
            $hours_inactive = round((time() - $_SESSION['last_activity'])/3600, 1);
            error_log("DEVELOPER MODE: İnaktivite süresi aşıldı ({$hours_inactive} saat) ama oturum korunuyor. Kullanıcı: " . $_SESSION['kullanici_adi']);
            
            // İsteğe bağlı: Çok uzun süre (12+ saat) inaktif kalırsa bile çıkış yapma
            // Sadece log tut
        } else {
            // Normal kullanıcılar için çıkış yap
            logout();
            exit;
        }
    }
    
    // Oturum açık değilse yönlendir
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: index.php");
        exit();
    }
    
    // Hangi sayfada olduğumuzu kontrol et
    $current_page = basename($_SERVER['PHP_SELF']);
    
    // Eğer zaten verify.php sayfasındaysak yönlendirme yapma
    if ($current_page == 'verify.php') {
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    // SMS doğrulaması gerekli mi kontrol et
    if (!isset($_SESSION['sms_verified']) || $_SESSION['sms_verified'] !== true) {
        header("Location: verify.php");
        exit();
    }
    
    // Son aktivite zamanını güncelle
    $_SESSION['last_activity'] = time();
    
    // Debug bilgisi (sadece geliştirici modunda ve localhost'ta)
    if ($is_developer_mode && $is_localhost) {
        $time_since_login = round((time() - $_SESSION['login_time'])/3600, 1);
        $time_since_activity = round((time() - $_SESSION['last_activity'])/60, 1);
        error_log("🔧 DEVELOPER SESSION: Login {$time_since_login}h ago | Activity {$time_since_activity}m ago | User: {$_SESSION['kullanici_adi']}");
    }
    
    return true;
}

/**
 * CSRF token oluşturur ve oturuma kaydeder
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token doğrulaması yapar
 * 
 * @param string $token Kontrol edilecek token
 * @return bool Token geçerli mi?
 */
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    
    return true;
}

/**
 * Kullanıcı giriş işlemi
 * 
 * @param int $user_id Kullanıcı ID
 * @param string $username Kullanıcı adı
 */
function loginUser($user_id, $username) {
    // Eski session verilerini temizle
    $_SESSION = array();
    
    // Session ID'yi yenile - session fixation koruması
    session_regenerate_id(true);
    
    // Kullanıcı bilgilerini kaydet
    $_SESSION['logged_in'] = true;
    $_SESSION['kullanici_adi'] = $username;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['login_time'] = time(); // Giriş yapılan zaman
    $_SESSION['last_activity'] = time();
    $_SESSION['last_regeneration'] = time();
    
    // Kullanıcı kimlik bilgilerini kaydet - session hijacking koruması
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    
    // İlk CSRF token'ı oluştur
    generateCSRFToken();
}

/**
 * Kullanıcı çıkış işlemini gerçekleştirir
 */
function logout() {
    // Oturum değişkenlerini temizle
    $_SESSION = array();
    
    // Session cookie'sini sil
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Oturumu sonlandır
    session_destroy();
    
    // Giriş sayfasına yönlendir
    header("Location: index.php");
    exit();
}

/**
 * SMS doğrulaması tamamlandı olarak işaretle
 */
function markSmsVerified() {
    $_SESSION['sms_verified'] = true;
    $_SESSION['last_sms_verification'] = time();
}

/**
 * İşlem yapılabilecek maksimum süre kontrolü
 * Belirli bir süre sonra tekrar kimlik doğrulama gerektirir
 * 
 * @param int $max_time Maksimum süre (saniye)
 * @return bool Süre geçerli mi?
 */
function checkActionTimeout($max_time = 600) { // Varsayılan 10 dakika
    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > $max_time)) {
        // Süre aşıldı, yeniden doğrulama gerekiyor
        return false;
    }
    return true;
}