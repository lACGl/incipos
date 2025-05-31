<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        throw new Exception('Yetkisiz erişim');
    }

    $search_term = isset($_GET['term']) ? trim($_GET['term']) : '';
    
    if (empty($search_term)) {
        throw new Exception('Arama terimi boş olamaz');
    }

    // Arama terimini kelimelere böl
    $keywords = explode(' ', $search_term);
    
    // SQL koşulunu oluştur
    $conditions = [];
    $params = [];
    
    foreach ($keywords as $index => $keyword) {
        if (strlen($keyword) >= 2) { // En az 2 karakterli kelimeleri ara
            $param_name = ":term{$index}";
            $conditions[] = "(us.barkod LIKE {$param_name} OR us.kod LIKE {$param_name} OR us.ad LIKE {$param_name})";
            $params[$param_name] = '%' . $keyword . '%';
        }
    }
    
    // Koşullar boşsa basit bir arama yap
    if (empty($conditions)) {
        $where_clause = "(us.barkod LIKE :term OR us.kod LIKE :term OR us.ad LIKE :term)";
        $params = [':term' => '%' . $search_term . '%'];
    } else {
        // Tüm kelimeleri AND ile birleştir (her kelime ürün adında olmalı)
        $where_clause = implode(' AND ', $conditions);
    }

    // Tek sorguda tüm bilgileri getir (LEFT JOIN ile mağaza ve depo stokları dahil)
    $sql = "SELECT 
        us.id, 
        us.kod,  
        us.barkod, 
        us.ad, 
        us.satis_fiyati,
        us.alis_fiyati,
        us.kdv_orani,
        IFNULL(us.indirimli_fiyat, us.satis_fiyati) AS indirimli_fiyat,
        (CASE WHEN us.indirimli_fiyat IS NOT NULL AND us.indirimli_fiyat < us.satis_fiyati THEN 1 ELSE 0 END) AS has_discount,
        (SELECT SUM(ms.stok_miktari) FROM magaza_stok ms WHERE ms.barkod = us.barkod) AS magaza_toplam_stok,
        (SELECT SUM(ds.stok_miktari) FROM depo_stok ds WHERE ds.urun_id = us.id) AS depo_toplam_stok
    FROM urun_stok us 
    WHERE {$where_clause}
    AND us.durum = 'aktif'
    ORDER BY 
        CASE 
            WHEN us.barkod = :exact_term THEN 1
            WHEN us.kod = :exact_term THEN 2
            WHEN us.ad LIKE :start_term THEN 3
            ELSE 4
        END,
        us.ad ASC
    LIMIT 20";

    $stmt = $conn->prepare($sql);
    
    // Parametreleri bağla
    foreach ($params as $param_name => $param_value) {
        $stmt->bindValue($param_name, $param_value, PDO::PARAM_STR);
    }
    
    $stmt->bindValue(':exact_term', $search_term, PDO::PARAM_STR);
    $stmt->bindValue(':start_term', $search_term . '%', PDO::PARAM_STR);

    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Her ürün için toplam stok hesapla ve formata uygun hale getir
    foreach ($products as &$product) {
        $magaza_toplam_stok = (int)($product['magaza_toplam_stok'] ?? 0);
        $depo_toplam_stok = (int)($product['depo_toplam_stok'] ?? 0);
        
        // Toplam stok bilgisini ekle
        $product['toplam_stok'] = $magaza_toplam_stok + $depo_toplam_stok;
        $product['stok_miktari'] = $product['toplam_stok']; // Eski format için uyumluluk

        // Mağaza ve depo stok detaylarını getir
        if ($product['toplam_stok'] > 0) {
            // Mağaza stokları
            $magazaStockSql = "SELECT 
                ms.barkod,
                ms.magaza_id,
                m.ad AS magaza_adi,
                ms.stok_miktari
            FROM magaza_stok ms
            JOIN magazalar m ON ms.magaza_id = m.id
            WHERE ms.barkod = :barkod AND ms.stok_miktari > 0";
            
            $magazaStockStmt = $conn->prepare($magazaStockSql);
            $magazaStockStmt->execute([':barkod' => $product['barkod']]);
            $product['magaza_stoklar'] = $magazaStockStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Depo stokları 
            $depoStockSql = "SELECT 
                ds.urun_id,
                us.barkod,
                ds.depo_id,
                d.ad AS depo_adi,
                ds.stok_miktari
            FROM depo_stok ds
            JOIN depolar d ON ds.depo_id = d.id
            JOIN urun_stok us ON ds.urun_id = us.id
            WHERE ds.urun_id = :urun_id AND ds.stok_miktari > 0";
            
            $depoStockStmt = $conn->prepare($depoStockSql);
            $depoStockStmt->execute([':urun_id' => $product['id']]);
            $product['depo_stoklar'] = $depoStockStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $product['magaza_stoklar'] = [];
            $product['depo_stoklar'] = [];
        }

        // Ürünün etkin fiyatını belirle
        $product['active_price'] = $product['indirimli_fiyat'] ?? $product['satis_fiyati'];
    }

    if (empty($products)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ürün bulunamadı',
            'search_term' => $search_term
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'products' => $products,
        'search_term' => $search_term,
        'count' => count($products)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ürün arama sırasında bir hata oluştu: ' . $e->getMessage()
    ]);
}