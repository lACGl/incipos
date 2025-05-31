<?php
/**
 * Barkod Tekli Yazdırma Sayfası
 */

// Session ve veritabanı kontrolü
if (!isset($conn)) {
    require_once '../../../session_manager.php';
    checkUserSession();
    require_once '../../../db_connection.php';
}

// Ürün ID'sini al
$urun_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$adet = isset($_GET['adet']) ? max(1, min(100, intval($_GET['adet']))) : 1;
$template = isset($_GET['template']) ? intval($_GET['template']) : intval(barcode_get_setting('etiket_sablonu', 1));

// Geçerli bir ID değilse
if ($urun_id <= 0) {
    echo '<div class="alert alert-danger">Geçersiz ürün ID\'si!</div>';
    exit;
}

// Ürün bilgilerini getir
$urun = barcode_get_product($urun_id);

// Ürün bulunamadıysa
if (!$urun) {
    echo '<div class="alert alert-danger">Ürün bulunamadı!</div>';
    exit;
}

// Barkodu yoksa
if (empty($urun['barkod'])) {
    echo '<div class="alert alert-danger">Bu ürünün tanımlanmış bir barkodu bulunmamaktadır.</div>';
    exit;
}

// Yazdırma işlemini logla
barcode_log_print($urun_id, $adet);

// QR kod veya barkod görüntüsünü oluştur
$code_type = barcode_get_setting('default_code_type', 'qr'); // Varsayılan olarak QR kod
$barkod_image = barcode_or_qr_image($urun['barkod'], $code_type);

// Etiket ayarlarını al
$etiket_genislik = barcode_get_setting('etiket_genislik', 100);
$etiket_yukseklik = barcode_get_setting('etiket_yukseklik', 38);
$kenar_boslugu = barcode_get_setting('kenar_boslugu', 2);
$firma_adi = barcode_get_setting('firma_adi', 'İnci Kırtasiye');

// Template sınıfını belirle
$template_class = 'template-' . $template;

// HTML çıktısı
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Etiket - <?= htmlspecialchars($urun['ad']) ?></title>
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
        
        .preview {
            max-width: 600px;
            margin: 20px auto;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .preview-title {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .label {
            width: <?= $etiket_genislik ?>mm;
            height: <?= $etiket_yukseklik ?>mm;
            margin: 20px auto;
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
            padding: 0px 10px;
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
        
        /* Template 2 - Yatay Yerleşim */
        .template-2 .label-content {
            flex-direction: row;
            flex-wrap: wrap;
        }
        
        .template-2 .product-name {
            width: 100%;
        }
        
        .template-2 .barcode-area {
            width: 60%;
            text-align: left;
            padding-left: 5mm;
        }
        
        .template-2 .price-area {
            width: 40%;
            text-align: right;
            padding-right: 5mm;
            align-self: center;
        }
        
        @media print {
            body {
                background: white;
            }
            
            .print-controls, .preview {
                display: none;
            }
            
            .label {
                margin: 0;
                box-shadow: none;
                border: none;
                page-break-after: always;
            }
        }
    </style>
</head>
<body class="print-page">
    <div class="print-controls">
        <a href="javascript:window.print();" class="print-btn print">Yazdır</a>
        <a href="./?page=extensions&ext=tag_management" class="print-btn">Geri Dön</a>
    </div>

    <!-- Önizleme alanı -->
    <div class="preview">
        <div class="preview-title">
            <h2>Etiket Önizleme</h2>
            <p>Toplam <?= $adet ?> adet etiket yazdırılacak.</p>
        </div>
        
        <!-- Önizleme için etiket -->
        <div class="label">
            <div class="label-content <?= $template_class ?>">
                <div class="product-name"><?= htmlspecialchars($urun['ad']) ?></div>
                
                <div class="bottom-section">
                    <div class="barcode-area">
                        <img src="<?= $barkod_image ?>" alt="QR Kod" class="barcode-img">
                    </div>
                    
                    <div class="price-section">
                        <div class="price"><?= number_format($urun['satis_fiyati'], 2) ?> ₺</div>
                        <div class="barcode-number"><?= formatBarcodeWithBold($urun['barkod']) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="side-text side-left"><?= htmlspecialchars($firma_adi) ?></div>
            <div class="side-text side-right"><?= htmlspecialchars($firma_adi) ?></div>
        </div>
    </div>

    <?php for ($i = 0; $i < $adet; $i++): ?>
    <div class="label">
        <div class="label-content <?= $template_class ?>">
            <div class="product-name"><?= htmlspecialchars($urun['ad']) ?></div>
            
            <div class="bottom-section">
                <div class="barcode-area">
                    <img src="<?= $barkod_image ?>" alt="QR Kod" class="barcode-img">
                </div>
                
                <div class="price-section">
                    <div class="price"><?= number_format($urun['satis_fiyati'], 2) ?> ₺</div>
                    <div class="barcode-number"><?= formatBarcodeWithBold($urun['barkod']) ?></div>
                </div>
            </div>
        </div>
        
        <div class="side-text side-left"><?= htmlspecialchars($firma_adi) ?></div>
        <div class="side-text side-right"><?= htmlspecialchars($firma_adi) ?></div>
    </div>
    <?php endfor; ?>

    <script>
        // URL parametrelerini al
        function getUrlParams() {
            var params = {};
            window.location.search.substring(1).split('&').forEach(function(pair) {
                var parts = pair.split('=');
                params[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1] || '');
            });
            return params;
        }
        
        // Otomatik yazdırma
        window.onload = function() {
            var params = getUrlParams();
            // "print=auto" parametresi varsa veya mobil cihaz değilse
            if (params.print === 'auto' || (window.innerWidth > 768)) {
                setTimeout(function() {
                    if (confirm('Etiketleri şimdi yazdırmak istiyor musunuz?')) {
                        window.print();
                    }
                }, 500);
            }
        };
    </script>
</body>
</html>