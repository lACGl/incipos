<?php
/**
 * Barkod Toplu Yazdırma Sayfası
 */

// Session ve veritabanı kontrolü
if (!isset($conn)) {
    require_once '../../../session_manager.php';
    checkUserSession();
    require_once '../../../db_connection.php';
}

// Barkodun son 6 hanesini koyu yapmak için fonksiyon
function formatBarcodeWithBold($barcode) {
    if (strlen($barcode) > 6) {
        $first_part = substr($barcode, 0, -6);
        $last_part = substr($barcode, -6);
        return htmlspecialchars($first_part) . '<span class="last-digits">' . htmlspecialchars($last_part) . '</span>';
    }
    return htmlspecialchars($barcode);
}

// Debug için
error_log("print_multiple.php başlatıldı.");

// Ürün ve adet bilgilerini al
$products_data = isset($_GET['products']) ? $_GET['products'] : [];
$template = isset($_GET['template']) ? intval($_GET['template']) : intval(barcode_get_setting('etiket_sablonu', 1));

// Debug için
error_log("Ürün verileri: " . print_r($products_data, true));

// Ürün ID'lerini al
$product_ids = array_keys($products_data);
$product_ids = array_map('intval', $product_ids);

// Ürünleri kontrol et
if (empty($product_ids)) {
    echo '<div class="alert alert-danger">Yazdırılacak ürün seçilmedi!</div>';
    exit;
}

