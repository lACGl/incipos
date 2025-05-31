<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

// Dosya yükleme kontrolü
if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] != 0) {
    die(json_encode(['success' => false, 'message' => 'Dosya yüklenemedi veya yükleme hatası']));
}

try {
    // Dosya detaylarını al
    $file = $_FILES['import_file'];
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    // Dosya Excel mi kontrol et
    if (!in_array($extension, ['xlsx', 'xls'])) {
        die(json_encode(['success' => false, 'message' => 'Sadece Excel dosyaları kabul edilir']));
    }
    
    // Geçici dosya
    $tempFile = $file['tmp_name'];
    
    // PhpSpreadsheet kütüphanesini yükle
    require_once '../vendor/autoload.php';
    
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($tempFile);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($tempFile);
    
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    // Başlık satırını kaldır
    $headers = array_shift($rows);
    
    // Sayaçlar
    $imported = 0;
    $skipped = 0;
    $errors = [];
    $stockProducts = []; // Stok bilgisi olan ürünler
    
    // İşlem başlat
    $conn->beginTransaction();
    
    foreach ($rows as $row) {
        // Excel sütunlarını veritabanı alanlarına eşleştir
        $productData = [
            'kod' => $row[0] ?? '',
            'barkod' => $row[1] ?? '',
            'ad' => $row[2] ?? '',
            'web_id' => $row[3] ?? null,
            'alis_fiyati' => floatval($row[4] ?? 0),
            'satis_fiyati' => floatval($row[5] ?? 0),
            'stok_miktari' => floatval($row[6] ?? 0),
            'kdv_orani' => floatval($row[7] ?? 0),
            'yil' => intval($row[8] ?? date('Y')),
            'resim_yolu' => $row[9] ?? null,
            'departman' => $row[10] ?? '',
            'birim' => $row[11] ?? '',
            'ana_grup' => $row[12] ?? '',
            'alt_grup' => $row[13] ?? '',
            'durum' => strtolower($row[14] ?? '') === 'pasif' ? 'pasif' : 'aktif'
        ];
        
        // Zorunlu alanları doğrula
        if (empty($productData['barkod']) || empty($productData['ad'])) {
            $skipped++;
            $errors[] = "Satır atlandı: Zorunlu alanlar eksik (barkod veya isim) - " . ($productData['ad'] ?: 'İsimsiz ürün');
            continue;
        }
        
        // İlişkisel verileri işle (departman, birim, gruplar)
        $productData['departman_id'] = getOrCreateRelation($conn, 'departmanlar', $productData['departman']);
        $productData['birim_id'] = getOrCreateRelation($conn, 'birimler', $productData['birim']);
        $productData['ana_grup_id'] = getOrCreateRelation($conn, 'ana_gruplar', $productData['ana_grup']);
        
        // Alt grup ana gruba bağlı olduğu için özel işlem gerekiyor
        if (!empty($productData['alt_grup']) && !empty($productData['ana_grup_id'])) {
            $productData['alt_grup_id'] = getOrCreateAltGrup($conn, $productData['alt_grup'], $productData['ana_grup_id']);
        } else {
            $productData['alt_grup_id'] = null;
        }
        
        // Ürün var mı kontrol et
        $stmt = $conn->prepare("SELECT id FROM urun_stok WHERE barkod = :barkod");
        $stmt->bindParam(':barkod', $productData['barkod']);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Mevcut ürünü güncelle
            $productId = $stmt->fetch(PDO::FETCH_COLUMN);
            $result = updateExistingProduct($conn, $productId, $productData);
        } else {
            // Yeni ürün ekle
            $result = insertNewProduct($conn, $productData);
            $productId = $result['product_id'] ?? null;
        }
        
        if ($result['success']) {
            $imported++;
            
            // Stok bilgisi varsa listeye ekle
            if ($productData['stok_miktari'] > 0 && $productId) {
                $stockProducts[] = [
                    'id' => $productId,
                    'ad' => $productData['ad'],
                    'barkod' => $productData['barkod'],
                    'miktar' => $productData['stok_miktari']
                ];
            }
        } else {
            $skipped++;
            $errors[] = $result['message'];
        }
    }
    
    // İşlemi tamamla
    $conn->commit();
    
    // Stok bilgisi olan ürün var mı?
    $hasStockProducts = count($stockProducts) > 0;
    
    echo json_encode([
        'success' => true,
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors,
        'has_stock_products' => $hasStockProducts,
        'stock_products' => $stockProducts
    ]);
    
} catch (Exception $e) {
    // Hata durumunda geri al
    if ($conn && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'İçe aktarma başarısız: ' . $e->getMessage()
    ]);
}

/**
 * İlişkisel veri için ID al veya yeni kayıt oluştur
 */
