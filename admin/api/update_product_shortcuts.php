<?php
session_start();
require_once '../db_connection.php';

// JSON veri tipinde yanıt döndüreceğimizi belirt
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

/**
 * Ürün kısayollarını güncelle
 * ---------------------------
 * Bu API, kasa ekranındaki ürün kısayol butonlarını güncellemek için kullanılır
 */

try {
    // POST verisini al
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    // Veri kontrolü
    if (!isset($inputData['shortcuts']) || !is_array($inputData['shortcuts'])) {
        throw new Exception('Geçersiz veri formatı');
    }
    
    $shortcuts = $inputData['shortcuts'];
    
    // Kısayolları doğrula
    foreach ($shortcuts as $shortcut) {
        if (!isset($shortcut['product_id']) || !isset($shortcut['position']) || !is_numeric($shortcut['product_id'])) {
            throw new Exception('Kısayol verisi eksik veya geçersiz');
        }
        
        if ($shortcut['position'] < 0 || $shortcut['position'] > 23) {
            throw new Exception('Geçersiz kısayol pozisyonu (0-23 arası olmalı)');
        }
    }
    
    // Ürünlerin varlığını kontrol et
    $productIds = array_column($shortcuts, 'product_id');
    
    if (!empty($productIds)) {
        $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
        
        $stmt = $conn->prepare("
            SELECT id FROM urun_stok 
            WHERE id IN ($placeholders) AND durum = 'aktif'
        ");
        $stmt->execute($productIds);
        $validProducts = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Geçerli olmayan ürünleri kaldır
        foreach ($shortcuts as $key => $shortcut) {
            if (!in_array($shortcut['product_id'], $validProducts)) {
                unset($shortcuts[$key]);
            }
        }
        
        // Indeksleri yeniden düzenle
        $shortcuts = array_values($shortcuts);
    }
    
    // Kısayolları JSON olarak kaydet
    $shortcutsJson = json_encode($shortcuts);
    
    // Sistem ayarlarını güncelle veya oluştur
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM sistem_ayarlari 
        WHERE anahtar = 'urun_kisayollari'
    ");
    $stmt->execute();
    $exists = $stmt->fetchColumn() > 0;
    
    if ($exists) {
        $stmt = $conn->prepare("
            UPDATE sistem_ayarlari 
            SET deger = ?, 
                guncelleme_tarihi = NOW() 
            WHERE anahtar = 'urun_kisayollari'
        ");
        $stmt->execute([$shortcutsJson]);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO sistem_ayarlari (
                anahtar, deger, 
                aciklama, guncelleme_tarihi
            ) VALUES (
                'urun_kisayollari', ?, 
                'POS kısayol butonları', NOW()
            )
        ");
        $stmt->execute([$shortcutsJson]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Ürün kısayolları başarıyla güncellendi',
        'shortcuts' => $shortcuts
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ürün kısayolları güncellenirken bir hata oluştu: ' . $e->getMessage()
    ]);
}