<?php
/**
 * Session Manager
 * Oturum yönetimi ve yetkilendirme için yardımcı fonksiyonlar
 */

// Session başlatılmadıysa başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kullanıcı oturum kontrolü yapar
 * Yetkisiz erişim durumunda login sayfasına yönlendirir
 * @return bool Oturum aktif mi?
 */
function checkUserSession() {
    // Session süresi kontrolü (24 saat = 86400 saniye)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 86400)) {
        // 24 saat geçtiyse oturumu sonlandır
        logout();
        return false;
    }
    
    // Oturum açık değilse yönlendir
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: index.php");
        exit();
    }
    
    // SMS doğrulaması gerekli mi kontrol et
    if (!isset($_SESSION['sms_verified']) || $_SESSION['sms_verified'] !== true) {
        // SMS doğrulamasını geçmemiş, verify.php'ye yönlendir
        header("Location: verify.php");
        exit();
    }
    
    // Son aktivite zamanını güncelle
    $_SESSION['last_activity'] = time();
    
    return true;
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
    $_SESSION['last_activity'] = time();
}