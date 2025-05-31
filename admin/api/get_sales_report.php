<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $magaza_id = $_GET['magaza'] ?? null;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $where_conditions = ["sf.fatura_tarihi BETWEEN :start_date AND :end_date"];
    $params = [
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ];

    if ($magaza_id) {
        $where_conditions[] = "sf.magaza = :magaza_id";
        $params[':magaza_id'] = $magaza_id;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Özet bilgileri al
    $summary_sql = "
        SELECT 
            COUNT(*) as toplam_satis, 
            SUM(CASE WHEN islem_turu = 'satis' THEN toplam_tutar ELSE 0 END) as toplam_ciro,
            SUM(CASE WHEN islem_turu = 'iade' THEN toplam_tutar ELSE 0 END) as toplam_iade,
            SUM(CASE WHEN odeme_turu = 'nakit' THEN toplam_tutar ELSE 0 END) as nakit_toplam,
            SUM(CASE WHEN odeme_turu = 'kredi_karti' THEN toplam_tutar ELSE 0 END) as kart_toplam,
            SUM(CASE WHEN odeme_turu = 'borc' THEN toplam_tutar ELSE 0 END) as borc_toplam
        FROM satis_faturalari sf
        WHERE $where_clause
    ";

    $stmt = $conn->prepare($summary_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Toplam kayıt sayısını hesapla
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM satis_faturalari sf
        WHERE $where_clause
    ";

    $stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    // Satış listesini al
    $sales_sql = "
        SELECT 
            sf.*,
            m.ad as magaza_adi,
            p.ad as personel_adi,
            CONCAT(mu.ad, ' ', mu.soyad) as musteri_adi
        FROM satis_faturalari sf
        LEFT JOIN magazalar m ON sf.magaza = m.id
        LEFT JOIN personel p ON sf.personel = p.id
        LEFT JOIN musteriler mu ON sf.musteri_id = mu.id
        WHERE $where_clause
        ORDER BY sf.fatura_tarihi DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $conn->prepare($sales_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'sales' => $sales,
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $page
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}