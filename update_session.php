<?php
session_start();
require_once 'db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['error' => 'Yetkisiz erişim']));
}

if (isset($_POST['items_per_page'])) {
    $items_per_page = (int)$_POST['items_per_page'];
    
    // Geçerli değerler kontrolü
    $valid_values = [5, 10, 20, 50, 100, 200, 500, 1000];
    if (!in_array($items_per_page, $valid_values)) {
        die(json_encode(['error' => 'Geçersiz değer']));
    }
    
    // Session'ı güncelle
    $_SESSION['items_per_page'] = $items_per_page;
    
    echo json_encode([
        'success' => true,
        'message' => 'Sayfa başına gösterilecek ürün sayısı güncellendi'
    ]);
} else {
    echo json_encode(['error' => 'Geçersiz istek']);
}