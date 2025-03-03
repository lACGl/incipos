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
    // Filtre parametreleri
    $filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    $filter_customer = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
    $filter_magaza = isset($_GET['magaza_id']) ? (int)$_GET['magaza_id'] : null;
    
    // WHERE koşulları
    $where_conditions = ['mb.odendi_mi = 0'];
    $params = [];
    
    // Tarih filtresi
    if ($filter_date_from) {
        $where_conditions[] = 'mb.borc_tarihi >= :date_from';
        $params[':date_from'] = $filter_date_from;
    }
    
    if ($filter_date_to) {
        $where_conditions[] = 'mb.borc_tarihi <= :date_to';
        $params[':date_to'] = $filter_date_to;
    }
    
    // Müşteri filtresi
    if ($filter_customer) {
        $where_conditions[] = 'mb.musteri_id = :customer_id';
        $params[':customer_id'] = $filter_customer;
    }
    
    // Mağaza filtresi
    if ($filter_magaza) {
        $where_conditions[] = 'mb.magaza_id = :magaza_id';
        $params[':magaza_id'] = $filter_magaza;
    }
    
    // WHERE cümlesini oluştur
    $where_clause = implode(' AND ', $where_conditions);
    
    // Ödenmemiş borçları getir
    $sql = "
        SELECT 
            mb.*,
            m.ad as musteri_adi,
            m.soyad as musteri_soyadi,
            m.telefon,
            mag.ad as magaza_adi,
            COALESCE((
                SELECT SUM(mbo.odeme_tutari) 
                FROM musteri_borc_odemeler mbo 
                WHERE mbo.borc_id = mb.borc_id
            ), 0) as odenen_tutar
        FROM 
            musteri_borclar mb
            JOIN musteriler m ON mb.musteri_id = m.id
            LEFT JOIN magazalar mag ON mb.magaza_id = mag.id
        WHERE 
            $where_clause
        ORDER BY 
            mb.borc_tarihi DESC
    ";
    
    $stmt = $conn->prepare($sql);
    
    // Parametreleri bağla
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam borç, ödeme ve kalan borç tutarlarını hesapla
    $total_credit = 0;
    $total_paid = 0;
    $total_remaining = 0;
    
    foreach ($credits as $credit) {
        $amount = floatval($credit['toplam_tutar']);
        $paid = floatval($credit['odenen_tutar']);
        $remaining = $amount - $paid;
        
        $total_credit += $amount;
        $total_paid += $paid;
        $total_remaining += $remaining;
    }
    
    // Özet bilgiler
    $summary = [
        'toplam_borc' => $total_credit,
        'toplam_odeme' => $total_paid,
        'kalan_borc' => $total_remaining,
        'borc_sayisi' => count($credits)
    ];
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'credits' => $credits,
        'summary' => $summary,
        'filters' => [
            'date_from' => $filter_date_from,
            'date_to' => $filter_date_to,
            'customer_id' => $filter_customer,
            'magaza_id' => $filter_magaza
        ]
    ]);

} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}