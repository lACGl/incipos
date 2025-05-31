<?php
require_once '../session_manager.php'; // Otomatik eklendi
secure_session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

// Yetki kontrolü
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim']));
}

// Fatura ID kontrolü
if (!isset($_POST['fatura_id']) || !is_numeric($_POST['fatura_id'])) {
    die(json_encode(['success' => false, 'message' => 'Geçersiz fatura ID']));
}

$fatura_id = $_POST['fatura_id'];

// Dosya yüklendi mi kontrolü
if (!isset($_FILES['html_file']) || $_FILES['html_file']['error'] != 0) {
    die(json_encode(['success' => false, 'message' => 'Dosya yüklenirken bir hata oluştu']));
}

try {
    // Faturanın durumunu kontrol et
    $stmt = $conn->prepare("SELECT durum FROM alis_faturalari WHERE id = ?");
    $stmt->execute([$fatura_id]);
    $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$fatura) {
        throw new Exception('Fatura bulunamadı');
    }
    
    if ($fatura['durum'] !== 'bos') {
        throw new Exception('Bu faturaya ürün eklenmiş, dosyadan içe aktarmak için yeni bir fatura oluşturun');
    }
    
    // Geçici dosyayı oku
    $htmlContent = file_get_contents($_FILES['html_file']['tmp_name']);
    
    // DOMDocument ile HTML içeriğini işle
    $doc = new DOMDocument();
    @$doc->loadHTML($htmlContent);
    
    // Tablo içindeki satırları bul - genellikle ürün satırları bir tabloda bulunur
    $tables = $doc->getElementsByTagName('table');
    
    $products = [];
    
    // HTML yapısını logla
    error_log("HTML'de bulunan tablo sayısı: " . $tables->length);
    
    // HTML yapısına göre ürün bilgilerini çıkar
    foreach ($tables as $tableIndex => $table) {
        $rows = $table->getElementsByTagName('tr');
        error_log("Tablo $tableIndex: " . $rows->length . " satır bulundu");
        
        foreach ($rows as $rowIndex => $row) {
            $cells = $row->getElementsByTagName('td');
            
            if ($cells->length > 0) {
                error_log("Satır $rowIndex: " . $cells->length . " hücre içeriyor");
                
                // Hücre içeriklerini kaydet
                $cellContents = [];
                for ($i = 0; $i < $cells->length; $i++) {
                    $cellContents[$i] = trim($cells->item($i)->nodeValue);
                    error_log("Hücre $i: " . $cellContents[$i]);
                }
                
                // Ürün satırı olduğunu kontrol et (genellikle ilk veya ikinci hücrede sayısal bir değer olur - sıra no)
                if (isset($cellContents[0]) && (is_numeric($cellContents[0]) || preg_match('/^\d+$/', $cellContents[0]))) {
                    // Ürün bilgilerini al
                    $barkod = null;
                    $urun_adi = null;
                    $miktar = null;
                    $birim_fiyat = null;
                    $kdv_orani = null;
                    
                    // Barkod için kontrol - genellikle 1. veya 2. hücrede (0 veya 1 indexli)
                    if (isset($cellContents[1]) && preg_match('/^\d{8,13}$/', $cellContents[1])) {
                        $barkod = $cellContents[1];
                    }
                    
                    // Eğer barkod bulunamadıysa, tüm hücrelerde arama yap
                    if (!$barkod) {
                        foreach ($cellContents as $content) {
                            if (preg_match('/^\d{8,13}$/', $content)) {
                                $barkod = $content;
                                break;
                            }
                        }
                    }
                    
                    // Ürün adı genellikle 2. hücrede
                    $urun_adi = isset($cellContents[2]) ? $cellContents[2] : null;
                    
                    // Miktar genellikle 3. veya 4. hücrede ve "1,0 Adet" formatında
                    for ($i = 0; $i < $cells->length; $i++) {
                        $content = $cellContents[$i];
                        if (preg_match('/^([\d\.,]+)\s*Adet/i', $content, $matches)) {
                            $miktar = str_replace(',', '.', $matches[1]);
                            break;
                        }
                    }
                    
                    // Birim fiyat genellikle 4. veya 5. hücrede ve "360,00 TL" formatında
                    for ($i = 0; $i < $cells->length; $i++) {
                        $content = $cellContents[$i];
                        if (preg_match('/^([\d\.,]+)\s*TL/i', $content, $matches)) {
                            $birim_fiyat = str_replace(',', '.', $matches[1]);
                            break;
                        }
                    }
                    
                    // KDV oranı genellikle 9. veya 10. hücrede ve "%20,00" formatında
                    for ($i = 0; $i < $cells->length; $i++) {
                        $content = $cellContents[$i];
                        if (preg_match('/^%?([\d\.,]+)/', $content, $matches)) {
                            $potentialKdv = str_replace(',', '.', $matches[1]);
                            // KDV oranı makul bir aralıkta olmalı (0-25)
                            if ($potentialKdv >= 0 && $potentialKdv <= 25) {
                                $kdv_orani = $potentialKdv;
                                break;
                            }
                        }
                    }
                    
                    // KDV oranı bulunamadıysa farklı bir formatla tekrar deneyelim
                    if (!$kdv_orani) {
                        for ($i = 0; $i < $cells->length; $i++) {
                            $content = $cellContents[$i];
                            if (stripos($content, 'kdv') !== false && preg_match('/(\d+)/', $content, $matches)) {
                                $kdv_orani = $matches[1];
                                break;
                            }
                        }
                    }
                    
                    // Varsayılan KDV oranını 18% olarak ayarla (eğer bulunamadıysa)
                    if (!$kdv_orani) {
                        $kdv_orani = 18;
                    }
                    
                    error_log("Ürün verisi çıkarıldı: Barkod=$barkod, Ürün=$urun_adi, Miktar=$miktar, Fiyat=$birim_fiyat, KDV=$kdv_orani");
                    
                    // Eğer ürün adı ve birim fiyat bulunduysa
                    if ($urun_adi && $birim_fiyat) {
                        // Miktar bulunamadıysa varsayılan olarak 1 kullan
                        if (!$miktar) {
                            $miktar = 1;
                            error_log("Miktar bulunamadı, varsayılan olarak 1 kullanılıyor");
                        }
                        
                        // Veritabanında ürünü bul
                        if ($barkod) {
                            $stmt = $conn->prepare("SELECT id FROM urun_stok WHERE barkod = ? AND durum = 'aktif'");
                            $stmt->execute([$barkod]);
                        } else {
                            // Barkod yoksa ürün adına göre ara
                            $stmt = $conn->prepare("SELECT id FROM urun_stok WHERE ad LIKE ? AND durum = 'aktif' LIMIT 1");
                            $search_term = '%' . $urun_adi . '%';
                            $stmt->execute([$search_term]);
                        }
                        
                        $urun = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($urun) {
                            $products[] = [
                                'urun_id' => $urun['id'],
                                'barkod' => $barkod,
                                'ad' => $urun_adi,
                                'miktar' => floatval($miktar),
                                'birim_fiyat' => floatval($birim_fiyat),
                                'kdv_orani' => floatval($kdv_orani),
                                'toplam' => floatval($miktar) * floatval($birim_fiyat)
                            ];
                        } else {
                            error_log("Ürün veritabanında bulunamadı: $urun_adi, Barkod: $barkod");
                        }
                    }
                }
            }
        }
    }
    
    // Hiç ürün bulunamadıysa hata ver
    if (empty($products)) {
        throw new Exception('Dosyada ürün bilgisi bulunamadı. Lütfen dosyayı kontrol edin.');
    }
    
    // Veritabanında işlemi başlat
    $conn->beginTransaction();
    
    // Mevcut ürünleri temizle
    $stmt = $conn->prepare("DELETE FROM alis_fatura_detay WHERE fatura_id = ?");
    $stmt->execute([$fatura_id]);
    
    // Toplam değerler
    $toplam_tutar = 0;
    $toplam_kdv = 0;
    
    // Yeni ürünleri ekle
    $stmt = $conn->prepare("
        INSERT INTO alis_fatura_detay (
            fatura_id, urun_id, miktar, birim_fiyat,
            iskonto1, iskonto2, iskonto3, kdv_orani, toplam_tutar
        ) VALUES (?, ?, ?, ?, 0, 0, 0, ?, ?)
    ");
    
    foreach ($products as $product) {
        $tutar = $product['miktar'] * $product['birim_fiyat'];
        $kdv_tutari = $tutar * ($product['kdv_orani'] / 100);
        
        $toplam_tutar += $tutar;
        $toplam_kdv += $kdv_tutari;
        
        $stmt->execute([
            $fatura_id,
            $product['urun_id'],
            $product['miktar'],
            $product['birim_fiyat'],
            $product['kdv_orani'],
            $tutar
        ]);
    }
    
    // Fatura durumunu güncelleme - durum 'bos' olarak kalacak (Yeni Fatura)
    $net_tutar = $toplam_tutar - $toplam_kdv;
    
    $stmt = $conn->prepare("
        UPDATE alis_faturalari 
        SET durum = 'bos',
            toplam_tutar = ?,
            kdv_tutari = ?,
            net_tutar = ?
        WHERE id = ?
    ");
    $stmt->execute([$toplam_tutar, $toplam_kdv, $net_tutar, $fatura_id]);
    
    // İşlemi kaydet
    $conn->commit();
    
    // Başarılı sonuç döndür
    echo json_encode([
        'success' => true,
        'message' => count($products) . ' ürün başarıyla içe aktarıldı. Fatura "Yeni Fatura" durumunda bırakıldı. Ürünleri gözden geçirip gerekli düzenlemeleri yapabilirsiniz.',
        'products' => $products
    ]);
    
} catch (Exception $e) {
    // Hata durumunda rollback
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("HTML içe aktarma hatası: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}