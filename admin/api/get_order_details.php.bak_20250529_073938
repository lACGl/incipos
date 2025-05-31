<?php
session_start();
require_once '../db_connection.php';
header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // ID kontrolü
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Geçersiz sipariş ID');
    }
    $order_id = (int)$_GET['id'];
    
    // Sipariş bilgilerini getir
    $stmt = $conn->prepare("
        SELECT 
            sf.*, 
            m.ad as magaza_adi,
            p.ad as personel_adi,
            CONCAT(mu.ad, ' ', mu.soyad) as musteri_adi
        FROM satis_faturalari sf
        LEFT JOIN magazalar m ON sf.magaza = m.id
        LEFT JOIN personel p ON sf.personel = p.id
        LEFT JOIN musteriler mu ON sf.musteri_id = mu.id
        WHERE sf.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Sipariş bulunamadı');
    }
    
    // Sipariş kalemlerini getir
    $stmt = $conn->prepare("
        SELECT 
            sfd.*,
            us.ad as urun_adi,
            us.barkod,
            us.kod
        FROM satis_fatura_detay sfd
        LEFT JOIN urun_stok us ON sfd.urun_id = us.id
        WHERE sfd.fatura_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Puan kullanımı bilgisini getir
    $stmt = $conn->prepare("
        SELECT harcanan_puan 
        FROM puan_harcama 
        WHERE fatura_id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $harcanan_puan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Puan kazanma bilgisini getir
    $stmt = $conn->prepare("
        SELECT kazanilan_puan 
        FROM puan_kazanma 
        WHERE fatura_id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $kazanilan_puan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Puan bilgilerini düzenle
    $puan_bilgileri = null;
    if ($harcanan_puan || $kazanilan_puan) {
        $puan_bilgileri = [
            'harcanan_puan' => $harcanan_puan ? $harcanan_puan['harcanan_puan'] : 0,
            'kazanilan_puan' => $kazanilan_puan ? $kazanilan_puan['kazanilan_puan'] : 0
        ];
    }
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'order' => $order,
        'items' => $items,
        'puan_bilgileri' => $puan_bilgileri
    ]);
    
} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>