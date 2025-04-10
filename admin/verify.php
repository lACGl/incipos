<?php
session_start();
require_once 'db_connection.php'; // Veritabanı bağlantısını sağlayın
require_once 'session_manager.php'; // Session yönetim fonksiyonları

// Kullanıcı oturum açmamışsa giriş sayfasına yönlendir
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

$message = '';
$disableForm = false;

// Doğrulama kodu ve süre başlatma
if (!isset($_SESSION['verification_start_time'])) {
    $_SESSION['verification_start_time'] = time();
    $_SESSION['verification_code'] = rand(100000, 999999); // 6 haneli kod
    // Burada Netgsm API'si ile kodu telefona gönderme işlemi yapılır
}

$timePassed = time() - $_SESSION['verification_start_time'];

if ($timePassed > 15) {
    unset($_SESSION['verification_start_time']);
    unset($_SESSION['verification_code']);
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['verification_code'])) {
        $verificationCode = $_POST['verification_code'];
        if ($verificationCode == $_SESSION['verification_code']) {
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_start_time']);
            
            // SMS doğrulaması başarılı olarak işaretle
            markSmsVerified();
            
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $_SESSION['verify_attempts'] = isset($_SESSION['verify_attempts']) ? $_SESSION['verify_attempts'] + 1 : 1;
            $remainingAttempts = 3 - $_SESSION['verify_attempts'];
            if ($_SESSION['verify_attempts'] >= 3) {
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

$remainingTime = 15 - $timePassed;
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
                <div class="login-message error-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="verify.php" class="login-form">
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
                
                <a href="index.php" class="back-button">
                    <i class="fas fa-arrow-left"></i> GERİ DÖN
                </a>
            </form>
            
            <?php if (isset($_SESSION['verification_code'])): ?>
                <div class="debug-code">
                    <p>Geliştirme modu: <strong><?php echo $_SESSION['verification_code']; ?></strong></p>
                </div>
            <?php endif; ?>
            
            <div class="login-footer">
                <p>© <?php echo date('Y'); ?> İnciPOS - Tüm Hakları Saklıdır</p>
            </div>
        </div>
    </div>
</body>
</html>