<?php
session_start();
require_once 'db_connection.php'; // Veritabanı bağlantısını sağlayın

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
    <title>Doğrulama</title>
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
<body>
    <h1>Doğrulama</h1>

    <form method="POST" action="verify.php">
        Doğrulama Kodu: <input type="text" name="verification_code" required><br>
        <input type="submit" value="Doğrula">
    </form>
    <form action="index.php" method="GET">
        <button type="submit">Geri Dön</button>
    </form>

    <?php if ($message): ?>
        <div style="color: red;"><?php echo $message; ?></div>
    <?php endif; ?>

    <p>Kalan süre: <span id="remaining-time"><?php echo $remainingTime; ?></span> saniye</p>

    <?php if (isset($_SESSION['verification_code'])): ?>
        <p>Geçici doğrulama kodu: <strong><?php echo $_SESSION['verification_code']; ?></strong></p>
    <?php endif; ?>
</body>
</html>
