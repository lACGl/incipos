<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $id = $_GET['id'] ?? null;
    if (!$id) {
        throw new Exception('Ürün ID gerekli');
    }

    // Debug için
    error_log('İstenen ürün ID: ' . $id);

    // Stok hareketlerini getir
    $stmt = $conn->prepare("
        SELECT 
            sh.*,
            CASE 
                WHEN sh.depo_id IS NOT NULL THEN 'Ana Depo'
                WHEN sh.magaza_id IS NOT NULL THEN m.ad
                ELSE 'Bilinmiyor'
            END as magaza_adi,
            au.kullanici_adi
        FROM stok_hareketleri sh
        LEFT JOIN magazalar m ON m.id = sh.magaza_id
        LEFT JOIN admin_user au ON sh.kullanici_id = au.id
        WHERE sh.urun_id = ?
        ORDER BY sh.tarih DESC
    ");
    $stmt->execute([$id]);
    $hareketler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug için
    error_log('Bulunan hareket sayısı: ' . count($hareketler));

    // Depo stoğunu getir
    $stmt = $conn->prepare("
        SELECT stok_miktari, son_guncelleme 
        FROM depo_stok 
        WHERE urun_id = ? AND depo_id = 1
    ");
    $stmt->execute([$id]);
    $depo_stok = $stmt->fetch(PDO::FETCH_ASSOC);

// Mağaza stoklarını getir
$stmt = $conn->prepare("
    SELECT 
        ms.stok_miktari,
        ms.satis_fiyati,
        ms.son_guncelleme,
        m.ad as magaza_adi
    FROM urun_stok us
    LEFT JOIN magaza_stok ms ON ms.barkod = us.barkod
    LEFT JOIN magazalar m ON m.id = ms.magaza_id
    WHERE us.id = ? 
    AND ms.magaza_id IS NOT NULL  
");

// Debug için SQL sorgusunu ve ID'yi logla 
error_log("SQL Query ID: " . $id);
$stmt->execute([$id]);
$magaza_stoklari = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gelen ham veriyi logla
error_log("Raw magaza_stoklari data: " . print_r($magaza_stoklari, true));

// JSON'a çevrilmiş veriyi logla
error_log("JSON encoded data: " . json_encode([
    'success' => true,
    'hareketler' => $hareketler,
    'depo_stok' => $depo_stok,
    'magaza_stoklari' => $magaza_stoklari
]));

echo json_encode([
    'success' => true,
    'hareketler' => $hareketler,
    'depo_stok' => $depo_stok,
    'magaza_stoklari' => $magaza_stoklari
]);

} catch (Exception $e) {
    error_log('Stok detayları hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}