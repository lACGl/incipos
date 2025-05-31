<?php
// Hata raporlamayı aç ama display'i kapat
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Önce JSON header gönder
header('Content-Type: application/json; charset=utf-8');

// Hata çıktısını yakala
ob_start();

try {
    // Session ve DB bağlantısı
    require_once '../session_manager.php';
    secure_session_start();
    require_once '../db_connection.php';

    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        throw new Exception('Yetkisiz erişim');
    }

    $fatura_id = $_GET['id'] ?? null;
    
    if (!$fatura_id) {
        throw new Exception('Fatura ID gerekli');
    }

    // Fatura ID'yi integer'a çevir
    $fatura_id = intval($fatura_id);
    
    if ($fatura_id <= 0) {
        throw new Exception('Geçersiz fatura ID');
    }

    // Önce faturanın varlığını kontrol et
    $faturaCheck = $conn->prepare("SELECT id, durum FROM alis_faturalari WHERE id = ?");
    if (!$faturaCheck) {
        throw new Exception('Veritabanı sorgu hazırlama hatası: ' . $conn->errorInfo()[2]);
    }
    
    $faturaCheck->execute([$fatura_id]);
    $fatura = $faturaCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$fatura) {
        throw new Exception('Fatura bulunamadı - ID: ' . $fatura_id);
    }

    // Ana sorgu - basit versiyon
    $sql = "SELECT 
        afd.id,
        afd.fatura_id,
        afd.urun_id,
        afd.miktar,
        afd.birim_fiyat,
        afd.iskonto1,
        afd.iskonto2,
        afd.iskonto3,
        afd.kdv_orani,
        afd.toplam_tutar,
        us.barkod,
        us.ad,
        us.kod
    FROM 
        alis_fatura_detay afd
        LEFT JOIN urun_stok us ON us.id = afd.urun_id
    WHERE 
        afd.fatura_id = ?
    ORDER BY afd.id ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Ana sorgu hazırlama hatası: ' . $conn->errorInfo()[2]);
    }
    
    $stmt->execute([$fatura_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Aktarım miktarlarını ayrı sorgu ile al
    $transferSql = "SELECT 
        fatura_id, 
        urun_id, 
        SUM(miktar) as aktarilan_miktar 
    FROM alis_fatura_detay_aktarim 
    WHERE fatura_id = ? 
    GROUP BY fatura_id, urun_id";
    
    $transferStmt = $conn->prepare($transferSql);
    $transferStmt->execute([$fatura_id]);
    $transfers = $transferStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Transfer verilerini indexle
    $transferMap = [];
    foreach ($transfers as $transfer) {
        $transferMap[$transfer['urun_id']] = floatval($transfer['aktarilan_miktar']);
    }

    // Sonuçları formatla
    $formattedProducts = [];
    foreach ($products as $product) {
        $urun_id = intval($product['urun_id']);
        $aktarilan_miktar = $transferMap[$urun_id] ?? 0;
        $kalanMiktar = floatval($product['miktar']) - $aktarilan_miktar;
        
        $formattedProducts[] = [
            'id' => intval($product['id']),
            'urun_id' => $urun_id,
            'fatura_id' => intval($product['fatura_id']),
            'kod' => $product['kod'] ?: '-',
            'barkod' => $product['barkod'] ?: '',
            'ad' => $product['ad'] ?: 'Bilinmeyen Ürün',
            'miktar' => floatval($product['miktar']),
            'birim_fiyat' => floatval($product['birim_fiyat']),
            'iskonto1' => floatval($product['iskonto1'] ?? 0),
            'iskonto2' => floatval($product['iskonto2'] ?? 0),
            'iskonto3' => floatval($product['iskonto3'] ?? 0),
            'kdv_orani' => floatval($product['kdv_orani'] ?? 0),
            'toplam_tutar' => floatval($product['toplam_tutar']),
            'aktarilan_miktar' => $aktarilan_miktar,
            'kalan_miktar' => $kalanMiktar
        ];
    }

    // Buffer'daki çıktıyı temizle
    ob_clean();

    // Başarılı yanıt
    $response = [
        'success' => true,
        'products' => $formattedProducts,
        'total_count' => count($formattedProducts),
        'fatura_id' => $fatura_id,
        'fatura_durum' => $fatura['durum']
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Buffer'ı temizle
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage(),
        'error_type' => 'database',
        'products' => [],
        'total_count' => 0
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Buffer'ı temizle
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'general',
        'products' => [],
        'total_count' => 0,
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Error $e) {
    // Buffer'ı temizle
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'message' => 'Fatal hata: ' . $e->getMessage(),
        'error_type' => 'fatal',
        'products' => [],
        'total_count' => 0
    ], JSON_UNESCAPED_UNICODE);
}

// Buffer'ı sonlandır
ob_end_flush();
?>