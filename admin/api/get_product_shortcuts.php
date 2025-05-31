<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

// JSON veri tipinde yanıt döndüreceğimizi belirt
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

/**
 * Ürün kısayollarını getir
 * -------------------------
 * Bu API, kasa ekranındaki ürün kısayol butonlarını yönetmek için kullanılır
 * İlk sürüm için sistem_ayarlari tablosunda saklanan basit bir JSON yapısı kullanıyoruz
 */

try {
    // Sistem ayarlarından ürün kısayollarını al
    $stmt = $conn->prepare("
        SELECT deger 
        FROM sistem_ayarlari 
        WHERE anahtar = 'urun_kisayollari'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Eğer kısayol ayarı yoksa boş bir dizi gönder
    if (!$result) {
        echo json_encode([
            'success' => true,
            'shortcuts' => []
        ]);
        exit;
    }

    // JSON verisi olarak çöz
    $shortcuts = json_decode($result['deger'], true);

    // Eğer decode işlemi başarısız olursa veya dizi değilse
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($shortcuts)) {
        echo json_encode([
            'success' => true,
            'shortcuts' => []
        ]);
        exit;
    }

    // Kısayol ürünlerinin detaylarını getir
    $productIds = array_column($shortcuts, 'product_id');
    
    if (empty($productIds)) {
        echo json_encode([
            'success' => true,
            'shortcuts' => []
        ]);
        exit;
    }

    // IN sorgusunu hazırla
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $conn->prepare("
        SELECT id, barkod, kod, ad, satis_fiyati, stok_miktari
        FROM urun_stok
        WHERE id IN ($placeholders) AND durum = 'aktif'
    ");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ürünleri id'ye göre indeksle
    $productMap = [];
    foreach ($products as $product) {
        $productMap[$product['id']] = $product;
    }

    // Kısayol bilgilerini tamamla
    foreach ($shortcuts as $key => $shortcut) {
        if (isset($productMap[$shortcut['product_id']])) {
            $shortcuts[$key]['product'] = $productMap[$shortcut['product_id']];
        } else {
            // Ürün artık mevcut değilse veya pasifse, kısayoldan kaldır
            unset($shortcuts[$key]);
        }
    }

    // Indeksleri yeniden düzenle
    $shortcuts = array_values($shortcuts);

    echo json_encode([
        'success' => true,
        'shortcuts' => $shortcuts
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ürün kısayolları getirilirken bir hata oluştu: ' . $e->getMessage()
    ]);
}