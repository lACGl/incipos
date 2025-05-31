<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

try {
    // Arama terimi
    $searchTerm = isset($_GET['search_term']) ? trim($_GET['search_term']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    // WHERE koşulları
    $where = [];
    $params = [];
    
    if (!empty($searchTerm)) {
        $where[] = "(m.ad LIKE ? OR m.soyad LIKE ? OR m.telefon LIKE ?)";
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
        $params[] = '%' . $searchTerm . '%';
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : "";
    
    // Müşteri listesini ve borç bilgilerini getir
    $sql = "
        SELECT 
            m.id, 
            m.ad,
            m.soyad,
            m.telefon,
            m.email,
            m.barkod,
            m.durum,
            mp.puan_bakiye,
            mp.puan_oran,
            mp.musteri_turu,
            COALESCE(
                (SELECT SUM(mb.toplam_tutar - mb.indirim_tutari - COALESCE(
                    (SELECT SUM(mbo.odeme_tutari) FROM musteri_borc_odemeler mbo WHERE mbo.borc_id = mb.borc_id), 
                    0
                )) 
                FROM musteri_borclar mb 
                WHERE mb.musteri_id = m.id AND mb.odendi_mi = 0), 
                0
            ) as toplam_borc
        FROM 
            musteriler m
            LEFT JOIN musteri_puanlar mp ON m.id = mp.musteri_id
        $whereClause
        ORDER BY m.ad, m.soyad
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    // Parametre bağlama
    if (!empty($searchTerm)) {
        for ($i = 0; $i < count($params); $i++) {
            $stmt->bindParam($i + 1, $params[$i]);
        }
        $stmt->bindParam(count($params) + 1, $limit, PDO::PARAM_INT);
    } else {
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Başarılı yanıt
    echo json_encode([
        'success' => true,
        'customers' => $customers
    ]);
    
} catch (PDOException $e) {
    // Veritabanı hatası
    error_log('PDO Hatası: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Genel hata
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}