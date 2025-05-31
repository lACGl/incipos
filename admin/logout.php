<?php
// Gerekli dosyaları dahil et
require_once 'session_manager.php';
require_once 'db_connection.php';

// Fonksiyon tanımlaması - logUserActivity
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

// Güvenli oturumu başlat
secure_session_start();

// Kullanıcı ID'sini ve tipini alıp kaydet (çıkış loglaması için)
$user_id = $_SESSION['user_id'] ?? null;
$user_type = 'admin'; // Bu dosya admin panelinde olduğundan sabit değer
$kullanici_adi = $_SESSION['kullanici_adi'] ?? 'Bilinmeyen';

// Çıkış log kaydı tutma işlemi
if ($user_id) {
    try {
        // Çıkış kaydı
        logUserActivity($conn, $user_id, $user_type, 'cikis', $kullanici_adi . ' kullanıcısı güvenli çıkış yaptı.');
    } catch (PDOException $e) {
        error_log('Çıkış logu kaydedilirken hata: ' . $e->getMessage());
    }
}

// Logout fonksiyonunu kullan
logout();

// Eğer logout() fonksiyonu çalışmazsa, manuel olarak oturumu sonlandır
if (session_status() === PHP_SESSION_ACTIVE) {
    // Oturum değişkenlerini temizle
    $_SESSION = array();
    
    // Oturum çerezini sil
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    
    // Oturumu sonlandır
    session_destroy();
    
    // Anasayfaya yönlendir
    header("Location: index.php");
    exit;
}
?>