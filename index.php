<?php
session_start();
require_once 'db_connection.php'; // Veritabanı bağlantısı dosyanız

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

            // SMS gönderimi
            $phone = $user['telefon_no']; // Kullanıcı telefon numarası
            $verificationCode = rand(100000, 999999); // 6 haneli doğrulama kodu
            $_SESSION['verification_code'] = $verificationCode;
            $_SESSION['user_id'] = $user['id'];
            sendSms($phone, "Doğrulama kodunuz: $verificationCode");

            // Yönlendirme
            header("Location: verify.php");
            exit;
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
    <title>Giriş Yap</title>
</head>
<body>
    <h1>Giriş Yap</h1>
    <?php if ($message): ?>
        <p style="color: red;"><?php echo $message; ?></p>
    <?php endif; ?>
    <form method="POST" action="index.php">
        <label for="username">Kullanıcı Adı:</label>
        <input type="text" name="username" id="username" required><br>
        <label for="password">Şifre:</label>
        <input type="password" name="password" id="password" required><br>
        <input type="submit" value="Giriş" <?php echo $disableForm ? 'disabled' : ''; ?>>
    </form>
</body>
</html>
