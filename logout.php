<?php
session_start();

// Tüm session değişkenlerini temizle
$_SESSION = array();

// Session çerezini yok et
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session'ı sonlandır
session_destroy();

// Giriş sayfasına yönlendir
header("Location: login.php");
exit;