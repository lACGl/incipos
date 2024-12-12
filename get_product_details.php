<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Ürün ID gerekli');
    }

    // Ana ürün bilgilerini al
    $sql = "SELECT * FROM urun_stok WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        throw new Exception('Ürün bulunamadı');
    }

    // Departmanları al
    $stmt = $conn->query("SELECT id, ad FROM departmanlar ORDER BY ad");
    $departmanlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Birimleri al
    $stmt = $conn->query("SELECT id, ad FROM birimler ORDER BY ad");
    $birimler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ana grupları al
    $stmt = $conn->query("SELECT id, ad FROM ana_gruplar ORDER BY ad");
    $ana_gruplar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Alt grupları al
    $stmt = $conn->query("SELECT id, ad, ana_grup_id FROM alt_gruplar ORDER BY ad");
    $alt_gruplar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tarih formatlamaları
    $product['kayit_tarihi'] = $product['kayit_tarihi'] ? date('Y-m-d', strtotime($product['kayit_tarihi'])) : null;
    $product['indirim_baslangic_tarihi'] = $product['indirim_baslangic_tarihi'] ? date('Y-m-d', strtotime($product['indirim_baslangic_tarihi'])) : null;
    $product['indirim_bitis_tarihi'] = $product['indirim_bitis_tarihi'] ? date('Y-m-d', strtotime($product['indirim_bitis_tarihi'])) : null;

    echo json_encode([
        'success' => true,
        'product' => $product,
        'departmanlar' => $departmanlar,
        'birimler' => $birimler,
        'ana_gruplar' => $ana_gruplar,
        'alt_gruplar' => $alt_gruplar
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}