// Ürünleri getir
try {
    global $conn;
    
    // Güvenli bir şekilde ürünleri al
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $sql = "SELECT * FROM urun_stok WHERE id IN ($placeholders) AND barkod IS NOT NULL AND barkod != ''";
    
    $stmt = $conn->prepare($sql);
    foreach ($product_ids as $k => $id) {
        $stmt->bindValue($k + 1, $id);
    }
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug için
    error_log("Bulunan ürün sayısı: " . count($products));
    
    // Ürün bulunamadıysa
    if (empty($products)) {
        echo '<div class="alert alert-danger">Yazdırılacak ürün bulunamadı!</div>';
        exit;
    }
    
    // Varsayılan kod tipi
    $code_type = barcode_get_setting('default_code_type', 'qr');
    
    // Etiketleri hazırla
    $labels = [];
    foreach ($products as $product) {
        $quantity = isset($products_data[$product['id']]) ? intval($products_data[$product['id']]) : 1;
        $quantity = max(1, min(100, $quantity));
        
        // Yazdırma işlemini logla
        barcode_log_print($product['id'], $quantity);
        
        // QR kod veya barkod görüntüsünü oluştur
        $barkod_image = barcode_or_qr_image($product['barkod'], $code_type);
        
        for ($i = 0; $i < $quantity; $i++) {
            $labels[] = [
                'id' => $product['id'],
                'ad' => $product['ad'],
                'barkod' => $product['barkod'],
                'fiyat' => $product['satis_fiyati'],
                'barkod_image' => $barkod_image
            ];
        }
    }
    
    // Etiket ayarlarını al
    $etiket_genislik = barcode_get_setting('etiket_genislik', 100);
    $etiket_yukseklik = barcode_get_setting('etiket_yukseklik', 38);
    $kenar_boslugu = barcode_get_setting('kenar_boslugu', 2);
    $firma_adi = barcode_get_setting('firma_adi', 'İnci Kırtasiye');
    
    // Template sınıfını belirle
    $template_class = 'template-' . $template;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toplu Etiketler</title>
    <style>
        @page {
            size: <?= $etiket_genislik ?>mm <?= $etiket_yukseklik ?>mm;
            margin: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
        }
        
        .print-controls {
            position: fixed;
            top: 10px;
            right: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 10px;
            z-index: 1000;
        }
        
        .print-btn {
            display: inline-block;
            padding: 6px 12px;
            margin-right: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #f8f9fa;
            color: #212529;
            text-decoration: none;
            cursor: pointer;
        }
        
        .print-btn.print {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        .print-btn:hover {
            opacity: 0.9;
        }
        
        /* Kontroller */
        .controls {
            position: fixed;
            top: 10px;
            right: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            z-index: 1000;
        }
        
        .summary {
            position: fixed;
            top: 10px;
            left: 10px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            z-index: 1000;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            margin-right: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #f8f9fa;
            color: #212529;
            text-decoration: none;
            cursor: pointer;
        }
        
        .btn-print {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
        }
        
        /* Etiket stilleri */
        .labels-container {
            padding: 20px;
        }
        
        .label {
            width: <?= $etiket_genislik ?>mm;
            height: <?= $etiket_yukseklik ?>mm;
            margin: 0 auto 20px;
            background: white;
            padding: <?= $kenar_boslugu ?>mm;
            box-sizing: border-box;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            page-break-after: always;
            position: relative;
            overflow: visible;
        }
        
        .label-content {
            width: <?= $etiket_genislik - ($kenar_boslugu * 2) ?>mm;
            height: <?= $etiket_yukseklik - ($kenar_boslugu * 2) ?>mm;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding: 1mm;
            box-sizing: border-box;
        }
        
        /* Üst kısım - Ürün adı */
        .product-name {
            font-size: 10pt;
            font-weight: bold;
            text-align: center;
            padding: 0px 20px;
            line-height: 1.2;
            max-height: 2.4em; /* 2 satır için */
            overflow: hidden;
            flex-shrink: 0; /* Küçülmeyi engelle */
            
            /* 2 satırlı metin kesme */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            white-space: normal;
        }
        
        /* Alt kısım - QR kod ve fiyat yan yana */
        .bottom-section {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            margin-top: auto;
            height: 25mm; /* Sabit yükseklik */
        }
        
        /* QR Kod alanı - Sol taraf */
        .barcode-area {
            width: 45%;
            display: flex;
            justify-content: flex-end;
            align-items: center;
        }
        
        .barcode-img {
            width: 18mm;
            height: 18mm;
            border: 2px solid black;
            margin: 0;
        }
        
        /* Fiyat alanı - Sağ taraf */
        .price-section {
            width: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .price {
            font-size: 24pt;
            font-weight: bold;
            text-align: center;
            color: #000;
            margin: 0;
        }
        
        .barcode-number {
            font-size: 8pt;
            font-weight: normal;
            text-align: center;
            color: #333;
            margin-top: 1px;
            line-height: 1;
        }
        
        .barcode-number .last-digits {
            font-weight: 600;
            color: #222;
        }
        
        /* Yan yazılar - Tek satır İnci Kırtasiye */
        .side-text {
            position: absolute;
            font-size: 10pt;
            font-weight: bold;
            color: #333;
            writing-mode: vertical-lr;
            text-orientation: mixed;
            background: white;
            border-left: 1px solid black;
            border-right: 1px solid black;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-right: 2px;
            height: 150%;
        }
        
        .side-left {
            left: 0.5mm;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .side-right {
            right: 0.5mm;
            top: 50%;
            transform: translateY(-50%);
        }
        
        /* Template 2 - Yan Yerleşim */
        .template-2 .label-content {
            flex-direction: column;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .print-controls, .controls, .summary, .labels-container {
                display: none !important;
            }
            
            .label {
                margin: 0;
                box-shadow: none;
                border: none;
                page-break-after: always;
                display: block !important;
            }
            
            .label-content {
                display: flex !important;
            }
            
            .product-name {
                display: -webkit-box !important;
            }
            
            .bottom-section {
                display: flex !important;
            }
            
            .barcode-area {
                display: flex !important;
            }
            
            .price-section {
                display: flex !important;
            }
            
            .side-text {
                display: flex !important;
            }
            
            .print-labels {
                display: block !important;
            }
        }
        
        .print-labels {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Kontrol butonları -->
    <div class="controls">
        <button onclick="window.print();" class="btn btn-print">Yazdır</button>
        <a href="./?page=extensions&ext=tag_management" class="btn">Barkod Yöneticisi</a>
        <a href="./?page=extensions&ext=tag_management&action=print_batch" class="btn">Yeni Seçim</a>
    </div>
    
    <div class="summary">
        <strong>Toplam Etiket Sayısı: <?= count($labels) ?></strong>
    </div>
    
    <!-- Önizleme için etiketler -->
    <div class="labels-container">
        <?php foreach (array_slice($labels, 0, 5) as $index => $label): ?>
        <div class="label">
            <div class="label-content <?= $template_class ?>">
                <div class="product-name"><?= htmlspecialchars($label['ad']) ?></div>
                
                <div class="bottom-section">
                    <div class="barcode-area">
                        <img src="<?= $label['barkod_image'] ?>" alt="QR Kod" class="barcode-img">
                    </div>
                    
                    <div class="price-section">
                        <div class="price"><?= number_format($label['fiyat'], 2) ?> ₺</div>
                        <div class="barcode-number"><?= formatBarcodeWithBold($label['barkod']) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="side-text side-left"><?= htmlspecialchars($firma_adi) ?></div>
            <div class="side-text side-right"><?= htmlspecialchars($firma_adi) ?></div>
        </div>
        <?php if ($index >= 4 && count($labels) > 5) echo '<div style="text-align:center;margin:20px 0;">... diğer etiketler yazdırma önizlemesinde gösterilecektir ...</div>'; ?>
        <?php endforeach; ?>
    </div>
    
    <!-- Yazdırma için tüm etiketler -->
    <div class="print-labels">
        <?php foreach ($labels as $label): ?>
        <div class="label">
            <div class="label-content <?= $template_class ?>">
                <div class="product-name"><?= htmlspecialchars($label['ad']) ?></div>
                
                <div class="bottom-section">
                    <div class="barcode-area">
                        <img src="<?= $label['barkod_image'] ?>" alt="QR Kod" class="barcode-img">
                    </div>
                    
                    <div class="price-section">
                        <div class="price"><?= number_format($label['fiyat'], 2) ?> ₺</div>
                        <div class="barcode-number"><?= formatBarcodeWithBold($label['barkod']) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="side-text side-left"><?= htmlspecialchars($firma_adi) ?></div>
            <div class="side-text side-right"><?= htmlspecialchars($firma_adi) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <script>
        // Sayfa yüklendiğinde
        window.addEventListener('load', function() {
            // 2 saniye sonra yazdırma işlemi için sor
            setTimeout(function() {
                // Yazdırma sayfası açılıyor
                console.log('Etiketler yazdırılmaya hazır');
                
                // Yazdırma diyaloğunu göster
                if (confirm('<?= count($labels) ?> etiket hazır. Yazdırmak istiyor musunuz?')) {
                    window.print();
                }
            }, 1000);
        });
    </script>
</body>
</html>
<?php
} catch (PDOException $e) {
    error_log("Yazdırma hatası: " . $e->getMessage());
    echo '<div style="color:red;padding:20px;background:#fff;border:1px solid #ddd;border-radius:5px;margin:20px;">
            <h3>Hata Oluştu</h3>
            <p>Veriler alınırken bir hata oluştu. Lütfen sistem yöneticinize bildirin.</p>
            <p>Hata detayı: ' . $e->getMessage() . '</p>
          </div>';
}
?>