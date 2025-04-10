<?php
session_start();
require_once 'db_connection.php'; // Veritabanı bağlantısı dosyanız
require_once 'session_manager.php'; // Session yönetim fonksiyonları

// Eğer kullanıcı zaten giriş yapmış ve SMS doğrulaması tamamlanmışsa dashboard'a yönlendir
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] && isset($_SESSION['sms_verified']) && $_SESSION['sms_verified']) {
    // Session süresini kontrol et (24 saat = 86400 saniye)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] <= 86400)) {
        // Session hala geçerli, dashboard'a yönlendir
        $_SESSION['last_activity'] = time(); // Son aktivite zamanını güncelle
        header("Location: admin_dashboard.php");
        exit;
    }
}

function sendSms($phoneNumber, $message) {
    $userCode = '4526060578'; // Netgsm kullanıcı kodu
    $password = 'M1-43nvE';    // Netgsm şifresi
    $msgHeader = 'INCIKIRTSYE'; // Mesaj başlığı
    $filter = '0';             // Ticari olmayan bilgilendirme mesajları için

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.netgsm.com.tr/sms/send/get',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'usercode' => $userCode,
            'password' => $password,
            'gsmno' => $phoneNumber,
            'message' => $message,
            'msgheader' => $msgHeader,
            'filter' => $filter
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    return $response; // Yanıtı döndür
}

$message = '';
$disableForm = false;

// Hatalı giriş denemeleri için oturum değişkenlerini başlatma
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Giriş engelleme süresi kontrolü
if (isset($_SESSION['block_time'])) {
    $timePassed = time() - $_SESSION['block_time'];
    if ($timePassed < 3600) { // 3600 saniye = 60 dakika
        $remainingTime = 3600 - $timePassed;
        $message = "Henüz giriş yapamazsınız. Kalan süre: " . ceil($remainingTime / 60) . " dakika.";
        $disableForm = true; // Formu devre dışı bırak
    } else {
        unset($_SESSION['block_time']);
        unset($_SESSION['login_attempts']);
    }
}

// Giriş yapma işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$disableForm) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Kullanıcı adı ve şifre kontrolü
    $query = "SELECT * FROM admin_user WHERE kullanici_adi = :kullanici_adi LIMIT 1";  // PDO uyumlu sorgu
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':kullanici_adi', $username, PDO::PARAM_STR);  // PDO'da bindParam kullanımı
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);  // PDO ile veri çekme

    if ($result) {
        $user = $result;
        if (password_verify($password, $user['sifre'])) {
            // Giriş başarılı
            $_SESSION['logged_in'] = true;
            $_SESSION['kullanici_adi'] = $username;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['last_activity'] = time(); // Aktivite zamanını kaydet
            
            // SMS kontrolü yapılacak mı? Son 24 saatte doğrulama yapılmış mı?
            $needSmsVerification = true;
            
            if (isset($_SESSION['sms_verified']) && $_SESSION['sms_verified'] && 
                isset($_SESSION['last_sms_verification']) && 
                (time() - $_SESSION['last_sms_verification'] < 86400)) {
                // Son 24 saat içinde SMS doğrulaması yapılmış
                $needSmsVerification = false;
            }
            
            if ($needSmsVerification) {
                // SMS doğrulaması gerekiyor
                $phone = $user['telefon_no']; // Kullanıcı telefon numarası
                $verificationCode = rand(100000, 999999); // 6 haneli doğrulama kodu
                $_SESSION['verification_code'] = $verificationCode;
                
                // SMS gönder
                sendSms($phone, "Doğrulama kodunuz: $verificationCode");
                
                // Yönlendirme
                header("Location: verify.php");
                exit;
            } else {
                // SMS doğrulamasına gerek yok, direkt dashboard'a yönlendir
                header("Location: admin_dashboard.php");
                exit;
            }
        } else {
            // Yanlış şifre
            $_SESSION['login_attempts']++;
            $message = "Kullanıcı adı veya şifre hatalı.";
            if ($_SESSION['login_attempts'] >= 3) {
                $message = "3 kez hatalı giriş yaptınız. Lütfen 60 dakika bekleyin.";
                // Kullanıcının giriş yapmasını 60 dakika boyunca engelleme
                $_SESSION['block_time'] = time();
            }
        }
    } else {
        // Kullanıcı bulunamadı
        $_SESSION['login_attempts']++;
        $message = "Kullanıcı adı veya şifre hatalı.";
        if ($_SESSION['login_attempts'] >= 3) {
            $message = "3 kez hatalı giriş yaptınız. Lütfen 60 dakika bekleyin.";
            $_SESSION['block_time'] = time();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Sistemi - Admin Giriş</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="login-page">
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-cash-register"></i>
                </div>
                <h1 class="login-title">İnciPOS</h1>
                <p class="login-subtitle">Yönetim Paneli Girişi</p>
            </div>
            
            <?php if ($message): ?>
                <div class="login-message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php" class="login-form">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Kullanıcı Adı</label>
                    <input type="text" name="username" id="username" class="login-input" required>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Şifre</label>
                    <input type="password" name="password" id="password" class="login-input" required>
                </div>
                <button type="submit" class="login-button" <?php echo $disableForm ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> GİRİŞ YAP
                </button>
            </form>
            
            <div class="login-footer">
                <p>© <?php echo date('Y'); ?> İnciPOS - Tüm Hakları Saklıdır</p>
            </div>
        </div>
    </div>
</body>
</html>