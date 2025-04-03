<?php
session_start();
require_once '../db_connection.php';
require_once '../helpers/stock_functions.php';

// Her zaman JSON header'ı gönder
header('Content-Type: application/json');

// Hata raporlamasını kapat
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

try {
    // Gelen veriyi logla
    error_log('Gelen Raw Veri: ' . file_get_contents('php://input'));
    
    // JSON verisini parse et
    $data = json_decode(file_get_contents('php://input'), true);
    
    // JSON parse hatasını kontrol et
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON parse hatası: ' . json_last_error_msg());
    }
    
    if (!isset($data['fatura_id']) || !isset($data['products']) || empty($data['products'])) {
        throw new Exception('Geçersiz veri formatı veya eksik bilgi');
    }

    $fatura_id = $data['fatura_id'];
    $products = $data['products'];
    $is_complete = $data['is_complete'] ?? false;

    $conn->beginTransaction();

    // Faturanın durumunu kontrol et
    $stmt = $conn->prepare("SELECT durum FROM alis_faturalari WHERE id = ?");
    $stmt->execute([$fatura_id]);
    $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fatura['durum'] === 'aktarildi') {
        throw new Exception('Bu fatura tamamlanmış, değişiklik yapamazsınız');
    }

    // Mevcut ürünleri sil
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay WHERE fatura_id = ?");
    $stmt->execute([$fatura_id]);

    // Yeni ürünleri ekle
    $sql = "INSERT INTO alis_fatura_detay (
        fatura_id, urun_id, miktar, birim_fiyat,
        iskonto1, iskonto2, iskonto3,
        kdv_orani, toplam_tutar
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $toplam_tutar = 0;
    $toplam_kdv = 0;

    foreach ($products as $product) {
        // Sayısal değerleri kontrol et ve dönüştür
        $miktar = floatval($product['miktar']);
        $birim_fiyat = floatval($product['birim_fiyat']);
        $iskonto1 = floatval($product['iskonto1'] ?? 0);
        $iskonto2 = floatval($product['iskonto2'] ?? 0);
        $iskonto3 = floatval($product['iskonto3'] ?? 0);
        $kdv_orani = floatval($product['kdv_orani']);
        $toplam = floatval($product['toplam']);

        // Ürünün veritabanında var olduğunu kontrol et
        $check_stmt = $conn->prepare("SELECT id FROM urun_stok WHERE id = ?");
        $check_stmt->execute([$product['urun_id']]);
        
        if (!$check_stmt->fetch()) {
            throw new Exception('Ürün ID: ' . $product['id'] . ' veritabanında bulunamadı');
        }

        // Ürünü faturaya ekle
        $stmt->execute([
            $fatura_id,
            $product['urun_id'],
            $miktar,
            $birim_fiyat,
            $iskonto1,
            $iskonto2,
            $iskonto3,
            $kdv_orani,
            $toplam
        ]);
        
        $toplam_tutar += $toplam;
        $kdv_tutari = $toplam * ($kdv_orani / 100);
        $toplam_kdv += $kdv_tutari;
    }

    // Fatura durumunu ve toplam tutarı güncelle
$durum = $is_complete ? 'urun_girildi' : 'bos';
$net_tutar = $toplam_tutar - $toplam_kdv; // Net tutar hesaplaması

$update_sql = "UPDATE alis_faturalari SET 
                durum = ?,
                toplam_tutar = ?,
                kdv_tutari = ?,
                net_tutar = ?
              WHERE id = ?";

$stmt = $conn->prepare($update_sql);
$stmt->execute([
    $durum, 
    $toplam_tutar, // Genel toplam
    $toplam_kdv,   // Toplam KDV
    $net_tutar,    // Net tutar
    $fatura_id
]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $is_complete ? 'Fatura tamamlandı' : 'Fatura kaydedildi'
    ]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Fatura kaydetme hatası: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $t) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Kritik hata: ' . $t->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Sistem hatası oluştu'
    ]);
}