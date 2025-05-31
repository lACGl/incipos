<?php
// test.php
require_once '../../session_manager.php';
checkUserSession();
require_once '../../db_connection.php';
require_once __DIR__ . '/init.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Sayfası</title>
    <script src="./assets/js/barcode.js"></script>
</head>
<body>
    <h1>Test Sayfası</h1>
    <div id="test-qr"></div>
    <script>
        // JavaScript kodunuzu burada test edin
        console.log("JavaScript çalışıyor");
    </script>
</body>
</html>