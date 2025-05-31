<?php
require_once 'session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once 'db_connection.php'; // Veritabanı bağlantısını sağlayın

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : null;  // Telefon numarası opsiyonel

    // Şifreyi hash'le
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    try {
        // Kullanıcıyı admin_user tablosuna ekle
        $stmt = $conn->prepare("INSERT INTO admin_user (kullanici_adi, sifre, telefon_no) VALUES (:username, :password, :phone)");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);  // Telefon numarası opsiyonel olabilir
        $stmt->execute();

        echo "Kullanıcı başarıyla eklendi.";
    } catch (PDOException $e) {
        echo "Hata: " . $e->getMessage();
    }
}
?>

<form method="POST" action="add.php">
    Kullanıcı Adı: <input type="text" name="username" required><br>
    Şifre: <input type="password" name="password" required><br>
    Telefon Numarası: <input type="text" name="phone"><br> <!-- Telefon numarası opsiyonel -->
    <input type="submit" value="Kullanıcı Ekle">
</form>
