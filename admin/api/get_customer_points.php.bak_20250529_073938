<?php
session_start();
require_once '../db_connection.php';

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
        SELECT m.*, mp.puan_bakiye, mp.puan_oran, mp.musteri_turu, mp.son_alisveris_tarihi
        FROM musteriler m
        LEFT JOIN musteri_puanlar mp ON m.id = mp.musteri_id
        WHERE m.id = ?
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Müşteri bulunamadı');
    }

    // Puan kazanma geçmişini al
    $stmt = $conn->prepare("
        SELECT pk.*, sf.fatura_seri, sf.fatura_no, 'kazanma' as islem_tipi
        FROM puan_kazanma pk
        LEFT JOIN satis_faturalari sf ON pk.fatura_id = sf.id
        WHERE pk.musteri_id = ?
        ORDER BY pk.tarih DESC
        LIMIT 50
    ");
    $stmt->execute([$customer_id]);
    $earned_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Puan harcama geçmişini al
    $stmt = $conn->prepare("
        SELECT ph.*, sf.fatura_seri, sf.fatura_no, 'harcama' as islem_tipi
        FROM puan_harcama ph
        LEFT JOIN satis_faturalari sf ON ph.fatura_id = sf.id
        WHERE ph.musteri_id = ?
        ORDER BY ph.tarih DESC
        LIMIT 50
    ");
    $stmt->execute([$customer_id]);
    $spent_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Puan geçmişini birleştir ve tarihe göre sırala
    $points_history = array_merge($earned_points, $spent_points);
    usort($points_history, function($a, $b) {
        return strtotime($b['tarih']) - strtotime($a['tarih']);
    });

    // Toplam kazanılan puan
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(kazanilan_puan), 0) as total
        FROM puan_kazanma
        WHERE musteri_id = ?
    ");
    $stmt->execute([$customer_id]);
    $total_earned = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Toplam harcanan puan
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(harcanan_puan), 0) as total
        FROM puan_harcama
        WHERE musteri_id = ?
    ");
    $stmt->execute([$customer_id]);
    $total_spent = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'points_history' => $points_history,
        'total_earned' => $total_earned,
        'total_spent' => $total_spent,
        'current_balance' => $customer['puan_bakiye']
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}