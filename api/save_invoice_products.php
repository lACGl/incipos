<?php
session_start();
require_once '../db_connection.php';
require_once 'helpers/stock_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['fatura_id']) || !isset($data['products']) || empty($data['products'])) {
        throw new Exception('Geçersiz veri');
    }

    $fatura_id = $data['fatura_id'];
    $products = $data['products'];

    $conn->beginTransaction();

    // Mevcut ürünleri sil
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay WHERE fatura_id = ?");
    $stmt->execute([$fatura_id]);

    // Yeni ürünleri ekle
    $sql = "INSERT INTO alis_fatura_detay (
        fatura_id, urun_id, miktar, birim_fiyat, kdv_orani, toplam_tutar
    ) VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $toplam_tutar = 0;

    foreach ($products as $product) {
        // Fatura detayını ekle
        $stmt->execute([
            $fatura_id,
            $product['id'],
            $product['miktar'],
            $product['birim_fiyat'],
            $product['kdv_orani'],
            $product['toplam']
        ]);
        
        $toplam_tutar += $product['toplam'];

        // Stok ekle
        stokEkle(
            $conn,
            $product['id'],
            $product['miktar'],
            $product['birim_fiyat'],
            $product['satis_fiyati'] ?? null
        );

        // Ürün fiyat geçmişi kaydı
        $fiyat_stmt = $conn->prepare("
            INSERT INTO urun_fiyat_gecmisi (
                urun_id, islem_tipi, yeni_fiyat,
                fatura_id, aciklama, kullanici_id
            ) VALUES (
                ?, 'alis', ?,
                ?, 'Alış faturasından giriş', ?
            )
        ");
        $fiyat_stmt->execute([
            $product['id'],
            $product['birim_fiyat'],
            $fatura_id,
            $_SESSION['user_id'] ?? null
        ]);
    }

    // Faturanın durumunu ve toplam tutarını güncelle
    $update_sql = "UPDATE alis_faturalari SET 
                    durum = 'urun_girildi',
                    toplam_tutar = ?
                  WHERE id = ?";
    
    $stmt = $conn->prepare($update_sql);
    $stmt->execute([$toplam_tutar, $fatura_id]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Ürünler başarıyla kaydedildi'
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}