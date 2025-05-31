<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
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
        throw new Exception('Geçersiz müşteri ID');
    }

    $customer_id = (int)$_GET['id'];

    // Müşteri bilgilerini al
    $stmt = $conn->prepare("
        SELECT m.*, mp.puan_bakiye
        FROM musteriler m
        LEFT JOIN musteri_puanlar mp ON m.id = mp.musteri_id
        WHERE m.id = ?
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Müşteri bulunamadı');
    }

    // Siparişleri al (satış faturaları)
    $stmt = $conn->prepare("
        SELECT sf.*, m.ad as magaza_adi
        FROM satis_faturalari sf
        LEFT JOIN magazalar m ON sf.magaza = m.id
        WHERE sf.musteri_id = ?
        ORDER BY sf.fatura_tarihi DESC
        LIMIT 50
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Kazanılan puanları al
    $stmt = $conn->prepare("
        SELECT fatura_id, kazanilan_puan
        FROM puan_kazanma
        WHERE musteri_id = ?
    ");
    $stmt->execute([$customer_id]);
    $kazanilan_puanlar = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Harcanan puanları al
    $stmt = $conn->prepare("
        SELECT fatura_id, harcanan_puan
        FROM puan_harcama
        WHERE musteri_id = ?
    ");
    $stmt->execute([$customer_id]);
    $harcanan_puanlar = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Siparişlere puan bilgilerini ekle
    foreach ($orders as &$order) {
        $order['kazanilan_puan'] = isset($kazanilan_puanlar[$order['id']]) ? $kazanilan_puanlar[$order['id']] : 0;
        $order['harcanan_puan'] = isset($harcanan_puanlar[$order['id']]) ? $harcanan_puanlar[$order['id']] : 0;
    }

    // Toplam alışveriş tutarını hesapla
    $stmt = $conn->prepare("
        SELECT SUM(toplam_tutar) as toplam
        FROM satis_faturalari
        WHERE musteri_id = ? AND islem_turu = 'satis'
    ");
    $stmt->execute([$customer_id]);
    $totalSpent = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'] ?: 0;
    
    // Toplam kazanılan puanları hesapla
    $stmt = $conn->prepare("
        SELECT SUM(kazanilan_puan) as toplam
        FROM puan_kazanma
        WHERE musteri_id = ?
    ");
    $stmt->execute([$customer_id]);
    $totalPointsEarned = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'] ?: 0;
    
    // Toplam harcanan puanları hesapla
    $stmt = $conn->prepare("
        SELECT SUM(harcanan_puan) as toplam
        FROM puan_harcama
        WHERE musteri_id = ?
    ");
    $stmt->execute([$customer_id]);
    $totalPointsSpent = $stmt->fetch(PDO::FETCH_ASSOC)['toplam'] ?: 0;

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'orders' => $orders,
        'totalSpent' => $totalSpent,
        'totalPointsEarned' => $totalPointsEarned,
        'totalPointsSpent' => $totalPointsSpent
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}