function getOrCreateRelation($conn, $table, $name) {
    if (empty($name)) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT id FROM {$table} WHERE ad = :ad");
    $stmt->bindParam(':ad', $name);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_COLUMN);
    } else {
        $stmt = $conn->prepare("INSERT INTO {$table} (ad) VALUES (:ad)");
        $stmt->bindParam(':ad', $name);
        $stmt->execute();
        return $conn->lastInsertId();
    }
}

/**
 * Alt grup için ID al veya oluştur (ana gruba bağlı)
 */
function getOrCreateAltGrup($conn, $name, $anaGrupId) {
    if (empty($name) || empty($anaGrupId)) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT id FROM alt_gruplar WHERE ad = :ad AND ana_grup_id = :ana_grup_id");
    $stmt->bindParam(':ad', $name);
    $stmt->bindParam(':ana_grup_id', $anaGrupId);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_COLUMN);
    } else {
        $stmt = $conn->prepare("INSERT INTO alt_gruplar (ad, ana_grup_id) VALUES (:ad, :ana_grup_id)");
        $stmt->bindParam(':ad', $name);
        $stmt->bindParam(':ana_grup_id', $anaGrupId);
        $stmt->execute();
        return $conn->lastInsertId();
    }
}

/**
 * Mevcut ürünü güncelle
 */
function updateExistingProduct($conn, $productId, $data) {
    try {
        $sql = "UPDATE urun_stok SET 
                kod = :kod,
                ad = :ad,
                web_id = :web_id,
                alis_fiyati = :alis_fiyati,
                satis_fiyati = :satis_fiyati,
                kdv_orani = :kdv_orani,
                departman_id = :departman_id,
                birim_id = :birim_id,
                ana_grup_id = :ana_grup_id,
                alt_grup_id = :alt_grup_id,
                durum = :durum,
                yil = :yil,
                resim_yolu = :resim_yolu
                WHERE id = :id";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':id', $productId);
        $stmt->bindParam(':kod', $data['kod']);
        $stmt->bindParam(':ad', $data['ad']);
        $stmt->bindParam(':web_id', $data['web_id']);
        $stmt->bindParam(':alis_fiyati', $data['alis_fiyati']);
        $stmt->bindParam(':satis_fiyati', $data['satis_fiyati']);
        $stmt->bindParam(':kdv_orani', $data['kdv_orani']);
        $stmt->bindParam(':departman_id', $data['departman_id']);
        $stmt->bindParam(':birim_id', $data['birim_id']);
        $stmt->bindParam(':ana_grup_id', $data['ana_grup_id']);
        $stmt->bindParam(':alt_grup_id', $data['alt_grup_id']);
        $stmt->bindParam(':durum', $data['durum']);
        $stmt->bindParam(':yil', $data['yil']);
        $stmt->bindParam(':resim_yolu', $data['resim_yolu']);
        $stmt->execute();
        
        return ['success' => true];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Ürün güncellenirken hata oluştu ({$data['ad']}): " . $e->getMessage()
        ];
    }
}

/**
 * Yeni ürün ekle
 */
function insertNewProduct($conn, $data) {
    try {
        $sql = "INSERT INTO urun_stok (
                kod, barkod, ad, web_id, alis_fiyati, satis_fiyati, kdv_orani,
                departman_id, birim_id, ana_grup_id, alt_grup_id, durum, kayit_tarihi,
                yil, resim_yolu)
                VALUES (
                :kod, :barkod, :ad, :web_id, :alis_fiyati, :satis_fiyati, :kdv_orani,
                :departman_id, :birim_id, :ana_grup_id, :alt_grup_id, :durum, NOW(),
                :yil, :resim_yolu)";
                
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':kod', $data['kod']);
        $stmt->bindParam(':barkod', $data['barkod']);
        $stmt->bindParam(':ad', $data['ad']);
        $stmt->bindParam(':web_id', $data['web_id']);
        $stmt->bindParam(':alis_fiyati', $data['alis_fiyati']);
        $stmt->bindParam(':satis_fiyati', $data['satis_fiyati']);
        $stmt->bindParam(':kdv_orani', $data['kdv_orani']);
        $stmt->bindParam(':departman_id', $data['departman_id']);
        $stmt->bindParam(':birim_id', $data['birim_id']);
        $stmt->bindParam(':ana_grup_id', $data['ana_grup_id']);
        $stmt->bindParam(':alt_grup_id', $data['alt_grup_id']);
        $stmt->bindParam(':durum', $data['durum']);
        $stmt->bindParam(':yil', $data['yil']);
        $stmt->bindParam(':resim_yolu', $data['resim_yolu']);
        $stmt->execute();
        
        $productId = $conn->lastInsertId();
        
        return [
            'success' => true,
            'product_id' => $productId
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "Ürün eklenirken hata oluştu ({$data['ad']}): " . $e->getMessage()
        ];
    }
}