<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $magaza_id = $_GET['magaza'] ?? null;

    $where_conditions = ["sf.fatura_tarihi BETWEEN :start_date AND :end_date"];
    $params = [
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ];

    if ($magaza_id) {
        $where_conditions[] = "sf.magaza = :magaza_id";
        $params[':magaza_id'] = $magaza_id;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Detaylı satış verileri
    $sql = "
        SELECT 
            sf.fatura_tarihi,
            sf.fatura_seri,
            sf.fatura_no,
            m.ad as magaza_adi,
            us.ad as urun_adi,
            sfd.miktar,
            sfd.birim_fiyat,
            sfd.toplam_tutar,
            ag.ad as kategori
        FROM satis_faturalari sf
        JOIN satis_fatura_detay sfd ON sf.id = sfd.fatura_id
        JOIN magazalar m ON sf.magaza = m.id
        JOIN urun_stok us ON sfd.urun_id = us.id
        LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
        WHERE $where_clause
        ORDER BY sf.fatura_tarihi DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Özet veriler
    $summary_sql = "
        SELECT 
            COUNT(DISTINCT sf.id) as toplam_fatura,
            SUM(sf.toplam_tutar) as toplam_satis,
            COUNT(sfd.id) as toplam_urun,
            COALESCE(COUNT(CASE WHEN sf.islem_turu = 'iade' THEN 1 END), 0) as iade_sayisi
        FROM satis_faturalari sf
        JOIN satis_fatura_detay sfd ON sf.id = sfd.fatura_id
        WHERE $where_clause
    ";

    $stmt = $conn->prepare($summary_sql);
    $stmt->execute($params);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Kategori dağılımı
    $category_sql = "
        SELECT 
            ag.ad as kategori,
            COUNT(*) as adet,
            SUM(sfd.toplam_tutar) as toplam
        FROM satis_fatura_detay sfd
        JOIN satis_faturalari sf ON sfd.fatura_id = sf.id
        JOIN urun_stok us ON sfd.urun_id = us.id
        LEFT JOIN ana_gruplar ag ON us.ana_grup_id = ag.id
        WHERE $where_clause
        GROUP BY ag.id, ag.ad
    ";

    $stmt = $conn->prepare($category_sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sales' => $sales,
        'summary' => $summary,
        'categories' => $categories
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}