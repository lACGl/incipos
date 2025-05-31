<?php
/**
 * Barkod Yöneticisi AJAX İşleyici
 */

// Session ve veritabanı kontrolü - doğrudan erişim için
// Not: index.php üzerinden çağrılıyorsa bunları tekrar include etmeye gerek yok
if (!isset($conn)) {
    // Session yönetimi ve yetkisiz erişim kontrolü
    require_once '../../../session_manager.php';
    // Session kontrolü
    checkUserSession();
    // Veritabanı bağlantısı
    require_once '../../../db_connection.php';
    // Fonksiyonlar
    require_once __DIR__ . '/functions.php';
}

// İşlem türünü al
$operation = isset($_GET['op']) ? $_GET['op'] : '';

// AJAX işlemleri için varsayılan JSON yanıtı
if ($operation !== 'barcode_image') {
    header('Content-Type: application/json');
}

switch ($operation) {
    case 'barcode_image':
        // Barkod veya QR görüntüsü oluştur ve döndür
        $code = isset($_GET['code']) ? trim($_GET['code']) : '';
        $type = isset($_GET['type']) ? trim($_GET['type']) : 'qr'; // Varsayılan olarak QR kod
        $size = isset($_GET['size']) ? intval($_GET['size']) : null;
        
        if (empty($code)) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Kod değeri gerekli!']);
            exit;
        }
        
        // Content type'ı değiştir
        header('Content-Type: image/png');
        
        // Önbelleği devre dışı bırak
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // QR kod veya barkod görüntüsü oluştur
        if ($type === 'qr') {
            // QR kod için Endroid kütüphanesini kullan
            if (class_exists('Endroid\\QrCode\\QrCode')) {
                try {
                    // QR kod boyutunu ayarlardan al (varsayılan 200)
                    $qr_size = $size ?: intval(barcode_get_setting('qr_boyut', 200));
                    
                    $qrCode = new \Endroid\QrCode\QrCode($code);
                    $qrCode->setSize($qr_size);
                    $qrCode->setMargin(10);
                    
                    $writer = new \Endroid\QrCode\Writer\PngWriter();
                    $result = $writer->write($qrCode);
                    
                    echo $result->getString();
                } catch (Exception $e) {
                    // Hata durumunda Google Chart API kullan
                    $qr_size = $size ?: intval(barcode_get_setting('qr_boyut', 200));
                    $url = 'https://chart.googleapis.com/chart?cht=qr&chs=' . $qr_size . 'x' . $qr_size . '&chl=' . urlencode($code);
                    echo file_get_contents($url);
                }
            } else {
                // Kütüphane yoksa Google Chart API kullan
                $qr_size = $size ?: intval(barcode_get_setting('qr_boyut', 200));
                $url = 'https://chart.googleapis.com/chart?cht=qr&chs=' . $qr_size . 'x' . $qr_size . '&chl=' . urlencode($code);
                echo file_get_contents($url);
            }
        } else {
            // Barkod için Picqer kütüphanesini kullan
            if (class_exists('Picqer\\Barcode\\BarcodeGeneratorPNG')) {
                $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
                $height = $size ?: intval(barcode_get_setting('barkod_yukseklik', 60));
                
                // Barkod tipini belirle
                $barcode_type = $generator::TYPE_CODE_128;
                if (!empty($type)) {
                    switch (strtolower($type)) {
                        case 'ean13':
                            $barcode_type = $generator::TYPE_EAN_13;
                            break;
                        case 'ean8':
                            $barcode_type = $generator::TYPE_EAN_8;
                            break;
                        case 'code39':
                            $barcode_type = $generator::TYPE_CODE_39;
                            break;
                        case 'code39e':
                            $barcode_type = $generator::TYPE_CODE_39E;
                            break;
                        case 'code93':
                            $barcode_type = $generator::TYPE_CODE_93;
                            break;
                        case 'upca':
                        case 'upc':
                            $barcode_type = $generator::TYPE_UPC_A;
                            break;
                        case 'upce':
                            $barcode_type = $generator::TYPE_UPC_E;
                            break;
                    }
                }
                
                try {
                    echo $generator->getBarcode($code, $barcode_type, 2, $height);
                } catch (Exception $e) {
                    // Hata durumunda varsayılan Code128 ile dene
                    try {
                        echo $generator->getBarcode($code, $generator::TYPE_CODE_128, 2, $height);
                    } catch (Exception $e) {
                        // Basit bir barkod görseli oluştur
                        $image = imagecreate(300, $height);
                        $white = imagecolorallocate($image, 255, 255, 255);
                        $black = imagecolorallocate($image, 0, 0, 0);
                        imagefilledrectangle($image, 0, 0, 300, $height, $white);
                        imagestring($image, 5, 10, ($height/2)-10, $code, $black);
                        imagepng($image);
                        imagedestroy($image);
                    }
                }
            } else {
                // Kütüphane yoksa basit bir barkod görseli oluştur
                $height = $size ?: intval(barcode_get_setting('barkod_yukseklik', 60));
                $image = imagecreate(300, $height);
                $white = imagecolorallocate($image, 255, 255, 255);
                $black = imagecolorallocate($image, 0, 0, 0);
                imagefilledrectangle($image, 0, 0, 300, $height, $white);
                imagestring($image, 5, 10, ($height/2)-10, $code, $black);
                imagepng($image);
                imagedestroy($image);
            }
        }
        exit; // Görüntü çıktısı oluşturulduktan sonra çık
        break;
        
    case 'get_product':
        // Ürün bilgilerini getir
        $urun_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($urun_id <= 0) {
            echo json_encode(['error' => 'Geçersiz ürün ID!']);
            exit;
        }
        
        $urun = barcode_get_product($urun_id);
        
        if ($urun) {
            echo json_encode(['success' => true, 'product' => $urun]);
        } else {
            echo json_encode(['error' => 'Ürün bulunamadı!']);
        }
        break;
        
    case 'save_barcode':
        // Barkod kaydet
        $urun_id = isset($_POST['urun_id']) ? intval($_POST['urun_id']) : 0;
        $barcode = isset($_POST['barcode']) ? trim($_POST['barcode']) : '';
        $code_type = isset($_POST['code_type']) ? trim($_POST['code_type']) : 'qr'; // Varsayılan olarak QR
        
        if ($urun_id <= 0) {
            echo json_encode(['error' => 'Geçersiz ürün ID!']);
            exit;
        }
        
        $result = barcode_save($urun_id, $barcode, $code_type);
        
        if ($result) {
            echo json_encode(['success' => true, 'barcode' => $result]);
        } else {
            echo json_encode(['error' => 'Kod kaydedilemedi!']);
        }
        break;
        
    case 'update_setting':
        // Tek bir ayarı güncelle
        $key = isset($_POST['key']) ? trim($_POST['key']) : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';
        
        if (empty($key)) {
            echo json_encode(['error' => 'Ayar anahtarı gerekli!']);
            exit;
        }
        
        $result = barcode_update_setting($key, $value);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Ayar güncellendi']);
        } else {
            echo json_encode(['error' => 'Ayar güncellenemedi!']);
        }
        break;
        
    case 'get_setting':
        // Ayar değerini getir
        $key = isset($_GET['key']) ? trim($_GET['key']) : '';
        $default = isset($_GET['default']) ? $_GET['default'] : null;
        
        if (empty($key)) {
            echo json_encode(['error' => 'Ayar anahtarı gerekli!']);
            exit;
        }
        
        $value = barcode_get_setting($key, $default);
        echo json_encode(['success' => true, 'value' => $value]);
        break;
        
    case 'preview_label':
        // Etiket önizleme için HTML oluştur
        $urun_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $etiket_genislik = isset($_GET['width']) ? floatval($_GET['width']) : null;
        $etiket_yukseklik = isset($_GET['height']) ? floatval($_GET['height']) : null;
        $kenar_boslugu = isset($_GET['margin']) ? floatval($_GET['margin']) : null;
        $firma_adi = isset($_GET['company']) ? trim($_GET['company']) : null;
        $code_type = isset($_GET['code_type']) ? trim($_GET['code_type']) : null;
        $template = isset($_GET['template']) ? intval($_GET['template']) : null;
        
        if ($urun_id <= 0) {
            echo json_encode(['error' => 'Geçersiz ürün ID!']);
            exit;
        }
        
        $urun = barcode_get_product($urun_id);
        
        if (!$urun) {
            echo json_encode(['error' => 'Ürün bulunamadı!']);
            exit;
        }
        
        // Değerler belirtilmemişse ayarlardan al
        if ($etiket_genislik === null) $etiket_genislik = barcode_get_setting('etiket_genislik', 100);
        if ($etiket_yukseklik === null) $etiket_yukseklik = barcode_get_setting('etiket_yukseklik', 38);
        if ($kenar_boslugu === null) $kenar_boslugu = barcode_get_setting('kenar_boslugu', 2);
        if ($firma_adi === null) $firma_adi = barcode_get_setting('firma_adi', 'İnci Kırtasiye');
        if ($code_type === null) $code_type = barcode_get_setting('default_code_type', 'qr');
        if ($template === null) $template = barcode_get_setting('etiket_sablonu', 1);
        
        // Barkod veya QR kod görüntüsü oluştur
        $code_image = './?page=extensions&ext=tag_management&action=ajax&op=barcode_image&code=' . 
                    urlencode($urun['barkod']) . 
                    '&type=' . $code_type;
        
        // Son 6 hanesi koyu olacak şekilde barkod numarasını formatlama
        $formatted_barcode = formatBarcodeWithBold($urun['barkod']);
        
        // Template sınıfını belirle
        $template_class = 'template-' . $template;
        
        // Etiket HTML'sini oluştur
        $html = '
        <div class="label-content ' . $template_class . '">
            <div class="product-name">' . htmlspecialchars($urun['ad']) . '</div>
            
            <div class="bottom-section">
                <div class="barcode-area">
                    <img src="' . $code_image . '" alt="Kod" class="barcode-img">
                </div>
                
                <div class="price-section">
                    <div class="price">' . number_format($urun['satis_fiyati'], 2) . ' ₺</div>
                    <div class="barcode-number">' . $formatted_barcode . '</div>
                </div>
            </div>
        </div>
        
        <div class="side-text side-left">' . htmlspecialchars($firma_adi) . '</div>
        <div class="side-text side-right">' . htmlspecialchars($firma_adi) . '</div>';
        
        // Stil değerlerini içeren nesne
        $style = [
            'width' => $etiket_genislik . 'mm',
            'height' => $etiket_yukseklik . 'mm',
            'padding' => $kenar_boslugu . 'mm',
            'contentWidth' => ($etiket_genislik - ($kenar_boslugu * 2)) . 'mm',
            'contentHeight' => ($etiket_yukseklik - ($kenar_boslugu * 2)) . 'mm'
        ];
        
        echo json_encode([
            'success' => true, 
            'html' => $html,
            'style' => $style,
            'dimensions' => $etiket_genislik . 'mm × ' . $etiket_yukseklik . 'mm',
            'padding' => $kenar_boslugu . 'mm'
        ]);
        break;
        
    case 'bulk_generate':
        // Toplu barkod oluşturma
        $count = barcode_generate_bulk();
        
        if ($count > 0) {
            echo json_encode(['success' => true, 'count' => $count, 'message' => $count . ' ürün için barkod oluşturuldu.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Barkod oluşturulacak ürün bulunamadı veya işlem sırasında hata oluştu.']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Geçersiz işlem!']);
        break;
}

// Barkodun son 6 hanesini koyu yapmak için fonksiyon
if (!function_exists('formatBarcodeWithBold')) {
    function formatBarcodeWithBold($barcode) {
        if (strlen($barcode) > 6) {
            $first_part = substr($barcode, 0, -6);
            $last_part = substr($barcode, -6);
            return htmlspecialchars($first_part) . '<span class="last-digits">' . htmlspecialchars($last_part) . '</span>';
        }
        return htmlspecialchars($barcode);
    }
}

// Görüntü çıktısı olmayan işlemler için sonlandır
exit;