<?php
$host = 'localhost'; // Veritabanı sunucusu
$dbname = 'incikir2_pos'; // Veritabanı adı
$username = 'incikir2_posadmin'; // Veritabanı kullanıcı adı (değiştirin)
$password = 'vD3YjbzpPYsc'; // Veritabanı şifresi

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Hata modunu gizle
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // PDO'nun güvenli kullanımını sağla
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Hata mesajını gösterme
    error_log($e->getMessage()); // Hata günlüğüne kaydet
    exit("Bağlantı hatası. Lütfen sistem yöneticisiyle iletişime geçin.");
}
?>
